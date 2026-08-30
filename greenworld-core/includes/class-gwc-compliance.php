<?php
/**
 * Product Compliance Engine.
 *
 * Phase 1 foundation for the Green World platform. Before any AI is allowed
 * near the catalogue, two things must be true: every product must carry
 * structured, approved information, and the catalogue must be free of
 * unsupported medical claims (for example "anti-cancer", "for AIDS patients",
 * "prevents the development of tumours", "cures ...").
 *
 * This module provides:
 *   1. A structured compliance record per product (ingredients, dosage,
 *      directions, warnings, approved claims, prohibited claims, regulatory
 *      status, and so on) editable in a metabox on the product editor.
 *   2. A prohibited-claims scanner and admin report (Products -> Compliance)
 *      that lists products whose title or description contain risky medical
 *      claims, and products whose compliance record is incomplete, each with an
 *      edit link.
 *   3. An optional front-end "claims guard" that neutralises a configurable set
 *      of specific high-risk claim phrases in displayed product titles and
 *      descriptions, as a stop-gap while the underlying data is cleaned.
 *
 * approved_payload() returns ONLY the approved, structured product information.
 * It is the single source the future Green World AI Engine is permitted to read
 * from - the raw marketing description (which may still contain claims) is
 * deliberately excluded.
 *
 * @package GreenWorldCore
 */

defined( 'ABSPATH' ) || exit;

final class GWC_Compliance {

	private static $instance = null;

	private const OPTION       = 'gwc_compliance';
	private const NONCE        = 'gwc_compliance_meta';
	private const META_PREFIX  = '_gw_pc_';
	private const FLAG_TRANSIENT = 'gwc_compliance_flagged';

	/**
	 * Structured, approved product fields. Key => label. Keys marked in
	 * MULTILINE render as textareas; the rest as single-line text inputs.
	 *
	 * @var array<string,string>
	 */
	private const FIELDS = array(
		'brand'             => 'Brand',
		'ingredients'       => 'Ingredients',
		'dosage'            => 'Dosage',
		'directions'        => 'Directions for use',
		'intended_use'      => 'Intended use (general wellness area)',
		'benefits'          => 'Approved wellness benefits',
		'contraindications' => 'Contraindications',
		'warnings'          => 'Warnings',
		'age_restriction'   => 'Age restriction',
		'pregnancy'         => 'Pregnancy / breastfeeding warning',
		'interactions'      => 'Medication interaction warning',
		'evidence'          => 'Evidence / source',
		'manufacturer'      => 'Manufacturer',
		'origin'            => 'Country of origin',
		'batch'             => 'Batch number',
		'expiry'            => 'Expiry',
		'reg_status'        => 'Regulatory / document status',
		'approved_claims'   => 'Approved marketing claims',
		'prohibited_claims' => 'Prohibited claims (never use)',
	);

	/** Fields rendered as multi-line textareas. */
	private const MULTILINE = array(
		'ingredients',
		'directions',
		'benefits',
		'contraindications',
		'warnings',
		'interactions',
		'evidence',
		'approved_claims',
		'prohibited_claims',
	);

	/** Fields that must be filled for a product to count as compliance-ready. */
	private const REQUIRED = array( 'ingredients', 'directions', 'warnings' );

	public static function instance(): GWC_Compliance {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// Product editor metabox.
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );

		// Product list column: compliance status badge.
		add_filter( 'manage_product_posts_columns', array( $this, 'column_head' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column_body' ), 20, 2 );

		// Admin report + settings (Products -> Compliance) and heads-up notice.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );

		// Front-end claims guard (stop-gap while source data is cleaned).
		if ( self::softener_enabled() ) {
			add_filter( 'the_title', array( $this, 'guard_title' ), 20, 2 );
			add_filter( 'woocommerce_short_description', array( $this, 'guard_html' ), 20 );
			add_filter( 'the_content', array( $this, 'guard_content' ), 20 );
		}
	}

	/* ================================================================== *
	 * Settings.
	 * ================================================================== */

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$defaults = array(
			'softener'    => 1,
			'phrase_map'  => self::default_phrase_map_raw(),
			'extra_terms' => '',
		);
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	public static function softener_enabled(): bool {
		$all = self::all();
		return ! empty( $all['softener'] );
	}

	/**
	 * Default "risky phrase | compliant replacement" map, seeded with the exact
	 * claims found on the live catalogue. Admins can extend it in settings.
	 */
	public static function default_phrase_map_raw(): string {
		$lines = array(
			'Anti-Cancer | Cellular wellness support',
			'anti cancer | cellular wellness support',
			'anticancer | cellular wellness support',
			'prevents the development of tumors | supports general wellness',
			'prevents the development of tumours | supports general wellness',
			'prevent the development of tumors | support general wellness',
			'for AIDS patients | for general immune wellness',
			'AIDS patients | people seeking immune wellness',
			'regulate female hormone and ovarian dysfunction | support women\'s hormonal wellness',
			'chronic diseases | long-term wellness',
			'chronic disease | long-term wellness',
		);
		return implode( "\n", $lines );
	}

	/**
	 * Parsed phrase map: array of array('from' => ..., 'to' => ...).
	 *
	 * @return array<int,array{from:string,to:string}>
	 */
	public static function phrase_map(): array {
		$all = self::all();
		$raw = isset( $all['phrase_map'] ) ? (string) $all['phrase_map'] : '';
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			$parts = explode( '|', $line, 2 );
			$from  = trim( $parts[0] );
			$to    = isset( $parts[1] ) ? trim( $parts[1] ) : '';
			if ( '' !== $from ) {
				$out[] = array(
					'from' => $from,
					'to'   => $to,
				);
			}
		}
		return $out;
	}

	/**
	 * Prohibited-claim lexicon used by the scanner (flags for human review).
	 * These are strong disease / treatment claim words that do not belong on a
	 * general-wellness catalogue. Admins can add more via settings.
	 *
	 * @return array<int,string>
	 */
	public static function lexicon(): array {
		$base = array(
			'cancer',
			'anti-cancer',
			'anticancer',
			'tumor',
			'tumour',
			'tumors',
			'tumours',
			'hiv',
			'aids',
			'cure',
			'cures',
			'cured',
			'curing',
			'heal disease',
			'treats',
			'treatment for',
			'diagnose',
			'diagnosis',
			'prevents disease',
			'prevents the development',
			'chronic disease',
			'chronic diseases',
			'diabetes',
			'hypertension',
			'kidney disease',
			'liver disease',
			'stroke',
			'prescription',
			'ovarian dysfunction',
		);
		$all   = self::all();
		$extra = isset( $all['extra_terms'] ) ? (string) $all['extra_terms'] : '';
		foreach ( preg_split( '/\r\n|\r|\n|,/', $extra ) as $t ) {
			$t = strtolower( trim( (string) $t ) );
			if ( '' !== $t ) {
				$base[] = $t;
			}
		}
		return array_values( array_unique( $base ) );
	}

	/* ================================================================== *
	 * Structured record + approved payload (the AI's only data source).
	 * ================================================================== */

	public static function get_field( int $product_id, string $key ): string {
		return (string) get_post_meta( $product_id, self::META_PREFIX . $key, true );
	}

	/**
	 * The approved, structured record for a product. This - and only this - is
	 * what the future AI Engine is allowed to read. The raw marketing
	 * description is intentionally excluded because it may still contain claims.
	 *
	 * @return array<string,mixed>
	 */
	public static function approved_payload( int $product_id ): array {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! method_exists( $product, 'get_id' ) ) {
			return array();
		}

		$fields = array();
		foreach ( array_keys( self::FIELDS ) as $key ) {
			$fields[ $key ] = self::get_field( $product_id, $key );
		}

		$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$scan = self::scan_product( $product_id );

		return array(
			'id'            => (int) $product->get_id(),
			'name'          => self::soften( (string) $product->get_name() ),
			'sku'           => (string) $product->get_sku(),
			'brand'         => $fields['brand'],
			'permalink'     => (string) get_permalink( $product_id ),
			'price'         => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_get_price_to_display( $product ) > 0 ? (string) wc_price( wc_get_price_to_display( $product ) ) : '' ) : '',
			'price_raw'     => (float) wc_get_price_to_display( $product ),
			'in_stock'      => (bool) $product->is_in_stock(),
			'categories'    => is_wp_error( $cats ) ? array() : array_values( (array) $cats ),
			'ingredients'   => $fields['ingredients'],
			'dosage'        => $fields['dosage'],
			'directions'    => $fields['directions'],
			'intended_use'  => $fields['intended_use'],
			'benefits'      => $fields['benefits'],
			'warnings'      => $fields['warnings'],
			'contraindications' => $fields['contraindications'],
			'age_restriction'   => $fields['age_restriction'],
			'pregnancy'         => $fields['pregnancy'],
			'interactions'      => $fields['interactions'],
			'approved_claims'   => $fields['approved_claims'],
			'prohibited_claims' => $fields['prohibited_claims'],
			'manufacturer'  => $fields['manufacturer'],
			'origin'        => $fields['origin'],
			'reg_status'    => $fields['reg_status'],
			'compliance'    => $scan['status'],
			'ai_usable'     => ( 'red' !== $scan['status'] && false === $scan['incomplete'] ),
		);
	}

	/**
	 * Approved payloads for the whole published catalogue - the controlled
	 * knowledge base the future AI Engine will retrieve from.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function catalogue_payload( int $limit = 500 ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => $limit,
				'return' => 'ids',
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			$out[] = self::approved_payload( (int) $id );
		}
		return $out;
	}

	/* ================================================================== *
	 * Scanner.
	 * ================================================================== */

	/**
	 * Scan one product for risky claim terms and record completeness.
	 *
	 * @return array{risky:array<int,string>,incomplete:bool,status:string,missing:array<int,string>}
	 */
	public static function scan_product( int $product_id ): array {
		$post = get_post( $product_id );
		$haystack = '';
		if ( $post instanceof WP_Post ) {
			$haystack = strtolower( wp_strip_all_tags( $post->post_title . ' ' . $post->post_excerpt . ' ' . $post->post_content ) );
		}

		$risky = array();
		foreach ( self::lexicon() as $term ) {
			$term = strtolower( trim( $term ) );
			if ( '' === $term ) {
				continue;
			}
			$pattern = '/(?<![a-z])' . preg_quote( $term, '/' ) . '(?![a-z])/u';
			if ( preg_match( $pattern, $haystack ) ) {
				$risky[] = $term;
			}
		}

		$missing = array();
		foreach ( self::REQUIRED as $key ) {
			if ( '' === self::get_field( $product_id, $key ) ) {
				$missing[] = $key;
			}
		}
		$incomplete = ! empty( $missing );

		if ( ! empty( $risky ) ) {
			$status = 'red';
		} elseif ( $incomplete ) {
			$status = 'amber';
		} else {
			$status = 'green';
		}

		return array(
			'risky'      => array_values( array_unique( $risky ) ),
			'incomplete' => $incomplete,
			'status'     => $status,
			'missing'    => $missing,
		);
	}

	/**
	 * Scan the whole catalogue and cache the flagged count for the admin notice.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function scan_catalogue( int $limit = 1000 ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$ids = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => $limit,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);
		$rows    = array();
		$flagged = 0;
		foreach ( (array) $ids as $id ) {
			$id   = (int) $id;
			$scan = self::scan_product( $id );
			if ( 'green' !== $scan['status'] ) {
				$flagged++;
			}
			$rows[] = array(
				'id'    => $id,
				'title' => (string) get_the_title( $id ),
				'scan'  => $scan,
			);
		}
		set_transient( self::FLAG_TRANSIENT, $flagged, 6 * HOUR_IN_SECONDS );
		return $rows;
	}

	/* ================================================================== *
	 * Front-end claims guard.
	 * ================================================================== */

	/**
	 * Replace configured risky phrases with compliant alternatives.
	 */
	public static function soften( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}
		$map = self::phrase_map();
		if ( empty( $map ) ) {
			return $text;
		}
		$from = array();
		$to   = array();
		foreach ( $map as $pair ) {
			$from[] = $pair['from'];
			$to[]   = $pair['to'];
		}
		return (string) str_ireplace( $from, $to, $text );
	}

	/**
	 * @param string $title
	 * @param int    $post_id
	 */
	public function guard_title( $title, $post_id = 0 ): string {
		$title = (string) $title;
		if ( is_admin() ) {
			return $title;
		}
		$pid = (int) $post_id;
		if ( $pid > 0 && 'product' !== get_post_type( $pid ) ) {
			return $title;
		}
		return self::soften( $title );
	}

	/**
	 * @param string $html
	 */
	public function guard_html( $html ): string {
		if ( is_admin() ) {
			return (string) $html;
		}
		return self::soften( (string) $html );
	}

	/**
	 * @param string $content
	 */
	public function guard_content( $content ): string {
		if ( is_admin() ) {
			return (string) $content;
		}
		$is_product = function_exists( 'is_product' ) && is_product();
		if ( ! $is_product && 'product' !== get_post_type() ) {
			return (string) $content;
		}
		return self::soften( (string) $content );
	}

	/* ================================================================== *
	 * Product editor metabox.
	 * ================================================================== */

	public function metabox(): void {
		add_meta_box(
			'gwc_compliance',
			__( 'Green World - Product Compliance', 'greenworld-core' ),
			array( $this, 'render_metabox' ),
			'product',
			'normal',
			'high'
		);
	}

	public function render_metabox( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$scan = self::scan_product( (int) $post->ID );

		echo '<p class="description">' . esc_html__( 'Structured, approved product information. The AI assistant will be allowed to use only these fields - never the raw marketing description. Fill these in and keep any disease/treatment claims out of the product title and description.', 'greenworld-core' ) . '</p>';

		echo '<p><strong>' . esc_html__( 'Compliance status:', 'greenworld-core' ) . '</strong> ' . wp_kses_post( self::badge_html( $scan ) ) . '</p>';
		if ( ! empty( $scan['risky'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Risky claim terms found in the title/description:', 'greenworld-core' ) . ' <code>' . esc_html( implode( ', ', $scan['risky'] ) ) . '</code>. ' . esc_html__( 'Please remove or reword these before this product is used by the AI.', 'greenworld-core' ) . '</p></div>';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::FIELDS as $key => $label ) {
			$val = self::get_field( (int) $post->ID, $key );
			$id  = 'gw_pc_' . $key;
			echo '<tr>';
			echo '<th scope="row" style="width:220px"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td>';
			if ( in_array( $key, self::MULTILINE, true ) ) {
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
			} else {
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array_keys( self::FIELDS ) as $key ) {
			$field = 'gw_pc_' . $key;
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$raw = wp_unslash( (string) $_POST[ $field ] );
			if ( in_array( $key, self::MULTILINE, true ) ) {
				$clean = sanitize_textarea_field( $raw );
			} else {
				$clean = sanitize_text_field( $raw );
			}
			if ( '' === $clean ) {
				delete_post_meta( $post_id, self::META_PREFIX . $key );
			} else {
				update_post_meta( $post_id, self::META_PREFIX . $key, $clean );
			}
		}
		delete_transient( self::FLAG_TRANSIENT );
	}

	/* ================================================================== *
	 * Product list column.
	 * ================================================================== */

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function column_head( $cols ): array {
		$new = array();
		foreach ( (array) $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['gw_compliance'] = __( 'Compliance', 'greenworld-core' );
			}
		}
		if ( ! isset( $new['gw_compliance'] ) ) {
			$new['gw_compliance'] = __( 'Compliance', 'greenworld-core' );
		}
		return $new;
	}

	/**
	 * @param string $col
	 * @param int    $post_id
	 */
	public function column_body( $col, $post_id ): void {
		if ( 'gw_compliance' !== $col ) {
			return;
		}
		echo wp_kses_post( self::badge_html( self::scan_product( (int) $post_id ) ) );
	}

	/**
	 * @param array{status:string,risky:array<int,string>,missing:array<int,string>} $scan
	 */
	public static function badge_html( array $scan ): string {
		$status = isset( $scan['status'] ) ? (string) $scan['status'] : 'green';
		if ( 'red' === $status ) {
			$color = '#b32d2e';
			$text  = __( 'Risky claims', 'greenworld-core' );
		} elseif ( 'amber' === $status ) {
			$color = '#996800';
			$text  = __( 'Incomplete', 'greenworld-core' );
		} else {
			$color = '#1a7f37';
			$text  = __( 'Ready', 'greenworld-core' );
		}
		return '<span style="display:inline-block;padding:2px 9px;border-radius:10px;background:' . esc_attr( $color ) . ';color:#fff;font-size:11px;font-weight:600;line-height:1.6">' . esc_html( $text ) . '</span>';
	}

	/* ================================================================== *
	 * Admin report + settings page (Products -> Compliance).
	 * ================================================================== */

	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Product Compliance', 'greenworld-core' ),
			__( 'Compliance', 'greenworld-core' ),
			'manage_woocommerce',
			'gwc-compliance',
			array( $this, 'render_page' )
		);
	}

	public function maybe_save_settings(): void {
		if ( ! isset( $_POST['gwc_compliance_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gwc_compliance_nonce'] ) ), 'gwc_save_compliance' ) ) {
			return;
		}
		$in    = ( isset( $_POST['gwc_c'] ) && is_array( $_POST['gwc_c'] ) ) ? wp_unslash( $_POST['gwc_c'] ) : array();
		$clean = array(
			'softener'    => empty( $in['softener'] ) ? 0 : 1,
			'phrase_map'  => isset( $in['phrase_map'] ) ? sanitize_textarea_field( (string) $in['phrase_map'] ) : '',
			'extra_terms' => isset( $in['extra_terms'] ) ? sanitize_textarea_field( (string) $in['extra_terms'] ) : '',
		);
		update_option( self::OPTION, $clean );
		delete_transient( self::FLAG_TRANSIENT );
		add_settings_error( 'gwc_compliance', 'saved', __( 'Compliance settings saved.', 'greenworld-core' ), 'updated' );
	}

	public function admin_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-product', 'product', 'dashboard' ), true ) ) {
			return;
		}
		$flagged = get_transient( self::FLAG_TRANSIENT );
		if ( false === $flagged || (int) $flagged <= 0 ) {
			return;
		}
		$url = admin_url( 'edit.php?post_type=product&page=gwc-compliance' );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Green World Compliance:', 'greenworld-core' ) . '</strong> ';
		echo esc_html( sprintf( _n( '%d product needs a compliance review (risky claim or missing information).', '%d products need a compliance review (risky claims or missing information).', (int) $flagged, 'greenworld-core' ), (int) $flagged ) );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the compliance report', 'greenworld-core' ) . '</a>.</p></div>';
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s    = self::all();
		$rows = self::scan_catalogue();

		$red   = 0;
		$amber = 0;
		foreach ( $rows as $r ) {
			if ( 'red' === $r['scan']['status'] ) {
				$red++;
			} elseif ( 'amber' === $r['scan']['status'] ) {
				$amber++;
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Product Compliance', 'greenworld-core' ) . '</h1>';
		settings_errors( 'gwc_compliance' );

		echo '<p class="description">' . esc_html__( 'Fix product claims and complete product information before enabling the AI assistant. "Risky claims" means a disease or treatment term was found in the product title or description. "Incomplete" means required approved fields (ingredients, directions, warnings) are missing.', 'greenworld-core' ) . '</p>';

		echo '<p><span style="display:inline-block;padding:3px 10px;border-radius:10px;background:#b32d2e;color:#fff;font-weight:600">' . esc_html( sprintf( __( 'Risky claims: %d', 'greenworld-core' ), $red ) ) . '</span> ';
		echo '<span style="display:inline-block;padding:3px 10px;border-radius:10px;background:#996800;color:#fff;font-weight:600">' . esc_html( sprintf( __( 'Incomplete: %d', 'greenworld-core' ), $amber ) ) . '</span> ';
		echo '<span style="display:inline-block;padding:3px 10px;border-radius:10px;background:#1a7f37;color:#fff;font-weight:600">' . esc_html( sprintf( __( 'Ready: %d', 'greenworld-core' ), count( $rows ) - $red - $amber ) ) . '</span></p>';

		// Settings form.
		echo '<h2>' . esc_html__( 'Claims guard settings', 'greenworld-core' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'gwc_save_compliance', 'gwc_compliance_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Front-end claims guard', 'greenworld-core' ) . '</th><td>';
		echo '<label><input type="checkbox" name="gwc_c[softener]" value="1" ' . checked( ! empty( $s['softener'] ), true, false ) . ' /> ' . esc_html__( 'Replace risky claim phrases with compliant wording in displayed product titles and descriptions (stop-gap while you clean the source text).', 'greenworld-core' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="gwc_pm">' . esc_html__( 'Phrase replacements', 'greenworld-core' ) . '</label></th><td>';
		echo '<textarea id="gwc_pm" name="gwc_c[phrase_map]" rows="8" class="large-text code">' . esc_textarea( (string) $s['phrase_map'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line, in the form:  risky phrase | compliant replacement', 'greenworld-core' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="gwc_et">' . esc_html__( 'Extra terms to flag', 'greenworld-core' ) . '</label></th><td>';
		echo '<textarea id="gwc_et" name="gwc_c[extra_terms]" rows="3" class="large-text code">' . esc_textarea( (string) $s['extra_terms'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Additional words/phrases the scanner should flag. Comma or newline separated.', 'greenworld-core' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save compliance settings', 'greenworld-core' ) );
		echo '</form>';

		// Report table.
		echo '<h2>' . esc_html__( 'Catalogue scan', 'greenworld-core' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Risky terms', 'greenworld-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Missing fields', 'greenworld-core' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No published products found.', 'greenworld-core' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$edit = (string) get_edit_post_link( $r['id'] );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $r['title'] ) . '</strong></td>';
			echo '<td>' . wp_kses_post( self::badge_html( $r['scan'] ) ) . '</td>';
			echo '<td>' . ( empty( $r['scan']['risky'] ) ? '&mdash;' : '<code>' . esc_html( implode( ', ', $r['scan']['risky'] ) ) . '</code>' ) . '</td>';
			echo '<td>' . ( empty( $r['scan']['missing'] ) ? '&mdash;' : esc_html( implode( ', ', $r['scan']['missing'] ) ) ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'greenworld-core' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}

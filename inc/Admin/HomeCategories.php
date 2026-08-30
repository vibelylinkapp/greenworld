<?php
declare( strict_types=1 );

namespace GreenWorld\Admin;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a shop manager hand-pick which product categories appear in the
 * homepage "Shop by Health Category" grid.
 *
 * Adds a "Show on homepage" checkbox to the Add / Edit Product Category
 * screens and an at-a-glance column on the category list. The choice is stored
 * as the term meta _gw_home_featured. When at least one category is ticked the
 * homepage shows exactly the ticked set (in Products -> Categories order);
 * with none ticked, Home::home_category_names() falls back to the automatic
 * "top categories that have products" behaviour, so the grid always renders.
 */
final class HomeCategories implements Bootable {

	/** Term-meta key holding the homepage-feature flag. */
	public const META = '_gw_home_featured';

	public function boot(): void {
		add_action( 'product_cat_add_form_fields', array( $this, 'add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_field' ) );
		add_action( 'created_product_cat', array( $this, 'save' ) );
		add_action( 'edited_product_cat', array( $this, 'save' ) );
		add_filter( 'manage_edit-product_cat_columns', array( $this, 'column' ) );
		add_filter( 'manage_product_cat_custom_column', array( $this, 'column_value' ), 10, 3 );
	}

	/**
	 * Checkbox on the "Add new category" form.
	 */
	public function add_field(): void {
		?>
		<div class="form-field term-gw-home-featured-wrap">
			<label for="gw_home_featured">
				<input type="checkbox" name="gw_home_featured" id="gw_home_featured" value="1" />
				<?php esc_html_e( 'Show on the homepage "Shop by Health Category" grid', 'greenworld' ); ?>
			</label>
			<p><?php esc_html_e( 'Tick the categories you want featured on the homepage. If you tick none, the homepage automatically shows your top categories that have products.', 'greenworld' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Checkbox on the "Edit category" form.
	 *
	 * @param \WP_Term $term Category being edited.
	 */
	public function edit_field( $term ): void {
		$term_id = isset( $term->term_id ) ? (int) $term->term_id : 0;
		$on      = ( '1' === (string) get_term_meta( $term_id, self::META, true ) );
		?>
		<tr class="form-field term-gw-home-featured-wrap">
			<th scope="row"><label for="gw_home_featured"><?php esc_html_e( 'Homepage grid', 'greenworld' ); ?></label></th>
			<td>
				<label for="gw_home_featured">
					<input type="checkbox" name="gw_home_featured" id="gw_home_featured" value="1" <?php checked( $on ); ?> />
					<?php esc_html_e( 'Show on the homepage "Shop by Health Category" grid', 'greenworld' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If you tick none, the homepage automatically shows your top categories that have products.', 'greenworld' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the flag when a category is created or edited.
	 *
	 * WooCommerce verifies the add / edit-term nonce before these hooks fire,
	 * so we only read the already-validated request here.
	 *
	 * @param int $term_id Category term ID.
	 */
	public function save( $term_id ): void {
		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$on = ( isset( $_POST['gw_home_featured'] ) && '1' === (string) wp_unslash( $_POST['gw_home_featured'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $on ) {
			update_term_meta( (int) $term_id, self::META, '1' );
		} else {
			delete_term_meta( (int) $term_id, self::META );
		}
	}

	/**
	 * Add a "Homepage" column to the category list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function column( array $columns ): array {
		$columns['gw_home_featured'] = __( 'Homepage', 'greenworld' );
		return $columns;
	}

	/**
	 * Render the "Homepage" column cell.
	 *
	 * @param string $content Column HTML.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function column_value( $content, $column, $term_id ) {
		if ( 'gw_home_featured' !== $column ) {
			return $content;
		}
		$on = ( '1' === (string) get_term_meta( (int) $term_id, self::META, true ) );
		return $on
			? '<span style="color:#1f7a3d;font-weight:600">Featured</span>'
			: '<span style="color:#c3c9c3">&mdash;</span>';
	}
}

<?php
declare( strict_types=1 );

namespace GreenWorld\Front;

use GreenWorld\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * Trust Center: the credibility architecture for a Kenyan health retailer.
 * Real business identity, sourcing, authenticity, conservative regulatory
 * language, customer protection (delivery / returns / payments / privacy),
 * team and contact. Every fact is Customizer-editable; nothing is invented.
 *
 * Rendered by template-trust-center.php and via [gw_trust_center]. The same
 * single-sourced copy also fills the footer pages (About, Shipping & Delivery,
 * Returns & Refunds, Privacy, Contact, Terms) through page-{slug}.php templates
 * that call the *_page() helpers below.
 *
 * Homepage trust band via [gw_why_trust] or TrustCenter::why_trust().
 */
final class TrustCenter implements Bootable {

	public function boot(): void {
		add_action( 'customize_register', array( $this, 'customize' ) );
		add_shortcode( 'gw_trust_center', array( __CLASS__, 'render_sc' ) );
		add_shortcode( 'gw_why_trust', array( __CLASS__, 'why_trust_sc' ) );
	}

	/* ---------------------------------------------------------------- */
	/*  Facts (confirmed defaults, all Customizer-editable)             */
	/* ---------------------------------------------------------------- */

	private static function v( string $key, string $default ): string {
		$val = trim( (string) get_theme_mod( 'gw_tc_' . $key, $default ) );
		return $val !== '' ? $val : $default;
	}

	private static function paras( string $text ): string {
		$out   = '';
		$parts = preg_split( '/\n\s*\n/', trim( $text ) );
		foreach ( (array) $parts as $p ) {
			$p = trim( (string) $p );
			if ( $p !== '' ) { $out .= '<p>' . esc_html( $p ) . '</p>'; }
		}
		return $out;
	}

	private static function about_default(): string {
		return "Green World Health Solutions is a Kenyan health and wellness retailer based in Nairobi. We supply genuine Green World brand health and wellness products to customers across Kenya, backed by clear product guidance, secure local payment options and reliable delivery.\n\nOur aim is simple: make trusted wellness products easy to buy, with none of the guesswork. When you order from us, you deal with a real Nairobi business that answers the phone.";
	}
	private static function sourcing_default(): string {
		return "Our products are Green World brand products, manufactured by the international Green World / World Food group (en.world-food.com). The group maintains a registered office in Upperhill, Nairobi, and product documentation is held at group level.\n\nWe select products from the Green World range and make them available to Kenyan customers with clear, honest product information.";
	}
	private static function authenticity_default(): string {
		return "Every item we sell is a genuine Green World brand product sourced through the group's supply chain, never counterfeit or grey-market stock. If you would ever like to verify a product's authenticity, contact us and we will help you check it.";
	}
	private static function regulatory_default(): string {
		return "Green World is an established international health-products brand, and the group's product documentation is held at its Nairobi (Upperhill) office. We provide available product documentation on request.\n\nWe do not make disease treatment or cure claims, and we do not display approval badges we cannot document. Our product information is for general wellness purposes and is not a substitute for professional medical advice, diagnosis or treatment.";
	}
	private static function returns_default(): string {
		return "Report window: contact us within 7 days of delivery, via WhatsApp or phone, with your order number.\n\nDamaged, defective or incorrect items: we replace them free of charge or issue a full refund. Please send a photo when you report the issue.\n\nSealed, unopened non-consumable items: returnable within 7 days in their original condition.\n\nOpened health or supplement products: for safety and hygiene reasons these cannot be returned unless they arrived damaged, defective or incorrect.\n\nRefunds: processed to your original payment method (M-Pesa or bank transfer) within 3 to 5 business days of approval.";
	}
	private static function privacy_default(): string {
		return "We collect only what we need to fulfil your orders and answer your enquiries. Because our free wellness consultation asks about your health situation, that information is treated as sensitive: it is used only to guide product suggestions, is never sold or shared for marketing, and is handled in line with Kenya's data-protection expectations. You can ask us to delete your information at any time.";
	}
	private static function terms_default(): string {
		return "These terms govern your use of this website and any order you place with Green World Health Solutions. By placing an order you confirm that the details you give us are accurate and that you are able to enter into this agreement.\n\nPrices are shown in Kenyan Shillings and include any taxes where they apply. We may update prices and product information at any time; the price that applies to your order is the one shown when we confirm it.\n\nAn order is a request to buy. It is accepted once we confirm it with you and arrange delivery or pickup. If a product turns out to be unavailable after you order, we will tell you and offer an alternative or a full refund.\n\nOur products are general health and wellness products. Information on this site is for general purposes only and is not medical advice; always read the label and consult a qualified health professional before starting any new product.\n\nDelivery, returns and refunds are governed by our Shipping & Delivery and Returns & Refunds pages, and your privacy by our Privacy Policy; together these form part of these terms.\n\nThese terms are governed by the laws of Kenya. Nothing here removes any right you have under Kenyan consumer law.";
	}

	/* ---------------------------------------------------------------- */
	/*  Shared building blocks                                          */
	/* ---------------------------------------------------------------- */

	private static function contact_bits(): array {
		return array(
			'name'    => self::v( 'name', 'Green World Health Solutions' ),
			'address' => self::v( 'address', 'Development House, 11th Floor, Room 7, Nairobi, Kenya' ),
			'hours'   => self::v( 'hours', 'Monday to Saturday, 8:30 AM to 6:00 PM' ),
			'phone'   => self::v( 'phone', '0723 579 873' ),
			'email'   => self::v( 'email', 'info@greenworldhealth.co.ke' ),
			'wa'      => (string) preg_replace( '/[^0-9]/', '', self::v( 'whatsapp', '254723579873' ) ),
			'pickup'  => self::v( 'pickup', 'Development House, 11th Floor, Room 7, Nairobi' ),
		);
	}

	private static function avatar(): string {
		return '<span class="gw-team__avatar" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="32" r="32" fill="#e8f0ea"/><circle cx="32" cy="25" r="11" fill="#b8ccbe"/><path d="M12 56c2-11 10-17 20-17s18 6 20 17" fill="#b8ccbe"/></svg></span>';
	}

	private static function cta_html(): string {
		$c   = self::contact_bits();
		$wa  = (string) $c['wa'];
		$tel = (string) preg_replace( '/\s+/', '', $c['phone'] );
		$out  = '<p class="gw-trust__cta">';
		$out .= '<a class="button gw-btn--gold" href="' . esc_url( $wa !== '' ? 'https://wa.me/' . $wa : 'tel:' . $tel ) . '">' . esc_html__( 'Chat on WhatsApp', 'greenworld' ) . '</a> ';
		$out .= '<a class="button" href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html__( 'Email us', 'greenworld' ) . '</a>';
		$out .= '</p>';
		return $out;
	}

	private static function page_open( string $eyebrow, string $title, string $intro ): void {
		echo '<div class="gw-container gw-trust">';
		echo '<header class="gw-trust__intro">';
		echo '<span class="gw-eyebrow">' . esc_html( $eyebrow ) . '</span>';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		if ( $intro !== '' ) { echo '<p>' . esc_html( $intro ) . '</p>'; }
		echo '</header>';
	}

	private static function page_close(): void {
		echo '</div>';
	}

	/**
	 * The current page's own editor content (WP-filtered), or '' when empty.
	 * Lets a site owner override the built-in default copy simply by writing
	 * content into the page in wp-admin.
	 */
	private static function editor_content(): string {
		if ( ! is_page() ) {
			return '';
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$raw = (string) $post->post_content;
		if ( trim( $raw ) === '' ) {
			return '';
		}
		return (string) apply_filters( 'the_content', $raw );
	}

	/**
	 * Echo the page's editor content when it has any, otherwise the built-in
	 * default HTML. Keeps the curated header + CTA while letting the owner
	 * replace the body copy from wp-admin.
	 */
	private static function body_or_default( string $default_html ): void {
		$content = self::editor_content();
		if ( $content !== '' ) {
			echo '<div class="gw-richtext">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-filtered post content.
		} else {
			echo $default_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* helpers.
		}
	}

	/** Delivery grid + timing notes (no heading), shared by render() and shipping_page(). */
	private static function delivery_html(): void {
		$c       = self::contact_bits();
		$fee_bus = self::v( 'fee_bus', 'KSh 300' );
		$fee_cur = self::v( 'fee_courier', 'KSh 550' );
		$fee_dhl = self::v( 'fee_dhl', 'KSh 7,000' );
		echo '<div class="gw-trust__delivery">';
		printf( '<div class="gw-trust__ship"><span class="gw-trust__fee">%s</span><strong>%s</strong><p>%s</p></div>', esc_html__( 'Free', 'greenworld' ), esc_html__( 'Nairobi same-day', 'greenworld' ), esc_html__( 'Order before 5:00 PM for same-day delivery within Nairobi.', 'greenworld' ) );
		printf( '<div class="gw-trust__ship"><span class="gw-trust__fee">%s</span><strong>%s</strong><p>%s</p></div>', esc_html( $fee_bus ), esc_html__( 'Bus & shuttle parcel', 'greenworld' ), esc_html__( 'Sent the same day via trusted bus and shuttle parcel services to your town anywhere in Kenya.', 'greenworld' ) );
		printf( '<div class="gw-trust__ship"><span class="gw-trust__fee">%s</span><strong>%s</strong><p>%s</p></div>', esc_html( $fee_cur ), esc_html__( 'Wells Fargo / G4S courier', 'greenworld' ), esc_html__( 'Dispatched via Wells Fargo or G4S courier to your nearest branch or address.', 'greenworld' ) );
		printf( '<div class="gw-trust__ship"><span class="gw-trust__fee">%s</span><strong>%s</strong><p>%s</p></div>', esc_html( $fee_dhl ), esc_html__( 'Worldwide via DHL', 'greenworld' ), esc_html__( 'International shipping for parcels 0.5 kg or less. We have delivered to clients across Africa, Europe, Asia and North America.', 'greenworld' ) );
		echo '</div>';
		echo '<ul class="gw-trust__times">';
		echo '<li>' . esc_html__( 'Estimated times: Nairobi same-day (before 5pm); major towns next day; other Kenyan areas 2 to 3 days; international via DHL typically 3 to 5 working days.', 'greenworld' ) . '</li>';
		echo '<li>' . esc_html( sprintf( 'Pickup: collect free from our office (%s) during business hours.', $c['pickup'] ) ) . '</li>';
		echo '<li class="gw-trust__note">' . esc_html__( 'International orders: your country may charge import duties or taxes on arrival. These are the customer\'s responsibility — please check your local import policy before ordering.', 'greenworld' ) . '</li>';
		echo '</ul>';
	}

	/** Business facts list, shared by render() and contact_page(). */
	private static function facts_html(): void {
		$c = self::contact_bits();
		echo '<ul class="gw-trust__facts">';
		printf( '<li><span>%s</span><strong>%s</strong></li>', esc_html__( 'Registered name', 'greenworld' ), esc_html( $c['name'] ) );
		printf( '<li><span>%s</span><strong>%s</strong></li>', esc_html__( 'Location', 'greenworld' ), esc_html( $c['address'] ) );
		printf( '<li><span>%s</span><strong>%s</strong></li>', esc_html__( 'Business hours', 'greenworld' ), esc_html( $c['hours'] ) );
		printf( '<li><span>%s</span><strong><a href="tel:%s">%s</a></strong></li>', esc_html__( 'Phone / WhatsApp', 'greenworld' ), esc_attr( (string) preg_replace( '/\s+/', '', $c['phone'] ) ), esc_html( $c['phone'] ) );
		printf( '<li><span>%s</span><strong><a href="mailto:%s">%s</a></strong></li>', esc_html__( 'Email', 'greenworld' ), esc_attr( $c['email'] ), esc_html( $c['email'] ) );
		echo '</ul>';
	}

	/** Team roles grid, shared by render() and about_page(). */
	private static function team_html(): void {
		echo '<p>' . esc_html__( 'A small, dedicated team looks after every order and every question. These are the people behind Green World Health Solutions and how they help you.', 'greenworld' ) . '</p>';
		echo '<div class="gw-team__grid">';
		$roles = array(
			array( 'role' => __( 'Founder / Managing Director', 'greenworld' ), 'desc' => __( 'Sets our standards for genuine Green World products and honest, no-pressure wellness guidance.', 'greenworld' ) ),
			array( 'role' => __( 'Wellness Product Advisor', 'greenworld' ), 'desc' => __( 'Helps you choose products suited to your goals and answers your questions before you buy.', 'greenworld' ) ),
			array( 'role' => __( 'Customer Care & Orders', 'greenworld' ), 'desc' => __( 'Looks after orders, payments, delivery and follow-up so your experience is smooth.', 'greenworld' ) ),
		);
		foreach ( $roles as $r ) {
			echo '<div class="gw-team__card">' . self::avatar() . '<strong>' . esc_html( (string) $r['role'] ) . '</strong><span>' . esc_html( (string) $r['desc'] ) . '</span></div>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------- */
	/*  Rendering                                                       */
	/* ---------------------------------------------------------------- */

	public static function render_sc(): string { ob_start(); self::render(); return (string) ob_get_clean(); }
	public static function why_trust_sc(): string { ob_start(); self::why_trust(); return (string) ob_get_clean(); }

	/**
	 * Full company / trust page. The intro is parameterizable so the same body
	 * serves both the Trust Center and the About page.
	 *
	 * @param array<string,string> $ctx Optional eyebrow/title/intro overrides.
	 */
	public static function render( array $ctx = array() ): void {
		$c       = self::contact_bits();
		$name    = $c['name'];
		$address = $c['address'];
		$hours   = $c['hours'];
		$phone   = $c['phone'];
		$email   = $c['email'];

		$eyebrow = isset( $ctx['eyebrow'] ) ? (string) $ctx['eyebrow'] : __( 'Why you can trust us', 'greenworld' );
		$title   = isset( $ctx['title'] ) ? (string) $ctx['title'] : __( 'Trust Center', 'greenworld' );
		$intro   = isset( $ctx['intro'] ) ? (string) $ctx['intro'] : sprintf( __( '%s is a real, Nairobi-based business. Here is exactly who we are, where our products come from, how we protect you as a customer, and how to reach us.', 'greenworld' ), $name );

		self::page_open( $eyebrow, $title, $intro );

		// Anchor nav
		$nav = array(
			'about'      => __( 'About', 'greenworld' ),
			'business'   => __( 'Our Business', 'greenworld' ),
			'quality'    => __( 'Product Quality', 'greenworld' ),
			'authentic'  => __( 'Authenticity', 'greenworld' ),
			'regulatory' => __( 'Regulatory', 'greenworld' ),
			'protection' => __( 'Customer Protection', 'greenworld' ),
			'team'       => __( 'Our Team', 'greenworld' ),
			'contact'    => __( 'Contact', 'greenworld' ),
		);
		echo '<nav class="gw-trust__toc" aria-label="' . esc_attr__( 'Trust Center sections', 'greenworld' ) . '">';
		foreach ( $nav as $id => $label ) {
			echo '<a href="#tc-' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		// About
		echo '<section id="tc-about" class="gw-trust__sec"><h2>' . esc_html__( 'About Green World Health Solutions', 'greenworld' ) . '</h2>';
		echo self::paras( self::v( 'about', self::about_default() ) ); // phpcs:ignore
		echo '</section>';

		// Our Business
		echo '<section id="tc-business" class="gw-trust__sec"><h2>' . esc_html__( 'Our Business', 'greenworld' ) . '</h2>';
		self::facts_html();
		echo '</section>';

		// Product Quality & Sourcing
		echo '<section id="tc-quality" class="gw-trust__sec"><h2>' . esc_html__( 'Product Quality & Sourcing', 'greenworld' ) . '</h2>';
		echo self::paras( self::v( 'sourcing', self::sourcing_default() ) ); // phpcs:ignore
		echo '</section>';

		// Authenticity
		echo '<section id="tc-authentic" class="gw-trust__sec"><h2>' . esc_html__( 'Product Authenticity', 'greenworld' ) . '</h2>';
		echo self::paras( self::v( 'authenticity', self::authenticity_default() ) ); // phpcs:ignore
		echo '</section>';

		// Regulatory
		echo '<section id="tc-regulatory" class="gw-trust__sec"><h2>' . esc_html__( 'Regulatory Information', 'greenworld' ) . '</h2>';
		echo self::paras( self::v( 'regulatory', self::regulatory_default() ) ); // phpcs:ignore
		echo '</section>';

		// Customer Protection
		echo '<section id="tc-protection" class="gw-trust__sec"><h2>' . esc_html__( 'Customer Protection', 'greenworld' ) . '</h2>';
		echo '<h3>' . esc_html__( 'Delivery', 'greenworld' ) . '</h3>';
		self::delivery_html();
		echo '<h3>' . esc_html__( 'Returns & Refunds', 'greenworld' ) . '</h3>';
		echo self::paras( self::v( 'returns', self::returns_default() ) ); // phpcs:ignore
		echo '<h3>' . esc_html__( 'Payments', 'greenworld' ) . '</h3>';
		echo '<p>' . esc_html__( 'We accept M-Pesa, Bank Transfer and Cash (on delivery within Nairobi, or on pickup). All online interactions run over a secure (SSL) connection.', 'greenworld' ) . '</p>';
		echo '<h3>' . esc_html__( 'Privacy & your data', 'greenworld' ) . '</h3>';
		echo self::paras( self::v( 'privacy', self::privacy_default() ) ); // phpcs:ignore
		echo '<h3>' . esc_html__( 'Customer Service', 'greenworld' ) . '</h3>';
		echo '<p>' . esc_html( sprintf( 'Reach us on WhatsApp or phone %s, or email %s, %s.', $phone, $email, $hours ) ) . '</p>';
		echo '</section>';

		// Team
		echo '<section id="tc-team" class="gw-trust__sec"><h2>' . esc_html__( 'Our Team', 'greenworld' ) . '</h2>';
		self::team_html();
		echo '</section>';

		// Contact
		echo '<section id="tc-contact" class="gw-trust__sec gw-trust__contact"><h2>' . esc_html__( 'Contact', 'greenworld' ) . '</h2>';
		echo '<p>' . esc_html( $address ) . '</p>';
		echo self::cta_html();
		echo '<p>' . esc_html( $hours ) . '</p>';
		echo '</section>';

		self::page_close();
	}

	/* ---------------------------------------------------------------- */
	/*  Footer-page renderers (page-{slug}.php templates call these)    */
	/* ---------------------------------------------------------------- */

	public static function about_page(): void {
		self::render( array(
			'eyebrow' => __( 'About us', 'greenworld' ),
			'title'   => __( 'About Green World Health Solutions', 'greenworld' ),
			'intro'   => __( 'Green World Health Solutions is a real, Nairobi-based health and wellness retailer. Here is who we are, where our products come from, how we protect you as a customer, and how to reach us.', 'greenworld' ),
		) );
	}

	public static function shipping_page(): void {
		self::page_open(
			__( 'Delivery', 'greenworld' ),
			__( 'Shipping & Delivery', 'greenworld' ),
			__( 'We deliver genuine Green World products across Kenya and, on request, worldwide. Here is how it works, how long it takes and what it costs.', 'greenworld' )
		);
		echo '<section class="gw-trust__sec">';
		self::delivery_html();
		echo '<h3>' . esc_html__( 'Payments', 'greenworld' ) . '</h3>';
		echo '<p>' . esc_html__( 'We accept M-Pesa, Bank Transfer and Cash (on delivery within Nairobi, or on pickup). All online interactions run over a secure (SSL) connection.', 'greenworld' ) . '</p>';
		$gw_extra = self::editor_content();
		if ( $gw_extra !== '' ) {
			echo '<div class="gw-richtext">' . $gw_extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-filtered post content.
		}
		echo self::cta_html();
		echo '</section>';
		self::page_close();
	}

	public static function returns_page(): void {
		self::page_open(
			__( 'Customer protection', 'greenworld' ),
			__( 'Returns & Refunds', 'greenworld' ),
			__( 'If something is not right with your order, here is exactly how we make it right.', 'greenworld' )
		);
		echo '<section class="gw-trust__sec">';
		self::body_or_default( self::paras( self::v( 'returns', self::returns_default() ) ) );
		echo '<h3>' . esc_html__( 'Payments & refunds', 'greenworld' ) . '</h3>';
		echo '<p>' . esc_html__( 'Refunds are returned to your original payment method — M-Pesa or bank transfer. Cash-on-delivery orders are refunded by M-Pesa.', 'greenworld' ) . '</p>';
		echo self::cta_html();
		echo '</section>';
		self::page_close();
	}

	public static function privacy_page(): void {
		self::page_open(
			__( 'Your data', 'greenworld' ),
			__( 'Privacy Policy', 'greenworld' ),
			__( 'What we collect, why we collect it, and the control you keep over it.', 'greenworld' )
		);
		echo '<section class="gw-trust__sec">';
		self::body_or_default( self::paras( self::v( 'privacy', self::privacy_default() ) ) );
		echo '<p>' . esc_html__( 'To see what we hold about you, or to have it deleted, contact us using the details below.', 'greenworld' ) . '</p>';
		echo self::cta_html();
		echo '</section>';
		self::page_close();
	}

	public static function terms_page(): void {
		self::page_open(
			__( 'The essentials', 'greenworld' ),
			__( 'Terms & Conditions', 'greenworld' ),
			__( 'The plain-language terms that apply when you use this site and order from us.', 'greenworld' )
		);
		echo '<section class="gw-trust__sec">';
		self::body_or_default( self::paras( self::v( 'terms', self::terms_default() ) ) );
		echo self::cta_html();
		echo '</section>';
		self::page_close();
	}

	public static function contact_page(): void {
		$c = self::contact_bits();
		self::page_open(
			__( 'Talk to us', 'greenworld' ),
			__( 'Contact Us', 'greenworld' ),
			__( 'A real Nairobi office you can call, message or visit. We are glad to help you choose the right product.', 'greenworld' )
		);
		echo '<section class="gw-trust__sec gw-trust__contact">';
		self::facts_html();
		self::map_html();
		echo self::cta_html();
		echo '<p>' . esc_html( sprintf( 'We reply during business hours: %s.', $c['hours'] ) ) . '</p>';
		echo '</section>';
		self::page_close();
	}

	/**
	 * Public wrapper for the single-sourced WhatsApp + email call-to-action so
	 * standalone page templates (FAQ, Health Disclaimer) can reuse it.
	 */
	public static function contact_cta(): void {
		echo self::cta_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Keyless Google Map embed for the office. Uses the maps query-embed
	 * endpoint (no API key required). The map query is Customizer-overridable via
	 * gw_tc_map so a precise place link can be pasted; it defaults to the office
	 * building so the map always resolves. Lazy-loaded to protect Core Web Vitals.
	 */
	public static function map_html(): void {
		$c     = self::contact_bits();
		$query = self::v( 'map', 'Development House, Nairobi, Kenya' );
		$src   = 'https://www.google.com/maps?q=' . rawurlencode( $query ) . '&output=embed';
		$dir   = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $query );
		echo '<div class="gw-trust__map" style="margin:1.25rem 0">';
		echo '<iframe title="' . esc_attr__( 'Our location on Google Maps', 'greenworld' ) . '" src="' . esc_url( $src ) . '" width="100%" height="360" style="border:0;border-radius:12px;display:block" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
		echo '<p style="margin:.6rem 0 0"><a class="button" href="' . esc_url( $dir ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get directions', 'greenworld' ) . '</a></p>';
		echo '</div>';
	}

	public static function why_trust(): void {
		$cards = array(
			array( __( 'Genuine Green World products', 'greenworld' ), __( 'Sourced through the international Green World / World Food group supply chain.', 'greenworld' ) ),
			array( __( 'Kenya-based', 'greenworld' ), __( 'A real Nairobi office you can visit or call, Monday to Saturday.', 'greenworld' ) ),
			array( __( 'Secure local payments', 'greenworld' ), __( 'M-Pesa, Bank Transfer and Cash, over a secure connection.', 'greenworld' ) ),
			array( __( 'Nationwide & worldwide delivery', 'greenworld' ), __( 'Same-day in Nairobi, countrywide courier, and DHL international shipping.', 'greenworld' ) ),
			array( __( 'Real support', 'greenworld' ), __( 'WhatsApp, phone and email — talk to a person, not a bot.', 'greenworld' ) ),
			array( __( 'Transparent policies', 'greenworld' ), __( 'Clear returns, refunds, delivery and privacy — no surprises.', 'greenworld' ) ),
		);
		echo '<section class="gw-section gw-whytrust"><div class="gw-container">';
		echo '<div class="gw-sec__head"><div class="gw-sec__heads"><span class="gw-eyebrow">' . esc_html__( 'Peace of mind', 'greenworld' ) . '</span><h2 class="gw-sec__title">' . esc_html__( 'Why Customers Trust Green World Health Solutions', 'greenworld' ) . '</h2></div>';
		echo '<a class="gw-sec__more" href="' . esc_url( home_url( '/trust-center/' ) ) . '">' . esc_html__( 'Visit our Trust Center', 'greenworld' ) . '</a></div>';
		echo '<div class="gw-whytrust__grid">';
		foreach ( $cards as $card ) {
			printf( '<div class="gw-whytrust__card"><strong>%s</strong><p>%s</p></div>', esc_html( $card[0] ), esc_html( $card[1] ) );
		}
		echo '</div></div></section>';
	}

	/* ---------------------------------------------------------------- */
	/*  Customizer                                                      */
	/* ---------------------------------------------------------------- */

	public function customize( \WP_Customize_Manager $wp ): void {
		if ( ! $wp->get_panel( 'greenworld' ) ) {
			$wp->add_panel( 'greenworld', array( 'title' => __( 'GreenWorld Theme', 'greenworld' ), 'priority' => 30 ) );
		}
		$wp->add_section( 'gw_trust', array( 'title' => __( 'Trust Center', 'greenworld' ), 'panel' => 'greenworld', 'priority' => 40 ) );

		$text = array(
			'name'        => __( 'Registered business name', 'greenworld' ),
			'address'     => __( 'Address', 'greenworld' ),
			'hours'       => __( 'Business hours', 'greenworld' ),
			'phone'       => __( 'Phone', 'greenworld' ),
			'email'       => __( 'Email', 'greenworld' ),
			'whatsapp'    => __( 'WhatsApp number (digits only)', 'greenworld' ),
			'pickup'      => __( 'Pickup address', 'greenworld' ),
			'fee_bus'     => __( 'Bus & shuttle parcel fee', 'greenworld' ),
			'fee_courier' => __( 'Wells Fargo / G4S courier fee', 'greenworld' ),
			'fee_dhl'     => __( 'Worldwide DHL fee', 'greenworld' ),
		);
		foreach ( $text as $k => $label ) {
			$wp->add_setting( 'gw_tc_' . $k, array( 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp->add_control( 'gw_tc_' . $k, array( 'label' => $label, 'section' => 'gw_trust', 'type' => 'text' ) );
		}

		$areas = array(
			'about'        => __( 'About text', 'greenworld' ),
			'sourcing'     => __( 'Product quality & sourcing', 'greenworld' ),
			'authenticity' => __( 'Authenticity', 'greenworld' ),
			'regulatory'   => __( 'Regulatory information', 'greenworld' ),
			'returns'      => __( 'Returns & refunds', 'greenworld' ),
			'privacy'      => __( 'Privacy', 'greenworld' ),
			'terms'        => __( 'Terms & conditions', 'greenworld' ),
		);
		foreach ( $areas as $k => $label ) {
			$wp->add_setting( 'gw_tc_' . $k, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
			$wp->add_control( 'gw_tc_' . $k, array( 'label' => $label, 'section' => 'gw_trust', 'type' => 'textarea', 'description' => __( 'Leave blank to use the built-in copy.', 'greenworld' ) ) );
		}
	}
}

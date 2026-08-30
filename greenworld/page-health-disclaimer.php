<?php
/**
 * Page template for "Health Disclaimer" (slug: health-disclaimer).
 *
 * Expanded, illustrated health disclaimer. The core statement is single-sourced
 * from the Customizer setting `gw_default_disclaimer` (the same text shown on
 * product pages), so it stays consistent site-wide. Illustrations are inline SVG
 * (no extra HTTP requests, good for Core Web Vitals).
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

$gw_core = trim( (string) get_theme_mod( 'gw_default_disclaimer', 'Product information is provided for general informational purposes and is not a substitute for professional medical advice, diagnosis or treatment. Always consult a qualified healthcare professional before starting any product, especially if you are pregnant, nursing, taking medication or managing a medical condition.' ) );

$gw_icons = array(
	'shield'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>',
	'stethoscope' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v5a4 4 0 0 0 8 0V3"/><path d="M6 3H4M14 3h2"/><path d="M10 15a6 6 0 0 0 6 6 4 4 0 0 0 4-4v-2"/><circle cx="20" cy="12" r="2"/></svg>',
	'label'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h9l7 7-9 9-9-9z"/><circle cx="9" cy="9" r="1.4"/></svg>',
	'person'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>',
	'heart'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.3-9.3-8.6C1 8 2.4 5 5.5 5 7.6 5 9 6.3 12 9c3-2.7 4.4-4 6.5-4 3.1 0 4.5 3 2.8 6.4C19 15.7 12 20 12 20z"/></svg>',
	'alert'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/></svg>',
);

$gw_points = array(
	array( 'shield', __( 'General wellness information only', 'greenworld' ), __( 'Our products are general health and wellness products. Nothing on this site is intended to diagnose, treat, cure or prevent any disease.', 'greenworld' ) ),
	array( 'stethoscope', __( 'Speak to a professional first', 'greenworld' ), __( 'Always consult a qualified doctor, pharmacist or other healthcare professional before starting a new product, changing your routine or adjusting prescribed medication.', 'greenworld' ) ),
	array( 'label', __( 'Read the label', 'greenworld' ), __( 'Follow the directions, serving size and precautions printed on each product. Keep products out of reach of children and store them as directed.', 'greenworld' ) ),
	array( 'person', __( 'Individual results vary', 'greenworld' ), __( 'Wellness outcomes differ from person to person. We do not promise specific results and we never publish claims we cannot support.', 'greenworld' ) ),
	array( 'heart', __( 'Special situations', 'greenworld' ), __( 'Take extra care if you are pregnant or nursing, managing a medical condition, taking medication, or preparing for surgery. Check with your doctor before use.', 'greenworld' ) ),
	array( 'alert', __( 'In an emergency', 'greenworld' ), __( 'If you have a serious or allergic reaction or a medical emergency, stop use immediately and seek urgent medical care. Do not rely on this website in an emergency.', 'greenworld' ) ),
);
?>
<div class="gw-container gw-disclaimer">
	<header class="gw-trust__intro">
		<span class="gw-eyebrow"><?php esc_html_e( 'Your safety comes first', 'greenworld' ); ?></span>
		<h1><?php esc_html_e( 'Health Disclaimer', 'greenworld' ); ?></h1>
		<p><?php esc_html_e( 'Please read this before using any product you buy from Green World Health Solutions. It explains how to use our product information safely and responsibly.', 'greenworld' ); ?></p>
	</header>

	<div class="gw-disclaimer__grid">
		<?php foreach ( $gw_points as $gw_p ) : ?>
			<div class="gw-disclaimer__card">
				<span class="gw-disclaimer__ic" aria-hidden="true"><?php echo $gw_icons[ $gw_p[0] ]; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<h3><?php echo esc_html( $gw_p[1] ); ?></h3>
				<p><?php echo esc_html( $gw_p[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="gw-disclaimer__statement">
		<h2><?php esc_html_e( 'Full disclaimer', 'greenworld' ); ?></h2>
		<p><?php echo esc_html( $gw_core ); ?></p>
		<p><?php esc_html_e( 'Product names, descriptions and traditional-use information are provided for general education and have not been evaluated as medical treatments. By using this website and our products you accept this disclaimer together with our Terms &amp; Conditions and Privacy Policy.', 'greenworld' ); ?></p>
	</div>

	<?php
	if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
		echo '<div class="gw-disclaimer__cta"><p>' . esc_html__( 'Questions about a product before you buy? Talk to us.', 'greenworld' ) . '</p>';
		\GreenWorld\Front\TrustCenter::contact_cta();
		echo '</div>';
	}
	?>
</div>
<style id="gw-disclaimer-css">
.gw-disclaimer{max-width:960px;padding-top:2rem;padding-bottom:3rem}
.gw-disclaimer__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin:1.75rem 0}
.gw-disclaimer__card{border:1px solid #e6ece7;border-radius:14px;background:#fff;padding:1.25rem}
.gw-disclaimer__ic{display:inline-flex;width:44px;height:44px;align-items:center;justify-content:center;border-radius:12px;background:#e8f0ea;color:#1E5B3E;margin-bottom:.6rem}
.gw-disclaimer__ic svg{width:24px;height:24px}
.gw-disclaimer__card h3{margin:.1rem 0 .35rem;color:#0b4f2e;font-size:1.05rem}
.gw-disclaimer__card p{margin:0;color:#33413a;line-height:1.55}
.gw-disclaimer__statement{border-left:4px solid #A98545;background:#faf7f0;padding:1.2rem 1.4rem;border-radius:0 12px 12px 0;margin:1.5rem 0}
.gw-disclaimer__statement h2{margin:.1rem 0 .5rem;color:#123726}
.gw-disclaimer__statement p{color:#3a4640;line-height:1.65}
.gw-disclaimer__cta{margin-top:1.5rem}
.gw-disclaimer__cta p{margin:0 0 .5rem;font-weight:600;color:#123726}
</style>
<?php
get_footer();

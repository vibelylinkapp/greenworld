<?php
/**
 * Page template for "FAQ" (slug: faq).
 *
 * Renders the customer FAQ as accessible, no-JS <details> accordions from the
 * single-sourced bank in \GreenWorld\Content\TopicMap::general_faqs(). The same
 * bank feeds FAQPage structured data (see \GreenWorld\Seo\Schema::faq_from_map),
 * so the visible content and rich-result markup always stay in sync.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

get_header();

$gw_faqs = class_exists( '\GreenWorld\Content\TopicMap' ) ? \GreenWorld\Content\TopicMap::general_faqs() : array();
?>
<div class="gw-container gw-faqpage">
	<header class="gw-trust__intro">
		<span class="gw-eyebrow"><?php esc_html_e( 'Help &amp; answers', 'greenworld' ); ?></span>
		<h1><?php esc_html_e( 'Frequently Asked Questions', 'greenworld' ); ?></h1>
		<p><?php esc_html_e( 'Everything you need to know about ordering genuine Green World products, payment, delivery across Kenya, authenticity and our free wellness consultation. Still stuck? Message us on WhatsApp and a real person will help.', 'greenworld' ); ?></p>
	</header>

	<div class="gw-faqpage__list">
		<?php foreach ( $gw_faqs as $gw_qa ) : ?>
			<details class="gw-faqpage__item">
				<summary><?php echo esc_html( (string) $gw_qa[0] ); ?></summary>
				<div class="gw-faqpage__a"><p><?php echo esc_html( (string) $gw_qa[1] ); ?></p></div>
			</details>
		<?php endforeach; ?>
	</div>

	<?php
	if ( class_exists( '\GreenWorld\Front\TrustCenter' ) ) {
		echo '<div class="gw-faqpage__cta"><p>' . esc_html__( 'Cannot find your answer? We are happy to help.', 'greenworld' ) . '</p>';
		\GreenWorld\Front\TrustCenter::contact_cta();
		echo '</div>';
	}
	?>
</div>
<style id="gw-faqpage-css">
.gw-faqpage{max-width:900px;padding-top:2rem;padding-bottom:3rem}
.gw-faqpage__list{margin:1.5rem 0}
.gw-faqpage__item{border:1px solid #e6ece7;border-radius:12px;margin:.6rem 0;background:#fff;overflow:hidden}
.gw-faqpage__item summary{cursor:pointer;list-style:none;padding:1rem 1.2rem;font-weight:600;color:#0b4f2e;font-size:1.05rem;display:flex;justify-content:space-between;align-items:center;gap:1rem}
.gw-faqpage__item summary::-webkit-details-marker{display:none}
.gw-faqpage__item summary::after{content:"+";font-size:1.4rem;line-height:1;color:#1E5B3E;flex:0 0 auto}
.gw-faqpage__item[open] summary::after{content:"\2212"}
.gw-faqpage__item[open] summary{border-bottom:1px solid #eef2ee}
.gw-faqpage__a{padding:.9rem 1.2rem 1.1rem;color:#33413a;line-height:1.6}
.gw-faqpage__a p{margin:0}
.gw-faqpage__cta{margin-top:1.75rem}
.gw-faqpage__cta p{margin:0 0 .5rem;font-weight:600;color:#123726}
</style>
<?php
get_footer();

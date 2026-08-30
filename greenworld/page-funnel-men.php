<?php
/**
 * Template Name: Funnel - Men's Vitality
 *
 * Direct-response sales funnel for Green World men's health solutions
 * (erectile support, stamina, prostate health and fertility). Pulls each
 * product live from WooCommerce by slug so images, prices and add-to-cart
 * always match the store. Assign this template to any Page from
 * Page Attributes > Template.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

$gw_wa     = preg_replace( '/[^0-9]/', '', (string) get_theme_mod( 'gw_whatsapp', '254723579873' ) );
$gw_wa_url = 'https://wa.me/' . $gw_wa . '?text=' . rawurlencode( 'Hello Green World, I would like a free private consultation about mens vitality.' );
$gw_hero   = get_parent_theme_file_uri( 'assets/img/funnel-men-hero.jpg' );
$gw_recon  = get_parent_theme_file_uri( 'assets/img/funnel-men-reconnect.jpg' );

if ( ! function_exists( 'gwf_render_product' ) ) {
	function gwf_render_product( $slug, $label, $benefit, $featured = false ) {
		$post    = get_page_by_path( $slug, OBJECT, 'product' );
		$product = ( $post && function_exists( 'wc_get_product' ) ) ? wc_get_product( $post->ID ) : null;
		$url     = $post ? get_permalink( $post->ID ) : home_url( '/product/' . $slug . '/' );
		$price   = $product ? $product->get_price_html() : '';
		$atc     = $product ? $product->add_to_cart_url() : $url;
		$cls     = $featured ? 'gwf-card gwf-card--featured' : 'gwf-card';
		if ( $product ) {
			$img = $product->get_image( 'woocommerce_thumbnail' );
		} else {
			$img = '<img src="' . esc_url( get_parent_theme_file_uri( 'assets/img/placeholder-product.svg' ) ) . '" alt="" loading="lazy" />';
		}
		?>
		<article class="<?php echo esc_attr( $cls ); ?>">
			<?php if ( $featured ) : ?><span class="gwf-card__badge">Most chosen</span><?php endif; ?>
			<a class="gwf-card__media" href="<?php echo esc_url( $url ); ?>"><?php echo wp_kses_post( $img ); ?></a>
			<div class="gwf-card__body">
				<h3 class="gwf-card__title"><?php echo esc_html( $label ); ?></h3>
				<p class="gwf-card__benefit"><?php echo esc_html( $benefit ); ?></p>
				<?php if ( $price ) : ?><div class="gwf-card__price"><?php echo wp_kses_post( $price ); ?></div><?php endif; ?>
				<div class="gwf-card__cta">
					<a class="gwf-btn gwf-btn--solid" href="<?php echo esc_url( $atc ); ?>">Add to cart</a>
					<a class="gwf-btn gwf-btn--ghost" href="<?php echo esc_url( $url ); ?>">See details</a>
				</div>
			</div>
		</article>
		<?php
	}
}

$gwf_groups = array(
	array(
		'title' => 'Performance &amp; Confidence',
		'items' => array(
			array( 'green-world-erectile-dysfunction-support-package-kenya', 'Erectile Dysfunction Support Package', 'A complete natural package to restore firm, lasting erections and rebuild your confidence.', true ),
			array( 'green-world-vigpower-capsule-strong-erection-stamina-premature-ejacula', 'VigPower Capsule', 'Fast-acting botanical support for stronger erections, stamina and staying power.', false ),
			array( 'green-world-premature-ejaculation-support-pack-kenya', 'Premature Ejaculation Support Pack', 'Regain control and last longer with a targeted, natural support pack.', false ),
		),
	),
	array(
		'title' => 'Prostate Health',
		'items' => array(
			array( 'green-world-prostate-support-combo-kenya', 'Prostate Support Combo', 'Ease frequent night-time urination and support a healthy, comfortable prostate.', false ),
			array( 'green-world-prostate-wellness-support-supplements-kenya', 'Prostate Wellness Support', 'Daily botanical support to keep the prostate healthy as you age.', false ),
		),
	),
	array(
		'title' => 'Fertility &amp; Sperm Health',
		'items' => array(
			array( 'low-sperm-count-oligospermia-full-treatment-package-vig-power-zinc-tablets-gingseng-and-se-tablets', 'Low Sperm Count (Oligospermia) Package', 'Support healthy sperm count, motility and quality on your fertility journey.', false ),
			array( 'azoospermia-treatment-package-vig-power-caps-gingseng-rhs-caps-ginkgo-biloba-caps-zinc-tablets-cordyceps-caps', 'Azoospermia Treatment Package', 'A comprehensive package to support sperm production and reproductive health.', false ),
		),
	),
);

get_header();
?>
<main id="primary" class="gwf">

	<style>
	.gwf{color:#173a2b;}
	.gwf p{margin:0 0 1rem;}
	.gwf-eyebrow{display:inline-block;text-transform:uppercase;letter-spacing:.14em;font-size:.78rem;font-weight:700;color:#C9A96A;margin:0 0 .4rem;}
	.gwf-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.95rem 1.6rem;border-radius:999px;font-weight:700;font-size:1rem;line-height:1;text-decoration:none;border:2px solid transparent;transition:transform .15s ease,box-shadow .15s ease;cursor:pointer;}
	.gwf-btn:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(0,0,0,.16);}
	.gwf-btn--gold{background:linear-gradient(135deg,#C9A96A,#A98545);color:#1c1206;}
	.gwf-btn--solid{background:#1f6f43;color:#ffffff;}
	.gwf-btn--wa{background:#25D366;color:#0b3a1e;}
	.gwf-btn--ghost{background:transparent;border-color:#1f6f43;color:#1f6f43;}
	.gwf-btn--ghost-light{background:transparent;border-color:rgba(255,255,255,.75);color:#ffffff;}

	.gwf-hero{position:relative;min-height:80vh;display:flex;align-items:center;overflow:hidden;}
	.gwf-hero__bg{position:absolute;inset:0;background-size:cover;background-position:center right;transform:scale(1.02);}
	.gwf-hero__overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(9,28,18,.9) 0%,rgba(9,28,18,.66) 46%,rgba(9,28,18,.12) 100%);}
	.gwf-hero__inner{position:relative;padding:5.5rem 0;}
	.gwf-hero__copy{max-width:640px;}
	.gwf-hero__title{color:#ffffff;font-size:clamp(2.1rem,5vw,3.5rem);line-height:1.06;margin:.4rem 0 .9rem;font-weight:800;}
	.gwf-hero__lead{color:#e9f2ec;font-size:1.14rem;line-height:1.6;max-width:560px;}
	.gwf-hero__cta{display:flex;gap:.9rem;flex-wrap:wrap;margin:1.7rem 0 1.3rem;}
	.gwf-trust{list-style:none;display:flex;gap:1.3rem;flex-wrap:wrap;padding:0;margin:.4rem 0 0;}
	.gwf-trust li{color:#dfeae1;font-size:.9rem;font-weight:600;position:relative;padding-left:1.1rem;}
	.gwf-trust li::before{content:"";position:absolute;left:0;top:.4em;width:.6rem;height:.6rem;border-radius:50%;background:#C9A96A;}

	.gwf-pain{background:#f6f7f5;padding:4.8rem 0;text-align:center;}
	.gwf-pain h2{font-size:clamp(1.6rem,3.5vw,2.4rem);color:#123726;margin:0 0 .4rem;}
	.gwf-sub{color:#5b6b60;max-width:660px;margin:.4rem auto 2rem;font-size:1.06rem;line-height:1.6;}
	.gwf-pain__grid{list-style:none;padding:0;margin:0 auto;max-width:900px;display:grid;grid-template-columns:repeat(2,1fr);gap:.9rem;text-align:left;}
	.gwf-pain__grid li{background:#ffffff;border:1px solid #e6ebe6;border-radius:12px;padding:1rem 1.1rem 1rem 2.7rem;position:relative;color:#2c3a31;font-weight:600;}
	.gwf-pain__grid li::before{content:"";position:absolute;left:1rem;top:1.15rem;width:.85rem;height:.85rem;border-radius:50%;background:#e7f0ea;box-shadow:inset 0 0 0 3px #C9A96A;}
	.gwf-pain__note{margin-top:1.7rem;font-weight:700;color:#1f6f43;font-size:1.05rem;}

	.gwf-why{padding:5.2rem 0;background:#ffffff;}
	.gwf-why__inner{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;}
	.gwf-why__media img{width:100%;border-radius:20px;box-shadow:0 26px 60px rgba(18,55,38,.2);display:block;}
	.gwf-why__copy h2{font-size:clamp(1.6rem,3.5vw,2.4rem);color:#123726;margin:.2rem 0 .8rem;}
	.gwf-why__points{list-style:none;padding:0;margin:1.2rem 0 0;display:grid;gap:.75rem;}
	.gwf-why__points li{padding-left:2rem;position:relative;color:#2c3a31;line-height:1.5;}
	.gwf-why__points li::before{content:"";position:absolute;left:0;top:.1rem;width:1.2rem;height:1.2rem;border-radius:50%;background:#e7f0ea;box-shadow:inset 0 0 0 2px #1f6f43;}

	.gwf-solutions{padding:5.2rem 0;background:#f2f6f2;}
	.gwf-section-head{text-align:center;max-width:720px;margin:0 auto 2.2rem;}
	.gwf-section-head h2{font-size:clamp(1.7rem,3.6vw,2.5rem);color:#123726;margin:.2rem 0 .6rem;}
	.gwf-group__title{font-size:1.28rem;color:#1f6f43;margin:2.4rem 0 1.1rem;padding-bottom:.5rem;border-bottom:2px solid #dce7de;}
	.gwf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,340px));gap:1.4rem;justify-content:center;}
	.gwf-card{background:#ffffff;border:1px solid #e6ebe6;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;position:relative;transition:transform .18s ease,box-shadow .18s ease;}
	.gwf-card:hover{transform:translateY(-4px);box-shadow:0 20px 44px rgba(18,55,38,.15);}
	.gwf-card--featured{border-color:#C9A96A;box-shadow:0 14px 32px rgba(201,169,106,.24);}
	.gwf-card__badge{position:absolute;top:.9rem;left:.9rem;background:#C9A96A;color:#1c1206;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;padding:.32rem .6rem;border-radius:999px;z-index:2;}
	.gwf-card__media{display:block;background:#f6f7f5;}
	.gwf-card__media img{width:100%;height:auto;display:block;aspect-ratio:1/1;object-fit:cover;}
	.gwf-card__body{padding:1.1rem 1.2rem 1.35rem;display:flex;flex-direction:column;flex:1;}
	.gwf-card__title{font-size:1.06rem;color:#173a2b;margin:0 0 .4rem;line-height:1.3;}
	.gwf-card__benefit{color:#5b6b60;font-size:.94rem;line-height:1.5;margin:0 0 .85rem;flex:1;}
	.gwf-card__price{color:#1f6f43;font-weight:800;font-size:1.12rem;margin-bottom:.9rem;}
	.gwf-card__price del{color:#9aa79d;font-weight:600;font-size:.9rem;margin-right:.35rem;}
	.gwf-card__cta{display:flex;gap:.5rem;}
	.gwf-card__cta .gwf-btn{flex:1;padding:.72rem .7rem;font-size:.9rem;}

	.gwf-steps{padding:5.2rem 0;background:#ffffff;}
	.gwf-steps h2{text-align:center;font-size:clamp(1.6rem,3.4vw,2.3rem);color:#123726;margin:0 0 2.4rem;}
	.gwf-steps__list{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(4,1fr);gap:1.4rem;}
	.gwf-steps__list li{text-align:center;}
	.gwf-steps__list span{display:inline-flex;width:3rem;height:3rem;align-items:center;justify-content:center;border-radius:50%;background:#1f6f43;color:#ffffff;font-weight:800;font-size:1.2rem;margin-bottom:.8rem;}
	.gwf-steps__list h4{margin:.2rem 0 .35rem;color:#173a2b;font-size:1.05rem;}
	.gwf-steps__list p{color:#5b6b60;font-size:.92rem;line-height:1.5;}

	.gwf-proof{padding:5.2rem 0;background:#123726;color:#eef4ef;}
	.gwf-proof h2{text-align:center;color:#ffffff;font-size:clamp(1.6rem,3.4vw,2.3rem);margin:0 0 2rem;}
	.gwf-proof__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.4rem;}
	.gwf-proof blockquote{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:1.5rem;margin:0;}
	.gwf-proof blockquote p{font-size:1rem;line-height:1.62;color:#eef4ef;margin:0;}
	.gwf-proof cite{display:block;margin-top:.9rem;color:#C9A96A;font-style:normal;font-weight:700;font-size:.9rem;}
	.gwf-proof__badges{text-align:center;margin-top:2.2rem;color:#bcd2c2;font-weight:600;letter-spacing:.02em;}
		.gwf-proof__stats{list-style:none;padding:0;margin:0 auto 1.8rem;max-width:840px;display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;text-align:center;}
		.gwf-proof__stats li{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:1.1rem .6rem;}
		.gwf-proof__stats strong{display:block;color:#ffffff;font-size:1.5rem;font-weight:800;line-height:1.1;}
		.gwf-proof__stats span{display:block;margin-top:.35rem;color:#c6dccb;font-size:.82rem;line-height:1.35;}
		@media(max-width:640px){.gwf-proof__stats{grid-template-columns:repeat(2,1fr);}}

	.gwf-faq{padding:5.2rem 0;background:#f6f7f5;}
	.gwf-faq h2{text-align:center;font-size:clamp(1.6rem,3.4vw,2.3rem);color:#123726;margin:0 0 1.8rem;}
	.gwf-faq__wrap{max-width:820px;margin:0 auto;}
	.gwf-faq details{background:#ffffff;border:1px solid #e6ebe6;border-radius:12px;padding:0 1.2rem;margin-bottom:.8rem;}
	.gwf-faq summary{cursor:pointer;padding:1.1rem 0;font-weight:700;color:#173a2b;list-style:none;position:relative;padding-right:1.6rem;}
	.gwf-faq summary::-webkit-details-marker{display:none;}
	.gwf-faq summary::after{content:"+";position:absolute;right:.1rem;top:1rem;color:#1f6f43;font-weight:800;}
	.gwf-faq details[open] summary::after{content:"-";}
	.gwf-faq details p{color:#5b6b60;line-height:1.62;padding-bottom:1.1rem;margin:0;}

	.gwf-final{padding:5.4rem 0;background:linear-gradient(135deg,#1f6f43,#123726);color:#ffffff;text-align:center;}
	.gwf-final__inner{max-width:720px;margin:0 auto;}
	.gwf-final h2{color:#ffffff;font-size:clamp(1.7rem,3.6vw,2.5rem);margin:0 0 .6rem;}
	.gwf-final p{color:#dfeae1;font-size:1.1rem;margin:0 0 1.6rem;}
	.gwf-final .gwf-hero__cta{justify-content:center;}

	.gwf-disclaimer{max-width:840px;margin:0 auto;padding:2.2rem 1rem 3rem;color:#8a978d;font-size:.82rem;line-height:1.55;text-align:center;background:#ffffff;}

	.gwf-sticky{position:fixed;left:0;right:0;bottom:0;z-index:60;display:none;gap:.6rem;padding:.6rem .8rem;background:rgba(255,255,255,.97);box-shadow:0 -6px 20px rgba(0,0,0,.14);}
	.gwf-sticky .gwf-btn{flex:1;padding:.85rem;font-size:.95rem;}

	@media(max-width:980px){.gwf-why__inner{grid-template-columns:1fr;}.gwf-grid{grid-template-columns:repeat(auto-fit,minmax(240px,340px));}.gwf-steps__list{grid-template-columns:repeat(2,1fr);}.gwf-proof__grid{grid-template-columns:1fr;}}
	@media(max-width:640px){.gwf-grid{grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.8rem;}.gwf-card__body{padding:.85rem .8rem 1rem;}.gwf-card__title{font-size:.95rem;min-height:2.4rem;}.gwf-card__benefit{font-size:.85rem;-webkit-line-clamp:2;}.gwf-card__cta{flex-direction:column;gap:.4rem;}.gwf-card__cta .gwf-btn{padding:.62rem;font-size:.82rem;}.gwf-pain__grid{grid-template-columns:1fr;}.gwf-steps__list{grid-template-columns:1fr;}.gwf-hero{min-height:auto;}.gwf-hero__overlay{background:linear-gradient(180deg,rgba(9,28,18,.72),rgba(9,28,18,.9));}.gwf-sticky{display:flex;}.gwf-disclaimer{padding-bottom:6rem;}}
	</style>

	<section class="gwf-hero">
		<div class="gwf-hero__bg" style="background-image:url('<?php echo esc_url( $gw_hero ); ?>');"></div>
		<div class="gwf-hero__overlay"></div>
		<div class="gw-container gwf-hero__inner">
			<div class="gwf-hero__copy">
				<span class="gwf-eyebrow">Men's Vitality &amp; Performance</span>
				<h1 class="gwf-hero__title">Feel like yourself again &mdash; strong, confident, in control</h1>
				<p class="gwf-hero__lead">Natural, carefully formulated solutions for firmer erections, stamina, prostate comfort and fertility &mdash; delivered discreetly across Kenya and backed by Green World's 30+ years of botanical science.</p>
				<div class="gwf-hero__cta">
					<a class="gwf-btn gwf-btn--gold" href="#gwf-solutions">Find my solution</a>
					<a class="gwf-btn gwf-btn--wa" href="<?php echo esc_url( $gw_wa_url ); ?>">Talk privately on WhatsApp</a>
				</div>
				<ul class="gwf-trust">
					<li>100% natural</li>
					<li>Discreet delivery</li>
					<li>Trusted across Kenya</li>
					<li>Free consultation</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="gwf-pain">
		<div class="gw-container">
			<h2>If this sounds familiar, you are not alone</h2>
			<p class="gwf-sub">Thousands of Kenyan men quietly face the same challenges. It is common, it is nothing to be ashamed of &mdash; and it can be supported naturally.</p>
			<ul class="gwf-pain__grid">
				<li>Erections that feel weaker or do not last</li>
				<li>Low energy, drive or confidence</li>
				<li>Finishing sooner than you would like</li>
				<li>Waking at night to urinate or prostate discomfort</li>
				<li>Trouble conceiving or a low sperm count</li>
				<li>Feeling distant from your partner</li>
			</ul>
			<p class="gwf-pain__note">Whatever you are facing, there is a discreet, natural path forward.</p>
		</div>
	</section>

	<section class="gwf-why">
		<div class="gw-container gwf-why__inner">
			<div class="gwf-why__media">
				<img src="<?php echo esc_url( $gw_recon ); ?>" alt="A confident, healthy couple reconnecting" loading="lazy" />
			</div>
			<div class="gwf-why__copy">
				<span class="gwf-eyebrow">The natural way back</span>
				<h2>Real support, without the harsh chemicals</h2>
				<p>Green World blends time-tested botanicals with modern nutritional science to work with your body &mdash; supporting healthy blood flow, hormones, stamina and reproductive health at the root, rather than only masking symptoms.</p>
				<ul class="gwf-why__points">
					<li><strong>Natural formulas</strong> &mdash; plant-based and gentle on the body</li>
					<li><strong>Root-cause approach</strong> &mdash; targets the real drivers, not just the symptom</li>
					<li><strong>Global trust</strong> &mdash; a brand with 30+ years across 40+ countries</li>
					<li><strong>Private guidance</strong> &mdash; a free, confidential consultation whenever you need it</li>
				</ul>
			</div>
		</div>
	</section>

	<section id="gwf-solutions" class="gwf-solutions">
		<div class="gw-container">
			<div class="gwf-section-head">
				<span class="gwf-eyebrow">Choose your solution</span>
				<h2>Targeted natural packages for every concern</h2>
				<p class="gwf-sub">Pick the package that matches what you are experiencing. Not certain which one fits? Tap &ldquo;Talk privately on WhatsApp&rdquo; and our team will guide you, free and in confidence.</p>
			</div>
			<?php foreach ( $gwf_groups as $gwf_group ) : ?>
				<h3 class="gwf-group__title"><?php echo wp_kses_post( $gwf_group['title'] ); ?></h3>
				<div class="gwf-grid">
					<?php foreach ( $gwf_group['items'] as $gwf_item ) { gwf_render_product( $gwf_item[0], $gwf_item[1], $gwf_item[2], $gwf_item[3] ); } ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="gwf-steps">
		<div class="gw-container">
			<h2>Getting started is simple and private</h2>
			<ol class="gwf-steps__list">
				<li><span>1</span><h4>Choose or ask</h4><p>Pick a package above, or message us for a free, confidential recommendation.</p></li>
				<li><span>2</span><h4>Discreet delivery</h4><p>We deliver countrywide in plain, unbranded packaging. Pay on delivery or via M-Pesa.</p></li>
				<li><span>3</span><h4>Follow the simple plan</h4><p>Use as guided. Our team checks in and supports you the whole way.</p></li>
				<li><span>4</span><h4>Feel the difference</h4><p>Rebuild your confidence, energy and connection.</p></li>
			</ol>
		</div>
	</section>

		<section class="gwf-proof">
		<div class="gw-container">
			<h2>Why men across Kenya choose Green World</h2>
			<ul class="gwf-proof__stats">
				<li><strong>30+</strong><span>years of botanical science</span></li>
				<li><strong>40+</strong><span>countries worldwide</span></li>
				<li><strong>100%</strong><span>natural formulas</span></li>
				<li><strong>Discreet</strong><span>plain-packaging delivery</span></li>
			</ul>
			<div class="gwf-proof__badges">Genuine products &nbsp;&bull;&nbsp; Discreet packaging &nbsp;&bull;&nbsp; Free consultation &nbsp;&bull;&nbsp; Countrywide delivery</div>
		</div>
	</section>

	<section class="gwf-faq">
		<div class="gw-container gwf-faq__wrap">
			<h2>Your questions, answered</h2>
			<details><summary>Is it safe to use?</summary><p>Green World products are natural, plant-based supplements used by people in over 40 countries and are generally well tolerated. If you have a medical condition or take prescription medicine, please check with your doctor first.</p></details>
			<details><summary>Are there side effects?</summary><p>Because the formulas are natural, most men tolerate them well when used as directed. Follow the recommended usage and stop if you notice any reaction.</p></details>
			<details><summary>How soon will I see results?</summary><p>Many men notice changes within a few weeks. Natural support works best used consistently, and fertility packages in particular are taken over a full cycle.</p></details>
			<details><summary>Is delivery really discreet?</summary><p>Yes. Everything arrives in plain, unbranded packaging. Only you know what is inside.</p></details>
			<details><summary>How do I pay?</summary><p>Pay on delivery or via M-Pesa, with delivery countrywide across Kenya.</p></details>
			<details><summary>Do I need a prescription?</summary><p>No prescription is needed. A free, confidential consultation simply helps you choose the right package.</p></details>
			<details><summary>I am not sure which one to choose.</summary><p>Message us on WhatsApp for a free, private recommendation based on what you are experiencing.</p></details>
		</div>
	</section>

	<section class="gwf-final">
		<div class="gw-container gwf-final__inner">
			<h2>Take the first step today &mdash; privately and with confidence</h2>
			<p>Talk to a specialist now for a free, discreet consultation, or choose your package and we will deliver it to your door.</p>
			<div class="gwf-hero__cta">
				<a class="gwf-btn gwf-btn--gold" href="<?php echo esc_url( $gw_wa_url ); ?>">Chat privately on WhatsApp</a>
				<a class="gwf-btn gwf-btn--ghost-light" href="#gwf-solutions">See the packages</a>
			</div>
		</div>
	</section>

	<p class="gwf-disclaimer">Green World products are natural wellness supplements. They are not intended to diagnose, treat, cure or prevent any disease. Results vary from person to person. Please consult a qualified healthcare professional, especially if you have a medical condition or take medication.</p>

	<div class="gwf-sticky">
		<a class="gwf-btn gwf-btn--wa" href="<?php echo esc_url( $gw_wa_url ); ?>">WhatsApp</a>
		<a class="gwf-btn gwf-btn--gold" href="#gwf-solutions">Shop solutions</a>
	</div>

</main>
<?php
get_footer();

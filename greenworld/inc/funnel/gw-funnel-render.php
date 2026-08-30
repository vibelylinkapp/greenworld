<?php
/**
 * Green World funnel engine.
 *
 * Shared renderer for the marketing / sales-funnel landing pages. A thin page
 * template defines a $config array and calls gwf_render_funnel( $config ). This
 * file owns the layout, the scoped .gwf styling and the live-WooCommerce
 * product logic (by slug list OR by product category), so every funnel stays
 * consistent and always in sync with the store.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gwf_product_from_slug' ) ) {
	/**
	 * Resolve a WooCommerce product from its URL slug.
	 *
	 * @param string $slug Product slug.
	 * @return WC_Product|null
	 */
	function gwf_product_from_slug( $slug ) {
		$post = get_page_by_path( $slug, OBJECT, 'product' );
		if ( $post && function_exists( 'wc_get_product' ) ) {
			return wc_get_product( $post->ID );
		}
		return null;
	}
}

if ( ! function_exists( 'gwf_products_from_category' ) ) {
	/**
	 * Fetch published, visible products in a product category (by slug).
	 *
	 * @param string $category_slug Product category slug.
	 * @param int    $limit         Max products.
	 * @return array Array of WC_Product objects.
	 */
	function gwf_products_from_category( $category_slug, $limit = 12 ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => (int) $limit,
				'category' => array( $category_slug ),
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
			)
		);
		return is_array( $products ) ? $products : array();
	}
}

if ( ! function_exists( 'gwf_render_product_card' ) ) {
	/**
	 * Render one product card.
	 *
	 * @param WC_Product|null $product Resolved product (or null).
	 * @param array           $opts    label, benefit, featured, fallback_slug.
	 */
	function gwf_render_product_card( $product, $opts = array() ) {
		$label    = isset( $opts['label'] ) ? $opts['label'] : '';
		$benefit  = isset( $opts['benefit'] ) ? $opts['benefit'] : '';
		$featured = ! empty( $opts['featured'] );
		$fallback = isset( $opts['fallback_slug'] ) ? $opts['fallback_slug'] : '';

		if ( $product ) {
			$url   = get_permalink( $product->get_id() );
			$title = $label ? $label : $product->get_name();
			$price = $product->get_price_html();
			$atc   = $product->add_to_cart_url();
			$img   = $product->get_image( 'woocommerce_thumbnail' );
			if ( ! $benefit ) {
				$benefit = trim( wp_strip_all_tags( $product->get_short_description() ) );
				if ( ! $benefit ) {
					$benefit = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 24 );
				}
			}
		} else {
			$url   = $fallback ? home_url( '/product/' . $fallback . '/' ) : '#';
			$title = $label ? $label : $fallback;
			$price = '';
			$atc   = $url;
			$img   = '';
		}

		$cls = $featured ? 'gwf-card gwf-card--featured' : 'gwf-card';
		?>
		<article class="<?php echo esc_attr( $cls ); ?>">
			<?php if ( $featured ) : ?><span class="gwf-card__badge">Most chosen</span><?php endif; ?>
			<a class="gwf-card__media" href="<?php echo esc_url( $url ); ?>"><?php echo $img ? wp_kses_post( $img ) : ''; ?></a>
			<div class="gwf-card__body">
				<h3 class="gwf-card__title"><?php echo esc_html( $title ); ?></h3>
				<?php if ( $benefit ) : ?><p class="gwf-card__benefit"><?php echo esc_html( $benefit ); ?></p><?php endif; ?>
				<?php if ( $price ) : ?><div class="gwf-card__price"><?php echo wp_kses_post( $price ); ?></div><?php endif; ?>
				<div class="gwf-card__cta">
					<a class="gwf-btn gwf-btn--solid" href="<?php echo esc_url( $atc ); ?>">Add to cart</a>
					<a class="gwf-btn gwf-btn--ghost" href="<?php echo esc_url( $url ); ?>">Details</a>
				</div>
			</div>
		</article>
		<?php
	}
}

if ( ! function_exists( 'gwf_render_funnel' ) ) {
	/**
	 * Render a full funnel landing page from a config array.
	 *
	 * @param array $c Funnel configuration.
	 */
	function gwf_render_funnel( array $c ) {
		$accent      = isset( $c['accent'] ) ? $c['accent'] : '#1f6f43';
		$accent_dark = isset( $c['accent_dark'] ) ? $c['accent_dark'] : '#123726';
		$gold        = isset( $c['gold'] ) ? $c['gold'] : '#C9A96A';
		$gold_dark   = isset( $c['gold_dark'] ) ? $c['gold_dark'] : '#A98545';
		$ink         = isset( $c['ink'] ) ? $c['ink'] : '#173a2b';

		$wa_number = preg_replace( '/[^0-9]/', '', (string) get_theme_mod( 'gw_whatsapp', '254723579873' ) );
		$wa_text   = isset( $c['wa_text'] ) ? $c['wa_text'] : 'Hello Green World, I would like a free private consultation.';
		$wa_url    = 'https://wa.me/' . $wa_number . '?text=' . rawurlencode( $wa_text );

		$hero_img = ! empty( $c['hero_img'] ) ? get_parent_theme_file_uri( 'assets/img/' . $c['hero_img'] ) : '';

		$eyebrow    = isset( $c['eyebrow'] ) ? $c['eyebrow'] : '';
		$hero_title = isset( $c['hero_title'] ) ? $c['hero_title'] : '';
		$hero_lead  = isset( $c['hero_lead'] ) ? $c['hero_lead'] : '';
		$trust      = isset( $c['trust'] ) ? (array) $c['trust'] : array( '100% natural', 'Discreet delivery', 'Trusted across Kenya', 'Free consultation' );

		$pain_title = isset( $c['pain_title'] ) ? $c['pain_title'] : 'If this sounds familiar, you are not alone';
		$pain_sub   = isset( $c['pain_sub'] ) ? $c['pain_sub'] : '';
		$pains      = isset( $c['pains'] ) ? (array) $c['pains'] : array();
		$pain_note  = isset( $c['pain_note'] ) ? $c['pain_note'] : 'Whatever you are facing, there is a natural path forward.';

		$why_eyebrow = isset( $c['why_eyebrow'] ) ? $c['why_eyebrow'] : 'The natural way forward';
		$why_title   = isset( $c['why_title'] ) ? $c['why_title'] : 'Real support, without the harsh chemicals';
		$why_copy    = isset( $c['why_copy'] ) ? $c['why_copy'] : 'Green World blends time-tested botanicals with modern nutritional science to work with your body, supporting health at the root rather than only masking symptoms.';
		$assurances  = isset( $c['assurances'] ) ? (array) $c['assurances'] : array(
			array( 'Natural formulas', 'Plant-based and gentle on the body' ),
			array( 'Root-cause approach', 'Targets the real drivers, not just the symptom' ),
			array( 'Global trust', 'A brand with 30+ years across 40+ countries' ),
			array( 'Private guidance', 'A free, confidential consultation whenever you need it' ),
		);

		$sol_eyebrow = isset( $c['solutions_eyebrow'] ) ? $c['solutions_eyebrow'] : 'Choose your solution';
		$sol_title   = isset( $c['solutions_title'] ) ? $c['solutions_title'] : 'Targeted natural packages for every concern';
		$sol_sub     = isset( $c['solutions_sub'] ) ? $c['solutions_sub'] : 'Not sure which one fits? Tap "Talk privately on WhatsApp" and our team will guide you, free and in confidence.';
		$groups      = isset( $c['groups'] ) ? (array) $c['groups'] : array();

		$steps = isset( $c['steps'] ) ? (array) $c['steps'] : array(
			array( 'Choose or ask', 'Pick a package above, or message us for a free recommendation.' ),
			array( 'Discreet delivery', 'We deliver countrywide. Pay on delivery or via M-Pesa.' ),
			array( 'Follow the simple plan', 'Use as guided. Our team supports you the whole way.' ),
			array( 'Feel the difference', 'Rebuild your health, energy and confidence.' ),
		);

		$proof_title  = isset( $c['proof_title'] ) ? $c['proof_title'] : 'Trusted by families across Kenya';

		$faqs = isset( $c['faqs'] ) ? (array) $c['faqs'] : array();

		$final_title = isset( $c['final_title'] ) ? $c['final_title'] : 'Take the first step today';
		$final_copy  = isset( $c['final_copy'] ) ? $c['final_copy'] : 'Talk to a specialist now for a free, discreet consultation, or choose your package and we will deliver it to your door.';

		$disclaimer = isset( $c['disclaimer'] ) ? $c['disclaimer'] : 'Green World products are natural wellness supplements. They are not intended to diagnose, treat, cure or prevent any disease. Results vary from person to person. Please consult a qualified healthcare professional, especially if you have a medical condition or take medication.';

		$style_vars = sprintf(
			'--gwf-accent:%s;--gwf-accent-dark:%s;--gwf-gold:%s;--gwf-gold-dark:%s;--gwf-ink:%s;',
			esc_attr( $accent ),
			esc_attr( $accent_dark ),
			esc_attr( $gold ),
			esc_attr( $gold_dark ),
			esc_attr( $ink )
		);

		get_header();
		?>
		<main id="primary" class="gwf" style="<?php echo esc_attr( $style_vars ); ?>">

		<style>
		.gwf{color:var(--gwf-ink);}
		.gwf p{margin:0 0 1rem;}
		.gwf-eyebrow{display:inline-block;text-transform:uppercase;letter-spacing:.14em;font-size:.78rem;font-weight:700;color:var(--gwf-gold-dark);margin:0 0 .4rem;}
		.gwf-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.95rem 1.6rem;border-radius:999px;font-weight:700;font-size:1rem;line-height:1;text-decoration:none;border:2px solid transparent;transition:transform .15s ease,box-shadow .15s ease;cursor:pointer;}
		.gwf-btn:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(0,0,0,.16);}
		.gwf-btn--gold{background:linear-gradient(135deg,var(--gwf-gold),var(--gwf-gold-dark));color:#1c1206;}
		.gwf-btn--solid{background:var(--gwf-accent);color:#ffffff;}
		.gwf-btn--wa{background:#25D366;color:#0b3a1e;}
		.gwf-btn--ghost{background:transparent;border-color:var(--gwf-accent);color:var(--gwf-accent);}
		.gwf-btn--ghost-light{background:transparent;border-color:rgba(255,255,255,.75);color:#ffffff;}

		.gwf-hero{position:relative;min-height:78vh;display:flex;align-items:center;overflow:hidden;}
		.gwf-hero__bg{position:absolute;inset:0;background-size:cover;background-position:center right;transform:scale(1.02);}
		.gwf-hero__overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,24,17,.9) 0%,rgba(10,24,17,.64) 46%,rgba(10,24,17,.1) 100%);}
		.gwf-hero__inner{position:relative;padding:5.5rem 0;}
		.gwf-hero__copy{max-width:640px;}
		.gwf-hero__title{color:#ffffff;font-size:clamp(2.1rem,5vw,3.5rem);line-height:1.06;margin:.4rem 0 .9rem;font-weight:800;}
		.gwf-hero__lead{color:#eef4ef;font-size:1.14rem;line-height:1.6;max-width:560px;}
		.gwf-hero__cta{display:flex;gap:.9rem;flex-wrap:wrap;margin:1.7rem 0 1.3rem;}
		.gwf-trust{list-style:none;display:flex;gap:1.3rem;flex-wrap:wrap;padding:0;margin:.4rem 0 0;}
		.gwf-trust li{color:#e4efe7;font-size:.9rem;font-weight:600;position:relative;padding-left:1.1rem;}
		.gwf-trust li::before{content:"";position:absolute;left:0;top:.4em;width:.6rem;height:.6rem;border-radius:50%;background:var(--gwf-gold);}

		.gwf-pain{background:#f6f7f5;padding:4.8rem 0;text-align:center;}
		.gwf-pain h2{font-size:clamp(1.6rem,3.5vw,2.4rem);color:var(--gwf-accent-dark);margin:0 0 .4rem;}
		.gwf-sub{color:#5b6b60;max-width:680px;margin:.4rem auto 2rem;font-size:1.06rem;line-height:1.6;}
		.gwf-pain__grid{list-style:none;padding:0;margin:0 auto;max-width:900px;display:grid;grid-template-columns:repeat(2,1fr);gap:.9rem;text-align:left;}
		.gwf-pain__grid li{background:#ffffff;border:1px solid #e6ebe6;border-radius:12px;padding:1rem 1.1rem 1rem 2.7rem;position:relative;color:#2c3a31;font-weight:600;}
		.gwf-pain__grid li::before{content:"";position:absolute;left:1rem;top:1.15rem;width:.85rem;height:.85rem;border-radius:50%;background:#eef2ee;box-shadow:inset 0 0 0 3px var(--gwf-gold);}
		.gwf-pain__note{margin-top:1.7rem;font-weight:700;color:var(--gwf-accent);font-size:1.05rem;}

		.gwf-why{padding:5.2rem 0;background:#ffffff;}
		.gwf-why__inner{display:grid;grid-template-columns:1.05fr .95fr;gap:3rem;align-items:center;}
		.gwf-why__copy h2{font-size:clamp(1.6rem,3.5vw,2.4rem);color:var(--gwf-accent-dark);margin:.2rem 0 .8rem;}
		.gwf-why__panel{background:linear-gradient(160deg,#f4f8f4,#eef4ee);border:1px solid #e2ebe3;border-radius:20px;padding:1.6rem 1.7rem;box-shadow:0 20px 50px rgba(18,55,38,.1);}
		.gwf-why__panel ul{list-style:none;padding:0;margin:0;display:grid;gap:1rem;}
		.gwf-why__panel li{padding-left:2.4rem;position:relative;}
		.gwf-why__panel li::before{content:"";position:absolute;left:0;top:.15rem;width:1.5rem;height:1.5rem;border-radius:50%;background:var(--gwf-accent);}
		.gwf-why__panel li::after{content:"";position:absolute;left:.52rem;top:.5rem;width:.42rem;height:.72rem;border:solid #ffffff;border-width:0 .16rem .16rem 0;transform:rotate(45deg);}
		.gwf-why__panel strong{display:block;color:var(--gwf-ink);font-size:1rem;}
		.gwf-why__panel span{color:#5b6b60;font-size:.92rem;line-height:1.45;}

		.gwf-solutions{padding:5.2rem 0;background:#f2f6f2;}
		.gwf-section-head{text-align:center;max-width:720px;margin:0 auto 2.2rem;}
		.gwf-section-head h2{font-size:clamp(1.7rem,3.6vw,2.5rem);color:var(--gwf-accent-dark);margin:.2rem 0 .6rem;}
		.gwf-group__title{font-size:1.28rem;color:var(--gwf-accent);margin:2.4rem 0 1.1rem;padding-bottom:.5rem;border-bottom:2px solid #dce7de;}
		.gwf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,340px));gap:1.4rem;justify-content:center;}
		.gwf-card{background:#ffffff;border:1px solid #e6ebe6;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;position:relative;transition:transform .18s ease,box-shadow .18s ease;}
		.gwf-card:hover{transform:translateY(-4px);box-shadow:0 20px 44px rgba(18,55,38,.15);}
		.gwf-card--featured{border-color:var(--gwf-gold);box-shadow:0 14px 32px rgba(201,169,106,.24);}
		.gwf-card__badge{position:absolute;top:.9rem;left:.9rem;background:var(--gwf-gold);color:#1c1206;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;padding:.32rem .6rem;border-radius:999px;z-index:2;}
		.gwf-card__media{display:block;background:#f6f7f5;}
		.gwf-card__media img{width:100%;height:auto;display:block;aspect-ratio:1/1;object-fit:cover;}
		.gwf-card__body{padding:1.1rem 1.2rem 1.35rem;display:flex;flex-direction:column;flex:1;}
		.gwf-card__title{font-size:1.06rem;color:var(--gwf-ink);margin:0 0 .4rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.75rem;}
		.gwf-card__benefit{color:#5b6b60;font-size:.94rem;line-height:1.5;margin:0 0 .85rem;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
		.gwf-card__price{color:var(--gwf-accent);font-weight:800;font-size:1.12rem;margin-bottom:.9rem;}
		.gwf-card__price del{color:#9aa79d;font-weight:600;font-size:.9rem;margin-right:.35rem;}
		.gwf-card__cta{display:flex;gap:.5rem;}
		.gwf-card__cta .gwf-btn{flex:1;padding:.72rem .7rem;font-size:.9rem;}
		.gwf-more{text-align:center;margin-top:2.2rem;}
		.gwf-empty{text-align:center;color:#5b6b60;background:#ffffff;border:1px dashed #cfdcd2;border-radius:14px;padding:2rem;}

		.gwf-steps{padding:5.2rem 0;background:#ffffff;}
		.gwf-steps h2{text-align:center;font-size:clamp(1.6rem,3.4vw,2.3rem);color:var(--gwf-accent-dark);margin:0 0 2.4rem;}
		.gwf-steps__list{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(4,1fr);gap:1.4rem;}
		.gwf-steps__list li{text-align:center;}
		.gwf-steps__list span{display:inline-flex;width:3rem;height:3rem;align-items:center;justify-content:center;border-radius:50%;background:var(--gwf-accent);color:#ffffff;font-weight:800;font-size:1.2rem;margin-bottom:.8rem;}
		.gwf-steps__list h4{margin:.2rem 0 .35rem;color:var(--gwf-ink);font-size:1.05rem;}
		.gwf-steps__list p{color:#5b6b60;font-size:.92rem;line-height:1.5;}

		.gwf-proof{padding:5.2rem 0;background:var(--gwf-accent-dark);color:#eef4ef;}
		.gwf-proof h2{text-align:center;color:#ffffff;font-size:clamp(1.6rem,3.4vw,2.3rem);margin:0 0 2rem;}
		.gwf-proof__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.4rem;}
		.gwf-proof blockquote{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:1.5rem;margin:0;}
		.gwf-proof blockquote p{font-size:1rem;line-height:1.62;color:#eef4ef;margin:0;}
		.gwf-proof cite{display:block;margin-top:.9rem;color:var(--gwf-gold);font-style:normal;font-weight:700;font-size:.9rem;}
		.gwf-proof__badges{text-align:center;margin-top:2.2rem;color:#c6dccb;font-weight:600;letter-spacing:.02em;}
		.gwf-proof__stats{list-style:none;padding:0;margin:0 auto 1.8rem;max-width:840px;display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;text-align:center;}
		.gwf-proof__stats li{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:1.1rem .6rem;}
		.gwf-proof__stats strong{display:block;color:#ffffff;font-size:1.5rem;font-weight:800;line-height:1.1;}
		.gwf-proof__stats span{display:block;margin-top:.35rem;color:#c6dccb;font-size:.82rem;line-height:1.35;}
		@media(max-width:640px){.gwf-proof__stats{grid-template-columns:repeat(2,1fr);}}

		.gwf-faq{padding:5.2rem 0;background:#f6f7f5;}
		.gwf-faq h2{text-align:center;font-size:clamp(1.6rem,3.4vw,2.3rem);color:var(--gwf-accent-dark);margin:0 0 1.8rem;}
		.gwf-faq__wrap{max-width:820px;margin:0 auto;}
		.gwf-faq details{background:#ffffff;border:1px solid #e6ebe6;border-radius:12px;padding:0 1.2rem;margin-bottom:.8rem;}
		.gwf-faq summary{cursor:pointer;padding:1.1rem 0;font-weight:700;color:var(--gwf-ink);list-style:none;position:relative;padding-right:1.6rem;}
		.gwf-faq summary::-webkit-details-marker{display:none;}
		.gwf-faq summary::after{content:"+";position:absolute;right:.1rem;top:1rem;color:var(--gwf-accent);font-weight:800;}
		.gwf-faq details[open] summary::after{content:"-";}
		.gwf-faq details p{color:#5b6b60;line-height:1.62;padding-bottom:1.1rem;margin:0;}

		.gwf-final{padding:5.4rem 0;background:linear-gradient(135deg,var(--gwf-accent),var(--gwf-accent-dark));color:#ffffff;text-align:center;}
		.gwf-final__inner{max-width:720px;margin:0 auto;}
		.gwf-final h2{color:#ffffff;font-size:clamp(1.7rem,3.6vw,2.5rem);margin:0 0 .6rem;}
		.gwf-final p{color:#e4efe7;font-size:1.1rem;margin:0 0 1.6rem;}
		.gwf-final .gwf-hero__cta{justify-content:center;}

		.gwf-disclaimer{max-width:840px;margin:0 auto;padding:2.2rem 1rem 3rem;color:#8a978d;font-size:.82rem;line-height:1.55;text-align:center;background:#ffffff;}

		.gwf-sticky{position:fixed;left:0;right:0;bottom:0;z-index:60;display:none;gap:.6rem;padding:.6rem .8rem;background:rgba(255,255,255,.97);box-shadow:0 -6px 20px rgba(0,0,0,.14);}
		.gwf-sticky .gwf-btn{flex:1;padding:.85rem;font-size:.95rem;}

		@media(max-width:980px){.gwf-why__inner{grid-template-columns:1fr;}.gwf-grid{grid-template-columns:repeat(auto-fit,minmax(240px,340px));}.gwf-steps__list{grid-template-columns:repeat(2,1fr);}.gwf-proof__grid{grid-template-columns:1fr;}}
		@media(max-width:640px){.gwf-grid{grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.8rem;}.gwf-card__body{padding:.85rem .8rem 1rem;}.gwf-card__title{font-size:.95rem;min-height:2.4rem;}.gwf-card__benefit{font-size:.85rem;-webkit-line-clamp:2;}.gwf-card__cta{flex-direction:column;gap:.4rem;}.gwf-card__cta .gwf-btn{padding:.62rem;font-size:.82rem;}.gwf-pain__grid{grid-template-columns:1fr;}.gwf-steps__list{grid-template-columns:1fr;}.gwf-hero{min-height:auto;}.gwf-hero__overlay{background:linear-gradient(180deg,rgba(10,24,17,.72),rgba(10,24,17,.92));}.gwf-sticky{display:flex;}.gwf-disclaimer{padding-bottom:6rem;}}
		</style>

		<section class="gwf-hero">
			<div class="gwf-hero__bg" style="background-image:url('<?php echo esc_url( $hero_img ); ?>');"></div>
			<div class="gwf-hero__overlay"></div>
			<div class="gw-container gwf-hero__inner">
				<div class="gwf-hero__copy">
					<?php if ( $eyebrow ) : ?><span class="gwf-eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></span><?php endif; ?>
					<h1 class="gwf-hero__title"><?php echo wp_kses_post( $hero_title ); ?></h1>
					<p class="gwf-hero__lead"><?php echo wp_kses_post( $hero_lead ); ?></p>
					<div class="gwf-hero__cta">
						<a class="gwf-btn gwf-btn--gold" href="#gwf-solutions">Find my solution</a>
						<a class="gwf-btn gwf-btn--wa" href="<?php echo esc_url( $wa_url ); ?>">Talk privately on WhatsApp</a>
					</div>
					<ul class="gwf-trust">
						<?php foreach ( $trust as $t ) : ?><li><?php echo esc_html( $t ); ?></li><?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>

		<?php if ( $pains ) : ?>
		<section class="gwf-pain">
			<div class="gw-container">
				<h2><?php echo wp_kses_post( $pain_title ); ?></h2>
				<?php if ( $pain_sub ) : ?><p class="gwf-sub"><?php echo wp_kses_post( $pain_sub ); ?></p><?php endif; ?>
				<ul class="gwf-pain__grid">
					<?php foreach ( $pains as $p ) : ?><li><?php echo esc_html( $p ); ?></li><?php endforeach; ?>
				</ul>
				<?php if ( $pain_note ) : ?><p class="gwf-pain__note"><?php echo wp_kses_post( $pain_note ); ?></p><?php endif; ?>
			</div>
		</section>
		<?php endif; ?>

		<section class="gwf-why">
			<div class="gw-container gwf-why__inner">
				<div class="gwf-why__copy">
					<span class="gwf-eyebrow"><?php echo wp_kses_post( $why_eyebrow ); ?></span>
					<h2><?php echo wp_kses_post( $why_title ); ?></h2>
					<p><?php echo wp_kses_post( $why_copy ); ?></p>
				</div>
				<div class="gwf-why__panel">
					<ul>
						<?php foreach ( $assurances as $a ) : ?>
							<li><strong><?php echo esc_html( $a[0] ); ?></strong><span><?php echo esc_html( $a[1] ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>

		<section id="gwf-solutions" class="gwf-solutions">
			<div class="gw-container">
				<div class="gwf-section-head">
					<?php if ( $sol_eyebrow ) : ?><span class="gwf-eyebrow"><?php echo wp_kses_post( $sol_eyebrow ); ?></span><?php endif; ?>
					<h2><?php echo wp_kses_post( $sol_title ); ?></h2>
					<?php if ( $sol_sub ) : ?><p class="gwf-sub"><?php echo wp_kses_post( $sol_sub ); ?></p><?php endif; ?>
				</div>
				<?php
				foreach ( $groups as $group ) {
					$g_title = isset( $group['title'] ) ? $group['title'] : '';
					$g_type  = isset( $group['type'] ) ? $group['type'] : 'slugs';
					if ( $g_title ) {
						echo '<h3 class="gwf-group__title">' . wp_kses_post( $g_title ) . '</h3>';
					}
					echo '<div class="gwf-grid">';
					if ( 'category' === $g_type ) {
						$cat      = isset( $group['category'] ) ? $group['category'] : '';
						$limit    = isset( $group['limit'] ) ? (int) $group['limit'] : 12;
						$products = $cat ? gwf_products_from_category( $cat, $limit ) : array();
						if ( $products ) {
							foreach ( $products as $product ) {
								gwf_render_product_card( $product );
							}
						} else {
							echo '</div><div class="gwf-empty">Our ' . esc_html( $g_title ? $g_title : 'products' ) . ' are being updated. Tap WhatsApp above and we will help you right away.</div><div class="gwf-grid" style="display:none">';
						}
					} else {
						$items = isset( $group['items'] ) ? (array) $group['items'] : array();
						foreach ( $items as $item ) {
							$slug    = isset( $item[0] ) ? $item[0] : '';
							$product = $slug ? gwf_product_from_slug( $slug ) : null;
							gwf_render_product_card(
								$product,
								array(
									'label'         => isset( $item[1] ) ? $item[1] : '',
									'benefit'       => isset( $item[2] ) ? $item[2] : '',
									'featured'      => ! empty( $item[3] ),
									'fallback_slug' => $slug,
								)
							);
						}
					}
					echo '</div>';
					if ( 'category' === $g_type && ! empty( $group['link'] ) ) {
						echo '<div class="gwf-more"><a class="gwf-btn gwf-btn--ghost" href="' . esc_url( $group['link'] ) . '">View all in this range</a></div>';
					}
				}
				?>
			</div>
		</section>

		<section class="gwf-steps">
			<div class="gw-container">
				<h2>Getting started is simple</h2>
				<ol class="gwf-steps__list">
					<?php $i = 1; foreach ( $steps as $s ) : ?>
						<li><span><?php echo (int) $i; ?></span><h4><?php echo esc_html( $s[0] ); ?></h4><p><?php echo esc_html( $s[1] ); ?></p></li>
					<?php $i++; endforeach; ?>
				</ol>
			</div>
		</section>

				<section class="gwf-proof">
			<div class="gw-container">
				<h2><?php echo wp_kses_post( $proof_title ); ?></h2>
				<ul class="gwf-proof__stats">
					<li><strong>30+</strong><span>years of botanical science</span></li>
					<li><strong>40+</strong><span>countries worldwide</span></li>
					<li><strong>100%</strong><span>natural formulas</span></li>
					<li><strong>Countrywide</strong><span>discreet delivery</span></li>
				</ul>
				<div class="gwf-proof__badges">Genuine products &nbsp;&bull;&nbsp; Discreet packaging &nbsp;&bull;&nbsp; Free consultation &nbsp;&bull;&nbsp; Countrywide delivery</div>
			</div>
		</section>

		<?php if ( $faqs ) : ?>
		<section class="gwf-faq">
			<div class="gw-container gwf-faq__wrap">
				<h2>Your questions, answered</h2>
				<?php foreach ( $faqs as $f ) : ?>
					<details><summary><?php echo esc_html( $f[0] ); ?></summary><p><?php echo wp_kses_post( $f[1] ); ?></p></details>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<section class="gwf-final">
			<div class="gw-container gwf-final__inner">
				<h2><?php echo wp_kses_post( $final_title ); ?></h2>
				<p><?php echo wp_kses_post( $final_copy ); ?></p>
				<div class="gwf-hero__cta">
					<a class="gwf-btn gwf-btn--gold" href="<?php echo esc_url( $wa_url ); ?>">Chat privately on WhatsApp</a>
					<a class="gwf-btn gwf-btn--ghost-light" href="#gwf-solutions">See the packages</a>
				</div>
			</div>
		</section>

		<p class="gwf-disclaimer"><?php echo wp_kses_post( $disclaimer ); ?></p>

		<div class="gwf-sticky">
			<a class="gwf-btn gwf-btn--wa" href="<?php echo esc_url( $wa_url ); ?>">WhatsApp</a>
			<a class="gwf-btn gwf-btn--gold" href="#gwf-solutions">Shop solutions</a>
		</div>

		</main>
		<?php
		get_footer();
	}
}

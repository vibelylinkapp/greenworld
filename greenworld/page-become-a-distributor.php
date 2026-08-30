<?php
/**
 * Page template for "Become a Distributor" (slug: become-a-distributor).
 *
 * Creative, illustrated landing page for the Green World direct-selling
 * opportunity. Built around the points-per-product + batch-allocation model and
 * keeps the existing [gw_register type="distributor"] signup flow intact.
 *
 * @package GreenWorld
 */

defined( 'ABSPATH' ) || exit;

$gw_wa     = preg_replace( '/[^0-9]/', '', (string) get_theme_mod( 'gw_whatsapp', '254723579873' ) );
$gw_wa_url = 'https://wa.me/' . $gw_wa . '?text=' . rawurlencode( 'Hello Green World, I would like to become a distributor.' );

get_header();
?>
<main id="primary" class="gw-dist">

	<section class="gw-dist-hero">
		<span class="gw-dist-hero__blob gw-dist-hero__blob--a" aria-hidden="true"></span>
		<span class="gw-dist-hero__blob gw-dist-hero__blob--b" aria-hidden="true"></span>
		<div class="gw-container gw-dist-hero__inner">
			<div class="gw-dist-hero__copy">
				<span class="gw-eyebrow gw-dist-hero__eyebrow">Partner with Green World</span>
				<h1 class="gw-dist-hero__title">Turn wellness into your own growing business</h1>
				<p class="gw-dist-hero__lead">Become a Green World Health Solutions distributor. Earn reward points on every product, receive stock in batches, and build an income you control &mdash; backed by a global wellness brand with 30+ years of history across 40+ countries.</p>
				<div class="gw-dist-hero__cta">
					<a class="button gw-btn--gold" href="#register">Become a distributor</a>
					<a class="button gw-dist-hero__ghost" href="<?php echo esc_url( $gw_wa_url ); ?>">Chat with our team</a>
				</div>
				<ul class="gw-dist-stats">
					<li><strong>30+</strong><span>years worldwide</span></li>
					<li><strong>40+</strong><span>countries</span></li>
					<li><strong>100%</strong><span>genuine products</span></li>
				</ul>
			</div>
			<div class="gw-dist-hero__art" aria-hidden="true">
				<svg viewBox="0 0 440 380" role="img" xmlns="http://www.w3.org/2000/svg">
					<defs>
						<linearGradient id="gwg1" x1="0" y1="0" x2="1" y2="1">
							<stop offset="0" stop-color="#2E7D52"/>
							<stop offset="1" stop-color="#123726"/>
						</linearGradient>
						<linearGradient id="gwg2" x1="0" y1="1" x2="0" y2="0">
							<stop offset="0" stop-color="#C9A96A"/>
							<stop offset="1" stop-color="#A98545"/>
						</linearGradient>
					</defs>
					<rect x="26" y="40" width="388" height="300" rx="26" fill="#ffffff" stroke="#E6E1D5"/>
					<rect x="26" y="40" width="388" height="64" rx="26" fill="url(#gwg1)"/>
					<circle cx="60" cy="72" r="9" fill="#C9A96A"/>
					<rect x="80" y="66" width="150" height="12" rx="6" fill="#ffffff" opacity=".85"/>
					<rect x="56" y="250" width="46" height="70" rx="8" fill="#E7EFE7"/>
					<rect x="120" y="212" width="46" height="108" rx="8" fill="#CFE3D5"/>
					<rect x="184" y="176" width="46" height="144" rx="8" fill="#8FC0A0"/>
					<rect x="248" y="140" width="46" height="180" rx="8" fill="#2E7D52"/>
					<path d="M70 250 L143 212 L207 176 L271 140 L340 120" fill="none" stroke="url(#gwg2)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
					<circle cx="340" cy="120" r="26" fill="url(#gwg2)"/>
					<text x="340" y="126" text-anchor="middle" font-family="Arial, sans-serif" font-size="17" font-weight="700" fill="#123726">PV</text>
					<g stroke="#A98545" stroke-width="2">
						<line x1="332" y1="150" x2="300" y2="196"/>
						<line x1="348" y1="150" x2="372" y2="196"/>
					</g>
					<circle cx="300" cy="200" r="12" fill="#123726"/>
					<circle cx="376" cy="200" r="12" fill="#123726"/>
				</svg>
			</div>
		</div>
	</section>

	<section class="gw-section gw-dist-points">
		<div class="gw-container">
			<div class="gw-dist-head">
				<span class="gw-eyebrow">How you earn</span>
				<h2>Every product carries points. Every batch grows your account.</h2>
				<p>Our distributor engine is simple and transparent. Each product is assigned a point value, points are allocated to you in batches, and those points build your earnings and move you up the ranks.</p>
			</div>
			<ol class="gw-dist-flow">
				<li class="gw-dist-flow__step">
					<span class="gw-dist-flow__ic">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>
					</span>
					<strong>Points per product</strong>
					<p>Admin sets how many points each product earns. Higher-value packs carry more points.</p>
				</li>
				<li class="gw-dist-flow__arrow" aria-hidden="true">&rsaquo;</li>
				<li class="gw-dist-flow__step">
					<span class="gw-dist-flow__ic">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1.5"/><rect x="3" y="10" width="18" height="5" rx="1.5"/><rect x="3" y="16" width="18" height="4" rx="1.5"/></svg>
					</span>
					<strong>Batches to you</strong>
					<p>Stock and points are assigned to your account in batches you can track any time.</p>
				</li>
				<li class="gw-dist-flow__arrow" aria-hidden="true">&rsaquo;</li>
				<li class="gw-dist-flow__step">
					<span class="gw-dist-flow__ic">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M9 10.5h4.2a1.8 1.8 0 0 1 0 3.6H9"/></svg>
					</span>
					<strong>Points credited</strong>
					<p>Every sale and re-order credits points to your balance, for you and your network.</p>
				</li>
				<li class="gw-dist-flow__arrow" aria-hidden="true">&rsaquo;</li>
				<li class="gw-dist-flow__step">
					<span class="gw-dist-flow__ic">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg>
					</span>
					<strong>Rewards &amp; rank</strong>
					<p>Points unlock better margins, bonuses and higher distributor ranks as you grow.</p>
				</li>
			</ol>
		</div>
	</section>

	<section class="gw-section gw-dist-tiers">
		<div class="gw-container">
			<div class="gw-dist-head">
				<span class="gw-eyebrow">Choose your level</span>
				<h2>Start where it suits you &mdash; grow at your own pace</h2>
				<p>Pick the partnership level that fits your budget and goals. Indicative investment ranges are shown; talk to us for current package details.</p>
			</div>
			<div class="gw-dist-grid">
				<article class="gw-dist-card">
					<span class="gw-dist-card__badge">Stockist</span>
					<p class="gw-dist-card__price">KSh 50K &ndash; 200K</p>
					<p class="gw-dist-card__desc">Stock and sell from home with distributor pricing and healthy margins.</p>
					<ul class="gw-dist-card__list">
						<li>30&ndash;40% distributor discount</li>
						<li>Sell to customers and consultants</li>
						<li>No franchise fees or royalties</li>
						<li>Training and marketing support</li>
					</ul>
				</article>
				<article class="gw-dist-card gw-dist-card--featured">
					<span class="gw-dist-card__flag">Most popular</span>
					<span class="gw-dist-card__badge">Service Center</span>
					<p class="gw-dist-card__price">KSh 300K &ndash; 800K</p>
					<p class="gw-dist-card__desc">Run an official branded Green World outlet with territory rights.</p>
					<ul class="gw-dist-card__list">
						<li>Official branding and signage</li>
						<li>Exclusive local territory</li>
						<li>Priority stock and support</li>
						<li>Training for you and your staff</li>
					</ul>
				</article>
				<article class="gw-dist-card">
					<span class="gw-dist-card__badge">Regional Distributor</span>
					<p class="gw-dist-card__price">KSh 1M &ndash; 5M</p>
					<p class="gw-dist-card__desc">Supply stockists and centers across a region with the best margins.</p>
					<ul class="gw-dist-card__list">
						<li>Exclusive regional rights</li>
						<li>Highest volume margins</li>
						<li>Direct line to head office</li>
						<li>Priority product allocation</li>
					</ul>
				</article>
			</div>
			<p class="gw-dist-tiers__note">Prefer to start small? You can register as a distributor first and upgrade your level any time.</p>
		</div>
	</section>

	<section class="gw-section gw-dist-benefits">
		<div class="gw-container">
			<div class="gw-dist-head">
				<span class="gw-eyebrow">Why partner with us</span>
				<h2>Everything you need to build with confidence</h2>
			</div>
			<div class="gw-dist-benes">
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span><strong>Trusted global brand</strong><p>30+ years of history and a loyal customer base in 40+ countries.</p></div>
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M9 10.5h4.2a1.8 1.8 0 0 1 0 3.6H9"/></svg></span><strong>Distributor pricing</strong><p>Buy the full range at discounted rates and keep the margin on every sale.</p></div>
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2z"/><path d="M8 13h8M8 17h5"/></svg></span><strong>Points and rewards</strong><p>Earn points on every product and unlock bonuses as your balance grows.</p></div>
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14v7M5 11v4c0 1.5 3 3 7 3s7-1.5 7-3v-4"/></svg></span><strong>Training and guidance</strong><p>Product knowledge, selling skills and ongoing coaching from our team.</p></div>
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18M8 21h8"/></svg></span><strong>Online account tools</strong><p>Track batches, points, orders and your network from any device.</p></div>
				<div class="gw-dist-bene"><span class="gw-dist-bene__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg></span><strong>Work flexibly</strong><p>Build your business online and offline, full time or alongside other work.</p></div>
			</div>
		</div>
	</section>

	<section class="gw-section gw-dist-steps">
		<div class="gw-container">
			<div class="gw-dist-head">
				<span class="gw-eyebrow">Getting started</span>
				<h2>Five simple steps to launch</h2>
			</div>
			<ol class="gw-dist-timeline">
				<li><span class="gw-dist-timeline__n">1</span><div><strong>Register your interest</strong><p>Complete the short distributor form below.</p></div></li>
				<li><span class="gw-dist-timeline__n">2</span><div><strong>Talk with our team</strong><p>We review your details and call to answer your questions.</p></div></li>
				<li><span class="gw-dist-timeline__n">3</span><div><strong>Choose your level</strong><p>Pick the package and territory that fit your goals.</p></div></li>
				<li><span class="gw-dist-timeline__n">4</span><div><strong>Get set up and trained</strong><p>Receive your first batch, points setup and product training.</p></div></li>
				<li><span class="gw-dist-timeline__n">5</span><div><strong>Start earning</strong><p>Begin selling, grow your network and watch your points build.</p></div></li>
			</ol>
		</div>
	</section>

	<section class="gw-section gw-dist-register" id="register">
		<div class="gw-container gw-dist-register__inner">
			<div class="gw-dist-register__copy">
				<span class="gw-eyebrow">Ready to begin</span>
				<h2>Register as a Green World distributor</h2>
				<p>Fill in your details and our team will reach out to help you get started. It takes a couple of minutes and there is no obligation.</p>
				<ul class="gw-dist-contact">
					<li><span>WhatsApp or call</span><a href="<?php echo esc_url( $gw_wa_url ); ?>">0723 579 873</a></li>
					<li><span>Email</span><a href="mailto:info@greenworldhealth.co.ke">info@greenworldhealth.co.ke</a></li>
					<li><span>Hours</span>Mon &ndash; Sat, 8:30 AM &ndash; 6:00 PM</li>
				</ul>
			</div>
			<div class="gw-dist-register__form">
				<?php echo do_shortcode( '[gw_register type="distributor"]' ); ?>
			</div>
		</div>
	</section>

	<section class="gw-section gw-dist-faq">
		<div class="gw-container gw-dist-faq__inner">
			<div class="gw-dist-head">
				<span class="gw-eyebrow">Good to know</span>
				<h2>Distributor questions, answered</h2>
			</div>
			<details class="gw-dist-q"><summary>How do the points actually work?</summary><p>Each product is assigned a point value by our team. When you or someone in your network orders, those points are credited to your distributor account in batches. Points build your earnings and move you up the reward ranks.</p></details>
			<details class="gw-dist-q"><summary>How much do I need to start?</summary><p>You can begin as a stockist from a modest amount and upgrade later. The tiers above show indicative ranges; contact us for current package pricing.</p></details>
			<details class="gw-dist-q"><summary>Do I need experience?</summary><p>No. We provide product training, selling guidance and ongoing support so you can start with confidence.</p></details>
			<details class="gw-dist-q"><summary>Are the products genuine?</summary><p>Yes. Every item is a genuine Green World product sourced through the group supply chain, never counterfeit or grey-market stock.</p></details>
			<p class="gw-dist-faq__legal"><em>Registering as a distributor is subject to approval and does not guarantee any income. Earnings depend on your own effort and sales.</em></p>
		</div>
	</section>

</main>
<?php
get_footer();

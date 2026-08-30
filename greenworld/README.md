# GreenWorld Wellness — Premium Health & Wellness WooCommerce Theme

A classic, premium, mobile-first WooCommerce theme built for **Green World Health Solutions** (Kenya). Timeless botanical-green + ivory design system, editorial typography (Fraunces + Inter), a health mega-menu, AJAX search, cart drawer, dual **Customer / Distributor** registration, an online **Health Consultation** intake, responsible health communication, Core Web Vitals performance and WCAG 2.1 AA accessibility.

Engineered on the same battle-tested architecture as our other stores (PSR-4 module container, WooCommerce hooks, security headers, SEO schema) — reimagined for health and wellness.

## Requirements
- PHP 8.0+, WordPress 6.4+, WooCommerce 8.0+
- Recommended: a Kenyan payment plugin (M-Pesa via a WooCommerce gateway), and optionally Contact Form 7 for the newsletter form.

## Install
1. Zip the `greenworld` folder and upload it via **Appearance → Themes → Add New → Upload Theme**, then **Activate**.
2. (Recommended) Also upload and activate **`greenworld-child`** — put all your customizations there so they survive updates.
3. On activation the theme runs a one-time setup: it creates the standard pages (About, Contact, Policies, FAQ, Health Disclaimer, Become a Distributor, Health Consultation, Track Order), sets a static homepage, builds the footer menus, and applies WooCommerce basics for Kenya (KES currency, Pay on Delivery, guest checkout, registration enabled).
4. Install **WooCommerce** if it is not active yet, then import products (CSV or the built-in demo).

## Setup wizard
On activation you are guided to **Appearance → GreenWorld Setup**: install required plugins → activate → import demo content → finish. You can re-run demo import safely; it skips a store that already has products.

## Configure everything without a developer
All store content is editable under **Appearance → Customize → GreenWorld Wellness**:
- **Header & Contact** — phone, WhatsApp, email, hours, address, top-bar message, WhatsApp order message, delivery note, Google reviews URL.
- **Branding & Colours** — botanical green, deep green (headings) and brass accent. The whole theme retints instantly.
- **Homepage Hero** — background image, eyebrow, heading and supporting text.
- **Health Disclaimer** — the responsible statement shown site-wide and on product pages.
- **Logo & Favicon** — set under Customize → Site Identity. A refined text/leaf lockup is used until you upload a logo.

### Fonts
Headings use **Fraunces** (a classic editorial serif) and body uses **Inter**, loaded from Google Fonts with `preconnect` + `display=swap` for fast, non-blocking rendering.

## Menus (Appearance → Menus)
Menu locations: Primary, Health Categories (Mega Menu), Top Utility Bar, Mobile Bottom Navigation, and three footer menus (Information, Customer Service, Health Categories). The primary navigation and mega menu are also generated automatically from your WooCommerce product categories, so the header looks complete from day one.

## Products (health specifics)
Each product has two extra fields under **Product data → General**:
- **Ingredients / Composition** — shown as a product tab.
- **How to Use** — directions for use, shown as a product tab.

Only enter accurate, supplied information. The theme never invents medical claims. Merchant-Center-friendly fields (brand, GTIN, MPN) are also available for Google Shopping.

## Customer & Distributor registration
Shoppers register from **My Account** (or the homepage/utility links) and choose:
- **Customer** — faster checkout, order tracking, wishlist.
- **Distributor** — assigned the **Distributor** role, with an optional Sponsor/Referral ID; the store owner is emailed to follow up. Review applicants under **Users** (Account type column) or on the **Become a Distributor** page.

No pricing/commission logic is hard-coded — the theme records intent and applicant details and assigns a role.

## Online Health Consultation
The **Free Health Consultation** page ( `[gw_health_consultation]` ) lets customers describe a health concern. Submissions are:
- stored privately under **Consultations** in wp-admin (visible to shop managers only),
- gated by an explicit consent checkbox,
- emailed to the store owner, and
- framed clearly as guidance, not a diagnosis or emergency service.

## Track order
The **Track Your Order** page uses WooCommerce's native order tracking. A `Track Your Order` page template is also included if you prefer to assign it manually.

## Payments & delivery
- Payments are handled by standard WooCommerce gateways — nothing is hard-coded. Add M-Pesa via a compatible WooCommerce plugin; Cash/Pay on Delivery is enabled by default.
- Set delivery zones and rates under **WooCommerce → Settings → Shipping**. Edit the product-page delivery note in the Customizer.

## Performance, SEO, accessibility, security
- **Performance:** one lean compiled CSS file, deferred vanilla JS (no framework), lazy images with explicit dimensions, preconnected fonts, preloaded hero — engineered for a 1–3s load and strong Core Web Vitals.
- **SEO:** semantic HTML5, JSON-LD (Organization, WebSite + SearchAction, Store, Product, Breadcrumb) that yields to Yoast / Rank Math / AIOSEO when active.
- **Accessibility:** keyboard-navigable menus and drawers, visible focus states, ARIA labels, sufficient contrast, reduced-motion support.
- **Security:** output escaping, input sanitization, nonces on AJAX, and OWASP-style response headers.

## Architecture
- `functions.php` — PSR-4 autoloader + boots `GreenWorld\Core\Theme`, plus fonts, brand CSS variables, starter content and the mega-menu renderer.
- `inc/Core/` — container (`Theme`), `Bootable` contract, `Assets`.
- `inc/Setup/` — `Supports`, `Menus`, setup wizard, plugin installer, demo importer.
- `inc/Account/Registration.php` — Customer/Distributor registration + role.
- `inc/Front/` — `Home` (homepage sections), `Consultation` (health intake), `Trust` (trust badges).
- `inc/Woo/` — WooCommerce presentation (badges, wishlist, trust, delivery, ingredients/how-to-use tabs, sticky ATC), Filters, QuickView (WhatsApp order), Merchant identifiers.
- `inc/Seo/` — `Schema` (JSON-LD) + `Meta` (OG/Twitter).
- `inc/Performance/`, `inc/Security/`, `inc/Admin/`, `inc/Compat/`, `inc/Support/`.
- `theme.json` — global design system (colours, fluid type, spacing).
- `assets/` — `css/main.css`, `js/app.js` (framework-free).
- `starter/` and `demo/` — pages, categories and products created on setup/import.

## Extending (child theme)
Never edit the parent. Use `greenworld-child` and these filters:
- `greenworld_social_profiles` — array of social URLs for schema.
- `greenworld_opening_hours` — opening-hours spec for schema.
- `greenworld_disable_schema` — return true to hand schema fully to your SEO plugin.

## Credits
Design & build by Green World Health Solutions. Fonts: Fraunces & Inter (Google Fonts, OFL).

## v1.29.2

- Fixed the legal / policy pages (Privacy Policy, Terms & Conditions, Returns & Refunds, Shipping & Delivery) showing only the theme's short built-in copy and ignoring the content you type into the page in wp-admin. The page-{slug}.php templates call the Trust Center renderers, which previously always printed the default text and never rendered the page's own content.
- Fix: the Privacy, Terms and Returns renderers now show the page's own editor content in place of the built-in default whenever the page has any (falling back to the default when the page is empty); the Shipping page appends your editor content below the delivery and payment details. All keep the curated header and the Chat on WhatsApp / Email us buttons. Content is rendered through the standard `the_content` filter, so shortcodes, blocks and formatting all work.

## v1.29.1

- Fixed the desktop header showing three stray account links (Sign in / Register as Customer / Become a Distributor) as faint text below the main menu. They come from the `.gw-drawer-extra` block, which is meant only for the mobile slide-out drawer, but nothing was hiding it on desktop.
- Fix (in the inline critical-nav CSS so it is delivered reliably): hide `.gw-drawer-extra` on desktop and show it only inside the mobile drawer. Desktop account access is unchanged (top utility bar Sign in / Join Green World, plus the Account icon).

## v1.29.0

- Introduced the **Green World Core** companion plugin (`greenworld-core/`) so business data (scan bookings now, and customer/distributor records + points in later phases) lives independently of the theme and survives theme updates or switches.
- **Book your scan** is now a booking form (name, phone, preferred date/time, location, note). Each booking is saved under a new "Scan Bookings" admin list AND sent automatically to staff on WhatsApp (Meta Cloud API) when configured. The scan band gracefully falls back to the previous WhatsApp button if the plugin is inactive.
- **Consultations** now also forward a copy to staff on WhatsApp on submit; the existing admin record and email notification are unchanged. Wired via a new `greenworld/consultation_submitted` action hook.

## v1.28.0

- Fixed single-product gallery horizontal overflow on Chrome / Samsung Internet (hero image spilling off the right edge on mobile). Root cause: the v1.26 CSS gallery made the hero `__image` (and its `<a>`) flex containers, and flexbox `min-width:auto` stops a flex item shrinking below its content's intrinsic width — so an 800px+ product image refused to shrink on a ~380px phone and overflowed. (Chromium browsers still showing the pre-v1.26 cached bundle, e.g. Brave, were unaffected.)
- Fix in `WooCommerce::critical_product_css()`: the hero is now block-based (`display:block; text-align:center`, inline-block centered image) instead of flex; added `min-width:0` to every gallery flex item and to all product grid cells; `box-sizing:border-box` where padding meets `width:100%`; and `overflow:hidden` on `.woocommerce-product-gallery` as a guard so nothing can ever spill outside the column again.
- Product summary column: added `min-width:0` + `overflow-wrap:break-word` so long descriptions can't clip at the right edge.


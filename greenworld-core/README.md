# Green World Core

Companion plugin for the GreenWorld theme. It holds the business logic that must
survive a theme change: WhatsApp notifications, scan bookings, the customer and
distributor dashboards, and the distributor points ledger.

Keeping this in a plugin — not the theme — means your bookings, customer records,
distributor status, and points ledger are **not** lost if the theme is updated,
switched, or rebuilt.

## What ships in v0.1.0 (Phase 1)

- **WhatsApp Cloud API sender** with a settings screen (Settings -> Green World).
- **Scan bookings**: `[gw_scan_form]` shortcode + a "Scan Bookings" admin list.
  Each booking is saved as a private record **and** sent to staff on WhatsApp.
- **Consultation bridge**: when the theme's consultation form is submitted, a copy
  is forwarded to staff on WhatsApp. The existing admin record + email are kept.

## What ships in v0.2.0 (Phase 2)

- **Customer health dashboard** on a new **My Health** tab in WooCommerce "My Account":
  - Lists the customer's purchased products.
  - **Request a refill or a change** of product — saved under "Refill Requests" in
    admin; staff are alerted on WhatsApp + email; each request has an Open/Handled
    status you can set.
  - **Progress check-ins** ("Doing well / Managing okay / Need help", with an
    optional product + note) — saved under "Check-ins".
  - **Message our team** — a two-way thread saved under "Customer Messages". Staff
    reply from the thread's comments in wp-admin; the customer is emailed each reply
    and sees it on the dashboard.
- The new "My Health" endpoint auto-registers on update (rewrite rules flush once
  per version), so no manual permalink re-save is needed after upgrading.

## What ships in v0.3.0 (Phase 3)

- **Distributor dashboard** on a new **Distributor** tab in WooCommerce "My Account"
  (shown only to distributor accounts):
  - **Status** — pending, active, or on hold, with a short explanation.
  - **Your details** — name, phone, county/town, and who referred them.
  - **Referral code** (once active) — a stable `GW#####` code plus a ready-made
    sign-up link, and a count of people who registered with it.
  - **Points** — current balance with a placeholder note; the earning ledger
    arrives in Phase 4.
- **Admin activation** at **Users -> Distributors**: review every applicant with
  their phone, county, sponsor and status, then **Activate** or **Put on hold**
  with one click. Activating issues the referral code and notifies the distributor
  on WhatsApp (if configured) + email.
- Registration itself (the Customer/Distributor toggle and the initial "pending"
  status) stays in the theme; the plugin reads the same user meta, so the workflow
  survives a theme change. The **Distributor** endpoint auto-registers on update
  (rewrite rules flush once per version).

## What ships in v0.4.0 (Phase 4)

- **Product point values**: each product gets a **Distributor points** field (product
  editor, General tab) — the points a distributor earns per unit when it is
  allocated to them.
- **Batch allocation** at **Users -> Allocate Batch**: pick a distributor, add
  product lines with quantities (plus an optional manual +/- adjustment and note),
  and allocate. Points = product point value x quantity, summed across the batch.
- **Points ledger**: every allocation is stored as a private **Point Batch** record
  (Users -> Point Batches) with a read-only breakdown. A distributor's balance is
  the sum of their batch totals — recomputed on every change, so it can never drift.
- **Distributor dashboard**: the points card now shows the live balance plus a
  history of recent batches (points, date, item summary).
- Allocating can **notify the distributor** on WhatsApp (if configured) + email.

## Install / deploy

This plugin lives in the repository at `greenworld-core/`, next to the `greenworld`
and `greenworld-child` theme folders.

1. Copy (or symlink) the `greenworld-core/` folder into `wp-content/plugins/` on the
   server, so the path is `wp-content/plugins/greenworld-core/greenworld-core.php`.
2. In wp-admin go to **Plugins** and activate **Green World Core**.
3. Flush permalinks once (Settings -> Permalinks -> Save) if the "Scan Bookings"
   menu does not appear immediately.

The plugin degrades gracefully: with it deactivated, the theme falls back to the
old "Book your scan" WhatsApp button, and consultations still save + email as before.

## Configure WhatsApp (Meta Cloud API)

You need a free Meta WhatsApp Cloud API app. High level:

1. Go to <https://developers.facebook.com/> -> create an app -> add the
   **WhatsApp** product.
2. In **WhatsApp -> API Setup**, note the **Phone number ID** and generate an
   **access token**. For production, create a **System User** token so it does not
   expire (temporary tokens last ~24 hours and are for testing only).
3. Add and verify the business phone number you want messages sent **from**.
4. In wp-admin -> **Settings -> Green World**, fill in:
   - **Enable WhatsApp alerts**: ticked
   - **Access token**: the (permanent/system-user) token
   - **Phone number ID**: from API Setup
   - **API version**: leave as `v21.0` unless Meta tells you otherwise
   - **Staff recipient numbers**: the staff WhatsApp number(s) that should receive
     bookings/consultations, full international format, digits only
     (e.g. `254723579873`), comma-separated for more than one.
5. Save. The **Last WhatsApp error** box on that screen shows the most recent API
   error (blank means the last send was accepted).

### Important: the 24-hour window

Meta only lets a business send **free-form text** to a number that has messaged the
business number within the last 24 hours. For staff alerts, the simplest reliable
setup is: **have each staff recipient send any message to the business WhatsApp
number** (this opens a rolling 24-hour window as long as they stay in contact).

To message staff reliably outside that window you must use an **approved message
template**. Create one in Meta (WhatsApp Manager -> Message templates), then enter
its **Template name** and **language** on the settings screen. (Template send is
scaffolded for a later release; plain text covers the in-window case now.)

## Data model (for maintainers)

- Scan bookings: post type `gw_scan` (private), meta keys `_gw_s_name`,
  `_gw_s_phone`, `_gw_s_date`, `_gw_s_time`, `_gw_s_location`, `_gw_s_note`.
- Settings: option `gwc_settings` (array). Last WhatsApp error: option
  `gwc_wa_last_error`.
- Distributors: user meta `_gw_account_type`, `_gw_phone`, `_gw_county`,
  `_gw_sponsor`, `_gw_distributor_status` (pending|active|suspended),
  `_gw_ref_code`, `_gw_distributor_activated`, `_gw_points_balance`. Role
  `gw_distributor`. Admin activation hook: `admin_post_gwc_dist_action`;
  My Account endpoint: `distributor`.
- Point batches: post type `gw_batch` (private), meta `_gw_b_user`, `_gw_b_points`,
  `_gw_b_items` (array), `_gw_b_adjust`, `_gw_b_note`, `_gw_b_by`. Product point
  value: product meta `_gw_points`. Allocation hook: `admin_post_gwc_allocate_batch`.
  Balance `_gw_points_balance` is recomputed from the batch ledger on each change.
- Theme hook consumed: `do_action( 'greenworld/consultation_submitted', $data )`.
- Hook fired: `do_action( 'greenworld/scan_booked', $post_id )`.

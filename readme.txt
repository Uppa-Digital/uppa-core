=== UPPA Core ===
Contributors:      uppadigital
Tags:              agency, utility, payments, paystack, flutterwave
Requires at least: 6.4
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Companion functionality plugin for the UPPA Base parent theme — CPT management,
ACF bridge, Paystack & Flutterwave payments, and shared utilities.

== Description ==

UPPA Core is the companion plugin for the UPPA Base parent theme, developed and
maintained by Upper Echelon Digital Services. It provides the shared back-end
infrastructure used across every WordPress site built by the agency.

= Features =

**Dynamic CPT Registration**
Register Custom Post Types and Taxonomies with a single array config call —
labels are generated automatically, REST API support is on by default, and
every type is available from the `init` action at priority 0.

**ACF Bridge**
Wraps Advanced Custom Fields (Free or Pro) with a graceful fallback layer.
Templates that call `UPPA_ACF_Bridge::get_instance()->get_field()` continue to
work via `get_post_meta()` when ACF is not installed — no conditional checks
needed in theme files.

**Payment Gateways**
Native Paystack and Flutterwave integrations for custom checkout flows,
donation forms, and event registration pages — without WooCommerce as a
dependency. API keys are managed from the plugin settings screen.

**Image Utilities**
* Lazy-loading `<img>` tags with `loading="lazy"` and `decoding="async"` by default.
* CSS `mix-blend-mode: multiply` inline background removal for product images.
* `srcset` generation from registered image sizes.
* WebP MIME type support for uploads.
* Base64 SVG placeholder images for CLS-free lazy loading.

**SEO Utilities**
* Structured breadcrumb data (no HTML output — render it your way).
* Page meta title with Rank Math / Yoast fallback chain.
* JSON-LD Organisation and BreadcrumbList schema blocks.

**Asset Utilities**
* Google Fonts enqueue (CSS2 API, `display=swap`, automatic preconnect hint).
* Local `@font-face` enqueue via inline styles.
* `<link rel="preload">` resource hints for LCP images and critical fonts.
* Child-theme-first asset URL resolution mirroring `get_template_part()` logic.

= Works best with =

The [UPPA Base](https://uppadigital.com) parent theme. The theme checks for
`function_exists('uppa_core_active')` and degrades gracefully when the plugin
is absent.

= Privacy =

UPPA Core does not collect or transmit any personal data on its own. Payment
data passes directly between your server and Paystack / Flutterwave — see
their respective privacy policies for how they handle that data.

== Installation ==

**From the WordPress admin**

1. Go to **Plugins → Add New Plugin**.
2. Search for "UPPA Core".
3. Click **Install Now**, then **Activate**.

**Manual installation**

1. Download the plugin zip from the [plugin page](https://wordpress.org/plugins/uppa-core/).
2. Upload the `uppa-core` folder to `/wp-content/plugins/` via FTP or the
   **Plugins → Add New Plugin → Upload Plugin** screen.
3. Activate the plugin through the **Plugins** screen.

**After activation**

Navigate to **UPPA Core → Settings** in the admin sidebar and enter your
Paystack and Flutterwave API keys. Public keys are passed to the front-end JS
automatically; secret keys are stored server-side only.

== Frequently Asked Questions ==

= Does this plugin require the UPPA Base theme? =

No. UPPA Core is a standalone plugin and functions independently of any theme.
Some features (such as the 14 action hook integration points) are only
meaningful alongside UPPA Base, but all utilities, payment integrations, and
the CPT manager work with any theme.

= Does this plugin require Advanced Custom Fields? =

No. The ACF Bridge detects whether ACF (Free or Pro) is active and wraps every
ACF function call in a `function_exists()` check. When ACF is absent, field
reads fall back to `get_post_meta()` automatically — your templates will not
produce errors or blank values.

= Which Paystack currencies are supported? =

Paystack currently supports NGN (Nigerian Naira), GHS (Ghanaian Cedi),
ZAR (South African Rand), and USD. Amounts must be passed in the smallest
currency unit — multiply naira by 100 to get kobo before calling the API.

= Which Flutterwave currencies are supported? =

Flutterwave supports over 30 currencies including NGN, GHS, KES, UGX, TZS,
ZAR, USD, EUR, and GBP. Unlike Paystack, Flutterwave accepts amounts in the
major currency unit (pass naira directly — do not convert to kobo).

= Is WooCommerce required for payments? =

No. The Paystack and Flutterwave integrations are standalone wrappers designed
for custom payment forms. They do not depend on WooCommerce and provide no
WooCommerce gateway classes.

= How do I register a Custom Post Type? =

Call `UPPA_CPT_Manager::register()` from your theme's `functions.php` or a
feature plugin — before or during the `init` action:

    UPPA_CPT_Manager::register([
        'post_type'   => 'project',
        'singular'    => 'Project',
        'plural'      => 'Projects',
        'icon'        => 'dashicons-portfolio',
        'supports'    => ['title', 'editor', 'thumbnail', 'excerpt'],
        'public'      => true,
        'has_archive' => true,
        'rewrite'     => ['slug' => 'projects'],
    ]);

Labels, REST API support, and sensible defaults are applied automatically.

= How do I load a Google Font? =

Hook into `wp_enqueue_scripts` and call:

    UPPA_Asset_Utils::enqueue_google_font( 'Inter', ['400', '500', '700'] );

A `<link rel="preconnect">` to `fonts.gstatic.com` is added automatically.

= Where are the payment API keys stored? =

All four keys (Paystack public + secret, Flutterwave public + secret) are
stored in a single WordPress option (`uppa_core_settings`). Secret keys are
never echoed back into the admin form HTML. Alternatively, define the PHP
constants `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `FLW_SECRET_KEY`, or
`FLW_PUBLIC_KEY` in `wp-config.php` and they will take precedence over the
database values — useful for environment-specific key management.

== Screenshots ==

1. The UPPA Core dashboard showing theme status, ACF detection, and registered CPTs.
2. The Settings screen for managing Paystack and Flutterwave API keys.

== Changelog ==

= 1.0.0 — 2024-01-01 =
* Initial release.
* Dynamic CPT and Taxonomy registration via `UPPA_CPT_Manager`.
* ACF Bridge with `get_field()` and `get_field_object()` fallbacks.
* Paystack integration: `initialize_transaction()` and `verify_transaction()`.
* Flutterwave integration: `initialize_payment()` and `verify_transaction()`.
* Image utilities: responsive images, CSS blend-mode BG removal, srcset, WebP, SVG placeholder.
* SEO utilities: breadcrumb data, meta title (Rank Math / Yoast / core), JSON-LD Organisation and BreadcrumbList schema.
* Asset utilities: Google Fonts (CSS2), local `@font-face`, `<link rel="preload">`, child-theme-first asset URLs.
* Admin settings page with WordPress Settings API (nonce-verified, capability-gated).
* Front-end AJAX handlers for payment initialisation and verification.
* GitHub Actions: PHPCS + ESLint on pull requests; GitHub Release + WP.org SVN deploy on version tags.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

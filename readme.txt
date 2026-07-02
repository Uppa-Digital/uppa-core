=== UPPA Core ===
Contributors:      uppadigital
Tags:              agency, utility, payments, paystack, flutterwave
Requires at least: 6.4
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Companion functionality plugin for the UPPA Base parent theme — payment forms,
webhooks, CPT management, ACF bridge, SEO schema, and shared utilities.

== Description ==

UPPA Core is the companion plugin for the UPPA Base parent theme, developed and
maintained by Upper Echelon Digital Services. It provides the shared back-end
infrastructure used across every WordPress site built by the agency.

= Payment Forms (Zero Configuration) =

Drop a Paystack or Flutterwave payment form anywhere with a single shortcode:

    [uppa_pay gateway="paystack" amount="5000" label="Pay Now"]
    [uppa_pay gateway="flutterwave" redirect_url="/thank-you/"]

Amount is in the major currency unit (naira, cedis, etc.). Omit `amount` to
show a user-entered field. The logged-in user's email is pre-filled
automatically. Place the verification shortcode on your callback/thank-you page:

    [uppa_verify success_message="Payment confirmed!" failure_message="Payment failed."]

The plugin verifies the transaction server-side on page load and updates the
container — no theme code required. A `uppa:payment-verified` CustomEvent fires
on `window` so themes can hook in additional behaviour.

= Webhooks (Server-Side Confirmation) =

Reliable payment confirmation that does not depend on the customer's browser
returning to your site. Configure the URLs in each gateway's dashboard:

* **Paystack** — `https://yoursite.com/wp-json/uppa-core/v1/webhooks/paystack`
* **Flutterwave** — `https://yoursite.com/wp-json/uppa-core/v1/webhooks/flutterwave`

Both endpoints verify the gateway's signature header before processing. Listen
in your theme or feature plugin:

    add_action( 'uppa_paystack_webhook', function( array $event ) {
        if ( 'charge.success' === $event['event'] ) {
            // mark order as paid, send confirmation email, etc.
        }
    } );

= Dynamic CPT Registration =

Register Custom Post Types and Taxonomies with a single array config call —
labels are generated automatically, REST API support is on by default, and
every type is available from the `init` action at priority 0.

    UPPA_CPT_Manager::register([
        'post_type' => 'project',
        'singular'  => 'Project',
        'plural'    => 'Projects',
        'icon'      => 'dashicons-portfolio',
        'public'    => true,
    ]);

= ACF Bridge =

Wraps Advanced Custom Fields (Free or Pro) with a graceful fallback layer.
Templates that call `UPPA_ACF_Bridge::get_instance()->get_field()` continue to
work via `get_post_meta()` when ACF is not installed — no conditional checks
needed in theme files.

= Automatic Front-End Enhancements =

Active as soon as the plugin is installed, no configuration required:

* `loading="lazy"` + `decoding="async"` injected on all WordPress attachment
  images globally. Individual images can override with `loading="eager"`.
* WebP MIME type added to allowed uploads.
* `schema.org/Organization` JSON-LD emitted in `<head>` on every page,
  sourcing the site name, URL, and Custom Logo automatically.
* `schema.org/BreadcrumbList` JSON-LD emitted on singular, archive, search,
  and 404 pages. Both schema blocks are filterable off:
  `add_filter( 'uppa_core_output_organization_schema', '__return_false' );`

= Image Utilities =

* Lazy-loading `<img>` tags with `loading="lazy"` and `decoding="async"`.
* CSS `mix-blend-mode: multiply` inline background removal for product images.
* `srcset` generation from registered image sizes.
* Base64 SVG placeholder images for CLS-free lazy loading.

= SEO Utilities =

* Structured breadcrumb data array (render it your way).
* Page meta title with Rank Math / Yoast fallback chain.
* JSON-LD Organisation and BreadcrumbList schema helpers.

= Asset Utilities =

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

1. Go to **UPPA Core → Settings** and enter your Paystack and/or Flutterwave
   API keys. Public keys are passed to the front-end JS automatically; secret
   keys are stored server-side only.
2. Set your **Default Currency** (NGN, GHS, KES, etc.).
3. *(Optional)* Copy the webhook URLs shown on the Settings page and paste them
   into each gateway's dashboard to enable server-side payment confirmation.

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

= How do I add a payment form to a page? =

Use the `[uppa_pay]` shortcode in any page, post, or widget:

    [uppa_pay gateway="paystack" amount="5000" currency="NGN" label="Pay ₦5,000"]

On the gateway's callback/return page, add:

    [uppa_verify success_message="Thank you!" failure_message="Payment failed."]

= Which Paystack currencies are supported? =

Paystack currently supports NGN (Nigerian Naira), GHS (Ghanaian Cedi),
ZAR (South African Rand), and USD. The `[uppa_pay]` shortcode accepts amounts
in the major currency unit — the plugin converts to the smallest unit (kobo,
pesewas, cents) automatically before calling the Paystack API.

= Which Flutterwave currencies are supported? =

Flutterwave supports over 30 currencies including NGN, GHS, KES, UGX, TZS,
ZAR, USD, EUR, and GBP. The `[uppa_pay]` shortcode passes amounts in the major
currency unit directly — no conversion is applied (Flutterwave requires this).

= Is WooCommerce required for payments? =

No. The Paystack and Flutterwave integrations are standalone wrappers designed
for custom payment forms. They do not depend on WooCommerce and provide no
WooCommerce gateway classes.

= How do webhooks work? =

After a customer pays, the gateway sends a signed POST request to your webhook
URL. UPPA Core verifies the signature and fires a WordPress action hook
(`uppa_paystack_webhook` or `uppa_flutterwave_webhook`) with the event payload.
This is more reliable than the browser-based callback flow because it works even
if the customer closes their browser before returning to your site.

Copy the webhook URLs from **UPPA Core → Settings** and paste them into your
gateway dashboard. For Flutterwave you also need to set a Webhook Secret Hash
in both places.

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

All keys (Paystack public + secret, Flutterwave public + secret + webhook hash)
are stored in a single WordPress option (`uppa_core_settings`). Secret keys are
never echoed back into the admin form HTML. Alternatively, define the PHP
constants `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY`, `FLW_SECRET_KEY`, or
`FLW_PUBLIC_KEY` in `wp-config.php` and they will take precedence over the
database values — useful for environment-specific key management.

= Can I disable the automatic JSON-LD schema output? =

Yes. Add these filters anywhere before `wp_head` runs:

    add_filter( 'uppa_core_output_organization_schema', '__return_false' );
    add_filter( 'uppa_core_output_breadcrumb_schema',   '__return_false' );

= Can I disable the automatic lazy-loading on images? =

The lazy-loading filter runs on `wp_get_attachment_image_attributes`. Individual
images can override it by passing `loading="eager"` as an attribute. To disable
it site-wide, remove the filter after the plugin initialises:

    add_action( 'plugins_loaded', function() {
        remove_filter( 'wp_get_attachment_image_attributes',
            [ UPPA_Core::get_instance()->get_public(), 'add_lazy_loading_defaults' ] );
    }, 20 );

== Screenshots ==

1. The UPPA Core dashboard showing theme status, ACF detection, gateway key
   status, and registered CPTs.
2. The Settings screen for managing gateway API keys, webhook URLs, and default
   currency.

== Changelog ==

= 1.0.0 — 2024-01-01 =
* Initial release.
* Dynamic CPT and Taxonomy registration via `UPPA_CPT_Manager`.
* ACF Bridge with `get_field()` and `get_field_object()` fallbacks.
* Paystack integration: `initialize_transaction()` and `verify_transaction()`.
* Flutterwave integration: `initialize_payment()` and `verify_transaction()`.
* `[uppa_pay]` shortcode for zero-config inline payment forms.
* `[uppa_verify]` shortcode for automatic server-side payment verification on
  the gateway callback page.
* REST API webhook endpoints for Paystack and Flutterwave with signature
  verification and `uppa_paystack_webhook` / `uppa_flutterwave_webhook` hooks.
* Automatic front-end enhancements: global lazy-loading, WebP support,
  Organization and BreadcrumbList JSON-LD schema (both filterable off).
* Image utilities: responsive images, CSS blend-mode BG removal, srcset, WebP,
  SVG placeholder.
* SEO utilities: breadcrumb data, meta title (Rank Math / Yoast / core),
  JSON-LD Organisation and BreadcrumbList schema.
* Asset utilities: Google Fonts (CSS2), local `@font-face`,
  `<link rel="preload">`, child-theme-first asset URLs.
* Admin settings page: gateway API keys, default currency selector, Flutterwave
  webhook secret hash field, webhook URL display.
* Admin dashboard: theme status, ACF detection, gateway key/mode status,
  registered CPTs.
* GitHub Actions: PHPCS + ESLint on pull requests; GitHub Release + WP.org SVN
  deploy on version tags.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

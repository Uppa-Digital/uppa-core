=== UPPA Core ===
Contributors:      uppadigital
Tags:              cpt, acf, paystack, flutterwave, utilities
Requires at least: 6.4
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Core functionality plugin for UPPA Digital client builds — CPT management, ACF bridge, payment integrations, and shared utilities.

== Description ==

UPPA Core is the foundational plugin powering all UPPA Digital WordPress client builds. It provides:

* **CPT Manager** — a centralised place to register Custom Post Types.
* **ACF Bridge** — graceful integration with Advanced Custom Fields (degrades cleanly when ACF is absent).
* **Image Utilities** — helpers for responsive srcsets and background-removal preparation.
* **SEO Utilities** — breadcrumb data builders and meta tag helpers.
* **Asset Utilities** — versioned enqueue helpers shared across builds.
* **Paystack Integration** — initialise and verify Paystack transactions.
* **Flutterwave Integration** — initialise and verify Flutterwave payments.

== Installation ==

1. Upload the `uppa-core` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **UPPA Core** in the admin sidebar to configure settings.

== Frequently Asked Questions ==

= Does this plugin require ACF? =

No. The ACF Bridge degrades gracefully when Advanced Custom Fields is not active.

= Which payment currencies are supported? =

Paystack supports NGN, GHS, ZAR, and USD. Flutterwave supports a wider range — see their documentation for the full list.

== Changelog ==

= 1.0.0 =
* Initial release — plugin scaffold, CPT manager, ACF bridge, payment integrations, shared utilities.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

# Changelog

All notable changes to UPPA Core will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `[uppa_pay]` shortcode — renders an inline Paystack or Flutterwave payment form with no theme code required. Attributes: `gateway`, `amount` (0 = user-entered field), `currency`, `label`, `class`, `redirect_url`, `email`. Logged-in user email is pre-filled automatically.
- `[uppa_verify]` shortcode — place on the gateway callback page; automatically fires server-side payment verification on page load and updates the container with a success or failure message. Dispatches `uppa:payment-verified` CustomEvent on `window` for theme hooks.
- REST API webhook endpoints at `/wp-json/uppa-core/v1/webhooks/paystack` and `/wp-json/uppa-core/v1/webhooks/flutterwave`. Both verify the gateway signature header before processing and fire `uppa_paystack_webhook` / `uppa_flutterwave_webhook` action hooks.
- Admin settings: **Default Currency** selector (NGN, GHS, KES, UGX, TZS, ZAR, USD, EUR, GBP) used by payment forms and passed to the front-end JS as `uppaCore.currency`.
- Admin settings: **Flutterwave Webhook Secret Hash** field for verifying incoming webhook requests.
- Auto-wired front-end enhancements: `loading="lazy"` + `decoding="async"` on all WordPress attachment images; WebP MIME type support; Organization and BreadcrumbList JSON-LD schema on `wp_head` (both filterable off via `uppa_core_output_organization_schema` / `uppa_core_output_breadcrumb_schema`).
- Admin dashboard **Payment Gateways** card showing key status and detected mode (Live/Test) per gateway.
- Admin notice for non-UPPA-Base themes is now permanently dismissible per user via AJAX.
- `uppa_core_active()` sentinel function — detected by the UPPA Base theme via `function_exists()`.

### Fixed
- Settings `sanitize_callback` now preserves existing secret keys and webhook hashes when fields are submitted blank (prevents inadvertent key deletion on save).
- Bootstrap moved to `plugins_loaded` action to prevent activation fatals in some environments.
- `deactivate_plugins()` path in `UPPA_Activator` corrected.
- `uppa_core_active()` wrapped in `function_exists()` guard to prevent redeclaration fatal when UPPA Base theme also defines the function.

## [1.0.0] - 2024-01-01

### Added
- Plugin scaffold with main entry file, activator, deactivator, and uninstall handler.
- `Uppa_Loader` — centralised action/filter registration manager.
- `Uppa_Core` — singleton orchestrator wiring all sub-modules.
- `Uppa_CPT_Manager` — stub for Custom Post Type registration.
- `Uppa_ACF_Bridge` — ACF integration with graceful fallback when ACF is absent.
- `Uppa_Image_Utils` — image helper stubs (srcset, background-removal prep).
- `Uppa_SEO_Utils` — SEO helper stubs (breadcrumb data, meta tags).
- `Uppa_Asset_Utils` — shared versioned enqueue helpers.
- `Uppa_Paystack` — Paystack transaction initialisation and verification stubs.
- `Uppa_Flutterwave` — Flutterwave payment initialisation and verification stubs.
- Admin settings page with top-level menu item.
- GitHub Actions workflows for PHP linting and release automation.

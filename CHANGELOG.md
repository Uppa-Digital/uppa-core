# Changelog

All notable changes to UPPA Core will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

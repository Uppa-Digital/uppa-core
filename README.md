# UPPA Core

Companion functionality plugin for the [UPPA Base](https://uppadigital.com) parent theme — CPT management, ACF bridge, Paystack & Flutterwave payments, and shared utilities.

Developed and maintained by **Upper Echelon Digital Services**.

---

## Features

### Dynamic CPT Registration

Register Custom Post Types and Taxonomies with a single array config call. Labels are generated automatically, REST API support is on by default, and every type is registered at `init` priority 0.

```php
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
```

### ACF Bridge

Wraps Advanced Custom Fields (Free or Pro) with a graceful fallback layer. Templates that call `UPPA_ACF_Bridge::get_instance()->get_field()` continue to work via `get_post_meta()` when ACF is not installed — no conditional checks needed in theme files.

### Payment Gateways

Native Paystack and Flutterwave integrations for custom checkout flows, donation forms, and event registration pages — without WooCommerce as a dependency. API keys are managed from the plugin settings screen and can be overridden via `wp-config.php` constants.

| Gateway      | Amount unit         | Supported currencies                          |
|-------------|---------------------|-----------------------------------------------|
| Paystack     | Smallest unit (kobo) | NGN, GHS, ZAR, USD                           |
| Flutterwave  | Major unit (naira)   | NGN, GHS, KES, UGX, TZS, ZAR, USD, EUR, GBP + 20 more |

### Image Utilities

- Lazy-loading `<img>` tags with `loading="lazy"` and `decoding="async"` by default
- CSS `mix-blend-mode: multiply` inline background removal for product images
- `srcset` generation from registered image sizes
- WebP MIME type support for uploads
- Base64 SVG placeholder images for CLS-free lazy loading

### SEO Utilities

- Structured breadcrumb data array (no HTML output — render it your way)
- Page meta title with Rank Math → Yoast → core fallback chain
- JSON-LD `Organization` and `BreadcrumbList` schema blocks

### Asset Utilities

- Google Fonts enqueue (CSS2 API, `display=swap`, automatic preconnect hint)
- Local `@font-face` enqueue via inline styles
- `<link rel="preload">` resource hints for LCP images and critical fonts
- Child-theme-first asset URL resolution mirroring `get_template_part()` logic

---

## Requirements

| Requirement       | Minimum |
|-------------------|---------|
| WordPress         | 6.4     |
| PHP               | 8.1     |
| ACF (Free or Pro) | Optional |

---

## Installation

**From the WordPress admin**

1. Go to **Plugins → Add New Plugin**.
2. Search for "UPPA Core".
3. Click **Install Now**, then **Activate**.

**Manual**

1. Download the plugin zip from [wordpress.org/plugins/uppa-core](https://wordpress.org/plugins/uppa-core/).
2. Upload the `uppa-core` folder to `/wp-content/plugins/`.
3. Activate the plugin through the **Plugins** screen.

**After activation**

Navigate to **UPPA Core → Settings** in the admin sidebar and enter your Paystack and Flutterwave API keys.

---

## Configuration

### Payment API Keys

All four keys are stored in a single WordPress option (`uppa_core_settings`). You can also define them as PHP constants in `wp-config.php` — constants take precedence over database values:

```php
define( 'PAYSTACK_PUBLIC_KEY',  'pk_live_…' );
define( 'PAYSTACK_SECRET_KEY',  'sk_live_…' );
define( 'FLW_PUBLIC_KEY',       'FLWPUBK_…' );
define( 'FLW_SECRET_KEY',       'FLWSECK_…' );
```

### Google Fonts

Hook into `wp_enqueue_scripts` and call:

```php
UPPA_Asset_Utils::enqueue_google_font( 'Inter', ['400', '500', '700'] );
```

A `<link rel="preconnect">` to `fonts.gstatic.com` is added automatically.

---

## Theme Integration

The UPPA Base theme detects this plugin via:

```php
if ( function_exists( 'uppa_core_active' ) ) {
    // Plugin is active — use bridge methods, CPT APIs, etc.
}
```

UPPA Core works with any theme, but some hook integration points are only meaningful alongside UPPA Base.

---

## Development

### Prerequisites

- PHP 8.1+
- Node.js 20+
- Composer (for PHPCS)

### Install JS dependencies

```bash
npm install
```

### Lint

```bash
npm run lint        # PHPCS + ESLint
npm run lint:php    # PHPCS only
npm run lint:js     # ESLint only
```

### Generate translation template

```bash
npm run pot
```

---

## CI / CD

| Workflow  | Trigger           | Steps                                                    |
|-----------|-------------------|----------------------------------------------------------|
| `lint`    | Pull request      | PHPCS (WordPress-Core + WordPress-Docs), ESLint (SARIF) |
| `release` | Push `v*.*.*` tag | GitHub Release + WordPress.org SVN deploy                |

The release workflow uses [`10up/action-wordpress-plugin-deploy`](https://github.com/10up/action-wordpress-plugin-deploy). Set `SVN_USERNAME` and `SVN_PASSWORD` in repository secrets before tagging a release.

---

## Changelog

### 1.0.0 — 2024-01-01

- Dynamic CPT and Taxonomy registration via `UPPA_CPT_Manager`
- ACF Bridge with `get_field()` and `get_field_object()` fallbacks
- Paystack integration: `initialize_transaction()` and `verify_transaction()`
- Flutterwave integration: `initialize_payment()` and `verify_transaction()`
- Image utilities: responsive images, CSS blend-mode BG removal, srcset, WebP, SVG placeholder
- SEO utilities: breadcrumb data, meta title (Rank Math / Yoast / core), JSON-LD Organization and BreadcrumbList schema
- Asset utilities: Google Fonts (CSS2), local `@font-face`, `<link rel="preload">`, child-theme-first asset URLs
- Admin settings page with WordPress Settings API (nonce-verified, capability-gated)
- Front-end AJAX handlers for payment initialisation and verification
- GitHub Actions: PHPCS + ESLint on pull requests; GitHub Release + WP.org SVN deploy on version tags

---

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

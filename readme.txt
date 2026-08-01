=== Checkout Blocker by Talistech ===
Contributors: talistech
Requires at least: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Tested up to: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block WooCommerce checkout by email address, full name or billing address.

== Description ==

Checkout Blocker by Talistech validates both the classic WooCommerce checkout and the block-based Checkout using server-side validation.

Supported rules:

* Exact email addresses, @domain rules and * wildcards.
* Full billing names with normalized accents and whitespace.
* Billing addresses in the format address line 1|postcode|country.

An optional admin notification can include the complete checkout context. Force logout and exact-email session destruction are also available from the settings screen.

== Installation ==

1. Upload the plugin to `wp-content/plugins/wp-checkout-blocker-by-talistech`.
2. Activate WooCommerce 10.9 or newer.
3. Activate Checkout Blocker by Talistech.
4. Configure the rules under Settings > Checkout Blocker.

== WP-CLI ==

Use `wp talistech-cb list --type=email`, `add`, `remove` and `destroy_sessions` to manage rules and exact-email sessions.

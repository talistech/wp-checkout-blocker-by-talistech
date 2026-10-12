=== Talistech Checkout Blocker for WooCommerce ===
Contributors: talistech
Tags: woocommerce, checkout, blocklist, email, security
Requires at least: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Tested up to: 7.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block WooCommerce checkout by email address, full billing name, or billing address using server-side validation.

== Description ==

Talistech Checkout Blocker for WooCommerce prevents selected customers from placing WooCommerce orders. It validates checkout data on the server and supports both the classic checkout and the block-based Checkout, including HPOS order storage. For signed-in customers, email rules check both the account email and the billing email; name and address rules use the submitted billing details.

Rules can match:

* Exact email addresses, complete domains, and wildcard email patterns.
* Exact full billing names and wildcard name patterns.
* Billing addresses using street, postcode, and country components.

The plugin can show a custom customer notice, notify administrators, force matching customers to log out, and destroy active sessions for exact blocked email addresses. Administrators and shop managers are protected from forced logout and session destruction.

This is an independent extension by Talistech. It is not affiliated with or endorsed by WooCommerce or Automattic. WooCommerce and its associated designs are trademarks of Automattic Inc.

The plugin has no external API, account, tracking or telemetry dependency. Optional administrator notifications use your site's WordPress mail configuration, which may use a mail provider.

== Screenshots ==

1. The actual settings page with email, full-name and billing-address rules, a custom checkout notice and optional account controls. All entered rules are synthetic demo data.
2. A real classic WooCommerce checkout refused by an email rule, showing the custom customer notice. Customer details and the product are fictitious.

== Installation ==

1. Upload and activate the plugin through the WordPress Plugins screen.
2. Make sure WooCommerce 10.9 or newer is active.
3. Open Settings > Talistech Checkout Blocker.
4. Add one rule per line and configure the customer notice.
5. Save the settings and test checkout in a private browser window.

== Rule examples ==

= Email rules =

* `blocked@example.com` blocks one exact email address.
* `@example.com` blocks every address directly on that domain.
* `test-*@example.net` blocks matching email patterns.
* `*@*.example.com` can match addresses on subdomains.

= Full-name rules =

* `Jan Jansen` blocks one complete billing name.
* `* De Smet` blocks any full billing name ending in De Smet.

Names are matched without casing, accent, or repeated-space differences, including Unicode letter casing.

= Billing-address rules =

Use `street and number|postcode|country code`.

* `Keringstraat 7|9506|BE` blocks one exact billing address.
* `*laan 10|2000|BE` uses a wildcard in the street component.
* `Keringstraat 7|*|BE` accepts any postcode for that street address.

Use the two-letter country code stored by WooCommerce, such as BE, NL, or FR.

== Frequently Asked Questions ==

= Does this work with the WooCommerce Checkout Block? =

Yes. Classic and block-based checkout are validated on the server before payment. Existing orders paid through the classic order-payment page or Store API are also checked.

= Does an @example.com rule include subdomains? =

No. It matches addresses directly on example.com. Use a wildcard rule when subdomains should match as well.

= Does the plugin send data to Talistech? =

No. The plugin has no external service or tracking connection. If administrator notifications are enabled, checkout details are sent through your own WordPress mail configuration.

= Which data can an administrator notification contain? =

The notification can include the customer's name, email address, billing address, IP address, user agent, date, and checkout URL. Enable it only when this fits your privacy policy.

== Privacy ==

Rules containing email addresses, names and addresses are stored in this site's WordPress options until removed by an administrator or the plugin is uninstalled. Administrator notifications are disabled by default. If enabled, checkout details are sent through the site's mail configuration; retention of these emails depends on your mail provider and shop policies. No data is sent to Talistech.

Notifications may contain billing and account email addresses, billing name and address, IP address, user agent and date. Checkout URLs exclude query parameters. Notifications are limited to one per IP address per ten minutes and sixty per site per hour. Only keyed IP hashes and counters are stored for throttling; expired hashes are pruned on the next notification attempt. Mail failures do not unblock checkout.

The WordPress privacy policy guide includes suggested text. Adapt it to your shop. Uninstalling removes plugin settings, notification limits and legacy notification transients from all sites in a multisite installation. Deactivation retains settings.

== Support ==

Visit https://www.talistech.com or report issues at https://github.com/talistech/wp-checkout-blocker-by-talistech. Do not include customer personal data in public reports.

== WP-CLI ==

Use `wp talistech-cb list --type=email`, `add`, `remove`, and `destroy_sessions` to manage rules and exact-email sessions.

== Changelog ==

= 1.2.1 =

* Rename the plugin to Talistech Checkout Blocker for WooCommerce with directory slug talistech-checkout-blocker-for-woocommerce.
* Scope operational administrator notices to relevant plugin-management screens.
* Escape invalid-rule warnings and harden Unicode and complex wildcard matching.
* Validate classic payments for existing orders, including their account email.
* Preserve existing settings, hooks and customer-session protections.

= 1.2.0 =

* Updated the plugin identity and author and website links.
* Check account and billing email addresses to prevent signed-in checkout bypasses.
* Protect administrators and shop managers from forced logout and session destruction.
* Harden notification limits, local redirects and settings validation.
* Add HPOS compatibility, privacy guidance and multisite data cleanup.
* Add reproducible release builds and checkout integration verification.

= 1.1.1 =

* Added the complete GPLv2 license text and explicit license metadata to the main plugin file.

= 1.1.0 =

* Added a redesigned, responsive settings screen with clear rule examples.
* Improved public documentation, metadata, accessibility, and branding.
* Refactored administration code while preserving existing settings and integrations.

= 1.0.0 =

* Initial release with classic and block-based checkout validation.

== Upgrade Notice ==

= 1.2.1 =

The directory and main filename change. Deactivate the old copy, activate this ZIP and verify your retained settings. Remove the old folder via SFTP or your file manager. Do not use WordPress Delete on the old copy: it removes the shared settings.

# Talistech Checkout Blocker for WooCommerce

Talistech Checkout Blocker for WooCommerce prevents selected customers from placing WooCommerce orders. It validates checkout data on the server against rules for email addresses, full billing names, and billing addresses.

It supports classic WooCommerce checkout, Checkout Blocks and payment of existing orders.

This independent Talistech extension is not affiliated with or endorsed by WooCommerce or Automattic. WooCommerce and its associated designs are trademarks of Automattic Inc.

## Features

- Block exact email addresses, complete domains, or wildcard email patterns.
- Block exact billing names or wildcard name patterns.
- Block billing addresses using street, postcode, and country rules.
- Show a configurable message when checkout is blocked.
- Optionally notify site administrators with checkout context.
- Optionally log out signed-in users whose email matches a rule.
- Invalidate active WordPress sessions for exact blocked email addresses.
- Manage rules through WP-CLI.
- No external service, account, or tracking dependency.

For signed-in customers, email rules check both the account email and the submitted billing email. Name and address rules use the submitted billing details. Administrators and shop managers are protected from forced logout and session destruction.

All checkout decisions are made server-side. Disabling JavaScript or using the Checkout Block does not bypass the configured rules.

## Requirements

- WordPress 7.0 or newer
- WooCommerce 10.9 or newer
- PHP 8.1 or newer

## Installation

1. Download the latest release ZIP from the GitHub Releases page.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP, install it, and activate the plugin.
4. Open **Settings → Talistech Checkout Blocker**.
5. Add your rules, configure the customer message, and save.
6. Test the result in a private browser window before using the rules in production.

## Rule syntax

Add one rule per line. A match in any category is enough to block checkout.

### Email rules

| Goal | Rule | Matches |
| --- | --- | --- |
| One exact address | `blocked@example.com` | Only that email address |
| One complete domain | `@example.com` | Every address directly on `example.com` |
| A pattern | `test-*@example.net` | Addresses such as `test-123@example.net` |
| Subdomains | `*@*.example.com` | Addresses such as `user@shop.example.com` |

Email matching is case-insensitive. A rule such as `@example.com` does not include subdomains; use a wildcard when subdomains should also match.

### Full-name rules

WooCommerce billing first name and last name are combined before matching.

| Goal | Rule | Matches |
| --- | --- | --- |
| One exact name | `Jan Jansen` | The complete billing name Jan Jansen |
| Any first name | `* De Smet` | Every full billing name ending in De Smet |

Name matching ignores casing, accents, and repeated spaces. Wildcards use `*` and may appear anywhere in the rule.

### Billing-address rules

Address rules contain exactly three parts separated by a vertical bar:

```text
street and number|postcode|country code
```

| Goal | Rule |
| --- | --- |
| One exact address | `Keringstraat 7|9506|BE` |
| Any matching street name | `*laan 10|2000|BE` |
| Any postcode for one address | `Keringstraat 7|*|BE` |

Use the two-letter country code stored by WooCommerce, such as `BE`, `NL`, or `FR`. Every address component must be present during checkout before an address rule can match.

## Behaviour options

**Customer notice** controls the error shown when checkout is blocked.

**Admin notifications** sends an email to site administrators when a rule matches. The message can contain the customer's name, email address, billing address, IP address, user agent, date, and checkout URL. Enable this only when it fits your privacy policy and data-retention practices. Notifications are limited to one per IP address per ten minutes and sixty per site per hour, including concurrent requests using a connection-owned MySQL/MariaDB lock. Limits are reserved before sending; failed mail attempts also consume the budget. Only keyed IP hashes and counters are retained. Changing the billing email cannot reset the limit. Checkout URLs exclude query parameters.

**Force logout** signs out a logged-in WordPress user when their account email matches an email rule. The configured redirect must be a local site URL.

**Destroy sessions** immediately invalidates active WordPress sessions for users listed with exact email rules. Domain and wildcard rules are deliberately ignored for this operation.

## WP-CLI

List rules:

```bash
wp talistech-cb list --type=email
wp talistech-cb list --type=name
wp talistech-cb list --type=address
```

Add or remove a rule:

```bash
wp talistech-cb add blocked@example.com --type=email
wp talistech-cb remove blocked@example.com --type=email
```

Destroy sessions for users with exact blocked email addresses:

```bash
wp talistech-cb destroy_sessions
```

## Privacy and security

The plugin has no telemetry or external API connection and sends no data to Talistech. Optional mail may pass through the site's configured mail provider. Rules and settings are stored in the WordPress options table. If admin notifications are enabled, checkout details are sent through the site's configured WordPress mail system.

Only administrators with the `manage_options` capability can change settings or destroy sessions. Settings are sanitized when saved, and session destruction is protected by a WordPress nonce.

## Development

Install development dependencies and run the test suite:

```bash
composer install
composer test
```

The production ZIP does not require Composer dependencies.

```bash
composer lint
nvm use # Node 24 is required for development tools
npm ci
npm run env:start
npm run test:integration
npm run test:matrix
npm run i18n
npm run build
npm run check:plugin
npm run env:stop
```

The isolated environment uses port 8894; the version matrix uses 8896–8898 and package verification uses 8895. Development dependencies are pinned in lockfiles, with overrides for security fixes. A documented postinstall patch switches wp-env 11.16 to the supported simple-git 4 named factory; it affects development tooling only. See [release verification](docs/VERIFICATION.md) for the compatibility matrix and [WordPress.org publication](docs/PUBLISHING.md) for submission instructions. Build output is `dist/talistech-checkout-blocker-for-woocommerce.zip`. Builds use an explicit runtime-file allowlist and fixed ZIP metadata for reproducible results.

The coding rules retain existing callback names, PSR-4 file paths and comparison style and do not require redundant function comments on typed methods. WordPress escaping, sanitization, nonce, database and internationalization checks remain enabled.

## Lifecycle

Version 1.2.1 uses the folder and main filename `talistech-checkout-blocker-for-woocommerce`. Existing installations under either historical directory need the manual migration described in [the publication guide](docs/PUBLISHING.md#existing-installations). Option keys, WP-CLI commands, callback names and public hooks remain unchanged. Email matching also accepts the trusted order account email for existing order-payment validation. The translation domain is `talistech-checkout-blocker-for-woocommerce`.

Network activation initializes every site, and newly created sites are initialized when the plugin is network active. Deactivation retains settings. Uninstall removes this plugin's settings and notification limiter data on all multisite sites, including legacy notification transients. Customer accounts, sessions unrelated to the explicit session command and orders are not deleted.

Rules are retained until manually removed or uninstalled. Expired IP hashes are pruned on the next notification attempt; dormant limiter data is also removed on uninstall. Mailbox retention is controlled by the shop and mail provider. Review the suggested privacy text under WordPress's privacy policy guide.

## Contributing and support

Bug reports and focused pull requests are welcome through the [GitHub repository](https://github.com/talistech/wp-checkout-blocker-by-talistech). Please do not include customer names, addresses, email addresses, or other personal data in public issues.

## License

Copyright © 2026 Talistech BV.

Licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html). The complete license text is included with every source and plugin release.

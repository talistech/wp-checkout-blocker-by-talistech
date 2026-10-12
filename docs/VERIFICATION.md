# Release 1.2.0 verification

The release targets WordPress 7.0+, WooCommerce 10.9+ and PHP 8.1+. Verification uses disposable local Docker environments and synthetic customer data.

## Compatibility matrix

| WordPress | WooCommerce | PHP | Order storage | Checkout |
| --- | --- | --- | --- | --- |
| 7.0 | 10.9.0 | 8.1 | HPOS on and off | Classic and Store API |
| 7.1.2 | 11.1.2 | 8.3 | HPOS on and off | Classic and Store API |
| 7.1.2 | 11.1.2 | 8.5 | HPOS on and off | Classic and Store API |

Each matrix profile passed 62 HTTP checks and 21 safety checks. The unit suite has 32 tests, including account-email matching, malformed settings, duplicate legacy rules, protected sessions, notification limits, mail exceptions and URLs stripped of query tokens. Real WordPress accent normalization is exercised by the HTTP suite; isolated unit tests use WordPress test doubles.

HTTP checks submit real classic AJAX and Store API checkouts using cash on delivery. They exercise permitted and blocked guests and accounts, exact addresses, domains and subdomains, wildcards, accents, billing addresses and account-email bypass attempts. They also check administrator pages, privacy-policy content, forced logout and concurrent notification reservations. HPOS data is synchronized before switching storage; checkout is then tested with synchronization disabled.

Blocked classic checkout returns failure. Blocked Store API checkout returns HTTP 400 before payment. WooCommerce may retain a checkout draft for rejected Store API submissions; it must not report successful payment.

## Security and lifecycle checks

- Both account and billing email are checked for signed-in customers. Names and addresses come from submitted billing data.
- Management accounts are protected from forced logout and session destruction, including management rights on another site in a multisite network. Duplicate exact-email rules count a customer once.
- Settings access requires `manage_options`; the destructive session handler rejects unauthorized users and invalid nonces.
- Local redirect validation rejects external hosts, credentials and unsupported URL schemes.
- The mail budget permits one notification attempt per IP per ten minutes and sixty per site per hourly window. MySQL/MariaDB connection locks serialize reservations and are released automatically if a process disconnects. Mail exceptions do not remove checkout errors.
- Network activation retains existing rules and initializes existing empty sites and new sites. Deactivation retains settings. Actual uninstall removes plugin options and legacy database transients from all four test sites while retaining unrelated options, customer accounts, sessions and orders.
- The historical 1.1.1 release and the new ZIP are loaded in the same private installation to verify preservation of all settings after a manual slug migration.

Legacy transients in an external persistent object cache expire according to their original ten-minute TTL; WordPress has no portable API to enumerate another cache backend's keys. New limiter state uses plugin-owned options and is explicitly deleted on uninstall. No broad cache flush is performed.

## Release checks

The exact installation ZIP is extracted into a separate test shop with no development dependencies. It passes the safety and HTTP suites and Plugin Check with zero errors. The release slug is now `checkout-blocker-by-talistech`; package verification requires zero warnings as well.

PHP syntax and the configured WordPress Coding Standards/PHPCompatibility rules are checked. The rules retain existing callback names, PSR-4 filenames and comparison style; escaping, nonce, sanitization, database and translation checks remain enabled. Composer and npm dependency audits are run separately from runtime checks.

The build uses fixed ZIP metadata and an explicit runtime file list, and prints its SHA-256. The regenerated translation template matches the directory slug. Version values in the header, runtime constant and readme must agree before a ZIP can be built.

## Reproduce

Follow [the build commands](PUBLISHING.md#build-and-verify). The integration scripts refuse non-local environments. Matrix logs and JSON results are written to the ignored `verification/` directory. Package verification uses port 8895 and the matrix uses ports 8896–8898. These commands create test accounts and orders and modify test-store settings.

After package verification, `python3 bin/verify-upgrade.py` verifies historical settings retention after renaming the main file to model a manual slug migration. It uses release commit `6026cd1` as the 1.1.1 source and modifies only the extracted package test copy, preserving the original source checkout.

Multisite lifecycle verification converts the disposable stable test database into a network, runs the preparation and activation phases, checks cross-site management protection, deactivates the plugin, then executes `wp plugin uninstall --deactivate --skip-delete` and the final lifecycle assertions. The integration fixture contains the phase assertions; source plugin files are retained during this check.

These results cover the configurations above and the built artifact. WordPress.org approval and validation in the intended shop remain separate steps.

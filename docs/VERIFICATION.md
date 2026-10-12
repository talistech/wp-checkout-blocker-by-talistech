# Release 1.2.1 verification

The release targets WordPress 7.0+, WooCommerce 10.9+ and PHP 8.1+. Verification uses disposable local Docker shops and synthetic customer data. The public identity is **Talistech Checkout Blocker for WooCommerce**, with slug/text domain `talistech-checkout-blocker-for-woocommerce`.

## Compatibility matrix

| Profile | WordPress | WooCommerce | PHP | Order storage | Checkout |
| --- | --- | --- | --- | --- | --- |
| Minimum | 7.0 | 10.9.0 | 8.1 | HPOS on and off | Classic and Store API |
| Stable | 7.1.3 | 11.2.1 | 8.3 | HPOS on and off | Classic and Store API |
| PHP 8.5 | 7.1.3 | 11.2.1 | 8.5 | HPOS on and off | Classic and Store API |

Each profile runs 49 unit tests / 138 assertions, 21 real-WordPress safety checks and 134 HTTP checks. HTTP cases use actual classic AJAX/Store API checkout and both existing-order payment paths, including pending and failed orders. They cover guests/accounts, exact and domain rules, wildcards, accents, Greek/Cyrillic casing, billing names/addresses, alternate billing email and trusted account email. HPOS storage is synchronized before switching and checkout runs with synchronization disabled.

The existing-order tests count real cash-on-delivery gateway calls through a local-only fixture. Blocked payments must retain the original pending/failed status and reach the gateway zero times; allowed payments must reach it exactly once and progress. Store API authorization still belongs to WooCommerce. Registered classic-order tests also attempt logged-out and different-account payment POSTs with a valid action nonce/order key.

Additional HTTP assertions cover actual settings/privacy-guide pages, manager protection, customer logout cookies and concurrent notification reservations. Blocked Store API checkout returns HTTP 400 before payment; WooCommerce may retain a checkout draft. These tests do not simulate every payment provider or third-party checkout replacement.

## Exact installation ZIP

`dist/talistech-checkout-blocker-for-woocommerce.zip` and the versioned alias contain identical bytes. The build uses fixed ZIP metadata and an explicit allowlist of 15 runtime/readme/license/translation files, with no dependencies, tests or marketing media. Header version, runtime constant and Stable tag must agree at 1.2.1. The exact current artifact has SHA-256:

```text
139282bd4cf41596f365604a8f4d63012a1fcc45ad2e7dd3bac91b49e4959322
```

Package verification extracts that ZIP into a separate shop with no plugin development dependencies and runs 21 safety checks, 19 admin-compliance checks with WooCommerce, 19 without it, the full 134 HTTP checks and complete Plugin Check. Plugin Check has **no check/file/directory exclusions** on the release. The result JSON records the tested SHA-256 and whether the full suite ran; check-only mode cannot establish an integration pass.

## Security, administration and lifecycle

- The unit suite includes malicious invalid-rule warnings, Unicode and complex wildcard regressions, protected sessions, malformed settings, mail failures, limits and sanitized URLs.
- The real WordPress admin fixture exercises core `settings_errors()`, relevant capabilities, site/network plugin screens, dismissible dependency/conflict notices and empty dashboard output. Operational notices disappear when their underlying condition resolves.
- Chromium browser checks submit actual `options.php` forms. Valid saves retain apostrophes/backslashes; invalid nonces receive HTTP 403 and cannot change settings. Customer settings access is denied; WooCommerce redirects customer update attempts away from administration without changing options. HTML in rejected rules remains plain text. The checkout screenshot follows an actual blocked Place order request.
- The safety suite checks exact-session invalidation, management protection, nonce/capability denial, local redirects, notification locks across database connections, sixty-per-hour limits, mail exceptions and privacy guidance.
- The upgrade suite archives actual historical releases 1.1.1 (`6026cd1`) and recovered 1.2.0 (`a73f783`). It checks unchanged settings, duplicate activation rejection, safe duplicate load order, activation of 1.2.1 after deactivation, and retention after removing the old folder without uninstall.
- The multisite suite converts the disposable stable database to a network. It checks existing/new sites, cross-site manager/session protection, retention on deactivation and actual uninstall cleanup across four sites. Unrelated options, customer accounts/sessions and WooCommerce orders remain.

Legacy transients in an external persistent object cache expire according to their original ten-minute TTL; WordPress has no portable API to enumerate another cache backend's keys. No broad cache flush is performed. New limiter options are explicitly removed.

## Static and dependency checks

Shipped PHP syntax and the configured WordPress Coding Standards/PHPCompatibility rules are checked. Existing callback names, PSR-4 filenames and comparison style are retained; escaping, nonces, input sanitation, database and translation checks remain enabled. Duplicate rule definitions were removed. The version-locked development patch for `wp-env` also replaces cache JSON atomically; a 1,200-write stress check confirms that parallel CLI execution preserves initialization. This changes no shipped code or notification concurrency. Python scripts are parsed, the Git diff is checked for whitespace errors and all required audit keywords are searched again.

Composer and npm audits cover development dependency lockfiles separately from runtime. The local older Composer executable emits PHP 8.5 deprecation messages from its own bundled tooling; these do not originate in shipped plugin code. PHP warning/error logs under `WP_DEBUG` are reviewed for shipped plugin failures.

Media has nine validated PNG deliverables, current generated naming edits, real unretouched Chromium screenshots and matching SHA-256/dimension metadata. SVN staging contains identical runtime content under trunk and tags/1.2.1, plus top-level directory assets. No publication is performed.

## Reproduce and evidence

See [PUBLISHING.md](PUBLISHING.md#build-and-verify). The integration scripts refuse non-local destinations and intentionally modify fixtures/test settings. Use only the disposable test stores. Package verification uses port 8895; matrix profiles use 8896–8898. The demonstration screenshot shop uses 8894.

Ignored local evidence:

- `verification/static-checks.log`, `composer-audit.log`, `browser-admin.json`, `final-search.txt`.
- `verification/matrix.json` and each `verification/environments/resubmission-*/verification.log`.
- `verification/resubmission-package/result.json`, `plugin-check.log`, `step-*.log`, `upgrade.json`.
- `verification/environments/resubmission-stable/multisite.json` and `multisite.log`.

The minimum profile completed all 128 checkout/payment cases before `wp-env` lost cached initialization during the parallel mail-reservation check. After repairing its cache writes, the six admin/logout/notification checks were rerun successfully with `--account-checks-only`; `account-checks.log` records that resumed phase. No checkout, payment or notification assertion was removed. The stable and PHP 8.5 profiles and exact ZIP completed all 134 HTTP checks in their full runs.

All required checks completed successfully on 2026-10-12. The durable summary is [verification-results.json](verification-results.json): 49 unit tests / 138 assertions, three compatibility profiles with 134 HTTP checks each, exact-ZIP safety/admin/134-HTTP checks with zero Plugin Check errors or warnings, both historical migrations, 26 multisite lifecycle assertions, browser checks, PHP/Python syntax, coding standards, dependency audits and media validation. No shipped-plugin-origin PHP warning/error was found in the reviewed `WP_DEBUG` logs. WordPress.org slug allocation and approval remain the review team's decision.

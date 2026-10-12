# WordPress.org resubmission review — 1.2.1

Review date: 2026-10-12. Identity: **Talistech Checkout Blocker for WooCommerce**. Directory slug and text domain: `talistech-checkout-blocker-for-woocommerce`.

## Findings before fixes

### Blocking for resubmission

- The submitted display name began with a generic description and the distinctive Talistech identifier came last. The header, readme, translation domain, package tooling and visual branding used the previous identity.
- Dependency/conflict notices could appear throughout administration. Their screen scope, capability checks and dismissal needed to follow Guideline 11.

### Important

- Invalid-rule messages passed administrator-entered HTML to the core settings-error renderer. Full messages now receive late HTML escaping. This matters on multisite installations where an administrator may lack `unfiltered_html`.
- The classic existing-order payment path was not covered by the checkout blocking hook. It now validates the stored billing details and the order owner's account email before the payment gateway runs. Current-account email checks remain intact.
- Complex wildcard patterns could exhaust PCRE backtracking and silently miss a blocked customer. Matching now uses atomic wildcard segments and a deterministic fallback for oversized patterns or PCRE failures.
- ASCII case comparison and byte-oriented line splitting could miss Unicode rules or split a Greek letter. UTF-8 case matching and Unicode line/whitespace handling now cover exact and wildcard rules.
- A changed directory/main filename can load two historical copies with identical PHP classes. Activation and load guards now prevent duplicate execution; upgrade tests cover the actual 1.1.1 and recovered 1.2.0 directories.
- The matrix source mapping needed repair to exercise the current checkout rather than an absent plugin directory.
- Repeated package extraction required stopping Docker to replace bind-mounted directories safely. Parallel `wp-env` cache writes could also lose initialization metadata; the existing version-locked development patch now replaces cache JSON atomically, validated with 1,200 concurrent writes. Runtime notification reservations remain concurrent.

### Minor

- The two submit buttons shared an HTML id. The session button now has its own id.
- Translation templates, release documentation, media captions and capture provenance needed regeneration.
- Full Plugin Check also found an overlong upgrade notice and two unprefixed temporary bootstrap variables. The notice was shortened without losing migration instructions and the variables now use a Talistech prefix.
- Duplicate coding-standard rules were removed. Active security checks remain enabled; final installation-package Plugin Check uses no exclusions.

## Implemented identity and compatibility

The main file is `talistech-checkout-blocker-for-woocommerce.php`; the plugin header/readme title and all shipped gettext domains match the final values. `Domain Path` remains `/languages`, where the regenerated matching POT resides. WordPress handles plugin-directory translations; there is no obsolete `load_plugin_textdomain()` call.

Namespaces, class names, `TCB_*` constant names, option/meta keys, public hooks, session action/nonces, admin page slug and `wp talistech-cb` commands are preserved. The existing CSS handle remains valid and does not depend on the previous directory slug. Its URL derives from the current plugin constant. The WooCommerce logger source now uses the new slug.

Both old installation directories require a **manual migration**: deactivate the old copy, install/activate the new ZIP, verify the settings, and remove the old directory with a file manager or SFTP. Do not use WordPress Delete on the old copy; its historical uninstall removes the shared settings. The new slug does not provide automatic updates across directory names. Existing external translation catalogs must be rebuilt for the new text domain.

## Security and API review

All shipped PHP, CSS, readme/license and translations were inspected, together with build, test and publication tooling. No custom AJAX or REST endpoint exists. WooCommerce owns the checkout endpoints, authorization and nonces; this plugin adds server-side validation before payment. HTTP checks exercise classic and Store API checkout with HPOS enabled and disabled, including existing pending/failed orders and alternate account-email attempts.

The WordPress Settings API handles authorized saves and request unslashing. The registered sanitizer validates rule formats, booleans, scalar types and local redirect URLs; checkout data is already unslashed by WooCommerce and sanitized once. Form values and output use context-specific escaping. Result query parameters are unslashed, sanitized and nonce-checked.

Settings access and session destruction require `manage_options`; the destructive handler verifies its nonce before calling WordPress session APIs. Forced logout excludes management accounts, admin/AJAX/REST requests and validates its local redirect. Multisite managers on another member site are protected because WordPress sessions are network-wide.

SQL is limited to prepared connection-owned notification locks and prepared uninstall cleanup of exact plugin transient prefixes. Table identifiers come from WordPress. The established locks are deliberately not object-cached. Runtime code fetches or writes no external files, executable code or third-party assets. Main and uninstall entrypoints have direct-access guards; class-only source files have no standalone side effects.

Orders use WooCommerce getters and checkout hooks; there is no direct order-table access. HPOS and Checkout Blocks declarations use `FeaturesUtil`. No deprecated API was identified in shipped code. Test-only storage synchronization uses a WooCommerce internal data synchronizer to switch fixtures; that test helper is excluded from the installation ZIP.

## Threat model

| Threat | Surface and reviewed control |
| --- | --- |
| Spoofing | A blocked customer submits another billing email or pays an existing order while signed out. Trusted current/owner account email is matched alongside billing data; WooCommerce owns endpoint authentication. |
| Tampering | Malformed settings, alternate order-payment POSTs, wildcard exhaustion and crafted redirect URLs. Validated settings, pre-payment server enforcement, deterministic matching and local redirect validation address these paths. |
| Repudiation | Notifications are optional diagnostics, not a complete order audit. WooCommerce retains its normal order records; the plugin does not claim tamper-proof logging. |
| Information disclosure | Optional mail contains personal checkout details. It is disabled by default, uses validated administrator recipients and excludes query tokens from URLs; retention is disclosed. No customer data is sent to Talistech. |
| Denial of service | Complex patterns, repeated mails and shared network sessions. Atomic matching/fallback and connection-owned mail budgets limit these effects; management sessions are explicitly protected. |
| Elevation of privilege | Restricted administrator injects HTML into settings warnings or a customer calls session destruction. Late escaping, capabilities and nonces are checked with unit, real WordPress and browser fixtures. |

The trust boundaries are public checkout requests, authenticated administration, the site database/session store and the site's own optional mail configuration. There is no plugin-specific public endpoint or remote service boundary.

## Guideline 11

Operational notices are limited to the plugins screens (including network plugins) and, where available, the plugin settings screen. They require plugin-activation rights, explain how to fix the condition, are dismissible and disappear when resolved. The duplicate-installation notice is limited to plugin management and checks the appropriate network capability. There are no promotional, review, donation, advertising, upgrade or tracking prompts, admin dashboard widgets, or global persistent nags. The successful session-operation result appears only on the plugin settings screen after a verified result nonce. No `all_admin_notices` hook or WooCommerce notice store is used.

This satisfies the reviewed scope and moderation requirements of [Guideline 11](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#11-plugins-should-not-hijack-the-admin-dashboard).

## Privacy, licensing and directory rules

The plugin sends no data to Talistech and has no telemetry, external API, remote code, affiliate behavior, paywall, obfuscation or runtime third-party library. Optional administrator notifications use the site's `wp_mail()` configuration and can therefore pass data to the site's chosen mail provider. The readme and WordPress privacy-policy guide disclose their personal-data fields, rule storage, hashed-IP limiter retention and uninstall behavior. Notifications default to disabled. Expired IP hashes are pruned on the next notification attempt; inactivity does not immediately delete the option.

The complete GPLv2 license is included and metadata states GPLv2 or later. The release contains readable PHP/CSS and the translation template; development dependency trees and promotional media are excluded. Media is original generated artwork, licensed under the repository's GPL terms, with no official platform logos.

The distinctive Talistech identifier comes first, followed by the compatibility phrase “for WooCommerce”. Readme, settings UI and visual assets identify an independent extension without endorsement. This follows the compatibility structure in [Woo's trademark guidelines](https://woocommerce.com/trademark-guidelines/) and the [WordPress directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). Exact-name searches did not identify another matching plugin; search coverage cannot guarantee name clearance or slug availability. Talistech ownership is the author's responsibility, and the review team may request evidence.

Readme metadata, five tags, contributors, description, installation, FAQ, screenshot captions, changelog and upgrade notice were reviewed. Stable tag, header version and runtime constant agree at 1.2.1. Minimum versions remain WordPress 7.0, PHP 8.1 and WooCommerce 10.9. Tested configurations and exact artifact evidence are documented in [VERIFICATION.md](VERIFICATION.md).

## Deliberately retained old references

A final repository search covers both prior names/slugs, notices, request globals, AJAX/REST routes, redirects, mail, SQL, sessions and logout. Development dependency trees, generated verification logs and ZIP binaries are outside the authored-source scan; the installation ZIP is separately checked without Plugin Check exclusions.

- The main-file guard retains both old basenames solely to detect duplicate installations.
- Upgrade fixtures and migration/recovery documentation retain the historical names and commits.
- The existing GitHub URL is retained so support/source links work; no repository rename was requested.
- Earlier image-generation prompt files remain as clearly marked provenance, not current branding instructions.
- Existing database/API identifiers remain for compatibility. No current display-name or translation-domain reference uses the old identity.

## Remaining considerations

The review team must reserve the requested slug and approve the submission. This review is not a promise of approval or compatibility with every payment gateway/theme/add-on. Tests cover the supported local matrix and cash on delivery, actual checkout/payment requests, management sessions, multisite lifecycle and manual migration. Validate the intended shop before production deployment. Optional notification delivery depends on the site's mail configuration; checkout blocking continues if mail fails.

Legacy transients in an external persistent object cache expire according to their original ten-minute TTL. WordPress has no portable enumeration API for another cache backend; uninstall does not flush unrelated caches. New limiter options are explicitly removed.

No WordPress.org upload or review email has been sent. The proposed reply and submission instructions are in [PUBLISHING.md](PUBLISHING.md).

## Changed-file inventory

Changes relative to restored release 1.2.0 (`a73f783`), including renamed/deleted historical files:

- `.gitignore`
- `.wp-env.json`
- `README.md`
- `bin/build-release.py`
- `bin/build-wordpress-org-stage.py`
- `bin/patch-wp-env.cjs`
- `bin/verify-matrix.py`
- `bin/verify-multisite.py`
- `bin/verify-package.py`
- `bin/verify-upgrade.py`
- `checkout-blocker-by-talistech.php`
- `composer.json`
- `composer.lock`
- `docs/HERSTEL.md`
- `docs/PUBLISHING.md`
- `docs/REVIEW.md`
- `docs/VERIFICATION.md`
- `docs/verification-results.json`
- `languages/talistech-checkout-blocker-for-woocommerce.pot`
- `media/README.md`
- `media/VALIDATION.md`
- `media/checkout-blocker-by-talistech-media-1.2.0.zip`
- `media/checkout-blocker-by-talistech-media-1.2.0.zip.sha256`
- `media/manifest.json`
- `media/preview/index.html`
- `media/preview/media-overview.png`
- `media/social/social-1080x1080.png`
- `media/social/social-1200x630.png`
- `media/sources/banner-master.png`
- `media/sources/capture-notes.json`
- `media/sources/export-media.cjs`
- `media/sources/resubmission-prompts.json`
- `media/sources/social-square-master.png`
- `media/sources/social-wide-master.png`
- `media/talistech-checkout-blocker-for-woocommerce-media-1.2.1.zip`
- `media/talistech-checkout-blocker-for-woocommerce-media-1.2.1.zip.sha256`
- `media/wordpress-org/banner-1544x500.png`
- `media/wordpress-org/banner-772x250.png`
- `media/wordpress-org/screenshot-1.png`
- `media/wordpress-org/screenshot-2.png`
- `package-lock.json`
- `package.json`
- `phpcs.xml.dist`
- `readme.txt`
- `src/Admin.php`
- `src/CLI.php`
- `src/Notifications.php`
- `src/Plugin.php`
- `src/RuleMatcher.php`
- `src/Settings.php`
- `src/WooCommerce.php`
- `talistech-checkout-blocker-for-woocommerce.php`
- `tests/Unit/AdminComplianceTest.php`
- `tests/Unit/RuleMatcherTest.php`
- `tests/Unit/SafetyTest.php`
- `tests/bootstrap.php`
- `tests/integration/admin-compliance.php`
- `tests/integration/checkout.py`
- `tests/integration/mu-plugins/tcb-local-mail.php`
- `tests/integration/multisite.php`
- `tests/wordpress-stubs.php`

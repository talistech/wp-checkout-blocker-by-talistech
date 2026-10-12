# Resubmit version 1.2.1 to WordPress.org

## Release identity

| Field | Value |
| --- | --- |
| Plugin name | Talistech Checkout Blocker for WooCommerce |
| Requested slug and text domain | talistech-checkout-blocker-for-woocommerce |
| Version / Stable tag | 1.2.1 |
| Author / contributor | Talistech / talistech |
| Author URI | https://www.talistech.com |
| License | GPLv2 or later |

This is an independent Talistech extension, not affiliated with or endorsed by WooCommerce or Automattic. WooCommerce is mentioned to describe compatibility. No official platform logo is used. The review team must allocate the requested slug before approval; the display name alone does not change the reservation.

## Existing installations

Both historical directories, `wp-checkout-blocker-by-talistech` (1.1.1) and `checkout-blocker-by-talistech` (1.2.0), use the same settings as the new installation. This is a manual directory migration, not an automatic WordPress update across slugs.

1. Back up the shop and deactivate the old plugin.
2. Upload `talistech-checkout-blocker-for-woocommerce.zip` and activate it.
3. Confirm that rules, customer notice, notification preferences and redirect settings were retained; verify permitted and blocked checkout.
4. Remove the old directory through SFTP or the hosting file manager. **Do not use WordPress Delete/uninstall on the old copy:** uninstall removes the shared settings.

The new bootstrap refuses activation while a known old installation is active and avoids loading duplicate classes. Option keys, public hooks, command names, existing callbacks and the settings page URL remain stable. Existing third-party translation catalogs need the new text domain/filename to translate this release.

## Build and verify

Use PHP 8.1+, Composer, Python 3.12+, Docker and Node 24. No development dependency is included in the runtime ZIP.

```bash
nvm use
composer install
npm ci
composer test
composer lint
npm run env:start
npm run i18n
npm run build
npm run test:package
npm run test:matrix
npm run test:upgrade
npm run test:multisite
npm run build:svn
```

Upload `dist/talistech-checkout-blocker-for-woocommerce.zip`. The versioned archive is identical and exists for reproducibility. Keep its SHA-256 and test reports. Plugin Check must inspect the complete extracted release with no excluded runtime checks. Resolve root causes of every finding; do not suppress checks to get a clean report.

## Resubmission

1. Sign in to the WordPress.org account `talistech` and upload the corrected ZIP using the existing submission's Add your Plugin page.
2. Reply to the existing review thread, explicitly requesting `talistech-checkout-blocker-for-woocommerce` as the new slug.
3. Use the short draft below only after upload. Do not claim that the slug is reserved or the plugin approved until the team confirms it.

> Hello,
>
> I have uploaded the reviewed and tested update as “Talistech Checkout Blocker for WooCommerce”. Please change the requested slug to “talistech-checkout-blocker-for-woocommerce”.
>
> I have reviewed the complete plugin, including Guideline 11. This is an independent Talistech extension, not affiliated with or endorsed by WooCommerce or Automattic.
>
> Kind regards,
> Mustafa / Talistech

If the review team asks for proof of ownership of the Talistech brand/account, provide accurate company/account evidence. Code and name checks do not establish legal ownership or guarantee approval.

## After approval

The staging builder prepares `dist/wordpress-org/trunk/`, `tags/1.2.1/` and top-level directory assets from the verified release and media. SVN is a release repository: review the staged files before committing the complete approved release. Confirm the final assigned slug before checkout of the SVN repository.

Nothing in the build scripts publishes to WordPress.org or sends a review email. GitHub source hosting remains at https://github.com/talistech/wp-checkout-blocker-by-talistech.

Sources: [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [Woo trademark guidelines](https://woocommerce.com/trademark-guidelines/), [submission handbook](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/).

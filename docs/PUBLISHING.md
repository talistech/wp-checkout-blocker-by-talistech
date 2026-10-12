# Publish version 1.2.0 on WordPress.org

This guide is for the Talistech account owner submitting the plugin and publishing its approved release.

## Release identity

| Field | Value |
| --- | --- |
| Plugin name | Checkout Blocker by Talistech |
| Version | 1.2.0 |
| Requested directory slug | checkout-blocker-by-talistech |
| Author | Talistech |
| Author URI | https://www.talistech.com |
| Plugin URI | Omitted until a dedicated plugin page is available |
| Contributors | talistech — confirm this is your WordPress.org username |
| License | GPLv2 or later |

The release uses the new folder and main filename `checkout-blocker-by-talistech`. Existing installations under `wp-checkout-blocker-by-talistech` need a manual migration: deactivate the old plugin, upload and activate the new ZIP, and confirm the existing rules and settings. Do not activate both copies together. Do not uninstall the old copy: uninstall removes the shared settings. After confirming the migration, remove only the old plugin folder using SFTP or the hosting file manager. Option keys remain unchanged.

## Build and verify

Use PHP 8.1+, Composer, Python 3.12+, Docker and Node 24. PHP development dependencies and npm dependencies are excluded from the distributed plugin.

```bash
nvm use
composer install
npm ci
composer test
composer lint
npm run env:start
npm run test:integration
npm run test:matrix
npm run i18n
npm run build
npm run check:plugin
python3 bin/build-wordpress-org-stage.py
```

The installation ZIP is `dist/checkout-blocker-by-talistech-1.2.0.zip`. The SVN staging directory contains `trunk/` and `tags/1.2.0/`, generated from that ZIP. Do not upload the development repository as your plugin ZIP.

Plugin Check must report zero errors and zero warnings for the release ZIP. The package verification script rejects every finding.

## Submit the ZIP

1. Sign in to the company-controlled WordPress.org account. Verify that `Contributors` uses its actual username and that the profile has the desired Talistech display name and website. Use a monitored company email address.
2. Open [Add your Plugin](https://wordpress.org/plugins/developers/add/).
3. Upload the verified ZIP, request the slug above, and describe the plugin's server-side email, name and billing-address blocking for classic checkout and Checkout Blocks.
4. Monitor email from `plugins@wordpress.org` and respond to review feedback. Code checks do not replace WordPress.org's review.

Suggested submission description:

> Checkout Blocker by Talistech blocks checkout using administrator-managed email, full billing-name and billing-address rules. It supports classic checkout, Checkout Blocks and HPOS. Signed-in customers are checked against both account and billing email addresses. Notifications and forced customer logout are optional and disabled by default. The plugin uses no external service or tracking connection.

## Publish after approval

Approval supplies access to the plugin's SVN repository. Replace the slug in these commands if the review team assigns a different one. Install Subversion locally if `svn` is unavailable.

```bash
svn checkout https://plugins.svn.wordpress.org/checkout-blocker-by-talistech/ wordpress-org-checkout
cp -R dist/wordpress-org/trunk/. wordpress-org-checkout/trunk/
mkdir -p wordpress-org-checkout/assets
cp -R dist/wordpress-org/assets/. wordpress-org-checkout/assets/
mkdir -p wordpress-org-checkout/tags/1.2.0
cp -R dist/wordpress-org/tags/1.2.0/. wordpress-org-checkout/tags/1.2.0/
cd wordpress-org-checkout
svn add --force trunk tags/1.2.0 assets
svn propset svn:mime-type image/png assets/*.png
svn status
svn diff
svn commit -m "Release 1.2.0"
```

Review the changes before committing. Enter your WordPress.org SVN credentials when prompted rather than putting passwords in commands. For this initial submission the repository should be empty; later releases must also remove obsolete files from SVN explicitly.

After publication, install the WordPress.org version in a staging shop, check the displayed name, author and website links, and repeat a permitted and blocked checkout. WordPress.org may require release confirmation; complete any account prompt or confirmation email.

The media pack in `media/` includes icons, banners, social images, an avatar and two real screenshots. See [the media guide](../media/README.md) and [the preview](../media/preview/index.html). `media/wordpress-org/` is staged into SVN's top-level `assets/`, separate from `trunk/assets` and the installation ZIP. The readme screenshot captions match `screenshot-1.png` and `screenshot-2.png`. The local staging command prepares these files without publishing them.

See [release verification](VERIFICATION.md) and the official [submission handbook](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/).

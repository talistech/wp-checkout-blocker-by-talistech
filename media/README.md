# Media pack — Talistech Checkout Blocker for WooCommerce

Version 1.2.1 · Slug `talistech-checkout-blocker-for-woocommerce`

[Open the visual preview](preview/index.html) · [Machine-readable file manifest](manifest.json)

## Deliverables

| File | Dimensions (px) | Use |
| --- | --- | --- |
| `wordpress-org/icon-128x128.png` | 128 × 128 | WordPress.org search and plugin list icon |
| `wordpress-org/icon-256x256.png` | 256 × 256 | WordPress.org Retina plugin icon |
| `avatar/avatar-1024x1024.png` | 1024 × 1024 | Plugin avatar for profiles and directories |
| `wordpress-org/banner-772x250.png` | 772 × 250 | WordPress.org plugin page banner |
| `wordpress-org/banner-1544x500.png` | 1544 × 500 | WordPress.org Retina plugin page banner |
| `social/social-1200x630.png` | 1200 × 630 | Social link preview and landscape announcement |
| `social/social-1080x1080.png` | 1080 × 1080 | Square social announcement |
| `wordpress-org/screenshot-1.png` | 1238 × 1726 | Real plugin settings page with synthetic block rules |
| `wordpress-org/screenshot-2.png` | 1064 × 1269 | Real classic checkout refused for blocked@example.com |

## Visual direction

An original white shopping cart with an emerald minus badge identifies restricted checkout. Midnight teal `#142633`, emerald `#147363` and off-white `#f4f7f6` are taken from the plugin interface. The restrained treatment also fits Talistech's professional software identity. The Talistech website uses Manrope and blue/cyan accents; these assets prioritize the plugin's existing green interface. Generated artwork contains its own rendered lettering, with no external font dependency. No official WordPress or WooCommerce logo is included.

The icon has no lettering, so it remains readable at directory sizes. Keep the avatar square; platform circle crops may be applied by the platform. The symbol identifies the plugin rather than replacing Talistech's corporate logo. Use the supplied banner variants at their native size.

## Sources and provenance

Artwork was generated with the built-in imagegen tool. Current generated PNG masters are in `sources/`. `resubmission-prompts.json` records the new Talistech-first naming edits. Earlier prompt files are historical provenance, not current branding instructions. `prompts.json` records the initial generation; `name-correction-prompts.json` records the imagegen edits that removed the previous WooCommerce display-name wording after the submission form rejected it. Export resizing uses Sharp; it does not retouch the screenshots. These are raster masters, not editable vectors.

Screenshots are genuine Chromium element captures from a local WordPress 7.1.3 / WooCommerce 11.2.1 installation running plugin 1.2.1. The checkout uses Storefront 4.6.2's normal full-width template and the classic checkout shortcode. Its rejection notice appeared after clicking **Place order** with `blocked@example.com`; no notice or interface was fabricated. Only the plugin panel / checkout content was captured, excluding browser chrome, admin navigation, storefront footer and official platform logos. All entered customer details, rules and products are fictitious.

`setup-demo.php` documents the local-only demo configuration. It modifies local demo options, pages and a product, and must not be used in a live shop. Authentication cookies and passwords are not included. `capture-notes.json` records the capture conditions.

## WordPress.org upload

Copy only the contents of `wordpress-org/` to the approved plugin SVN repository's top-level `assets/` directory. This is a sibling of `trunk/` and `tags/`; it is not the plugin runtime `assets/` directory. Both banner resolutions and both PNG icon resolutions are included. `readme.txt` contains two matching screenshot captions. The staging builder copies these directory assets automatically.

The exact supported filenames, pixel sizes and limits follow the [WordPress.org asset handbook](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/). Exported icons are below 1 MB, banners below 4 MB and screenshots below 10 MB. Images use lowercase filenames. Publication has not been performed.

## Rebuild

From the project root, install the optional export tool in the ignored development area:

```bash
npm install --prefix verification/media-tools --no-audit --no-fund sharp@0.35.5
node media/sources/export-media.cjs
python3 bin/build-release.py
python3 bin/build-wordpress-org-stage.py
```

`export-media.cjs` checks dimensions and image size limits and writes SHA-256 hashes to the manifest. The media ZIP contains the complete `media/` folder and the matching plugin `readme.txt`. It is a handover archive, not an installable plugin ZIP.

# Release 1.2.1 media validation

Updated and checked on 2026-10-12.

The banner and both social masters were edited with the built-in imagegen tool to display **Talistech Checkout Blocker for WooCommerce**. Icons and avatar have no product-name lettering and retain their original artwork. No official WooCommerce, Automattic or WordPress logo is used.

Both screenshots were captured again from an actual local WordPress 7.1.3 / WooCommerce 11.2.1 / Storefront 4.6.2 installation running plugin 1.2.1. The checkout rejection followed a real Place order request. They use synthetic data and have not been retouched. Capture conditions are in `sources/capture-notes.json`.

The export script validates all nine PNG files, dimensions, integrity, file-size limits and SHA-256 hashes. `manifest.json` records the current results. The preview overview is captured from the current local preview page. Historical generation prompts remain solely for provenance.

The media archive is a handover pack; submit the separate installation ZIP. Directory assets are staged separately under `dist/wordpress-org/assets/`.

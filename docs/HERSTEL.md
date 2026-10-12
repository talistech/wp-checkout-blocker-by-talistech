# Herstel na verwijdering — 12 oktober 2026

De projectmap is hersteld op de oorspronkelijke locatie. De Git-geschiedenis en tags v1.0.0, v1.1.0 en v1.1.1 komen uit de oorspronkelijke GitHub-repository. De lokale versie 1.2.0 is gereconstrueerd uit de Downloads-ZIP, de bewaarde Codex-sessie en de originele gegenereerde afbeeldingen.

De installatie-ZIP is **byte voor byte gelijk aan de laatste ZIP vóór de verwijdering**. SHA-256 uit zowel de oorspronkelijke sessie als de herbouwde ZIP:

`5ada134cb526260a51d1d87c38d3d73228b01842d5f263dfe057c92acec0662a`

## Hersteld

- Plugincode, recente naam en slug, vertalingssjabloon en readme.
- Configuratie, unittests, integratietests, buildscripts en publicatiehandleidingen.
- Afbeeldingsmasters, prompts, exports, screenshots, mediapreview en media-archief.
- Ontwikkelafhankelijkheden via Composer en npm. Lockfiles zijn opnieuw gegenereerd; deze hoeven niet identiek te zijn aan de verwijderde lockfiles.
- Installatie-ZIP, indienings-ZIP en WordPress.org stagingbestanden in `dist/`.

## Controles tijdens herstel

- PHPUnit: 32 tests, 79 assertions geslaagd.
- WordPress coding standards: nul fouten, nul waarschuwingen.
- Checkout HTTP-integratie: 62 controles geslaagd, inclusief klassieke checkout en Checkout Blocks met HPOS aan en uit.
- Plugin Check op de installatie-ZIP: nul fouten, nul waarschuwingen.
- Alle negen PNG-deliverables gecontroleerd tegen dimensies en hashes in het manifest.
- Python-scripts en JSON-configuratie gecontroleerd op geldige syntax.

De oude compatibiliteitsmatrix en multisitecontroles zijn tijdens dit herstel niet opnieuw uitgevoerd. Historische beschrijvingen in VERIFICATION.md blijven historische resultaten. Screenshots zijn uit de afbeeldingsdata in de sessielogs hersteld en kunnen opnieuw gecodeerd zijn; zie media/VALIDATION.md.

## Bewaring

Het herstel staat op de lokale branch `recovery/1.2.0`. Er is niets naar GitHub gepusht. Een volledige back-up inclusief Git-geschiedenis, broncode, media, releasebestanden en herstelbewijs staat buiten de projectmap:

`/home/mustafa/Downloads/wp-checkout-blocker-by-talistech-recovered-2026-10-12.tar.gz`

Vendor en node_modules zijn uitgesloten van de back-up. Afhankelijkheden kunnen met `composer install` en `npm ci` opnieuw worden geïnstalleerd. De mediabibliotheek gebruikt `npm ci --prefix verification/media-tools`.

De bewaarde sessie en gereconstrueerde handelingen staan onder `verification/recovery/`. De nieuwe pakketcontroles staan onder `verification/package/`.

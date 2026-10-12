// Exact-size exports from imagegen masters; no retouching of browser screenshots.
// Run from the repository root after installing sharp under verification/media-tools.
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const sharp = require(path.join(root, 'verification/media-tools/node_modules/sharp'));
const media = path.join(root, 'media');
const outputs = [
  ['icon-master.png', 'wordpress-org/icon-128x128.png', 128, 128, 'WordPress.org search and plugin list icon'],
  ['icon-master.png', 'wordpress-org/icon-256x256.png', 256, 256, 'WordPress.org Retina plugin icon'],
  ['icon-master.png', 'avatar/avatar-1024x1024.png', 1024, 1024, 'Plugin avatar for profiles and directories'],
  ['banner-master.png', 'wordpress-org/banner-772x250.png', 772, 250, 'WordPress.org plugin page banner'],
  ['banner-master.png', 'wordpress-org/banner-1544x500.png', 1544, 500, 'WordPress.org Retina plugin page banner'],
  ['social-wide-master.png', 'social/social-1200x630.png', 1200, 630, 'Social link preview and landscape announcement'],
  ['social-square-master.png', 'social/social-1080x1080.png', 1080, 1080, 'Square social announcement'],
];
async function main() {
  const manifest = [];
  for (const [source, file, width, height, use] of outputs) {
    const destination = path.join(media, file);
    await fs.mkdir(path.dirname(destination), { recursive: true });
    await sharp(path.join(media, 'sources', source))
      .resize(width, height, { fit: 'cover', position: 'centre', kernel: 'lanczos3' })
      .toColourspace('srgb').png({ compressionLevel: 9 }).toFile(destination);
    manifest.push({ file, width, height, use, source: 'imagegen artwork, resampled to required export dimensions' });
  }
  for (const [file, use] of [
    ['wordpress-org/screenshot-1.png', 'Real plugin settings page with synthetic block rules'],
    ['wordpress-org/screenshot-2.png', 'Real classic checkout refused for blocked@example.com'],
  ]) {
    const m = await sharp(path.join(media, file)).metadata();
    manifest.push({ file, width: m.width, height: m.height, use, source: 'Unretouched Chromium element screenshot from local WordPress' });
  }
  for (const item of manifest) {
    const bytes = await fs.readFile(path.join(media, item.file));
    const m = await sharp(bytes).metadata();
    if (m.width !== item.width || m.height !== item.height || m.format !== 'png') throw new Error(item.file);
    const limit = item.file.includes('icon-') ? 1_000_000 : item.file.includes('banner-') ? 4_000_000 : item.file.includes('screenshot-') ? 10_000_000 : Infinity;
    if (bytes.length > limit) throw new Error(`${item.file}: directory size limit exceeded`);
    item.bytes = bytes.length;
    item.sha256 = crypto.createHash('sha256').update(bytes).digest('hex');
  }
  await fs.writeFile(path.join(media, 'manifest.json'), JSON.stringify({
    plugin: 'Talistech Checkout Blocker for WooCommerce', version: '1.2.1',
    slug: 'talistech-checkout-blocker-for-woocommerce', generator: 'Built-in imagegen; Playwright / Chromium for real screenshots',
    files: manifest,
  }, null, 2) + '\n');
  console.log(`Verified ${manifest.length} PNG deliverables: exact dimensions, PNG integrity and WordPress.org size limits.`);
}
main().catch(error => { console.error(error); process.exitCode = 1; });

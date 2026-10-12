// wp-env 11.16 still imports simple-git's removed CommonJS default export.
// Use the supported named factory so the fixed simple-git 4 release can be installed.
// This touches development dependencies only; none are included in the plugin ZIP.
const fs = require('node:fs');
const path = require('node:path');
const directory = path.dirname(require.resolve('@wordpress/env/package.json'));
const version = require('@wordpress/env/package.json').version;
if (version !== '11.16.0') {
  throw new Error('Review the wp-env simple-git compatibility patch before upgrading wp-env.');
}
for (const filename of ['lib/download-sources.js', 'lib/runtime/docker/download-wp-phpunit.js']) {
  const target = path.join(directory, filename);
  const original = fs.readFileSync(target, 'utf8');
  const oldImport = "const SimpleGit = require( 'simple-git' );";
  const newImport = "const SimpleGit = require( 'simple-git' ).simpleGit;";
  if (original.includes(oldImport)) {
    fs.writeFileSync(target, original.replace(oldImport, newImport));
  } else if (!original.includes(newImport)) {
    throw new Error('Unexpected wp-env source: ' + filename);
  }
}

// Parallel CLI commands can read a partially written JSON cache, then overwrite
// it without the saved runtime. Atomic replacement preserves complete reads.
// The real notification reservations still run concurrently in the database.
const cacheTarget = path.join(directory, 'lib/cache.js');
const cacheOriginal = fs.readFileSync(cacheTarget, 'utf8');
const cacheOld = "\tawait fs.writeFile(\n\t\tgetPathToCacheFile( options.workDirectoryPath ),\n\t\tJSON.stringify( existingCache )\n\t);";
const cacheNew = [
  '\tconst cacheFilePath = getPathToCacheFile( options.workDirectoryPath );',
  "\tconst temporaryCachePath = `${ cacheFilePath }.${ process.pid }.${ require( 'node:crypto' ).randomUUID() }.tmp`;",
  '\tawait fs.writeFile( temporaryCachePath, JSON.stringify( existingCache ) );',
  '\tawait fs.rename( temporaryCachePath, cacheFilePath );',
].join('\n');
if (cacheOriginal.includes(cacheOld)) {
  fs.writeFileSync(cacheTarget, cacheOriginal.replace(cacheOld, cacheNew));
} else if (!cacheOriginal.includes(cacheNew)) {
  throw new Error('Unexpected wp-env cache source; review the atomic write patch.');
}

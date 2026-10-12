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

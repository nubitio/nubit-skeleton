import { readdir, readFile } from 'node:fs/promises';
import { gzipSync } from 'node:zlib';
import { extname, resolve } from 'node:path';

const assetsDir = resolve(import.meta.dirname, '../dist/assets');
const limits = { '.js': 350 * 1024, '.css': 50 * 1024 };
const files = await readdir(assetsDir);
const failures = [];

for (const file of files) {
  const extension = extname(file);
  if (extension === '.woff' || extension === '.ttf' || /^Phosphor.*\.svg$/.test(file)) {
    failures.push(`${file}: legacy font fallback must not be emitted; keep the build woff2-only`);
    continue;
  }
  const limit = limits[extension];
  if (!limit) continue;
  const bytes = gzipSync(await readFile(resolve(assetsDir, file))).byteLength;
  if (bytes > limit) failures.push(`${file}: ${bytes} gzip bytes exceeds ${limit}`);
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exitCode = 1;
} else {
  console.log(
    'bundle budget OK (JS <= 350 KiB gzip, CSS <= 50 KiB gzip per asset, fonts woff2-only)',
  );
}

import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { spawnSync } from 'node:child_process';

const root = resolve(import.meta.dirname, '..');
const fixturePath = resolve(
  root,
  'vendor/nubitio/api-platform/contracts/x-grid-protocol.fixtures.json',
);

const readJson = async (path) => JSON.parse(await readFile(path, 'utf8'));
const compatibility = await readJson(resolve(root, 'nubit-compatibility.json'));
const backend = await readJson(resolve(root, 'composer.json'));
const frontend = await readJson(resolve(root, 'frontend/package.json'));

const failures = [];
const print = (value) => JSON.stringify(value);
// Composer stability flags ("^1.0@RC") name the same line as "^1.0".
const lineOf = (range) =>
  String(range)
    .replace(/^[^0-9]*/, '')
    .replace(/@.*$/, '')
    .split('.')
    .slice(0, 2)
    .join('.');

for (const name of compatibility.backend.packages) {
  const range = backend.require[name];
  if (!range) failures.push(`backend dependency missing: ${name}`);
  else if (lineOf(range) !== compatibility.backend.line) {
    failures.push(`${name} resolves from line ${lineOf(range)}, expected ${compatibility.backend.line}`);
  }
}

for (const name of compatibility.frontend.packages) {
  const range = frontend.dependencies[name];
  if (!range) failures.push(`frontend dependency missing: ${name}`);
  else if (lineOf(range) !== compatibility.frontend.line) {
    failures.push(`${name} resolves from line ${lineOf(range)}, expected ${compatibility.frontend.line}`);
  }
}

let fixtures;
try {
  fixtures = await readJson(fixturePath);
} catch (error) {
  failures.push(`contract fixture unavailable: ${error.message}`);
}

if (fixtures?.protocol !== compatibility.protocols.grid) {
  failures.push(`installed grid fixture uses ${fixtures?.protocol ?? 'no protocol URI'}`);
}

if (fixtures) {
  for (const section of ['operatorCases', 'loadOptionCases', 'responseCases']) {
    if (!Array.isArray(fixtures[section]) || fixtures[section].length === 0) {
      failures.push(`contract fixture section ${section} must be a non-empty array`);
    }
  }

  try {
    const frontendRequire = createRequire(resolve(root, 'frontend/package.json'));
    const hydraEntry = frontendRequire.resolve('@nubitio/hydra');
    const { HydraRemoteDataSource } = await import(pathToFileURL(hydraEntry).href);

    for (const fixture of fixtures.loadOptionCases ?? []) {
      const source = new HydraRemoteDataSource({ url: '/api/products', idField: 'id' });
      const actual = source.prepareLoadOptions(fixture.input);
      for (const [key, expected] of Object.entries(fixture.expected)) {
        if (JSON.stringify(actual[key]) !== JSON.stringify(expected)) {
          failures.push(
            `TypeScript: ${fixture.name}: ${key} expected ${print(expected)}, got ${print(actual[key])}`,
          );
        }
      }
      for (const key of fixture.absent ?? []) {
        if (key in actual) failures.push(`TypeScript: ${fixture.name}: ${key} must be absent`);
      }
    }

    for (const fixture of fixtures.responseCases ?? []) {
      const httpClient = {
        get: async () => ({ data: fixture.body, headers: new Headers(fixture.headers) }),
      };
      const source = new HydraRemoteDataSource({
        url: '/api/products',
        idField: 'id',
        httpClient,
      });
      const actual = await source.load({});
      if (actual.totalCount !== fixture.expected.totalCount) {
        failures.push(
          `TypeScript: ${fixture.name}: totalCount expected ${print(fixture.expected.totalCount)}, got ${print(actual.totalCount)}`,
        );
      }
      if (JSON.stringify(actual.gridSummary) !== JSON.stringify(fixture.expected.gridSummary)) {
        failures.push(
          `TypeScript: ${fixture.name}: gridSummary expected ${print(fixture.expected.gridSummary)}, got ${print(actual.gridSummary)}`,
        );
      }
    }
  } catch (error) {
    failures.push(`TypeScript: unable to execute fixtures: ${error.stack ?? error.message}`);
  }

  const php = spawnSync(
    process.env.PHP_BINARY ?? 'php',
    [resolve(root, 'scripts/check-grid-php.php'), fixturePath],
    {
      cwd: root,
      encoding: 'utf8',
    },
  );
  if (php.status !== 0) {
    failures.push(
      php.stderr?.trim() ||
        php.stdout?.trim() ||
        `PHP: fixture runner failed (${php.error?.message ?? php.signal ?? `status ${php.status}`})`,
    );
  } else if (php.stdout?.trim()) {
    console.log(php.stdout.trim());
  }
}

if (failures.length > 0) {
  console.error(failures.map((failure) => `- ${failure}`).join('\n'));
  process.exitCode = 1;
} else {
  console.log(`compatible: backend ${compatibility.backend.line}, frontend ${compatibility.frontend.line}, grid v1`);
}

import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const reactRoot = resolve(process.env.NUBIT_REACT_PATH ?? `${root}/../nubit-react`);
const symfonyRoot = resolve(process.env.NUBIT_SYMFONY_PATH ?? `${root}/../nubit-symfony`);

const readJson = async (path) => JSON.parse(await readFile(path, 'utf8'));
const compatibility = await readJson(resolve(root, 'nubit-compatibility.json'));
const backend = await readJson(resolve(root, 'composer.json'));
const frontend = await readJson(resolve(root, 'frontend/package.json'));
const checkSourceContracts = process.env.NUBIT_SKIP_SOURCE_CONTRACTS !== '1';

const failures = [];
const lineOf = (range) => String(range).replace(/^[^0-9]*/, '').split('.').slice(0, 2).join('.');

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

if (checkSourceContracts) {
  const react = await readJson(resolve(reactRoot, 'package.json'));
  const symfonyFixture = await readJson(
    resolve(symfonyRoot, 'packages/api-platform/contracts/x-grid-protocol.fixtures.json'),
  );
  const reactFixture = await readJson(resolve(reactRoot, 'contracts/x-grid-protocol.fixtures.json'));
  if (lineOf(react.devDependencies.react) !== lineOf(frontend.dependencies.react)) {
    failures.push('React major/minor differs between nubit-react and nubit-skeleton');
  }
  if (symfonyFixture.protocol !== compatibility.protocols.grid) {
    failures.push(`Symfony fixture uses ${symfonyFixture.protocol}`);
  }
  if (JSON.stringify(symfonyFixture) !== JSON.stringify(reactFixture)) {
    failures.push('grid protocol fixtures differ between nubit-symfony and nubit-react');
  }
}

if (failures.length > 0) {
  console.error(failures.map((failure) => `- ${failure}`).join('\n'));
  process.exitCode = 1;
} else {
  console.log(`compatible: backend ${compatibility.backend.line}, frontend ${compatibility.frontend.line}, grid v1`);
}

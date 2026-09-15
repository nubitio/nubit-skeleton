# Nubit compatibility policy

`nubit-compatibility.json` is the machine-readable compatibility declaration
for this template. A Nubit `0.x` minor is treated as a release line: packages
inside one frontend or backend line are installed lockstep.

The template CI installs both locked dependency sets and executes the grid
fixture shipped by the installed `nubitio/api-platform` package. PHP executes
the operator cases through `GridFilterHelper`; TypeScript executes the load
option and response cases through the public `@nubitio/hydra` entry point.
The scheduled canary runs the same gate after resolving the latest packages in
the declared backend and frontend lines.

| Channel | Purpose | Failure meaning |
| --- | --- | --- |
| stable | Locked dependencies committed here | Template regression |
| latest | Fresh resolution within declared lines | Release drift |

Changing a protocol requires a new protocol URI. Changing only fixtures within
that protocol is allowed when it clarifies existing behaviour and both adapters
pass the same cases.

From a clean checkout, run the composed gate with:

```bash
composer install --no-interaction --no-progress
corepack pnpm --dir frontend install --frozen-lockfile
node scripts/check-compatibility.mjs
```

Fixture changes originate in `nubit-symfony` under
`packages/api-platform/contracts/`. Semantic changes require a new protocol URI
and coordinated `nubitio/api-platform` and `@nubitio/hydra` releases. Each
failure names the fixture and the PHP or TypeScript implementation that
diverged.

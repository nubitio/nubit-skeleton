# Agent guide — Nubit skeleton

Full-stack admin starter: Symfony 7.4 + API Platform 4 (backend) and React 19
+ `@nubitio/react-admin` (frontend). CRUD screens are **generated from the API
docs** — adding a feature means defining a PHP entity, not building UI.

> Detailed workflow, templates and gotchas: the **nubit-stack skill**
> (`.claude/skills/nubit-stack/SKILL.md`, also exposed at
> `.agents/skills/nubit-stack/` for tools following the
> [Agent Skills](https://agentskills.io) convention). Read it before adding
> resources or touching auth.

## Commands

```bash
docker compose up -d --build                                  # full stack
docker compose exec app php bin/console doctrine:migrations:diff --no-interaction
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed              # admin@example.com / admin1234
docker compose exec app php bin/console cache:clear
cd frontend && corepack pnpm dev                              # or use the compose `frontend` service
cd frontend && corepack pnpm build                            # tsc + vite build
```

URLs: app/API http://localhost:8000 · React http://localhost:5173 · Mercure http://localhost:3000

## Map

| Path | What |
| --- | --- |
| `src/Entity/Product.php` | Reference entity — copy its pattern for new resources |
| `src/Command/SeedCommand.php` | Demo data |
| `config/packages/security.yaml` | Firewall + access control (JWT authenticator from the bundle) |
| `config/packages/nubit_admin.yaml` | Auth TTLs, cookie flags, docs locale |
| `frontend/src/App.tsx` | Providers, Mercure, toast runtime, menu, routes, `/api/me` session |
| `src/Controller/MeController.php` | `GET /api/me` — username + roles for `SmartCrudRolesProvider` |
| `frontend/src/pages/ProductsPage.tsx` | Reference page — `defineResource` + `SmartCrudPage` + audit trail |
| `frontend/src/pages/SalesDocumentsPage.tsx` | Master-detail — `formDetail`, drawer, `canEditRow` |
| `src/Entity/SalesDocument.php` | Embedded lines collection + processor totals |

## Conventions

- PHP ≥ 8.5, `declare(strict_types=1)`, fluent setters (`setX(): static`).
- Entities expose `#[ApiResource]` + `#[ApiFilter(DataGridFilter::class)]` +
  `x-crud` hints per property; validation via `#[Assert\*]`.
- Frontend styling uses the `@nubitio/ui` CSS tokens (`var(--surface-1)`,
  `var(--text-secondary)`, …) — never hardcode colors; dark mode must keep working.
- Spanish UI? Wrap the app in `UiStringsProvider strings={ES_UI_STRINGS}` and
  set `lng: 'es'` in `frontend/src/i18n.ts`.
- Don't edit `vendor/` or `frontend/node_modules/`; library changes belong in
  [nubit-react](https://github.com/nubitio/nubit-react) /
  [nubit-symfony](https://github.com/nubitio/nubit-symfony).

## Verification bar for any change

Backend: `docker compose exec app php bin/console lint:container` + the curl
round-trip in the skill (login → filtered grid query → `X-Total-Count`).
Frontend: `corepack pnpm build` (includes `tsc --noEmit`) + check the page in
the browser, light and dark.

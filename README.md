# Nubit Skeleton

Full-stack starter for the Nubit admin stack: **Symfony 7.4 + API Platform 4** backend ([`nubitio/admin-bundle`](https://github.com/nubitio/nubit-symfony)) and a **React** admin ([`@nubitio/react-admin`](https://www.npmjs.com/package/@nubitio/react-admin)) that generates CRUD screens from your API docs.

Annotate an entity → get a datagrid with filters/sorting/search, create/edit forms with validation, auth, and real-time refresh. Build vertical systems (ERP, POS, industry SaaS) in hours.

## Start a new project

Two ways to get a copy (it's a [template repository](https://github.com/nubitio/nubit-skeleton)):

```bash
# a) GitHub CLI — new repo under your account, no shared history
gh repo create my-app --template nubitio/nubit-skeleton --private --clone && cd my-app

# b) Web UI — the "Use this template" button on GitHub
```

Then boot it:

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed
```

Open **http://localhost:5173** and sign in with `admin@example.com` / `admin1234`.

The template ships one resource — `Product` — single-tenant, with every
optional module off. That is deliberate: anything enabled here becomes schema,
endpoints and config your project inherits and then has to remove. Turn on what
you need, one at a time; [`config/packages/nubit_admin.yaml`](config/packages/nubit_admin.yaml)
lists each module and what it adds.

**Multi-tenant?** Add it when you need it — the tenancy layer is a feature, not
a foundation:

```bash
composer require nubitio/tenant-bundle
```

Then set `nubit_tenant.enabled: true`, point `tenant_entity` at your tenant root,
and mark tenant-owned entities with `TenantOwnedInterface` + `TenantOwnedTrait`.
The nubit-stack skill's `references/platform-and-saas.md` walks the whole path,
and [nubit-inmobiliaria](https://github.com/nubitio/nubit-inmobiliaria) is a
multi-tenant application built this way.

| Service | URL |
| --- | --- |
| React admin (Vite dev) | http://localhost:5173 |
| API + docs | http://localhost:8000/api |
| Mercure hub (proxied in dev) | http://localhost:5173/.well-known/mercure |
| Session profile | `GET /api/me` |

OpenTelemetry is available as an opt-in Compose profile. See
[`docs/observability.md`](docs/observability.md) for the local Collector smoke
test and production guidance. Analytics is off; [`docs/analytics.md`](docs/analytics.md)
covers provider configuration, operations and failure recovery once you enable it.

> Before anything real: change `APP_SECRET` in `.env` (≥ 32 bytes — it signs the auth JWTs) and the database/Mercure passwords in `compose.yaml`.

Before deployment, run `php bin/console app:doctor --strict`. Production and
strict checks fail while any known template secret or password is active.

## Add your own resource

1. Create an entity with the grid filter and `x-crud` hints (see [`src/Entity/Product.php`](src/Entity/Product.php)):

```php
#[ApiResource(operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()], mercure: true)]
#[ApiFilter(DataGridFilter::class)]
class Customer
{
    #[ApiProperty(openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 0]])]
    private string $name = '';
    // ...
}
```

2. Migrate: `docker compose exec app php bin/console doctrine:migrations:diff && ... migrations:migrate`

3. Add a page (see [`frontend/src/pages/ProductsPage.tsx`](frontend/src/pages/ProductsPage.tsx)):

```tsx
const customers = defineResource('/api/customers', { title: 'Customers' });
export const CustomersPage = () => <SchemaCrudPage resource={customers} />;
```

4. Register the route + menu item in [`frontend/src/App.tsx`](frontend/src/App.tsx). Done — full CRUD.

Master-detail documents, sequences, workflows, audit trails and spreadsheet
export are all supported and all off here. The **nubit-stack skill**
(`.claude/skills/nubit-stack/`) has a worked example of each, and
[nubit-inmobiliaria](https://github.com/nubitio/nubit-inmobiliaria) is a real
application built on them.

## Auth

The bundle ships dual web/mobile JWT auth (see the [admin-bundle README](https://github.com/nubitio/nubit-symfony/tree/main/packages/admin-bundle)):

- **Web**: HttpOnly cookies, auto-refresh, same-origin via the Vite proxy. Already wired in `frontend/src/App.tsx`.
- **Mobile/API**: send `"response_mode": "json"` on login, then `Authorization: Bearer <token>`; refresh with `{ "refreshToken": "..." }`.

Refresh tokens rotate and are stored hashed (`nubit_refresh_token` table).

## AI-agent ready

The template ships agent knowledge so coding agents can build features correctly from the first prompt:

- **`.claude/skills/nubit-stack/`** — [Agent Skill](https://agentskills.io) (mirrored at `.agents/skills/`): the add-a-resource workflow, field-type mapping, extension points, verification steps and known gotchas.
- **`AGENTS.md` / `CLAUDE.md`** — project map, commands and conventions for any agent.

Try it: open the project with your agent and ask *"add a Customers CRUD with name, email and an active flag"*.

## Project layout

```
src/                  Symfony app: entities, seed command
config/               security.yaml (firewall), nubit_admin.yaml, api_platform.yaml
frontend/             Vite + React admin (pnpm)
compose.yaml          FrankenPHP app + PostgreSQL + Mercure + frontend dev server
```

## Production notes

- `frontend && pnpm build` produces a static SPA (`frontend/dist`) — serve it from any static host or from FrankenPHP, with `/api` proxied to the Symfony app.
- Set `cookie_secure: true` (default outside dev), real secrets, and a persistent `MERCURE_JWT_SECRET`.
- Follow the phased [privacy, telemetry, analytics and feature-flag rollout](docs/platform-rollout.md) before enabling external providers.

## Security

Report vulnerabilities privately through the repository's **Security** tab; see
[`SECURITY.md`](SECURITY.md). Note that the placeholder secrets in `.env` are
deliberate and are not a finding — `App\Security\ProductionReadiness` refuses
them outside dev.

## License

MIT

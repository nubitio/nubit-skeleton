---
name: nubit-stack
description: Build and troubleshoot CRUD admin features on the Nubit stack (Symfony + API Platform backend, @nubitio/react-admin frontend). Use whenever the user asks to add a resource/entity/page/module, customize a grid, form, filter, or column, wire up auth/roles/permissions/feature flags, add SSO/single sign-on (OIDC, Okta, Entra ID, Google Workspace, Keycloak), file uploads, an audit trail, exports (XLS/PDF/spreadsheet download), email or in-app notifications, a dashboard/KPI page, a document workflow (invoices, orders, sequences, status transitions), master-detail/line items, multi-tenant/SaaS behavior (quotas, billing UI, per-tenant data isolation, database backups), or debug why a screen isn't showing fields or data — even if they just say "add a Customers page", "why isn't this field showing up", or "add an export button" without naming the stack. The frontend GENERATES screens from the backend's API docs, so most features need zero hand-written frontend field code — reach for this skill before hand-building a datagrid, form, chart, login flow, or export endpoint.
---

# Building with the Nubit stack

This app generates admin CRUD screens from the backend's OpenAPI/Hydra docs.
The golden rule: **define the resource once, in PHP**. The React side only
needs a route. Do not hand-build datagrids or forms unless explicitly asked.

## Architecture in one minute

| Layer | Where | Role |
| --- | --- | --- |
| Entities + API | `src/Entity/*.php` | `#[ApiResource]` + `DataGridFilter` + `x-crud` hints drive everything |
| Auth, formats, services | `nubitio/admin-bundle` (vendor) | JWT dual cookie/Bearer, JSON formats, grid filter — already wired |
| React admin | `frontend/src/` | `createNubitApp()` bootstraps providers; `SchemaCrudPage` renders from `/api/docs.jsonld` |
| Infra | `compose.yaml` | FrankenPHP (`app`, :8000) + PostgreSQL + Mercure (:3000) + Vite (:5173) |

Run backend commands inside the container: `docker compose exec app php bin/console …`

## Workflow: add a CRUD resource

### 1. Entity (the only place fields are defined)

```php
// src/Entity/Customer.php — follow src/Entity/Product.php
#[ORM\Entity]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    mercure: true,                        // optional: live grid refresh
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(DataGridFilter::class)]       // Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter
class Customer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]                    // → required field with * in the form
    #[ApiProperty(openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 0]])]
    private string $name = '';
    // … getters/setters fluent (setX(): static)
}
```

`x-crud` hints (all optional):

| Hint | Effect |
| --- | --- |
| `filterable` / `sortable` | show in the filter row / allow sorting |
| `order` | column order |
| `width` | column width in px |
| `hideInGrid: true` | drop the column from the grid, keep the field in the form |
| `showInForm: false` | drop the field from the form, keep the column — computed/server-set fields |
| `readonly: true` | render in the form but not editable |
| `format` | `'currency'` (decimal as money: right-aligned, locale-aware separators, 2 decimals — see `price` in `src/Entity/Product.php`) · `'image'` / `'file'` (media upload dropzone — see `references/detail-views-and-media.md`) |

⚠️ `hidden` and `visibleOnForm` are the **deprecated** spellings of
`hideInGrid` and `showInForm`. They still work, and both are still all over
older code and docs, but they log a one-time console warning in dev. Write the
new names in anything you add; only touch existing ones when you're already
editing that property.

Closed value sets: add `'enum' => ['draft', 'sent', 'paid']` to the
`openapiContext` and the form renders a select with humanized labels
('credit_note' → "Credit Note") instead of free text.

Column labels: humanized from the property name (`firstName` → "First Name").
For custom/translated labels set `description: 'app.customer.name'` (an i18n
key) — the bundle translates it into the docs.

Field type → control mapping (automatic, from Doctrine/serialization types):
string → text · text → textarea · decimal/float/int → number · bool → switch +
"All/Yes/No" grid filter · datetime → date picker · **ManyToOne → entity
select** (loads options from the related resource; shows its `name`/`title`/
`description` field, submits the IRI). The related entity must also be an
`#[ApiResource]`.

### 2. Migrate

```bash
docker compose exec app php bin/console doctrine:migrations:diff --no-interaction
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Frontend page (3 lines) + route + menu

```tsx
// frontend/src/pages/CustomersPage.tsx
import { SchemaCrudPage, defineResource } from '@nubitio/react-admin';
const customers = defineResource('/api/customers', { title: 'Customers' });
export const CustomersPage = () => <SchemaCrudPage resource={customers} />;
```

In `frontend/src/App.tsx`: add the menu item and route to `createNubitApp()`:

```tsx
import { createNubitApp } from '@nubitio/react-admin';
import { CustomersPage } from './pages/CustomersPage';

const { App } = createNubitApp({
  title: 'Nubit Admin',
  menu: [
    // …existing items…
    { text: 'Customers', icon: 'ph ph-users', path: '/customers' },
  ],
  routes: [
    // …existing routes…
    { path: '/customers', element: <CustomersPage /> },
  ],
});

export { App };
```

Icons are Phosphor classes (`ph ph-*`). Optional `roles` on menu items hide entries
by session role; `filterMenu` adds app-specific rules (tenant features, runtime config).

### 4. Verify (always do this)

```bash
# x-crud hints present for the new resource?
curl -s http://localhost:8000/api/docs.jsonld | grep -o 'customers' | head -1
# login + grid round-trip
curl -s -c /tmp/cj -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' -d '{"username":"admin@example.com","password":"admin1234"}'
curl -s -b /tmp/cj 'http://localhost:8000/api/customers?filter%5B0%5D%5B0%5D=name&filter%5B0%5D%5B1%5D=contains&filter%5B0%5D%5B2%5D=foo' -D - -o /dev/null | grep -i x-total-count
```

Then check the browser at http://localhost:5173 (grid renders, create works).
Contract tests (no tenant plugins): `docker compose exec app php vendor/bin/phpunit`.
After changing entities: `docker compose exec app php bin/console cache:clear`
**and then `docker compose restart app`** — the FrankenPHP worker keeps API
Platform property metadata in process memory, so new fields won't appear in
/api/docs.jsonld until the worker restarts (clear first, then restart).

## Resource naming (URL discovery)

Since @nubitio 0.3.0 the frontend reads the **real** collection URLs from the
API entrypoint (`GET /api`) and only falls back to a dash-case + pluralize
heuristic when that fetch fails. Consequences:

- `defineResource` paths must match the **backend's actual routes** (whatever
  the configured path generator produces — e.g. `/api/sales-documents` with
  the dash generator).
- A `No schema found for <url>` error lists the known URLs and suggests the
  closest match — follow the suggestion.
- Entity names with irregular plurals are no longer fatal, but the heuristic
  fallback still mangles them; prefer regular names for resilience.

## Integration wiring (already in this skeleton)

- **Mercure**: `createNubitApp()` registers `MercureProvider` by default (`hydra: true`).
  Hub URL `/.well-known/mercure` (Vite proxies to the hub in dev — see `vite.config.ts`).
  SSE topic matching is controlled by `CoreConfigProvider`'s `mercureTopicOrigin` prop
  (not an env var nubit-react reads itself) — point it at the API's public origin
  (`http://localhost:8000` in Docker) so topics match API Platform `@id` IRIs; the
  backend's `DEFAULT_URI` must agree. Per-resource `mercure?: boolean` on
  `defineResource` (default `true`) opts a single resource out of the subscription.
- **Toasts**: `useAppRuntime()` + `ToastHost` feed `CoreProvider.runtime.notify`.
- **Session**: `GET /api/me` on boot; logout calls `POST /api/auth/logout`.
  `useSession()` returns `{ session, refresh, logout, roles, username }` — there is
  **no `session.profile.permissions`**; role checks read `roles` (array of strings),
  not a permissions map. `session.profile` also carries `appProfile`, `tenant`, and
  `features` (see feature flags below).
- **Runtime config**: `RuntimeConfigProviderInterface` on the backend (behind
  `nubit_admin.runtime_config: true`, off by default in this skeleton) serves
  `GET /api/runtime-config`. On the frontend it's **not** a `createNubitApp` option —
  call the `useRuntimeConfig()` hook directly wherever you need the values (e.g. inside
  `filterMenu` or a page component).
- **CRUD-engine translations**: setting `lng: 'es'` on your i18next instance only
  affects strings you own. To translate the CRUD engine's own built-in strings
  (grid toolbar, "History" button, validation messages) call `initCoreI18n()` from
  `@nubitio/react-admin` once at startup — without it those stay in whatever locale
  the bundled default is, regardless of `lng`.
- **Master-detail demo**: `SalesDocumentsPage.tsx` + `SalesDocument` entity.

## Beyond flat CRUD

Three reference files cover everything past the basic grid+form. Read the one
that matches what's being built — don't load them all up front.

- **`references/detail-views-and-media.md`** — line items inside a form
  (master-detail), drawer/page view modes, image/file uploads, audit-trail
  panels, lifecycle timelines, grid/form footer summaries.
- **`references/erp-and-permissions.md`** — the full ERP document pattern
  (sequence numbers + status workflow + audit + embedded lines, e.g.
  invoices), grouping pages into a tabbed module (`FeatureHubLayout`),
  role-based UI gating, per-row workflow actions/locking, SSO/OpenID Connect,
  and smaller customizations (soft delete, virtual columns, roles per
  operation, theming, extra JWT claims).
- **`references/platform-and-saas.md`** — multi-tenant apps (`nubitio/tenant-bundle`)
  and per-tenant backups, feature flags/entitlements and quota UI, export
  (XLS/PDF), email + in-app notifications, the `@nubitio/dashboard` package,
  analytics/observability, and grid summary aggregation. This skeleton is
  `app_profile: internal` by default — read this file only when the app needs
  `saas`/`hybrid` behavior or one of these specific features.

Sections carry a "since" note when a capability arrived after the initial
0.x line. This skeleton pins `nubitio/*` 0.14 and `@nubitio/*` 0.11 (see
`nubit-compatibility.json`); `composer show nubitio/admin-bundle` and
`frontend/package.json` are the ground truth for what's actually installed.

This skeleton defaults `viewMode` to `'dialog'` when a resource sets none
(`'dialog' | 'drawer' | 'page'`) — pick `drawer` or `page` explicitly for
anything with more than a handful of fields.

## Gotchas (cost real debugging time — respect them)

1. `application/json` POST/PATCH bodies work because the **bundle** prepends
   the API Platform formats. Don't add `formats:` to `api_platform.yaml`
   unless overriding deliberately.
2. `APP_SECRET` must be **≥ 32 bytes** — it signs the HS256 JWTs; the bundle
   fails fast otherwise.
3. The frontend pins **pnpm@10** (`packageManager` field). Don't switch to
   pnpm 11/npm; use `corepack pnpm …`.
4. Auth cookies are HttpOnly — JS can't read them. Session detection = probe
   an API endpoint (see `frontend/src/App.tsx`).
5. Refresh tokens **rotate**: reusing an old one returns 401 by design.
6. Fonts (Inter/Syne) load via `@fontsource` imports in `frontend/src/main.tsx`;
   removing them degrades typography to system-ui.
7. In dev, Vite proxies `/api` → the `app` container (same-origin cookies).
   Don't add CORS config for the SPA; it's not cross-origin.
8. New vendor service classes are NOT autodiscovered — bundle registers its
   own; app services go in `config/services.yaml` as usual.
9. Resources with `mercure: true` publish **after** the flush. A dead hub no
   longer 500s an HTTP request (`nubit_admin.mercure.fail_safe`, default
   `true`): the write returns 2xx and the failure is logged as a warning
   ("Mercure publish failed") — live refresh degrades to manual. In
   messenger/console contexts the error is rethrown so async `Update`
   routing keeps retrying. A broken hub shows as 502 on
   `/.well-known/mercure`; if host port 3000 is taken, start the stack with
   `MERCURE_PORT=3001 docker compose up -d`.
10. `formDetail.inferFields` is legacy — inference from the backend's
    `x-embedded-lines` metadata is automatic now. Only set `inferFields: false`
    if you deliberately want to suppress it and hand-write `fields`.

## Library source (for deeper digging)

- Frontend packages: https://github.com/nubitio/nubit-react (`@nubitio/*` on npm)
- Backend packages: https://github.com/nubitio/nubit-symfony (`nubitio/*` on Packagist)

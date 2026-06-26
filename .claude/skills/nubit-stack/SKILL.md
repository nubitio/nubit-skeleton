---
name: nubit-stack
description: Build CRUD admin features on the Nubit stack (Symfony + API Platform + @nubitio/react-admin). Use when adding resources/entities/pages, customizing grids or forms, wiring auth, or troubleshooting this skeleton. The frontend GENERATES screens from the API docs — most features need zero frontend field code.
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
| React admin | `frontend/src/` | `createNubitApp()` bootstraps providers; `SmartCrudPage` renders from `/api/docs.jsonld` |
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

`x-crud` hints (all optional): `filterable`, `sortable`, `order` (column order),
`width` (px), `hidden: true` (exclude from grid, keep in form),
`visibleOnForm: false` (exclude from form, keep in grid — for computed/server-set
fields), `format: 'currency'` (decimal renders as money: right-aligned,
locale-aware thousands separators, 2 decimals — see `price` in
`src/Entity/Product.php`).

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
import { SmartCrudPage, defineResource } from '@nubitio/react-admin';
const customers = defineResource('/api/customers', { title: 'Customers' });
export const CustomersPage = () => <SmartCrudPage resource={customers} />;
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
  Set `VITE_MERCURE_TOPIC_ORIGIN` to the API public origin (`http://localhost:8000`
  in Docker) so SSE topics match API Platform `@id` IRIs; backend uses
  `DEFAULT_URI` for the same value.
- **Toasts**: `useAppRuntime()` + `ToastHost` feed `CoreProvider.runtime.notify`.
- **Session**: `GET /api/me` on boot; logout calls `POST /api/auth/logout`.
- **Runtime config** (opt-in): `runtime_config: true` + `RuntimeConfigProviderInterface`
  → `GET /api/runtime-config`; React hook `useRuntimeConfig()`. Off by default in
  this skeleton — enable when the app needs UI flags/defaults separate from `/api/me`.
- **Master-detail demo**: `SalesDocumentsPage.tsx` + `SalesDocument` entity.

## Master-detail (lines inside a form), drawer and page modes

`defineResource` accepts far more than `title` — use the engine:

- `viewMode: 'drawer' | 'page' | { mode: 'drawer', drawerSize: 'md' }`.
  Page mode needs BOTH routes (`/sales` and `/sales/:id`) pointing at the same
  page component plus `routing: { routeParam: 'id' }` — or use the
  `crudRoute('/sales', <SalesPage />)` helper which returns both routes.
- `formDetail: { propertyName: 'lines', url: embeddedLinesUrl('/api/sales_document_lines', 'document'), fields: [...] }`
  renders an editable detail grid inside the form. Rows are submitted
  **embedded** in the parent payload under `propertyName`; on edit they are
  reloaded from `url` (the `{id}` placeholder is required — without it the
  edit form shows an empty detail grid). On the line entity add
  `#[EmbeddedLines(parentProperty: 'document')]` — the bundle serves the reload
  endpoint; no custom controller. Extend `AbstractEmbeddedLinesProcessor` on the
  parent processor to bind lines. The "add row" button has aria-label
  `Add item` and no visible text.
- `gridDetail: { url: '...?document={id}', fields: [...] }` adds an expandable
  row panel to the main grid (read-only). Expose an API Platform
  `SearchFilter` on the child's parent property so `?document=<id>` works.
- Detail `fields` accept builder instances directly (`entityField(...)
  .name('product')`) — `.build()` is called for you (also valid to call it
  yourself).
- `entityField(url, valueField, textField)`: use `valueField: '_iri'` — that
  is what the Hydra data source injects on option rows. `'@id'` will not
  resolve labels (plain-JSON option payloads have no `@id`).
- `onDetailRowsChanged(formRef)` lets you recompute header fields live from
  `formRef.current?.getDetailData()`.

Backend side for embedded detail rows: serialization groups on parent and
line fields, `cascade: ['persist','remove']` + `orphanRemoval: true` on the
collection, and a state processor that sets the back-reference and computes
amounts on every save. Detail rows are sent without ids → treat saves as full
replace. Don't put groups on the line's back-reference property (circular).
Compute totals in the parent's processor; the line's own processor never runs
for embedded writes.

To keep an embedded collection out of the auto-generated form, use
`#[ApiProperty(readable: false)]` (excluded from reads entirely) or the
`x-crud: ['visibleOnForm' => false]` hint (column stays in the grid).
Plain `x-crud: hidden` only hides grid columns.

## Uploads / media library

`nubit_admin.media.enabled: true` (already on in this skeleton) gives you the
full pipeline. To add an image/file to an entity:

```php
#[ORM\ManyToOne(targetEntity: \Nubit\AdminBundle\Media\Entity\Media::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
#[ApiProperty(openapiContext: ['x-crud' => ['format' => 'image', 'hidden' => true]])]
private ?Media $photo = null;   // see src/Entity/Product.php
```

`format: 'image'` (or `'file'` for documents) renders a dropzone that uploads
**instantly** to `POST /api/media` (multipart, field `file`) and submits only
the media IRI with the form. The serialized `path` is always a public URL
(default: the bundle streaming route `/api/media/{id}/file`). Storage is
local `var/uploads` by default; S3 = point `media.storage.filesystem` at a
FilesystemOperator service. Schedule `bin/console nubit:media:purge` — deletes
are soft and abandoned-form uploads orphan files.

## Audit trail (change history per row)

`nubit_admin.audit.enabled: true` (already on here) + `#[Auditable]`
(`Nubit\ApiPlatform\Attribute\Auditable`) on the entity records field-level
before/after diffs of every create/update/delete, attributed to the logged-in
user. Wire the panel per resource:

```ts
defineResource('/api/products', {
  auditTrail: { enabled: true, apiUrl: (id) => `/api/audit-trail/product/${id}` },
})
```

The grid gains a History toolbar button acting on the selected row (see
`ProductsPage.tsx` + `src/Entity/Product.php`). The `{resource}` URL segment
is the lowercased class short name, or `#[Auditable(resource: '...')]`.
Diffs skip `ignored_fields` (createdAt/updatedAt/password by default) and
collection contents; relations collapse to their id. Schedule
`bin/console nubit:audit:purge` — the log grows with every audited write.

## Timelines (document lifecycles and event logs)

`Timeline` / `TimelineItem` from `@nubitio/react-admin` — one primitive, two
variants, fully token-themed:

- `variant="stepper"`: workflow stages (e.g. a document lifecycle: draft →
  sent → accepted/rejected). `status` per item: `complete` (check), `current`
  (ring), `pending`, `error` (red ✗). Use this instead of alert()s or ad-hoc
  status text when a row has a state machine. Add
  `orientation="horizontal"` for wizard/checkout layouts (1 → 2 → 3, labels
  under the markers).
- `variant="log"`: chronological events with tone-colored dot markers
  (`tone: success|info|danger|warning`) + `timestamp`/`dateTime`. The
  AuditTrailPanel renders change history with it automatically — reach for it
  directly when building custom activity feeds.

```tsx
<Timeline variant="stepper" title="F001-672" description="SUNAT status">
  <TimelineItem status="complete" title="Draft created" />
  <TimelineItem status="current" title="Awaiting CDR" />
  <TimelineItem status="error" title="Rejected · code 2017" />
</Timeline>
```

## Summaries (grid footers and line totals)

- `formDetail.summary: { sticky: true, items: [...] }` adds a footer to the
  lines grid inside the form (e.g. running tax + total while editing). Safe
  there: the form always loads ALL lines of the document.
- `summaryFields: [...]` adds the same footer to the MAIN grid, but it is
  computed client-side over the **loaded page only** — on paginated grids the
  number lies once there is more than one page. **Don't use it on paginated
  resources** until server-side summaries exist. The currency preset reads
  the app-wide currency from `<CoreConfigProvider currency="USD">`; per-item
  `currency` overrides it, and with neither set it falls back to plain
  fixed-point formatting.

## ERP document pattern — Sequence + Workflow + Auditable + lines

The reference implementation is `src/Entity/Invoice.php` + `InvoiceLine.php` +
`src/State/InvoiceProcessor.php` + `frontend/src/pages/InvoicesPage.tsx`.
Copy this for every document type (purchase orders, receipts, credit notes…).

### Backend — four attributes on the header entity

```php
// Install once: composer require nubitio/workflow-bundle nubitio/sequence-bundle
// Both bundles are auto-registered in config/bundles.php by Flex.

#[Sequence(field: 'number', name: 'invoice', prefix: 'INV-', padding: 4)]
#[Workflow(
    field: 'status',
    transitions: [
        'confirm' => ['from' => ['draft'], 'to' => 'confirmed', 'label' => 'Confirm'],
        'mark_paid' => ['from' => ['confirmed'], 'to' => 'paid', 'label' => 'Mark as paid', 'roles' => ['ROLE_ADMIN']],
        'cancel'   => ['from' => ['draft', 'confirmed'], 'to' => 'cancelled', 'label' => 'Cancel', 'roles' => ['ROLE_ADMIN']],
    ],
)]
#[Auditable(resource: 'invoice')]
#[ApiResource(processor: InvoiceProcessor::class, ...)]
class Invoice { /* lines collection, header fields, recalculateTotals() */ }
```

- `#[Sequence]` fills `$number` on first persist; skip if field already set.
  Register any `scope: ['customer']` paths to scope counters per dimension.
- `#[Workflow]` auto-registers `POST /api/invoices/{id}/transition/{name}`.
  `SmartCrudPage` reads `x-workflow` from `/api/docs.jsonld` and builds row
  action buttons automatically — no frontend code needed.
  Optional `guard: MyGuard::class` (implement `WorkflowGuardInterface`) for
  domain rules that go beyond role checks.
- `#[Auditable]` records field-level diffs. Use `#[AuditMasked]` on sensitive
  properties to exclude them.
- Status field: mark `'visibleOnForm' => false` in `x-crud` — the workflow
  engine sets it, the user never edits it directly.

### Line entity

```php
#[EmbeddedLines(parentProperty: 'invoice', normalizationGroups: ['invoice:read'])]
#[ORM\Entity]
class InvoiceLine { /* product, quantity, unitPrice, taxRate, lineTotal */ }
```

### State processor

```php
// config/services.yaml — required, interface has multiple candidates
App\State\InvoiceProcessor:
    arguments:
        $persistProcessor: '@api_platform.doctrine.orm.state.persist_processor'
```

```php
final readonly class InvoiceProcessor extends AbstractEmbeddedLinesProcessor {
    protected function supports(mixed $data): bool   { return $data instanceof Invoice; }
    protected function linesProperty(): string        { return 'lines'; }
    protected function lineSetter(): string           { return 'setInvoice'; }
    protected function afterLinesSynced(mixed $data): void {
        if ($data instanceof Invoice) { $data->recalculateTotals(); }
    }
}
```

### Frontend page

```tsx
// SmartCrudPage auto-reads x-workflow and builds transition buttons — no rowActions needed.
const invoices = defineResource('/api/invoices', {
  title: 'Invoices',
  viewMode: { mode: 'drawer', drawerSize: 'lg' },
  permissions: {
    canView: true,
    canEditRow: (row) => !['paid', 'cancelled'].includes(String(row.status)),
    canDeleteRow: (row) => row.status === 'draft',
  },
  auditTrail: { enabled: true, apiUrl: (id) => `/api/audit-trail/invoice/${id}` },
  formDetail: {
    propertyName: 'lines',
    url: embeddedLinesUrl('/api/invoice_lines', 'invoice'),
    allowAdding: true, allowDeleting: true, allowUpdating: true,
    fields: [
      entityField('/api/products', '_iri', 'name').name('product').required(true).build(),
      numberField().name('quantity').precision(2).build(),
      currencyField().name('unitPrice').build(),
      numberField().name('taxRate').precision(2).build(),
      currencyField().name('lineTotal').readonly(true).build(),
    ],
  },
});
export function InvoicesPage() { return <SmartCrudPage resource={invoices} />; }
```

## Module navigation (FeatureHubLayout)

Group related pages under a single sidebar entry with tabs. Required for ERP
modules (Sales, Purchasing, Inventory, HR…).

`FeatureHubLayout` uses `<Outlet />` so it **must** be used as a React Router
layout route inside a nested `<Routes>`. The parent App.tsx entry needs `/*`.

```tsx
// frontend/src/pages/SalesModule.tsx
import { Navigate, Route, Routes } from 'react-router-dom';
import { FeatureHubLayout } from '@nubitio/react-admin';

const BASE = '/sales';

export function SalesModule() {
  return (
    <Routes>
      <Route
        element={
          <FeatureHubLayout
            title="Sales"
            basePath={BASE}
            defaultPath={`${BASE}/invoices`}
            density="compact"
            tabs={[
              { key: 'invoices',  label: 'Invoices',  path: `${BASE}/invoices`,  icon: 'invoice' },
              { key: 'orders',    label: 'Orders',    path: `${BASE}/orders`,    icon: 'receipt' },
              { key: 'customers', label: 'Customers', path: `${BASE}/customers`, icon: 'users' },
            ]}
          />
        }
      >
        <Route index element={<Navigate to={`${BASE}/invoices`} replace />} />
        <Route path="invoices"  element={<InvoicesPage />} />
        <Route path="orders"    element={<OrdersPage />} />
        <Route path="customers" element={<CustomersPage />} />
      </Route>
    </Routes>
  );
}
```

```tsx
// frontend/src/App.tsx — one menu entry + wildcard route per module
menu: [{ text: 'Sales', icon: 'ph ph-receipt', path: '/sales' }],
routes: [{ path: '/sales/*', element: <SalesModule /> }],
```

`density="compact"` collapses title + tabs to a single row, leaving more
vertical space for the grid. Use `density="default"` for hub landing pages
with a subtitle or banner.

## Granular permissions on /api/me

The frontend can't read HttpOnly cookies so it can't inspect the JWT.
`GET /api/me` is the single source of truth for UX gating.

Add a `permissions` map by decorating `MeResponseBuilderInterface`:

```php
// src/Session/AppMeResponseBuilder.php
final readonly class AppMeResponseBuilder implements MeResponseBuilderInterface {
    public function __construct(private MeResponseBuilderInterface $inner) {}
    public function build(UserInterface $user): array {
        $response = $this->inner->build($user);
        $isAdmin  = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $response['permissions'] = [
            'invoice.pay'    => $isAdmin,
            'invoice.cancel' => $isAdmin,
            'catalog.manage' => $isAdmin,
            // add keys as the app grows
        ];
        return $response;
    }
}
```

```yaml
# config/services.yaml
Nubit\AdminBundle\Session\MeResponseBuilderInterface:
    alias: App\Session\AppMeResponseBuilder
App\Session\AppMeResponseBuilder:
    arguments:
        $inner: '@Nubit\AdminBundle\Session\DefaultMeResponseBuilder'
```

Frontend consumption:
```tsx
const { session } = useSession();
const perms = (session as any).profile?.permissions ?? {};
const canPay = perms['invoice.pay'] ?? false;
```

Rules:
- Keep permission keys as `module.action` strings.
- Symfony `security:` expressions on operations are the **real gate** — this
  is UX mirroring only.
- Add new keys here as modules are built; don't abstract prematurely.

## Workflow actions and row locking

- `rowActions: (row) => [...]` adds per-row menu actions (confirm + PATCH
  state transitions work well; grids with `mercure: true` refresh themselves).
- When `#[Workflow]` is on the entity, `SmartCrudPage` auto-builds row actions
  from `x-workflow` in the API docs — **do not set `rowActions` manually**.
- `permissions.canEditRow / canDeleteRow: (row) => boolean` hide Edit/Delete
  per row and make row-click open read-only. **Set `canView: true` alongside
  them** — it defaults to false, and a fully locked row would otherwise have
  no actions at all.
- Menu items render as `[role=menuitem]` buttons (useful for E2E selectors).

## Customizations

- **Computed/joined grid columns** (no ORM mapping): implement
  `Nubit\ApiPlatform\Doctrine\Filter\GridVirtualFieldInterface` (autoconfigured
  by the bundle). Use `GridFilterHelper::dqlOperator/valueForOperator/uniqueParameterName`.
- **Soft delete**: add `#[Nubit\ApiPlatform\Attribute\SoftDeletable]` to the entity (plus a
  `deleted_at` nullable datetime column) — HTTP queries hide deleted rows automatically;
  console commands see everything.
- **Change password**: `POST /api/auth/change-password` ships with the bundle
  (`currentPassword`/`newPassword`); it rotates all sessions. Purge old refresh tokens with
  `bin/console nubit:auth:purge-refresh-tokens`.
- **Roles per operation**: standard API Platform `security: "is_granted('ROLE_ADMIN')"`
  on the operation (needs symfony/expression-language). Routes under `/api`
  already require `ROLE_USER` (see `config/packages/security.yaml`
  `access_control`).
- **Role-aware UI**: cookies are HttpOnly, so the SPA can't read the JWT.
  `GET /api/me` comes from `nubitio/admin-bundle` (`MeResponseBuilderInterface`);
  the skeleton sets `app_profile: internal` in `config/packages/nubit_admin.yaml`.
  The response is wired into `SmartCrudRolesProvider` via `SessionProvider`.
  Mirror roles as UX only (menu filtering, `defineResource` permission presets).
  Symfony `security:` expressions remain the real gate. Alias
  `MeResponseBuilderInterface` to add domain fields (branch, currency, etc.).
- **Extra JWT claims / login payload**: implement `TokenClaimsProviderInterface`
  and alias it over `DefaultTokenClaimsProvider` in `config/services.yaml`.
- **Extra login cookies** (e.g. Mercure subscriber JWT): implement
  `LoginResponseDecoratorInterface` (autoconfigured).
- **Manual field control on the frontend** (rare): pass `fields: [...]` built
  with `textField()/numberField()/entityField()…` to `defineResource` (builder
  instances are accepted directly — calling `.build()` is optional), plus
  `adapter: RestAdapter` for non-Hydra backends.
- **App-wide currency**: `currency` formatting takes the ISO 4217 code from
  `item.currency` row data when present, else from
  `<CoreConfigProvider currency="USD">` (`frontend/src/App.tsx`). With neither,
  values render as plain fixed-point numbers — the library defaults to no
  country's currency.
- **Theming**: tokens are CSS custom properties from `@nubitio/ui`
  (`--surface-*`, `--text-*`, `--accent-color`, `--font-family-{sans,display}`).
  Style custom pages with tokens, never hardcoded colors — dark mode is free.
  Theme css files live in `frontend/public/themes/` (copied from the package
  by `scripts/copy-themes.mjs` on `pnpm dev/build`).

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
9. Resources with `mercure: true` publish **after** the flush. Since bundle
   0.7 a dead hub no longer 500s the request: the write returns 2xx and the
   failure is logged as a warning ("Mercure publish failed") — live refresh
   degrades to manual. In messenger workers the error is rethrown so async
   `Update` routing keeps retrying. A broken hub shows as 502 on
   `/.well-known/mercure`; if host port 3000 is taken, start the stack with
   `MERCURE_PORT=3001 docker compose up -d`.

## Library source (for deeper digging)

- Frontend packages: https://github.com/nubitio/nubit-react (`@nubitio/*` on npm)
- Backend packages: https://github.com/nubitio/nubit-symfony (`nubitio/*` on Packagist)

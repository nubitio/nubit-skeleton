# Multi-tenant, entitlements, export, dashboards, and platform internals

This skeleton ships with `app_profile: internal` (single tenant, no billing,
no quotas). Read this file when the task pushes past that: turning the app
into a multi-tenant SaaS product (`app_profile: saas`/`hybrid`), adding
exports, building an analytics dashboard, or wiring observability. Everything
here is additive — it doesn't change how plain CRUD resources are built (see
the main `SKILL.md` for that).

## Multi-tenant apps (`nubitio/tenant-bundle`)

```bash
composer require nubitio/tenant-bundle   # auto-registered by Flex
```

- `#[TenantScoped]` on an entity scopes every query to the current tenant
  automatically (via a Doctrine filter, `TenantFilter`).
- `TenantResolverInterface` has multiple stock implementations — pick (or
  compose via `Composite`) based on how tenancy is identified: `Header`,
  `Subdomain`, `JwtClaim`, `User`.
- `TenantDatabaseConnectionSwitcherInterface` — how tenant data is physically
  isolated: `Column` (shared table + tenant_id column, cheapest), `Database`
  (separate DB per tenant), `PostgresSchema` (separate schema per tenant),
  or `Routing` (custom). Pick this once, early — it's not a per-entity choice.
- `#[QuotaResource]` + `FeatureQuotaEnforcer` — caps how many rows of an
  entity a tenant can create under their plan; pairs with the frontend's
  `useQuotaUsage`/`<QuotaUsageBanner>` (see below).
- `bin/console nubit:tenant:list` — enumerate configured tenants.
- `PerTenantCommand` (from `nubitio/platform`) — base class for console
  commands that need to iterate every tenant (e.g. a nightly job).
- **Per-tenant backups** (since nubitio 0.14): `nubit_admin.backup.enabled: true`
  registers a PostgreSQL `TenantBackupRunnerInterface` (`pg_dump
  --format=custom`, credentials read from the Doctrine connection, password
  passed via `PGPASSWORD` so it never lands in `ps aux`) plus
  `bin/console nubit:tenant:backup <tenant> [--type=full] [--dry-run]`.
  Dumps are written through Flysystem, so "local disk vs S3" is just which
  filesystem you point it at:

  ```yaml
  nubit_admin:
      backup:
          enabled: true
          storage:
              filesystem: ~                                   # service id of a FilesystemOperator; overrides local_directory
              local_directory: '%kernel.project_dir%/var/backups'
          pg_dump_binary: pg_dump                              # must be on PATH in the container
          timeout_seconds: 300
  ```

  PostgreSQL only — it throws on any other driver rather than writing a
  partial dump. It keeps no backup history table (the returned `id` is a
  timestamp); add your own entity if you need to query past runs.

Frontend counterpart: `session.profile.tenant` and `session.profile.appProfile`
(`'internal' | 'saas' | 'hybrid'`) come back on `GET /api/me` — branch UI on
these rather than re-deriving tenancy client-side.

## Feature flags, quotas, billing (frontend)

Two related-but-different systems — don't conflate them:

- **Entitlements** (`#[RequiresFeature]` on the backend, `useFeature()` /
  `<FeatureGate>` on the frontend) — "is capability X available to this
  user/tenant". Documented in `references/erp-and-permissions.md`.
- **Feature flags** (`nubitio/platform`'s `FeatureFlagProviderInterface`,
  `StaticFeatureFlagProvider`, `TenantFeatureFlags`) — static or
  tenant-scoped rollout flags, closer to a classic flag system than to
  billing-driven entitlements.

SaaS UI building blocks in `@nubitio/react-admin`:
- `useQuotaUsage()` + `parseQuotaError()` + `<QuotaUsageBanner>` — surfaces
  "12/20 invoices used this month" style banners and turns a 403 quota error
  into a readable message.
- `<PlanPanel>` — a ready-made billing/plan summary panel.

## Export (XLS / PDF)

Two different things, and the difference matters:

**The library (shipped, `nubitio/platform`).** Classes you call from your own
controller/state provider — there is no HTTP endpoint until you write one:

- `XlsExporter` / `XlsWorkbookBuilder` / `XlsColumnResolver` / `XlsTotalsWriter`
  / `XlsValidationSpecResolver` / `XlsResponseFactory` for spreadsheet export
  (column resolution reuses the same `x-crud` metadata the grid already has,
  including totals rows).
- `PdfExporter` for PDF export (needs `pontedilana/php-weasyprint`).
- Requires `phpoffice/phpspreadsheet` and **`ext-zip`** — both are `suggest`,
  not hard requirements, so a missing one surfaces as "class not found" at the
  first export rather than at install.

**The one-line grid export (nubitio 0.14 / @nubitio 0.11).** Turning it on
registers `xlsx` as an API Platform format for
**every** `#[ApiResource]` at once, the same way `json`/`jsonld` are — no
per-resource wiring, no custom endpoint:

```yaml
# config/packages/nubit_admin.yaml
nubit_admin:
    export:
        enabled: true    # requires phpoffice/phpspreadsheet + ext-zip
```

```bash
curl -b /tmp/cj 'http://localhost:8000/api/products?_format=xlsx' -o products.xlsx
# or: -H 'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
```

Frontend: `permissions: { canExport: true }` on `defineResource` puts an
**Export** button in the grid's utility toolbar. It exports every row matching
the grid's **current filters and sort with pagination dropped** — not the page
on screen — and names the file from the response's `Content-Disposition`. The
button only appears when the store implements `export()` (the Hydra adapter
does), so `canExport: true` against a plain REST adapter is silently inert.

> Before @nubitio 0.11, `permissions.canExport` reached the grid as
> `allowExport` and nothing consumed it — no button was rendered. If someone on
> an older version reports a missing export button, that's why; it is not a
> wiring mistake.

## Notifications — email + in-app

Since nubitio 0.14 / @nubitio 0.11. Domain code dispatches one
channel-agnostic message; channels decide how it's delivered.

```yaml
nubit_admin:
    notification:
        enabled: true
        from_address: 'no-reply@example.com'   # built-in email channel
        in_app:
            enabled: true                      # maps a new table — run doctrine:migrations:diff
```

```php
// Anywhere in domain code — a workflow transition listener, a processor…
$this->dispatcher->dispatch(new NotificationMessage(
    recipient: $user->getUserIdentifier(),     // a plain identifier string, not a User FK
    subject: 'Invoice INV-0042 confirmed',
    body: 'The invoice was confirmed and is awaiting payment.',
    channels: ['email', 'in_app'],             // [] means every registered channel
    context: ['html' => $renderedHtml],        // optional; the email channel reads 'html'
));
```

- `NotificationDispatcherInterface` (autowired) goes through **Messenger**, so
  a slow mail server never blocks the request. Route `NotificationMessage` to a
  transport in `messenger.yaml` to make it truly async — it runs sync otherwise.
- Extra channels: implement `NotificationChannelInterface`
  (`getIdentifier()` + `send()`); it's autoconfigured onto the
  `nubit.admin.notification_channel` tag. Slack/SMS/push belong here.
- `email` needs `symfony/mailer`; the channel is skipped entirely when it isn't
  installed, so in-app-only setups don't have to pull one in.
- `in_app.enabled` maps the `nubit_notification` table and exposes
  `GET /api/notifications` (`mercure: true`, `PATCH { "read": true }` to mark
  read). Visibility is enforced by a **Doctrine filter**
  (`nubit_notification_recipient`), not a query parameter — a user cannot ask
  for someone else's rows, and there is no `recipient` filter to bypass.
- Frontend (`@nubitio/admin`): `useNotifications()` returns
  `{ items, unreadCount, loading, markAsRead, refetch }` and stays live over
  Mercure; `<NotificationPanel>` is the ready-made dropdown, designed to be
  returned from an `AdminHeaderAction.renderPanel`. Its labels are props
  (`title`, `emptyTitle`, `markAllReadLabel`) — the `@nubitio/admin` package
  has no i18n of its own, so pass translated strings from the app.

## Reports

`ExportableReportInterface` + `ReportQueryBuilder` + `ReportQuery` +
`GridFilterApplier` (`nubitio/platform`) — build a reusable, filterable
server-side report/query on top of the same grid-filter contract
`DataGridFilter` uses, when a screen needs aggregation beyond what a plain
resource grid can express.

## Dashboards (`@nubitio/dashboard`)

A separate package mirroring `defineResource`'s ergonomics for
metrics/BI screens:

```tsx
import { defineDashboard, DashboardPage } from '@nubitio/dashboard';

const salesDashboard = defineDashboard({
  title: 'Sales overview',
  widgets: [/* built with the package's widget builders */],
});
export const SalesDashboardPage = () => <DashboardPage dashboard={salesDashboard} />;
```

Also available: `DashboardPeriodFilter`, `DashboardLayoutControls`, and the
`useDashboardData` / `useWidgetQuery` hooks for custom widgets. Reach for
this instead of hand-building charts + grids when the ask is "an overview
page with KPIs and charts."

## Privacy, observability, analytics (backend)

These are `nubit_admin.yaml` config toggles plus `nubitio/platform` classes —
mostly invisible until something needs to comply with a data-handling
requirement or you're debugging production behavior:

- **Privacy**: `#[SensitiveData]` marks a property as PII; `DataRedactor` +
  `SensitiveDataPolicyInterface` (default: `DefaultSensitiveDataPolicy`)
  decide how it's redacted in logs/audit/analytics. This is what backs
  `#[AuditMasked]` under the hood conceptually — reach for `#[SensitiveData]`
  when the same field needs redacting *outside* the audit trail too (logs,
  analytics events).
- **Observability**: `nubit_admin.observability.enabled` wires
  privacy-safe Monolog processors (`SensitiveDataProcessor`,
  `TenantLogProcessor`) and OpenTelemetry-style tracing (`DbalTracer`,
  `HttpRequestTracingListener`, `OperationalMetrics`). Turn this on before
  debugging a production performance issue rather than adding ad-hoc logging.
- **Analytics**: `nubit_admin.analytics.enabled` persists typed analytics
  events through a transactional Doctrine outbox (redaction, dedup, retry
  built in). Config: `analytics.redaction_hmac_key`, `delivery_endpoint`,
  `delivery_token`. Commands: `bin/console nubit:analytics:dispatch-outbox`,
  `bin/console nubit:analytics:purge-outbox` — schedule the dispatcher or
  events pile up undelivered.
- **Rate limiting**: `TenantRateLimiter` + `RateLimitPolicy` (`nubitio/platform`)
  for per-tenant API rate limits.
- **Messenger stamps**: if a resource processor dispatches async messages,
  `TenantContextMiddleware`/`TenantStampMiddleware`/`TracingMiddleware` (and
  their `ActorStamp`/`TenantStamp`/`TraceContextStamp`) propagate tenant and
  trace context into the async handler automatically — don't thread tenant
  id through message payloads by hand.

## Escape hatches and dev tooling

- **`@nubitio/eject`** — generates plain, hand-editable field code from the
  backend's API docs. Use this when a resource has outgrown schema-driven
  generation and needs to become a fully custom page — it's the documented
  off-ramp, not a sign something is broken. It's a CLI first, and it is **not**
  installed in this skeleton — add it when you need it:

  ```bash
  cd frontend && corepack pnpm add -D @nubitio/eject
  corepack pnpm exec nubit eject fields /api/products \
      --docs http://localhost:8000/api/docs.jsonld --out src/pages/productFields.ts
  corepack pnpm exec nubit eject page ProductsPage /api/products --out src/pages/ProductsPage.tsx
  ```

  Programmatic equivalents, if you need them: `ejectFieldsFromDocs()`,
  `renderFieldsModule()`, `renderPageModule()`, `fieldToCodeLine()`. Both
  commands print to stdout when `--out` is omitted.
- **DevTools panel**: `createNubitApp({ devTools: true })` (on by default on
  `localhost`) renders `NubitDevToolsPanel`, which inspects the live
  provider tree and shows *why* a field was mapped to a given control —
  check this before assuming a rendering bug when a field looks wrong.
- **Custom field types**: `registerFieldType()` extends the field-type
  registry at runtime for entirely new control kinds beyond the built-ins.

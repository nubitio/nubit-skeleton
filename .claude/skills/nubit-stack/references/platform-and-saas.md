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

`nubitio/platform` ships a full export pipeline — reach for this instead of
hand-rolling a CSV endpoint:

- `XlsExporter` / `XlsWorkbookBuilder` / `XlsColumnResolver` / `XlsTotalsWriter`
  / `XlsValidationSpecResolver` / `XlsResponseFactory` for spreadsheet export
  (column resolution reuses the same `x-crud` metadata the grid already has,
  including totals rows).
- `PdfExporter` for PDF export.
- Frontend: `permissions.canExport` on `defineResource` gates the toolbar
  export button that calls through to these.

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
  backend's API docs (`ejectFromDocs`, `fieldToCode`). Use this when a
  resource has outgrown schema-driven generation and needs to become a fully
  custom page — it's the documented off-ramp, not a sign something is broken.
- **DevTools panel**: `createNubitApp({ devTools: true })` (on by default on
  `localhost`) renders `NubitDevToolsPanel`, which inspects the live
  provider tree and shows *why* a field was mapped to a given control —
  check this before assuming a rendering bug when a field looks wrong.
- **Custom field types**: `registerFieldType()` extends the field-type
  registry at runtime for entirely new control kinds beyond the built-ins.

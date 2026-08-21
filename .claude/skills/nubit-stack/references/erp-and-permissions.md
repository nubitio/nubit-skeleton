# ERP document pattern, module navigation, permissions, customizations

Read this when building a document type with a lifecycle (quote → invoice →
purchase order…), grouping several pages under one sidebar entry, gating UI
by role/permission, or making a customization that isn't plain CRUD (soft
delete, virtual columns, extra JWT claims…).

## ERP document pattern — Sequence + Workflow + Auditable + lines

The reference implementation is `src/Entity/Invoice.php` + `InvoiceLine.php` +
`src/State/InvoiceProcessor.php` + `frontend/src/pages/InvoicesPage.tsx`.
Copy this for every document type (purchase orders, receipts, credit notes…).
For the embedded-lines mechanics referenced below, see
`references/detail-views-and-media.md`.

### Backend — four attributes on the header entity

```php
// Install once: composer require nubitio/workflow-bundle nubitio/sequence-bundle
// Both bundles are auto-registered in config/bundles.php by Flex.

#[Sequence(field: 'number', name: 'invoice', prefix: 'INV-', padding: 4)]
#[Workflow(
    field: 'status',
    transitions: [
        'confirm' => ['from' => ['draft'], 'to' => 'confirmed', 'label' => 'Confirm'],
        'mark_paid' => [
            'from' => ['confirmed'], 'to' => 'paid', 'label' => 'Mark as paid',
            'roles' => ['ROLE_ADMIN'],
            'guard' => InvoicePaymentGuard::class,           // per-transition, not top-level
            'set'   => ['paymentMethod' => 'cash'],           // optional: field assignments applied on success
        ],
        'cancel'   => ['from' => ['draft', 'confirmed'], 'to' => 'cancelled', 'label' => 'Cancel', 'roles' => ['ROLE_ADMIN']],
    ],
)]
#[Auditable(resource: 'invoice')]
#[ApiResource(processor: InvoiceProcessor::class, ...)]
class Invoice { /* lines collection, header fields, recalculateTotals() */ }
```

- `#[Sequence]` fills `$number` on first persist; skip if field already set.
  Register any `scope: ['customer']` paths to scope counters per dimension.
- `#[Workflow]` auto-registers `POST /api/invoices/{id}/transition/{name}`
  (override the inferred collection path with `routePrefix: '...'`).
  `SchemaCrudPage` reads `x-workflow` from `/api/docs.jsonld` and builds row
  action buttons automatically — no frontend code needed.
  `guard` goes **inside a transition entry**, not as a sibling of `field`/
  `transitions` — it names a class implementing `WorkflowGuardInterface`:
  `canTransition(object $entity, string $transitionName): bool` and
  `getBlockReason(): ?string` (shown to the user when blocked), for domain
  rules that go beyond role checks.
- `#[Auditable]` records field-level diffs. Use `#[AuditMasked]` on sensitive
  properties to exclude them.
- Status field: mark `'showInForm' => false` in `x-crud` — the workflow
  engine sets it, the user never edits it directly. (`visibleOnForm` is the
  deprecated spelling; see the hint table in `SKILL.md`.)

### Line entity

```php
#[EmbeddedLines(
    parentProperty: 'invoice',
    route: '/api/invoice_lines',
    normalizationGroups: ['invoice:read'],
)]
#[ApiResource(operations: [new GetCollection(), new Get()], normalizationContext: ['groups' => ['invoice:read']])]
#[ORM\Entity]
class InvoiceLine { /* product, quantity, unitPrice, taxRate, lineTotal + x-crud hints */ }
```

`#[ApiResource]` on the line entity exposes its schema in `/api/docs.jsonld`.
`SchemaCrudPage` infers `formDetail` fields automatically from `x-embedded-lines`
on the parent — no manual `formDetail.fields` in the frontend.

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
// SchemaCrudPage auto-reads x-workflow and builds transition buttons — no rowActions needed.
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
    allowAdding: true,
    allowDeleting: true,
    allowUpdating: true,
    required: true,
    summary: {
      sticky: true,
      items: [{ column: 'lineTotal', summaryType: 'sum', valueFormat: 'currency', label: 'Lines total' }],
    },
  },
});
export function InvoicesPage() { return <SchemaCrudPage resource={invoices} />; }
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
with a subtitle or banner. Extra `FeatureHubLayout` props: `subtitle`,
`banner: { message, tone, icon, visible, showInCompact }` (an inline notice
under the header), `tabsAriaLabel`, and per-tab `visible?: boolean` to hide a
tab without removing its route (e.g. behind a feature flag or role check).

## Role-aware UI via /api/me

The frontend can't read HttpOnly cookies so it can't inspect the JWT.
`GET /api/me` is the single source of truth for UX gating. `useSession()`
returns `{ session, refresh, logout, roles, username }` — `roles` is a plain
`string[]`. There is **no `permissions` map on the session** — don't invent
one. Use `roles` directly in `defineResource` permission callables and menu
`roles` arrays:

```tsx
const { roles } = useSession();
const isAdmin = roles.includes('ROLE_ADMIN');
```

```yaml
# config/services.yaml — extend the /api/me payload with domain fields
Nubit\AdminBundle\Session\MeResponseBuilderInterface:
    alias: App\Session\AppMeResponseBuilder
App\Session\AppMeResponseBuilder:
    arguments:
        $inner: '@Nubit\AdminBundle\Session\DefaultMeResponseBuilder'
```

```php
// src/Session/AppMeResponseBuilder.php — add fields, e.g. branch/currency,
// not ad-hoc "permission keys": Symfony security: expressions remain the
// real gate; keep this response shaped for UX only.
final readonly class AppMeResponseBuilder implements MeResponseBuilderInterface {
    public function __construct(private MeResponseBuilderInterface $inner) {}
    public function build(UserInterface $user): array {
        $response = $this->inner->build($user);
        $response['branch'] = $user->getBranch();
        return $response;
    }
}
```

If what's actually needed is "is capability X available to this
user/tenant" rather than a role check, reach for feature flags instead of
inventing a permissions map — see the next section.

## SSO / OpenID Connect (⏳ unreleased)

Not in nubitio 0.13. One generic integration for **any** compliant IdP (Okta,
Entra ID, Google Workspace, Auth0, Keycloak…) via issuer discovery — there is
no per-provider SDK to install.

```yaml
# config/packages/nubit_admin.yaml
nubit_admin:
    oidc:
        enabled: true
        providers:
            okta:
                issuer: 'https://example.okta.com'          # {issuer}/.well-known/openid-configuration must resolve
                client_id: '%env(OKTA_CLIENT_ID)%'
                client_secret: '%env(OKTA_CLIENT_SECRET)%'
                scopes: ['openid', 'email', 'profile']      # default
                redirect_uri: 'https://api.example.com/api/auth/oidc/okta/callback'
                post_login_redirect_uri: 'https://app.example.com/'
```

Two things the app **must** provide — the bundle deliberately doesn't guess:

```yaml
# config/packages/security.yaml — the authenticator is not added for you
firewalls:
    main:
        custom_authenticators:
            - Nubit\AdminBundle\Auth\Oidc\OidcAuthenticator

# config/services.yaml — provisioning policy is yours (this bundle
# doesn't know your User class, same as TokenClaimsProviderInterface)
Nubit\AdminBundle\Auth\Oidc\OidcUserResolverInterface:
    alias: App\Security\OidcUserResolver
```

`OidcUserResolver::resolve(array $claims, OidcProviderConfig $provider): UserInterface`
decides everything policy-shaped: look up by `sub`/`email`, JIT-provision on
first login, reject unknown users, map IdP groups to roles. Throw
`OidcAuthenticationException` to refuse.

How it behaves, so you can debug it:

- Login starts at `GET /api/auth/oidc/{provider}/redirect` — point the "Sign in
  with…" button there. It is a **top-level browser navigation**, not an XHR.
- Authorization code + PKCE (S256). `state`/`nonce`/verifier ride in an
  HMAC-signed `OIDC_FLOW` cookie (10 min TTL, `SameSite=Lax` — `Strict` would
  drop it on the way back from the IdP and break every login).
- On success the callback issues the **same token pair as password login**, so
  from `/api/me` onward an SSO session is indistinguishable from a normal one.
  Failures redirect to `post_login_redirect_uri` with `?error=oidc_failed`, so
  check the server log for the real reason — the query string never carries it.
- Needs `symfony/http-client` (discovery + JWKS + token exchange) and
  `symfony/cache` (caches both for an hour); both are `suggest`.
- ID tokens are verified against the provider's JWKS by `kid` — the token
  header's `alg` is never trusted — plus `iss`, `aud`, `nonce`, and `azp`
  (a multi-audience token without `azp`, or with someone else's, is rejected).

## Feature flags / entitlements (finer-grained than roles)

For gating that's about a *capability being enabled* rather than *who the
user is* (a paid add-on, a tenant-specific module, a beta feature), use the
feature system instead of stretching roles to mean something they don't:

- Backend: `#[Nubit\Platform\Feature\Attribute\RequiresFeature('invoice.pay')]`
  on a resource/operation, checked via `FeatureCheckerInterface`. The result
  feeds into `DefaultMeResponseBuilder`'s `features` block on `/api/me`
  (`session.profile.features: Record<string, { enabled, config }>`).
- Frontend: `useFeature('invoice.pay'): boolean` and
  `useFeatureConfig('invoice.pay')` hooks, or wrap UI in
  `<FeatureGate featureKey="invoice.pay">…</FeatureGate>` to hide/disable it
  declaratively.
- This is distinct from `nubitio/platform`'s separate `FeatureFlagProviderInterface`
  system (static/tenant-scoped flags for rollout control) — the two overlap
  in purpose but are different APIs; don't mix them up. See
  `references/platform-and-saas.md` for multi-tenant and quota-aware
  variants of this.

## Workflow actions and row locking

- `rowActions: (row) => [...]` adds per-row menu actions (confirm + PATCH
  state transitions work well; grids with `mercure: true` refresh themselves).
- When `#[Workflow]` is on the entity, `SchemaCrudPage` auto-builds row actions
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
  `access_control`), so an operation with no `security:` is not world-open —
  it's reachable by *any authenticated user*, whatever their role. That's a
  fine default for reads and a common accident on writes.
  `bin/console nubit:security:audit` (⏳ unreleased) lists every
  POST/PUT/PATCH/DELETE that never opted in; `--strict` exits non-zero, so it
  works as a CI gate.
- **Role-aware UI**: see "Role-aware UI via /api/me" above — `app_profile`
  (`internal`/`saas`/`hybrid`) lives in `config/packages/nubit_admin.yaml` and
  is echoed on `session.profile.appProfile`.
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

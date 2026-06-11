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
| React admin | `frontend/src/` | `SmartCrudPage` renders grid + forms from `/api/docs.jsonld` |
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
`width` (px), `hidden: true` (exclude from grid, keep in form).

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

In `frontend/src/App.tsx`: add `{ text: 'Customers', icon: 'ph ph-users', path: '/customers' }`
to `menu` and `<Route path="/customers" element={<CustomersPage />} />`.
Icons are Phosphor classes (`ph ph-*`).

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
After changing entities: `docker compose exec app php bin/console cache:clear`.

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
  on the operation. Routes under `/api` already require `ROLE_USER` (see
  `config/packages/security.yaml` `access_control`).
- **Extra JWT claims / login payload**: implement `TokenClaimsProviderInterface`
  and alias it over `DefaultTokenClaimsProvider` in `config/services.yaml`.
- **Extra login cookies** (e.g. Mercure subscriber JWT): implement
  `LoginResponseDecoratorInterface` (autoconfigured).
- **Manual field control on the frontend** (rare): pass `fields: [...]` built
  with `textField()/numberField()/entityField()…` to `defineResource`, plus
  `adapter: RestAdapter` for non-Hydra backends.
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

## Library source (for deeper digging)

- Frontend packages: https://github.com/nubitio/nubit-react (`@nubitio/*` on npm)
- Backend packages: https://github.com/nubitio/nubit-symfony (`nubitio/*` on Packagist)

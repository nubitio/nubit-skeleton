# Nubit Skeleton

Full-stack starter for the Nubit admin stack: **Symfony 7.4 + API Platform 4** backend ([`nubitio/admin-bundle`](https://github.com/nubitio/nubit-symfony)) and a **React** admin ([`@nubitio/react-admin`](https://www.npmjs.com/package/@nubitio/react-admin)) that generates CRUD screens from your API docs.

Annotate an entity → get a datagrid with filters/sorting/search, create/edit forms with validation, auth, and real-time refresh. Build vertical systems (ERP, POS, industry SaaS) in hours.

## Start a new project

Three ways to get a copy (it's a [template repository](https://github.com/nubitio/nubit-skeleton)):

```bash
# a) GitHub CLI — new repo under your account, no shared history
gh repo create my-app --template nubitio/nubit-skeleton --private --clone && cd my-app

# b) Web UI — the "Use this template" button on GitHub

# c) Composer
composer create-project nubitio/nubit-skeleton my-app && cd my-app
```

Then boot it:

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:seed
```

Open **http://localhost:5173** and sign in with `admin@example.com` / `admin1234`.

| Service | URL |
| --- | --- |
| React admin (Vite dev) | http://localhost:5173 |
| API + docs | http://localhost:8000/api |
| Mercure hub | http://localhost:3000/.well-known/mercure |

> Before anything real: change `APP_SECRET` in `.env` (≥ 32 bytes — it signs the auth JWTs) and the database/Mercure passwords in `compose.yaml`.

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
export const CustomersPage = () => <SmartCrudPage resource={customers} />;
```

4. Register the route + menu item in [`frontend/src/App.tsx`](frontend/src/App.tsx). Done — full CRUD.

## Auth

The bundle ships dual web/mobile JWT auth (see the [admin-bundle README](https://github.com/nubitio/nubit-symfony/tree/main/packages/admin-bundle)):

- **Web**: HttpOnly cookies, auto-refresh, same-origin via the Vite proxy. Already wired in `frontend/src/App.tsx`.
- **Mobile/API**: send `"response_mode": "json"` on login, then `Authorization: Bearer <token>`; refresh with `{ "refreshToken": "..." }`.

Refresh tokens rotate and are stored hashed (`nubit_refresh_token` table).

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

## License

MIT

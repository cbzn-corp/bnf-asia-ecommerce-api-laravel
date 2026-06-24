# BNF Asia E-Commerce API

Laravel 12 REST API for the BNF Asia e-commerce platform. Powers the storefront and admin dashboard with the same `/api` routes and JWT contract.

**Standalone repository** — deploy this repo on [Laravel Cloud](https://cloud.laravel.com). The Next.js storefront and admin live in the separate [bnf_asia_ecommerce](https://github.com/sebastiandimla/bnf_asia_ecommerce) monorepo.

## Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12, PHP 8.2+ |
| Database | PostgreSQL (Supabase or Laravel Cloud Postgres) |
| Auth | JWT (`php-open-source-saver/jwt-auth`, 8h TTL) |
| Storage | Supabase Storage (product/CMS images) |
| PDF | DomPDF (order invoices) |
| Hosting | [Laravel Cloud](https://cloud.laravel.com) |

## Local development

### Prerequisites

- PHP 8.2+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`, `sodium` (JWT)
- Composer 2.x
- PostgreSQL 14+ (local or Supabase)

## Database & migrations

**All tables and schema changes live in this app.** There is no separate Prisma or Nest migration path.

| What | Location |
|------|----------|
| Ecommerce schema (users, products, orders, …) | `database/migrations/2026_06_23_000001_create_ecommerce_schema.php` |
| Laravel cache / queue tables | `database/migrations/0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php` |
| Bootstrap data (roles, categories, shipping, admin) | `database/seeders/DatabaseSeeder.php` |
| New schema changes | Add new files under `database/migrations/` — run `php artisan migrate` |

```bash
php artisan migrate          # apply pending migrations
php artisan db:seed          # roles, category tree, shipping rates, admin user (from SEED_ADMIN_*)
php artisan migrate:status   # check state
```

Use Supabase **direct** URL (port 5432) in `DB_URL` when running migrations locally. See [docs/SUPABASE.md](docs/SUPABASE.md).

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

- `DB_URL` — PostgreSQL connection (use Supabase **direct** URL port 5432 for `migrate`)
- `JWT_SECRET` — long random string
- `SUPABASE_*` — for image uploads
- `STOREFRONT_URL` / `ADMIN_URL` — CORS origins

### Database setup (Supabase)

```bash
# Set DB_URL to Supabase direct URL (port 5432) in .env
php artisan migrate
php artisan db:seed
```

For runtime (`php artisan serve` or production), you can switch `DB_URL` to the Supabase pooler (port 6543).

API base URL: **http://localhost:8000/api**

Smoke test:

```text
GET http://localhost:8000/api/settings/platform
```

### Default admin (after seed)

Set `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` in `.env` before `php artisan db:seed`. The seeder does **not** create demo products, promotions, or a Store Manager account — import catalog data via admin CSV import or add products manually.

## Laravel Cloud deployment

### First deploy

1. Install the [Laravel Cloud CLI](https://cloud.laravel.com/docs/api/cli).
2. From this directory: `cloud ship` (creates app, connects Git, deploys).
3. In the Cloud dashboard, add a **Postgres** database (or attach Supabase via `DB_URL`).
4. Set environment variables from `.env.production.example`.
5. Set **Deploy command**: `php artisan migrate --force`
6. Add custom domain (e.g. `api.yourdomain.com`).

### Ongoing deploys

```bash
cloud deploy
```

Or push to the connected branch for automatic deploys.

### Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `JWT_SECRET` set (unique, not the example value)
- [ ] Postgres attached; migrations run on deploy
- [ ] `SUPABASE_*` for image uploads
- [ ] `PAYMONGO_*` / `STRIPE_*` webhook URLs → `https://api.yourdomain.com/api/webhooks/paymongo` and `/stripe`
- [ ] `STOREFRONT_URL`, `ADMIN_URL` for CORS and payment redirects
- [ ] Seed admin once: `php artisan db:seed` with `SEED_ADMIN_*` set
- [ ] Smoke test: `GET /api/settings/platform`, admin login, checkout preview

## API modules

164 routes including:

- Auth, roles, users
- Products, categories, collections, bundles
- Orders, checkout, payments, webhooks
- Promotions, reviews, wishlist, addresses
- Settings, content/CMS, email templates
- Abandoned carts, support chat, analytics
- Audit logs, payment logs

## Scripts

| Command | Description |
|---------|-------------|
| `php artisan serve` | Local dev server (port 8000) |
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Seed roles, category tree, shipping rates, admin user |
| `php artisan route:list --path=api` | List API routes |
| `composer test` | Run PHPUnit tests |

## Notes

- Table/column names use PascalCase tables and camelCase columns (PostgreSQL).
- Error responses: `{ "statusCode", "message", "error" }`.
- JWT payload includes `id`, `email`, `roleId`, `roleKey`, `roleName`, `isStaff`, `permissions`.

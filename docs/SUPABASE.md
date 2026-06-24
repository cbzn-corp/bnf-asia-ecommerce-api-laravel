# Supabase setup (database + image storage)

This API uses **Supabase PostgreSQL** for the database and **Supabase Storage** for product images.

## 1. Create a Supabase project

1. Go to [supabase.com/dashboard](https://supabase.com/dashboard) and create a project.
2. Wait for the database to finish provisioning.

## 2. Database connection

In **Project Settings → Database**, copy the connection strings.

Add to `.env`:

```env
DB_CONNECTION=pgsql

# Direct connection (port 5432) — use for php artisan migrate
DB_URL="postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres"

# Optional: switch to pooler (port 6543) after migrating for php artisan serve / production runtime
# DB_URL="postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres?pgbouncer=true"
```

For **local PostgreSQL**:

```env
DB_URL=postgresql://postgres:postgres@localhost:5432/bnf_asia_ecommerce
```

Run migrations and seed (all schema is defined in `database/migrations/`):

```bash
php artisan migrate
php artisan db:seed
```

Set admin credentials before seeding (creates roles, category tree, shipping rates, and one Super Admin — no demo products):

```powershell
# PowerShell — add to .env or export for one run
$env:SEED_ADMIN_EMAIL="admin@example.com"
$env:SEED_ADMIN_PASSWORD="change-me"
php artisan db:seed
```

## 3. Storage bucket (product images)

1. In the Supabase dashboard, open **Storage**.
2. Create a bucket named **`product-images`**.
3. Enable **Public bucket** (storefront and admin read image URLs without auth).

Uploads are performed by the API using the **service role** key (server-side only).

## 4. API environment variables

In **Project Settings → API**, copy:

| Variable | Where to find it |
|----------|------------------|
| `SUPABASE_URL` | Project URL |
| `SUPABASE_SERVICE_ROLE_KEY` | `service_role` key (secret — never expose to the browser) |

Add to `.env`:

```env
SUPABASE_URL=https://[PROJECT-REF].supabase.co
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
SUPABASE_STORAGE_BUCKET=product-images
```

Verify storage from the API:

```bash
curl http://localhost:8000/api/products/storage-status
# { "configured": true, "bucket": "product-images" }
```

## Security notes

- Never put `SUPABASE_SERVICE_ROLE_KEY` in the admin or storefront `.env` files.
- Only this Laravel API uploads files; staff auth is enforced via JWT + permissions.
- Rotate keys if the service role key is ever exposed.

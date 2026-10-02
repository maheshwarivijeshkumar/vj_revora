# Local Setup

Written for a developer joining the project on Windows + Laragon. Adjust paths
for other environments.

## Prerequisites

|          | Version | Notes                                                                     |
| -------- | ------- | ------------------------------------------------------------------------- |
| PHP      | 8.3+    | With `zip`, `pdo_mysql`, `pdo_pgsql`, `gd`, `intl`, `mbstring`, `openssl` |
| MySQL    | 8.4     | Running on 3306                                                           |
| Redis    | 5+      | Cache and queue                                                           |
| Node     | 22+     |                                                                           |
| Composer | 2       |                                                                           |

### PHP extensions

`zip` (imports/exports) and `pdo_pgsql` (the future PostgreSQL move, ADR-008)
are enabled in `php.ini`. If you are setting up fresh, uncomment:

```ini
extension=zip
extension=pdo_pgsql
extension=pgsql
```

Verify with `php -m | grep -E "zip|pgsql"`.

> There is no `php_redis` extension available here, so the project uses
> **predis** (pure PHP) — `REDIS_CLIENT=predis`. Do not set this to `phpredis`
> unless you have installed that extension.

## First run

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Databases
mysql -uroot -e "CREATE DATABASE vj_lead_revora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -uroot -e "CREATE DATABASE vj_lead_revora_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate
php artisan db:seed

npm run build
```

### Start Redis

Laragon ships Redis but does not run it by default:

```bash
"/d/projects/laragon/bin/redis/redis-x64-5.0.14.1/redis-server.exe" \
  "/d/projects/laragon/bin/redis/redis-x64-5.0.14.1/redis.windows.conf"
```

It must be running — `CACHE_STORE` and `QUEUE_CONNECTION` both point at it.
Install it as a Windows service if you would rather not start it each session.

### Run

```bash
php artisan serve      # http://127.0.0.1:8000
npm run dev            # Vite dev server
php artisan queue:work --queue=webhooks,default # Queue worker (Horizon is deployment-only — ADR-011)
```

Sign in with the seeded demo workspace:

```
owner@demo.test / password
```

That account owns the **Demo Workspace** tenant on the Growth plan, holds all
106 permissions, and will show the full navigation. Local only — the seeder
skips demo data when `APP_ENV=production`.

## Quality gate

Everything below must pass before a merge. CI runs the same set.

```bash
composer ci:check      # npm check + phpstan + pint + pest
```

Or individually:

```bash
./vendor/bin/pint --parallel        # PHP formatting
./vendor/bin/phpstan analyse        # Static analysis, level 7
php artisan test                    # Pest
npm run check                       # Lint + format (--fix to apply)
npm run types:check                 # vue-tsc
```

**Tests run against `vj_lead_revora_test` on MySQL**, not SQLite in memory.
Slower, but the schema uses JSON columns, composite uniques and foreign keys
whose behaviour differs between engines — testing on a different engine than
production is how those differences reach users.

## Public surfaces

| Route               | What it is                                                |
| ------------------- | --------------------------------------------------------- |
| `/`                 | Coming soon, or the landing page — decided by `SITE_MODE` |
| `/home`             | The landing page while `SITE_MODE=coming_soon`            |
| `/coming-soon`      | The coming-soon page while `SITE_MODE=live`               |
| `/branding`         | Live brand picker — preview all eight identities          |
| `/login`            | Sign in                                                   |
| `/dashboard`        | The application (auth required)                           |
| `/site.webmanifest` | PWA manifest, generated from the active brand             |
| `/api/v1/health`    | API liveness                                              |

Flip `SITE_MODE=live` in `.env` when you are ready to launch; both pages stay
routable either way, so marketing copy can be reviewed without exposing it.

### Choosing the final brand

Open **`/branding`** and apply each candidate — it re-themes the entire product
for your session only. Once you have decided:

```bash
# .env
BRAND_KEY=leadforge     # whichever you picked
BRAND_PREVIEW=false     # retires the picker
```

Then `php artisan config:clear`. Favicons, app icons, the PWA manifest, the
`theme-color` meta tag and every primary colour follow automatically — there
are no hard-coded brand references to chase.

> **Not `/brand`.** That path collides with the `public/brand/` asset
> directory, which web servers resolve before the application runs.

## Brand assets

All eight candidate identities are generated into `resources/brand/` and
`public/brand/`. Revora is currently wired in. To regenerate after editing mark
geometry in `scripts/brand/brands.mjs`:

```bash
node scripts/brand/generate.mjs
```

See [`resources/brand/README.md`](../../resources/brand/README.md) for how to
switch the active brand.

## Things worth knowing before you write code

**Tenant isolation is enforced, and it fails closed.** With no tenant bound,
queries on tenant-owned models return _nothing_ — not everything. If a query
mysteriously returns zero rows, check that a tenant is bound before assuming the
data is missing. Cross-tenant work goes through
`TenantContext::withoutScoping()`, and those call sites are security-sensitive.

**Adding a model?** If it is tenant-owned it needs a `tenant_id` column, an
index leading with `tenant_id`, and the `BelongsToTenant` trait. The test in
`tests/Feature/Tenancy/TenantIsolationTest.php` fails the build if you forget —
that is the point. If the model is genuinely a platform-level record, add its
table to the `$central` allowlist in that test.

**Never check plan limits inline.** Ask `Entitlements` — `hasFeature()`,
`withinLimit()`, `remaining()` — or use the `feature:` / `limit:` route
middleware. No plan key, price or limit may be branched on in code (§101.28).

**Never rely on HTML `required`.** Server-side validation is authoritative
(§42, §101.16). Client-side validation is a UX affordance only.

**Adding a navigation item?** Register its Lucide icon in
`resources/js/lib/icons.ts`. The registry is explicit because a namespace
import of `lucide-vue-next` pulls the whole icon set into the bundle (it cost
600kB before this was caught).

**Permissions in the UI are not the security boundary.** `useAuthorization()`
mirrors the server so the interface hides what would be rejected. Policies and
entitlement middleware remain authoritative.

## Known gaps at end of Phase 0

| Gap                                                   | Where it lands                                                   |
| ----------------------------------------------------- | ---------------------------------------------------------------- |
| Registration, email verification, password reset, 2FA | Phase 1.1                                                        |
| Tenant provisioning job (the seeder stands in for it) | Phase 1.2                                                        |
| All CRM modules, DataTable, widget-driven dashboard   | Phase 1                                                          |
| Horizon, Reverb                                       | ADR-011 — deployment / Phase 3                                   |
| PostgreSQL                                            | ADR-008 — before production                                      |
| Tenant-qualified sign-in for multi-workspace users    | ADR-010 — before production                                      |     | Route | What it is |
| ---                                                   | ---                                                              |
| `/`                                                   | Coming soon, or the marketing home page — decided by `SITE_MODE` |
| `/home`                                               | Marketing home while `SITE_MODE=coming_soon`                     |
| `/product`                                            | Five-stage product walkthrough                                   |
| `/solutions`                                          | By audience (sales, marketing, agency, existing CRM)             |
| `/integrations`                                       | Provider tables, and what we refuse to build                     |
| `/pricing`                                            | Plans plus full comparison matrix, read from the database        |
| `/security`                                           | Isolation, credentials, webhooks, audit                          |
| `/developers`                                         | REST API, webhook events, behaviour                              |
| `/about`                                              | Positioning and current status                                   |
| `/contact`                                            | Enquiry form, persisted to `contact_enquiries`                   |
| `/coming-soon`                                        | The coming-soon page while `SITE_MODE=live`                      |
| `/branding`                                           | Live brand picker — preview all eight identities                 |
| `/login` · `/dashboard`                               | The application                                                  |
| `/site.webmanifest`                                   | PWA manifest, generated from the active brand                    |
| `/api/v1/health`                                      | API liveness                                                     |

All marketing pages share `MarketingLayout` and are served by a single
`MarketingController`. Adding a page means adding a method, a route and a Vue
file under `resources/js/pages/marketing/`.

Flip `SITE_MODE=live` in `.env` when you are ready to launch; both pages stay
routable either way, so marketing copy can be reviewed without exposing it.

### Choosing the final brand

Open **`/branding`** and apply each candidate — it re-themes the entire product
for your session only. Once you have decided:

```bash
# .env
BRAND_KEY=leadforge     # whichever you picked
BRAND_PREVIEW=false     # retires the picker
```

Then `php artisan config:clear`. Favicons, app icons, the PWA manifest, the
`theme-color` meta tag and every primary colour follow automatically — there
are no hard-coded brand references to chase.

> **Not `/brand`.** That path collides with the `public/brand/` asset
> directory, which web servers resolve before the application runs.

## Brand assets

All eight candidate identities are generated into `resources/brand/` and
`public/brand/`. Revora is currently wired in. To regenerate after editing mark
geometry in `scripts/brand/brands.mjs`:

```bash
node scripts/brand/generate.mjs
```

See [`resources/brand/README.md`](../../resources/brand/README.md) for how to
switch the active brand.

## Things worth knowing before you write code

**Tenant isolation is enforced, and it fails closed.** With no tenant bound,
queries on tenant-owned models return _nothing_ — not everything. If a query
mysteriously returns zero rows, check that a tenant is bound before assuming the
data is missing. Cross-tenant work goes through
`TenantContext::withoutScoping()`, and those call sites are security-sensitive.

**Adding a model?** If it is tenant-owned it needs a `tenant_id` column, an
index leading with `tenant_id`, and the `BelongsToTenant` trait. The test in
`tests/Feature/Tenancy/TenantIsolationTest.php` fails the build if you forget —
that is the point. If the model is genuinely a platform-level record, add its
table to the `$central` allowlist in that test.

**Never check plan limits inline.** Ask `Entitlements` — `hasFeature()`,
`withinLimit()`, `remaining()` — or use the `feature:` / `limit:` route
middleware. No plan key, price or limit may be branched on in code (§101.28).

**Never rely on HTML `required`.** Server-side validation is authoritative
(§42, §101.16). Client-side validation is a UX affordance only.

**Adding a navigation item?** Register its Lucide icon in
`resources/js/lib/icons.ts`. The registry is explicit because a namespace
import of `lucide-vue-next` pulls the whole icon set into the bundle (it cost
600kB before this was caught).

**Permissions in the UI are not the security boundary.** `useAuthorization()`
mirrors the server so the interface hides what would be rejected. Policies and
entitlement middleware remain authoritative.

## Known gaps at end of Phase 0

| Gap                                                   | Where it lands                 |
| ----------------------------------------------------- | ------------------------------ |
| Registration, email verification, password reset, 2FA | Phase 1.1                      |
| Tenant provisioning job (the seeder stands in for it) | Phase 1.2                      |
| All CRM modules, DataTable, widget-driven dashboard   | Phase 1                        |
| Horizon, Reverb                                       | ADR-011 — deployment / Phase 3 |
| PostgreSQL                                            | ADR-008 — before production    |
| Tenant-qualified sign-in for multi-workspace users    | ADR-010 — before production    |

# Laravel React stack

Initial technical scaffold using PHP 8.5, Laravel, Inertia, React, TypeScript,
Vite, Tailwind CSS, shadcn/ui and PostgreSQL through Eloquent.

Includes the starter authentication and account settings, framework migrations,
dependency lockfiles, Pest, Pint, Larastan and frontend checks. Business modules,
customer roles, catalog, quotes, CRM, AI and prediction are added in later commits.

## Local development

No Docker or Sail. Install PHP 8.5 with PDO PostgreSQL/SQLite and Node.js 24.

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env`, configure PostgreSQL and run `php artisan key:generate`.
3. Prepare the configured schema and run `php artisan migrate` for pending migrations only.
4. Run `composer run dev`.

Database credentials remain in the ignored `.env`. The PostgreSQL example uses
the Session pooler on port 5432 with SSL and a dedicated schema. Authentication
and sessions stay in Laravel. Tests use SQLite in memory; they do not reset a
remote database. This scaffold does not send AI requests.

## Checks

```sh
npm run build
composer ci:check
```

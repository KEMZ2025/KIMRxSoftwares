# KIM Rx

KIM Rx is a multi-tenant pharmacy and retail operations platform built on Laravel 12.

It currently includes:
- retail and wholesale sales
- purchases and stock control
- accounting
- cash drawer and shift/day closing
- audit trail
- import center
- insurance billing and reconciliation
- package presets and subscription workflow
- full platform backup and restore

## Local Setup

From the project root:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
php artisan serve
```

## Useful Commands

```bash
php artisan test
php artisan view:cache
php artisan efris:process --scope=ready --limit=25
php artisan platform:go-live-check --allow-non-production
```

## Production Guidance

Deployment and production hardening instructions are in:

- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- [.env.production.example](.env.production.example)

## Backup Guidance

Platform owner backup and restore is available in:

- `Administration -> Backups`

This first version provides:
- full platform backup
- full platform restore

It does not yet provide tenant-only restore.

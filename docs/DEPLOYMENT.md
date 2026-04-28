# KIM Rx Deployment And Go-Live Guide

## 1. Server Requirements

- PHP 8.2 or newer
- MySQL or MariaDB
- Composer
- Node.js and npm
- Web server such as Nginx or Apache
- Queue worker process manager such as Supervisor, systemd, or Laravel Forge
- Cron support for the Laravel scheduler

## 2. Production Environment File

Start from:

- [.env.production.example](../.env.production.example)

Minimum production values to review carefully:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- `APP_KEY`
- database credentials
- mail credentials
- EFRIS transport and connector credentials when used

## 3. First Deployment

From the server project directory:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan view:cache
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

Then run the readiness check:

```bash
php artisan platform:go-live-check
```

If you are rehearsing locally or on a staging box, you can use:

```bash
php artisan platform:go-live-check --allow-non-production
```

## 4. Background Processes

### Scheduler

The app needs the Laravel scheduler every minute:

```bash
* * * * * cd /path-to-kimrx && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker

If queue connection is database or another async backend, keep a persistent worker running:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Use Supervisor, systemd, Forge, or another process manager so the worker restarts automatically.

## 5. Safe Update Flow

Before every live update:

1. Log in as platform owner
2. Open `Administration -> Backups`
3. Create a full platform backup with a clear note
4. Confirm the backup appears in the catalog

Then deploy:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan view:cache
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan queue:restart
php artisan platform:go-live-check
php artisan platform:post-deploy-smoke-test
```

## 6. Post-Deploy Smoke Check

After deployment, run:

```bash
php artisan platform:post-deploy-smoke-test
```

The command checks these flows from inside Laravel:

- login page
- owner workspace
- backups screen
- client setup screen
- tenant dashboard
- sales screen
- purchases screen
- reports screen
- cash drawer screen when an eligible tenant user exists
- insurance screen when an eligible tenant user exists

If you still want a quick human check after the command passes, test these flows in the browser:

1. Login works
2. Owner workspace opens
3. Sales screen opens
4. Purchases screen opens
5. Reports screen opens
6. Backups screen opens
7. Cash drawer screen opens if enabled
8. Insurance screen opens if enabled
9. Package/client setup opens for the platform owner

## 7. Rollback Path

If a deployment causes major issues:

1. Open `Administration -> Backups`
2. Review the backup created before the deployment
3. Type the exact filename on the restore screen
4. Leave safety backup enabled
5. Restore the full platform
6. Refresh and log in again if needed

## 8. Notes About The Readiness Command

`platform:go-live-check` validates items the app can inspect directly, including:

- app environment and debug mode
- app key and app URL
- database driver
- queue, cache, and session prerequisites
- public storage access
- writable storage paths
- platform owner access
- backup availability

It also reminds you about items Laravel cannot prove from inside the app, such as:

- cron scheduler registration
- persistent queue worker processes

## 9. Recommended Backup Policy

- Create a backup before every deployment
- Create a backup before major imports
- Create a backup before risky package or settings changes
- Keep regular off-server copies of important archives when possible

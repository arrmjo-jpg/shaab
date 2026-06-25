# AlphaCMS — Production Deployment Checklist

> Generated from the Wave 1–3 hardening program. Execute **in order**.
> Current dev host is Windows/Laragon — production target is a Linux server (not yet provisioned).

## 0. Pre-flight (codebase — already done in Waves 1–3)

- [x] Privilege-escalation / super_admin hard lock (Wave 1)
- [x] Security headers middleware on `api` group (Wave 1)
- [x] SVG/upload hardening + CORS env-driven + Sanctum expiry + endpoint throttling (Wave 1)
- [x] Transactions on RBAC/media writes; async mail; `route:cache`-safe routes; health checks; scheduler overlap/onOneServer (Wave 2)
- [x] Backup offsite/encryption/alerts env-driven; queue job timeouts; ops artifacts (Wave 3)

## 1. Environment (`.env` on the server)

Set, at minimum (see `.env.example` "تقوية الإنتاج" block):

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- [ ] `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` (shared Redis — required for `onOneServer()`/`withoutOverlapping()` and queued mail)
- [ ] `REDIS_HOST/PORT/PASSWORD` (auth enabled, bound private)
- [ ] `SESSION_DRIVER=redis` or `database`; `SESSION_SECURE_COOKIE=true`
- [ ] `CORS_ALLOWED_ORIGINS=https://admin.example.com` (no `*`)
- [ ] `SANCTUM_TOKEN_EXPIRATION=10080`, `MAIL_TIMEOUT=10`
- [ ] `SANCTUM_STATEFUL_DOMAINS` = production SPA host (remove dev hosts)
- [ ] Mail: real SMTP creds + `MAIL_FROM_ADDRESS`
- [ ] `BACKUP_DESTINATION_DISKS=s3` (or `local,s3`) + S3/R2 `AWS_*` creds
- [ ] `BACKUP_ARCHIVE_PASSWORD=` (strong, from secret manager) + `BACKUP_VERIFY=true`
- [ ] `BACKUP_NOTIFICATION_EMAIL=`, `HEALTH_NOTIFICATION_EMAIL=` (real ops inbox)
- [ ] No duplicate keys in `.env` (audit lesson)

## 2. Build & migrate

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # includes create_health_tables
php artisan config:cache
php artisan route:cache              # now succeeds (web.php closure removed)
php artisan event:cache
php artisan view:cache
# frontend:
cd admin-frontend && npm ci && npm run build   # tsc -b + vite build
```

## 3. Mandatory runtime infrastructure

- [ ] **Web**: nginx + php-fpm (tuned `pm`, `upload_max_filesize`≈8–16M, `expose_php=Off`, `display_errors=Off`, opcache `validate_timestamps=0`)
- [ ] **Queue worker (MANDATORY — queued auth mail depends on it)**: install `deployment/queue-worker.service` (or `supervisor-queue-worker.conf`)
- [ ] **Scheduler**: install `deployment/scheduler.cron` (single `schedule:run` line)
- [ ] **Redis**: shared instance, `requirepass`, cache policy `allkeys-lru`, bound localhost/private
- [ ] **MySQL**: tuned `innodb_buffer_pool_size`, slow log on, bound private
- [ ] TLS + HTTP/2 + HSTS at edge; Cloudflare proxy + WAF; cache rules exclude `/api/*`
- [ ] SPF/DKIM/DMARC on sending domain; send a verified test email

## 4. Backup & DR

- [ ] First `php artisan backup:run` produces an **encrypted** archive on the **offsite** disk
- [ ] Confirm `backup:monitor` alert reaches `BACKUP_NOTIFICATION_EMAIL`
- [ ] Perform a **restore drill** (download → decrypt → `mysql` import → app boot) and document RTO
- [ ] `backup:clean` retention verified (see `config/backup.php` default_strategy)

## 5. Monitoring

- [ ] `php artisan health:check` green; scheduled every 15 min (in `routes/console.php`)
- [ ] Protected `GET /api/v1/admin/system/health` returns results (admin auth + `permission:scheduler.view`)
- [ ] External uptime monitor on `/up`
- [ ] Alerts: disk / CPU / RAM / failed-jobs / backup-freshness / TLS-expiry

## 6. Post-deploy verification

```bash
php artisan about
php artisan queue:work --once        # smoke a job
php artisan schedule:list            # 8 tasks + health visible
tail -f storage/logs/laravel.log     # daily channel, no debug
```

## Known follow-ups (backlog, not blocking)

- Dedicated `system.health` permission (currently reuses `scheduler.view`)
- Raster re-encode + deep `finfo` (P1-5); manual scheduler-run → queue (P5-6)
- Dead-dep removal (Scout/Meilisearch, owen-it, medialibrary); frontend code-split

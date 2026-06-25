# Performance Architecture

AlphaCMS is a high-performance news/media platform. Performance > architectural purity.

## Cache

- Backend: **Redis** (`CACHE_STORE=redis`, `phpredis`).
- Global prefix: `CACHE_PREFIX` (default `alphacms`) — applied automatically.
- Keys: ONLY via `App\Support\Cache\CacheKeys`. See `.ai/cache-keys.md`.
- TTL: ONLY via `App\Support\Cache\CacheTtl`. No raw integers.
- Access: `Cache` facade directly. **No `CacheService` wrapper** (rejected — premature abstraction).

### What to cache

| Data | Key | TTL |
|------|-----|-----|
| System settings | `settings:*` | `SETTINGS` (24h) |
| Roles list | `roles:list` | `METADATA` (12h) |
| Permissions grouped | `permissions:grouped` | `METADATA` |
| Permission groups | `permissions:groups` | `METADATA` |
| Public list endpoints | `<resource>:list:*` | `LISTS` (5m) |

Public GET endpoints must be cache-friendly and deterministic.

### Invalidation rules

- Explicit `Cache::forget(CacheKeys::...)` after writes that affect a cached read.
- **No namespace flushing** — not standardized across drivers; explicit keys only.
- **No observers/listeners/invalidation automation** — explicit `forget()`
  in the module's Action after the write. Spatie already flushes its own
  permission cache; do not duplicate.

### Invalidation rules (explicit — apply in the module Action)

| Trigger | Action |
|---------|--------|
| settings updated | `Cache::forget(CacheKeys::settings($group))` |
| role updated | `Cache::forget(CacheKeys::rolesList())` + `CacheKeys::role($id)` |
| permission sync | `Cache::forget(CacheKeys::permissionsGrouped())` + `permissionGroups()` |
| user role changed | Spatie permission map auto-flushed; forget any derived user cache |
| article published | future module — `<articles>:*` keys (not yet built) |

Spatie permission flush (role/permission writes) is automatic — never re-implement it.

## Permission cache

- `config/permission.php`: 24h, auto-flushed on role/permission updates.
- Seeders already call `PermissionRegistrar::forgetCachedPermissions()`.
- Do NOT add observers now — no models warrant it yet.

## Settings cache (spatie/laravel-settings)

- Settings classes (built later) cache under `settings:<group>` via `CacheKeys::settings()`.
- TTL `CacheTtl::SETTINGS`. Forget on settings update.

## Queue

- Connection: **Redis** (`QUEUE_CONNECTION=redis`).
- Logical queues (route via `->onQueue()`): `default`, `notifications`,
  `mail`, `media`, `search`, `sitemap`, `ai`, `analytics`.
- Convention documented in `config/queue.php`.

### Async boundaries (MUST be queued — never sync in request)

- image conversion / optimization (`media`)
- search indexing — Scout/Meilisearch (`search`)
- push notifications — Firebase (`notifications`)
- mail incl. password reset (`mail`)
- sitemap generation (`sitemap`)
- AI content generation (`ai`)
- analytics events (`analytics`)

## CDN / Frontend config

| Concern | Config | Env |
|---------|--------|-----|
| Public SPA | `config/frontend.php` `public_url` | `FRONTEND_URL` |
| Admin SPA | `config/frontend.php` `admin_url` | `ADMIN_FRONTEND_URL` |
| Static assets | `config/cdn.php` `url` | `CDN_URL` |
| Media (R2) | `config/cdn.php` `media_url` | `MEDIA_URL` |

Password reset URL: role-based (admin → admin SPA, else public SPA),
configured in `AppServiceProvider::configurePasswordReset()`. **Done — do not re-touch.**

## HTTP / query standards

- Always eager load relations (`with()`); zero N+1.
- Always paginate list endpoints. Defaults from `config/performance.php`
  (`default_per_page` 15, `max_per_page` 100).
- Offset pagination for admin tables. **Cursor pagination** for large
  public infinite-scroll feeds (articles) when those modules are built.
- Filter/sort via `spatie/laravel-query-builder` with explicit allow-lists.
- Index any column that is filterable or sortable.

## Anti-patterns (rejected in this project)

- ❌ Service-layer wrappers over Cache/built-ins (`CacheService`).
- ❌ Cache namespace-flush hacks.
- ❌ Raw TTL integers / hardcoded key strings.
- ❌ Custom domain exceptions for expected outcomes (return `ApiResponse`).
- ❌ Premature performance test suites for foundation layers.

---
Fakhri Al-Najjar — arrmjo@gmail.com

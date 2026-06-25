# Cache Keys Convention

MANDATORY: All cache keys go through `App\Support\Cache\CacheKeys`. Never hardcode key strings.

## Format

Logical key: `resource:scope:identifier`

`CacheKeys` returns the LOGICAL key only (no app prefix).
The `alphacms` prefix is applied automatically by the cache layer
(`config/cache.php` → `CACHE_PREFIX`, default `alphacms`).

```
CacheKeys::settings('general')      => "settings:general"
  stored in Redis as               => "alphacms:settings:general"
```

Single source of truth for the prefix = `CACHE_PREFIX`. Do NOT re-prefix in code.

## Available builders

| Call | Logical key |
|------|-------------|
| `CacheKeys::settings('general')`   | `settings:general` |
| `CacheKeys::rolesList()`           | `roles:list` |
| `CacheKeys::role($id)`             | `roles:id:{id}` |
| `CacheKeys::permissionsGrouped()`  | `permissions:grouped` |
| `CacheKeys::permissionGroups()`    | `permissions:groups` |
| `CacheKeys::usersList($page)`      | `users:list:page:{page}` |
| `CacheKeys::make('a','b','c')`     | `a:b:c` (generic — for new modules) |

New modules: add a named static builder, do not inline `make()` at call sites.

## TTL

Use `App\Support\Cache\CacheTtl` constants — never raw integers.

| Constant | Seconds | Use for |
|----------|---------|---------|
| `SHORT` / `LISTS`   | 300   | volatile lists (users, articles) |
| `MEDIUM`            | 1800  | medium-churn data |
| `LONG` / `METADATA` | 21600 | roles, permissions, categories |
| `SETTINGS`          | 86400 | system settings |

Values mirror `config/performance.php` → `cache.*` (env-tunable source).

## Usage

Direct `Cache` facade. No service wrapper.

```php
Cache::remember(CacheKeys::rolesList(), CacheTtl::METADATA, fn () => Role::all());
Cache::forget(CacheKeys::rolesList()); // explicit invalidation
```

## Invalidation

Explicit `Cache::forget(CacheKeys::...)` for affected keys.
No namespace-flush. Spatie permission cache is auto-flushed by the package
on role/permission writes — do not duplicate that.

---
name: model-audit
description: >
  AlphaCMS audit/activity convention. MUST be applied to EVERY Eloquent model
  and to every non-Eloquent state change (Spatie Settings). Use whenever a new
  model is created, an existing model changes, a new admin Action mutates state,
  or auditing/activity/history behaviour is touched. Single source of truth:
  Spatie Activitylog (owen-it/laravel-auditing is deprecated and unused).
---

# Model Audit Convention (AlphaCMS)

## Decision (authoritative)
- **One system only: `spatie/laravel-activitylog`.** All change history is read
  from the `activity_log` table.
- `owen-it/laravel-auditing` is **deprecated / not used**. Do NOT add `Auditable`,
  do NOT create an `audits` migration, do NOT read `audits`. (Removing the
  dependency is a separate approved cleanup; until then it stays unused.)
- Installed Activitylog API note: trait is
  `Spatie\Activitylog\Models\Concerns\LogsActivity`, options class is
  `Spatie\Activitylog\Support\LogOptions`, and the "skip empty" method is
  **`dontLogEmptyChanges()`** (NOT `dontSubmitEmptyLogs`). Always go through the
  shared trait below — never wire LogsActivity directly per model.

## Rule 1 — Every Eloquent model is audited
Every model under `app/Models` MUST:

```php
use App\Support\Audit\AuditsChanges;

class Foo extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'foo';            // snake, lowercase
    /** @var array<int,string> NO secrets ever */
    protected array $auditAttributes = ['name', 'status', ...];
}
```

- `$auditAttributes` is an **explicit whitelist**. Never `['*']`.
- **Never list secrets**: password, remember_token, *_token, *secret*, *api_key*,
  encrypted Settings fields. (`AuditsChanges` also force-excludes
  `password`/`remember_token` as a safety belt.)
- Models extending Spatie (`Role`, `Permission`) still use the trait — no exceptions.
- New domain models (News, Articles, Ads, Comments, …) MUST add this on creation.

`AuditsChanges` (`app/Support/Audit/AuditsChanges.php`) centralises:
`useLogName` · `logOnly($auditAttributes)` · `logExcept` global secrets ·
`logOnlyDirty()` · `dontLogEmptyChanges()` · localized description
(`lang/{ar,en}/audit.php` → `audit.event.{created,updated,deleted,restored}`).

## Rule 2 — Never bypass Eloquent events for audited data
Audited writes MUST go through Eloquent (`create/update/save/delete/restore`).
**Forbidden** for audited models:
`DB::table()->update()`, `Model::query()->update()`, `saveQuietly()`,
`updateQuietly()`, `withoutEvents()`, raw SQL writes. These skip the activity log.
(`forceFill()->save()` is allowed — it still fires events.)

## Rule 3 — Non-Eloquent state (Spatie Settings) is logged manually
Spatie Settings are not Eloquent → not auto-audited. Any `Update*SettingsAction`
MUST call:

```php
use App\Support\Audit\SettingsAudit;
SettingsAudit::log('general', array_keys($validated), $secrets);
```

Logs **key names only** (never values) under log_name `settings`, event `updated`.

## Rule 4 — Auth/security events
Use `App\Support\Auth\AuthActivity::log($event, $user)` for auth/account events
(login, profile update, password change) and `PasswordResetAudit` for resets.
Context is whitelist-sanitised (source/ip/user_agent/timestamp).

## Rule 5 — Reading / exposing activity
- Admin global page: `GET /admin/activity` (permission `activity.view`) →
  `ListActivityLogAction` + `AdminActivityResource` (sanitises any
  `password|secret|token|api_key` key to `••••••`).
- Self page: `GET /admin/profile/activity` → `ProfileActivityResource`
  (strict whitelist: source, ip, user_agent, requested_email, timestamp).
- Resources MUST sanitise. Never return raw `properties`.

## Checklist when adding/altering a model
- [ ] `use AuditsChanges;` + `$auditLogName` + `$auditAttributes` (no secrets)
- [ ] No event-bypassing writes for it anywhere in Actions
- [ ] If it has secret columns, they are excluded from `$auditAttributes`
- [ ] If it is exposed in an activity resource, sensitive keys are masked
- [ ] Feature test asserts the change appears in `activity_log`
- [ ] (Settings only) `SettingsAudit::log()` added to its Update Action

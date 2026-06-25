# AlphaCMS Advertising Subsystem — Architecture Reference

> Canonical architectural reference for the AlphaCMS Advertising subsystem.
> **Source of truth for future agents.** Reflects the *actual* implemented code
> (Batches 1–6 + audit remediations V1–V8), not aspirations.
>
> Last reviewed: 2026-05-26. When in doubt, the code wins — update this file when
> the code changes. Items that could not be verified from code are marked `UNKNOWN`.

---

## 0. Source map (canonical locations)

| Layer | Path |
|---|---|
| Config | `config/advertising.php` |
| Enums | `app/Enums/Ad{CampaignStatus,CreativeType,DeviceClass,SelectorStrategy,EventType,PlacementType,PacingMode}.php` |
| Models | `app/Models/Ad{Zone,Campaign,Creative,Placement,Counter,StatDaily}.php` |
| Serving | `app/Support/Advertising/{AdServer,AdBucket,AdSelectorFactory}.php`, `app/Support/Advertising/Selectors/*` |
| Lifecycle | `app/Support/Advertising/AdCampaignLifecycle.php` |
| Tracking | `app/Support/Advertising/{AdTracker,AdBeaconToken,AdEventBuffer,AdStatsRollup,AdClientIp}.php` |
| Security | `app/Support/Advertising/{AdHtmlSanitizer,AdUrlSafety}.php` |
| Cache | `app/Support/Cache/AdCacheTags.php`, `App\Support\Cache\CacheKeys::adZonePool()` |
| Invalidation | `app/Support/Advertising/AdServingInvalidator.php` |
| Public Actions | `app/Actions/Public/Advertising/{ServeAdAction,TrackAdImpressionAction,TrackAdClickAction,RedirectAdClickAction}.php` |
| Admin Actions | `app/Actions/Admin/Advertising/*` (CRUD, `ChangeAdCampaignStatusAction`, `TickAdCampaignsAction`, `AdAnalyticsAction`) |
| Public controller | `app/Http/Controllers/Api/V1/Public/Advertising/AdServeController.php` |
| Admin controllers | `app/Http/Controllers/Api/V1/Admin/Advertising/*` |
| Public routes | `routes/api/v1/public.php` (`ads` prefix) |
| Admin routes | `routes/api/v1/admin.php` (`campaigns`, `ad-creatives`, `ad-placements`, `ad-zones`, `ads/analytics`) |
| Scheduler | `routes/console.php` + `App\Support\Scheduler\SchedulerRegistry` (`ads_flush_events`, `ads_campaigns_tick`) |
| TrustProxies | `bootstrap/app.php` (env-driven `TRUSTED_PROXIES`) |
| Public view | `resources/views/components/ad-slot.blade.php` |
| Public JS | `resources/js/ads.js` → `resources/js/ads/slot.js` (uses `resources/js/broadcast/api.js`) |
| Admin SPA | `admin-frontend/src/features/advertising/*`, `admin-frontend/src/services/ad{Zones,Campaigns,Creatives,Placements,Analytics}.service.ts`, `admin-frontend/src/types/advertising.types.ts` |
| Tests | `tests/Feature/Advertising/*`, `tests/Unit/Advertising/*` |
| Migrations | `database/migrations/2026_06_01_1000xx_*` (zones, campaigns, creatives, placements, tracking, device_targets) |

---

## 1. Product vision

AlphaCMS Advertising is a **native, first-party** ad serving / campaign management /
analytics subsystem for the publisher's own inventory. It provides:

- **Ad inventory management** — stable, key-addressed display slots (zones).
- **Campaign management** — scheduling, priority, weight, lifecycle.
- **Creative management** — image and HTML creatives (video schema-ready, disabled).
- **Placement control** — creative↔zone assignment with compatibility + device targeting.
- **Ad analytics** — impressions, clicks, CTR, daily trends, channel breakdown, top entities.
- **CDN-aware scalable serving** — server-side deterministic selection cached at the edge.

**This is NOT third-party ad network infrastructure.** There is no header bidding, no
external ad exchange, no SafeFrame vendor integration, no GAM. It is publisher-owned
advertising: the publisher defines zones, runs its own/house/direct-sold campaigns, and
measures them. Third-party networks are a future roadmap item (§15), not current scope.

---

## 2. Domain model

Six Eloquent entities. All except the two analytics tables are audited via
`App\Support\Audit\AuditsChanges` (per the model-audit convention).

### AdZone — `app/Models/AdZone.php` · table `ad_zones`
A stable display slot addressed by a programmatic `key`, consumed by the frontend via
`GET /api/v1/ads/serve/{key}`.

- **Columns**: `key` (unique, ≤64), `name`, `description`, `placement_type`
  (`AdPlacementType`, default `banner`, indexed), `selector_strategy`
  (`AdSelectorStrategy`, default `weighted`), `width`/`height` (nullable uint), `locale`
  (nullable; `null` = all locales, else `ar`/`en`), `is_active` (default true, indexed),
  `sort_order`. Composite index `(is_active, placement_type)`.
- **Soft delete**: **NONE.** Zones are config entities. Deactivate via `is_active`; hard
  delete is guarded by `restrictOnDelete` on `ad_placements` (a zone with placements
  cannot be deleted — detach first).
- **Active/inactive**: `scopeActive` (`is_active = true`). `scopeForLocale($locale)`
  matches `locale = null OR locale = $locale`.
- **Audit log**: `ad_zone`. `LOCALES = ['ar','en']`.

### AdCampaign — `app/Models/AdCampaign.php` · table `ad_campaigns`
Scheduling / priority / weight container. Owns creatives.

- **Columns**: `uuid` (auto on create), `name`, `advertiser_name`, `status`
  (`AdCampaignStatus`), `priority`, `weight`, `starts_at`, `ends_at`, `budget_total`,
  `budget_spent`, `pacing_mode` (`AdPacingMode`), `targeting` (array cast), `created_by`,
  `updated_by`.
- **Soft delete**: **YES** (`SoftDeletes`).
- **Scopes / state**: `scopeActive` (`status = active`), `scopeInFlight` (`starts_at`
  null-or-past **and** `ends_at` null-or-future), `scopeServable` (`active` + `inFlight`).
  Instance `isServable(): bool` (status `Active` + within window) — added by remediation
  **V3** and used for serve-time re-validation.
- **Future-ready (no engine yet)**: `budget_total`/`budget_spent`, `pacing_mode`,
  `targeting`. Stored so the engine can be added later without a migration.
- **Audit log**: `ad_campaign`.

### AdCreative — `app/Models/AdCreative.php` · table `ad_creatives`
The rendered unit. Belongs to a campaign.

- **Columns**: `uuid`, `ad_campaign_id`, `type` (`AdCreativeType`), `title`, `alt_text`,
  `landing_url`, `html_code`, `media_asset_id`, `weight`, `is_active`.
- **Types**: `image` (via central `media_assets`), `html` (sanitized markup), `video`
  (**schema-ready, disabled** — rejected at validation as "not enabled", excluded from
  serving via `AdCreativeType::isServableNow()`).
- **Soft delete**: **YES** (`SoftDeletes`). Force-delete cascades to placements.
- **`html_code` sanitization**: enforced at the **model boundary** via an Eloquent
  `Attribute` mutator (remediation **V8**) that runs `AdHtmlSanitizer::sanitize()` on
  every assignment (`null` passes through to preserve the image-path null). This covers
  all write paths (Actions, factories, seeders, imports) — see §7.
- **Audit log**: `ad_creative` (NB: `html_code` is **excluded** from audited attributes —
  size/sanitization; only the transition is audited).

### AdPlacement — `app/Models/AdPlacement.php` · table `ad_placements`
Creative↔zone assignment — the **servable candidate**.

- **Columns**: `ad_creative_id` (`cascadeOnDelete`), `ad_zone_id` (`restrictOnDelete`),
  `weight` (nullable → inherits creative weight), `is_active`, `device_targets` (array;
  added by `..._add_device_targets_to_ad_placements`). Unique `(ad_creative_id,
  ad_zone_id)`. Serving index `(ad_zone_id, is_active, weight)`.
- **Soft delete**: **NONE.** A placement is a link; "detach" is a hard delete.
- **Behavior**: `effectiveWeight()` = placement weight ?? creative weight ?? 1.
  `eligibleForDevice($device)` = empty/null targets → all devices; else device ∈ targets.
- **Audit log**: `ad_placement`.

### AdCounter — `app/Models/AdCounter.php` · table `ad_counters`
Hot live per-placement totals (`impressions`, `clicks`). Unique `ad_placement_id`. **No
FK, not audited** (mirrors `EngagementCounter`). Fed by `AdEventBuffer::flush()`.

### AdStatDaily — `app/Models/AdStatDaily.php` · table `ad_stats_daily`
Daily per-placement rollup + denormalized reporting dimensions (`ad_zone_id`,
`ad_creative_id`, `ad_campaign_id`) that survive entity deletion for historical reporting.
Channel columns `impressions_{direct,internal,search,social,referral}`. Unique
`(ad_placement_id, day)`; indexes on `(ad_campaign_id, day)`, `(ad_creative_id, day)`,
`(ad_zone_id, day)`. **No FK, not audited.** `day` cast `date:Y-m-d`. Fed by
`AdStatsRollup`.

### Enums
- `AdCampaignStatus`: `draft, scheduled, active, paused, completed, archived`;
  `isServable()` = `Active`.
- `AdCreativeType`: `image, html, video`; `isServableNow()` = not `video`.
- `AdDeviceClass`: `desktop, mobile, tablet`; `default()` = `desktop`; `fromString()`
  lenient (unknown → default).
- `AdSelectorStrategy`: `weighted, round_robin, even`.
- `AdEventType`: `impression, click`.
- `AdPlacementType`: `banner, inline, sidebar, interstitial, overlay, preroll`.
- `AdPacingMode`: `none, even, asap` (future-ready, no engine).

---

## 3. Campaign lifecycle

`App\Support\Advertising\AdCampaignLifecycle` is the **single source of truth** for
transitions (no hidden implicit behavior).

### States
`draft · scheduled · active · paused · completed · archived`

### Manual transition matrix (`manualTargets`)
| From | Allowed → |
|---|---|
| `draft` | `scheduled`, `archived` |
| `scheduled` | `active`, `draft`, `archived` |
| `active` | `paused`, `completed`, `archived` |
| `paused` | `active`, `completed`, `archived` |
| `completed` | `paused`, `archived` |
| `archived` | `draft`, `paused` |

- **Window guard**: a manual `→ active` is allowed only if the window is not expired
  (`canActivateNow`: `ends_at` null or `now ≤ ends_at`).
- **Publish completeness guard** (V12): a manual `→ scheduled` or `→ active` is allowed only when
  `AdCampaign::publishValidation()` returns an `ok` result — the **single source of truth**. It
  returns a `CampaignPublishValidation` **Result Object** (`App\Support\Advertising`) with
  `ok` · `reason` (stable code) · `messageKey` (i18n) · `details` (optional, e.g. `{zone_id}`);
  `isPublishable()` is derived (`->ok`). Checks run **cheapest-first** and short-circuit on the
  first failure:
  1. `starts_at` set → `missing_start_date` / `ads.campaign.no_start_date`
  2. `ends_at ≥ starts_at` (if set) → `invalid_dates` / `ads.campaign.bad_dates`
  3. ≥1 active creative → `no_creative` / `ads.campaign.no_creative`
  4. creative renderable (image+`media_asset` or HTML+`html_code`; video unsupported) →
     `creative_not_renderable` / `ads.campaign.creative_not_renderable`
  5. active placement → `no_active_placement` / `ads.campaign.no_placement`
  6. placement on active zone → `zone_inactive` / `ads.campaign.zone_inactive` (+`details.zone_id`)

  On block: `ApiResponse::error(messageKey, details, 422)`; the campaign stays `draft`. Creation is
  **always `draft`** (status not accepted by the store request), so publishing flows **only**
  through this guard. **Rule:** any screen/API/button/CLI/automation that needs "can this publish?"
  calls `publishValidation()` and reads `ok`/`reason`/`messageKey`/`details` — never re-implement
  publish conditions elsewhere, and never depend on `isPublishable()` for logic (it is a
  convenience wrapper over `->ok` with no independent logic and currently **no consumers**). New
  rules are added in this one method; **reserved future `reason` codes**: `budget_exhausted`
  (over `budget_total`), `advertiser_disabled`.
- **Invalid transitions** (same-state or not in the matrix, or activating past the window)
  return an `ApiResponse` error (`422`, keys `ads.campaign.invalid_transition` /
  `ads.campaign.window_expired`) — no exceptions (AlphaCMS policy).

### Automatic transitions (scheduler `ads:campaigns-tick`, every minute, `critical`)
`AdCampaignLifecycle::autoTransitionFor`:
- `scheduled → completed` if `now > ends_at` (missed window).
- `scheduled → active` if `now ≥ starts_at` and `now ≤ ends_at`.
- `active → completed` if `now > ends_at`.
- `paused`, `completed`, `archived`, `draft` are **never** auto-managed (admin-only).

These transitions keep the admin-facing **label** in sync and refresh pools promptly; serving
itself is **date-driven** and does not depend on them (see Serving semantics).

### Admin override & invalidation
- Manual changes go through `ChangeAdCampaignStatusAction` (enforces the matrix + window
  guard, sets `updated_by`, DB transaction). Route gated by `permission:ads.publish`.
- Both manual (`ChangeAdCampaignStatusAction`) and automatic (`TickAdCampaignsAction`)
  transitions call `AdServingInvalidator::forCampaign($campaign)` to flush affected
  serving pools immediately.

### Serving semantics (V10 — date-driven)
**Dates are the source of truth.** Servable = `status ∈ {scheduled, active}` **and** within
`[starts_at, ends_at]`. Status only *withholds* (`draft`/`paused`/`completed`/`archived` never
serve); it does **not** gate the *start* of serving — the date window does. The servable set is
the single source `AdCampaignStatus::servable()` (mirrored by `scopeServable` at pool build and
`isServable()` re-validated at serve time, V3, so an out-of-window campaign cannot keep serving
from a cached pool).

**Independence from the scheduler:** a `scheduled` campaign within its window serves
immediately — it does **not** wait for `ads:campaigns-tick` to promote it to `active`. The
scheduler keeps the admin label consistent and refreshes pools promptly; if delayed/down,
serving continues from the dates (cached pools re-evaluate the date-driven set on TTL expiry).

**Creation default:** new campaigns are created **`draft`** (safe — not served) by
`CreateAdCampaignAction`. An explicit **Save & Publish** sends `status=scheduled` (the store
request restricts the creatable status to `draft`|`scheduled`); from there serving is fully
date-driven. Publishing an existing draft uses the normal `draft → scheduled` lifecycle
transition. This keeps accidental "Create" from publishing an unfinished campaign while removing
the manual activation step once published.

---

## 4. Serving architecture

Server-side **deterministic** selection, edge-cacheable per time bucket.

### Endpoint
`GET /api/v1/ads/serve/{zoneKey}` — `zoneKey` constrained to `[a-z0-9_]+`; middleware
`public.cache` + `throttle:ads.serve`. Locale/device are **query parameters**
(`?locale=&device=`) — the subsystem is locale-independent at the path level.

### Flow (`ServeAdAction`)
1. `locale` = query `locale` if in `AdZone::LOCALES` (`ar|en`) else `ar`.
2. `device` = `AdDeviceClass::fromString(query device)` (lenient → `desktop`).
3. `bucket` = `AdBucket::current()`.
4. `AdServer::serve(zoneKey, locale, device, bucket)` → one candidate or `null`.
5. `buildAd(placementId, bucket)` resolves the response (below) or `null`.
6. Response envelope `{ zone, bucket, ad }`, meta `{ expires_in }`.

### Candidate pool (`AdServer`)
- Cached via `CachedRead::remember`, tags `AdCacheTags::zoneTags(zoneKey)` =
  `['ads', 'ads:zone:{key}']`, key `CacheKeys::adZonePool(zoneKey, locale, device)`, TTL
  `config('advertising.serve.pool_ttl', 300)` seconds.
- `build()` query: active zone `forLocale` + active placements `whereHas` active creative
  (servable type) `whereHas` **servable campaign** + PHP `eligibleForDevice` filter +
  `sortByDesc(effectiveWeight)` + `take(max_candidates_per_zone, 500)`.
- Pool stores **lean arrays** `{placement_id, creative_id, type, weight}` (no models — no
  deserialization risk). Single-flight via `CachedRead`.

### Time-bucket rotation (`AdBucket`)
- `window()` = `config('advertising.serve.bucket_window', 30)` seconds.
- `current()` = `intdiv(time(), window)`.
- `seed(zone, locale, device, bucket)` = `"{zone}:{locale}:{device}:bucket_{n}"`.
- Selection is deterministic for a given seed+bucket → identical across all edge/worker
  nodes within a bucket (no variance), rotates fairly across buckets.

### Serve-time `buildAd` (re-validation + render)
- Re-queries the placement (`is_active`) + creative (`is_active`) + **campaign
  (`isServable`, V3)** + `mediaAsset` + zone (`width/height`). Any failure → `null` ad
  (graceful empty; the slot stays collapsed).
- Renders: `image` → `{ image_url, alt }` (skips if media missing); `html` →
  `{ html: html_code }` (pre-sanitized at write).
- Issues an `AdBeaconToken` and returns `impression: { token, url }`.
- Returns `click: { token, url }` **only when** `AdUrlSafety::isSafe(landing_url)`
  (image-creative whole-creative click). For HTML creatives without a safe `landing_url`
  the `click` object is absent, but `impression.token` is present and is reused by the
  V2 HTML click beacon.

### Cache headers
`Cache-Control: public, max-age={window}, s-maxage={window}` — **no
`stale-while-revalidate`** (remediation **V4**: SWR could serve a response whose token
bucket has expired, dropping the impression). `track`/`click` responses are `no-store`.

### CDN awareness
The serve response is edge-cacheable per URL (locale/device differentiate). The origin
normalizes locale to `ar|en` and device to a known `AdDeviceClass`, so only a small number
of logical variants exist per zone. The CDN should be configured to key the ad-serve cache
on locale+device only (see V9, §13).

---

## 5. Selector architecture

`App\Support\Advertising\Selectors\AdSelector` interface:
`select(array $candidates, int $bucket, string $seed): ?array`. Fully deterministic w.r.t.
seed+bucket so it matches across edge/worker nodes and enables edge caching. Resolved by
`AdSelectorFactory::make($strategy)` (unknown strategy → `Weighted`, safe default).

- **WeightedSelector** — weighted by **explicit weight** (creative/placement), not by
  clicks. `unit(seed)` = `(crc32(seed) & 0x7FFFFFFF) / 2^31` ∈ `[0,1)`; `point =
  floor(unit × Σweight)`; walks candidates accumulating `max(1, weight)`. Same seed →
  same pick (sticky within a bucket); bucket change rotates proportionally to weight.
- **RoundRobinSelector** — sorts candidates by `creative_id` ascending (stable across
  nodes), picks `candidates[bucket % n]`. Ignores weight (pure rotation).
- **EvenSelector** — sorts by `creative_id`, picks
  `candidates[(crc32(seed) & 0x7FFFFFFF) % n]`. Ignores weight; scattered-uniform
  (differs from round-robin's sequential rotation).

The enum is **optimization-ready**: a future CTR/Thompson selector can be added as a new
strategy value without breaking the contract.

---

## 6. Tracking architecture

served ≠ rendered ≠ tracked. Serving never counts an impression; the client confirms it.

### Impression (visibility beacon)
`POST /api/v1/ads/track/impression` (`throttle:ads.track`), body `{ token }`.
`TrackAdImpressionAction`: `AdBeaconToken::verifyAndDecode` → `AdTracker::record(Impression,
…)`. Always returns `accepted` for a valid token (no count-state leak). `no-store`.

### Click — image creatives (signed redirect)
`GET /api/v1/ads/click/{token}` (`throttle:ads.click`). `RedirectAdClickAction`:
decode token → load `placement.creative.landing_url` → `AdUrlSafety::safeTarget` → record
click → `redirect()->away(target, 302)` with `no-store`. **The client never supplies the
URL** (no open redirect). Invalid token → `422`; missing/unsafe target → `404`.

### Click — HTML creatives (beacon, remediation V2)
`POST /api/v1/ads/track/click` (`throttle:ads.click`), body `{ token }`.
`TrackAdClickAction`: decode → record click → `accepted`, `no-store`. **No redirect** —
HTML creatives own their own (sanitized) links; `slot.js` fires this beacon (keepalive) on
anchor click and lets navigation proceed. Image creatives keep the signed redirect.

### Dedup model (`AdTracker::record`)
1. Bot filter: `EngagementActor->isBot` (UA-based) → reject.
2. Atomic `Cache::add` (SET-NX), TTL `AdBucket::window() × 2` (~60s). Key:
   `adtrk:{type}:{placement}:{identity}:{bucket}`.
   - **Impressions**: `identity = actor->key()` (always actor-keyed — IP-anchoring
     impressions would wrongly collapse shared-NAT viewers).
   - **Clicks**: `identity = 'ip:'+ipKey` **iff** `strict_click_dedup` is on **and** an
     `ipKey` was passed (remediation V1); otherwise `actor->key()`.
   - Net: one count per `(type, placement, actor, bucket)`; clicks optionally one per
     `(placement, IP, bucket)` when strict mode is enabled.

### Rate-limiting model (`AppServiceProvider` named limiters)
Layered (remediation V1) — each returns `[per-client, per-IP?]`:
- `ads.serve`: per-client `by(X-Client-Id ?: ip)` at `serve.rate_limit` (300/min) **+**
  per-IP ceiling at `serve.per_ip_rate_limit` (**0 = disabled**, default).
- `ads.track` / `ads.click`: per-client `by(user ?? X-Client-Id ?? ip)` at
  `tracking.rate_limit.max` (60/min) **+** per-IP ceiling at
  `tracking.per_ip_rate_limit` (**0 = disabled**, default).
- The per-IP ceiling engages only when its config value is `> 0`.

### Client identity (`App\Support\Engagement\EngagementActor`)
Shared platform-wide. Authenticated → `u{id}`; guest → `f{sha256(X-Client-Id || ip|ua)}`.
`isBot` from User-Agent. **`X-Client-Id` is client-supplied** — see §13 (V1).

### `AdClientIp` (remediation V1) — `app/Support/Advertising/AdClientIp.php`
`key(Request)` = `sha256` of the `inet_pton`-normalized client IP: IPv6 reduced to the
**/64 prefix** (first 8 bytes, so a client cannot rotate within its own range), IPv4 full.
Used by the per-IP rate ceiling and strict click dedup. **Correctness depends on
TrustProxies** resolving the real client IP (§12).

### Safe-by-default & anti-abuse assumptions
- With `per_ip_rate_limit = 0` and `strict_click_dedup = false` (defaults), behavior is
  identical to pre-V1. The IP-anchored layers are an **ops-gated** hardening (§12/§13).
- Bot filtering is **UA-only** (`BotSignature`) — catches declared/known bots, not
  sophisticated ones. The per-IP layers are the primary anti-inflation control once enabled.

---

## 7. Security model

### HTML sanitization — `AdHtmlSanitizer` (HTMLPurifier, whitelist)
Allowed set is fixed in `config('advertising.creatives.html')` (intentionally **not**
env-overridable — a reviewed security boundary):
- `allowed_html`: `a[href|title|target|rel]`, `b, strong, i, em, u, br`,
  `p|div|span[class|style]`, `ul|ol[class]`, `li`, `h1`–`h6`,
  `img[src|alt|width|height|class|style]`.
- `allowed_css`: `color, background-color, text-align, font-size, font-weight,
  font-style, line-height, margin, padding, width, height, max-width, border,
  text-decoration` — **no `position`/`display`** (no overlay/clickjacking via inline CSS).
- `allowed_schemes`: `http, https, mailto` (blocks `javascript:`/`data:`).
- `target="_blank"` only; `rel="noopener noreferrer"` added automatically. No
  `script`/`iframe`/`object`/`embed`, no `on*` handlers.
- **Enforcement point**: the `AdCreative.html_code` model mutator (V8) — every write path
  is sanitized, not just the Actions.

> **Actual rendering note (important):** the `AdCreativeType` enum docstring mentions
> rendering HTML "inside an isolated iframe (SafeFrame)". That is **aspirational** — the
> **actual** implementation injects the server-sanitized markup via `el.innerHTML` in
> `slot.js` (no iframe). SafeFrame/iframe isolation is a roadmap item (§15), not current
> behavior. Trust the sanitizer + CSP, not iframe isolation.

### Token signing — `AdBeaconToken`
HMAC-SHA256 over payload `"{placement}:{zone}:{bucket}:{exp}"` keyed by
`config('app.key')`, base64url-encoded. `verify` uses constant-time `hash_equals`.
- **Expiry**: `exp` = now + `config('advertising.tracking.beacon_ttl', 3600)` seconds.
- **Replay tolerance**: bucket window **±1** (current and previous bucket only) → effective
  ~30–60s, the tighter bound. Combined with per-actor dedup (§6).
- `verifyAndDecode` returns `{placement_id, zone_id, bucket}` for the click path (token
  alone). Forgery requires `APP_KEY`. The token is non-secret and edge-cacheable by design.

### URL safety — `AdUrlSafety`
`isSafe(url)` = scheme ∈ `{http, https}` **and** non-empty host. Applied at creative write
(`landing_url` validation in `StoreAdCreativeRequest`) and again at click redirect
(defense in depth). `safeTarget` returns the trimmed URL or `null`.

### Open-redirect protection
Closed: the click redirect resolves only the **stored** `landing_url` and re-checks it with
`AdUrlSafety` before `redirect()->away`. No client-supplied destination is ever followed.

### Public route protections
`zoneKey` (`[a-z0-9_]+`) and click `token` (`[A-Za-z0-9._\-]+`) charset-constrained;
`throttle:ads.*` on every public route; `no-store` on track/click; impression endpoint
returns `accepted` regardless of count outcome (no oracle).

### Known tradeoffs
See §13 (resolved V1/V2/V8 and deferred V6/V7/V9) and the accepted-tradeoffs list there.

---

## 8. Analytics pipeline

### Buffering — `AdEventBuffer` (Redis)
- `supported()` = `Cache::getStore() instanceof LockProvider` (a **structural** check, not
  a liveness probe).
- `add(type, placement, channel)` → `Cache::increment('adbuf:delta:{type}:{placement}:
  {channel}')`; the first increment registers the member in a lock-guarded dirty index.
- `flush()` (command `ads:flush-events`, every minute): pulls all deltas, aggregates per
  placement (impressions/clicks + per-channel impression deltas), resolves derived dims in
  one batch (`placement → creative → campaign`), then writes to `AdCounter`
  (`firstOrCreate` + `DB::raw` increment) and `AdStatsRollup`. Returns 0 if `!supported()`.
- `AdTracker` uses the buffer when `buffer_enabled` **and** `supported()`; otherwise it
  takes a synchronous `direct()` DB path.

### Daily rollup — `AdStatsRollup`
`insertOrIgnore(placement, day)` (ensures the day row, atomic via the unique key) then
`col = col + delta` (`DB::raw`, atomic on MySQL/SQLite). `COLUMNS` is a whitelist
(anti-injection). Channels mapped via `TrafficChannel::tryFrom($channel) ?? Direct` →
`impressions_{value}`. `day` = `now()->toDateString()` (application timezone).

### Live counters & history
- `AdCounter` — hot per-placement live totals.
- `AdStatDaily` — daily per-placement + denormalized `{zone, creative, campaign}` dims for
  historical reporting (survive soft-delete; names resolved `withTrashed`).

### Dashboard aggregates — `AdAnalyticsAction` (`GET /admin/ads/analytics`, `ads.view`)
- Window: `24h | 7d | 30d | custom` (custom bounded by `MAX_DAYS = 366`).
- Cached via `Cache::remember(key, CacheTtl::SHORT /*300s*/, …)`. **NB:** the cache key is
  currently a hardcoded string `'ads:analytics:…'` (see V6, §13) and untagged → staleness
  bounded only by the 300s TTL (acceptable for analytics).
- Computes over `ad_stats_daily whereBetween day`:
  - **Totals**: `impressions`, `clicks`, **CTR** = `round(clicks / impressions × 100, 2)`,
    `0` when impressions are 0.
  - **Trend**: zero-filled daily points (continuous series for charting).
  - **Channels**: the five `impressions_*` sums (`direct, internal, search, social,
    referral`).
  - **Top reports**: top-10 campaigns/creatives/zones by impressions; names resolved
    `withTrashed` for soft-deleted entities (`#id` fallback).

---

## 9. Admin frontend

React 18 + Vite + TypeScript SPA (`admin-frontend/`). Advertising lives under
`src/features/advertising/`.

### Surfaces (verified present)
- **Pages** (`features/advertising/pages/`): `AdZonesPage`, `AdZoneFormPage`,
  `CampaignsPage`, `CampaignFormPage`, `CreativesPage`, `CreativeFormPage`,
  `PlacementsPage`, `AdsAnalyticsPage`.
- **Components**: `CreativeImagePicker` (media-library integration for image creatives).
- **Data**: `features/advertising/hooks.ts` (React Query); services
  `ad{Zones,Campaigns,Creatives,Placements,Analytics}.service.ts`; types
  `types/advertising.types.ts`; i18n `i18n/{ar,en}/advertising.json`.
- **Navigation** (`config/navigation.ts`, section `advertising`, icon `Megaphone`):
  Campaigns (`ads.view`), Creatives (`ads.view`), Placements (`ads.view`), Zones
  (`ad-zones.view`), Analytics (`ads.view`).

### RBAC (admin routes — `routes/api/v1/admin.php`)
| Surface | Permissions |
|---|---|
| Campaigns | view `ads.view` · create `ads.create` · edit `ads.edit` · status `ads.publish` · delete `ads.delete` · restore `ads.restore` · force-delete `ads.force_delete` |
| Creatives | view/create/edit/delete/restore/force-delete = `ads.{view,create,edit,delete,restore,force_delete}` |
| Placements | view `ads.view` · create `ads.create` · edit `ads.edit` · delete `ads.delete` (no restore — hard delete) |
| Zones | view `ad-zones.view` · create/update/delete `ad-zones.manage` |
| Analytics | `ads.view` |

Enforced by Spatie `permission:` route middleware (no Policy classes). Campaigns/creatives
expose restore + force-delete (soft delete); placements/zones are hard delete.

### Behavior (platform patterns)
- Lifecycle UI maps to the `PATCH /campaigns/{id}/status` endpoint; allowed targets follow
  the lifecycle matrix (mirrored client-side via `AD_CAMPAIGN_TRANSITIONS` in
  `advertising.types.ts`).
- Placement compatibility UX reflects the zone-type→creative-type matrix
  (`AD_PLACEMENT_COMPAT`) plus device targeting.
- HTML-creative editing submits raw markup; sanitization is server-side (§7).
- Follows the platform CRUD convention: reused `DataTable`/`Pagination`/`SearchInput`,
  `useToast`, `ProtectedRoute`/`hasPermission`, local `useState` + `patch()` (not RHF),
  and `AnalyticsKit` for the analytics dashboard.
- `UNKNOWN`: exact per-page field layouts were not re-verified line-by-line for this
  document; treat the page files as authoritative for UI specifics.

---

## 10. Public frontend

Blade SSR + a vanilla ES-module progressive-enhancement bundle (no public React widget).

### `<x-ad-slot>` — `resources/views/components/ad-slot.blade.php`
Anonymous Blade component. Props: `zone` (required), `locale` (optional), `device`
(optional). Emits `<div data-ad-zone="{zone}" [data-locale] [data-device]
class="ad-slot">`. Empty-safe (renders nothing if no ad).

### Hydration — `resources/js/ads.js` → `resources/js/ads/slot.js`
- Entry boots `initAdSlots()` which hydrates every `[data-ad-zone]`.
- `detectDevice()`: viewport `< 768` → mobile, `< 1024` → tablet, else desktop.
  `pageLocale()`: `<html lang>` → `en`, else `ar`. Both overridable per slot via
  `data-locale`/`data-device`.
- `hydrateSlot` (idempotent via `data-ad-ready`): fetches
  `GET /ads/serve/{zone}?locale&device` via the shared `apiRequest` (sends `X-Client-Id`,
  same-origin). Sets `data-ad-state` = `empty | filled | error`.
- **Image render**: `<a href={click.url} target="_blank" rel="noopener noreferrer
  sponsored"><img loading="lazy" decoding="async">`.
- **HTML render**: `el.innerHTML = render.html` (server-sanitized).
- **Impression beacon**: `IntersectionObserver` (threshold 0.5) fires once per slot
  (`unobserve` after), `POST /ads/track/impression`. Falls back to fire-on-render if no
  `IntersectionObserver`.
- **Click tracking (V2)**: for HTML creatives, a delegated anchor-click listener fires
  `POST /ads/track/click` (keepalive, survives navigation) using `impression.token`, then
  lets navigation proceed. Image creatives use the signed redirect.
- **Resilience**: all wrapped in try/catch — failures never break the host page; the slot
  collapses gracefully.

---

## 11. Configuration (`config/advertising.php` + env)

### Serving (`serve`)
| Key | Env | Default | Notes |
|---|---|---|---|
| `max_candidates_per_zone` | `ADS_MAX_CANDIDATES` | 500 | safe default |
| `default_selector` | `ADS_DEFAULT_SELECTOR` | `weighted` | safe default |
| `pool_ttl` | `ADS_POOL_TTL` | 300 | safe default |
| `bucket_window` | `ADS_BUCKET_WINDOW` | 30 | safe default; also the edge `max-age` |
| `rate_limit` | `ADS_SERVE_RATE_LIMIT` | 300 | per-client/min |
| `per_ip_rate_limit` | `ADS_SERVE_PER_IP_RATE_LIMIT` | **0 (off)** | **production override** — enable only with TrustProxies |

### Tracking (`tracking`)
| Key | Env | Default | Notes |
|---|---|---|---|
| `buffer_enabled` | `ADS_TRACKING_BUFFER` | `true` | safe default (requires Redis) |
| `dedup_minutes` | `ADS_DEDUP_MINUTES` | 30 | **currently UNUSED** (see V7, §13) |
| `beacon_ttl` | `ADS_BEACON_TTL` | 3600 | token expiry seconds |
| `rate_limit.max` | `ADS_TRACK_RATE_MAX` | 60 | per-client/min |
| `rate_limit.window` | `ADS_TRACK_RATE_WINDOW` | 60 | seconds |
| `per_ip_rate_limit` | `ADS_TRACK_PER_IP_RATE_LIMIT` | **0 (off)** | **production override** |
| `strict_click_dedup` | `ADS_STRICT_CLICK_DEDUP` | **`false`** | **production override** — IP-anchored click dedup |

### Security (`creatives.html`)
`allowed_html`, `allowed_css`, `allowed_schemes` — fixed in code (no env override) as a
reviewed boundary (§7).

### Env outside `config/advertising.php`
- `TRUSTED_PROXIES` (`bootstrap/app.php`) — comma-separated CIDRs or `*`; **empty = trust
  none** (default). **Production-required** behind a CDN before enabling per-IP layers.
- `APP_KEY` — beacon-token HMAC secret; must be stable across all nodes.

**Production-required overrides for billable inventory**: `TRUSTED_PROXIES` +
`ADS_SERVE_PER_IP_RATE_LIMIT` + `ADS_TRACK_PER_IP_RATE_LIMIT` + `ADS_STRICT_CLICK_DEDUP`.
Everything else has safe defaults.

---

## 12. Operational requirements

- **Redis (mandatory)** — `LockProvider` store backs the event buffer, atomic dedup
  (`Cache::add`), the tagged pool cache (`AdCacheTags` needs a tag-capable store), and rate
  limiting. Enforced by `RedisProductionCheck`. If Redis is configured-but-down, tracking
  writes may throw and events may be lost (an accepted minor loss, §13); there is no live
  fallback to the DB path because `supported()` is structural, not a liveness probe.
- **Scheduler (mandatory)** — registered in `SchedulerRegistry`, run from
  `routes/console.php` (code-driven cron; the admin UI only toggles `enabled`):
  - `ads_campaigns_tick` → `ads:campaigns-tick`, every minute, **critical** — lifecycle
    auto-transitions + pool invalidation.
  - `ads_flush_events` → `ads:flush-events`, every minute — buffer → counters/daily.
  - Without the scheduler: buffered events never flush (counters/daily stay at 0); campaign
    transitions are delayed (serve-time `isServable` (V3) still prevents over-serve; the
    pool rebuild on `pool_ttl` re-applies the window).
- **CDN** — serve is edge-cacheable (`s-maxage = bucket_window`); track/click are
  `no-store`. Configure the edge to key the ad-serve cache on locale+device only (V9).
- **TrustProxies** — set `TRUSTED_PROXIES` so `$request->ip()` is the real client IP behind
  the CDN; **required before** enabling per-IP rate ceilings or strict click dedup.
  Alternatively the web tier may set the real IP at the socket level (then Laravel's
  TrustProxies is moot) — `UNKNOWN` which the deployment uses; verify before enabling V1
  layers.
- **APP_KEY consistency** — identical across all web/worker nodes (token HMAC).
- **Clock sync** — NTP across nodes; the ±1 bucket tolerance absorbs ~30s skew. Rollup
  `day` and analytics windows both use application-timezone `now()` (consistent).
- **Frontend build** — admin SPA built in `admin-frontend/`; public assets via Vite
  (`npm run build` at the repo root → the `ads` bundle). The public `ads` bundle builds
  cleanly as of this writing.

---

## 13. Audit findings / remediations & tradeoffs

A production-readiness audit produced findings V1–V9. Severity in parentheses.

### Resolved (implemented + tested)
- **V1 (Major) — client-identity inflation.** Dedup and rate-limiting keyed on the
  client-supplied `X-Client-Id` could be defeated by header rotation. Remediation: layered
  rate limiters (per-client **+** per-IP ceiling), gated IP-anchored click dedup, the
  `AdClientIp` /64-normalized key, and env-driven `trustProxies`. **Safe-by-default**: the
  per-IP layers are disabled (`*_per_ip_rate_limit = 0`, `strict_click_dedup = false`)
  until ops sets `TRUSTED_PROXIES`, locks the origin to the CDN, and enables the knobs —
  the pre-launch gate for billable inventory.
- **V2 (Minor) — HTML-creative clicks untracked.** Added `POST /ads/track/click` beacon +
  `slot.js` delegated listener; image creatives keep the signed redirect.
- **V3 (Minor) — expired-campaign over-serve.** `ServeAdAction::buildAd` now re-validates
  `AdCampaign::isServable()` at serve time (closes the over-serve window regardless of pool
  age / scheduler health).
- **V4 (Minor) — token/edge-cache timing.** Dropped `stale-while-revalidate` from the serve
  response so a served response can't outlive its token bucket. Replay tolerance left
  unchanged (±1 bucket).
- **V8 (Minor) — sanitize-only-at-Action.** Moved HTML sanitization to the
  `AdCreative.html_code` model mutator (defense-in-depth; covers all write paths).

### Deferred (documented, not yet implemented)
- **V6 (Minor)** — `AdAnalyticsAction` hardcodes its cache key (`'ads:analytics:…'`),
  violating `.ai/cache-keys.md` (should route through `CacheKeys`). Untagged → TTL-only
  invalidation. Consistent with the `VideoAnalyticsAction` precedent.
- **V7 (Minor)** — `advertising.tracking.dedup_minutes` is **unused**; actual dedup is the
  ~60s bucket window. Either honor it or remove the key/env.
- **V9 (Minor)** — the serve endpoint is edge-cache-pollutable via arbitrary query params
  (origin reads only locale/device, but the CDN keys on the full URL). Fix via CDN cache-key
  normalization and/or origin param rejection.

### Accepted tradeoffs (deliberate)
- **served ≠ rendered**: impressions count only on the visibility beacon, not on serve.
- **Token model**: non-secret, non-actor-bound, edge-cacheable; replay bounded by the ±1
  bucket window + per-actor dedup; forgery requires `APP_KEY`.
- **HTML creative outbound links**: scheme-filtered at write (HTMLPurifier) but not routed
  through the signed redirect; clicks are best-effort beacons (§6/§10).
- **Identity model** `user ?? X-Client-Id ?? ip` is platform-wide; the ad-specific per-IP
  layers are off by default and ops-gated.
- **Bot filter** is UA-only.
- **Buffer**: "minor loss on Redis collapse acceptable" (documented in `AdEventBuffer`);
  Redis is mandatory in production.
- **Video creatives** are schema-ready but disabled; `preroll` zones cannot be assigned a
  creative yet.
- **Zones/placements have no soft delete** (config/link entities); analytics resolves names
  `withTrashed` for soft-deleted campaigns/creatives.

---

## 14. Testing status

### Implemented (`tests/Feature/Advertising/*`, `tests/Unit/Advertising/*`)
77 advertising feature+unit tests passing (verified post-remediation). Coverage includes:
- **Unit**: `AdSelectorTest` (weighted/round-robin/even determinism), `AdUrlSafetyTest`.
- **Serving/selection**: `AdServerTest`, `AdServeEndpointTest` (serve, edge headers, **V3**
  expiry-stops-serving, **V4** no-SWR assertion), `AdDomainSmokeTest`.
- **Tokens/sanitization**: `AdBeaconTokenTest` (sign/verify/replay window), `AdHtmlSanitizerTest`.
- **Tracking**: `AdTrackingTest` (buffer flush, dedup, bot filter), `AdTrackingEndpointTest`
  (impression/click endpoints, bot, **V1** per-IP ceiling + strict click dedup + safe
  default, **V2** HTML click beacon + bot).
- **Lifecycle/admin Actions**: `AdCampaignLifecycleTest`, `AdCampaignStatusActionTest`,
  `AdZoneActionTest`, `AdCampaignActionTest`, `AdCreativeActionTest` (+ **V8** model-boundary
  sanitization), `AdPlacementActionTest`, `AdAnalyticsTest`. Action tests assert
  `activity_log` entries where applicable.

### Known uncovered (open gaps)
- **No HTTP-level admin RBAC/permission tests** — admin Action tests instantiate Actions
  directly, bypassing the `permission:` route middleware; the permission→route mapping is
  unverified by tests.
- **No HTTP FormRequest validation tests** for admin endpoints (rules exercised only via a
  direct `Validator` in one creative case).
- **No image-creative serve endpoint test** (`AdServeEndpointTest` uses HTML creatives).
- **No locale/device pool-segmentation endpoint test.**
- **No frontend tests at all** — `admin-frontend/` has no test runner; `slot.js` (public
  hydration, beacons) is untested.

### Known pre-existing unrelated failures
`ImagePipelineTest` and `MediaAssetFoundationTest` (image toolchain) fail independently of
advertising and reproduce in isolation. `UNKNOWN`: the final full-suite count at the time of
this document — the advertising suite (77) is green; the two image-toolchain tests are the
only known reds and are unrelated.

---

## 15. Future roadmap

Possibilities (not implemented; listed for direction):
- **Third-party ad networks** — header bidding / ad exchange / GAM integration. Current
  system is strictly first-party.
- **SafeFrame / iframe isolation** for HTML creatives (current: sanitized `innerHTML`).
- **Video creatives** — schema is ready (`AdCreativeType::Video`, `AdPlacementType::Preroll`)
  but disabled; needs a player + preroll/midroll formatting + tracking.
- **Revenue / billing reporting** — `budget_total`/`budget_spent` columns exist; no spend
  engine or revenue reports yet.
- **Advanced fraud detection** — beyond UA bot filter + per-IP/dedup: behavioral signals,
  IP reputation, datacenter-range filtering.
- **Frequency capping** — per-user/session exposure limits (not implemented).
- **Targeting expansion** — the `targeting` JSON column exists; geo/segment/daypart/context
  engines are future.
- **Pacing engine** — `AdPacingMode` (`even`/`asap`) fields exist; no pacing engine.
- **CTR-optimizing selector** — `AdSelectorStrategy` is extensible; a Thompson-sampling /
  bandit strategy can be added as a new value without breaking the `AdSelector` contract.

---

*End of reference. Keep this file in sync with the code; update §13/§14 as deferred items
(V6/V7/V9) and test gaps are addressed.*

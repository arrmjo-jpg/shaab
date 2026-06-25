# AlphaCMS Polls / Voting Subsystem — Architecture Reference

> **STATUS: APPROVED DESIGN — NOT YET IMPLEMENTED.** As of this writing there is **no**
> poll code in the repository. This document is the canonical, source-of-truth design for
> future implementation. It reflects the approved direction from the legacy-system concept
> analysis, re-expressed in **AlphaCMS-native** patterns.
>
> Last reviewed: 2026-05-26. Obey `.ai/README.md`, `architecture.md`, `api-standards.md`,
> `naming-rules.md`, `cache-keys.md`, `performance-architecture.md`, `auth-architecture.md`,
> and the model-audit convention. When this subsystem is built, keep this file in sync.
>
> **Legend**: *existing* = a verified platform primitive to reuse · *proposed* = a new
> poll class/table to create · `UNKNOWN` = not determinable from the current codebase.

---

## 0. Product vision & scope boundary

AlphaCMS Polls is a **native audience-engagement subsystem** for:
- editorial audience participation,
- article/news **embedded** polls,
- standalone public polls,
- lightweight opinion voting,
- future analytics / command-center integration.

**It is NOT a survey/form builder.** No multi-question forms, no branching, no free-text
answers, no respondent profiles. It is a focused publisher **single-question polling**
subsystem. If a survey builder is ever needed, it is a separate subsystem — do not bloat
Polls into it.

### Reused existing platform primitives (do not reinvent)
- **Identity**: `App\Support\Engagement\EngagementActor` (`user ?? X-Client-Id ?? ip`
  fingerprint + `BotSignature` UA bot flag).
- **IP normalization**: `App\Support\Advertising\AdClientIp` (`inet_pton` + IPv6 `/64`
  prefix + `sha256`) — built for advertising V1; reuse for per-IP poll keys.
- **TrustProxies**: env-driven `trustProxies` in `bootstrap/app.php` (`TRUSTED_PROXIES`).
- **Cache**: `App\Support\Cache\CacheKeys` (mandatory), `CacheTtl`, `CachedRead::remember`
  (single-flight + negative caching), per-module `*CacheTags` + `Cache::tags()->flush()`.
- **Audit**: `App\Support\Audit\AuditsChanges` (spatie/laravel-activitylog).
- **Responses**: `App\Support\Responses\ApiResponse` (`{success,message,data,meta}` /
  `{success,message,errors}`).
- **RBAC**: Spatie `permission:` route middleware.
- **Scheduler**: `App\Support\Scheduler\SchedulerRegistry` (code-driven cron, admin toggles).
- **Rate limiting**: named limiters in `App\Providers\AppServiceProvider`.
- **Abuse escalation**: `VerifyRecaptcha` middleware (alias `recaptcha`, gated by
  `recaptcha_enabled`). `UNKNOWN`: whether reCAPTCHA is configured in this deployment.
- **Public delivery**: anonymous Blade component pattern (`<x-ad-slot>`) + vanilla ES-module
  progressive JS bundled by Vite (`resources/js/broadcast/api.js` `apiRequest` helper).
- **Admin**: React SPA in `admin-frontend/` + `AnalyticsKit` charting.

### Convention note (divergence to resolve once)
`.ai/module-checklist.md` and `architecture.md` list **DTOs** (`Create*Data`) and
**Policies**. The most recent real subsystems (advertising, video library, broadcast) **omit
DTOs** (Actions receive validated arrays) and use **`permission:` route middleware instead of
Policy classes**. This document follows the recent-sibling precedent (advertising) for
consistency, since Polls will sit beside it. DTOs may be adopted if the team prefers — treat
as a one-time team decision, not a per-feature choice.

---

## 1. Domain model (proposed)

Four proposed entities. Tables/columns are a design proposal (no migration exists).

| Entity | Table | Soft delete | Audited (`AuditsChanges`) |
|---|---|---|---|
| `Poll` | `polls` | **Yes** | Yes — log `poll` |
| `PollOption` | `poll_options` | No (see §2) | Yes — log `poll_option` |
| `PollVote` | `poll_votes` | No (immutable fact) | **No** (high-volume) |
| `PollVoteOption` | `poll_vote_options` | No (join) | **No** |

> **Audit exemption rationale**: per the model-audit convention "every model uses
> `AuditsChanges`," but the platform already exempts counter/event/stat tables
> (`AdCounter`, `AdStatDaily`, engagement events). `PollVote`/`PollVoteOption` are
> immutable, high-volume vote facts — exempt them per that precedent. Admin mutations
> (poll/option create/update/delete/toggle) are audited via the parent models.

### Poll — responsibility: the question + its rules/window
Proposed columns:
- `id`, `uuid` *(proposed — stable public reference, mirrors ads/campaign uuid)*
- `question` (string)
- `allow_multiple` (bool) — single vs multi choice
- `is_active` (bool)
- `starts_at` (nullable datetime), `ends_at` (nullable datetime)
- `audience_mode` (enum `PollAudienceMode`) — *proposed values*: `public` (guest+auth),
  `authenticated` (logged-in only). Default `public`.
- `result_visibility` (enum `PollResultVisibility`) — *proposed values*: `always`,
  `after_vote`, `after_close`. Default `after_vote`.
- `metadata` (nullable json) — **assumption**: reserved for future, optional extensibility
  (e.g., embed context, theming). MVP may leave it unused; include the column only if it
  costs nothing. Mark `UNKNOWN` whether MVP needs it — default to *omit until a concrete
  need*.
- `created_by`, `updated_by` (admin user ids), `timestamps`, soft deletes.

Relationships: `hasMany PollOption`, `hasMany PollVote`. Lifecycle: §3. Soft delete →
trash/restore (admin). Scopes (proposed): `active`, `started`, `notEnded`, `votable`
(= active + started + not-ended), mirroring the legacy `votable` but AlphaCMS-clean.

### PollOption — responsibility: a choice + its denormalized tally
Proposed columns: `id`, `poll_id`, `label` (string — legacy called it `option_text`),
`sort_order` (int), `votes_count` (unsigned int, **denormalized** live tally), `timestamps`.

- **Deletion constraints (§2)**: an option **with votes cannot be deleted** (blocked at the
  Action). Unvoted options may be removed during edit (hard delete is fine — nothing to
  retain). Options are **not** independently soft-deleted and are **not** removed when the
  parent poll is soft-deleted (so restore is lossless); force-deleting a poll cascades.
- Relationships: `belongsTo Poll`, `hasMany PollVoteOption` (its choices).

### PollVote — responsibility: one immutable ballot per voter per poll
Proposed columns: `id`, `poll_id`, `voter_hash` (sha256 identity — §4), `created_at`
(only; immutable — no `updated_at`). **Unique `(poll_id, voter_hash)`** — the hard
duplicate guarantee. No soft delete (votes are facts). Relationships: `belongsTo Poll`,
`hasMany PollVoteOption`.

### PollVoteOption — responsibility: normalized choice(s) of a ballot
Proposed columns: `id`, `poll_vote_id`, `poll_option_id`. No timestamps, no soft delete.
A single-choice ballot has one row; a multi-choice ballot has many. Relationships:
`belongsTo PollVote`, `belongsTo PollOption`.

> **Why retain individual votes** (not just counters): enforces uniqueness, enables a clean
> reconcile of `votes_count` from source of truth, and — because each row is timestamped —
> lets analytics/trends be **backfilled** later with zero data loss (§8). This differs
> deliberately from ads, which discard raw events into daily aggregates.

---

## 2. Product rules (exact expected behavior)

All domain-rule failures return `ApiResponse::error(__('polls...'), [], 422)` from the
**Action** (lang keys only — never inline strings, never a hand-thrown
`ValidationException` for business rules; that legacy habit is discarded). Structural input
validation (types, arity, membership) lives in the **Form Request**.

| Rule | Expected behavior |
|---|---|
| **Single choice** (`allow_multiple = false`) | Exactly **one** option id required; reject 0 or >1. |
| **Multiple choice** (`allow_multiple = true`) | **One or more** option ids; reject empty. |
| **Closed poll** (`is_active = false`) | Reject voting (`poll not open`). |
| **Not yet started** (`now < starts_at`) | Reject voting (not yet votable). |
| **Expired** (`now > ends_at`) | Reject voting (poll closed). |
| **Option ownership** | Every submitted option id must belong to the poll; reject otherwise. |
| **Voted-option deletion** | **Forbidden** — an option with ≥1 vote cannot be deleted during edit. |
| **Duplicate vote** | One ballot per `(poll, voter_hash)` — enforced by DB unique + dedup (§4); second attempt → "already voted." |
| **Result visibility** | Governed by `result_visibility` (`always` / `after_vote` / `after_close`). |
| **Audience mode** | `authenticated` polls reject guest votes; `public` allows both. |

Re-check open/active state inside the vote transaction (the authoritative gate), not only
in the Form Request, to avoid a race where a poll closes between validation and write.

---

## 3. Voting lifecycle (intentionally simple)

**This is NOT the ad-campaign 6-state machine. Do not import it.** Polls derive an open
state; they do not have a workflow engine.

```
open  ⇐  is_active = true  AND  (starts_at is null OR now ≥ starts_at)
                            AND  (ends_at  is null OR now ≤ ends_at)
```

- `Poll::votable()` (query scope) and `Poll::isOpenForVoting()` (instance) express the gate.
- `Poll::resultsVisible(?voter)` derives from `result_visibility`:
  - `always` → true; `after_vote` → true once the actor has a ballot; `after_close` → true
    once `ends_at` has passed (or `is_active = false`).
- **Future expansion** (only if editorial demand appears): a lightweight status enum
  (`draft / published / closed / archived`). Keep it additive and optional — **not** MVP.
  Avoid `paused`/`completed`/`scheduled` proliferation; the derived open-state already
  covers scheduling.

---

## 4. Identity / abuse model

**Be brutally honest: guest voting is NOT tamper-proof.** Pick a threat-model tier per poll;
never market "one vote per person" for guest polls.

### Identity tiers (proposed; offer via `audience_mode` + a per-poll integrity setting)
| Tier | `voter_hash` basis | Pros | Cons / abuse limit |
|---|---|---|---|
| **A — client-id only** | `sha256(X-Client-Id)` | Frictionless; no NAT collapse | Trivially bypassed by rotating the client-supplied header (same flaw as ads audit V1). |
| **B — client-id + normalized IP** | `sha256(X-Client-Id + AdClientIp::key)` | Header rotation alone no longer escapes | Shared NAT/CGNAT partially collapses distinct voters; determined attacker on one IP rotating client-id still multi-votes. |
| **C — authenticated identity** | `u{user_id}` | Strongest integrity | Requires login; excludes guests. |

Recommended default: **A for low-stakes polls, C (`authenticated`) for high-integrity
polls.** Tier **B** is an opt-in hardening, **off by default** and gated exactly like ads V1
(`per_ip` knobs + `TRUSTED_PROXIES`).

### Guarantees & dependencies
- **DB uniqueness**: unique `(poll_id, voter_hash)` is the hard guarantee. Catch the
  integrity violation on insert → return "already voted" (do not pre-`SELECT`-then-insert
  race).
- **Optional fast-path dedup cache**: `Cache::add(NX)` keyed `poll:vote:{poll}:{voter_hash}`
  to short-circuit repeats before hitting the DB (mirrors `AdTracker`). Cache is an
  optimization; the DB unique is the source of truth.
- **TrustProxies dependency**: any IP-anchored tier (B) requires `TRUSTED_PROXIES` set so
  `$request->ip()` is the real client, not the CDN edge — identical hard precondition to
  ads V1. Reuse `AdClientIp` for the `/64`-normalized key.
- **Rate limiting**: a *proposed* `poll.vote` named limiter in `AppServiceProvider` —
  per-actor (`user ?? X-Client-Id ?? ip`) **+** optional per-IP ceiling (off by default).
- **Bot filtering**: `EngagementActor->isBot` (UA-only) — naive; state it plainly.
- **reCAPTCHA escalation**: for contested polls, gate the vote route behind the existing
  `recaptcha` middleware (per-poll opt-in). `UNKNOWN`: whether it's enabled here.
- **Privacy**: store **only** the one-way `voter_hash`. **Never store a raw IP.** (Legacy
  `voter_key` derivation is `UNKNOWN`; do not repeat it if it was raw.)

---

## 5. Caching architecture

Follow `.ai/cache-keys.md` exactly — **all keys via `CacheKeys`, never hardcoded** (note:
the ads analytics action currently violates this, audit finding V6 — do **not** copy that
mistake here).

- **Keys** (`resource:scope:identifier` per `naming-rules.md`): proposed
  `CacheKeys::pollResults($pollId)` → `poll:results:{id}`; `CacheKeys::pollSummary($pollId)`
  → `poll:summary:{id}`; `CacheKeys::pollsStats()` → `polls:stats`; future
  `poll:analytics:{id}:{range}`.
- **Tags**: proposed `PollCacheTags` with `ALL = 'polls'`, `poll($id) = 'polls:poll:{id}'`,
  `tags($id) = [ALL, poll($id)]` (mirrors `AdCacheTags`).
- **Reads via `CachedRead::remember`** (single-flight). `CacheTtl::SHORT` for open-poll
  results; a closed poll's results are immutable → may use `CacheTtl::LONG`.
- **Invalidation = explicit on write** (no model observers — platform policy). On
  vote / option edit / toggle / delete: `Cache::tags(PollCacheTags::tags($pollId))->flush()`.
- **Hot-poll guard**: for a viral open poll, per-vote invalidation can cause read stampede.
  Acceptable MVP choice: invalidate-on-vote (simple, correct) with a `CachedRead` short-TTL
  backstop; switch a hot poll to short-TTL-without-per-vote-invalidation if needed.

Cached surfaces: results, summary, admin index stats; future analytics aggregates.

---

## 6. Admin architecture (React SPA only)

**No Livewire. No Blade admin.** (The legacy Livewire/Blade/SweetAlert admin is discarded.)
Implement in `admin-frontend/src/features/polls/` mirroring `features/advertising/`.

Proposed surfaces:
- **Polls list** — question, open/closed/scheduled badge, total votes, schedule; filter +
  search; reuse `DataTable`/`Pagination`/`SearchInput`.
- **Create/Edit** — question, `allow_multiple`, schedule, `audience_mode`,
  `result_visibility`; **Option manager** (add/remove/reorder) with a guard that blocks
  deleting voted options.
- **Results** — per-option bars, percentages, total + unique voters; refresh.
- **Trash** — soft-deleted polls; restore / force-delete.
- **Analytics** (future) — trends + top polls via `AnalyticsKit`.

Conventions: React Query v5; local `useState` + `patch()` for CRUD (platform pattern, not
RHF); `useToast` (success/error/confirm); `ProtectedRoute` + `hasPermission`; **no
border-radius** per the UI memory; i18n `admin-frontend/src/i18n/{ar,en}/polls.json`;
nav entry in `config/navigation.ts`.

### RBAC (proposed permissions — Spatie `permission:` middleware)
`polls.view`, `polls.create`, `polls.edit`, `polls.delete`, `polls.restore`,
`polls.force_delete`. (Optionally `polls.results.view` if results need separate gating.)
Route protection mirrors `routes/api/v1/admin.php` advertising blocks.

---

## 7. Public frontend architecture

Approved model: **Blade SSR + progressive JS** (the JSON API stays JSON-only per
`api-standards.md`; the Blade widget is the public *web* shell that calls that API — same
split as `<x-ad-slot>`).

- **`<x-poll-widget>`** *(proposed anonymous Blade component)* — props e.g. `:poll`/`id`;
  renders `<div data-poll-id="…">` with the question + options server-side (works without
  JS), hydrated by a `resources/js/polls.js` Vite entry → `resources/js/polls/widget.js`,
  reusing `broadcast/api.js` `apiRequest`.
- **Public endpoints** *(proposed, under `routes/api/v1/public.php` `polls` prefix)*:
  - `GET /api/v1/polls/{poll}/results` — cache-friendly, deterministic (edge-cacheable,
    short `s-maxage`); respects `result_visibility`.
  - `POST /api/v1/polls/{poll}/vote` — `no-store`; `throttle:poll.vote`; body `{ option_ids }`.
- **Vote UX flow**: render options (radio for single, checkbox for multi) → submit vote →
  on success swap to results with animated percentage bars → mark voted (localStorage +
  server dedup).
- **Duplicate-vote UX**: if the server reports already-voted (422/dedup), show results
  directly (no error noise).
- **Closed/empty/error states**: closed → render results read-only (if visible) or a
  "voting closed" notice; empty (no poll) → render nothing (collapse); network/JS error →
  graceful, never break the host page (try/catch, mirrors `slot.js`).
- **Progressive enhancement**: SSR shows the question + options; JS adds interactivity +
  live results. With JS disabled, the question is still readable.

---

## 8. Analytics strategy

Polls **preserve individual vote rows**, so trends are backfillable — defer the dashboard
without losing data.

- **MVP (day-one, lightweight)**: `votes_count` (denormalized, atomic), total votes, unique
  voters (`count(PollVote)` for the poll), cached result percentages. No dashboard.
- **Future**: daily trends (votes/day from `PollVote.created_at`), top polls, participation
  analytics, command-center integration. A *proposed* `poll_stats_daily` rollup table +
  scheduled `poll:rollup` task in `SchedulerRegistry` **only if** live `GROUP BY` over votes
  becomes expensive — otherwise compute-on-read with caching.
- **Schema readiness preserved**: keeping timestamped vote rows means the rollup can be
  introduced later and **backfilled**. Dashboard explicitly deferred (Phase 3).
- **Command center**: `UNKNOWN` whether a unified command-center surface exists (only the
  broadcast command center is confirmed); treat as forward-compatible (Phase 5), not a
  current integration point.

---

## 9. Article / content embedding (Phase 3 — IMPLEMENTED)

Polls embed into editorial content as a **TipTap content node** (Option B — *not* a
`poll_id` FK). `Article` is the unified content entity (no separate News); its content is a
TipTap document (`content_json`), and `TipTapRenderer` derives the `content` HTML delivered
via `PublicArticleResource.content_html` to the **headless** reading frontend.

**Persisted node contract (canonical):** `{ "type": "poll", "attrs": { "uuid": "<poll-uuid>" } }`
— uuid only, no poll payload. `TipTapSanitizer` allow-lists the `poll` node and validates the
uuid format (extra attrs stripped; malformed uuid → whole document rejected, 422).
`TipTapRenderer` emits an escaped **placeholder** `<figure data-poll-uuid="<uuid>"></figure>`
(mirrors the `embed` node; never inlines poll data).

**Admin editor:** a TipTap `Poll` node (`PollExtension`) + an "Insert poll" toolbar action
open a picker (`PollDialog`) that searches the admin polls list (`pollsService`) and inserts
the node by uuid; an in-editor NodeView previews the question.

**Public hydration — integration contract (reading frontend is headless; no Blade article
page in this repo):** the reading frontend MUST hydrate every `[data-poll-uuid]` placeholder
via the Phase-2 public API: `GET /api/v1/polls/{uuid}` (render form/results per state) ·
`POST /api/v1/polls/{uuid}/vote` · `GET /api/v1/polls/{uuid}/results`. This is the **same
contract** `resources/js/polls/widget.js` (`<x-poll-widget>`) already implements for Blade
surfaces — reuse that bundle where shared, else reimplement the same calls. A deleted poll →
`404` → render a graceful "unavailable" state.

**Decoupling / cache:** the marker is a static uuid, so the article page cache and poll
lifecycle are fully **decoupled** — editing / closing / deleting a poll needs **no article
cache invalidation** (the widget fetches live state via the no-store hydration call).

---

## 10. Security model

- **Input validation**: Form Requests only (`CastVoteRequest`, `StorePollRequest`,
  `UpdatePollRequest`) — never validate in controllers. `option_ids` typed + arity per
  `allow_multiple`.
- **Ownership / option integrity**: every submitted option must belong to the poll
  (reject otherwise); options belong to exactly one poll.
- **Duplicate prevention**: DB unique `(poll_id, voter_hash)` + optional `Cache::add` NX
  (§4).
- **Tamper assumptions**: guest identity is spoofable (tiers A/B); only tier C
  (authenticated) is strong. Document per-poll.
- **Bot assumptions**: UA-only filtering is weak; the per-IP rate ceiling (when enabled) is
  the real volume cap.
- **Captcha escalation path**: optional `recaptcha` middleware on the vote route for
  high-abuse polls.
- **No secrets / no raw PII**: `voter_hash` only; no raw IP; no inline strings (lang files).
- **Optional signed vote token** *(future hardening, mark optional)*: a short-lived HMAC
  token issued with the rendered widget (à la `AdBeaconToken`) to bind a vote to a served
  poll render and curb direct-API stuffing. Not MVP; dedup + rate limit suffice initially.

---

## 11. Performance expectations

- **Traffic profile**: polls are far lower volume than ad impressions; a poll gets
  thousands–tens-of-thousands of votes, not millions/min.
- **Atomic counter updates**: `votes_count` incremented via `DB::raw('votes_count + n')`
  inside the vote transaction (no read-modify-write race).
- **Transaction expectations**: the vote write is one DB transaction — insert `PollVote`,
  insert `PollVoteOption` rows, increment each option's `votes_count`. Bounded, small.
- **Vote write path**: insert + insert + increment + explicit cache invalidation. Re-check
  open-state inside the transaction.
- **Cache read path**: results/summary served from cache (`CachedRead`) — no DB on hit.
- **Redis vote buffering intentionally DEFERRED.** Unlike ads (which buffer to avoid
  hot-row contention at impression scale), polls write individual rows synchronously —
  needed for uniqueness anyway. **Reconsider** a `PollVoteBuffer` (mirroring `AdEventBuffer`)
  + scheduled flush only if a single poll sustains very high write throughput (e.g.,
  hundreds of votes/sec) — at which point row-insert/`votes_count` contention warrants it.
  Document the trigger; do not pre-build it.

---

## 12. MVP scope (LOCKED)

**IN (MVP):**
- Single-choice polls.
- Multi-choice polls.
- Public voting.
- Admin CRUD (Actions + Form Requests + Resources + controllers + versioned routes).
- Scheduling (`starts_at`/`ends_at`) + `is_active`.
- Duplicate prevention (DB unique + dedup).
- Guest support **and** authenticated support (hybrid `EngagementActor`).
- Cached results (percentages, totals, unique voters).
- Trash / restore (soft delete on `Poll`).

**OUT (explicitly not MVP):**
- Giant lifecycle state machine.
- Advanced analytics dashboard.
- Fake / manual vote injection (the legacy `manual:` ballot hack — **never port it**).
- Redis vote buffering.
- Command-center integration.
- Heavy workflow engine.
- Survey/form-builder features (permanently out of scope).

---

## 13. Future roadmap

- Authenticated-only polls (`audience_mode = authenticated`).
- Strict anti-abuse mode (tier B IP-anchored dedup + per-IP ceiling, gated).
- Daily analytics (rollup + trends).
- Top polls / participation analytics.
- Editorial embedding (content blocks).
- Command-center integration.
- Alerting (e.g., abuse spikes) — depends on command center.
- reCAPTCHA escalation for contested polls.
- Advanced integrity modes (signed vote token, device attestation) — evaluate ROI.

---

## 14. Implementation phases (mandatory; each independently shippable)

Use the same gated workflow as the ad remediations (discovery → plan → execute → validate →
stop per slice).

- **Phase 1 — Domain + Admin CRUD.** Migrations, models, enums (`PollAudienceMode`,
  `PollResultVisibility`), Form Requests, Actions (`Create/Update/Delete/Restore/
  ForceDeletePollAction`, `TogglePollActiveAction`), Resources, admin controllers + RBAC
  routes, option manager rules (block voted-option deletion), trash, audit on
  `Poll`/`PollOption`, tests. **No public voting yet.**
- **Phase 2 — Public voting.** `CastVoteAction` + `GetPollResultsAction`, public endpoints,
  identity/dedup (tiers A/C; B gated off), `poll.vote` rate limiter, `<x-poll-widget>` +
  `polls.js`, result visibility, tests.
- **Phase 3 — Analytics.** Unique-voter + trend reads (compute-on-read first; rollup table +
  `poll:rollup` task only if needed), admin analytics page (`AnalyticsKit`), tests.
- **Phase 4 — Embedding.** Article/news content-block integration + SSR widget reuse.
- **Phase 5 — Command center + hardening.** Tiles/feed, optional reCAPTCHA gating, tier-B
  strict dedup, optional signed vote token.

---

## 15. Open decisions / UNKNOWN (resolve at implementation time)

- DTOs + Policy classes vs validated-array Actions + `permission:` middleware — this doc
  follows the recent-sibling (advertising) precedent; confirm with the team.
- Whether `Poll.metadata` (json) is worth including in MVP — default **omit** until needed.
- Exact value sets for `PollAudienceMode` / `PollResultVisibility` — proposed above; confirm.
- Whether reCAPTCHA is enabled in this deployment (`recaptcha_enabled`).
- Whether a unified command-center surface exists to integrate with (only broadcast's is
  confirmed).
- The article content-block/shortcode mechanism for embedding (Phase 4 dependency).

---

*End of reference. This is approved design, not implemented code. Update on build:
flip the status banner, replace "proposed" with verified file paths, and add a source map +
testing-status section (as in `advertising.md`) once Phase 1 lands.*

# AlphaCMS Homepage Architecture — The 4-Layer Model

> Status: **Approved (Phase 1) — v1.0 (post-audit corrections applied)**. See also
> `.ai/frontend-platform.md` and `.ai/theme-contract.md`.

Homepage **business composition** differs per customer (which blocks, in what
order, with what limits, and what is visible). Homepage **business logic** is
never duplicated. Per-customer difference is expressed as a **declarative
manifest**, consumed by a single shared orchestrator running shared resolvers.

---

## The 4 layers

| Layer | Owner | Responsibility | Boundary |
|---|---|---|---|
| **1. Manifest** | Config / Data (per client) | Ordered list of block instances + typed params (which blocks, order, limits, source, visibility) | Declarative, **JSON-serializable**, no logic, no nesting |
| **2. Orchestrator** | Core code (single, shared) | Read manifest -> resolve feature flags -> run resolvers (in parallel) -> **isolate per-block failures** -> caching/revalidation/locale/SEO/tracking -> assemble `HomepageView` | No per-client `if`s — differences come from the manifest |
| **3. Block resolvers** | Core code (one per block type) | Given params, fetch and shape **one** block's data into its block view-model | Pure, cacheable, tagged; no presentation |
| **4. Renderers** | Theme | Render a block view-model visually; provide presentational empty/loading/error states | No fetching, no business logic; interactive data via Core data-access hooks |

Flow: `manifest -> orchestrator -> resolvers -> theme renderers`.

## Two gating axes (keep distinct)

- **Composition** = editorial / layout choice -> a block is present, absent, or
  ordered in the manifest.
- **Feature flag** = capability / licensing -> whether a module is enabled for the
  deployment at all.

The orchestrator resolves **feature flags first** (skip resolving data for disabled
capabilities), then applies **composition**. Example: "no polls" can mean the polls
module is not licensed (flag) or simply not placed on the homepage (composition).

## Per-block failure isolation & fallback ownership

- The orchestrator runs resolvers independently and assigns each block a state:
  **`ok` | `empty` | `error`**. A single resolver failure (API hiccup, timeout)
  **never blanks the page** — that block degrades while the rest renders.
- **Core owns the decision** (which state, skip vs. show-empty) and provides a
  **default renderer** for any block a theme does not cover.
- **The theme owns the visual** empty / loading / error treatment for its renderers.
- The page (and its SEO) always renders even if some blocks are empty/errored.

## Interactive blocks (load-more, live)

A list/rail/live block that loads more or refreshes uses a **Core data-access hook**
(e.g. `useFeedPage(blockKey, cursor)`, `useLiveRefresh(channel)`). The theme
**triggers** the hook and renders results; it never fetches. See
`theme-contract.md` ("Interactive data & mutations").

## How per-customer variation maps (no duplication)

Same orchestrator + same resolvers, different manifest. All examples use **catalog
keys** (see below):

- **Customer A** -> `hero(source: featured, limit: 5)` , `article-list(source: latest, limit: 12)` ,
  `category-rail(category: sports)` , `poll-spotlight` , `video-strip`
- **Customer B** -> `breaking(priority)` , `hero(source: stories, limit: 10)` ,
  `video-strip(variant: grid, limit: large)`  (poll-spotlight and the sports rail simply absent)
- **Customer C** -> `live-strip(priority)` , `trending` , `article-list(limit: 6)`

Different limits = a param. Different order = array order. Different visibility =
presence + flag. **Zero business-logic duplication.**

## Manifest model & schema

- **JSON-serializable, declarative data only** — no executable code, imports, or
  helpers. (This is what makes the Phase 2 migration to a backend-served manifest
  clean.)
- **Language-neutral schema (JSON Schema)** in `src/core/config`, consumed by the
  Next build for validation **and reusable as-is by Laravel** when the manifest
  becomes backend-managed. One schema, two consumers — no fork.
- **Referential integrity validated at build**: referenced categories, sources, and
  locales must exist; unknown block keys or out-of-range params fail the build.
- Always **structured data over the fixed catalog** — never a freeform visual
  canvas, in any phase.

## Manifest source (Phase 1 vs later)

- **Phase 1:** the manifest is a **JSON-serializable config file in client config**
  (`src/clients/<client-id>/homepage`). Changing a homepage is a config change +
  redeploy.
- **Phase 2+:** the **same schema** moves into the Laravel backend and becomes
  **editor-managed** via the admin (recompose without a redeploy). Only the *source*
  changes; schema, orchestrator, resolvers, and renderers do not.

## Block catalog strategy (curated, enterprise-safe)

A **fixed catalog**. Each block type is defined once in Core as:
`{ key, params schema (typed, bounded), resolver, view-model, default renderer }`,
with a theme renderer per preset. **Flat composition, no nesting, no logic in
params.**

Initial catalog (extend deliberately, not speculatively):

`hero` · `breaking/headlines` · `category-rail` · `article-list` ·
`editors-picks` · `most-read/trending` · `video-strip` · `reels-strip` ·
`live-strip` · `poll-spotlight` · `ad-slot` · `newsletter-cta`

Rules:
- **New composition** (which / order / limits / visibility) = config, **zero code**.
- **New block type** = a one-time Core addition (resolver + contract + default
  renderer), then available to **all** clients via config. This is the
  anti-duplication mechanism: novel needs extend the shared catalog; they never
  fork a homepage into a theme.
- Blocks carry stable keys.

## View-model contracts

Canonical, theme-facing DTOs in `src/core/view-models` — the only thing themes
receive. Serializable, presentation-ready, no raw API shapes, no business flags a
theme should not see.

- **Page views:** `HomepageView` (`{ blocks: ResolvedBlock[], seo (internal) }`,
  each `ResolvedBlock` carrying its `state: ok|empty|error`), `ArticleView`,
  `CategoryView`.
- **`CardView` — a discriminated union (not a forced universal card):** a shared
  envelope (`id`, `title`, `url`, `image`, `kind`) plus a **`kind`-specific payload**
  (`article | video | reel | broadcast`) carrying type-specific fields (video
  `duration`, live `status`, reel `aspect`, broadcast `schedule`). Themes implement
  **one card family with per-`kind` variant slots** for type-specific affordances
  (duration badge, LIVE pill) — they do *not* render a single lowest-common-
  denominator card. The unification is the **shared envelope + atoms**, which is what
  reduces surface area; the per-kind variance is explicit, not hidden.
- **Block views:** `HeroView`, `RailView`, `ListView`, `TrendingView`,
  `VideoStripView`, `LiveStripView`, `PollSpotlightView`, etc. (one per block).
- **Atoms:** `MediaRef`, `AuthorRef`, `Breadcrumb`, `Taxonomy`.
- **Rules:** themes use **type-only** imports; mappers live in Core; contracts are
  **compiler-enforced in Phase 1** (formal semver deferred — see
  `frontend-platform.md` §3/§18); contract tests validate mapper output.

## Caching & revalidation

- **Per-block cache** keyed by `(block-type, params, locale)` — the same pattern as
  the backend `publicFeed(locale, kind, limit)` keys.
- Each block entry is **tagged** with the content tags it depends on
  (`article:<id>`, `category:<slug>`, `feed:<kind>:<locale>`, ...); the **composed
  page is tagged with the union** of its blocks' tags.
- **Coherence:** revalidating a content tag busts the affected blocks **and** the
  composed page — no fresh-block/stale-page split. Revalidation is driven by backend
  publish/update events (on-demand `revalidateTag`), with ISR TTL as the safety net.
  Full model: `frontend-platform.md` §12.
- Resolvers are **pure**, so cross-block **coalescing** can be added later without
  touching config or themes. Design for it; do not build it in Phase 1.

## Homepage SEO

- **Identity metadata** (title, description, canonical, OG) is **Core and
  manifest-independent** — derived from site config, never from which blocks happen
  to be present. A config-built homepage never produces thin or duplicated metadata,
  and canonical is stable regardless of cache state.
- **Structured data** (`WebSite`, and optionally `ItemList`) is Core-built and *may*
  reflect the resolved top items so it is rich rather than empty — still Core-owned,
  never theme-owned.

## Anti-patterns

- Page-builder chaos: arbitrary nesting, per-instance code, logic in config.
- A theme fetching block data (instead of using Core data-access hooks).
- Per-client branches inside the orchestrator.
- Solving a new need by copying a homepage into a theme.
- Manifest params that smuggle logic (conditionals, expressions).
- A non-serializable (code-bearing) manifest — breaks the Phase 2 backend migration.
- One failing block blanking the whole page.
- Coupling homepage identity metadata to manifest contents.

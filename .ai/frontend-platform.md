# AlphaCMS Frontend Platform — Canonical Architecture Reference

> Status: **Approved (Phase 1) — v1.0 (post-audit corrections applied)**.
> Authoritative reference for the AlphaCMS white-label public frontend
> (Next.js, App Router).
>
> Companion documents:
> - `.ai/theme-contract.md` — what theme authors may and must never do.
> - `.ai/homepage-architecture.md` — the 4-layer homepage model + block catalog.
> - `.ai/client-onboarding.md` — how to add a new customer deployment.

---

## 1. Architectural vision

The AlphaCMS public frontend is a **single, shared, headless Next.js platform**
sold as a white-label product to many independent customers (target 20+).
Visual identity and content composition vary per customer; **platform behaviour
is identical everywhere**.

The frontend is intentionally **thin**: it orchestrates data from the existing
Laravel headless APIs and delegates presentation to a build-selected theme.
Business correctness (SEO, routing, redirects, tracking, integrations, data
access) lives in Core and in the backend — **never in themes**.

## 2. White-label product model

- **Not runtime multi-tenancy.** Each customer is a separate build + deployment,
  pointed at its own API base URL.
- **Client = Preset + Tokens + Config.** A *preset* is a reusable theme; *tokens*
  rebrand it; *config* composes content and toggles features.
- **Few presets, many clients.** Presets are expensive (build effort in weeks);
  clients are cheap (config in hours). Novel needs **extend the shared catalog**,
  they never fork a client.

## 3. Phase 1 scope and explicit deferrals

Phase 1 is **enterprise-grade boundaries without enterprise ceremony**. Contracts
and separation are real from day one; operational machinery is added only when
real multi-client scale demands it.

**In scope (Phase 1):**
- Single Next.js application with strict internal boundaries (`src/core`,
  `src/themes`, `src/clients`, `src/shared`).
- Core/Theme/Client separation enforced by lint + folder conventions.
- A **Core data-access layer** themes invoke for interactive UX (§11).
- A defined **caching & revalidation architecture** (§12).
- A defined **rendering strategy by route class** (§13).
- **Build-time single-theme resolution** (§14).
- Homepage 4-layer model with manifests living in **client config files**.
- A small, curated block catalog with per-block failure isolation.
- **Compiler-enforced** view-model contracts (formal semver deferred — §3 deferrals).

**Deferred until proven necessary (do NOT build in Phase 1):**
- Monorepo / pnpm workspaces / Turborepo.
- Independent Core package publishing or per-client Core version pinning.
- **Formal contract semver + deprecation windows** (in a single app the compiler
  is the version check; formalise only at the decoupling/monorepo phase).
- Backend-managed homepage composition (admin/editor manifest management).
- Fleet control plane (centralised rollout governance, fleet dashboards).
- Advanced rollout governance (canary orchestration across deployments).

These deferrals are **scheduling decisions, not architectural compromises**. Phase
1 boundaries are designed so each deferred item can be adopted later **without
rewrites** (§17–18).

## 4. Core / Theme / Client separation

- **Core** (`src/core`, `src/app`) — business logic and contracts: API clients,
  the **data-access layer** (theme-invokable hooks/actions), view-model mappers,
  the route tree, the homepage orchestrator, block resolvers, the SEO engine, the
  cache/revalidation layer, all integrations (auth, polls, ads, comments, search,
  analytics, i18n, players, sharing), and the per-build block→renderer binding with
  default fallbacks.
- **Theme / preset** (`src/themes/*`) — presentation only: tokens, presentational
  components, block renderers, page layouts, presentational empty/loading/error
  states. A pure function of `(view-model, tokens) -> markup`, plus calls into the
  Core data-access layer for approved interactive UX.
- **Shared** (`src/shared`) — framework primitives and the design-system substrate:
  UI primitives (Button, Link, Image, Icon), the token schema, shared types. These
  encode accessibility and performance so themes cannot regress them.
- **Client** (`src/clients/*`) — declarative data only: branding, preset selection,
  feature flags, homepage manifest, nav/footer, locales, SEO values, env mapping.
  **No code.**

## 5. Architecture principles

1. Headless Core is thin orchestration: `fetch -> view-model -> metadata -> delegate`.
2. Themes receive **view-models** and **Core data-access hooks** — never API
   responses, endpoints, or HTTP clients.
3. Contracts are **explicit and compiler-enforced** in Phase 1; formal
   semver/deprecation is deferred to the decoupling phase.
4. Config is **declarative**; logic lives in Core.
5. **RSC-first**: data fetching and SEO happen on the server; client JS is minimal.
6. **One route tree, one orchestrator, one resolver set** — shared by all clients.
7. **Request-level fetch deduplication**: data needed by both `generateMetadata`
   and the page component is fetched once (React `cache()` / the framework data
   cache). Never fetch the same entity twice per request.
8. Prove the boundary with a **second real client** before scaling to many.

## 6. Non-negotiable invariants

- SEO output (canonical, hreflang, structured data, sitemap, robots, RSS) is
  **identical-by-construction** regardless of theme.
- Routing contracts (paths, params, redirects via `url_history`) are
  **theme-independent**.
- Article body HTML and embed hydration (polls / ads / media) are **Core** and
  byte-identical across themes.
- Analytics / tracking fire from **Core**, not themes.
- **Themes never construct requests or know endpoints/shapes**; interactive data
  flows through the Core data-access layer (§11).
- **Cache coherence**: a composed/route cache entry is invalidated whenever any
  content it depends on is invalidated (shared tags — §12).
- **Auth-safe caching**: no cacheable response (route or CDN) ever contains
  per-user data; personalization hydrates client-side (§13).
- A failing block or missing renderer **degrades to a Core default / empty state**,
  never blanks the page (§ homepage-architecture).
- A theme can never change *what data is fetched* or *how the platform behaves* —
  only how it looks.

## 7. Forbidden patterns

- Themes **calling HTTP directly** (`fetch`, SDKs) or importing API clients. (They
  invoke Core data-access hooks instead — §11.)
- Themes owning endpoints, API contracts, or business data logic.
- Per-theme or per-client route definitions or middleware.
- Per-client forks of Core or a theme ("just this once").
- Business logic or feature-flag branching inside the orchestrator instead of the
  manifest.
- Logic / conditionals inside config (config that becomes a programming language).
- A generic visual page-builder (arbitrary nesting, per-instance custom code).
- Theme code touching, parsing, or re-rendering article body HTML, or owning embed
  hydration.
- Baking per-user data into a cacheable response.

## 8. Folder architecture (Phase 1 — single application)

A single Next.js app. Boundaries are enforced by convention + lint, not packaging.
The package split (monorepo) is a possible later evolution (§18), not a Phase 1
requirement.

```
src/
  app/                      # CORE-OWNED App Router route tree (the only routes that exist)
                            #   thin segments: fetch -> view-model -> metadata -> delegate
                            #   route-class behaviour per §13
  core/
    api/                    # typed clients to the Laravel headless APIs (Core-internal)
    data-access/            # THEME-INVOKABLE hooks/actions/services (load-more, search,
                            #   filters, live refresh, mutations) — owns endpoints/shapes
    view-models/            # canonical contracts + mappers (API response -> view-model)
    orchestrator/           # homepage / page orchestration + per-block failure isolation
    resolvers/              # one resolver per block type (server-side, pure, cacheable)
    seo/                    # metadata, structured data, sitemap, robots, RSS
    cache/                  # cache tags + revalidation handlers (maps backend events -> revalidateTag)
    integrations/           # auth, polls, ads, comments, search, analytics, i18n,
                            #   players (video/reels/live), sharing
    config/                 # client-config + manifest schema (JSON Schema), validation, loader
    blocks/                 # default (fallback) block renderers
  shared/
    ui/                     # primitives (Button, Link, Image, Icon...) token-driven, a11y/perf encoded
    tokens/                 # design-token schema + base scales
    types/                  # shared types
  themes/
    <preset>/               # renderers + layouts + token overrides + blocks map (one map per preset)
  clients/
    _base/                  # thin shared default config (one level of inheritance only)
    <client-id>/            # branding assets + config + homepage manifest + env mapping (NO code)
```

Notes:
- Route segments in `src/app` are Core; they import from `src/core` and render the
  **build-selected** theme (§14). **Themes are never in `src/app`.**
- `src/themes` and `src/clients` must not import from `src/core/api`. Themes **may**
  import from `src/core/data-access` (hooks/actions) and **type-only** from
  `src/core/view-models`.

## 9. Config vs code

| Configurable (data, in `src/clients/<id>`) | Code (Core, never config) |
|---|---|
| Branding (logo, name, palette/fonts -> token overrides) | Routing, data fetching, redirects |
| Preset selection | SEO engine, metadata, structured data, sitemap/robots/RSS |
| Feature flags (module on/off + licensing tier) | Auth/session, permissions, middleware |
| Homepage manifest (ordered blocks + params) | Analytics / tracking / beacons |
| Nav structure, footer, social handles | Data-access layer, view-model mappers, resolvers, orchestrator |
| Locales + default | Cache/revalidation layer, integrations |
| SEO values (site name, default OG, verification) | New block types, new routes, new integrations |
| Env secrets (API base, analytics/ad IDs) | The block→renderer binding |

**Test:** "what shows where + how it is branded" -> config. "How the platform
behaves" -> code.

## 10. Configuration model

- **Validation gate (mandatory):** all client config + the homepage manifest are
  validated against a **JSON Schema** (`src/core/config`) at build. The build
  **fails** on: invalid config, unknown block keys, out-of-range/unknown params, or
  **dangling references** (a referenced category/source/locale that does not exist).
- **Base config + one-level inheritance:** `src/clients/_base` holds thin platform
  defaults (default flags, nav scaffold, locale behaviour). A client overrides
  specific keys. **Exactly one level** (base → client); no deep inheritance chains
  (that is the config-complexity trap).
- **Locales:** a client's configured locale set **constrains generated routes** —
  the `[locale]` segment accepts only the client's locales; others `notFound()`.
  Default-locale handling is Core middleware.
- **Routes vs feature flags:** routes physically exist (Core), but a **disabled
  module's routes return 404/410** — the segment checks the client's feature flags
  and `notFound()`s when the module is off. Nav never links to disabled modules.

## 11. Data access layer (theme-invokable) — closes audit C1

Themes are **fetch-free but not data-frozen**. Initial render data comes from Core
resolvers (RSC). **Interactive, post-render data and mutations** flow through the
**Core data-access layer** in `src/core/data-access`:

- Core exposes typed, client-safe **hooks / actions / services** that own endpoint
  construction, auth, retries, error handling, and view-model mapping. Examples:
  `useFeedPage(blockKey, cursor)` (load-more / infinite scroll),
  `useSearch(query)`, client-side filtering of already-permitted data,
  `useLiveRefresh(channel)` (live blocks), and mutations `submitVote(...)`,
  `subscribeNewsletter(...)`, `loadMoreComments(cursor)`.
- A theme **calls these functions**; it never builds URLs, knows shapes, or imports
  an HTTP client. Mutations are implemented by Core (Server Actions or client calls
  — Core's choice); the theme only invokes them and renders the result.
- This preserves the invariant ("themes never own data") while enabling standard
  interactive UX. The precise theme-facing rule lives in `theme-contract.md`.

## 12. Caching & revalidation architecture — closes audit C2

Four cache layers, each with an owner; one coherent invalidation model.

| Layer | Owner | Holds | Invalidated by |
|---|---|---|---|
| **L1 Laravel API cache (Redis)** | Backend | API responses (existing `CacheKeys`/`CacheTtl`/tags) | Backend writes via existing tag flush |
| **L2 Next data cache** | Frontend Core | Fetched API responses + per-request dedup (React `cache()`) | Tag-based revalidation + TTL |
| **L3 Next route / ISR cache** | Frontend Core | Rendered route + per-block output | On-demand `revalidateTag` + ISR TTL fallback |
| **L4 CDN** | Edge | Route output at the edge | Honors revalidate / purge from L3 |

**Tagging & coherence.** Frontend cache entries (L2/L3) carry **content tags that
mirror backend tags**: e.g. `article:<id>`, `category:<slug>`, `feed:<kind>:<locale>`,
`home:<locale>`, `poll:<uuid>`. A per-block cache entry is tagged with every content
tag it depends on; the **composed page is tagged with the union** of its blocks'
tags. Revalidating one content tag therefore busts the affected blocks **and** the
page that composed them — no stale-page-with-fresh-block split.

**Revalidation triggers (backend -> frontend).** On a backend write, Laravel emits
an **on-demand revalidation signal** (authenticated webhook) to a Core route handler
that calls `revalidateTag(tag)`. Mapped triggers:

| Event | Tags revalidated | Stale tolerance |
|---|---|---|
| Breaking-news publish | `home:*`, `feed:breaking:*`, `category:<slug>` | Near-zero (on-demand; ISR fallback ~30–60s) |
| Article publish/update | `article:<id>`, `category:<slug>`, `feed:*`, `home:*` | On-demand; ISR fallback ~5 min |
| Poll state change | `poll:<uuid>` (+ host article/home if embedded) | Short (poll results already SHORT TTL) |
| Ad campaign update | none at route layer — ads serve client/edge via the ad subsystem | n/a |
| Sitemap/RSS-affecting publish | `sitemap`, `rss:<locale>` | Long + on publish |

**Rules.** Personalized routes are **never** cached at L3/L4 (§13). If the webhook
is unavailable, ISR TTL is the safety net (content self-heals within the TTL). The
backend remains the single source of freshness; the frontend never guesses
invalidation from its own state.

## 13. Rendering strategy by route class — closes audit C3

| Class | Routes | Strategy | Caching |
|---|---|---|---|
| **A — Public content** | home, category, article, video, reel, live listing/detail | **Static + ISR** with on-demand revalidation (§12); RSC | Cacheable at L3 + CDN; auth state hydrates client-side over the cached shell |
| **B — Personalized / authenticated** | profile, dashboards, notifications, anything per-user | **Dynamic SSR**, `no-store`, auth-gated | Never cached at L3/CDN |
| **C — Interactive public** | search results | **Dynamic** (query-dependent) or short edge-cache by query | Short TTL only; never per-user |

**Auth-safe boundary (mandatory).** A Class-A response is a **cached public shell
with zero per-user content**. All per-user UI (name, avatar, notification count,
bookmark state) is rendered by **client components reading session client-side**
via Core auth hooks, *after* hydration. User identity is never baked into a cacheable
response. This is what lets the platform cache aggressively at 100k concurrent while
still showing logged-in chrome.

## 14. Build-time theme resolution — closes audit C4

There is **no runtime registry that imports all themes**. Each preset exports a
**static block map** (`src/themes/<preset>/blocks`) of `blockKey -> renderer`. At
build, the `CLIENT` env selects the client's preset, and a **build-time module
alias** (e.g. `@active-theme`) resolves to that single preset. Core imports the
active theme's block map through the alias; unknown keys fall back to Core default
renderers (`src/core/blocks`).

Consequences:
- The bundler **tree-shakes to exactly one preset** — no accidental bundling of all
  themes. This reconciles "registry" (now a per-build static binding) with the
  tree-shaking guarantee in `client-onboarding.md`.
- Renderer lookup is static and analyzable (good for RSC/Client boundaries and
  dead-code elimination).

## 15. Boundary enforcement (single-app)

- **Lint import boundaries**: `src/themes/**` and `src/clients/**` may not import
  `src/core/api/**` or any HTTP client. They **may** import `src/core/data-access`
  and **type-only** from `src/core/view-models`. Build fails on violation.
- **Type-only contract imports**: view-model types are isolated from runtime so a
  type import cannot transitively pull in API clients.
- **Folder conventions**: routes only in `src/app`; resolvers only in
  `src/core/resolvers`; renderers only in `src/themes/*`.
- **CODEOWNERS** gates `src/core`, `src/app`, and `src/shared`.
- **Contract tests**: mappers produce valid view-models; renderers are pure
  functions of their view-model.

## 16. Scaling assumptions

- 20+ deployments, each with its own build, host, and API base.
- Phase 1 ships one client (`client-default`) and validates with a second.
- Deferred-until-scale (§3): fleet control plane, Core version pinning, rollout
  governance. Phase 1 relies on a single shared codebase + a parameterised CI build.

## 17. Migration strategy (no big-bang)

| Phase | Action | Exit gate |
|---|---|---|
| 0 | Audit current Next frontend; define contracts on paper | Contracts agreed |
| 1 | Introduce the Core seam in-place (segment -> fetch + view-model + metadata -> delegate); keep one theme; stand up the data-access + cache/revalidation layers | SEO + visual snapshots match production; revalidation verified |
| 2 | Extract tokens + primitives into `src/shared`; current design becomes "Theme Zero" | Pixel-identical output |
| 3 | Add the block catalog + config manifest; reproduce the current homepage 1:1 via config | Homepage parity; manifest schema-valid |
| 4 | Add `CLIENT` build selection + build-time theme alias; current site = `client-default` | One client builds clean; single preset bundled |
| 5 | Onboard client #2 (config + tokens + a few overrides) | Boundary validated by a real second client |
| 6 | Generalise: add presets / blocks as real demand appears | — |

**Guardrail every phase:** SEO snapshot + visual regression + revalidation check per
route per client in CI. Never migrate a route without a passing SEO snapshot.

## 18. Evolution path (adopt later, without rewrites)

- **Monorepo / workspaces** — when Core needs independent versioning across many
  live clients. The `src/core | src/shared | src/themes` split maps directly to
  packages; **formal contract semver/deprecation begins here** (not in Phase 1).
- **Backend-managed manifest** — when editors must recompose homepages without a
  redeploy. The manifest schema (JSON Schema) is **identical**; only the *source*
  moves from `src/clients/<id>` to a Laravel-served document (see
  `homepage-architecture.md`).
- **Fleet control plane + version pinning + rollout governance** — when the number
  of live deployments makes manual oversight unsafe.

The trigger for each is **proven need at scale**, never speculation.

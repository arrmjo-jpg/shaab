# AlphaCMS Theme Author Contract

> Status: **Approved (Phase 1) — v1.0 (post-audit corrections applied)**. Binding
> contract for every theme/preset under `src/themes/*`. See also
> `.ai/frontend-platform.md` and `.ai/homepage-architecture.md`.

A theme is a **presentation package**: `(view-model, tokens) -> markup`, plus calls
into the Core data-access layer for approved interactive UX. If a change could
affect SEO, routing, data correctness, tracking, or security, it is **not** a theme
concern — it belongs to Core.

---

## What a theme receives

- A **view-model** — a typed, serializable, presentation-ready DTO. Never an API
  response, never a raw backend shape.
- A resolved **token set** (from `src/shared/tokens` + the client's overrides).
- Provided **Core business components** (e.g. `PollWidget`, `AdSlot`,
  `CommentThread`, `SearchBox`, players) to place and style.
- **Core data-access hooks / actions** (e.g. `useFeedPage`, `useSearch`,
  `submitVote`, `subscribeNewsletter`) for approved interactive UX.
- **Core i18n string keys** for all user-facing copy.

A theme outputs markup and invokes Core abstractions. Nothing else.

## A theme MAY

- Define and override **design tokens** (color, typography scale, spacing, radius,
  shadow) within the token schema.
- Implement **presentational components**: header, footer, navigation, cards,
  typography, and section / list / article / category layouts.
- Provide **block renderers** keyed to Core block types, consuming the block's
  view-model.
- Provide **presentational empty / loading / error states** for its renderers
  (the visual treatment; the *decision* to show them is Core's — see "Failure &
  empty states").
- Provide an optional **bespoke page layout** that consumes Core view-models
  (it must never refetch data).
- **Invoke Core data-access abstractions** for approved interactive UX (see below).
- **Compose** Core business components as children and **style them** via tokens,
  variants, or provided slots.
- Render user-facing copy via **Core i18n string keys**.
- Decide visual placement, responsive behaviour, animation, and RTL-safe layout.

## A theme MUST NEVER

- **Own endpoints, API contracts, HTTP calls, or business data logic.** Never call
  `fetch`/HTTP directly or import API clients. (Interactive data needs go through
  Core data-access abstractions — see below. Lint-enforced: no import from
  `src/core/api`.)
- Own or implement business logic, or branch on business state.
- Own SEO — no metadata, canonical, hreflang, structured data, or OG generation.
- Own routing — no route/segment/middleware definitions, no redirects.
- Own authentication or session logic.
- Own ads logic — no zone resolution, no click beacons. (Style `AdSlot` only.)
- Own poll logic — no API calls; submit votes only via the Core action. (Style
  `PollWidget` only.)
- Own analytics or tracking — no beacons, no event firing.
- Parse, mutate, or re-render article body HTML, or own embed hydration.
- **Hardcode user-facing copy** — all strings come from Core i18n keys.
- Hold secrets or tokens, or read environment beyond provided token/branding props.
- Reimplement a Core business component (style only).
- Introduce values outside the token schema.

## Interactive data & mutations — the corrected fetch rule (audit C1)

Themes are **fetch-free, not data-frozen**. Initial render data arrives as
view-models (server-resolved). For data that appears **after** render or in
response to user interaction, the theme **calls a Core data-access abstraction** —
it never constructs a request, URL, or knows a shape.

Approved interactive cases (all via Core hooks/actions):
- **Load more / infinite scroll** — e.g. `useFeedPage(blockKey, cursor)`.
- **Client-side filtering / sorting** of already-permitted data.
- **Live refresh** of live blocks — e.g. `useLiveRefresh(channel)`.
- **Mutations** — voting (`submitVote`), newsletter (`subscribeNewsletter`),
  "load more comments", etc.

The rule in one line: **the theme calls a Core function and renders the result; it
never owns the endpoint, the contract, the HTTP call, or the data logic.**

## Failure & empty states (ownership)

- **Core decides** when a block is `ok` / `empty` / `error` and isolates per-block
  resolver failures so one failing block never blanks the page.
- **The theme provides** the *visual* empty / loading / error treatment for its
  renderers. If a theme omits one, Core's default renderer covers it.

## Article body — the highest-stakes rule

The article body is delivered by Core as server-rendered HTML (`content_html`,
including poll `figure[data-poll-uuid]` and ad markers). The theme:

- **May** style the prose container via typography tokens.
- **Must never** parse the HTML, alter it, or mount the embeds.

Poll widgets, ad slots, lazy media, and the signed view-beacon hydrate via **Core
client logic regardless of theme**. If a theme touches body HTML or embed
hydration, polls/ads break per customer.

## i18n / UI strings

All user-facing copy (labels like "Read more", "Trending", "Load more") is rendered
from **Core-provided i18n string keys**, never hardcoded in a theme. This keeps
translation and rebranding Core-controlled and consistent across presets, and keeps
RTL/localization correct. Themes own *layout and styling* of text, not its wording.

## Enforcement (Phase 1, single-app)

- ESLint import-boundary rule: `src/themes/**` cannot import `src/core/api/**` or
  any HTTP client. It **may** import `src/core/data-access` and **type-only** from
  `src/core/view-models`. Build fails on violation.
- Type-only contract imports prevent a view-model type import from transitively
  pulling in runtime/API code.
- Folder conventions: renderers live only in `src/themes/*`; routes only in
  `src/app`.
- CODEOWNERS gates Core, routes, and shared; theme PRs cannot modify them.
- Contract tests assert each renderer is a pure function of its view-model.

## Contract versioning (Phase 1)

In the single-app Phase 1, view-model and block contracts are **compiler-enforced**:
a contract change updates all themes in the same commit and the type-checker catches
breakage. **Formal semver + deprecation windows are deferred** to the
decoupling/monorepo phase (when themes consume Core as independent versions). Core
still provides default renderers so a theme that does not cover a block degrades
gracefully rather than blanking.

## Theme author checklist

- [ ] No imports from `src/core/api` or any fetch/HTTP client; no direct `fetch`.
- [ ] Interactive data + mutations go through Core data-access hooks/actions only.
- [ ] No SEO, routing, auth, analytics, ads, or poll business logic.
- [ ] Every component is a pure function of its view-model + tokens.
- [ ] Article body rendered via the provided prose container; embeds untouched.
- [ ] All user-facing copy comes from Core i18n keys (no hardcoded strings).
- [ ] Presentational empty / loading / error states provided for each renderer.
- [ ] All styling expressed through tokens (no off-system values).
- [ ] Core business components are composed and styled, never reimplemented.
- [ ] A default renderer exists in Core for any block this theme does not cover.
- [ ] RTL (Arabic) verified where the client enables it.

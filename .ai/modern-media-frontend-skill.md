# AlphaCMS — Modern Media Frontend Skill
> STATUS: **APPROVED — Source of Truth for the public-frontend UI layer.**
> Prescriptive target-state. EXTENDS frontend-platform.md; EVOLVES theme-contract.md styling clause
> (resolves the deferred CSS-framework decision). Untouched: backend/APIs/DB, SEO architecture,
> ISR/SSG/CDN, cache-keys, platform-core, model-audit.
> NORTH STAR: AlphaCMS must look and feel like a PREMIUM modern media platform — International-Ready,
> Theme-Agnostic, White-Label-Ready — WITHOUT sacrificing performance, caching, or SEO.

## 0. Governing principles & non-negotiable guardrails
- **Visual quality is a FIRST-CLASS official goal** (§1), reviewed like correctness — not a side effect.
- **International-Ready:** every component/layout works RTL+LTR, any locale, no rebuild.
- **Theme-Agnostic:** zero genre assumption in Core/shared; genre = a PRESET (tokens + renderers).
- **White-Label-Ready:** branding = CSS-var token cascade (base→preset→client); build-time selection.
- **Perf/SEO guardrails (win over looks if ever in conflict):** Next App Router + RSC-first ·
  ISR/SSG/on-demand revalidate · Dynamic only for personalized · CDN/Cloudflare cache ·
  server metadata+JSON-LD · no Core Web Vitals regression.
- Backend/APIs/DB/Core data-access/view-models/SEO/cache UNCHANGED.
- **Dual acceptance:** a slice is DONE only if it is BOTH premium-looking (§1) AND within all guardrails.
  Fast-but-generic = incomplete. Premium-but-slow/SEO-breaking = rejected.

## 1. Visual Quality Standards  (MANDATORY — governing)
**1.1 Mandate.** AlphaCMS MUST present as a premium, modern media platform. It MUST NOT look like:
a traditional WordPress theme · a newspaper template · a traditional account page · a recolored admin panel.
**1.2 North-star references** (hierarchy, layout quality, content presentation, motion, UX, responsive):
Bloomberg · The Verge · Vercel · Linear · Apple Newsroom · 365Scores · ESPN · Medium.
**1.3 Mandatory visual requirements (every relevant surface):**
1) Large, strong Hero sections · 2) Video-first design · 3) Reels-first design · 4) Modern gallery experience ·
5) Premium cards · 6) Rich media layouts · 7) Advanced visual hierarchy · 8) Modern empty states ·
9) Premium status banners · 10) SaaS-grade user dashboard · 11) Professional sidebar · 12) High-end motion ·
13/14/15) Excellent mobile + tablet + desktop (crafted per breakpoint, not merely "responsive").
**1.4 Media-first principle.** The design system is organized around RICH MEDIA content
(Articles/News/Videos/Reels/Galleries/Writers) — content-presentation-first, NOT CRUD-form-first.
**1.5 Data honesty (with analytics-telemetry-skill).** Visual standards define TARGET surfaces; each
surface's data source is resolved per-slice via the Compliance Gate — reuse an existing endpoint or surface
the gap. NEVER fabricate data to fill a premium layout; show honest empty/"not-tracked-yet" states.
**1.6 Review.** Visual quality is a reviewed acceptance criterion: each slice states how it meets the premium
bar (hierarchy, spacing, typography, motion, responsive) alongside its Performance & Caching Impact.

**1.7 Visual Benchmark Gallery (MANDATORY benchmark — every new slice is evaluated against it BEFORE approval).**
The target visual tier per page type. A slice that is architecturally correct but only "average" visually
is INCOMPLETE and must not be approved. (Reference screenshots may be added under
`.ai/reference/frontend/` later; the rubric below is binding now.)
| Page type | Benchmark references | Target qualities a slice MUST hit |
|---|---|---|
| **Homepage** | Bloomberg · The Verge · Apple Newsroom | Editorial hierarchy with a dominant hero + curated zones; generous spacing; premium mixed-media cards; clear sectioning; immediate "premium media" impression. |
| **Hero Slider** | Apple Newsroom · Vercel · 365Scores | Large cinematic, image-forward; gradient/overlay; bold display typography; smooth transform/opacity transitions; LCP-priority image; dir-aware. |
| **Article Page** | Medium · The Verge · Bloomberg | Premium reading: comfortable measure, strong title/lede ramp, rich inline media, generous whitespace, share + related rail, refined motion. |
| **Video Page** | ESPN · 365Scores | Video-first: prominent player, clean metadata, related/up-next rail, refined controls; fast. |
| **Reels Experience** | Instagram Reels · TikTok tier | Full-bleed vertical, immersive, snap scroll, minimal chrome, instant playback, mobile-perfect. |
| **Galleries** | Apple · The Verge galleries | Immersive grid + lightbox, captions, smooth keyboard/swipe nav, premium transitions. |
| **Search** | Linear · Vercel | Fast, clean results, real filters, premium zero/empty states, crisp hierarchy. |
| **User Dashboard** | Linear · Vercel (SaaS) | Welcome hero + KPI cards + activity feed + quick actions + professional sidebar; refined data presentation; modern empty states; NOT a traditional account page. |

## 2. Technology Direction (official)
**Primary Frontend Stack:** Next.js (App Router, RSC-first) · Tailwind CSS (token-bound, logical-properties) ·
shadcn/ui · Radix UI · Framer Motion. **Optional component/effect source (cherry-pick only, perf-budgeted,
RTL-checked, never on a critical/LCP path):** Magic UI · Aceternity UI.

## 3. Frontend Architecture Rules
**Layering:** `core` (data/routing/SEO/cache/i18n — never visual) → `shared/tokens` (token schema incl.
locale fonts, radius, direction-neutral) → `shared/ui` (genre-neutral, dir-aware, locale-agnostic, a11y
primitives via shadcn/Radix) → `themes/<preset>` (genre presentation; composes primitives + Core business
components) → `clients/<id>` (tokens + locales + preset + manifest; no code).
**ALLOWED:** token-bound Tailwind utilities; logical-direction utilities only; shadcn re-tokenized; Radix a11y;
Framer in client islands; Server Components by default; container queries.
**FORBIDDEN:** off-token values; physical-direction utilities for layout (`ml-/mr-/left-/right-/text-left`);
hardcoded user-facing copy (use Core i18n keys); themes fetching/owning SEO/routing/HTTP; per-user data in
cacheable responses; client-only indexable content; genre/locale assumptions in Core/shared; Magic/Aceternity
on a critical/LCP path.
**New PAGE:** Core segment → `fetch → view-model → generateMetadata → delegate to active preset`; ISR default
(Dynamic only if personalized); locale + dir resolved by Core.
**New COMPONENT:** reuse a `shared/ui` primitive (token-driven, dir-aware, locale-agnostic) → preset composes;
Server by default; Radix for interactive a11y; Framer island for motion; verify RTL+LTR + premium bar.

## 4. Design Language (premium, direction- & locale-aware)
**Token schema (CSS-vars — branding + i18n surface):** color (`bg, surface, surface-2, fg, muted, border,
primary, primary-fg` + semantic `success/warning/danger/info`) · locale-scoped font tokens (Arabic family for
`ar`, Latin for `en`) + shared fluid type scale (`display, h1–h6, body, sm, caption`) + weights/line-heights ·
spacing (4/8) · radius (`none/sm/md/lg/full` token) · elevation (shadow scale) · motion (duration/easing) ·
breakpoints.
**Rules:** logical properties/utilities ONLY (`ms/me, ps/pe, start/end, text-start/end, border-s/e,
rounded-s/e`); `rtl:`/`ltr:` variants for genuine direction-specific visuals only; `<html dir/lang>` from
locale; copy from Core i18n; `Intl` for dates/numbers. 12-col grid + max-width container + container queries.
Strong hierarchy via the scale. Premium cards (variants), LCP-aware heroes, motion = transform/opacity +
reduced-motion, branded empty states (Core decides, theme styles).

## 5. Component Standards (canonical, genre-neutral, dir-aware, premium)
The ONLY approved blocks; extend via variants/presets, never fork. Acceptance: premium visual bar + RTL+LTR +
locale-agnostic + token-only. Hero (lead/grid/slider) · NewsCard/VideoCard/ReelCard/GalleryCard (variants) ·
KPICard · DashboardWidget · StatusBanner · QuickActionTile · Sidebar · EmptyState · Search
(Input/Filters/Results/Empty). Layer: shared/ui primitives + theme compositions. Server by default;
players/sliders/forms/motion = islands.

## 6. Dashboard Standards (SaaS-grade)  (MANDATORY)
The User Dashboard MUST be a SaaS-grade experience — NOT a traditional account page. ONE state-aware dashboard
(User / Pending-writer / Approved-writer) via `is_writer` + writer-request state. Target surfaces (data-source
gated per §1.5): Welcome Hero · KPI Cards · Quick Actions · Activity Feed · Recent Content · Writer Status ·
Upgrade Requests · Notifications · Media Summary · Professional Sidebar. Premium hierarchy/motion/responsive.
Personalized ⇒ Dynamic/no-store (never CDN-cached; no per-user data in shared cache).

## 7. Performance Rules
ISR for public content (home 300s · videos/playlists 120s · reels/latest 60s · articles/categories on-demand);
SSG for static/config; Dynamic ONLY for personalized (`force-dynamic`, `no-store`). Tailwind = build-time
purged CSS, ZERO runtime JS. Framer = client islands only, `next/dynamic` lazy, transform/opacity,
reduced-motion, never gate LCP. Hydration minimal (server-default + small islands). Images lazy (priority for
LCP) + reserved dims (CLS); below-fold islands dynamic-imported. Per-route bundle budget, audited. CWV gate per
slice (LCP/CLS/INP + ISR/SSG/CDN impact) — regression blocks.

## 8. White-Label & Multi-Brand Rules
Branding = CSS-var tokens (base→preset→client); Tailwind utilities + shadcn themed via the SAME tokens; rebrand
= override vars only; arbitrary values forbidden. A client selects a preset (genre theme) + token overrides +
locales + manifest. Presets reusable (few presets, many clients). Build-time single (client, preset,
locale-set); no runtime switching; no imported hardcoded design language.

## 9. Internationalization & Theme Evolution
**Direction:** `<html dir>` from locale (Core); logical properties/utilities exclusively; `rtl:`/`ltr:` only
for true direction-specific visuals. **Locale & copy:** all strings via Core i18n keys (no hardcoded text);
`<html lang>`; `Intl` formatting. Today single-locale-per-build (`config.defaultLocale`, locale-less URLs);
open to multi-locale (`[locale]` segment ⇒ per-locale ISR, or per-locale builds) with NO UI change.
**Typography:** font families are locale-scoped tokens; adding English = set Latin font token + strings, no
rebuild. **Themes/genres:** genre only in a PRESET; Core/shared genre-neutral; future News/Sports/Magazine/
Financial/Corporate = new presets via `@active-theme` + tokens. **Anti-lock-in:** direction/locale/copy/fonts/
genre are CONFIG (tokens/locale/preset), never hardcoded. **Perf invariance:** locale/theme resolved at
Core/build-time; content always server-rendered; ISR/SSG/CDN/SEO/CWV preserved.

## 10. Migration Strategy (UI-layer re-skin, phased — not a rewrite)
KEEP: all `src/core` + backend/APIs/DB + token cascade & build-time selection (EXPAND schema). ADD: Tailwind +
PostCSS + logical/RTL config; `shared/ui` primitives (shadcn/Radix, dir-aware, premium); expanded tokens;
Framer. REBUILD (UI only): `themes/base/blocks/*` on Tailwind + primitives, slice-by-slice; retire `acm-*`
progressively. DELETE (after migrate+verify): `ThemeStyles.tsx` `acm-*` monolith + ad-hoc inline styles.
SEQUENCE: Phase 0 foundation (tokens+locale-fonts + Tailwind/RTL + first primitives) → Phase 1 dashboard
(SaaS-grade) → Phase 2 editorial (Home/Hero/cards) → Phase 3 video/reels/galleries → Phase 4 search/writers →
Phase 5 cleanup. Each phase: per-slice gates (tsc/build + RTL+LTR + visual-bar §1.7 + CWV/SEO/cache) + STOP.
UI-layer-only; no big-bang.

## 11. Compliance gate hooks
Every future frontend slice opens with the Skill Compliance Gate citing THIS skill + frontend-platform +
theme-contract, and includes BOTH a **Visual Quality** statement (§1 + benchmark §1.7) and a
**Performance & Caching Impact** statement, asserting RTL+LTR + locale-agnostic + token-only, before any code.

*Source of Truth for the AlphaCMS public-frontend UI. Evolve deliberately; keep in sync with the codebase.*

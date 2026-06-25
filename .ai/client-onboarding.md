# AlphaCMS — Client Onboarding (Adding a New Customer)

> Status: **Approved (Phase 1) — v1.0 (post-audit corrections applied)**. See also
> `.ai/frontend-platform.md`, `.ai/theme-contract.md`,
> `.ai/homepage-architecture.md`.

Onboarding a customer is **config + branding + manifest** — never feature work. If
onboarding requires Core or theme code changes, the boundary was violated (the
correct response is to add the missing capability to the **shared** catalog / Core
once, then configure it).

---

## Principle

> A new client = new config, new branding, new theme selection.
> Not a frontend feature rewrite.

## Client folder layout

Everything for a client lives under one folder, with **no code**:

```
src/clients/
  _base/                # thin shared default config (flags, nav scaffold, locale behaviour)
  <client-id>/
    config              # name, preset selection, feature flags, locales, nav, footer, social, SEO values
                        #   (overrides _base; exactly one inheritance level)
    branding/           # logo(s), favicon, default OG image, fonts (or font references)
    tokens              # token overrides (palette, typography, spacing) within the schema
    homepage            # the homepage manifest — JSON-serializable data over the catalog (Phase 1 source of truth)
    env.example         # the environment variables this client requires (documented, not secret values)
```

Inheritance is **one level only**: `_base` -> `<client-id>`. No deep chains.

## Steps

1. **Create `src/clients/<client-id>/`** — config, branding assets, token
   overrides, homepage manifest, env mapping. No code.
2. **Branding** — name, logo(s), favicon, default OG image; palette and fonts as
   **token overrides** within the token schema.
3. **Preset selection** — choose one **existing** preset. Presets are built **on
   demand**, not all up front; a new preset is created only when visual divergence
   genuinely cannot be expressed via tokens — a deliberate platform decision, not an
   onboarding step.
4. **Feature flags** — enable/disable modules (ads, polls, reels, live, comments,
   search, newsletter) per the customer's licensing tier. Disabled modules: their
   **routes return 404/410** and nav never links to them (Core-enforced).
5. **Homepage manifest** — declare the ordered blocks + params, using the **catalog
   only** (`homepage-architecture.md`), as **JSON-serializable data**. Phase 1: the
   config file above. Phase 2+: editors manage it in the admin (same schema).
6. **Navigation & footer** — structure, links, social handles (config).
7. **Locales** — locale set + default. The locale set **constrains generated
   routes** (only configured locales resolve; others 404). Verify RTL where Arabic
   is enabled.
8. **Validate (mandatory gate)** — config + manifest are validated against the
   JSON Schema at build. The build **fails** on invalid config, unknown block keys,
   out-of-range params, or **dangling references** (missing category/source/locale).
9. **Environment variables** — `CLIENT=<client-id>`, `API_BASE_URL` (this customer's
   Laravel API), the **revalidation webhook secret** (so the backend can trigger
   Next revalidation — see `frontend-platform.md` §12), analytics/ad IDs, CDN config.
   Secrets come from the deployment environment and are **never committed**.
10. **SEO values** — site name, default OG image, search-console verification token.

## Build process (Phase 1)

- Single Next.js application; build-time client selection:
  `CLIENT=<client-id> next build`.
- The build resolves the client's config + selected preset + token overrides +
  homepage manifest, binds the **single active theme** via the build-time alias
  (`@active-theme` -> the selected preset — `frontend-platform.md` §14), and
  **tree-shakes to that one preset**. No accidental bundling of other themes.
- The config/manifest **validation gate** (step 8) runs as part of the build.
- One shared CI pipeline, parameterised by `CLIENT`; **N deploy targets**.
- No workspaces, no per-client package versioning in Phase 1 (deferred — see
  `frontend-platform.md` §3 / §18).

## Rendering & caching expectations (per route class)

- **Public content routes** (home, category, article, video, reel, live) are
  static + ISR, cached at L3/CDN, revalidated on backend publish/update via the
  webhook. Logged-in chrome hydrates **client-side** over the cached public shell.
- **Personalized routes** (profile, dashboards, notifications) are dynamic SSR,
  `no-store`, never cached. (See `frontend-platform.md` §13.)

## Preview / draft (Phase 1, minimal)

A preview path lets editors/stakeholders see **unpublished content in this client's
theme** before publish: a Core-owned preview mode (e.g. a draft token / preview
route) renders draft content through the same resolvers + selected theme, **bypassing
the public cache** and excluded from indexing. Phase 1 keeps this minimal; the
authoring UX expands when the manifest becomes backend-managed.

## Deployment expectations

- Separate host / project per customer (its own deployment), pointed at its own
  `API_BASE_URL` and CDN, with the revalidation webhook wired back from Laravel.
- **Phase 1 observability (minimal):** per-deployment error tracking and a build-time
  SEO snapshot + visual regression + ar/en (RTL) render check before go-live.
- **Deferred until multi-client scale:** fleet dashboards, centralised rollout
  governance, Core version pinning.

## Definition of done (a clean onboarding)

- No Core or theme code changed.
- Only `src/clients/<client-id>/` (+ deployment env) added.
- Config + manifest **pass schema validation** (no dangling references).
- SEO snapshot, visual regression, and RTL checks are green.
- Revalidation webhook verified (a backend publish busts the relevant routes).
- Preview verified (draft content renders in the client theme, uncached/noindex).
- The homepage renders entirely from the manifest + catalog (no bespoke code).

## What is explicitly NOT part of onboarding

- Editing routes, SEO, analytics, data-access, or any integration logic.
- Adding a new block type or a new preset (those are shared Core/preset changes,
  done once, then configured here).
- Forking Core or a theme for this client (forbidden — see
  `frontend-platform.md` §7).

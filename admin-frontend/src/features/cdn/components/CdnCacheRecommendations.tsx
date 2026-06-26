import { useState } from 'react';
import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
  ServerCog,
  Table2,
  LayoutTemplate,
  SlidersHorizontal,
  Gauge,
  Database,
  ListChecks,
  ListOrdered,
  ListTodo,
  FileCode2,
  Braces,
  Copy,
  Check,
  Ban,
  Infinity as InfinityIcon,
  ClipboardCheck,
  AlertTriangle,
  Square,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

// بطاقة مرجعيّة بحتة (لا حالة كاش/حقول/API/منطق جديد): دليل إعداد Cloudflare تشغيليّ مبنيّ على مسارات
// AlphaCMS الفعليّة (صفحات الواجهة + BFF + المصادقة + الوسائط) — لا أمثلة عامّة. المحتوى التقنيّ إنجليزيّ.

type Status = 'cache' | 'short' | 'immutable' | 'bypass' | 'mixed';

const STATUS_STYLE: Record<Status, string> = {
  cache: 'bg-emerald-500/10 text-emerald-500',
  short: 'bg-amber-500/10 text-amber-500',
  immutable: 'bg-sky-500/10 text-sky-500',
  bypass: 'bg-destructive/10 text-destructive',
  mixed: 'bg-muted text-muted-foreground',
};
const STATUS_LABEL: Record<Status, string> = {
  cache: 'Cacheable',
  short: 'Short + SWR',
  immutable: 'Immutable',
  bypass: 'Bypass',
  mixed: 'Selective',
};

type Risk = 'High' | 'Medium' | 'Low';
const RISK_STYLE: Record<Risk, string> = {
  High: 'bg-destructive/10 text-destructive',
  Medium: 'bg-amber-500/10 text-amber-500',
  Low: 'bg-emerald-500/10 text-emerald-500',
};

// ── Cloudflare Cache Matrix ─────────────────────────────────────────────────────────────────
interface MatrixRow {
  type: string;
  rule: string;
  browser: string;
  edge: string;
  status: Status;
  purge: string;
  plan: string;
  recFor: string;
  notes: string;
}
const MATRIX: MatrixRow[] = [
  { type: 'Homepage', rule: 'Eligible', browser: '0', edge: '60s + SWR', status: 'short', purge: 'Revalidate + Purge URL', plan: 'Free+ ¹', recFor: 'News, Sports', notes: 'HTML not cached by default.' },
  { type: 'Section / Category', rule: 'Eligible', browser: '0', edge: '300s + SWR', status: 'short', purge: 'Cache Tag + URL', plan: 'Free+ ¹', recFor: 'News, Magazine', notes: 'Aligns with ISR 300.' },
  { type: 'Article pages', rule: 'Eligible', browser: '0', edge: '300s + SWR', status: 'cache', purge: 'Cache Tag / URL', plan: 'Free+ ²', recFor: 'News, Magazine', notes: 'Instant purge on edit/unpublish.' },
  { type: 'Breaking / Live', rule: 'Eligible', browser: '0', edge: '15–30s + SWR', status: 'short', purge: 'Self-expire', plan: 'Business+', recFor: 'News, Sports', notes: 'Sub-minute TTL needs Business+.' },
  { type: 'Tag / Author', rule: 'Eligible', browser: '0', edge: '300–1800s + SWR', status: 'short', purge: 'Cache Tag', plan: 'Free+ ¹ ²', recFor: 'News, Magazine', notes: 'Updates on related publish.' },
  { type: 'Search', rule: 'Bypass', browser: '0', edge: '0', status: 'bypass', purge: 'N/A', plan: 'All', recFor: 'All', notes: 'High-cardinality query fragments key.' },
  { type: 'RSS / Sitemap', rule: 'Respect origin', browser: '5m / 0', edge: '300s – 6h', status: 'cache', purge: 'On publish', plan: 'Free+', recFor: 'All', notes: 'XML needs a rule.' },
  { type: 'robots.txt / manifest', rule: 'Eligible', browser: '1d', edge: '1d', status: 'cache', purge: 'Purge URL', plan: 'All', recFor: 'All', notes: 'JSON not default-cached → rule.' },
  { type: 'Public API (GET JSON)', rule: 'Respect origin', browser: 'per origin', edge: '60–900s', status: 'mixed', purge: 'Self-expire', plan: 'Free+', recFor: 'All', notes: 'Idempotent GET only; rest bypass.' },
  { type: 'Admin / Auth / Dashboard', rule: 'Bypass', browser: '0', edge: '0', status: 'bypass', purge: 'N/A', plan: 'All', recFor: 'All', notes: 'Bypass on the session cookie.' },
  { type: 'Images', rule: 'Cache (default)', browser: '1y', edge: '1y', status: 'cache', purge: 'URL Versioning', plan: 'All ³', recFor: 'All', notes: 'Cache Reserve = paid (TTL ≥ 10h).' },
  { type: 'Image conversions / WebP', rule: 'Cache (default)', browser: '1y', edge: '1y', status: 'immutable', purge: 'URL Versioning', plan: 'All', recFor: 'All', notes: 'Derived per asset id → immutable.' },
  { type: 'Video files (.mp4)', rule: 'Cache (default)', browser: '1d', edge: '30d', status: 'cache', purge: 'Versioned Paths', plan: 'All ³', recFor: 'Video Platform', notes: '+ Cache Reserve + Tiered Cache.' },
  { type: 'HLS playlists (.m3u8)', rule: 'Eligible', browser: '0', edge: 'Live 1–3s / VOD 60s', status: 'short', purge: 'Self-expire', plan: 'Business+', recFor: 'Video Platform', notes: '.m3u8 not default-cached → rule.' },
  { type: 'Video segments (.ts)', rule: 'Cache', browser: '1y', edge: '1y', status: 'immutable', purge: 'Versioned Paths', plan: 'All ³', recFor: 'Video Platform', notes: 'Immutable once written.' },
  { type: 'CSS / JS (hashed)', rule: 'Cache (default)', browser: '1y', edge: '1y', status: 'immutable', purge: 'URL Versioning', plan: 'All', recFor: 'All', notes: 'Content-hashed filenames.' },
  { type: 'Fonts', rule: 'Cache (default)', browser: '1y', edge: '1y', status: 'immutable', purge: 'None', plan: 'All', recFor: 'All', notes: 'Immutable.' },
  { type: 'Ad endpoints', rule: 'Respect origin', browser: '0', edge: '30s', status: 'short', purge: 'Self-expire', plan: 'Business+ ⁴', recFor: 'All', notes: 'Beacons = no-store.' },
  { type: 'Widgets / fragments', rule: 'Eligible / Bypass', browser: '0', edge: '30–300s', status: 'mixed', purge: 'Cache Tag', plan: 'Free+', recFor: 'All', notes: 'Bypass if personalized.' },
];

// ── Cloudflare Rules Reference — قواعد AlphaCMS الفعليّة (مسارات حقيقيّة فقط) ─────────────────
interface RuleRef {
  name: string;
  expr: string;
  action: string;
  browser: string;
  edge: string;
  purge: string;
  module: string;
  risk: Risk;
  why: string;
  notes: string;
}
const RULES_REF: RuleRef[] = [
  { name: 'User Account Bypass', expr: '(starts_with(http.request.uri.path, "/account"))', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'Account (per-user)', risk: 'High', why: 'Per-user pages render with force-dynamic; caching would serve one reader another reader’s data.', notes: '' },
  { name: 'Auth Pages Bypass', expr: '(http.request.uri.path in {"/login" "/register" "/forgot-password"})', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'Auth', risk: 'High', why: 'Login / register / reset carry CSRF tokens and set session cookies.', notes: '' },
  { name: 'Following Page Bypass', expr: '(http.request.uri.path eq "/sport/following")', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'Frontend (per-user)', risk: 'High', why: 'Personalized follow feed (force-dynamic).', notes: 'Place above the Sport cache rule.' },
  { name: 'Ad Serve (Respect Origin)', expr: '(starts_with(http.request.uri.path, "/api/ads/serve"))', action: 'Respect Origin', browser: '0', edge: '30s', purge: 'Self-expire', module: 'Ads BFF', risk: 'Medium', why: 'Origin sends max-age=30; the impression token is valid for its 30s bucket, so edge-caching is safe.', notes: 'Place above the API bypass; normalize the cache key (V9).' },
  { name: 'Weather API (Respect Origin)', expr: '(http.request.uri.path eq "/api/weather")', action: 'Respect Origin', browser: '15m', edge: '900s', purge: 'Self-expire', module: 'BFF', risk: 'Low', why: 'Public weather JSON already sends max-age=900 + SWR.', notes: 'Place above the API bypass.' },
  { name: 'API Bypass', expr: '(starts_with(http.request.uri.path, "/api"))', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'BFF (per-user / mutation)', risk: 'High', why: 'The BFF is mostly no-store: auth, account, follow, engagement, comments, media status, beacons, revalidate webhook.', notes: 'Catches everything not matched by the two rules above.' },
  { name: 'Static Assets (immutable)', expr: '(starts_with(http.request.uri.path, "/_next/static") or starts_with(http.request.uri.path, "/build/"))', action: 'Cache Everything', browser: '1y', edge: '1y', purge: 'Versioned (none)', module: 'Static', risk: 'Low', why: 'Content-hashed Next.js chunks + the epaper reader build assets are immutable.', notes: '' },
  { name: 'Homepage Cache', expr: '(http.request.uri.path eq "/")', action: 'Eligible for Cache', browser: '0', edge: '60s', purge: 'Revalidate + Purge URL', module: 'Frontend (ISR 3600)', risk: 'Medium', why: 'Highest-traffic page; a short edge TTL + SWR absorbs spikes without hitting the origin.', notes: '60s edge needs Business+ (Free floor 2h).' },
  { name: 'Article Pages Cache', expr: '(starts_with(http.request.uri.path, "/articles/"))', action: 'Eligible for Cache', browser: '0', edge: '300s', purge: 'Cache Tag / URL on edit', module: 'Frontend (ISR 21600)', risk: 'Medium', why: 'Real article route /articles/[idslug]; cache hard, purge instantly on correction.', notes: '' },
  { name: 'Category Pages Cache', expr: '(starts_with(http.request.uri.path, "/category/"))', action: 'Eligible for Cache', browser: '0', edge: '300s', purge: 'Cache Tag on publish', module: 'Frontend (ISR 21600)', risk: 'Low', why: 'Section listings /category/[slug].', notes: '' },
  { name: 'Author Pages Cache', expr: '(starts_with(http.request.uri.path, "/writer/"))', action: 'Eligible for Cache', browser: '0', edge: '600s', purge: 'Cache Tag on publish', module: 'Frontend (ISR 21600)', risk: 'Low', why: '/writer/[id] is a PUBLIC author profile (cacheable); the writer dashboard is on the admin subdomain, not here.', notes: '' },
  { name: 'Live / TV / Radio Cache', expr: '(starts_with(http.request.uri.path, "/live") or starts_with(http.request.uri.path, "/tv/") or starts_with(http.request.uri.path, "/radio/"))', action: 'Eligible for Cache', browser: '0', edge: '30s', purge: 'Self-expire', module: 'Frontend (ISR 30)', risk: 'Medium', why: 'Live coverage refreshes every ~30s (ISR 30).', notes: 'Sub-minute TTL needs Business+.' },
  { name: 'Sport Cache', expr: '(starts_with(http.request.uri.path, "/sport"))', action: 'Eligible for Cache', browser: '0', edge: '120s', purge: 'Cache Tag', module: 'Frontend', risk: 'Low', why: 'Sport hub + competition / team / player / match pages.', notes: '/sport/following is bypassed above.' },
  { name: 'Video / Reels Cache', expr: '(starts_with(http.request.uri.path, "/videos") or starts_with(http.request.uri.path, "/reels"))', action: 'Eligible for Cache', browser: '0', edge: '600s', purge: 'Cache Tag', module: 'Frontend (ISR 21600)', risk: 'Low', why: 'Video + reels listings and detail pages.', notes: '' },
  { name: 'Section Pages Cache', expr: '(http.request.uri.path in {"/latest" "/trending" "/economy" "/bourse" "/gold-prices" "/weather"})', action: 'Eligible for Cache', browser: '0', edge: '300s', purge: 'Self-expire', module: 'Frontend (ISR 300)', risk: 'Low', why: 'Data / section pages refreshed every 5 min (ISR 300).', notes: '' },
  { name: 'Static Pages Cache', expr: '(starts_with(http.request.uri.path, "/pages/"))', action: 'Eligible for Cache', browser: '1h', edge: '1d', purge: 'Purge on edit', module: 'Frontend (ISR 86400)', risk: 'Low', why: 'CMS static pages (about, policies) change rarely.', notes: '' },
  { name: 'Search Bypass', expr: '(starts_with(http.request.uri.path, "/search"))', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'Frontend', risk: 'Low', why: 'Query-driven; the high-cardinality "q" param fragments the cache.', notes: 'Or cache 60s if search traffic is heavy.' },
  { name: 'Feeds & Metadata Cache', expr: '(http.request.uri.path in {"/rss.xml" "/sitemap.xml" "/robots.txt" "/manifest.webmanifest"})', action: 'Eligible for Cache', browser: '1d', edge: '1h', purge: 'On publish / deploy', module: 'Frontend (metadata)', risk: 'Low', why: 'Generated feed + metadata routes; change infrequently.', notes: 'RSS origin sends s-maxage=300.' },
  { name: 'Epaper (Blade rewrite)', expr: '(http.request.uri.path contains "/epaper")', action: 'Eligible for Cache', browser: '0', edge: '300s', purge: 'On publish', module: 'Backend Blade (rewrite)', risk: 'Low', why: '/:locale/epaper rewrites to the Laravel reader; the /epaper landing is a Next page.', notes: '' },
  { name: 'Media Origin (images)', expr: '(starts_with(http.request.uri.path, "/uploads/") or starts_with(http.request.uri.path, "/storage/"))', action: 'Cache Everything', browser: '1y', edge: '1y', purge: 'URL versioning', module: 'Backend media (Laravel)', risk: 'Low', why: 'Uploaded assets, conversions and branding are immutable per asset id.', notes: 'Applies to the media / backend origin, not the Next zone.' },
  { name: 'Admin Subdomain Bypass', expr: '(http.host eq "admin.<your-domain>")', action: 'Bypass Cache', browser: '0', edge: '0', purge: 'N/A', module: 'Admin SPA (subdomain)', risk: 'High', why: 'The admin SPA + admin API are private and live on their own subdomain (router root "/"), not /admin.', notes: 'Set your real admin host. Optionally cache only its hashed /assets/.' },
];

// ── AlphaCMS Recommended Rule Order — الترتيب النهائيّ (Cloudflare يُقيّم من الأعلى للأسفل) ─────
interface OrderRow {
  n: number;
  rule: string;
  why: string;
  breaks: string;
}
const RULE_ORDER: OrderRow[] = [
  { n: 1, rule: 'User Account Bypass', why: 'Per-user content must be excluded before any cache rule can match it.', breaks: 'A later HTML cache rule marks /account eligible → one reader’s account is cached and served to others (data leak).' },
  { n: 2, rule: 'Auth Pages Bypass', why: 'Credential pages must never be cached; grouped at the top with the other bypasses.', breaks: 'Cached /login or /register serve stale CSRF tokens and can leak session state.' },
  { n: 3, rule: 'Following Page Bypass', why: '/sport/following is personalized and would be caught by the broad Sport cache rule.', breaks: 'The Sport cache rule caches one user’s follow feed and serves it to everyone.' },
  { n: 4, rule: 'Ad Serve (Respect Origin)', why: 'Cacheable exception that must sit above the broad API bypass.', breaks: 'The API bypass swallows /api/ads/serve → ads never edge-cache; every impression hits the origin.' },
  { n: 5, rule: 'Weather API (Respect Origin)', why: 'Cacheable exception placed above the API bypass.', breaks: 'The API bypass catches it → weather JSON is re-fetched from the origin on every request.' },
  { n: 6, rule: 'API Bypass', why: 'Broad catch-all for the no-store BFF — placed AFTER its cacheable exceptions.', breaks: 'If moved above #4–#5, it bypasses ads and weather too.' },
  { n: 7, rule: 'Static Assets', why: 'Specific /_next/static + /build, placed before broad HTML so they keep the 1-year TTL.', breaks: 'A broad HTML rule applies a 60s TTL to hashed assets — wasting the immutable cache.' },
  { n: 8, rule: 'HTML Page Cache', why: 'After all bypasses, so per-user / auth pages are already excluded.', breaks: 'If moved above the bypasses, it caches /account, /login and /sport/following.' },
  { n: 9, rule: 'Feeds & Metadata', why: 'Exact-match paths (/rss.xml, /sitemap.xml…); order-tolerant, kept after HTML.', breaks: 'Negligible — exact paths rarely collide with other rules.' },
  { n: 10, rule: 'Media Origin (images)', why: 'Separate backend / media origin; ordered within that zone.', breaks: 'N/A on the Next zone (different origin).' },
  { n: 11, rule: 'Admin Subdomain Bypass', why: 'Host-scoped (admin.<domain>); evaluated independently of the path rules.', breaks: 'N/A — matches by host, not path.' },
];

// ── Cloudflare Setup Checklist — مهامّ الإعداد والتشغيل ───────────────────────────────────────
const SETUP: { item: string; hint: string }[] = [
  { item: 'Create Bypass Rules', hint: '/account, /login, /register, /forgot-password, /sport/following, /api.' },
  { item: 'Create Cache Rules', hint: 'home, /articles/, /category/, /writer/, /live, /sport, /videos, /reels, sections, /pages/.' },
  { item: 'Create Media Rules', hint: '/uploads, /storage on the media origin → 1-year immutable.' },
  { item: 'Create RSS Rules', hint: '/rss.xml → 300s, respect origin.' },
  { item: 'Create Sitemap Rules', hint: '/sitemap.xml → 1-hour edge TTL.' },
  { item: 'Create Asset Rules', hint: '/_next/static, /build → 1-year immutable.' },
  { item: 'Enable Tiered Cache', hint: 'Smart Tiered Cache for origin shielding.' },
  { item: 'Enable Cache Reserve', hint: 'If media-heavy (TTL ≥ 10h; paid add-on).' },
  { item: 'Enable Compression Rules', hint: 'Brotli is on by default — tune via Compression Rules.' },
  { item: 'Test CF-Cache-Status', hint: 'curl -I a URL → expect HIT / MISS / DYNAMIC / BYPASS as designed.' },
];

// ── Copy Ready Expressions — تعابير AlphaCMS النهائيّة جاهزة للنسخ ─────────────────────────────
interface ExprSnippet {
  label: string;
  expr: string;
}
const EXPRESSIONS: ExprSnippet[] = [
  { label: 'User Account Bypass', expr: '(starts_with(http.request.uri.path, "/account"))' },
  { label: 'Auth Pages Bypass', expr: '(http.request.uri.path in {"/login" "/register" "/forgot-password"})' },
  { label: 'Following Page Bypass', expr: '(http.request.uri.path eq "/sport/following")' },
  { label: 'Ad Serve (Respect Origin)', expr: '(starts_with(http.request.uri.path, "/api/ads/serve"))' },
  { label: 'Weather API (Respect Origin)', expr: '(http.request.uri.path eq "/api/weather")' },
  { label: 'API Bypass', expr: '(starts_with(http.request.uri.path, "/api"))' },
  { label: 'Static Assets', expr: '(starts_with(http.request.uri.path, "/_next/static") or starts_with(http.request.uri.path, "/build/"))' },
  { label: 'Homepage Cache', expr: '(http.request.uri.path eq "/")' },
  { label: 'Article Pages Cache', expr: '(starts_with(http.request.uri.path, "/articles/"))' },
  { label: 'Category Pages Cache', expr: '(starts_with(http.request.uri.path, "/category/"))' },
  { label: 'Author Pages Cache', expr: '(starts_with(http.request.uri.path, "/writer/"))' },
  { label: 'Live / TV / Radio Cache', expr: '(starts_with(http.request.uri.path, "/live") or starts_with(http.request.uri.path, "/tv/") or starts_with(http.request.uri.path, "/radio/"))' },
  { label: 'Sport Cache', expr: '(starts_with(http.request.uri.path, "/sport"))' },
  { label: 'Video / Reels Cache', expr: '(starts_with(http.request.uri.path, "/videos") or starts_with(http.request.uri.path, "/reels"))' },
  { label: 'Section Pages Cache', expr: '(http.request.uri.path in {"/latest" "/trending" "/economy" "/bourse" "/gold-prices" "/weather"})' },
  { label: 'Static Pages Cache', expr: '(starts_with(http.request.uri.path, "/pages/"))' },
  { label: 'Feeds & Metadata Cache', expr: '(http.request.uri.path in {"/rss.xml" "/sitemap.xml" "/robots.txt" "/manifest.webmanifest"})' },
  { label: 'Media Origin (images)', expr: '(starts_with(http.request.uri.path, "/uploads/") or starts_with(http.request.uri.path, "/storage/"))' },
];

// ── AlphaCMS Presets ────────────────────────────────────────────────────────────────────────
interface Preset {
  name: string;
  home: string;
  article: string;
  image: string;
  api: string;
  purge: string;
}
const PRESETS: Preset[] = [
  { name: 'News Website', home: '30–60s', article: '300s', image: '1 year', api: 'Short / bypass', purge: 'Auto-purge (Tag + URL) on publish' },
  { name: 'Sports Website', home: '15–30s', article: '60–120s', image: '1 year', api: 'Short (live 10–30s)', purge: 'Cache Tag + frequent (live scores)' },
  { name: 'Magazine', home: '300s', article: '1800s', image: '1 year', api: 'Respect origin', purge: 'Cache Tag on edit (evergreen)' },
  { name: 'Video Platform', home: '60s', article: '300s', image: '1 year + Reserve', api: 'Short', purge: 'Versioned paths + Cache Reserve' },
];

// ── Recommended Cloudflare Configuration ────────────────────────────────────────────────────
const CONFIG: { setting: string; value: string; reason: string }[] = [
  { setting: 'SSL/TLS mode', value: 'Full (Strict)', reason: 'End-to-end TLS to a valid origin certificate; blocks downgrade / MITM.' },
  { setting: 'Always Use HTTPS', value: 'Enabled', reason: 'Redirect all HTTP requests to HTTPS.' },
  { setting: 'HTTP/3 (with QUIC)', value: 'Enabled', reason: 'Lower-latency connections, especially on mobile.' },
  { setting: 'Brotli', value: 'Default-on (no toggle)', reason: 'Toggle removed 2024-06-14; Brotli is on by default — tune via Compression Rules.' },
  { setting: 'Tiered Cache (Smart)', value: 'Enabled', reason: 'Origin shielding → higher hit ratio and fewer origin hits.' },
  { setting: 'Cache Reserve', value: 'Enabled if media-heavy', reason: 'Persistent R2-backed store for long-lived images/video (TTL ≥ 10h; paid add-on).' },
  { setting: 'Browser Integrity Check', value: 'Enabled', reason: 'Lightweight filtering of malformed headers / bad bots.' },
  { setting: 'Rocket Loader', value: 'Disabled', reason: 'Async-defers JS → breaks many widgets/SPAs and weakens strong ETags; optimize at build instead.' },
  { setting: 'Auto Minify', value: 'Removed (2024)', reason: 'Disabled and removed by Cloudflare (Aug–Oct 2024); minify at build (Next.js / Vite already do).' },
  { setting: 'Early Hints', value: 'Optional', reason: '103 Early Hints can preload critical assets when the origin emits them.' },
];

interface PlanRow {
  feature: string;
  free: string;
  pro: string;
  business: string;
  enterprise: string;
}
const PLAN_MATRIX: PlanRow[] = [
  { feature: 'Cache Rules (max count)', free: '10', pro: '25', business: '50', enterprise: '300' },
  { feature: 'Min Edge Cache TTL', free: '2 hours', pro: '1 hour', business: '1 second', enterprise: '1 second' },
  { feature: 'Cache Everything / Eligible', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Bypass on cookie', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Ignore / normalize query string', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Cache by device type', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Custom cache key (header / cookie / geo)', free: '—', pro: '—', business: '—', enterprise: '✓' },
  { feature: 'Stale-while-revalidate', free: '✓', pro: '✓', business: '✓', enterprise: '✓ migrating' },
  { feature: 'Tiered Cache (Smart)', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Tiered Cache (Regional / Generic)', free: '—', pro: '—', business: '—', enterprise: '✓' },
  { feature: 'Cache Reserve (paid add-on)', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Purge by URL / Everything', free: '✓', pro: '✓', business: '✓', enterprise: '✓' },
  { feature: 'Purge by Tag / Hostname / Prefix', free: 'verify *', pro: 'verify *', business: 'verify *', enterprise: '✓' },
  { feature: 'Origin Cache Control toggle', free: 'locked-on', pro: 'locked-on', business: 'locked-on', enterprise: '✓' },
];

const CURRENT_POLICY: { component: string; strategy: string }[] = [
  { component: 'Homepage', strategy: 'ISR revalidate = 3600 (Next.js)' },
  { component: 'Latest', strategy: 'ISR 60' },
  { component: 'Live / Radio / TV', strategy: 'ISR 30' },
  { component: 'Trending / Economy / Bourse / Gold / Epaper', strategy: 'ISR 300' },
  { component: 'Article / Category / Author / Videos / Reels', strategy: 'ISR 21600 + cache tags' },
  { component: 'Static pages', strategy: 'ISR 86400' },
  { component: 'Data fetches (feed, articles, sport…)', strategy: 'next: { revalidate, tags }' },
  { component: 'Ads serve', strategy: 'Laravel max-age=30, public — BFF currently forces no-store' },
  { component: 'Ad beacons (impression / click)', strategy: 'no-store (POST)' },
  { component: 'User pages (account, follow, auth, following)', strategy: 'no-store / force-dynamic' },
  { component: 'RSS / Weather API', strategy: 's-maxage=300 / max-age=900 + SWR' },
  { component: 'Invalidation', strategy: 'Webhook /api/revalidate → revalidateTag + revalidatePath' },
];

const RULES: string[] = [
  'Bypass auth / account / personalized — /account, /login, /register, /forgot-password, /sport/following → Bypass cache.',
  'Cache public HTML — homepage, /articles/, /category/, /writer/, /live, /sport, /videos, /reels → Eligible for cache, short Edge TTL, Browser TTL 0, Serve stale ON.',
  'Normalize the cache key — ignore utm_*, fbclid, gclid, ref, igshid on HTML (the default key includes the full query string → fragmentation).',
  'Immutable static assets — /_next/static, /build → Edge + Browser TTL 1 year.',
  'Media long-cache + Cache Reserve — /uploads, /storage on the media origin → Edge 30d–1y, Cache Reserve ON, Tiered Cache ON.',
  'Respect the origin for feeds / ads — /rss.xml, /api/ads/serve, /api/weather → Respect origin Cache-Control + Serve stale ON.',
];

const DO_NOT_CACHE: string[] = [
  'Admin Subdomain', '/login', '/register', '/forgot-password', '/account/*',
  '/sport/following', 'Auth APIs (/api/auth)', 'CSRF Endpoints', 'Personalized Responses', 'Session-Based Content',
];
const LONG_TERM: string[] = [
  'Versioned CSS', 'Versioned JS', 'Fonts', 'Static Images', 'Media Conversions', 'Optimized WebP',
];
const CHECKLIST: { item: string; hint: string }[] = [
  { item: 'CDN Zone Connected', hint: 'Domain proxied (orange-cloud) through Cloudflare.' },
  { item: 'SSL Full (Strict)', hint: 'End-to-end TLS to a valid origin certificate.' },
  { item: 'Cache Rules Configured', hint: 'In the order of the AlphaCMS default rules above.' },
  { item: 'Bypass Auth Routes', hint: '/account, /login, /register, /forgot-password, /api.' },
  { item: 'Revalidation + Purge Integrated', hint: '/api/revalidate webhook wired to CDN purge.' },
  { item: 'Tiered Cache Enabled', hint: 'Origin shielding (Smart Tiered Cache).' },
  { item: 'Cache Reserve Enabled', hint: 'Persistence for long-lived media (paid add-on).' },
  { item: 'Images Served From CDN', hint: '/uploads, /storage on the media origin behind the zone.' },
  { item: 'Browser Cache Policy Reviewed', hint: "HTML Browser TTL = 0 (browser cache can't be purged)." },
];

function StatusBadge({ status }: { status: Status }) {
  return (
    <span className={cn('inline-block whitespace-nowrap rounded px-2 py-0.5 text-[11px] font-medium', STATUS_STYLE[status])}>
      {STATUS_LABEL[status]}
    </span>
  );
}

function RiskBadge({ risk }: { risk: Risk }) {
  return (
    <span className={cn('inline-block whitespace-nowrap rounded px-2 py-0.5 text-[11px] font-medium', RISK_STYLE[risk])}>
      {risk}
    </span>
  );
}

function execCopy(text: string) {
  try {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  } catch {
    /* no-op */
  }
}

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);
  const onCopy = () => {
    const flash = () => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1200);
    };
    try {
      const p = navigator.clipboard?.writeText(text);
      if (p && typeof p.then === 'function') {
        p.then(flash).catch(() => {
          execCopy(text);
          flash();
        });
        return;
      }
    } catch {
      /* fall through to execCommand */
    }
    execCopy(text);
    flash();
  };
  return (
    <button
      type="button"
      onClick={onCopy}
      className="inline-flex shrink-0 items-center justify-center rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-primary"
      aria-label="Copy expression"
    >
      {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
    </button>
  );
}

function SubBlock({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <div className="mt-6">
      <div className="mb-3 flex items-center gap-2">
        <Icon className="h-4 w-4 text-muted-foreground" />
        <h3 className="text-sm font-semibold">{title}</h3>
      </div>
      {children}
    </div>
  );
}

const TH = 'px-3 py-2 text-start font-medium';
const TD = 'px-3 py-2 align-top';

export function CdnCacheRecommendations() {
  const { t } = useTranslation('cdn');

  return (
    <section className="rounded-3xl border border-border bg-background p-6 shadow-soft sm:p-7">
      <header className="mb-5 flex items-start gap-3">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <ServerCog className="h-5 w-5" />
        </span>
        <div>
          <h2 className="text-base font-semibold">{t('recommendations.title', 'CDN Cache Recommendations')}</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {t('recommendations.subtitle', 'A practical Cloudflare setup guide built on the real AlphaCMS routes — based on Cloudflare Cache docs + industry best practices.')}
          </p>
        </div>
      </header>

      <div className="mb-1 flex flex-wrap gap-2">
        {(['cache', 'short', 'immutable', 'bypass'] as Status[]).map((s) => (
          <StatusBadge key={s} status={s} />
        ))}
      </div>

      {/* 1 — Cloudflare Cache Matrix */}
      <SubBlock icon={Table2} title={t('recommendations.sections.cacheMatrix', 'Cloudflare Cache Matrix')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[1080px] border-collapse text-[11px]">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Content type</th>
                <th className={TH}>Cache rule</th>
                <th className={TH}>Browser TTL</th>
                <th className={TH}>Edge TTL</th>
                <th className={TH}>Cache status</th>
                <th className={TH}>Purge method</th>
                <th className={TH}>Plan</th>
                <th className={TH}>Recommended for</th>
                <th className={TH}>Notes</th>
              </tr>
            </thead>
            <tbody>
              {MATRIX.map((r) => (
                <tr key={r.type} className="border-t border-border">
                  <td className={cn(TD, 'font-medium')}>{r.type}</td>
                  <td className={cn(TD, 'whitespace-nowrap')}>{r.rule}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{r.browser}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{r.edge}</td>
                  <td className={TD}><StatusBadge status={r.status} /></td>
                  <td className={cn(TD, 'text-muted-foreground')}>{r.purge}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{r.plan}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{r.recFor}</td>
                  <td className={cn(TD, 'text-muted-foreground')}>{r.notes}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p dir="ltr" className="mt-2 text-[11px] leading-relaxed text-muted-foreground">
          ¹ The Eligible rule is Free+, but a useful short Edge TTL needs Business+ (Free floor = 2h, Pro = 1h).{' '}
          ² Instant per-article Tag purge is effectively Enterprise; URL purge works on lower tiers.{' '}
          ³ Cache Reserve is a paid add-on (any plan).{' '}
          ⁴ Ad edge-caching is safe only when the cache key is normalized correctly (see V9).
        </p>
      </SubBlock>

      {/* 2 — Cloudflare Rules Reference (real AlphaCMS routes) */}
      <SubBlock icon={FileCode2} title={t('recommendations.sections.rulesRef', 'Cloudflare Rules Reference')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[1320px] border-collapse text-[11px]">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Rule name</th>
                <th className={TH}>Cloudflare expression</th>
                <th className={TH}>Action</th>
                <th className={TH}>Browser TTL</th>
                <th className={TH}>Edge TTL</th>
                <th className={TH}>Purge strategy</th>
                <th className={TH}>AlphaCMS module</th>
                <th className={TH}>Risk</th>
                <th className={TH}>Why</th>
                <th className={TH}>Notes</th>
              </tr>
            </thead>
            <tbody>
              {RULES_REF.map((r) => (
                <tr key={r.name} className="border-t border-border">
                  <td className={cn(TD, 'whitespace-nowrap font-medium')}>{r.name}</td>
                  <td className={TD}>
                    <div className="flex items-center gap-1.5">
                      <code className="whitespace-nowrap rounded bg-muted px-1.5 py-0.5 font-mono text-[10.5px]">{r.expr}</code>
                      <CopyButton text={r.expr} />
                    </div>
                  </td>
                  <td className={cn(TD, 'whitespace-nowrap')}>{r.action}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{r.browser}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{r.edge}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{r.purge}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{r.module}</td>
                  <td className={TD}><RiskBadge risk={r.risk} /></td>
                  <td className={cn(TD, 'min-w-[220px] text-muted-foreground')}>{r.why}</td>
                  <td className={cn(TD, 'min-w-[160px] text-muted-foreground')}>{r.notes || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p dir="ltr" className="mt-2 text-[11px] leading-relaxed text-muted-foreground">
          Cloudflare evaluates rules top-down (first match wins): keep narrow rules above broad ones — e.g. Ad Serve and Weather above the API bypass, and Following Page above the Sport cache.
        </p>
      </SubBlock>

      {/* 3 — AlphaCMS Recommended Rule Order */}
      <SubBlock icon={ListOrdered} title={t('recommendations.sections.ruleOrder', 'AlphaCMS Recommended Rule Order')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[820px] border-collapse text-[11px]">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>#</th>
                <th className={TH}>Rule</th>
                <th className={TH}>Why this position</th>
                <th className={TH}>If placed below another rule</th>
              </tr>
            </thead>
            <tbody>
              {RULE_ORDER.map((o) => (
                <tr key={o.n} className="border-t border-border">
                  <td className={cn(TD, 'tabular-nums font-medium')}>{o.n}</td>
                  <td className={cn(TD, 'whitespace-nowrap font-medium')}>{o.rule}</td>
                  <td className={cn(TD, 'min-w-[240px] text-muted-foreground')}>{o.why}</td>
                  <td className={cn(TD, 'min-w-[300px] text-muted-foreground')}>{o.breaks}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SubBlock>

      {/* 4 — Copy Ready Expressions */}
      <SubBlock icon={Braces} title={t('recommendations.sections.expressions', 'Copy Ready Expressions')}>
        <div dir="ltr" className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {EXPRESSIONS.map((e) => (
            <div key={e.label} className="rounded-lg border border-border bg-muted/30 p-2.5">
              <div className="mb-1 text-[11px] font-medium text-muted-foreground">{e.label}</div>
              <div className="flex items-center justify-between gap-2">
                <code className="min-w-0 flex-1 overflow-x-auto whitespace-nowrap font-mono text-[11px]">{e.expr}</code>
                <CopyButton text={e.expr} />
              </div>
            </div>
          ))}
        </div>
      </SubBlock>

      {/* 5 — AlphaCMS Presets */}
      <SubBlock icon={LayoutTemplate} title={t('recommendations.sections.presets', 'AlphaCMS presets')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[720px] border-collapse text-xs">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Preset</th>
                <th className={TH}>Homepage TTL</th>
                <th className={TH}>Article TTL</th>
                <th className={TH}>Image TTL</th>
                <th className={TH}>API policy</th>
                <th className={TH}>Purge strategy</th>
              </tr>
            </thead>
            <tbody>
              {PRESETS.map((p) => (
                <tr key={p.name} className="border-t border-border">
                  <td className={cn(TD, 'whitespace-nowrap font-medium')}>{p.name}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{p.home}</td>
                  <td className={cn(TD, 'whitespace-nowrap tabular-nums text-muted-foreground')}>{p.article}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{p.image}</td>
                  <td className={cn(TD, 'text-muted-foreground')}>{p.api}</td>
                  <td className={cn(TD, 'text-muted-foreground')}>{p.purge}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SubBlock>

      {/* 6 — Recommended Cloudflare Configuration */}
      <SubBlock icon={SlidersHorizontal} title={t('recommendations.sections.config', 'Recommended Cloudflare configuration')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[640px] border-collapse text-xs">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Setting</th>
                <th className={TH}>Recommended value</th>
                <th className={TH}>Reason</th>
              </tr>
            </thead>
            <tbody>
              {CONFIG.map((c) => (
                <tr key={c.setting} className="border-t border-border">
                  <td className={cn(TD, 'whitespace-nowrap font-medium')}>{c.setting}</td>
                  <td className={cn(TD, 'whitespace-nowrap')}>{c.value}</td>
                  <td className={cn(TD, 'text-muted-foreground')}>{c.reason}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SubBlock>

      {/* 7 — Feature by Cloudflare plan */}
      <SubBlock icon={Gauge} title={t('recommendations.sections.matrix', 'Feature by Cloudflare plan')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[640px] border-collapse text-xs">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Feature</th>
                <th className={TH}>{t('plans.free', 'Free')}</th>
                <th className={TH}>{t('plans.pro', 'Pro')}</th>
                <th className={TH}>{t('plans.business', 'Business')}</th>
                <th className={TH}>{t('plans.enterprise', 'Enterprise')}</th>
              </tr>
            </thead>
            <tbody>
              {PLAN_MATRIX.map((p) => (
                <tr key={p.feature} className="border-t border-border">
                  <td className={cn(TD, 'font-medium')}>{p.feature}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{p.free}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{p.pro}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{p.business}</td>
                  <td className={cn(TD, 'whitespace-nowrap text-muted-foreground')}>{p.enterprise}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p dir="ltr" className="mt-2 text-[11px] text-muted-foreground">
          * Purge by Tag/Hostname/Prefix: current Cloudflare docs list it for all tiers, but it was historically Enterprise-only — verify in your dashboard.
        </p>
      </SubBlock>

      {/* 8 — Recommended Cloudflare rules (strategy) */}
      <SubBlock icon={ListChecks} title={t('recommendations.sections.rules', 'Recommended Cloudflare rules')}>
        <ol dir="ltr" className="list-decimal space-y-1.5 ps-5 text-xs leading-relaxed text-muted-foreground">
          {RULES.map((r, i) => (
            <li key={i}>{r}</li>
          ))}
        </ol>
      </SubBlock>

      {/* 9 — Do not cache */}
      <SubBlock icon={Ban} title={t('recommendations.sections.donot', 'Do not cache')}>
        <div dir="ltr" className="flex flex-wrap gap-2">
          {DO_NOT_CACHE.map((d) => (
            <span key={d} className="rounded bg-destructive/10 px-2.5 py-1 text-[11px] font-medium text-destructive">
              {d}
            </span>
          ))}
        </div>
        <p className="mt-2 text-[11px] text-muted-foreground">
          {t('recommendations.donotHint', 'Bypass cache + bypass-on-cookie; never mark "Eligible for cache". Credentials / session / personalization → caching risks data leakage.')}
        </p>
      </SubBlock>

      {/* 10 — Long-term cache assets */}
      <SubBlock icon={InfinityIcon} title={t('recommendations.sections.longterm', 'Long-term cache assets (1 year)')}>
        <div dir="ltr" className="flex flex-wrap gap-2">
          {LONG_TERM.map((a) => (
            <span key={a} className="rounded bg-sky-500/10 px-2.5 py-1 text-[11px] font-medium text-sky-500">
              {a}
            </span>
          ))}
        </div>
        <p className="mt-2 text-[11px] text-muted-foreground">
          {t('recommendations.longtermHint', 'Edge + Browser TTL = 1 year, immutable, Cache Reserve eligible, never purged — content-addressed filenames change on update.')}
        </p>
      </SubBlock>

      {/* 11 — AlphaCMS current policy */}
      <SubBlock icon={Database} title={t('recommendations.sections.current', 'AlphaCMS current policy')}>
        <div dir="ltr" className="overflow-x-auto rounded-xl border border-border">
          <table className="w-full min-w-[480px] border-collapse text-xs">
            <thead>
              <tr className="bg-muted/50 text-muted-foreground">
                <th className={TH}>Component</th>
                <th className={TH}>Current strategy</th>
              </tr>
            </thead>
            <tbody>
              {CURRENT_POLICY.map((c) => (
                <tr key={c.component} className="border-t border-border">
                  <td className={cn(TD, 'font-medium')}>{c.component}</td>
                  <td className={cn(TD, 'text-muted-foreground')}>{c.strategy}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SubBlock>

      {/* 12 — Cloudflare readiness checklist */}
      <SubBlock icon={ClipboardCheck} title={t('recommendations.sections.checklist', 'Cloudflare readiness checklist')}>
        <ul dir="ltr" className="space-y-2">
          {CHECKLIST.map((c) => (
            <li key={c.item} className="flex items-start gap-2.5 text-xs">
              <Square className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span>
                <span className="font-medium">{c.item}</span>
                <span className="text-muted-foreground"> — {c.hint}</span>
              </span>
            </li>
          ))}
        </ul>
      </SubBlock>

      {/* Zone creation notice */}
      <div className="mt-6 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
        <div>
          <h3 className="text-sm font-semibold text-amber-600 dark:text-amber-500">
            {t('recommendations.sections.notice', 'Zone creation notice')}
          </h3>
          <p className="mt-1 text-sm leading-relaxed text-amber-700 dark:text-amber-200/90">
            {t(
              'recommendations.noticeText',
              'When you create a new Zone in AlphaCMS, no Cache Rules, TTLs, or Cache Everything are applied automatically. Every Zone starts with no cache policy until the administrator configures it manually.',
            )}
          </p>
        </div>
      </div>

      {/* Cloudflare Setup Checklist (final) */}
      <SubBlock icon={ListTodo} title={t('recommendations.sections.setupChecklist', 'Cloudflare Setup Checklist')}>
        <ul dir="ltr" className="space-y-2">
          {SETUP.map((c) => (
            <li key={c.item} className="flex items-start gap-2.5 text-xs">
              <Square className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span>
                <span className="font-medium">{c.item}</span>
                <span className="text-muted-foreground"> — {c.hint}</span>
              </span>
            </li>
          ))}
        </ul>
      </SubBlock>

      <p className="mt-4 text-[11px] text-muted-foreground">
        {t(
          'recommendations.disclaimer',
          'Sourced from the official Cloudflare Cache documentation + industry best practices for high-traffic news. Verify plan-gated features in your Cloudflare dashboard before relying on them.',
        )}
      </p>
    </section>
  );
}

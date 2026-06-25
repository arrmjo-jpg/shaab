# AlphaCMS Engineering Skill

## Project Identity
This project is AlphaCMS, a high-performance enterprise-grade news/media platform backend.
Admin and Public code boundaries must remain physically separated.

Do not mix admin/public implementation files.
Architecture targets:
- Laravel 13
- PHP 8.4
- API-first backend
- React admin frontend (separate)
- Mobile apps
- TV apps
- Cloudflare CDN
- Cloudflare R2
- Redis
- Meilisearch
- Firebase
- Reverb

Primary priorities:
1. Performance
2. Maintainability
3. Consistency
4. Scalability

Performance is more important than architectural purity.

---

## Hard Rules

### Forbidden
Never:
- create random folders
- invent architecture patterns
- add Blade views
- add Livewire
- add Inertia
- place business logic inside controllers
- return raw Eloquent models
- create generic services like AppService or HelperService
- create repositories unless explicitly requested
- write synchronous heavy tasks in request lifecycle
- introduce N+1 queries
- create files outside documented architecture
- ignore caching for public endpoints

---

### Mandatory
Always:
- follow documented architecture
- use typed PHP code
- use strict typing
- use Laravel best practices
- use DTOs for structured business input
- use Form Requests for validation
- use Resources for API responses
- queue heavy operations
- cache expensive public responses
- eager load relationships
- optimize database queries
- use indexes where needed
- reuse existing patterns

---

## Architecture Philosophy
This project follows:

Performance-first DDD-lite architecture.

Meaning:
- clean structure
- modular design
- no unnecessary abstraction
- no overengineering

Flow:
Controller
→ Request validation
→ Action
→ Service (if external integration needed)
→ Models
→ Resource
→ Cache layer

---

## Performance Rules
Always optimize for:
- Redis caching
- Cloudflare edge caching
- response caching
- queued processing
- CDN-friendly architecture

Heavy operations MUST be queued:
- image conversion
- image optimization
- SEO generation
- AI content generation
- push notifications
- sitemap generation
- search indexing
- cache warming

---

## Media Rules
Media stack:
- Spatie MediaLibrary
- Spatie Image
- Imagick
- Cloudflare R2

Default image output:
- WebP

Collections:
- articles
- avatars
- social
- ads
- thumbnails

---

## Search Rules
Use:
- Laravel Scout
- Meilisearch

Never implement raw DB search for public search endpoints.

---

## Security Rules
Use:
- Sanctum
- Policies
- Form Requests
- validation everywhere
- HTML purification when rendering rich content

---

## Final Rule
Before creating code:
inspect the existing project structure and follow it exactly.
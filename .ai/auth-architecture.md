# AlphaCMS Authentication Architecture Rules

## Core Identity Model
Admin authentication code must live in dedicated admin namespaces and folders.

Public authentication code must live in dedicated public namespaces and folders.

Shared auth controllers are forbidden.
Authentication must use a single users table.

Forbidden:
- admins table
- admin_users table
- separate admin authentication models
- duplicate identity storage

All identities must live in:
users

---

## Authentication Boundaries
Authentication must be fully separated between public users and admin users.

Public auth endpoints:
- /api/v1/auth/register
- /api/v1/auth/login
- /api/v1/auth/logout
- /api/v1/auth/me
- /api/v1/auth/forgot-password
- /api/v1/auth/reset-password
- /api/v1/auth/social/google
- /api/v1/auth/social/facebook

Admin auth endpoints:
- /api/v1/admin/auth/login
- /api/v1/admin/auth/logout
- /api/v1/admin/auth/me
- /api/v1/admin/auth/forgot-password
- /api/v1/admin/auth/reset-password

Forbidden:
- shared login endpoints
- shared admin/public auth controllers
- admin registration endpoint

---

## Sanctum Token Security
Public authentication tokens must only have:
user

Example:
$user->createToken('public-token', ['user'])

Admin authentication tokens must only have:
admin

Example:
$user->createToken('admin-token', ['admin'])

Forbidden:
- shared token abilities
- unrestricted tokens

---

## Role Security
Admin routes must require BOTH:
1. sanctum authentication
2. admin token ability
3. valid admin role

Admin roles:
- super_admin
- editor
- reviewer
- moderator
- social_media_manager
- journalist
- contributor

Public users must not access admin routes.

Use Spatie permission.

---

## API Architecture
Laravel backend is API-only.

Forbidden:
- Blade auth UI
- Livewire auth
- Inertia auth
- server-rendered auth pages

Frontend consumers:
- React admin dashboard
- mobile apps
- TV apps
- public frontend

---

## Admin Dashboard Rules
Admin frontend requirements:
- Arabic only
- RTL only
- Tajawal font only
- shadcn/ui mandatory
- SweetAlert2 for alerts
- toast notifications
- primary theme color: #3B7597

No English admin UI.

---

## Localization Rules
All UI strings must be externalized.

Forbidden:
hardcoded Arabic strings inside code

Use:
frontend locale files

Laravel validation messages must use localization.

---

## Branding Rules
Application name source of truth:
ALPHACMS env variable

Do not hardcode app name.

---

## Controller Rules
Controllers must remain thin.

Allowed:
- receive request
- call action
- return resource

Forbidden:
- business logic
- raw queries
- validation logic

---

## Security Rules
Use:
- Sanctum
- Form Requests
- Policies
- Spatie permission

Never trust frontend claims about roles.

Always verify server-side.

---

## Final Rule
Do not invent alternate authentication architecture.
Strictly follow this specification.


Roles and permissions must support admin-friendly metadata.

Permissions must include:
- display_name (Arabic)
- group
- description

Roles must include:
- display_name (Arabic)
- description

Permission groups are mandatory for admin UI organization.
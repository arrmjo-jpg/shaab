# API Standards

## API Type
JSON API only.

No Blade rendering.
No server-rendered pages.

---

## Response Format

Success:

{
  "success": true,
  "message": "",
  "data": {},
  "meta": {}
}

Error:

{
  "success": false,
  "message": "",
  "errors": {}
}

---

## API Versioning
All endpoints must be versioned.

Example:
/api/v1/...

---

## Pagination
Always paginate list endpoints.

Never return huge datasets.

---

## Validation
Use Form Requests only.

Never validate directly inside controllers.

---

## Resources
Use API Resources for all responses.

Never return raw Eloquent models.

---

## Public Endpoints
Public GET endpoints must be cache-friendly.

Prefer deterministic payloads.

---

## Naming
Use RESTful naming.

Examples:
GET /articles
GET /articles/{slug}
POST /admin/articles
PATCH /admin/articles/{id}
DELETE /admin/articles/{id}
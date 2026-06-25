# Project Architecture

## Folder Structure
Admin and Public code boundaries must remain physically separated.

Do not mix admin/public implementation files.

app/
  Actions/
  DTOs/
  Enums/
  Exceptions/
  Http/
    Controllers/
      Api/
        V1/
    Middleware/
    Requests/
    Resources/
  Jobs/
  Models/
  Policies/
  Services/
  Settings/
  Support/
    AI/
    Cache/
    CDN/
    Media/
    Search/
    SEO/

routes/
  api/
    v1/
      auth.php
      public.php
      admin.php

---

## Responsibilities

### Controllers
Controllers must remain thin.

Allowed:
- receive request
- call Action
- return Resource

Forbidden:
- business logic
- raw queries
- validation logic

---

### Actions
Actions represent business use cases.

Examples:
- CreateArticleAction
- PublishArticleAction
- FeatureArticleAction
- UploadArticleImageAction

---

### Services
Services are only for external integrations.

Examples:
- FirebaseService
- OpenAIService
- CloudflareService
- SeoService
- SearchService
- MediaService

---

### DTOs
DTOs contain structured business input.

Examples:
- CreateArticleData
- UpdateArticleData
- UploadImageData

---

### Resources
All API output must use Resources.

Never return raw models.

---

### Jobs
All heavy async tasks go here.

Examples:
- GenerateSeoJob
- OptimizeImageJob
- SendPushNotificationJob
- WarmHomepageCacheJob

---

### Support
Infrastructure utilities only.

Examples:
- cache keys
- CDN helpers
- search adapters
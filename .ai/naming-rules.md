# Naming Rules

## Actions
Format:
Verb + Entity + Action

Examples:
CreateArticleAction
PublishArticleAction
DeleteCommentAction

---

## DTOs
Format:
Verb + Entity + Data

Examples:
CreateArticleData
UpdateCategoryData

---

## Requests
Format:
Verb + Entity + Request

Examples:
StoreArticleRequest
UpdateArticleRequest

---

## Resources
Format:
Entity + Resource

Examples:
ArticleResource
CategoryResource

---

## Jobs
Format:
Verb + Purpose + Job

Examples:
GenerateSeoJob
OptimizeImageJob
WarmHomepageCacheJob

---

## Services
Format:
SpecificPurpose + Service

Examples:
FirebaseService
CloudflareService
MediaService

Forbidden:
AppService
CommonService
HelperService

---

## Enums
Format:
Entity + Enum

Examples:
ArticleStatus
UserRole
MediaCollection

---

## Cache Keys
Format:
resource:scope:identifier

Examples:
home:feed
home:trending
article:slug:breaking-news
settings:general
seo:defaults
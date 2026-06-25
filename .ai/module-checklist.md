# Module Creation Checklist

Before creating a new module:
Before creating a module:
determine whether it belongs to Admin or Public boundary.

## Step 1
Identify domain entity.

Examples:
- Article
- Category
- Comment
- Poll

---

## Step 2
Create model if needed.

---

## Step 3
Create Form Requests
Examples:
- StoreArticleRequest
- UpdateArticleRequest

---

## Step 4
Create DTOs
Examples:
- CreateArticleData
- UpdateArticleData

---

## Step 5
Create Actions
Examples:
- CreateArticleAction
- UpdateArticleAction
- DeleteArticleAction

---

## Step 6
Create Resource
Example:
- ArticleResource

---

## Step 7
Create Policy if permissions needed.

---

## Step 8
Add API routes in correct version file.

---

## Step 9
Add tests.

---

## Step 10
Review caching opportunities.

Questions:
- should this be cached?
- should this be queued?
- should this use Redis?
- should Cloudflare cache this?

---

## Step 11
Check performance:
- eager loading?
- indexes?
- pagination?
- N+1?
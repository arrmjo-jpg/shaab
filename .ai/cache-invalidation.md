# Cache Invalidation — Event-Driven First (Source of Truth)

نظام الكاش **حدثيّ أوّلًا**: المحتوى يبقى مُكاشًا حتى يقع حدث حقيقيّ يخصّه، وTTL/ISR **شبكة أمان فقط**
(يلتقط فقدان حدث نادرًا — طابور ساقط/شبكة)، لا وسيلة تحديث. أيّ `fetch` جديد بالواجهة يجب أن يحمل
وسمًا من هذا القاموس، وأيّ كتابة جديدة بالباك إند يجب أن تُطلق وسومها عبر `FrontendRevalidate::tags()`.
**ممنوع** وسم بلا مُطلِق أو مُطلِق بلا مستمع — هذا الملفّ هو العقد.

## السلسلة
`Action ← save/commit ← (afterCommit) FrontendRevalidate ⇒ RevalidateFrontendCacheJob (queue، tries=1)
⇒ POST {FRONTEND_REVALIDATE_URL} بسرّ x-revalidate-secret ⇒ revalidateTag()` — وبالتوازي CDN purge
(بوّابة cdn_enabled+cdn_auto_purge، Buffer+RateLimiter) وSearch ping (بوّابة SEARCH_PING_ENABLED).
كلّ `purge()` ملفوف بـ`rescue()` — فشل أيّ طرف يُسجَّل ولا يكسر حفظ المحتوى.

## القاموس الموحَّد (Laravel ⇄ Next — حرفيًّا)

| الوسم | يستهلكه (lib) | يُطلقه |
|---|---|---|
| `feed:hero` | `getHeroFeed` | كتابة مقال **مميّز** (أو كان) — مشروط بالعلم |
| `feed:header` | `getHeaderFeed` | كتابة مقال **هيدر** (أو كان) — مشروط |
| `feed:latest` | `getLatestFeed` (latest + الشريط الجانبيّ) | كلّ كتابة مقال |
| `feed:most_read` | `getMostReadFeed` | كلّ كتابة مقال (المحرّك الحقيقيّ عدّادات ⇒ زمنيّ جوهرًا) |
| `article:{slug}` | `getArticle` | كتابة المقال (+وسم الـslug **القديم** عند تغييره) |
| `articles` (مظلّة) | كلّ جلبات المقالات | **فقط** تغيّر slug تصنيف (breadcrumbs/روابط داخل صفحات المقالات) |
| `category:{slug}` | `getCategoryFeed` | كتابة مقال بالقسم + أحداث التصنيف (+القديم عند تغيّر slug) |
| `categories` | شجرة `getCategoryById` | كلّ أحداث التصنيف الستّ |
| `site-settings` | `getSiteSettings` (هيدر/فوتر/SEO/nav) | إعدادات عامّة/جريدة + كلّ أحداث التصنيف (nav) |
| `page-feed:{L}` / `page:{L}:{slug}` | قوائم/تفاصيل الصفحات الثابتة | أحداث الصفحة السبعة (+القديم) |
| `reel-feed:{L}` / `reel:{L}:{slug}` | خلاصة/تفاصيل الريلز | أحداث الريل الثمانية (+القديم) |
| `video-feed:{L}` / `video:{L}:{slug}` / `video-category:{L}:{slug}` / `playlist:{L}:{slug}` | قوائم/تفاصيل الفيديو والقوائم | كلّ أكشنات الفيديو (23) عبر `fromVideoTags` |
| `writers` / `writer:{id}` | بروفايل الكاتب | تعديل المستخدم/حالته |
| `comments` / `comments:{slug}` | قائمة تعليقات المقال | **الإشراف** (اعتماد/رفض/حذف) — الإنشاء pending فلا يُبطل |
| `tts-config` / `social-config` / `recaptcha-config` | بوّابات الميزات | تحديث إعدادات الطرف الثالث |

**زمنيّ بقرار معماريّ (يُمنع تحويله لحدثيّ):** `most_read/trending` والعدّادات (مُجمَّعة — حدث/مشاهدة =
عاصفة)، الطقس/الذهب/البورصة/365Scores (لا أحداث مصدر)، حضور البثّ، `latest` بسقفه 60s (دلالة «الآن»)،
**وإعلانات العرض no-store** (رموز انطباع ضمن نافذة دلو — لا كاش أصلًا).

## سقوف ISR (أمان لا تحديث)
هوم 3600 · مقال/قسم/فيديو/ريلز/كاتب 21600 · صفحات ثابتة 86400 · latest 60 · trending 300 (زمنيّ بالتصميم).
**ملاحظة بنيويّة:** الشريط الجانبيّ الحيّ داخل صفحات المقالات (feed:latest/most_read) يعيد بناءها كسولًا
مع كلّ نشر — مرغوب إخباريًّا؛ لا تَعِد بكاش مقالات أطول من ذلك.

## تشغيل إلزاميّ (بدونه الأحداث تنام بصمت)
- **عامل طابور دائم**: `php artisan queue:work --queue=default,media,cdn-purge,search --sleep=3 --tries=1`
  (إنتاجًا تحت Supervisor/systemd؛ محلّيًّا أبقه يعمل).
- **مجدول**: cron `* * * * * php artisan schedule:run` (إنتاجًا) أو `php artisan schedule:work` (محلّيًّا).
- **مراقبة قائمة**: Spatie Health — `QueueCheck` (heartbeat، مسجَّل لكلّ السائقين) + `ScheduleCheck` +
  `SchedulerHealthCheck`. إنذارات: `HEALTH_NOTIFICATION_EMAIL` (بريد) و`SCHEDULE_HEARTBEAT_URL`
  (مراقب خارجيّ — يكسر دائرة «مراقبة تراقب نفسها»). أصلِح بريد الإرسال (فشل backups التاريخيّ سببه sender مرفوض).
- envs الربط: `FRONTEND_REVALIDATE_URL` + `FRONTEND_REVALIDATE_SECRET` (مضبوطان).

## بوّابة التوسّع الأفقيّ (إلزاميّة قبل Next × N)
`revalidateTag`/ISR محلّيّان لكلّ نسخة Next. قبل تشغيل أكثر من نسخة خلف موازن: فعِّل
**`cacheHandler` الرسميّ في next.config** بمخزن مشترك على Redis القائم (وثائق Next: custom cache
handler) — وإلا أصاب الإبطالُ نسخةً واحدة وخدمت البقيّة قديمًا حتى ISR. Laravel جاهز للتعدّد أصلًا
(Redis مشترك + جلسات DB)؛ أضف `onOneServer()` للمهامّ المجدولة حينها.

## حدود بنيويّة معلَنة (لا تُحلّ بلا تغيير معماريّ كامل)
صفحة HTML = وحدة إبطال واحدة (تجزئة البلوكات تتطلّب PPR/ESI) · العدّادات حدثيًّا = stream processing ·
الخارجيّات سقفها polling · الودجات الحيّة المشتركة تفرض إعادة كسولة لصفحات المقالات مع كلّ نشر.

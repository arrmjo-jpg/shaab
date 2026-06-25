# Notification System — Release Notes v1

**الحالة:** 🔒 مُجمَّد (Frozen) · **التاريخ:** 2026-06-19 · **الوحدة:** `app/Modules/Notifications`

منصّة تنسيق إشعارات موحّدة (Event-First) داخل AlphaCMS — حملات متعدّدة القنوات (Firebase / WhatsApp / Email) يقودها الأدمن، بمحرّك واحد ومدخل واحد.

---

## ① ما اكتمل في v1

**المحرّك**
- خطّ واحد: `Event → NotificationManager` (مدخل وحيد) `→ KillSwitch → PolicyRouter → CampaignDispatcher` (سلطة إنشاء وحيدة) `→ DispatchCampaignJob → SendBatchJob → Driver`.
- آلة حالة الحملة (9 حالات) + دورة حياة ذرّيّة: **approve / pause / resume / cancel** (transitions عبر `UPDATE…WHERE status=expected` + حارسا سباق وإلغاء).
- **لا حالة Sending أبديّة**: مُصالِح (reconcile sweeper) + عدّ skipped.

**المحتوى والثبات (Immutability)**
- القالب يُصيَّر **مرّة واحدة عند الإنشاء** ويُجمَّد في snapshot القناة. تعديل/حذف قالب أو مصفوفة **لا يمسّ الحملات القائمة** (مُثبَت E2E).
- القالب المربوط في المصفوفة (`template_id`) يُقرأ ويُستعمل فعليًّا عند الإنشاء.
- EventCatalog هو SoT للأحداث (مزامنة + أرشفة-لا-حذف). المصفوفة snapshot كامل — **صفر قراءة حيّة عند الإرسال**.

**القنوات والموثوقيّة**
- **Firebase: مُثبَت End-to-End حتى FCM** (استوثاق بمفاتيح حقيقيّة، معالجة، تقليم التوكن الميّت).
- WhatsApp (UltraMsg) + Email (Mail): مبنيّان، config-gated (تخطٍّ آمن إن لم يُهيّآ).
- صحّة القنوات (probe دوريّ + `effective_state`) · Kill Switch · idempotency (dedupe + deliveries unique + topic claim).
- **opt-out مُحترَم على الحملات** (`PreferenceFilter` channel-aware، كتم عامّ؛ الضيوف يبقَون).

**طبقة الإدارة (Backend)**
- 21 مسار أدمن محميّ: حملات (سرد/تأليف يدويّ/دورة حياة/ملخّص) · مصفوفة · قوالب (CRUD + فرض المتغيّرات الموثّقة) · جماهير (سرد + معاينة عدد) · صحّة.
- الصلاحيات: `notifications.view` / `notifications.manage` / `notifications.send` (مبذورة ومربوطة).
- الجدولة موصولة: `dispatch-due` (دقيقة) · `probe-channels` (10د) · `reconcile` (15د) + نبض كشف عامل ميّت.

---

## ② مؤجَّل إلى v1.2

| البند | الحالة الحاليّة |
|---|---|
| DirectDispatcher الفعليّ (comment.reply / password_reset / system.alert) | stub يسجّل فقط؛ `system.alert` log-only (الصحّة مرئيّة عبر Health API) |
| `notification_stats_daily` | جدول بلا كاتب (تحليلات) |
| Topic-audience للبثّ (breaking_news عبر topic) | غير موصول؛ الحملات per-recipient |
| تفضيلات granular (category/event) + مسار كتابة التفضيلات | الموجود: كتم عامّ فقط |
| أولويّة الإرسال على SendBatchJob (M4) · sampling للتسليمات (M6) | كلّ الإرسال على `notifications-default` |
| Custom audiences + segments | جداول `notification_audiences`/`notification_segments` غير موصولة |
| `matrix.default_audience_id` | يُحرَّر، لا يُقرأ بعد (لمشغّلات Phase 6) |
| WhatsApp / Email E2E | لم تُختبَرا فعليًّا (تتدهوران بأمان) |
| Phase 6 Triggers (نشر مقال/فيديو/ريل/استطلاع/بثّ → حدث تلقائيّ) | مؤجَّل |
| Phase 5 Admin UI | مؤجَّل |

---

## ③ متطلبات التشغيل (بوّابة الإطلاق)

> الكود مُجمَّد وجاهز. الإطلاق الفعليّ مرهون **بالتشغيل** فقط:

**Queue Workers** (إلزاميّ — بدونها لا إرسال)
```
php artisan queue:work --queue=notifications-high,notifications-default,notifications-low \
  --sleep=1 --tries=3 --max-time=3600
```
- عامل **مستقلّ** عن طابور `media` (كي لا تختنق الإشعارات خلف ركام الميديا).
- تحت مُشرف يُبقيه حيًّا (Supervisor / NSSM / خدمة Windows).

**Cron** (إلزاميّ — للحملات المجدولة + المصالحة + فحص الصحّة)
```
* * * * * php artisan schedule:run
```

**Channel Setup**
- Firebase: مُهيّأ ✅ (مفاتيح حقيقيّة).
- WhatsApp / Email: هيّئ واختبر إن كانتا قناتَي إطلاق، وإلّا أبقهما `disabled` في المصفوفة.
- فعّل قواعد المصفوفة المطلوبة (كلّها `disabled` افتراضيًّا) عبر `notifications:sync-catalog` ثمّ تحريرها.
- أنشئ قوالب الأحداث المُطلَقة.

---

## ④ قائمة المراقبة بعد الإطلاق

- **عمق الطوابير**: `notifications-*` — ارتفاع مستمرّ ⇒ العامل متوقّف/بطيء.
- **عامل حيّ**: `health:queue-check-heartbeat` (يفشل إن لم يلتقط عامل النبضة).
- **المُجدوِل**: `SchedulerState` + `health:schedule-check-heartbeat` (كشف توقّف cron).
- **صحّة القنوات**: `GET /admin/notifications/health` — `effective_state` لكلّ قناة + `consecutive_failures`.
- **الحملات العالقة**: سجلّات `notifications:reconcile-campaigns` (يجب ألّا تبقى حملة في Sending > العتبة).
- **معدّلات الفشل/Invalid**: عدّادات القناة (`failed` / `invalid`) — ارتفاع `invalid` ⇒ توكنات ميّتة (تُقلَّم تلقائيًّا).
- **ملخّص اللوحة**: `GET /admin/notifications/campaigns/summary` — توزيع الحالات + إجماليّات الإرسال.

---

## خارج النطاق (مسار منفصل)
ركام طابور الميديا (167k × `GenerateMediaAssetConversionsJob`) + علّة `MediaConversions.php:130 (tempnam)` — **تشغيليّ في مسار الميديا، لا يخصّ الإشعارات**.

---

*v1 مُجمَّد. لا ميزات/تعديلات إلّا hotfix حرج. التوسعات في v1.2.*

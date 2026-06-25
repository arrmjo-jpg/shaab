# Production Go-Live Checklist — AlphaCMS

> قائمة مراجعة إلزاميّة قبل إطلاق الموقع على بيئة **Production**.
> هذا الملفّ **جزء من المشروع** (يُنقل مع الكود لأيّ جهاز/فريق) — وليس ذاكرة Agent ولا مجرّد تعليقات كود.
> راجِع كلّ بند وضع علامة `[x]` قبل الإطلاق.

---

## 1. إزالة ميزات التطوير المؤقّتة

### Quick Incremental Import — اختصار «استيراد الجديد فقط» (ترحيل ووردبريس)
- [ ] إزالة أو تعطيل **Quick Incremental Import**.
- [ ] التأكّد أنّ: `WP_MIGRATION_QUICK_INCREMENTAL=false`.
- [ ] التأكّد أنّ الزرّ **«استيراد الجديد فقط»** غير ظاهر في الواجهة.
- [ ] التأكّد أنّ الـAPI **يرفض** أيّ استدعاء لمسار `runs/{run}/quick-incremental`.

> 🔎 لتحديد كلّ ملفّاته فوراً: `grep -rn "TEMPORARY FEATURE" app config routes lang tests admin-frontend/src`

**(أ) تعطيل سريع:** ضع `WP_MIGRATION_QUICK_INCREMENTAL=false` في `.env` الإنتاج ثمّ `php artisan config:clear`.

**(ب) إزالة كاملة** — احذف الأجزاء المُعلَّمة `⚠️ TEMPORARY FEATURE`:
- [ ] `config/wp-migration.php` (مفتاح `quick_incremental`)
- [ ] `app/Actions/Admin/WpMigration/QuickIncrementalImportAction.php` (**احذف الملفّ**)
- [ ] `app/Http/Controllers/.../WpMigrationController.php` (دالّة `quickIncremental` + الاستيراد)
- [ ] `routes/api/v1/admin.php` (مسار `quick-incremental`)
- [ ] `app/Http/Resources/.../MigrationRunResource.php` (حقل `quick_incremental_enabled`)
- [ ] `lang/{ar,en}/wp_migration.php` (مفتاحا `never_approved`/`quick_disabled`)
- [ ] `tests/Feature/Admin/WpMigration/ExecutionOrchestrationTest.php` (اختبارا الاختصار)
- [ ] الواجهة: `wpMigration.types.ts` · `wpMigration.service.ts` · `hooks.ts` · زرّ `MigrationConsolePage.tsx` ثمّ `npm --prefix admin-frontend run build`

---

## 2. تفعيل إعدادات الإنتاج
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` صحيح (الدومين الفعليّ، https)
- [ ] أعلام/مفاتيح التطوير الأخرى مضبوطة للإنتاج (CDN, Mail, Broadcast, Scout/Meili…)

## 3. تحسين الأداء
- [ ] `php artisan optimize`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] (اختياريّ) `composer dump-autoload -o`

## 4. الخدمات
- [ ] **Queue Worker** يعمل ويغطّي كلّ طوابير التطبيق (`default` + `migration` + `vertix` + `whatsapp` + بقيّة الطوابير الحدثيّة)
- [ ] **Scheduler** يعمل (`schedule:work`/cron) — شرط حياة الأحداث والكاش الحدثيّ
- [ ] **Redis** يعمل (كاش/طوابير/قفل)
- [ ] قاعدة البيانات متّصلة وسليمة

## 5. التحقّق من الموقع
- [ ] الصفحة الرئيسية تعمل
- [ ] لوحة الإدارة تعمل
- [ ] تسجيل الدخول يعمل
- [ ] إنشاء خبر يعمل
- [ ] رفع الصور يعمل
- [ ] البحث يعمل

## 6. النسخ الاحتياطية
- [ ] إنشاء **Backup كامل** لقاعدة البيانات
- [ ] إنشاء **Backup للملفات** (الوسائط/التخزين)
- [ ] التأكّد من **إمكانية الاسترجاع** (اختبار استعادة فعليّ)

## 7. الأمان
- [ ] تعطيل أيّ **Endpoint/Shortcut** خاصّ بالتطوير (راجع القسم 1 + بحث `TEMPORARY FEATURE`)
- [ ] إزالة أيّ `TODO`/`DEBUG`/Test Routes غير مخصّصة للإنتاج
- [ ] مراجعة صلاحيّات المستخدمين والأدوار
- [ ] التأكّد من عدم تسريب أسرار (كلمات مرور/مفاتيح) في الردود أو السجلّات

## 8. بعد الإطلاق
- [ ] مراقبة **Logs**
- [ ] مراقبة **Queue** (لا تكدّس/فشل)
- [ ] مراقبة استهلاك **CPU / RAM**
- [ ] التأكّد من عدم وجود أخطاء **500/404** غير متوقّعة

---

## ⚠️ قاعدة أساسيّة
أيّ ميزة تحمل الوسم **`⚠️ TEMPORARY FEATURE`** يجب مراجعتها قبل الإطلاق،
وإزالتها أو تعطيلها إذا لم تكن جزءاً من النظام النهائيّ.
عند إضافة أيّ ميزة مؤقّتة جديدة: علّمها بهذا الوسم في الكود **وأضف بنداً في القسم 1**.

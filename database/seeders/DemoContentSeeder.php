<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Enums\CategoryScope;
use App\Enums\CategoryStatus;
use App\Enums\MediaVisibility;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo content seeder — generates realistic Arabic news data so the homepage
 * renders a credible newspaper (not fallback/empty blocks).
 *
 * Creates: 4 categories · 1 editor user · 20 articles with cover images
 *          · 5 featured (hero) articles · engagement counters (for trending sort)
 *
 * Safe to run multiple times: skips if articles already exist.
 */
class DemoContentSeeder extends Seeder
{
    // Stable picsum seeds so images are deterministic across re-seeds.
    private const COVERS = [
        'https://picsum.photos/seed/acm-01/1200/675',
        'https://picsum.photos/seed/acm-02/1200/675',
        'https://picsum.photos/seed/acm-03/1200/675',
        'https://picsum.photos/seed/acm-04/1200/675',
        'https://picsum.photos/seed/acm-05/1200/675',
        'https://picsum.photos/seed/acm-06/1200/675',
        'https://picsum.photos/seed/acm-07/1200/675',
        'https://picsum.photos/seed/acm-08/1200/675',
        'https://picsum.photos/seed/acm-09/1200/675',
        'https://picsum.photos/seed/acm-10/1200/675',
        'https://picsum.photos/seed/acm-11/1200/675',
        'https://picsum.photos/seed/acm-12/1200/675',
        'https://picsum.photos/seed/acm-13/1200/675',
        'https://picsum.photos/seed/acm-14/1200/675',
        'https://picsum.photos/seed/acm-15/1200/675',
        'https://picsum.photos/seed/acm-16/1200/675',
        'https://picsum.photos/seed/acm-17/1200/675',
        'https://picsum.photos/seed/acm-18/1200/675',
        'https://picsum.photos/seed/acm-19/1200/675',
        'https://picsum.photos/seed/acm-20/1200/675',
    ];

    public function run(): void
    {
        if (Article::count() > 0) {
            $this->command->info('Demo content already present — skipping.');

            return;
        }

        $editor = $this->upsertEditor();
        $cats = $this->seedCategories();
        $assets = $this->seedMediaAssets();
        $this->seedArticles($editor, $cats, $assets);

        $this->command->info('Demo content seeded: 4 categories, 20 articles, 5 featured (hero).');
    }

    // ── Editor user ──────────────────────────────────────────────────────────

    private function upsertEditor(): User
    {
        return User::firstOrCreate(
            ['email' => 'editor@demo.test'],
            [
                'name' => 'محرر الموقع',
                'password' => bcrypt('password'),
                'status' => 'active',
            ],
        );
    }

    // ── Categories ───────────────────────────────────────────────────────────

    /** @return array<string,Category> keyed by slug */
    private function seedCategories(): array
    {
        $defs = [
            ['slug' => 'local',   'name' => 'محليات', 'scope' => CategoryScope::News,    'order' => 1],
            ['slug' => 'sports',  'name' => 'رياضة',  'scope' => CategoryScope::News,    'order' => 2],
            ['slug' => 'economy', 'name' => 'اقتصاد', 'scope' => CategoryScope::News,    'order' => 3],
            ['slug' => 'opinion', 'name' => 'رأي',    'scope' => CategoryScope::Opinion, 'order' => 4],
        ];

        $result = [];
        foreach ($defs as $d) {
            $result[$d['slug']] = Category::firstOrCreate(
                ['slug' => $d['slug'], 'locale' => 'ar'],
                [
                    'name' => $d['name'],
                    'scope' => $d['scope'],
                    'status' => CategoryStatus::Active,
                    'show_in_header' => true,
                    'show_in_body' => true,
                    'show_in_footer' => false,
                    'sort_order' => $d['order'],
                ],
            );
        }

        return $result;
    }

    // ── Media assets (external URL — no disk storage needed) ────────────────

    /** @return list<MediaAsset> */
    private function seedMediaAssets(): array
    {
        $assets = [];
        foreach (self::COVERS as $i => $url) {
            $assets[] = MediaAsset::create([
                'uuid' => Str::uuid()->toString(),
                'kind' => 'external',
                'visibility' => MediaVisibility::Public,
                'source_url' => $url,
                'original_name' => "demo-cover-{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'width' => 1200,
                'height' => 675,
                'disk' => 'public',
                'path' => '',
                'processing_status' => 'done',
                'stored_local' => false,
                'stored_remote' => false,
                'uploaded_by' => null,
            ]);
        }

        return $assets;
    }

    // ── Articles (مكان العرض بأعلام الخبر، لا تنسيبات) ──────────────────────────

    /** @param array<string,Category> $cats @param list<MediaAsset> $assets */
    private function seedArticles(User $editor, array $cats, array $assets): void
    {
        $definitions = $this->articleDefinitions();

        foreach ($definitions as $i => $def) {
            $cat = $cats[$def['category']];
            $asset = $assets[$i % count($assets)];
            $now = now()->subHours($i * 3); // spread over 60 h, all recent

            $article = Article::create([
                'author_id' => $editor->id,
                'primary_category_id' => $cat->id,
                'locale' => 'ar',
                'type' => $def['type'] ?? ArticleType::News,
                'status' => ArticleStatus::Published,
                'title' => $def['title'],
                'subtitle' => $def['subtitle'] ?? null,
                'slug' => Str::slug($def['slug_hint'] ?? Str::ascii($def['title']), '-') ?: "article-{$i}",
                'excerpt' => $def['excerpt'],
                'content' => '<p>'.$def['excerpt'].'</p>',
                'content_json' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $def['excerpt']]]],
                ]],
                'published_at' => $now,
                'is_featured' => $def['hero'] ?? false,
                'is_breaking' => false,
                'is_header' => $def['hero'] ?? false,
                'is_editor_pick' => false,
                'comments_enabled' => false,
            ]);

            // Attach cover image via article_media pivot
            DB::table('article_media')->insert([
                'article_id' => $article->id,
                'media_asset_id' => $asset->id,
                'collection' => 'cover',
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // مكان العرض (هيرو) صار بعلم is_featured المضبوط أعلاه — لا تنسيب منفصل.
        }
    }

    // ── Article content definitions ──────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    private function articleDefinitions(): array
    {
        return [
            // ── Hero articles (5) — local category ──────────────────────────
            [
                'hero' => true,
                'category' => 'local',
                'slug_hint' => 'arafat-street-renovation',
                'title' => 'بدء أعمال إعادة تأهيل شارع عرفات في العاصمة عمّان',
                'subtitle' => 'المشروع يشمل توسعة الطريق وإضافة ممرات للمشاة وتحديث شبكة الصرف الصحي',
                'excerpt' => 'أعلنت أمانة عمّان الكبرى عن بدء تنفيذ مشروع إعادة تأهيل شارع عرفات بتكلفة تتجاوز 12 مليون دينار أردني، يشمل توسعة الطريق الرئيسي وإضافة ممرات للمشاة وتحديث البنية التحتية للصرف الصحي والإنارة.',
            ],
            [
                'hero' => true,
                'category' => 'local',
                'slug_hint' => 'jordan-gdp-growth',
                'title' => 'الأردن يسجّل نموًا اقتصاديًا بنسبة 2.8% في الربع الأول',
                'subtitle' => 'وزارة التخطيط تؤكد استمرار الزخم الإيجابي في القطاعات الرئيسية',
                'excerpt' => 'كشفت وزارة التخطيط والتعاون الدولي أن الاقتصاد الأردني حقق نموًا بنسبة 2.8% خلال الربع الأول من العام الجاري، مدفوعًا بتحسّن أداء قطاعات السياحة والخدمات المالية والصناعات التحويلية.',
            ],
            [
                'hero' => true,
                'category' => 'local',
                'slug_hint' => 'education-digital-transform',
                'title' => 'إطلاق المرحلة الثانية من مشروع التحوّل الرقمي في التعليم',
                'subtitle' => 'تشمل تزويد 500 مدرسة حكومية بالبنية التحتية التقنية اللازمة',
                'excerpt' => 'أطلقت وزارة التربية والتعليم المرحلة الثانية من مشروع التحوّل الرقمي الشامل للمدارس الحكومية، والتي تستهدف تزويد 500 مدرسة بشبكات الإنترنت عالية السرعة وأجهزة التعلّم التفاعلية خلال العام الدراسي الحالي.',
            ],
            [
                'hero' => true,
                'category' => 'local',
                'slug_hint' => 'aqaba-port-expansion',
                'title' => 'ميناء العقبة يرفع طاقته الاستيعابية إلى 25 مليون طن سنويًا',
                'subtitle' => 'مشاريع التوسعة تسهم في تعزيز مكانة المملكة مركزًا لوجستيًا إقليميًا',
                'excerpt' => 'أعلن مسؤولو سلطة منطقة العقبة الاقتصادية الخاصة عن اكتمال المرحلة الأولى من مشاريع التوسعة في ميناء العقبة، ليصل إجمالي الطاقة الاستيعابية إلى 25 مليون طن سنويًا، مما يضع الأردن في مصاف أبرز المراكز اللوجستية في منطقة الشرق الأوسط.',
            ],
            [
                'hero' => true,
                'category' => 'local',
                'slug_hint' => 'water-project-zarqa',
                'title' => 'مشروع جديد لتوفير مياه الشرب النظيفة لـ80 ألف أسرة في الزرقاء',
                'subtitle' => 'التمويل مشترك بين الحكومة والبنك الدولي بقيمة 70 مليون دولار',
                'excerpt' => 'وقّعت وزارة المياه والري اتفاقية تمويل مع البنك الدولي بقيمة 70 مليون دولار لتنفيذ مشروع توفير مياه الشرب النظيفة لأكثر من 80 ألف أسرة في محافظة الزرقاء، يشمل إنشاء محطات ضخ حديثة وتجديد شبكات التوزيع.',
            ],

            // ── Local section articles (3 more) ─────────────────────────────
            [
                'category' => 'local',
                'slug_hint' => 'amman-smart-traffic',
                'title' => 'أمانة عمّان تطلق نظام إدارة الحركة المرورية الذكي في 15 تقاطعًا',
                'excerpt' => 'بدأت أمانة عمّان الكبرى تفعيل نظام إشارات المرور الذكية المرتبطة بمركز التحكّم المركزي في 15 تقاطعًا رئيسيًا، بما يُقلّص أوقات الانتظار بنسبة تصل إلى 40%.',
            ],
            [
                'category' => 'local',
                'slug_hint' => 'jordan-tourism-record',
                'title' => 'قطاع السياحة يُحقّق رقمًا قياسيًا بـ9 ملايين زيارة خلال عام',
                'excerpt' => 'كشفت وزارة السياحة والآثار أن المملكة استقبلت ما يزيد على 9 ملايين زيارة سياحية خلال العام الماضي، بزيادة تبلغ 18% مقارنةً بالعام السابق، وهو الرقم الأعلى في تاريخ القطاع.',
            ],
            [
                'category' => 'local',
                'slug_hint' => 'health-insurance-expansion',
                'title' => 'توسيع مظلة التأمين الصحي الشامل لتغطية 92% من المواطنين',
                'excerpt' => 'أعلنت وزارة الصحة عن خطة لتوسيع مظلة التأمين الصحي الشامل لتغطية 92% من المواطنين بحلول نهاية العام، بما يشمل إضافة أدوية جديدة للقائمة المموّلة وتحسين خدمات الرعاية الأولية.',
            ],

            // ── Sports articles (4) ──────────────────────────────────────────
            [
                'category' => 'sports',
                'slug_hint' => 'al-faisaly-win',
                'title' => 'الفيصلي يُتوِّج مسيرته بالفوز بكأس الأردن للمرة الثامنة عشرة',
                'excerpt' => 'حقّق نادي الفيصلي لقب كأس الأردن للموسم الحالي بعد فوزه على غريمه الوحدات بهدفين مقابل هدف في نهائي مثير شهده استاد عمّان الدولي بحضور جماهيري كثيف.',
            ],
            [
                'category' => 'sports',
                'slug_hint' => 'jordan-national-team',
                'title' => 'المنتخب الوطني يُنهي معسكره التحضيري استعدادًا لتصفيات كأس العالم',
                'excerpt' => 'أنهى المنتخب الوطني الأردني لكرة القدم معسكره التحضيري في مدينة عمّان، وسط أجواء إيجابية أعرب عنها الجهاز الفني بقيادة المدرب الجديد قبيل انطلاق مرحلة التصفيات.',
            ],
            [
                'category' => 'sports',
                'slug_hint' => 'jordan-athletics-medal',
                'title' => 'ميدالية برونزية لأردنية في بطولة آسيا لألعاب القوى',
                'excerpt' => 'حصدت العداءة الأردنية على ميدالية برونزية في سباق 1500 متر ضمن فعاليات البطولة الآسيوية لألعاب القوى، لتُضاف إلى رصيد المملكة في المحافل القارية.',
            ],
            [
                'category' => 'sports',
                'slug_hint' => 'wihdat-asian-champions',
                'title' => 'الوحدات يواجه الهلال السعودي في دور الـ16 من دوري أبطال آسيا',
                'excerpt' => 'قرعة دوري أبطال آسيا تضع الوحدات في مواجهة الهلال السعودي في دور الـ16، وسط ترقّب جماهيري واسع للمواجهة المرتقبة في مباراة الذهاب المقرّرة الأسبوع المقبل.',
            ],

            // ── Economy articles (4) ─────────────────────────────────────────
            [
                'category' => 'economy',
                'slug_hint' => 'dinar-exchange-stable',
                'title' => 'البنك المركزي يؤكد استقرار سعر صرف الدينار أمام العملات الرئيسية',
                'excerpt' => 'أكد البنك المركزي الأردني أن الاحتياطيات الأجنبية بلغت مستويات مريحة تكفي لتغطية أكثر من 8 أشهر من المستوردات، مشيرًا إلى استقرار سعر صرف الدينار في مواجهة التقلبات الدولية.',
            ],
            [
                'category' => 'economy',
                'slug_hint' => 'jordan-fdi-increase',
                'title' => 'الاستثمار الأجنبي المباشر يرتفع 22% إلى 1.8 مليار دولار',
                'excerpt' => 'أفادت هيئة الاستثمار الأردنية بأن تدفقات الاستثمار الأجنبي المباشر ارتفعت بنسبة 22% لتبلغ 1.8 مليار دولار خلال العام الماضي، في ظل اهتمام متزايد من المستثمرين في قطاعات التقنية والطاقة المتجددة.',
            ],
            [
                'category' => 'economy',
                'slug_hint' => 'startups-jordan',
                'title' => 'الأردن يتصدّر المنطقة في عدد الشركات الناشئة التقنية الممولة',
                'excerpt' => 'تقرير حديث يُشير إلى أن الأردن تصدّر دول المنطقة في عدد الشركات الناشئة في قطاع التقنية التي حصلت على تمويل أولي خلال الربعين الماضيين، بإجمالي تجاوز 200 مليون دولار.',
            ],
            [
                'category' => 'economy',
                'slug_hint' => 'solar-energy-project',
                'title' => 'توقيع اتفاقية لإنشاء أكبر محطة طاقة شمسية في تاريخ المملكة',
                'excerpt' => 'وقّعت وزارة الطاقة اتفاقية مع تحالف دولي لإنشاء محطة طاقة شمسية بقدرة 500 ميغاواط في منطقة معان، لتكون الأكبر من نوعها في تاريخ الأردن والأولى من حيث التصدير إلى الشبكة الخليجية.',
            ],

            // ── Opinion articles (4) ─────────────────────────────────────────
            [
                'category' => 'opinion',
                'type' => ArticleType::Opinion,
                'slug_hint' => 'digital-economy-vision',
                'title' => 'رؤية اقتصاد المعرفة: فرصة لا يجب أن تضيع',
                'excerpt' => 'في ظل التحولات المتسارعة التي يشهدها الاقتصاد العالمي، بات من الضروري أن يُسرّع الأردن خطاه نحو تحوّل حقيقي وشامل نحو اقتصاد المعرفة، لا يقتصر على الشعارات بل يتجسّد في سياسات وبرامج تُعيد رسم خارطة الإنتاج الوطني.',
            ],
            [
                'category' => 'opinion',
                'type' => ArticleType::Opinion,
                'slug_hint' => 'water-security-strategy',
                'title' => 'الأمن المائي: أزمة لا تحتمل الانتظار',
                'excerpt' => 'لم يعد الحديث عن شحّ المياه في الأردن ترفًا أكاديميًا، بل باتت الأزمة المائية تطرق أبواب الحياة اليومية بقوة. الوقت ينفد لبناء استراتيجية وطنية شاملة تتجاوز الحلول الترقيعية إلى رؤية جذرية مستدامة.',
            ],
            [
                'category' => 'opinion',
                'type' => ArticleType::Opinion,
                'slug_hint' => 'education-reform-needed',
                'title' => 'إصلاح التعليم: لماذا تتعثّر الإصلاحات دومًا؟',
                'excerpt' => 'تُجمع الدراسات والتقارير الدولية على أن منظومة التعليم الأردنية تحتاج إلى إصلاح عميق، غير أن المحاولات المتكررة تصطدم بعقبات هيكلية وإدارية تجعل الوضع يراوح مكانه. أين تكمن جذور المشكلة الحقيقية؟',
            ],
            [
                'category' => 'opinion',
                'type' => ArticleType::Opinion,
                'slug_hint' => 'regional-integration-chance',
                'title' => 'التكامل الإقليمي: نافذة الأردن على المستقبل',
                'excerpt' => 'يقف الأردن اليوم أمام فرصة تاريخية نادرة للاستفادة من موقعه الجغرافي وعلاقاته المتوازنة لقيادة مشاريع التكامل الإقليمي في البنية التحتية والطاقة والتجارة، شريطة أن تتخلص السياسة الخارجية من خطاب الاستجداء وتتبنّى لغة الشراكة الندّية.',
            ],
        ];
    }
}

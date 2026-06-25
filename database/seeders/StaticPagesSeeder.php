<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * يُنشئ الصفحات الثابتة القياسية (من نحن/الخصوصية/الاستخدام/الشروط/أعلن معنا) منشورةً
 * وظاهرةً في التذييل. قابل للتشغيل المتكرر دون تكرار (idempotent عبر firstOrCreate
 * بالـ locale+slug) — لا يدهس تعديلات المحرّر اللاحقة. المحتوى نصّ مبدئيّ يراجعه/يحرّره
 * المدير من اللوحة (خصوصاً الصفحات القانونية).
 *
 * @author Fakhri Al-Najjar <arrmjo@gmail.com>
 */
class StaticPagesSeeder extends Seeder
{
    public function run(): void
    {
        // مالك افتراضي = المدير الأساسي (إن وُجد) — يُنشأ في SuperAdminSeeder قبله.
        $authorId = User::query()->oldest('id')->value('id');

        foreach ($this->pages() as $sort => $page) {
            Page::firstOrCreate(
                ['locale' => 'ar', 'slug' => $page['slug']],
                [
                    'author_id' => $authorId,
                    'published_by_id' => $authorId,
                    'status' => PageStatus::Published->value,
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'seo_title' => $page['title'],
                    'seo_description' => $page['excerpt'],
                    'show_in_header' => false,
                    'show_in_footer' => true,
                    'sort_order' => $sort + 1,
                    'published_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array<int, array{slug:string,title:string,excerpt:string,content:string}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'about-us',
                'title' => 'من نحن',
                'excerpt' => 'تعرّف على رؤيتنا ورسالتنا والفريق الذي يقف خلف المنصّة.',
                'content' => '<p>هذه صفحة «من نحن» المبدئية. حرّر هذا المحتوى من لوحة الإدارة ليعكس رؤية مؤسستك ورسالتها.</p>',
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'سياسة الخصوصية',
                'excerpt' => 'كيف نجمع بياناتك ونستخدمها ونحميها.',
                'content' => '<p>هذه سياسة خصوصية مبدئية. يجب على المؤسسة مراجعتها وتحديثها بما يوافق تشريعات الخصوصية المعمول بها قبل النشر النهائي.</p>',
            ],
            [
                'slug' => 'usage-policy',
                'title' => 'سياسة الاستخدام',
                'excerpt' => 'القواعد والضوابط التي تحكم استخدام الموقع وخدماته.',
                'content' => '<p>هذه سياسة استخدام مبدئية. حرّرها من لوحة الإدارة لتحديد قواعد استخدام الموقع وخدماته.</p>',
            ],
            [
                'slug' => 'terms',
                'title' => 'الشروط والأحكام',
                'excerpt' => 'الشروط والأحكام التي تنظّم العلاقة بينك وبين المنصّة.',
                'content' => '<p>هذه شروط وأحكام مبدئية. يجب مراجعتها قانونياً وتحديثها قبل النشر النهائي.</p>',
            ],
            [
                'slug' => 'advertise',
                'title' => 'أعلن معنا',
                'excerpt' => 'فرص الإعلان والشراكات التجارية على منصّتنا.',
                'content' => '<p>هذه صفحة «أعلن معنا» المبدئية. أضف تفاصيل التواصل وفرص الإعلان من لوحة الإدارة.</p>',
            ],
        ];
    }
}

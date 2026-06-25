<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            // الصفحات الثابتة القياسية (محتوى حقيقي — بعد المدير ليُنسَب إليه التأليف).
            StaticPagesSeeder::class,
            // المجموعة الافتراضية الوحيدة لحملات واتساب («مشتركو الموقع»).
            WhatsappGroupsSeeder::class,
        ]);

        if (app()->isLocal()) {
            $this->call([DemoContentSeeder::class]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WhatsappGroup;
use Illuminate\Database\Seeder;

/**
 * المجموعة الافتراضية الوحيدة لنظام حملات واتساب: «مشتركو الموقع» (Default) —
 * وجهة الاشتراك التلقائية من الموقع. أي مجموعات أخرى تُنشأ من لوحة الإدارة.
 * idempotent: قابل للتشغيل المتكرر دون تكرار.
 */
class WhatsappGroupsSeeder extends Seeder
{
    public function run(): void
    {
        WhatsappGroup::query()->firstOrCreate(
            ['name' => 'مشتركو الموقع'],
            ['description' => 'المشتركون من الموقع تلقائياً', 'is_default' => true],
        );
    }
}

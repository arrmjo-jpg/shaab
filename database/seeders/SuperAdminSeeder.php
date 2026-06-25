<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * ينشئ حساب مدير النظام الافتراضي إذا لم يكن موجوداً.
 * قابل للتشغيل المتكرر دون تكرار (idempotent).
 *
 * @author Fakhri Al-Najjar <arrmjo@gmail.com>
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = User::updateOrCreate(
            ['email' => 'arrmjo@gmail.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('01020304'),
                'status' => UserStatus::Active,
                // مؤكَّد افتراضياً — وإلا حُبس المدير الأساسي بقاعدة تأكيد البريد
                'email_verified_at' => now(),
            ]
        );

        // تعيين الدور بعد التأكد من عدم التكرار
        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }
    }
}

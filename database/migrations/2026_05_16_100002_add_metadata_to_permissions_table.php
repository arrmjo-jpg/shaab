<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('group')->nullable()->after('display_name')->index();
            $table->text('description')->nullable()->after('group');

            $table->index('name');
            $table->index('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['guard_name']);
            $table->dropIndex(['group']);
            $table->dropColumn(['display_name', 'group', 'description']);
        });
    }
};

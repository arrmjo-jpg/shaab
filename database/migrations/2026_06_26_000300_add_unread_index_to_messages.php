<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس (conversation_id, created_at) لخدمة استعلام عدّاد غير المقروء
 * (created_at > last_read_at لكل محادثة). يكمّل فهرس (conversation_id, id) الخاص
 * بترقيم المؤشّر. idempotent عبر hasIndex — آمن على الجديد والمُهاجَر سابقاً
 * (درس team: لا نعدّل migration قد يكون شُغِّل).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('messages', ['conversation_id', 'created_at'])) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('messages', 'messages_conversation_created_idx')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropIndex('messages_conversation_created_idx');
            });
        }
    }
};

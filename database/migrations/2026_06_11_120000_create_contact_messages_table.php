<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->string('type')->index();              // ContactMessageType
            $table->text('message');
            $table->string('status')->default('new');     // ContactMessageStatus — مصدر Badge
            $table->timestamp('read_at')->nullable();     // seen metadata فقط (لا يقود Badge)
            $table->text('reply_body')->nullable();       // نصّ الردّ
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();             // ip / user_agent
            $table->timestamps();
            $table->softDeletes();

            // عدّاد Badge السريع: count(status='new')؛ والقوائم المُرتَّبة بالحالة/التاريخ.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

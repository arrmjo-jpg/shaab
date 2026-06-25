<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظام حملات واتساب — الأساس: مجموعات + جهات اتصال (phone واحد E.164) + حملات
 * (إعلانية/خبر) + سجلّ رسائل لكلّ مستلم (سبب الفشل عند توفّره). بسيط بلا وسوم/فلاتر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('description', 500)->nullable();
            // مجموعة افتراضية واحدة (مشتركو الموقع) — وجهة اشتراك الموقع التلقائية.
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            // رقم دولي كامل E.164 في حقل واحد فقط (لا country_code) — فريد لمنع التكرار.
            $table->string('phone', 20)->unique();
            $table->string('status', 20)->default('subscribed')->index(); // subscribed|unsubscribed
            $table->string('unsubscribe_token', 64)->unique();
            $table->string('source', 20)->default('manual'); // manual|import|api
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_contact_group', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->foreignId('whatsapp_group_id')->constrained('whatsapp_groups')->cascadeOnDelete();
            $table->unique(['whatsapp_contact_id', 'whatsapp_group_id'], 'wa_contact_group_unique');
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('type', 20)->index();   // promo|article
            $table->string('status', 20)->default('draft')->index(); // draft|scheduled|sending|completed|failed|cancelled
            $table->text('message_text')->nullable();
            $table->string('media_type', 10)->default('none'); // none|image|video
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            // منع إنشاء حملة مكررة بالخطأ (hash للمحتوى+المجموعات ضمن نافذة قصيرة).
            $table->string('dedupe_hash', 64)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_campaign_group', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('whatsapp_group_id')->constrained('whatsapp_groups')->cascadeOnDelete();
            $table->unique(['whatsapp_campaign_id', 'whatsapp_group_id'], 'wa_campaign_group_unique');
        });

        Schema::create('whatsapp_campaign_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->string('phone', 20); // لقطة الرقم وقت الإرسال (يصمد لو حُذف المشترك)
            $table->string('status', 10)->default('pending'); // pending|sent|failed
            $table->string('provider_message_id', 100)->nullable();
            $table->text('error')->nullable(); // سبب الفشل لكل رسالة عند توفره
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            // idempotency: رسالة واحدة لكل رقم داخل الحملة + فهرس متابعة الحالة.
            $table->unique(['whatsapp_campaign_id', 'phone'], 'wa_campaign_phone_unique');
            $table->index(['whatsapp_campaign_id', 'status'], 'wa_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_messages');
        Schema::dropIfExists('whatsapp_campaign_group');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_contact_group');
        Schema::dropIfExists('whatsapp_contacts');
        Schema::dropIfExists('whatsapp_groups');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_migration_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('wp_migration_runs')->cascadeOnDelete();

            // هوية المصدر الثابتة: 'att:{wp_attachment_id}' أو 'url:{sha1(url)}'.
            $table->string('source_key', 191);
            $table->unsignedBigInteger('wp_attachment_id')->nullable();
            $table->string('source_url', 2048)->nullable();

            // يُربط بأصل MediaAsset موجود عند إعادة الاستخدام (dedup) أو المُنشأ حديثاً.
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('checksum', 64)->nullable();

            $table->string('status', 20)->default('pending');
            $table->timestamp('imported_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'source_key']);
            $table->index(['run_id', 'status']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_migration_media');
    }
};

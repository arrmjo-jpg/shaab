<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجمهور ككيان أوّل-درجة (cohort مُسمّى). type يحدّد الـresolver؛ params مثل team_id
 * لمتابعي فريق بعينه؛ custom يستند لقواعد segment. channel_reachability = القنوات التي يصلها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_audiences', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->string('type', 30)->index(); // all|logged|guests|sports_followers|…|custom
            $table->json('params')->nullable();
            $table->foreignId('segment_id')->nullable()->constrained('notification_segments')->nullOnDelete();
            $table->json('channel_reachability')->nullable();
            $table->boolean('is_preset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_audiences');
    }
};

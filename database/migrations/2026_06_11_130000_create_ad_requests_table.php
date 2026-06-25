<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone');
            $table->string('website')->nullable();          // اختياريّ
            $table->string('ad_type');
            $table->string('budget')->nullable();           // تقريبيّة، اختياريّة (نصّ يسمح بالنطاقات)
            $table->text('description');
            $table->string('status')->default('new');       // AdRequestStatus — مصدر Badge
            $table->timestamp('read_at')->nullable();       // seen metadata فقط
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('meta')->nullable();               // ip / user_agent
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_requests');
    }
};

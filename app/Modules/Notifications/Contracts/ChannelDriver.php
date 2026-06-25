<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Support\ChannelCapabilities;
use App\Modules\Notifications\Support\ChannelHealth;
use App\Modules\Notifications\Support\ChannelMessage;
use App\Modules\Notifications\Support\RecipientBatch;
use App\Modules\Notifications\Support\SendReport;
use App\Modules\Notifications\Support\ValidationResult;

/**
 * عقد قناة الإرسال — ناقل أصمّ: لا يعرف Events ولا Campaigns ولا Audiences. يصف نفسه
 * (key/addressing/capabilities)، مُبوَّب بالإعداد (isConfigured)، ويُرجِع SendReport مُهيكلاً
 * ولا يرمي استثناءً إلى الطبقات العليا (نمط App\Support\Whatsapp\WhatsappSendResult).
 */
interface ChannelDriver
{
    public function key(): ChannelKey;

    public function addressing(): AddressingModel;

    public function capabilities(): ChannelCapabilities;

    /** هل القناة مفعّلة في الإعدادات؟ (مدخل effective_state، منفصل عن وجود الاعتماد). */
    public function enabled(): bool;

    /** بوّابة الإرسال: enabled + وجود الاعتماد (نمط UltraMsgClient::isConfigured). */
    public function isConfigured(): bool;

    /** صحّة القناة — للسجلّ المركزيّ ولبوّابتَي التوفّر (تخطيط/إرسال). */
    public function health(): ChannelHealth;

    public function validate(ChannelMessage $message): ValidationResult;

    /**
     * يُرسِل الرسالة لدفعة مستلمين (per_recipient) أو topic. لا يرمي أبداً — يُعيد SendReport.
     * $idempotencyKey يمنع الإرسال المزدوج عند إعادة المحاولة.
     */
    public function send(ChannelMessage $message, RecipientBatch $recipients, string $idempotencyKey): SendReport;
}

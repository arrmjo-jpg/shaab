<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsappMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالة واحدة داخل حملة واتساب — سجلّ تشغيلي عالي الحجم (آلاف الصفوف لكل حملة):
 * لقطة الرقم + الحالة + معرف المزود + سبب الفشل عند توفره.
 *
 * استثناء موثَّق من model-audit: بلا AuditsChanges عمداً — تدقيق كل صف هنا يعني آلاف
 * أسطر activity_log لكل حملة (إغراق بلا قيمة تحريرية)، والكتابة دفعية (insert/update
 * جماعي من الـ Jobs). تدقيق الحملة نفسها (WhatsappCampaign) يغطي القرار التحريري،
 * مرآةُ نهج عدّادات التفاعل/الإعلانات الدفعية.
 */
class WhatsappCampaignMessage extends Model
{
    protected $fillable = [
        'whatsapp_campaign_id', 'whatsapp_contact_id', 'phone',
        'status', 'provider_message_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsappMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class, 'whatsapp_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'whatsapp_contact_id');
    }
}

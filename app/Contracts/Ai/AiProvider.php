<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

/**
 * عقد مزوّد نموذج لغوي قابل للتبديل (OpenAI / Anthropic / مستقبليّ).
 *
 * هذا هو محور التبديل الوحيد: المنطق التحريري (AiEditorialService) يعتمد على
 * هذا العقد فقط ولا يعرف المزوّد الفعلي — لا قفل على مزوّد، ولا تكرار للمنطق.
 */
interface AiProvider
{
    /**
     * يرسل محادثة ويُعيد نصّ المساعد الخام.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  array<string,mixed>  $options  خيارات اختيارية (json, temperature, max_tokens)
     *
     * @throws \RuntimeException عند فشل النقل/المصادقة/الاستجابة غير الصالحة
     */
    public function chat(array $messages, array $options = []): string;

    /** هل المزوّد مُهيّأ (مفتاح موجود) ويمكن النداء؟ */
    public function configured(): bool;

    /** المعرّف القصير للمزوّد (openai|anthropic|…) — للتشخيص. */
    public function name(): string;
}

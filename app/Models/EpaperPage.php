<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EpaperStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفحة عدد بنصّها المُستخرَج (OCR — Phase 4a). تشغيليّة بحتة (غير مُدقَّقة):
 * تُعاد كتابتها كلياً عند كل استخراج. أساس بحث «داخل العدد» (Phase 4b، DB) و«الأرشيف
 * العابر» (Enterprise Hardening — Meilisearch، وثيقة لكل صفحة).
 *
 * فهرسة البحث **تُدار صراحةً** عبر EpaperSearchIndexer (لا سمة Scout self-syncing):
 * دورة حياة الصفحة كتليّة (حذف+إعادة بناء عند كل OCR، وهو مسارٌ يتجاوز أحداث النموذج)
 * وتتعاقب من حالة العدد (نشر/إلغاء/تحديث وصول) — وكلاهما لا يناسب مزامنة Scout لكل
 * نموذج. الفهرس مُغنىً بحقول العدد فيُفرَض الوصول **داخل المحرّك** (صفر تسريب) ويُتاح
 * التنقّل الدقيق. توصيف الوثيقة/الأهليّة هنا (مصدر الحقيقة للمخطّط)، والمزامنة هناك.
 */
class EpaperPage extends Model
{
    /** اسم فهرس Meilisearch (يطابق مفتاح config/scout.php → meilisearch.index-settings). */
    public const SEARCH_INDEX = 'epaper_pages_index';

    protected $fillable = [
        'epaper_id',
        'page_number',
        'text',
        'source',
        'has_text',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'has_text' => 'boolean',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperPage> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }

    // ─── Search index schema (مصدر الحقيقة؛ المزامنة في EpaperSearchIndexer) ──

    /**
     * أهليّة الفهرسة: نصّ صفحةٍ لعددٍ منشورٍ غير مجدوَل وغير محذوف فقط — نظافة الفهرس
     * + دفاع بالعمق. تغيُّر حالة العدد يُعالَج بإعادة فهرسة صفحاته صراحةً.
     */
    public function isSearchable(): bool
    {
        if (! $this->has_text) {
            return false;
        }

        $issue = $this->relationLoaded('epaper') ? $this->epaper : $this->epaper()->first();

        return $issue !== null
            && $issue->deleted_at === null
            && $issue->status === EpaperStatus::Published
            && $issue->published_at !== null
            && ! $issue->published_at->isFuture();
    }

    /**
     * وثيقة الفهرس: نصّ الصفحة + ميتاداتا العدد المُغناة. access_level/locale يُمكّنان
     * فرض الوصول داخل المحرّك (filter)؛ issue_* للعرض والتنقّل؛ publication_date رقم
     * (طابع زمنيّ) للترشيح بالمدى والترتيب. يُمرَّر العدد لتفادي N+1 أثناء البناء الكتليّ.
     *
     * @return array<string,mixed>
     */
    public function toSearchDocument(?Epaper $issue = null): array
    {
        $issue ??= ($this->relationLoaded('epaper') ? $this->epaper : $this->epaper()->first());

        return [
            'id' => $this->id,
            'epaper_id' => $this->epaper_id,
            'page_number' => $this->page_number,
            'text' => (string) $this->text,
            'locale' => $issue?->locale,
            'access_level' => $issue?->access_level?->value,
            'issue_number' => $issue?->issue_number,
            'issue_title' => (string) $issue?->title,
            'issue_subtitle' => (string) $issue?->subtitle,
            'issue_slug' => $issue?->slug,
            'page_count' => $issue?->page_count,
            'publication_date' => $issue?->publication_date?->getTimestamp(),
        ];
    }
}

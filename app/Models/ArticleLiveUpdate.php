<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * تحديث ضمن خط زمني تغطية حيّة (P8) — تابع لمقال type=live.
 *
 * المحتوى يعيد استخدام بنية TipTap: content_json مصدر الحقيقة،
 * content عرض HTML مشتقّ مُعقَّم (مطابق لنمط Article).
 *
 * الترتيب: المثبّت أولاً ثم happened_at تنازلياً.
 */
class ArticleLiveUpdate extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'live_update';

    /**
     * المحتوى الطويل لا يُدقَّق (مطابق لاستثناء Article للمحتوى).
     *
     * @var array<int,string>
     */
    protected array $auditAttributes = [
        'article_id', 'author_id', 'title', 'is_pinned', 'is_breaking', 'is_featured', 'happened_at',
    ];

    protected $fillable = [
        'article_id', 'author_id', 'title',
        'content_json', 'content', 'is_pinned', 'is_breaking', 'is_featured', 'position', 'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'is_pinned' => 'boolean',
            'is_breaking' => 'boolean',
            'is_featured' => 'boolean',
            'happened_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * وسائط التحديث من المكتبة المركزية — يعيد استخدام جدول الإسناد المشترك
     * (article_media عبر live_update_id). نفس بنية وسائط المقال.
     */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'article_media', 'live_update_id', 'media_asset_id')
            ->withPivot(['collection', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * ترتيب خط زمني موحّد: المثبّت أولاً، ثم الترتيب التحريري الصريح (position
     * تنازلياً = الأعلى)، ثم زمن الحدث تنازلياً، ثم id كقاطع تعادل نهائي.
     */
    public function scopeTimelineOrder(Builder $q): Builder
    {
        return $q->orderByDesc('is_pinned')
            ->orderByDesc('position')
            ->orderByDesc('happened_at')
            ->orderByDesc('id');
    }

    public function scopeForArticle(Builder $q, int $articleId): Builder
    {
        return $q->where('article_id', $articleId);
    }
}

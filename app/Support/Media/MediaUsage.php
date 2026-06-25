<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * مصدر الحقيقة الوحيد لاستخدام أصل وسائط — أيّ محتوى يُسنِد هذا الأصل عبر مفتاح أجنبي.
 *
 * يُستخدَم في ثلاثة مواضع كي لا تتكرّر قوائم العلاقات المبعثرة (السبب الجذريّ لعيب
 * فقدان الوسائط: حُذِف/نُظِّف أصلٌ تستخدمه الفيديوهات/الأعداد لأنّ الحارس عدّ المقالات
 * والريلز فقط):
 *   - حارس الحذف اليدوي              (DeleteMediaAssetAction)
 *   - تنظيف الأصول اليتيمة المُجدوَل  (PruneOrphanMediaAssetsAction)
 *   - عدّاد الاستخدام في القائمة/التفصيل (ListMediaAssetsAction + MediaAssetResource)
 *
 * المالك المحذوف ناعماً (soft-deleted) يبقى مالكاً قابلاً للاسترجاع ⇒ يُحتسَب ضمن
 * الاستخدام (withTrashed حيثما يدعم النموذجُ الحذفَ الناعم) فلا يُحذف أصلٌ تستعيده
 * محتوياتٌ من السلّة. جدول wp_migration_media مُستثنى عمداً: سِجلّ استيراد/إزالة-تكرار
 * (ledger) لا «محتوى يستخدم الأصل»؛ تضمينه يثبّت الأصل للأبد ويُعطّل التنظيف.
 */
final class MediaUsage
{
    /**
     * علاقات الاستهلاك المُعرَّفة على MediaAsset — القائمة الوحيدة المعتمدة.
     * تشمل: وسائط المقالات والتغطيات الحيّة (pivot)، صورة og للمقال، الريلز، الفيديو،
     * أغلفة تصنيفات/قوائم الفيديو، أغلفة البثّ وتصنيفاته، الأعداد ونُسخها.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'articles',
        'liveUpdates',
        'articleOgImages',
        'reels',
        'videos',
        'videoCategories',
        'videoPlaylists',
        'broadcasts',
        'broadcastCategories',
        'epapers',
        'epaperVersions',
    ];

    /** هل الأصل مُستخدَم من أيّ مستهلك؟ (يقصُر عند أوّل تطابق). */
    public static function inUse(MediaAsset $asset): bool
    {
        foreach (self::RELATIONS as $relation) {
            if (self::withTrashedIfSupported($asset->{$relation}())->exists()) {
                return true;
            }
        }

        return false;
    }

    /** إجماليّ مرّات الاستخدام عبر كلّ المستهلكين (للحارس وعدّاد الواجهة). */
    public static function count(MediaAsset $asset): int
    {
        $total = 0;
        foreach (self::RELATIONS as $relation) {
            $total += self::withTrashedIfSupported($asset->{$relation}())->count();
        }

        return $total;
    }

    /**
     * يقيّد استعلام MediaAsset على الأصول اليتيمة فعلاً (غير المُسنَدة لأيّ مستهلك).
     * يُطبَّق في-المكان: يُضيف whereDoesntHave لكلّ علاقة (مع المحذوف ناعماً حيثما يُدعَم).
     */
    public static function constrainUnused(Builder $query): void
    {
        foreach (self::RELATIONS as $relation) {
            $query->whereDoesntHave($relation, static function (Builder $q): void {
                self::withTrashedIfSupported($q);
            });
        }
    }

    /**
     * محدّدات withCount/loadCount — عمود عدّ لكلّ علاقة يضمّ المحذوف ناعماً حيثما يُدعَم.
     *
     * @return array<string,\Closure>
     */
    public static function countSelectors(): array
    {
        $selectors = [];
        foreach (self::RELATIONS as $relation) {
            $selectors[$relation] = static function (Builder $q): void {
                self::withTrashedIfSupported($q);
            };
        }

        return $selectors;
    }

    /**
     * يجمع أعمدة *_count المُحمَّلة (withCount/loadCount) → usage_count موثوق عبر كلّ
     * المستهلكين. يُعيد null إن لم تُحمَّل أيّ عدّادات (سياق لا يطلب الاستخدام).
     */
    public static function sumLoadedCounts(MediaAsset $asset): ?int
    {
        $present = false;
        $total = 0;

        foreach (self::RELATIONS as $relation) {
            $value = $asset->getAttribute(Str::snake($relation).'_count');
            if ($value !== null) {
                $present = true;
                $total += (int) $value;
            }
        }

        return $present ? $total : null;
    }

    /**
     * يُضيف withTrashed إن كان النموذج المرتبط يستخدم الحذف الناعم — وإلّا يتركه كما هو
     * (استدعاء withTrashed على نموذج بلا SoftDeletes يرمي استثناءً). يقبل علاقة (سياق
     * العدّ على نموذج) أو Builder (سياق whereDoesntHave/withCount).
     */
    private static function withTrashedIfSupported(Relation|Builder $query): Relation|Builder
    {
        $related = $query instanceof Relation ? $query->getRelated() : $query->getModel();

        if (self::usesSoftDeletes($related)) {
            $query->withTrashed();
        }

        return $query;
    }

    private static function usesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}

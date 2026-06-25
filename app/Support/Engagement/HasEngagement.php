<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Models\Engagement;
use App\Models\EngagementCounter;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * يُكسِب أي نموذج محتوى قدرات التفاعل الموحّدة (تفاعلات + عدّاد). يُستخدَم في
 * Article الآن، وأي نوع مستقبلي (Reel/Video/Stream/PrintedEdition) لاحقاً.
 */
trait HasEngagement
{
    public function engagements(): MorphMany
    {
        return $this->morphMany(Engagement::class, 'engageable');
    }

    public function engagementCounter(): MorphOne
    {
        return $this->morphOne(EngagementCounter::class, 'engageable');
    }

    /** مقاييس مُجمَّعة جاهزة للعرض (تعتمد العدّاد المُحمَّل مسبقاً إن وُجد). */
    public function engagementMetrics(): array
    {
        $c = $this->relationLoaded('engagementCounter')
            ? $this->getRelation('engagementCounter')
            : $this->engagementCounter;

        return [
            'views' => (int) ($c->views ?? 0),
            'likes' => (int) ($c->likes ?? 0),
            'dislikes' => (int) ($c->dislikes ?? 0),
            'favorites' => (int) ($c->favorites ?? 0),
        ];
    }
}

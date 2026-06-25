<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * عدّادات تفاعل مُجمَّعة لكل هدف محتوى (مصدر مقاييس المنصّة). لا عدّ وقت تشغيل.
 */
class EngagementCounter extends Model
{
    protected $fillable = [
        'engageable_type', 'engageable_id', 'likes', 'dislikes', 'favorites', 'views',
    ];

    protected function casts(): array
    {
        return [
            'likes' => 'integer',
            'dislikes' => 'integer',
            'favorites' => 'integer',
            'views' => 'integer',
        ];
    }

    public function engageable(): MorphTo
    {
        return $this->morphTo();
    }
}

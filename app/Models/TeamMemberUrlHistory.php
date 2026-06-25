<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية لأعضاء الفريق — التقاط canonical القديم عند تغيّر slug
 * (للأعضاء النشِطين)، يُستهلَك من TeamMemberRedirectResolver لإعادة توجيه 301.
 * مرآة PageUrlHistory. old_path → team_member_id (مؤشّر مباشر، خالٍ من الحلقات).
 */
class TeamMemberUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'team_member_url_history';

    protected $fillable = [
        'team_member_id', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}

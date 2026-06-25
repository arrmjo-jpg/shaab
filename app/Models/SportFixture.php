<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * مرآة موعد مباراة من 365 (نظام إشعارات «تابع»). **عمدًا بلا AuditsChanges**: هذا ليس موديل نطاق ذا تاريخ تغيير
 * دلاليّ، بل **مرآة بيانات خارجيّة تُزامَن دوريًّا** (كلّ ~10د، تحديثات آليّة كثيفة) — أشبه بالكاش. تدقيقها يُغرق
 * activity_log بالضجيج ويضخّم الكتابة بلا فائدة تدقيقيّة. (استثناء موثَّق لقاعدة «كلّ موديل مُدقَّق» — راجع model-audit.)
 * لا أسرار/PII. الكتابة عبر updateOrCreate (المزامنة) — لا تجاوز أحداث Eloquent (الموديل غير مُدقَّق أصلًا).
 */
class SportFixture extends Model
{
    protected $fillable = [
        'game_id',
        'competition_id',
        'season_num',
        'home_team_id',
        'home_name',
        'away_team_id',
        'away_name',
        'status',
        'start_at',
        'last_event_id',
        'next_poll_at',
    ];

    protected function casts(): array
    {
        return [
            'game_id' => 'integer',
            'competition_id' => 'integer',
            'season_num' => 'integer',
            'home_team_id' => 'integer',
            'away_team_id' => 'integer',
            'last_event_id' => 'integer',
            'start_at' => 'datetime',
            'next_poll_at' => 'datetime',
        ];
    }

    /** مباريات لم تنتهِ (مجدولة/جارية) — مرشَّحة للتذكير/الاستطلاع. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'live']);
    }

    /** مباريات تخصّ فريقًا (مضيفًا أو ضيفًا). */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId));
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Models\TeamMember;
use App\Support\Cache\TeamMemberCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إعادة ترتيب الأعضاء بقائمة معرّفات مرتّبة (sort_order = الفهرس). يُحدِّث فقط الصفوف
 * التي تغيّر ترتيبها — نتيجة حتمية مستقرّة. يمرّ عبر save() (لا تجاوز للأحداث) احتراماً
 * لقاعدة model-audit.
 *
 * @param  array<int,int>  $ids
 */
class ReorderTeamMembersAction
{
    public function handle(array $ids): JsonResponse
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                $member = TeamMember::find($id);
                if ($member !== null && $member->sort_order !== $position) {
                    $member->forceFill(['sort_order' => $position])->save();
                }
            }
        });

        // الترتيب يؤثّر على القائمة العامة (مُجمّعة/مرتّبة) — أبطِل وسم القائمة.
        Cache::tags(TeamMemberCacheTags::feedTags())->flush();

        return ApiResponse::success(__('team.reordered'));
    }
}

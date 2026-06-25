<?php

declare(strict_types=1);

namespace App\Actions\Admin\Team;

use App\Http\Resources\Admin\Team\TeamMemberResource;
use App\Models\TeamMember;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListTeamMembersAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(TeamMember::class)
            ->with('avatarAsset') // eager-load — يمنع N+1 على عمود الصورة في القائمة
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('department'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts('id', 'name', 'sort_order', 'created_at')
            ->defaultSort('sort_order', 'id');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $members = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: TeamMemberResource::collection($members)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $members->total(),
                    'count' => $members->count(),
                    'per_page' => $members->perPage(),
                    'current_page' => $members->currentPage(),
                    'total_pages' => $members->lastPage(),
                ],
            ]
        );
    }
}

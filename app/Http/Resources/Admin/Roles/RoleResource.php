<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Roles;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد الدور — يتضمن صلاحياته مجمّعة حسب المجموعة + عدّادات.
 */
class RoleResource extends JsonResource
{
    /** الأدوار النظامية المبذورة (محميّة جزئياً). */
    private const SYSTEM_ROLES = [
        'super_admin', 'editor', 'reviewer', 'moderator',
        'social_media_manager', 'journalist', 'contributor', 'user',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'is_system' => in_array($this->name, self::SYSTEM_ROLES, true),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toISOString(),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions
                ->groupBy('group')
                ->map(fn ($items, $group) => [
                    'group' => $group,
                    'items' => $items->map(fn ($p) => [
                        'name' => $p->name,
                        'display_name' => $p->display_name,
                    ])->values(),
                ])
                ->values()
            ),
        ];
    }
}

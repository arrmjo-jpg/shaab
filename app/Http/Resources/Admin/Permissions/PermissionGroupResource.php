<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Permissions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد مجموعة الصلاحيات (كيان حقيقي) — مع صلاحياتها وعدّادها.
 */
class PermissionGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_system' => $this->is_system,
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions
                ->map(fn ($p) => [
                    'name' => $p->name,
                    'display_name' => $p->display_name,
                    'description' => $p->description,
                ])->values()
            ),
        ];
    }
}

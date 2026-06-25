<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد المستخدم في وحدة إدارة المستخدمين (سياق الإدارة).
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'email_verified' => $this->email_verified_at !== null,
            'is_admin' => $this->whenLoaded('roles', fn () => $this->isAdmin()),
            'is_writer' => (bool) $this->is_writer,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'social_links' => $this->social_links ?? [],
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])),
        ];
    }
}

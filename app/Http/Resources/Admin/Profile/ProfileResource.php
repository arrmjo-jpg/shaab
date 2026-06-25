<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * بروفيل المستخدم الذاتي. بدون permissions — /admin/auth/me يوفّرها
 * بالفعل لبوابات الواجهة (تجنّب تكرار العقد).
 */
class ProfileResource extends JsonResource
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
            'is_writer' => (bool) $this->is_writer,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'social_links' => $this->social_links ?? [],
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at->toISOString(),
            'roles' => $this->roles->map(fn ($role) => [
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values(),
        ];
    }
}

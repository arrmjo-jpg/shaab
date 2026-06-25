<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد مصادقة الإدارة — يتضمن بيانات المستخدم + الأدوار + الـ token.
 */
class AdminAuthResource extends JsonResource
{
    private string $token;

    public function __construct(mixed $resource, string $token)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status->value,
                'roles' => $this->getRoleNames(),
                'last_login_at' => $this->last_login_at?->toISOString(),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * جلسة Sanctum (توكن وصول شخصي) للمستخدم.
 */
class ProfileSessionResource extends JsonResource
{
    private int $currentId;

    public function __construct(mixed $resource, int $currentId)
    {
        parent::__construct($resource);
        $this->currentId = $currentId;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'current' => $this->id === $this->currentId,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * مجموعة مع تمرير مُعرّف التوكن الحالي لكل عنصر.
     */
    public static function for(iterable $tokens, int $currentId): array
    {
        $out = [];
        foreach ($tokens as $token) {
            $out[] = (new self($token, $currentId))->resolve();
        }

        return $out;
    }
}

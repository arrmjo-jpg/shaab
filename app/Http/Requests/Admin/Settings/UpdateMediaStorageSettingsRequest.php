<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use App\Http\Requests\BaseFormRequest;
use App\Support\Security\SafeUrl;
use Closure;

class UpdateMediaStorageSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remote_enabled' => ['sometimes', 'boolean'],
            'remote_driver' => ['sometimes', 'string', 'in:s3'],
            'remote_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_bucket' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_region' => ['sometimes', 'nullable', 'string', 'max:50'],
            // SSRF: نقطة النهاية يتّصل بها الخادم — يجب أن تكون https على مضيف عام.
            'remote_endpoint' => ['sometimes', 'nullable', 'string', 'max:255', self::safeEndpointRule()],
            'remote_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_use_path_style' => ['sometimes', 'boolean'],
        ];
    }

    /** قاعدة مشتركة: endpoint غير فارغ يجب أن يكون https على مضيف عام (anti-SSRF). */
    public static function safeEndpointRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '' && ! SafeUrl::isPublicHttps($value)) {
                $fail(__('setting.media_endpoint_unsafe'));
            }
        };
    }
}

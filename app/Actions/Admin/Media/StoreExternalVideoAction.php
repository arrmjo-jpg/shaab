<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\ExternalVideoResolver;
use Illuminate\Support\Str;

/**
 * إنشاء فيديو خارجي كأصل مكتبة مركزي (Wave 2 — لا نموذج منفصل).
 *
 * - يُحَلّ الرابط عبر ExternalVideoResolver (مُتحقَّق مسبقاً في الطلب).
 * - dedupe: نفس (provider, provider_id) أو نفس embed_url ⇒ يُعاد الأصل الموجود.
 * - أصل بلا ملف: disk='external'، لا مشتقّات ولا طابور تحويل.
 */
class StoreExternalVideoAction
{
    public function handle(string $url, User $actor): MediaAsset
    {
        /** @var array{provider:string,provider_id:?string,embed_url:string,source_url:string,poster_url:?string} $r */
        $r = ExternalVideoResolver::resolve($url);

        $existing = MediaAsset::query()
            ->where('kind', 'external')
            ->when(
                $r['provider_id'] !== null,
                fn ($q) => $q->where('provider', $r['provider'])->where('provider_id', $r['provider_id']),
                fn ($q) => $q->where('embed_url', $r['embed_url']),
            )
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'kind' => 'external',
            'disk' => 'external',
            'path' => '',
            'filename' => '',
            'original_name' => Str::limit($r['source_url'], 255, ''),
            'mime_type' => 'video/external',
            'extension' => '',
            'size' => 0,
            'checksum' => hash('sha256', $r['embed_url']),
            'provider' => $r['provider'],
            'provider_id' => $r['provider_id'],
            'embed_url' => $r['embed_url'],
            'source_url' => $r['source_url'],
            'poster_url' => $r['poster_url'],
            'visibility' => MediaVisibility::Public->value,
            'uploaded_by' => $actor->id,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Requests\BaseFormRequest;
use App\Models\VideoPlaylist;
use Illuminate\Validation\Rule;

class StoreVideoPlaylistRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'locale' => ['required', 'string', Rule::in(VideoPlaylist::LOCALES)],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cover_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],
            'status' => ['sometimes', Rule::in(VideoStatus::values())],
            'visibility' => ['sometimes', Rule::in(VideoVisibility::values())],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('video_playlists', 'slug')->where(fn ($q) => $q->where('locale', $this->input('locale'))),
            ],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}

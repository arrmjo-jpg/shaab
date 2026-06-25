<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\VideoLibrary;

use App\Actions\Public\VideoLibrary\ListPublicPlaylistsAction;
use App\Actions\Public\VideoLibrary\ShowPublicPlaylistAction;
use App\Http\Controllers\Controller;
use App\Support\Content\PlaylistRedirectResolver;
use App\Support\Content\PublicSeoBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * واجهة قوائم التشغيل العامة. القائمة/التفاصيل داخل public.cache؛ redirect خارجها
 * (استجابة 301 خاصّة عبر playlist_url_history).
 */
class PlaylistController extends Controller
{
    public function index(Request $request, string $locale): JsonResponse
    {
        return (new ListPublicPlaylistsAction)->handle($locale, $request);
    }

    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicPlaylistAction)->handle($locale, $slug);
    }

    public function redirect(Request $request, string $locale): JsonResponse
    {
        $path = (string) $request->query('path', '');
        $target = $path !== '' ? PlaylistRedirectResolver::resolveByPath($locale, $path) : null;

        if ($target === null) {
            return response()->json(['message' => __('video_playlist.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Content\PublicSeoBuilder;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt — emits the sitemap index URL so search engines can
 * discover all per-locale sitemaps. Stays open by default; restrictive
 * directives belong in config when needed.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemapUrl = PublicSeoBuilder::absoluteUrl(route('sitemap.index', [], false));

        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /admin/',
            '',
            "Sitemap: {$sitemapUrl}",
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
        ]);
    }
}

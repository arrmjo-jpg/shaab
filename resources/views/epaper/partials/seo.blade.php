{{-- SSR-first SEO head for an epaper issue. Renders ONLY what EpaperSeoBuilder returns.
     Structured data (PublicationIssue/schema.org) + sitemap are deferred to the SEO concern. --}}
<title>{{ $seo['title'] }}</title>

@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif

@if (! empty($seo['canonical_url']))
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">
@endif

@if (! empty($seo['robots']))
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif

@foreach ($seo['og'] ?? [] as $ogKey => $ogValue)
    <meta property="og:{{ $ogKey }}" content="{{ $ogValue }}">
@endforeach

@foreach ($seo['twitter'] ?? [] as $twKey => $twValue)
    <meta name="twitter:{{ $twKey }}" content="{{ $twValue }}">
@endforeach

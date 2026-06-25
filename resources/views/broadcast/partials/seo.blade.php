{{-- SSR-first SEO head for a broadcast detail page. Renders ONLY what the builder
     returns (state-safe — BroadcastSeoBuilder already reflects status). --}}
<title>{{ $seo['title'] }}</title>

@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif

@if (! empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif

@if (! empty($seo['canonical_url']))
    <link rel="canonical" href="{{ $seo['canonical_url'] }}">
@endif

@if (! empty($seo['robots']))
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif

{{-- Open Graph — keys are pre-filtered by the builder (e.g. og:video* only for video kinds). --}}
@foreach ($seo['og'] ?? [] as $ogKey => $ogValue)
    <meta property="og:{{ $ogKey }}" content="{{ $ogValue }}">
@endforeach

{{-- Twitter / X card --}}
@foreach ($seo['twitter'] ?? [] as $twKey => $twValue)
    <meta name="twitter:{{ $twKey }}" content="{{ $twValue }}">
@endforeach

{{-- JSON-LD structured data (VideoObject / AudioObject + optional BroadcastEvent). --}}
@if (! empty($seo['structured_data']))
    {{-- JSON_HEX_TAG escapes < > so admin-controlled name/description can't break out of the
         <script> block (stored-XSS defense-in-depth). HEX_AMP/APOS/QUOT for completeness. --}}
    <script type="application/ld+json">{!! json_encode($seo['structured_data'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif

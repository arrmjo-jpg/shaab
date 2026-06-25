<!DOCTYPE html>
<html dir="{{ ($locale ?? 'ar') === 'en' ? 'ltr' : 'rtl' }}" lang="{{ $locale ?? 'ar' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @hasSection('seo')
        @yield('seo')
    @else
        <title>{{ config('app.name', 'AlphaCMS') }}</title>
    @endif

    @fonts
    {{-- قارئ PDF.js يُروى تدريجياً من [data-epaper-reader]؛ لا فعل له على الأرشيف. --}}
    @vite(['resources/css/app.css', 'resources/js/epaper.js', 'resources/js/ads.js'])
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased">
    @inject('newspaper', \App\Settings\NewspaperSettings::class)

    <header class="border-b border-zinc-200">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ url('/'.($locale ?? 'ar').'/epaper') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight">
                <span class="flex h-8 w-8 items-center justify-center bg-zinc-900 text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 5h13v14H6a2 2 0 0 1-2-2V5z"/><path d="M17 8h3v9a2 2 0 0 1-2 2"/><path d="M8 8h5M8 12h5M8 16h3"/>
                    </svg>
                </span>
                <span>{{ $newspaper->display_name }}</span>
            </a>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-zinc-200">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-zinc-500 sm:px-6">
            &copy; {{ now()->year }} {{ config('app.name', 'AlphaCMS') }}
        </div>
    </footer>
</body>
</html>

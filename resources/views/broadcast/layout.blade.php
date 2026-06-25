<!DOCTYPE html>
<html dir="rtl" lang="ar">
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
    @vite(['resources/css/app.css', 'resources/js/broadcast.js', 'resources/js/ads.js'])
</head>
<body class="min-h-screen bg-[#0b0b0f] text-zinc-100 antialiased selection:bg-red-600/30">

    <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0b0b0f]/85 backdrop-blur supports-[backdrop-filter]:bg-[#0b0b0f]/70">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight">
                <span class="flex h-8 w-8 items-center justify-center bg-red-600 text-white shadow-lg shadow-red-600/30">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path d="M6 4l12 8-12 8V4z" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
                <span>{{ config('app.name', 'AlphaCMS') }}</span>
            </a>

            <nav class="flex items-center gap-1 text-sm font-medium" aria-label="أقسام البثّ">
                @php($navItems = ['live' => 'مباشر', 'tv' => 'تلفزيون', 'radio' => 'راديو'])
                @foreach ($navItems as $navKind => $navLabel)
                    @php($active = isset($kind) && $kind->value === $navKind)
                    <a href="{{ url('/'.$navKind) }}"
                       @if($active) aria-current="page" @endif
                       class="px-3.5 py-2 transition-colors {{ $active ? 'bg-white/10 text-white' : 'text-zinc-400 hover:bg-white/5 hover:text-white' }}">
                        {{ $navLabel }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-zinc-500 sm:flex-row sm:px-6">
            <p>&copy; {{ now()->year }} {{ config('app.name', 'AlphaCMS') }}. جميع الحقوق محفوظة.</p>
            <nav class="flex items-center gap-4" aria-label="روابط سريعة">
                <a href="{{ url('/live') }}" class="transition-colors hover:text-zinc-200">مباشر</a>
                <a href="{{ url('/tv') }}" class="transition-colors hover:text-zinc-200">تلفزيون</a>
                <a href="{{ url('/radio') }}" class="transition-colors hover:text-zinc-200">راديو</a>
            </nav>
        </div>
    </footer>

</body>
</html>

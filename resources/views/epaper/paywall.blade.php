@extends('epaper.layout')

@section('seo')
    @include('epaper.partials.seo')
@endsection

@section('content')
    @inject('newspaper', \App\Settings\NewspaperSettings::class)

    <nav class="mb-4 text-sm">
        <a href="{{ url('/'.$locale.'/epaper') }}" class="text-zinc-500 transition-colors hover:text-zinc-900">
            <span aria-hidden="true">{{ $locale === 'en' ? '←' : '→' }}</span>
            {{ __('epaper.public.back_to_archive') }}
        </a>
    </nav>

    <header class="mb-6">
        <span class="text-xs font-medium text-zinc-500">
            {{ __('epaper.public.issue_number', ['number' => $epaper->issue_number]) }}
            @if ($epaper->publication_date) · {{ $epaper->publication_date->isoFormat('LL') }} @endif
        </span>
        <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $epaper->title }}</h1>
        @if ($epaper->subtitle)
            <p class="mt-1 text-zinc-500">{{ $epaper->subtitle }}</p>
        @endif
    </header>

    {{-- صفحة تشويق للمشتركين — تعرض الميتاداتا فقط، بلا أيّ تصيير جزئيّ للـ PDF. --}}
    <div class="border border-zinc-200 bg-zinc-50 p-8 text-center sm:p-12">
        <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center bg-zinc-900 text-white">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <rect x="5" y="11" width="14" height="9"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
            </svg>
        </span>
        @if ($epaper->summary)
            <p class="mx-auto mb-4 max-w-xl text-zinc-600">{{ $epaper->summary }}</p>
        @endif
        <p class="text-base font-semibold text-zinc-900">{{ __('epaper.public.subscriber_only') }}</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-zinc-500">{{ __('epaper.public.subscriber_hint') }}</p>
        @if ($newspaper->subscribe_url !== '')
            <a href="{{ $newspaper->subscribe_url }}"
               class="mt-6 inline-flex items-center gap-2 bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-zinc-700">
                {{ __('epaper.public.subscribe_cta') }}
            </a>
        @endif
    </div>
@endsection

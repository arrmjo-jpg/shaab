{{--
    Premium broadcast card for listing grids.
    Props: $broadcast (Broadcast), $kind (BroadcastKind enum).
    - cover via shareImageUrl() with graceful fallback
    - kind/status badge, breaking badge (is_featured)
    - viewers-now for live (hydrated by JS), countdown for scheduled (JS)
    - radio gets audio-first styling
--}}
@php
    use App\Enums\BroadcastStatus;
    use App\Enums\BroadcastKind;

    $isLive = $broadcast->status === BroadcastStatus::Live;
    $isScheduled = $broadcast->status === BroadcastStatus::Scheduled;
    $isUnavailable = in_array($broadcast->status, [BroadcastStatus::Offline, BroadcastStatus::Failed], true);
    $isRadio = $broadcast->kind === BroadcastKind::Radio;
    $cover = $broadcast->shareImageUrl();
    $href = url($broadcast->canonicalPath());
    $metrics = $broadcast->engagementMetrics();
@endphp

<article class="group relative flex flex-col overflow-hidden border border-white/10 bg-[#13131a] shadow-lg shadow-black/30 transition-all duration-200 hover:border-white/25 hover:shadow-black/50 {{ $isUnavailable ? 'opacity-80' : '' }}">
    <a href="{{ $href }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
        <div class="relative aspect-video overflow-hidden bg-zinc-900">
            @if ($cover)
                <img src="{{ $cover }}" alt="{{ $broadcast->title }}" loading="lazy"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105 {{ $isUnavailable ? 'grayscale' : '' }}">
            @else
                {{-- Graceful fallback cover: gradient + kind glyph --}}
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-zinc-800 to-zinc-900">
                    @if ($isRadio)
                        <svg class="h-14 w-14 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M3 17a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2z"/>
                            <path d="M16 4l-9 4M16 4v11M7 8v7M7 8a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                    @else
                        <svg class="h-14 w-14 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="14" rx="1"/>
                            <path d="M9 9l5 3-5 3V9z" fill="currentColor" stroke="none"/>
                        </svg>
                    @endif
                </div>
            @endif

            {{-- top-start badges --}}
            <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-2.5">
                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($isLive)
                        <span class="inline-flex items-center gap-1.5 bg-red-600 px-2 py-1 text-xs font-bold uppercase tracking-wide text-white shadow">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping bg-white opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 bg-white"></span>
                            </span>
                            مباشر
                        </span>
                    @elseif ($isScheduled)
                        <span class="inline-flex items-center gap-1 bg-amber-500/90 px-2 py-1 text-xs font-bold text-black shadow">
                            مجدول
                        </span>
                    @elseif ($isUnavailable)
                        <span class="inline-flex items-center gap-1 bg-zinc-700/90 px-2 py-1 text-xs font-bold text-zinc-100 shadow">
                            {{ $broadcast->status->label() }}
                        </span>
                    @endif

                    @if ($broadcast->is_featured)
                        <span class="inline-flex items-center gap-1 bg-white px-2 py-1 text-xs font-extrabold uppercase tracking-wide text-red-700 shadow">
                            عاجل
                        </span>
                    @endif
                </div>

                {{-- viewers-now (live only) — hydrated by JS; SSR seeds with snapshot viewer_count --}}
                @if ($isLive)
                    <span class="inline-flex items-center gap-1.5 bg-black/70 px-2 py-1 text-xs font-semibold text-white backdrop-blur"
                          data-viewers-now data-broadcast-id="{{ $broadcast->id }}">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span data-viewers-count>{{ number_format((int) $broadcast->viewer_count) }}</span>
                    </span>
                @endif
            </div>

            {{-- scheduled countdown overlay (JS-hydrated) --}}
            @if ($isScheduled && $broadcast->scheduled_at)
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent p-3">
                    <div class="flex items-center gap-1.5 text-xs font-medium text-amber-200">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                        </svg>
                        <span>يبدأ خلال</span>
                        <time data-countdown datetime="{{ $broadcast->scheduled_at->toIso8601String() }}"
                              class="font-mono font-bold text-white tabular-nums">--:--:--</time>
                    </div>
                </div>
            @endif
        </div>
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-center gap-2 text-xs text-zinc-500">
            @if ($broadcast->category)
                <span class="text-zinc-400">{{ $broadcast->category->name }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $broadcast->kind->label() }}</span>
        </div>

        <h3 class="line-clamp-2 font-bold leading-snug text-zinc-50">
            <a href="{{ $href }}" class="transition-colors hover:text-red-400 focus:outline-none focus-visible:underline">
                {{ $broadcast->title }}
            </a>
        </h3>

        @if ($broadcast->excerpt)
            <p class="line-clamp-2 text-sm leading-relaxed text-zinc-400">{{ $broadcast->excerpt }}</p>
        @endif

        @if ($isScheduled && $broadcast->scheduled_at)
            <p class="mt-auto pt-1 text-xs text-zinc-500">
                {{ $broadcast->scheduled_at->translatedFormat('l j F Y · H:i') }}
            </p>
        @elseif ($isUnavailable)
            <p class="mt-auto pt-1 text-xs text-zinc-500">غير متاح حالياً</p>
        @else
            <div class="mt-auto flex items-center gap-3 pt-1 text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 10v12M15 5.88L14 10h5.83a2 2 0 011.92 2.56l-2.33 8A2 2 0 0117.5 22H4a2 2 0 01-2-2v-8a2 2 0 012-2h2.76a2 2 0 001.79-1.11L12 2a3.13 3.13 0 013 3.88z"/></svg>
                    {{ number_format($metrics['likes']) }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 14V2M9 18.12L10 14H4.17a2 2 0 01-1.92-2.56l2.33-8A2 2 0 016.5 2H20a2 2 0 012 2v8a2 2 0 01-2 2h-2.76a2 2 0 00-1.79 1.11L12 22a3.13 3.13 0 01-3-3.88z"/></svg>
                    {{ number_format($metrics['dislikes']) }}
                </span>
            </div>
        @endif
    </div>
</article>

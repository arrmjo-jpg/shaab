@extends('broadcast.layout')

@php
    use App\Enums\BroadcastStatus;
    use App\Enums\BroadcastKind;

    $b = $broadcast;
    $isLive = $b->status === BroadcastStatus::Live;
    $isScheduled = $b->status === BroadcastStatus::Scheduled;
    $isEnded = $b->status === BroadcastStatus::Ended;
    $isOffline = $b->status === BroadcastStatus::Offline;
    $isFailed = $b->status === BroadcastStatus::Failed;
    $isRadio = $b->kind === BroadcastKind::Radio;
    $cover = $b->shareImageUrl();
    $metrics = $b->engagementMetrics();
@endphp

@section('seo')
    @include('broadcast.partials.seo', ['seo' => $seo])
@endsection

@section('content')
    {{-- Root node carries config for progressive JS. The live source URL is emitted
         ONLY in the playable LIVE state (it's a public stream) — never for offline/failed. --}}
    <div
        data-broadcast-id="{{ $b->id }}"
        data-status="{{ $b->status->value }}"
        data-kind="{{ $b->kind->value }}"
        data-source-type="{{ $b->source_type->value }}"
        @if ($isLive) data-source-url="{{ $b->source_url }}" @endif
        @if ($isScheduled && $b->scheduled_at) data-scheduled-at="{{ $b->scheduled_at->toIso8601String() }}" @endif
    >
        <nav class="mb-5 flex items-center gap-1.5 text-sm text-zinc-500" aria-label="مسار التنقّل">
            <a href="{{ url('/'.$b->kind->value) }}" class="transition-colors hover:text-zinc-300">{{ $b->kind->label() }}</a>
            <span aria-hidden="true">/</span>
            <span class="truncate text-zinc-400">{{ $b->title }}</span>
        </nav>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0">

                {{-- ─────────── STAGE: state-driven ─────────── --}}
                @if ($isLive)
                    {{-- LIVE: player container hydrated by JS from data-attributes --}}
                    <div class="relative overflow-hidden border border-white/10 bg-black shadow-2xl shadow-black/50">
                        <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-2 p-3">
                            <span class="inline-flex items-center gap-1.5 bg-red-600 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white shadow">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping bg-white opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 bg-white"></span>
                                </span>
                                مباشر
                            </span>
                            <span class="inline-flex items-center gap-1.5 bg-black/70 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur"
                                  data-viewers-now>
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span data-viewers-count>{{ number_format((int) $b->viewer_count) }}</span>
                                <span>مشاهد</span>
                            </span>
                        </div>

                        {{-- player mount point; broadcast.js injects <video>/<audio>/<iframe> here --}}
                        <div class="aspect-video w-full {{ $isRadio ? 'flex items-center justify-center bg-gradient-to-br from-zinc-900 to-black' : '' }}"
                             data-player
                             @if ($cover) data-poster="{{ $cover }}" @endif>
                            {{-- SSR placeholder until JS hydrates --}}
                            <div class="flex h-full w-full items-center justify-center" data-player-placeholder>
                                <div class="flex flex-col items-center gap-3 text-zinc-500">
                                    <span class="h-10 w-10 animate-spin border-2 border-white/20 border-t-red-500"></span>
                                    <span class="text-sm">جارٍ تحميل البثّ…</span>
                                </div>
                            </div>
                        </div>

                        {{-- player error surface (revealed by JS) --}}
                        <div class="hidden items-center justify-center gap-2 bg-red-950/60 px-4 py-3 text-sm text-red-200" data-player-error role="alert">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                            <span>تعذّر تشغيل البثّ. يُرجى المحاولة لاحقاً.</span>
                        </div>

                        {{-- presence control surface (kicked/banned/closed/ended/offline) revealed by JS --}}
                        <div class="hidden items-center justify-center gap-2 bg-zinc-900 px-4 py-3 text-sm text-zinc-200" data-presence-notice role="status"></div>
                    </div>

                @elseif ($isScheduled)
                    {{-- SCHEDULED: cover + big countdown + remind CTA. NOT playable. --}}
                    <div class="relative overflow-hidden border border-white/10 bg-black shadow-2xl shadow-black/50">
                        <div class="relative aspect-video w-full">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="{{ $b->title }}" class="h-full w-full object-cover opacity-60">
                            @else
                                <div class="h-full w-full bg-gradient-to-br from-zinc-800 to-zinc-950"></div>
                            @endif
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/40 p-6 text-center">
                                <span class="inline-flex items-center gap-1.5 bg-amber-500 px-3 py-1 text-xs font-bold text-black">مجدول</span>
                                <p class="text-sm font-medium text-zinc-300">يبدأ البثّ خلال</p>
                                @if ($b->scheduled_at)
                                    <time data-countdown datetime="{{ $b->scheduled_at->toIso8601String() }}"
                                          class="font-mono text-4xl font-extrabold tabular-nums text-white sm:text-5xl">--:--:--</time>
                                    <p class="text-sm text-zinc-400">{{ $b->scheduled_at->translatedFormat('l j F Y · H:i') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- remind CTA — JS swaps to active/auth-CTA based on token presence --}}
                    <div class="mt-4" data-reminder>
                        <button type="button" data-reminder-toggle
                                class="inline-flex items-center gap-2 border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
                            <span data-reminder-label>ذكّرني عند البدء</span>
                        </button>
                        <p class="mt-2 hidden text-sm text-zinc-400" data-reminder-feedback role="status"></p>
                    </div>

                @elseif ($isEnded)
                    {{-- ENDED: closed state + optional VOD CTA --}}
                    <div class="relative overflow-hidden border border-white/10 bg-black shadow-2xl shadow-black/50">
                        <div class="relative aspect-video w-full">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="{{ $b->title }}" class="h-full w-full object-cover opacity-40 grayscale">
                            @else
                                <div class="h-full w-full bg-gradient-to-br from-zinc-800 to-zinc-950"></div>
                            @endif
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/55 p-6 text-center">
                                <svg class="h-12 w-12 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
                                <h2 class="text-xl font-bold text-white">انتهى البثّ</h2>
                                <p class="max-w-md text-sm text-zinc-400">شكراً لمتابعتكم. انتهى هذا البثّ المباشر.</p>
                                @if ($b->vodVideo)
                                    <a href="{{ url($b->vodVideo->canonicalPath()) }}"
                                       class="mt-2 inline-flex items-center gap-2 bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                        مشاهدة التسجيل الكامل
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                @else
                    {{-- FAILED / OFFLINE: graceful unavailable UX — NO source exposed --}}
                    <div class="relative overflow-hidden border border-white/10 bg-black shadow-2xl shadow-black/50">
                        <div class="relative aspect-video w-full">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="{{ $b->title }}" class="h-full w-full object-cover opacity-40 grayscale">
                            @else
                                <div class="h-full w-full bg-gradient-to-br from-zinc-800 to-zinc-950"></div>
                            @endif
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/55 p-6 text-center">
                                <svg class="h-12 w-12 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0119 12.55M5 12.55a10.94 10.94 0 015.17-2.39M10.71 5.05A16 16 0 0122.58 9M1.42 9a15.91 15.91 0 014.7-2.88M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg>
                                <h2 class="text-xl font-bold text-white">
                                    {{ $isOffline ? 'خارج البثّ مؤقّتاً' : 'غير متاح حالياً' }}
                                </h2>
                                <p class="max-w-md text-sm text-zinc-400">
                                    @if ($isOffline)
                                        هذه {{ $isRadio ? 'المحطة' : 'القناة' }} خارج البثّ مؤقّتاً. يُرجى المحاولة لاحقاً.
                                    @else
                                        تعذّر بثّ هذا المحتوى حالياً. نعمل على معالجة المشكلة.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ─────────── Title + meta + reactions (always) ─────────── --}}
                <div class="mt-6">
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        @if ($b->category)
                            <span class="text-zinc-400">{{ $b->category->name }}</span>
                            <span aria-hidden="true">·</span>
                        @endif
                        <span>{{ $b->kind->label() }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $b->status->label() }}</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-start gap-3">
                        <h1 class="flex-1 text-2xl font-extrabold leading-tight tracking-tight text-white sm:text-3xl">{{ $b->title }}</h1>
                        @if ($b->is_featured)
                            <span class="mt-1 inline-flex items-center gap-1 bg-white px-2.5 py-1 text-xs font-extrabold uppercase tracking-wide text-red-700 shadow">عاجل</span>
                        @endif
                    </div>

                    {{-- like/dislike bar — counts SSR from engagementMetrics(); JS makes them interactive --}}
                    <div class="mt-5 flex items-center gap-3" data-reactions>
                        <button type="button" data-react="like" aria-pressed="false"
                                class="inline-flex items-center gap-2 border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold text-zinc-200 transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 10v12M15 5.88L14 10h5.83a2 2 0 011.92 2.56l-2.33 8A2 2 0 0117.5 22H4a2 2 0 01-2-2v-8a2 2 0 012-2h2.76a2 2 0 001.79-1.11L12 2a3.13 3.13 0 013 3.88z"/></svg>
                            <span data-like-count>{{ number_format($metrics['likes']) }}</span>
                        </button>
                        <button type="button" data-react="dislike" aria-pressed="false"
                                class="inline-flex items-center gap-2 border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold text-zinc-200 transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 14V2M9 18.12L10 14H4.17a2 2 0 01-1.92-2.56l2.33-8A2 2 0 016.5 2H20a2 2 0 012 2v8a2 2 0 01-2 2h-2.76a2 2 0 00-1.79 1.11L12 22a3.13 3.13 0 01-3-3.88z"/></svg>
                            <span data-dislike-count>{{ number_format($metrics['dislikes']) }}</span>
                        </button>
                        <p class="hidden text-sm text-zinc-400" data-reactions-feedback role="status"></p>
                    </div>

                    @if ($b->description)
                        <div class="mt-6 max-w-none border-t border-white/10 pt-6 leading-relaxed text-zinc-300">
                            {!! nl2br(e($b->description)) !!}
                        </div>
                    @endif
                </div>
            </div>

            {{-- ─────────── Sidebar ─────────── --}}
            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                <div class="border border-white/10 bg-[#13131a] p-5">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-zinc-400">تفاصيل البثّ</h2>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500">النوع</dt>
                            <dd class="font-medium text-zinc-200">{{ $b->kind->label() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500">الحالة</dt>
                            <dd class="font-medium text-zinc-200">{{ $b->status->label() }}</dd>
                        </div>
                        @if ($b->category)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500">التصنيف</dt>
                                <dd class="font-medium text-zinc-200">{{ $b->category->name }}</dd>
                            </div>
                        @endif
                        @if ($isScheduled && $b->scheduled_at)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500">موعد البدء</dt>
                                <dd class="font-medium text-zinc-200">{{ $b->scheduled_at->translatedFormat('j F · H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($cover)
                    <div class="overflow-hidden border border-white/10 bg-[#13131a]">
                        <img src="{{ $cover }}" alt="{{ $b->title }}" loading="lazy" class="w-full object-cover">
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection

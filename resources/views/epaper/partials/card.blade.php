<a href="{{ url($issue->canonicalPath()) }}"
   class="group flex flex-col border border-zinc-200 bg-white p-5 transition-colors hover:border-zinc-900">
    <span class="text-xs font-medium text-zinc-500">
        {{ __('epaper.public.issue_number', ['number' => $issue->issue_number]) }}
    </span>
    <h2 class="mt-1 line-clamp-2 text-lg font-bold leading-snug group-hover:underline">{{ $issue->title }}</h2>
    @if ($issue->subtitle)
        <p class="mt-1 line-clamp-1 text-sm text-zinc-500">{{ $issue->subtitle }}</p>
    @endif
    <span class="mt-3 text-xs text-zinc-400">{{ $issue->publication_date?->isoFormat('LL') }}</span>
    <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-zinc-900">
        {{ __('epaper.public.read') }}
        <span aria-hidden="true">{{ ($locale ?? 'ar') === 'en' ? '→' : '←' }}</span>
    </span>
</a>

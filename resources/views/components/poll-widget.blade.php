{{--
    ودجة استطلاع عامة (PollWidget) — مكوّن Blade مجهول. شِل مُكاش على الحافة (لا SSR
    per-actor) يُهيَّأ بـ resources/js/polls.js عبر data-poll-uuid: يجلب الاستطلاع
    (GET /api/v1/polls/{uuid})، يعرض النموذج أو النتائج حسب الحالة ورؤية النتائج، يصوّت
    عبر POST محدود المعدّل، ويرسم النتائج. غير فارغ بلا JS (نصّ بديل + noscript).

    الاستخدام:
        <x-poll-widget :uuid="$poll->uuid" />
        <x-poll-widget uuid="..." :locale="app()->getLocale()" class="my-6" />
--}}
@props(['uuid', 'locale' => null])

<div
    data-poll-uuid="{{ $uuid }}"
    data-poll-state="loading"
    @if ($locale) data-locale="{{ $locale }}" @endif
    {{ $attributes->merge(['class' => 'poll-widget']) }}
>
    {{-- نصوص الواجهة مترجمة على الخادم وتُمرَّر للـ JS (صديق CDN: مُكاش لكل لغة). --}}
    <script type="application/json" data-poll-i18n>@json(__('polls.widget'))</script>

    {{-- بديل رشيق غير فارغ: يظهر حتى تُهيّئ JS، ويبقى للمتصفّحات دون JS. --}}
    <p data-poll-placeholder class="poll-widget__placeholder">{{ __('polls.widget.loading') }}</p>
    <noscript>
        <p class="poll-widget__noscript">{{ __('polls.widget.noscript') }}</p>
    </noscript>
</div>

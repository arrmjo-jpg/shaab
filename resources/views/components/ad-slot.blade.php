{{--
    مساحة إعلانية عامة (AdSlot) — مكوّن Blade مجهول. يصدر عنصراً يُهيَّأ بـ
    resources/js/ads.js عبر data-ad-zone: الخادم يختار الإبداع (مُكاش على الحافة بنافذة
    الدلو)، الانطباع يُؤكَّد عند الظهور، والنقرة عبر تحويل موقّع. فارغ بأمان إن لا إعلان.

    الاستخدام:
        <x-ad-slot zone="home_top" />
        <x-ad-slot zone="sidebar" :locale="app()->getLocale()" class="my-6" />
        <x-ad-slot zone="article_inline" device="mobile" />
--}}
@props(['zone', 'locale' => null, 'device' => null])

<div
    data-ad-zone="{{ $zone }}"
    @if ($locale) data-locale="{{ $locale }}" @endif
    @if ($device) data-device="{{ $device }}" @endif
    {{ $attributes->merge(['class' => 'ad-slot']) }}
></div>

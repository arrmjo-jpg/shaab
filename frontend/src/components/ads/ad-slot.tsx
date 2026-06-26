// مكان محجوز للإعلان — يحجز مساحة ثابتة في التخطيط (دعم «حسب الشهر»: إعلان شهريّ يوضع هنا).
//
// النظام الإعلانيّ أوّل-الطرف موجود في الباك إند: `GET /api/v1/ads/serve/{zoneKey}?locale=&device=`
// (انظر .ai/advertising.md §4/§10). تكامل واجهة Next الكامل (جلب الإعلان + beacon ظهور عبر
// IntersectionObserver + تتبّع نقر) **مؤجَّل بصدق**؛ هذا عنصر نائب يحجز المساحة بلا تلفيق محتوى.
// عند التوصيل لاحقاً: حوِّله إلى client island يقرأ `zone` ويستدعي serve. تمرّر `zone` الآن للتوثيق.
export function AdSlot({
  className,
  label = 'مساحة إعلانية',
  zone,
}: {
  className?: string;
  label?: string;
  zone?: string;
}) {
  return (
    <div
      className={`flex min-h-[280px] flex-col items-center justify-center gap-2 border border-dashed border-border bg-surface p-6 text-center text-muted ${className ?? ''}`}
      role="complementary"
      aria-label={label}
      data-ad-zone={zone}
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={1.5}
        className="size-8 opacity-50"
        aria-hidden
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
        />
      </svg>
      <span className="text-caption font-semibold uppercase tracking-wide">{label}</span>
    </div>
  );
}

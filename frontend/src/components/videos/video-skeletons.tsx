// هياكل تحميل (shimmer) — احتياطيّات Suspense/loading لصفحة الفيديو. مربّعة، tokens، CSS فقط (لا JS).
// تحجز الأبعاد (aspect-video / spotlight grid) فلا قفز تخطيط (CLS=0).

function Box({ className = '' }: { className?: string }) {
  return <div className={`animate-pulse bg-surface-2 ${className}`} aria-hidden />;
}

export function VideoCardSkeleton() {
  return (
    <div className="flex flex-col">
      <Box className="aspect-video w-full" />
      <div className="space-y-2 pt-3">
        <Box className="h-3 w-16" />
        <Box className="h-4 w-full" />
        <Box className="h-3 w-2/3" />
      </div>
    </div>
  );
}

export function VideoRailSkeleton({ count = 5 }: { count?: number }) {
  return (
    <div className="flex gap-4 overflow-hidden">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="w-[80%] shrink-0 sm:w-[44%] md:w-[300px]">
          <VideoCardSkeleton />
        </div>
      ))}
    </div>
  );
}

export function SpotlightSkeleton() {
  return (
    <div className="grid gap-5 lg:grid-cols-12 lg:gap-6">
      <Box className="aspect-video w-full lg:col-span-7" />
      <div className="flex flex-col gap-3 lg:col-span-5">
        {[0, 1, 2, 3].map((i) => (
          <div key={i} className="flex gap-3">
            <Box className="aspect-video w-32 shrink-0 sm:w-40" />
            <div className="flex-1 space-y-2 pt-1">
              <Box className="h-3 w-12" />
              <Box className="h-4 w-full" />
              <Box className="h-3 w-2/3" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function HeroVideoSkeleton() {
  return <Box className="aspect-video w-full sm:aspect-[21/9]" />;
}

export function SectionHeaderSkeleton() {
  return (
    <div className="mb-5 flex items-center gap-3 border-b border-border pb-4">
      <span className="h-7 w-1.5 shrink-0 bg-surface-3" aria-hidden />
      <Box className="h-7 w-40" />
    </div>
  );
}

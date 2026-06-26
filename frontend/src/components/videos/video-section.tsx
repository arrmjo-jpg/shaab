import { ChevronLeft, Film } from 'lucide-react';
import Link from 'next/link';

import { Container } from '@/components/layout/container';
import { VideoLibrary } from '@/components/videos/video-library';
import { getLatestVideos } from '@/lib/videos';

// قسم «فيديو» — مكتبة فيديو **مستقلّة** (آخر ٦ فيديوهات، غير مرتبطة بالأخبار/الريلز). Server Component،
// ISR 300s (كاش)، يجلب من `getLatestVideos` ويمرّرها لمكتبة عميل تفاعليّة (مشغّل hls.js + مصغّرات).
// لا فيديوهات ⇒ يُخفى (عزل فشل، لا تلفيق).
export async function VideoSection() {
  const videos = await getLatestVideos(6);
  if (videos.length === 0) return null;

  return (
    <section className="bg-white" dir="rtl" aria-labelledby="videos-heading">
      <Container className="pb-8 pt-2 sm:pb-10 sm:pt-3">
        <div className="mb-6 flex items-center justify-between gap-4 border-b border-border pb-4">
          <div className="flex items-center gap-3">
            <span className="h-7 w-1.5 shrink-0 bg-primary" aria-hidden />
            <h2 id="videos-heading" className="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-fg sm:text-3xl">
              <Film className="size-6 shrink-0 text-primary" aria-hidden />
              <Link href="/videos" className="transition-colors hover:text-primary">
                فيديو
              </Link>
            </h2>
          </div>
          <Link
            href="/videos"
            className="flex shrink-0 items-center gap-1 text-sm font-bold text-muted transition-colors hover:text-primary"
          >
            عرض الكل
            <ChevronLeft className="size-4" aria-hidden />
          </Link>
        </div>

        <VideoLibrary videos={videos} />
      </Container>
    </section>
  );
}

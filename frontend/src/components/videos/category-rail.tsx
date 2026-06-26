import { SectionHeader } from './section-header';
import { VideoRail } from './video-rail';
import type { VideoItem } from '@/lib/videos';

// رفّ تصنيف — ترويسة (اسم التصنيف + «عرض الكل») + رفّ فيديو أفقيّ. مُقدِّميّ بحت يُغذّى بالبيانات (S1) عبر props
// (لا يجلب، لا hardcoding لتصنيف). فارغ ⇒ null (حالة فارغة صادقة: لا رفّ بلا محتوى، لا تلفيق).
export function CategoryRail({
  title,
  videos,
  id,
  href,
  subtitle,
}: {
  title: string;
  videos: VideoItem[];
  id?: string;
  href?: string;
  subtitle?: string;
}) {
  if (videos.length === 0) return null;

  return (
    <section aria-labelledby={id}>
      <SectionHeader title={title} id={id} subtitle={subtitle} viewAllHref={href} />
      <VideoRail items={videos} />
    </section>
  );
}

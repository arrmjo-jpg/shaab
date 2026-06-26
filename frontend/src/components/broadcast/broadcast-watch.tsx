import { Clock, Eye, ThumbsDown, ThumbsUp } from 'lucide-react';

import { ShareControl } from '@/components/epaper/share-control';
import type { BroadcastDetail } from '@/lib/broadcast';

import { BroadcastPlayer } from './broadcast-player';
import { BroadcastSuggestions } from './broadcast-suggestions';

// صفحة البثّ — الترتيب (بطلب المستخدم): العنوان فوق → الفيديو بعرض الموقع (1280) → الإعجابات
// والمشاركة تحت → الوصف. عدّاد المشاهدين يظهر فقط إن > 0 (لقطة صادقة).
function fmtTime(iso: string | null): string {
  if (!iso) return '';
  try {
    return new Intl.DateTimeFormat('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
  } catch {
    return '';
  }
}

const STATE_LABEL: Record<BroadcastDetail['playback']['state'], string> = {
  live: 'مباشر',
  upcoming: 'مجدوَل',
  ended: 'انتهى',
  offline: 'متوقّف مؤقّتاً',
  failed: 'غير متاح',
  unavailable: 'غير متاح',
};

export function BroadcastWatch({ broadcast: b }: { broadcast: BroadcastDetail }) {
  const state = b.playback.state;
  const timeLabel =
    state === 'live' ? fmtTime(b.startedAt) : state === 'upcoming' ? fmtTime(b.scheduledAt) : fmtTime(b.endedAt);

  return (
    <div dir="rtl" className="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {/* العمود الرئيسيّ — 8 أعمدة */}
        <div className="lg:col-span-8">
          {/* 1) العنوان فوق */}
          <div className="mb-2 flex flex-wrap items-center gap-2">
        {state === 'live' ? (
          <span className="inline-flex items-center gap-1.5 bg-primary px-2.5 py-1 text-xs font-bold text-white">
            <span className="size-2 animate-pulse rounded-full bg-white" aria-hidden /> مباشر
          </span>
        ) : (
          <span className="border border-border px-2.5 py-1 text-xs font-bold text-muted">{STATE_LABEL[state]}</span>
        )}
        {b.category ? <span className="text-sm font-bold text-primary">{b.category.name}</span> : null}
      </div>

      <h1 className="font-heading text-2xl font-extrabold tracking-tight text-fg sm:text-3xl">{b.title}</h1>

      <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted">
        {timeLabel ? (
          <span className="inline-flex items-center gap-1.5">
            <Clock className="size-4" aria-hidden /> {timeLabel}
          </span>
        ) : null}
        {b.viewerCount > 0 ? (
          <span className="inline-flex items-center gap-1.5">
            <Eye className="size-4" aria-hidden /> {b.viewerCount.toLocaleString('ar-EG')} مشاهد
          </span>
        ) : null}
      </div>

      {/* 2) الفيديو — بعرض الموقع (1280) */}
      <div className="mt-5 overflow-hidden bg-black">
        <BroadcastPlayer playback={b.playback} poster={b.shareImage} title={b.title} />
      </div>

      {/* 3) الإعجابات والمشاركة — تحت الفيديو */}
      <div className="mt-4 flex flex-wrap items-center gap-3 border-y border-border py-3">
        <span className="inline-flex items-center gap-1.5 text-sm text-fg" title="إعجابات">
          <ThumbsUp className="size-4" aria-hidden /> {b.metrics.likes.toLocaleString('ar-EG')}
        </span>
        <span className="inline-flex items-center gap-1.5 text-sm text-fg" title="عدم إعجاب">
          <ThumbsDown className="size-4" aria-hidden /> {b.metrics.dislikes.toLocaleString('ar-EG')}
        </span>
        <span className="ms-auto">
          <ShareControl title={b.title} href={b.href} />
        </span>
      </div>

          {b.description ? (
            <p className="mt-4 whitespace-pre-line text-base leading-relaxed text-fg">{b.description}</p>
          ) : b.excerpt ? (
            <p className="mt-4 text-base leading-relaxed text-muted">{b.excerpt}</p>
          ) : null}
        </div>

        {/* الشريط الجانبيّ — 4 أعمدة: بثوث أخرى مقترحة */}
        <aside className="lg:col-span-4">
          <BroadcastSuggestions excludeId={b.id} />
        </aside>
      </div>
    </div>
  );
}

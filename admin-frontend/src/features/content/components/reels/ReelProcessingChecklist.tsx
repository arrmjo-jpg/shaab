import { useTranslation } from 'react-i18next';
import { AlertTriangle, Check, Loader2, Minus, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { TranscodeArtifact, TranscodeProgress } from '@/types/content.types';

interface Props {
  progress: TranscodeProgress;
}

/** Localized label for an artifact key (MP4 renditions are derived generically). */
function useArtifactLabel() {
  const { t } = useTranslation('content');
  return (key: string): string => {
    const mp4 = /^mp4_(\d+)$/.exec(key);
    if (mp4) return `MP4 ${mp4[1]}p`;
    return t(`reels.media.artifacts.steps.${key}`, { defaultValue: key });
  };
}

function StateIcon({ state }: { state: TranscodeArtifact['state'] }) {
  switch (state) {
    case 'ready':
      return <Check className="h-3.5 w-3.5 shrink-0 text-success" />;
    case 'failed':
      return <X className="h-3.5 w-3.5 shrink-0 text-destructive" />;
    case 'skipped':
      return <Minus className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
    default:
      return <Loader2 className="h-3.5 w-3.5 shrink-0 animate-spin text-muted-foreground" />;
  }
}

/**
 * قائمة تحقّق حبيبية لتقدّم ترميز فيديو الريل — تعرض حالة كل قطعة أثرية
 * (poster / مصغّرة / نسخ MP4 / HLS) ونسبة الإنجاز ومرحلة الفشل إن وُجدت.
 * تقرأ حالة مشتقّة من الخادم (TranscodeProgress) — لا منطق معالجة في الواجهة.
 */
export function ReelProcessingChecklist({ progress }: Props) {
  const { t } = useTranslation('content');
  const label = useArtifactLabel();
  const { artifacts, completed, total, failed_stage, error } = progress;
  const errorText = error ? t(`reels.media.artifacts.errors.${error}`, { defaultValue: error }) : null;

  return (
    <div className="space-y-2 border border-border bg-muted/30 p-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-foreground/80">
          {t('reels.media.artifacts.title')}
        </span>
        <span className="text-xs text-muted-foreground">
          {t('reels.media.artifacts.summary', { completed, total })}
        </span>
      </div>

      {errorText ? (
        <p className="flex items-center gap-1.5 text-xs text-destructive">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
          {errorText}
        </p>
      ) : failed_stage ? (
        <p className="flex items-center gap-1.5 text-xs text-destructive">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
          {t('reels.media.artifacts.failedAt', { stage: label(failed_stage) })}
        </p>
      ) : null}

      <ul className="space-y-1">
        {artifacts.map((a) => (
          <li key={a.key} className="flex items-center gap-2 text-xs">
            <StateIcon state={a.state} />
            <span
              className={cn(
                a.state === 'ready' && 'text-foreground',
                a.state === 'failed' && 'text-destructive',
                (a.state === 'pending' || a.state === 'skipped') && 'text-muted-foreground',
              )}
            >
              {label(a.key)}
            </span>
            {a.state === 'skipped' ? (
              <span className="text-[10px] text-muted-foreground">
                ({t('reels.media.artifacts.skipped')})
              </span>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}

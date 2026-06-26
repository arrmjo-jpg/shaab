import { useTranslation } from 'react-i18next';
import { Link2 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import type { BroadcastSourceType } from '@/types/broadcast.types';

/** القيمة المُبلَّغة للنموذج: نوع المصدر + رابطه (البثّ خارجي موثوق فقط — لا رفع ملفات). */
export interface BroadcastSourceValue {
  source_type: BroadcastSourceType;
  source_url: string;
}

interface Props {
  sourceType: BroadcastSourceType;
  sourceUrl: string;
  onChange: (v: BroadcastSourceValue) => void;
}

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export const BROADCAST_SOURCE_TYPES: BroadcastSourceType[] = [
  'hls',
  'iptv',
  'youtube_live',
  'external_provider',
  'icecast',
  'shoutcast',
];

/**
 * مدير مصدر البثّ — اختيار نوع من الستّة، وحقل رابط واحد خارجيّ موثوق بنصوص خاصّة
 * بكلّ نوع (تسمية/مكان مؤقّت/تلميح). لا رفع ملفات — البثّ خارجيّ بالكامل. يبلّغ
 * {source_type, source_url} عبر onChange.
 */
export function BroadcastSourceManager({ sourceType, sourceUrl, onChange }: Props) {
  const { t } = useTranslation('broadcast');

  const setType = (next: BroadcastSourceType) => onChange({ source_type: next, source_url: sourceUrl });
  const setUrl = (next: string) => onChange({ source_type: sourceType, source_url: next });

  return (
    <div className="space-y-4 border border-border bg-background p-3">
      <div className="space-y-1.5">
        <label className="text-sm font-medium">{t('form.source.typeLabel')}</label>
        <select
          className={`${selectCls} w-full`}
          value={sourceType}
          onChange={(e) => setType(e.target.value as BroadcastSourceType)}
        >
          {BROADCAST_SOURCE_TYPES.map((s) => (
            <option key={s} value={s}>
              {t(`source.${s}`)}
            </option>
          ))}
        </select>
        <p className="text-xs text-muted-foreground">{t(`form.source.typeHint.${sourceType}`)}</p>
      </div>

      <div className="space-y-1.5">
        <label className="flex items-center gap-1.5 text-sm font-medium">
          <Link2 className="h-3.5 w-3.5" />
          {t(`form.source.urlLabel.${sourceType}`)}
        </label>
        <Input
          value={sourceUrl}
          onChange={(e) => setUrl(e.target.value)}
          placeholder={t(`form.source.urlPlaceholder.${sourceType}`)}
          dir="ltr"
        />
        <p className="text-xs text-muted-foreground">{t('form.source.urlGenericHint')}</p>
      </div>
    </div>
  );
}

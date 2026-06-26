import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ImagePlus, Loader2, Upload } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { useMediaLibrary } from '../hooks';

interface Props {
  /** Current image URL (null when none). */
  value: string | null;
  onChange: (assetId: number | null) => void;
  /** Optional label/hint overrides — defaults to the live-coverage OG strings. */
  label?: string;
  hint?: string;
}

/**
 * صورة مشاركة مخصّصة للحدث: اختيار من المكتبة المركزية أو رفع جديد — يعيد
 * استخدام خدمة الوسائط المشتركة (لا نظام موازٍ).
 */
export function OgImagePicker({ value, onChange, label, hint }: Props) {
  const { t } = useTranslation('content');
  const { error } = useToast();
  const [open, setOpen] = useState(false);
  const [uploading, setUploading] = useState(false);
  const lib = useMediaLibrary({ type: 'image', per_page: 24 }, open);

  const onUpload = async (file?: File) => {
    if (!file) return;
    setUploading(true);
    try {
      const asset = await mediaLibraryService.upload(file);
      onChange(asset.id);
      setOpen(false);
    } catch {
      error(t('liveCoverage.og.uploadFailed'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-foreground">{label ?? t('liveCoverage.og.label')}</p>
      <p className="text-xs text-muted-foreground">{hint ?? t('liveCoverage.og.hint')}</p>
      <div className="flex items-center gap-3">
        <div className="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted/30">
          {value ? (
            <img src={value} alt="" className="h-full w-full object-cover" />
          ) : (
            <ImagePlus className="h-5 w-5 text-muted-foreground" />
          )}
        </div>
        <div className="flex flex-col items-start gap-1.5">
          <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>
            {t('liveCoverage.og.choose')}
          </Button>
          {value ? (
            <button
              type="button"
              onClick={() => onChange(null)}
              className="text-xs text-muted-foreground hover:text-destructive"
            >
              {t('liveCoverage.og.remove')}
            </button>
          ) : null}
        </div>
      </div>

      <Modal open={open} onClose={() => setOpen(false)} title={t('liveCoverage.og.choose')} size="lg">
        <div className="space-y-3">
          <label className="inline-flex cursor-pointer items-center gap-2 border border-input px-3 py-2 text-sm transition-colors hover:border-primary">
            {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
            {uploading ? t('liveCoverage.og.uploading') : t('liveCoverage.og.upload')}
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={(e) => void onUpload(e.target.files?.[0])}
            />
          </label>

          <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {(lib.data?.data ?? [])
              .filter((a) => a.is_image)
              .map((a) => (
                <button
                  key={a.id}
                  type="button"
                  onClick={() => {
                    onChange(a.id);
                    setOpen(false);
                  }}
                  className="aspect-square overflow-hidden border border-border transition-colors hover:border-primary"
                  title={a.alt ?? a.original_name}
                >
                  <img
                    src={a.thumb ?? a.url ?? ''}
                    alt={a.alt ?? a.original_name}
                    className="h-full w-full object-cover"
                    loading="lazy"
                  />
                </button>
              ))}
          </div>
        </div>
      </Modal>
    </div>
  );
}

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ImagePlus, Loader2, Upload } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { useMediaLibrary } from '@/features/content/hooks';

export interface PickedImage {
  id: number;
  url: string | null;
}

interface Props {
  /** عنوان الصورة الحاليّ (null حين لا توجد) — للمعاينة. */
  value: string | null;
  onChange: (asset: PickedImage | null) => void;
  label: string;
  hint?: string;
}

/**
 * منتقي صورة الإبداع — يختار من مكتبة الوسائط المركزية أو يرفع جديداً (نفس خدمة الوسائط
 * المشتركة، لا نظام موازٍ). يُعيد المعرّف + الرابط معاً لمعاينة فوريّة وحفظ media_asset_id.
 */
export function CreativeImagePicker({ value, onChange, label, hint }: Props) {
  const { t } = useTranslation('advertising');
  const { error } = useToast();
  const [open, setOpen] = useState(false);
  const [uploading, setUploading] = useState(false);
  const lib = useMediaLibrary({ type: 'image', per_page: 24 }, open);

  const onUpload = async (file?: File) => {
    if (!file) return;
    setUploading(true);
    try {
      const asset = await mediaLibraryService.upload(file);
      onChange({ id: asset.id, url: asset.url ?? asset.thumb ?? null });
      setOpen(false);
    } catch {
      error(t('creatives.form.uploadFailed'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-foreground">{label}</p>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      <div className="flex items-center gap-3">
        <div className="flex h-20 w-32 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted/30">
          {value ? (
            <img src={value} alt="" className="h-full w-full object-cover" />
          ) : (
            <ImagePlus className="h-5 w-5 text-muted-foreground" />
          )}
        </div>
        <div className="flex flex-col items-start gap-1.5">
          <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>
            {t('creatives.form.chooseImage')}
          </Button>
          {value ? (
            <button
              type="button"
              onClick={() => onChange(null)}
              className="text-xs text-muted-foreground hover:text-destructive"
            >
              {t('creatives.form.removeImage')}
            </button>
          ) : null}
        </div>
      </div>

      <Modal open={open} onClose={() => setOpen(false)} title={t('creatives.form.chooseImage')} size="lg">
        <div className="space-y-3">
          <label className="inline-flex cursor-pointer items-center gap-2 border border-input px-3 py-2 text-sm transition-colors hover:border-primary">
            {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
            {uploading ? t('creatives.form.uploading') : t('creatives.form.upload')}
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
                    onChange({ id: a.id, url: a.url ?? a.thumb ?? null });
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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ImagePlus, Loader2, Upload } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { useMediaLibrary } from '@/features/content/hooks';

export interface PickedAvatar {
  id: number;
  url: string | null;
}

interface Props {
  /** رابط الصورة الحالي (null حين لا توجد) — للمعاينة الفوريّة. */
  value: string | null;
  onChange: (asset: PickedAvatar | null) => void;
  disabled?: boolean;
}

/**
 * منتقي صورة عضو الفريق — domain picker رفيع فوق primitives الوسائط المشتركة
 * (useMediaLibrary + mediaLibraryService + Modal). يطابق نمط CreativeImagePicker:
 * يختار من المكتبة المركزية أو يرفع جديداً، ويُعيد { id, url } لمعاينة فوريّة وحفظ
 * avatar_asset_id. لا نظام موازٍ — نفس MediaAsset (CDN/conversions/حوكمة).
 */
export function TeamAvatarPicker({ value, onChange, disabled }: Props) {
  const { t } = useTranslation('team');
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
      error(t('avatar.uploadFailed'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-3">
        <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted/30">
          {value ? (
            <img src={value} alt="" className="h-full w-full object-cover" />
          ) : (
            <ImagePlus className="h-5 w-5 text-muted-foreground" />
          )}
        </div>
        <div className="flex flex-col items-start gap-1.5">
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={disabled}
            onClick={() => setOpen(true)}
          >
            {t('avatar.choose')}
          </Button>
          {value ? (
            <button
              type="button"
              disabled={disabled}
              onClick={() => onChange(null)}
              className="text-xs text-muted-foreground hover:text-destructive disabled:opacity-50"
            >
              {t('avatar.remove')}
            </button>
          ) : null}
        </div>
      </div>

      <Modal open={open} onClose={() => setOpen(false)} title={t('avatar.choose')} size="lg">
        <div className="space-y-3">
          <label className="inline-flex cursor-pointer items-center gap-2 border border-input px-3 py-2 text-sm transition-colors hover:border-primary">
            {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
            {uploading ? t('avatar.uploading') : t('avatar.upload')}
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

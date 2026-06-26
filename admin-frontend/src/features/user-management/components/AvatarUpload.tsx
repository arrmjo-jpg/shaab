import * as React from 'react';
import { Camera, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { storageUrl } from '@/lib/storage';
import { DEFAULT_AVATAR } from '@/lib/constants';
import { useUploadAvatar } from '../hooks';

interface Props {
  value: string;
  onChange: (path: string) => void;
}

/** اختيار/رفع صورة شخصية فعلية مع معاينة وصورة افتراضية. */
export function AvatarUpload({ value, onChange }: Props) {
  const { t } = useTranslation('users');
  const upload = useUploadAvatar();
  const inputRef = React.useRef<HTMLInputElement>(null);

  const preview = value ? storageUrl(value) ?? DEFAULT_AVATAR : DEFAULT_AVATAR;

  const pick = (file?: File) => {
    if (!file) return;
    upload.mutate(file, { onSuccess: (r) => onChange(r.path) });
  };

  return (
    <div className="flex flex-col items-center gap-3">
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className="group relative h-32 w-32 overflow-hidden rounded-full border-4 border-primary/20 transition-colors hover:border-primary/50"
      >
        <img src={preview} alt="" className="h-full w-full object-cover" />
        <span className="absolute inset-0 flex items-center justify-center bg-foreground/40 opacity-0 transition-opacity group-hover:opacity-100">
          {upload.isPending ? (
            <Loader2 className="h-7 w-7 animate-spin text-white" />
          ) : (
            <Camera className="h-7 w-7 text-white" />
          )}
        </span>
      </button>
      <div className="text-center">
        <p className="text-sm font-medium">{t('users.form.avatarChoose')}</p>
        <p className="text-xs text-muted-foreground">{t('users.form.avatarHint')}</p>
      </div>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={(e) => pick(e.target.files?.[0])}
      />
    </div>
  );
}

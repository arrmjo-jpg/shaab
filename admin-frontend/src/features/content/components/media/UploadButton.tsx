import { useRef } from 'react';
import { Loader2, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
  accept: string;
  label: string;
  busy?: boolean;
  /** 0–100 while uploading. */
  progress?: number;
  variant?: 'default' | 'outline' | 'ghost';
  size?: 'sm' | 'default' | 'icon';
  disabled?: boolean;
  onPick: (file: File) => void;
}

/**
 * Button that opens a native file picker and reports the chosen file.
 * While `busy`, it shows the live upload percentage. The hidden input is reset
 * after each pick so the same file can be chosen again.
 */
export function UploadButton({
  accept,
  label,
  busy,
  progress,
  variant = 'outline',
  size = 'sm',
  disabled,
  onPick,
}: Props) {
  const ref = useRef<HTMLInputElement | null>(null);

  return (
    <>
      <Button
        type="button"
        variant={variant}
        size={size}
        disabled={busy || disabled}
        onClick={() => ref.current?.click()}
      >
        {busy ? (
          <>
            <Loader2 className="h-4 w-4 animate-spin" />
            {typeof progress === 'number' ? `${progress}%` : null}
          </>
        ) : (
          <>
            <Upload className="h-4 w-4" />
            {label}
          </>
        )}
      </Button>
      <input
        ref={ref}
        type="file"
        accept={accept}
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          e.target.value = '';
          if (f) onPick(f);
        }}
      />
    </>
  );
}

/** Thin determinate progress bar (used under media tiles during upload). */
export function UploadProgress({ percent }: { percent: number }) {
  return (
    <div className="h-1 w-full overflow-hidden bg-muted">
      <div
        className="h-full bg-primary transition-[width] duration-150"
        style={{ width: `${Math.min(100, Math.max(0, percent))}%` }}
      />
    </div>
  );
}

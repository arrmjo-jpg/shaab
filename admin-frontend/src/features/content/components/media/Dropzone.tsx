import { useRef, useState } from 'react';
import { UploadCloud } from 'lucide-react';

interface Props {
  accept: string;
  multiple?: boolean;
  label: string;
  hint?: string;
  disabled?: boolean;
  onFiles: (files: File[]) => void;
}

/**
 * Drag/drop + multi-select file dropzone. Reports the chosen files; the input
 * is reset after each pick so the same file can be re-selected.
 */
export function Dropzone({ accept, multiple = true, label, hint, disabled, onFiles }: Props) {
  const ref = useRef<HTMLInputElement | null>(null);
  const [over, setOver] = useState(false);

  const pick = (list: FileList | null) => {
    if (!list) return;
    const files = Array.from(list);
    if (files.length > 0) onFiles(files);
  };

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={() => !disabled && ref.current?.click()}
      onKeyDown={(e) => {
        if ((e.key === 'Enter' || e.key === ' ') && !disabled) ref.current?.click();
      }}
      onDragOver={(e) => {
        e.preventDefault();
        if (!disabled) setOver(true);
      }}
      onDragLeave={() => setOver(false)}
      onDrop={(e) => {
        e.preventDefault();
        setOver(false);
        if (!disabled) pick(e.dataTransfer.files);
      }}
      className={[
        'flex cursor-pointer flex-col items-center justify-center gap-2 border border-dashed p-6 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        over ? 'border-primary bg-primary/5' : 'border-input bg-background',
        disabled ? 'pointer-events-none opacity-50' : '',
      ].join(' ')}
    >
      <UploadCloud className="h-6 w-6 text-muted-foreground" />
      <p className="text-sm font-medium">{label}</p>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      <input
        ref={ref}
        type="file"
        accept={accept}
        multiple={multiple}
        className="hidden"
        onChange={(e) => {
          pick(e.target.files);
          e.target.value = '';
        }}
      />
    </div>
  );
}

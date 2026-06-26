import * as React from 'react';
import { FileJson, UploadCloud } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface JsonUploadFieldProps {
  label: string;
  configured: boolean;
  uploading: boolean;
  onUpload: (file: File) => void;
}

/** رفع ملف JSON (اعتماد Firebase) — لا معاينة صورة. */
export function JsonUploadField({ label, configured, uploading, onUpload }: JsonUploadFieldProps) {
  const { t } = useTranslation('thirdParty');
  const [file, setFile] = React.useState<File | null>(null);
  const [drag, setDrag] = React.useState(false);
  const inputRef = React.useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium">{label}</p>
        {configured ? (
          <Badge variant="success">{t('common.configured')}</Badge>
        ) : (
          <Badge variant="muted">{t('common.notConfigured')}</Badge>
        )}
      </div>

      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDrag(true);
        }}
        onDragLeave={() => setDrag(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDrag(false);
          setFile(e.dataTransfer.files?.[0] ?? null);
        }}
        onClick={() => inputRef.current?.click()}
        className={cn(
          'flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-border bg-secondary/30 px-4 py-6 transition-colors',
          drag && 'border-primary bg-primary/5',
        )}
      >
        {file ? <FileJson className="h-6 w-6 text-primary" /> : <UploadCloud className="h-6 w-6 text-muted-foreground" />}
        <span className="truncate text-sm text-muted-foreground">
          {file ? file.name : t('firebase.jsonHint')}
        </span>
        <input
          ref={inputRef}
          type="file"
          accept="application/json,.json"
          className="hidden"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={!file || uploading}
        onClick={() => file && onUpload(file)}
      >
        {uploading ? t('firebase.uploading') : t('firebase.uploadJson')}
      </Button>
    </div>
  );
}

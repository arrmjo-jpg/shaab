import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ImagePlus, Loader2, Send, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { useSendMessage } from '../chat.hooks';

interface StagedAttachment {
  id: number;
  preview: string | null;
  name: string;
}

export function MessageComposer({ conversationId }: { conversationId: number }) {
  const { t } = useTranslation('chat');
  const { error } = useToast();
  const send = useSendMessage();

  const [body, setBody] = useState('');
  const [attachment, setAttachment] = useState<StagedAttachment | null>(null);
  const [uploading, setUploading] = useState(false);

  const onFile = async (file?: File) => {
    if (!file) return;
    setUploading(true);
    try {
      const asset = await mediaLibraryService.upload(file);
      setAttachment({ id: asset.id, preview: asset.thumb ?? asset.url, name: asset.original_name });
    } catch {
      error(t('composer.attaching'));
    } finally {
      setUploading(false);
    }
  };

  const submit = () => {
    const text = body.trim();
    if (!text && !attachment) return;
    send.mutate(
      { conversationId, payload: { body: text || undefined, attachment_asset_id: attachment?.id ?? null } },
      {
        onSuccess: () => {
          setBody('');
          setAttachment(null);
        },
      },
    );
  };

  return (
    <div className="border-t border-border p-3">
      {attachment ? (
        <div className="mb-2 flex items-center gap-2 border border-border bg-muted/30 p-2">
          {attachment.preview ? (
            <img src={attachment.preview} alt="" className="h-10 w-10 border border-border object-cover" />
          ) : null}
          <span className="flex-1 truncate text-xs text-muted-foreground">{attachment.name}</span>
          <button type="button" onClick={() => setAttachment(null)} title={t('composer.removeAttachment')}>
            <X className="h-4 w-4 text-muted-foreground hover:text-destructive" />
          </button>
        </div>
      ) : null}

      <div className="flex items-end gap-2">
        <label className="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center border border-input text-muted-foreground transition-colors hover:border-primary" title={t('composer.attach')}>
          {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ImagePlus className="h-4 w-4" />}
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="hidden"
            onChange={(e) => void onFile(e.target.files?.[0])}
          />
        </label>

        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              submit();
            }
          }}
          rows={1}
          placeholder={t('composer.placeholder')}
          className="max-h-32 min-h-10 flex-1 resize-none border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />

        <Button
          size="icon"
          className="h-10 w-10 shrink-0"
          disabled={send.isPending || (!body.trim() && !attachment)}
          onClick={submit}
          title={t('composer.send')}
        >
          <Send className="h-4 w-4" />
        </Button>
      </div>
      <p className="mt-1 text-[10px] text-muted-foreground">{t('composer.hint')}</p>
    </div>
  );
}

import { useEffect, useState } from 'react';
import type { Editor } from '@tiptap/react';
import { useTranslation } from 'react-i18next';
import { Loader2, Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { useAiRewrite } from '../../ai.hooks';
import type { AiRewriteMode, ContentLocale } from '@/types/content.types';

interface Props {
  editor: Editor;
  locale: ContentLocale;
}

const MODES: AiRewriteMode[] = [
  'journalistic',
  'formal',
  'concise',
  'stronger',
  'simplified',
  'professional',
  'seo',
];

/**
 * إجراء «إعادة صياغة» على التحديد داخل المحرّر. يستبدل المقطع المختار فقط
 * بالصياغة الجديدة — لا يمسّ بقية المتن أبداً.
 */
export function RewriteMenu({ editor, locale }: Props) {
  const { t } = useTranslation('content');
  const { hasPermission } = useAuth();
  const [open, setOpen] = useState(false);
  // التحديد يُتتبَّع تفاعلياً: شريط الأدوات لا يُعيد الرسم تلقائياً عند تغيّر
  // التحديد في هذه النسخة، فنشترك في أحداث المحرّر لتحديث حالة الزر.
  const [range, setRange] = useState({ from: 0, to: 0 });
  const mut = useAiRewrite();

  useEffect(() => {
    const sync = () => {
      const { from, to } = editor.state.selection;
      setRange({ from, to });
    };
    sync();
    editor.on('selectionUpdate', sync);
    editor.on('transaction', sync);
    return () => {
      editor.off('selectionUpdate', sync);
      editor.off('transaction', sync);
    };
  }, [editor]);

  if (!hasPermission('ai.use')) return null;

  const { from, to } = range;
  const hasSelection = from !== to;

  const rewrite = (mode: AiRewriteMode) => {
    const text = editor.state.doc.textBetween(from, to, '\n').trim();
    if (text === '') return;
    setOpen(false);
    mut.mutate(
      { text, mode, locale },
      {
        onSuccess: (result) => {
          if (result.trim() === '') return;
          // استبدال المقطع المختار فقط بالصياغة الجديدة.
          editor.chain().focus().insertContentAt({ from, to }, result).run();
        },
      },
    );
  };

  return (
    <div className="relative inline-block">
      <button
        type="button"
        className={cn(
          'inline-flex h-9 items-center gap-1 border border-transparent px-2 text-xs font-medium text-primary transition-colors',
          hasSelection && !mut.isPending
            ? 'hover:bg-primary/10'
            : 'cursor-not-allowed opacity-50',
        )}
        disabled={!hasSelection || mut.isPending}
        onClick={() => setOpen((v) => !v)}
        title={hasSelection ? t('ai.rewrite.button') : t('ai.rewrite.selectFirst')}
      >
        {mut.isPending ? (
          <Loader2 className="h-4 w-4 animate-spin" />
        ) : (
          <Sparkles className="h-4 w-4" />
        )}
        {t('ai.rewrite.button')}
      </button>

      {open && hasSelection ? (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} aria-hidden />
          <div className="absolute z-50 mt-1 w-40 border border-border bg-background py-1 shadow-soft-lg start-0">
            {MODES.map((mode) => (
              <button
                key={mode}
                type="button"
                onClick={() => rewrite(mode)}
                className="block w-full px-3 py-1.5 text-start text-xs transition-colors hover:bg-primary/10"
              >
                {t(`ai.rewrite.modes.${mode}`)}
              </button>
            ))}
          </div>
        </>
      ) : null}
    </div>
  );
}

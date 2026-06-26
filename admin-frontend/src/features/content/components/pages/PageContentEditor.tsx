import { useEffect, useMemo, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Link from '@tiptap/extension-link';
import { useTranslation } from 'react-i18next';
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  Heading2,
  Heading3,
  Heading4,
  Italic,
  Link as LinkIcon,
  List,
  ListOrdered,
  Minus,
  Quote,
  Redo2,
  Strikethrough,
  Underline as UnderlineIcon,
  Undo2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ContentLocale } from '@/types/content.types';
import { isSafeLinkUrl } from '@/features/content/editor/LinkDialog';

interface Props {
  value: string;
  onChange: (html: string) => void;
  locale: ContentLocale;
  disabled?: boolean;
}

/**
 * محرّر صفحات ثابتة TipTap — يطابق قائمة سماح PageContentSanitizer (HTMLPurifier)
 * بالضبط: عناوين h2/h3/h4 فقط (لا h1)، فقرات، قوائم، اقتباس، خط فاصل، روابط آمنة،
 * تعليمات الصياغة (bold/italic/underline/strike). لا صور/تضمينات/استطلاعات (لا توجد
 * نقاط رفع للصفحات؛ والتنقية الخلفية ستزيل أي وسم غير مدرَج هنا حتى لو لُصق).
 *
 * المخرَج HTML عبر editor.getHTML() — يُمرَّر كحقل `content` للـ backend ليُنقّى مرّةً
 * أخرى دفاعاً بالعمق.
 */
export function PageContentEditor({ value, onChange, locale, disabled }: Props) {
  const { t } = useTranslation('content');
  // نص خامّ من المحرّر — يُستخدَم لاحتساب الكلمات/الأحرف/وقت القراءة.
  // يُحدَّث كلّ update + عند ترطيب قيمة خارجية (وضع التعديل).
  const [plainText, setPlainText] = useState('');

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3, 4] },
        dropcursor: { width: 2 },
      }),
      Underline,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
        alignments: ['left', 'center', 'right', 'justify'],
      }),
      Link.configure({
        autolink: false,
        openOnClick: false,
        HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer nofollow' },
        protocols: ['http', 'https', 'mailto'],
        validate: (href) => isSafeLinkUrl(href),
      }),
    ],
    content: value || '<p></p>',
    editable: !disabled,
    parseOptions: { preserveWhitespace: 'full' },
    onUpdate: ({ editor: ed }) => {
      onChange(ed.getHTML());
      setPlainText(ed.getText());
    },
    editorProps: {
      attributes: {
        class:
          'min-h-[320px] w-full border border-input bg-background px-4 py-3 text-sm leading-7 focus:outline-none focus:ring-2 focus:ring-ring prose prose-sm max-w-none',
        dir: locale === 'ar' ? 'rtl' : 'ltr',
        spellcheck: 'true',
      },
    },
  });

  // إعادة مزامنة قيمة خارجية (مثلاً عند ترطيب نموذج التعديل بعد جلب الصفحة).
  useEffect(() => {
    if (!editor) return;
    const next = value || '<p></p>';
    if (editor.getHTML() === next) return;
    editor.commands.setContent(next, { emitUpdate: false });
    setPlainText(editor.getText());
  }, [value, editor]);

  useEffect(() => {
    if (!editor) return;
    editor.setEditable(!disabled);
  }, [disabled, editor]);

  // إحصاءات المحتوى — تُحسَب من النص الخامّ. وقت القراءة معدّل قياسي 200 كلمة/دقيقة.
  const stats = useMemo(() => {
    const text = plainText.trim();
    const words = text === '' ? 0 : text.split(/\s+/u).length;
    const chars = text.length;
    const minutes = words === 0 ? 0 : Math.max(1, Math.round(words / 200));
    return { words, chars, minutes };
  }, [plainText]);

  if (!editor) return null;

  const setLink = () => {
    const previous = editor.getAttributes('link').href as string | undefined;
    const input = window.prompt(t('editor.link.urlLabel'), previous ?? '');
    if (input === null) return;
    const url = input.trim();
    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }
    if (!isSafeLinkUrl(url)) {
      window.alert(t('editor.link.invalid'));
      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  };

  const isActive = (name: string, attrs?: Record<string, unknown>): boolean =>
    editor.isActive(name, attrs);

  return (
    <div className="border border-border bg-background">
      <div className="flex flex-wrap items-center gap-1 border-b border-border bg-muted/40 p-1.5">
        <ToolGroup>
          <ToolBtn label={t('editor.tool.bold')} onClick={() => editor.chain().focus().toggleBold().run()} active={isActive('bold')}>
            <Bold className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.italic')} onClick={() => editor.chain().focus().toggleItalic().run()} active={isActive('italic')}>
            <Italic className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.underline')} onClick={() => editor.chain().focus().toggleUnderline().run()} active={isActive('underline')}>
            <UnderlineIcon className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.strike')} onClick={() => editor.chain().focus().toggleStrike().run()} active={isActive('strike')}>
            <Strikethrough className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>

        <ToolGroup>
          <ToolBtn label={t('editor.tool.h2')} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} active={isActive('heading', { level: 2 })}>
            <Heading2 className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.h3')} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} active={isActive('heading', { level: 3 })}>
            <Heading3 className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('page.editor.h4')} onClick={() => editor.chain().focus().toggleHeading({ level: 4 }).run()} active={isActive('heading', { level: 4 })}>
            <Heading4 className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>

        <ToolGroup>
          <ToolBtn label={t('editor.tool.bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()} active={isActive('bulletList')}>
            <List className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()} active={isActive('orderedList')}>
            <ListOrdered className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()} active={isActive('blockquote')}>
            <Quote className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.hr')} onClick={() => editor.chain().focus().setHorizontalRule().run()}>
            <Minus className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>

        <ToolGroup>
          <ToolBtn label={t('editor.tool.alignRight')} onClick={() => editor.chain().focus().setTextAlign('right').run()} active={editor.isActive({ textAlign: 'right' })}>
            <AlignRight className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.alignCenter')} onClick={() => editor.chain().focus().setTextAlign('center').run()} active={editor.isActive({ textAlign: 'center' })}>
            <AlignCenter className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.alignJustify')} onClick={() => editor.chain().focus().setTextAlign('justify').run()} active={editor.isActive({ textAlign: 'justify' })}>
            <AlignJustify className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.alignLeft')} onClick={() => editor.chain().focus().setTextAlign('left').run()} active={editor.isActive({ textAlign: 'left' })}>
            <AlignLeft className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>

        <ToolGroup>
          <ToolBtn label={t('editor.tool.link')} onClick={setLink} active={isActive('link')}>
            <LinkIcon className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>

        <ToolGroup>
          <ToolBtn label={t('editor.tool.undo')} onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()}>
            <Undo2 className="h-4 w-4" />
          </ToolBtn>
          <ToolBtn label={t('editor.tool.redo')} onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()}>
            <Redo2 className="h-4 w-4" />
          </ToolBtn>
        </ToolGroup>
      </div>

      <EditorContent editor={editor} />

      {/* ── شريط حالة سفلي: كلمات / أحرف / وقت قراءة مقدّر ── */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border bg-muted/30 px-3 py-1.5 text-xs text-muted-foreground">
        <span className="tabular-nums">
          {t('page.editor.stats.words', { count: stats.words })} · {t('page.editor.stats.chars', { count: stats.chars })}
        </span>
        <span className="tabular-nums">
          {stats.minutes > 0
            ? t('page.editor.stats.readingTime', { count: stats.minutes })
            : t('page.editor.stats.empty')}
        </span>
      </div>
    </div>
  );
}

function ToolGroup({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-0.5 border-e border-border pe-1 last:border-0 last:pe-0">
      {children}
    </div>
  );
}

function ToolBtn({
  children,
  label,
  onClick,
  active,
  disabled,
}: {
  children: React.ReactNode;
  label: string;
  onClick: () => void;
  active?: boolean;
  disabled?: boolean;
}) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      className={cn('h-8 w-8', active && 'bg-accent text-accent-foreground')}
      title={label}
      aria-label={label}
      aria-pressed={active}
      disabled={disabled}
      onClick={onClick}
    >
      {children}
    </Button>
  );
}

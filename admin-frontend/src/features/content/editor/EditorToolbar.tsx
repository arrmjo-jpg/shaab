import { useRef, useState } from 'react';
import type { Editor } from '@tiptap/react';
import { useTranslation } from 'react-i18next';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Strikethrough,
  Code,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Code2,
  Minus,
  Image as ImageIcon,
  Link2,
  Video,
  BarChart3,
  Table as TableIcon,
  AlignRight,
  AlignCenter,
  AlignLeft,
  AlignJustify,
  Undo2,
  Redo2,
  Loader2,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { LinkDialog } from './LinkDialog';
import { EmbedDialog } from './EmbedDialog';
import { PollDialog } from './PollDialog';
import { RewriteMenu } from '../components/ai/RewriteMenu';
import type { ContentLocale } from '@/types/content.types';

interface Props {
  editor: Editor;
  /** Required for inline image upload — null on CREATE (no article yet). */
  articleId: number | null;
  onUploadImage: (file: File) => Promise<{ url: string; alt?: string } | null>;
  uploading?: boolean;
  locale: ContentLocale;
}

type Variant = 'plain' | 'active';

function btnCls(variant: Variant, disabled?: boolean) {
  return cn(
    'inline-flex h-9 w-9 items-center justify-center border border-transparent text-muted-foreground transition-colors',
    !disabled && 'hover:bg-accent hover:text-foreground',
    variant === 'active' && 'bg-primary/15 text-primary',
    disabled && 'cursor-not-allowed opacity-50',
  );
}

export function EditorToolbar({ editor, articleId, onUploadImage, uploading, locale }: Props) {
  const { t } = useTranslation('content');
  const fileRef = useRef<HTMLInputElement | null>(null);

  const [linkOpen, setLinkOpen] = useState(false);
  const [embedOpen, setEmbedOpen] = useState(false);
  const [pollOpen, setPollOpen] = useState(false);

  const can = editor.can();
  const isOn = (mark: string, attrs?: Record<string, unknown>) =>
    attrs ? editor.isActive(mark, attrs) : editor.isActive(mark);

  const triggerImagePick = () => {
    if (articleId === null) return;
    fileRef.current?.click();
  };

  const onPickFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    e.target.value = ''; // allow re-picking the same file
    if (!f) return;
    const result = await onUploadImage(f);
    if (result) {
      editor.chain().focus().setImage({ src: result.url, alt: result.alt ?? '' }).run();
    }
  };

  const openLink = () => {
    setLinkOpen(true);
  };

  const onLinkConfirm = (url: string) => {
    editor
      .chain()
      .focus()
      .extendMarkRange('link')
      .setLink({ href: url })
      .run();
    setLinkOpen(false);
  };

  const onLinkRemove = () => {
    editor.chain().focus().extendMarkRange('link').unsetLink().run();
    setLinkOpen(false);
  };

  const currentLinkHref = (editor.getAttributes('link') as { href?: string }).href ?? '';

  return (
    <div className="flex flex-wrap items-center gap-1 border-b border-border bg-muted/30 p-1">
      <button
        type="button"
        className={btnCls(isOn('bold') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleBold().run()}
        title={t('editor.tool.bold')}
      >
        <Bold className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('italic') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleItalic().run()}
        title={t('editor.tool.italic')}
      >
        <Italic className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('underline') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleUnderline().run()}
        title={t('editor.tool.underline')}
      >
        <UnderlineIcon className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('strike') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleStrike().run()}
        title={t('editor.tool.strike')}
      >
        <Strikethrough className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('code') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleCode().run()}
        title={t('editor.tool.inlineCode')}
      >
        <Code className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <button
        type="button"
        className={btnCls(isOn('heading', { level: 1 }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
        title={t('editor.tool.h1')}
      >
        <Heading1 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('heading', { level: 2 }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
        title={t('editor.tool.h2')}
      >
        <Heading2 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('heading', { level: 3 }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
        title={t('editor.tool.h3')}
      >
        <Heading3 className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <button
        type="button"
        className={btnCls(isOn('bulletList') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleBulletList().run()}
        title={t('editor.tool.bulletList')}
      >
        <List className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('orderedList') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleOrderedList().run()}
        title={t('editor.tool.orderedList')}
      >
        <ListOrdered className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('blockquote') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleBlockquote().run()}
        title={t('editor.tool.blockquote')}
      >
        <Quote className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(isOn('codeBlock') ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().toggleCodeBlock().run()}
        title={t('editor.tool.codeBlock')}
      >
        <Code2 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls('plain')}
        onClick={() => editor.chain().focus().setHorizontalRule().run()}
        title={t('editor.tool.hr')}
      >
        <Minus className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <button
        type="button"
        className={btnCls(editor.isActive({ textAlign: 'right' }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().setTextAlign('right').run()}
        title={t('editor.tool.alignRight')}
      >
        <AlignRight className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(editor.isActive({ textAlign: 'center' }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().setTextAlign('center').run()}
        title={t('editor.tool.alignCenter')}
      >
        <AlignCenter className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(editor.isActive({ textAlign: 'justify' }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().setTextAlign('justify').run()}
        title={t('editor.tool.alignJustify')}
      >
        <AlignJustify className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls(editor.isActive({ textAlign: 'left' }) ? 'active' : 'plain')}
        onClick={() => editor.chain().focus().setTextAlign('left').run()}
        title={t('editor.tool.alignLeft')}
      >
        <AlignLeft className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <button
        type="button"
        className={btnCls(isOn('link') ? 'active' : 'plain')}
        onClick={openLink}
        title={t('editor.tool.link')}
      >
        <Link2 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls('plain', articleId === null || uploading)}
        disabled={articleId === null || uploading}
        onClick={triggerImagePick}
        title={
          articleId === null
            ? t('editor.tool.imageSaveFirst')
            : t('editor.tool.image')
        }
      >
        {uploading ? (
          <Loader2 className="h-4 w-4 animate-spin" />
        ) : (
          <ImageIcon className="h-4 w-4" />
        )}
      </button>
      <input
        ref={fileRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={onPickFile}
      />
      <button
        type="button"
        className={btnCls('plain')}
        onClick={() => setEmbedOpen(true)}
        title={t('editor.tool.embed')}
      >
        <Video className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls('plain')}
        onClick={() => setPollOpen(true)}
        title={t('editor.tool.poll')}
      >
        <BarChart3 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls('plain')}
        onClick={() =>
          editor
            .chain()
            .focus()
            .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
            .run()
        }
        title={t('editor.tool.table')}
      >
        <TableIcon className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <button
        type="button"
        className={btnCls('plain', !can.undo())}
        disabled={!can.undo()}
        onClick={() => editor.chain().focus().undo().run()}
        title={t('editor.tool.undo')}
      >
        <Undo2 className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={btnCls('plain', !can.redo())}
        disabled={!can.redo()}
        onClick={() => editor.chain().focus().redo().run()}
        title={t('editor.tool.redo')}
      >
        <Redo2 className="h-4 w-4" />
      </button>

      <div className="mx-1 h-6 w-px bg-border" aria-hidden />

      <RewriteMenu editor={editor} locale={locale} />

      <LinkDialog
        open={linkOpen}
        initialUrl={currentLinkHref}
        onClose={() => setLinkOpen(false)}
        onConfirm={onLinkConfirm}
        onRemove={currentLinkHref ? onLinkRemove : undefined}
      />
      <EmbedDialog
        open={embedOpen}
        onClose={() => setEmbedOpen(false)}
        onConfirm={(attrs) => {
          editor.chain().focus().insertEmbed(attrs).run();
          setEmbedOpen(false);
        }}
      />
      <PollDialog
        open={pollOpen}
        onClose={() => setPollOpen(false)}
        onConfirm={(attrs) => {
          editor.chain().focus().insertPoll(attrs).run();
          setPollOpen(false);
        }}
      />
    </div>
  );
}

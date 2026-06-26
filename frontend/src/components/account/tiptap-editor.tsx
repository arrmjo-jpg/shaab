'use client';

import { mergeAttributes, Node } from '@tiptap/core';
import { Image } from '@tiptap/extension-image';
import { TextAlign } from '@tiptap/extension-text-align';
import {
  EditorContent,
  NodeViewWrapper,
  type NodeViewProps,
  ReactNodeViewRenderer,
  useEditor,
  useEditorState,
} from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import { useRef, useState } from 'react';

import {
  AlignCenterIcon,
  AlignJustifyIcon,
  AlignLeftIcon,
  AlignRightIcon,
  BoldIcon,
  Heading2Icon,
  Heading3Icon,
  ImagePlusIcon,
  ItalicIcon,
  LinkIcon,
  ListIcon,
  ListOrderedIcon,
  QuoteIcon,
  RedoIcon,
  StrikethroughIcon,
  UnderlineIcon,
  UndoIcon,
  VideoIcon,
} from '@/components/icons';

export interface TiptapDoc {
  type: 'doc';
  content?: unknown[];
}

const IMG_ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];
const IMG_MAX = 5 * 1024 * 1024;

// استخراج معرّف يوتيوب + بناء embed_url (يطابق ExternalVideoResolver الخلفيّ).
function parseYouTube(url: string): { id: string; embedUrl: string } | null {
  const m = url.match(
    /(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/live\/|youtube(?:-nocookie)?\.com\/embed\/)([\w-]{6,20})/i,
  );
  if (!m) return null;
  return { id: m[1], embedUrl: `https://www.youtube.com/embed/${m[1]}?rel=0&modestbranding=1` };
}

function isImageUrl(url: string): boolean {
  return /^https?:\/\/.+\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(url);
}

// ─── عقدة التضمين (تطابق قائمة سماح TipTapSanitizer: provider+embed_url+id) ──
function EmbedView({ node }: NodeViewProps) {
  const url = node.attrs.embed_url as string | null;
  return (
    <NodeViewWrapper className="my-3" dir="ltr">
      {url ? (
        <div className="relative w-full overflow-hidden bg-black" style={{ aspectRatio: '16 / 9' }}>
          <iframe
            src={url}
            title="فيديو مضمّن"
            className="absolute inset-0 size-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          />
        </div>
      ) : (
        <div className="border border-border bg-surface-2 p-3 text-caption text-muted">فيديو غير صالح</div>
      )}
    </NodeViewWrapper>
  );
}

const Embed = Node.create({
  name: 'embed',
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes() {
    return {
      provider: { default: 'youtube' },
      embed_url: { default: null },
      id: { default: null },
    };
  },
  parseHTML() {
    return [{ tag: 'div[data-embed]' }];
  },
  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-embed': '' })];
  },
  addNodeView() {
    return ReactNodeViewRenderer(EmbedView);
  },
});

/**
 * محرّر نصوص غنيّ (TipTap) — يُخرِج content_json مطابقاً لقائمة سماح TipTapSanitizer.
 * لصق ذكيّ: صورة منسوخة تُرفَع عبر طبقة ملكيّة الكاتب؛ رابط صورة يُعرَض صورةً؛ رابط
 * يوتيوب يُضمَّن مشغّلاً (عقدة embed) لا رابطاً. + محاذاة النصّ.
 */
export function TiptapEditor({ onChange }: { onChange: (doc: TiptapDoc, text: string) => void }) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function uploadImage(file: File) {
    if (!editor) return;
    setError(null);
    if (!IMG_ACCEPT.includes(file.type)) {
      setError('صيغة صورة غير مدعومة (JPEG/PNG/WebP).');
      return;
    }
    if (file.size > IMG_MAX) {
      setError('حجم الصورة يتجاوز 5 ميغابايت.');
      return;
    }
    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('/api/media', { method: 'POST', body: fd });
      const data: { success?: boolean; message?: string; asset?: { url?: string } } = await res
        .json()
        .catch(() => ({}));
      if (!res.ok || !data.success || !data.asset?.url) {
        setError(data.message || 'تعذّر رفع الصورة.');
        return;
      }
      editor.chain().focus().setImage({ src: data.asset.url, alt: file.name }).run();
    } catch {
      setError('تعذّر الاتصال بالخادم.');
    } finally {
      setUploading(false);
    }
  }

  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        link: {
          openOnClick: false,
          autolink: true,
          HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
        },
      }),
      Image.configure({ allowBase64: false }),
      TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
      Embed,
    ],
    content: '',
    editorProps: {
      attributes: { class: 'tiptap-content px-3 py-3', dir: 'rtl' },
      // لصق ذكيّ: ملف صورة → رفع؛ رابط يوتيوب → تضمين؛ رابط صورة → عرض.
      handlePaste: (_view, event) => {
        const files = Array.from(event.clipboardData?.files ?? []).filter((f) => IMG_ACCEPT.includes(f.type));
        if (files.length > 0) {
          files.forEach((f) => void uploadImage(f));
          return true;
        }
        const text = event.clipboardData?.getData('text/plain')?.trim();
        if (text) {
          const yt = parseYouTube(text);
          if (yt) {
            editor
              ?.chain()
              .focus()
              .insertContent({ type: 'embed', attrs: { provider: 'youtube', embed_url: yt.embedUrl, id: yt.id } })
              .run();
            return true;
          }
          if (isImageUrl(text)) {
            editor?.chain().focus().setImage({ src: text }).run();
            return true;
          }
        }
        return false;
      },
    },
    onUpdate: ({ editor }) => onChange(editor.getJSON() as TiptapDoc, editor.getText()),
  });

  const s = useEditorState({
    editor,
    selector: ({ editor }) =>
      editor
        ? {
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            underline: editor.isActive('underline'),
            strike: editor.isActive('strike'),
            h2: editor.isActive('heading', { level: 2 }),
            h3: editor.isActive('heading', { level: 3 }),
            bullet: editor.isActive('bulletList'),
            ordered: editor.isActive('orderedList'),
            quote: editor.isActive('blockquote'),
            link: editor.isActive('link'),
            alignRight: editor.isActive({ textAlign: 'right' }),
            alignCenter: editor.isActive({ textAlign: 'center' }),
            alignLeft: editor.isActive({ textAlign: 'left' }),
            alignJustify: editor.isActive({ textAlign: 'justify' }),
            canUndo: editor.can().undo(),
            canRedo: editor.can().redo(),
          }
        : null,
  });

  function promptLink() {
    if (!editor) return;
    const prev = (editor.getAttributes('link').href as string | undefined) ?? 'https://';
    const url = window.prompt('رابط (https://):', prev);
    if (url === null) return;
    if (url.trim() === '') {
      editor.chain().focus().unsetLink().run();
      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
  }

  function promptEmbed() {
    if (!editor) return;
    const url = window.prompt('رابط فيديو يوتيوب:', 'https://www.youtube.com/watch?v=');
    if (!url) return;
    const yt = parseYouTube(url.trim());
    if (!yt) {
      setError('رابط يوتيوب غير صالح.');
      return;
    }
    editor
      .chain()
      .focus()
      .insertContent({ type: 'embed', attrs: { provider: 'youtube', embed_url: yt.embedUrl, id: yt.id } })
      .run();
  }

  if (!editor) {
    return <div className="h-64 border border-border bg-surface" aria-hidden />;
  }

  return (
    <div className="border border-border bg-surface">
      <div className="flex flex-wrap items-center gap-0.5 border-b border-border p-1.5">
        <ToolBtn label="عريض" active={s?.bold} onClick={() => editor.chain().focus().toggleBold().run()}>
          <BoldIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="مائل" active={s?.italic} onClick={() => editor.chain().focus().toggleItalic().run()}>
          <ItalicIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="تحته خط" active={s?.underline} onClick={() => editor.chain().focus().toggleUnderline().run()}>
          <UnderlineIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="يتوسّطه خط" active={s?.strike} onClick={() => editor.chain().focus().toggleStrike().run()}>
          <StrikethroughIcon className="size-4" aria-hidden />
        </ToolBtn>
        <Sep />
        <ToolBtn label="عنوان كبير" active={s?.h2} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
          <Heading2Icon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="عنوان فرعيّ" active={s?.h3} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}>
          <Heading3Icon className="size-4" aria-hidden />
        </ToolBtn>
        <Sep />
        <ToolBtn label="قائمة نقطيّة" active={s?.bullet} onClick={() => editor.chain().focus().toggleBulletList().run()}>
          <ListIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="قائمة رقميّة" active={s?.ordered} onClick={() => editor.chain().focus().toggleOrderedList().run()}>
          <ListOrderedIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="اقتباس" active={s?.quote} onClick={() => editor.chain().focus().toggleBlockquote().run()}>
          <QuoteIcon className="size-4" aria-hidden />
        </ToolBtn>
        <Sep />
        <ToolBtn label="محاذاة لليمين" active={s?.alignRight} onClick={() => editor.chain().focus().setTextAlign('right').run()}>
          <AlignRightIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="توسيط" active={s?.alignCenter} onClick={() => editor.chain().focus().setTextAlign('center').run()}>
          <AlignCenterIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="محاذاة لليسار" active={s?.alignLeft} onClick={() => editor.chain().focus().setTextAlign('left').run()}>
          <AlignLeftIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="ضبط" active={s?.alignJustify} onClick={() => editor.chain().focus().setTextAlign('justify').run()}>
          <AlignJustifyIcon className="size-4" aria-hidden />
        </ToolBtn>
        <Sep />
        <ToolBtn label="رابط" active={s?.link} onClick={promptLink}>
          <LinkIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="إدراج صورة" onClick={() => fileRef.current?.click()} disabled={uploading}>
          <ImagePlusIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="إدراج فيديو يوتيوب" onClick={promptEmbed}>
          <VideoIcon className="size-4" aria-hidden />
        </ToolBtn>
        <Sep />
        <ToolBtn label="تراجع" onClick={() => editor.chain().focus().undo().run()} disabled={!s?.canUndo}>
          <UndoIcon className="size-4" aria-hidden />
        </ToolBtn>
        <ToolBtn label="إعادة" onClick={() => editor.chain().focus().redo().run()} disabled={!s?.canRedo}>
          <RedoIcon className="size-4" aria-hidden />
        </ToolBtn>
        {uploading && <span className="px-2 text-caption text-muted">جارٍ رفع الصورة…</span>}
      </div>

      <EditorContent editor={editor} />

      <input
        ref={fileRef}
        type="file"
        accept={IMG_ACCEPT.join(',')}
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void uploadImage(f);
          if (fileRef.current) fileRef.current.value = '';
        }}
      />

      {error && (
        <p role="alert" className="border-t border-border px-3 py-2 text-caption text-danger">
          {error}
        </p>
      )}
    </div>
  );
}

function ToolBtn({
  children,
  label,
  active,
  disabled,
  onClick,
}: {
  children: React.ReactNode;
  label: string;
  active?: boolean;
  disabled?: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      title={label}
      aria-label={label}
      aria-pressed={active ?? false}
      className={`inline-flex size-8 items-center justify-center text-fg transition-colors hover:bg-surface-2 disabled:opacity-40 ${active ? 'bg-surface-2 text-primary' : ''}`}
    >
      {children}
    </button>
  );
}

function Sep() {
  return <span className="mx-0.5 h-5 w-px bg-border" aria-hidden />;
}

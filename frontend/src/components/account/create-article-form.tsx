'use client';

import { useRouter } from 'next/navigation';
import { useId, useState, useTransition } from 'react';

import { MediaImageField, type UploadedImage } from '@/components/account/media-image-field';
import { TiptapEditor, type TiptapDoc } from '@/components/account/tiptap-editor';
import { TagIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { createArticleAction } from '@/lib/account-actions';
import type { CategoryOption } from '@/lib/categories';

const FIELD =
  'w-full border border-border bg-surface px-3 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

type ArticleKind = '' | 'news' | 'opinion';

export function CreateArticleForm({
  newsCategories,
  opinionCategories,
}: {
  newsCategories: CategoryOption[];
  opinionCategories: CategoryOption[];
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [articleType, setArticleType] = useState<ArticleKind>(''); // فارغ افتراضيّاً — اختيار واعٍ
  const [content, setContent] = useState<TiptapDoc | null>(null);
  const [contentText, setContentText] = useState('');
  const [cover, setCover] = useState<UploadedImage | null>(null);

  // الأقسام تتبع النوع: لا شيء حتى يُختار النوع، ثمّ المجموعة المفلترة الموافقة.
  const categories = articleType === 'news' ? newsCategories : articleType === 'opinion' ? opinionCategories : [];
  const kindLabel = articleType === 'news' ? 'الخبر' : articleType === 'opinion' ? 'المقال' : 'المحتوى';

  const typeId = useId();
  const titleId = useId();
  const subtitleId = useId();
  const catId = useId();
  const tagsId = useId();

  function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const fd = new FormData(event.currentTarget);
    const title = String(fd.get('title') ?? '').trim();
    const subtitle = String(fd.get('subtitle') ?? '').trim();
    const primaryCategoryId = Number(fd.get('category') ?? 0);
    const tags = String(fd.get('tags') ?? '')
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean)
      .slice(0, 30);

    if (!articleType) {
      setError('يرجى اختيار النوع: خبر أو مقال.');
      return;
    }
    if (!primaryCategoryId) {
      setError('يرجى اختيار القسم.');
      return;
    }
    if (!content || contentText.trim().length < 2) {
      setError('يرجى كتابة نصّ المحتوى.');
      return;
    }

    startTransition(async () => {
      const r = await createArticleAction({
        title,
        type: articleType,
        primaryCategoryId,
        contentJson: content,
        subtitle,
        tags,
        coverAssetId: cover?.id ?? null,
      });
      if (!r.ok) {
        setError(r.message);
        return;
      }
      router.push('/account/content?tab=articles');
    });
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-5">
      {error && (
        <div role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* النوع — فارغ افتراضيّاً + إجباريّ، يقود الأقسام */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={typeId} className="text-sm font-medium text-fg">
          النوع <span className="text-danger">*</span>
        </label>
        <select
          id={typeId}
          required
          value={articleType}
          onChange={(e) => setArticleType(e.target.value as ArticleKind)}
          className={`${FIELD} h-11`}
        >
          <option value="" disabled>
            اختر النوع…
          </option>
          <option value="news">خبر</option>
          <option value="opinion">مقال</option>
        </select>
      </div>

      {/* العنوان */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={titleId} className="text-sm font-medium text-fg">
          العنوان <span className="text-danger">*</span>
        </label>
        <input id={titleId} name="title" type="text" required minLength={2} maxLength={200} className={`${FIELD} h-11`} />
      </div>

      {/* العنوان الفرعيّ */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={subtitleId} className="text-sm font-medium text-fg">
          العنوان الفرعيّ (اختياري)
        </label>
        <input id={subtitleId} name="subtitle" type="text" maxLength={250} className={`${FIELD} h-11`} />
      </div>

      {/* القسم — يظهر بعد اختيار النوع، ويتبدّل معه */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={catId} className="text-sm font-medium text-fg">
          القسم <span className="text-danger">*</span>
        </label>
        {articleType === '' ? (
          <p className="border border-border bg-surface-2 px-3 py-2.5 text-sm text-muted">
            اختر النوع أوّلاً لعرض الأقسام.
          </p>
        ) : categories.length === 0 ? (
          <p className="border border-warning/30 bg-warning/10 px-3 py-2.5 text-sm text-warning">
            لا تتوفّر أقسام لنوع «{kindLabel}» حاليّاً.
          </p>
        ) : (
          <select id={catId} key={articleType} name="category" required defaultValue="" className={`${FIELD} h-11`}>
            <option value="" disabled>
              اختر قسماً
            </option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        )}
      </div>

      {/* الوسوم */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={tagsId} className="flex items-center gap-1.5 text-sm font-medium text-fg">
          <TagIcon className="size-4 text-muted" aria-hidden />
          الوسوم (اختياري)
        </label>
        <input
          id={tagsId}
          name="tags"
          type="text"
          placeholder="افصل بين الوسوم بفاصلة: سياسة، اقتصاد، رياضة"
          className={`${FIELD} h-11`}
        />
      </div>

      {/* نصّ المحتوى — محرّر TipTap (تنسيق + صور داخل الخبر) */}
      <div className="flex flex-col gap-1.5">
        <span className="text-sm font-medium text-fg">
          نصّ {kindLabel} <span className="text-danger">*</span>
        </span>
        <TiptapEditor
          onChange={(doc, text) => {
            setContent(doc);
            setContentText(text);
          }}
        />
        <p className="text-caption text-muted">يمكنك التنسيق وإدراج أكثر من صورة داخل النصّ من شريط الأدوات.</p>
      </div>

      {/* الصورة الرئيسية — عبر طبقة ملكيّة وسائط الكاتب */}
      <MediaImageField
        label="الصورة الرئيسية (اختياري)"
        hint="JPEG/PNG/WebP، حتى 5 ميغابايت. تظهر كصورة غلاف للخبر."
        value={cover}
        onChange={setCover}
      />

      <div className="flex items-center gap-3">
        <Button type="submit" variant="primary" size="md" disabled={pending} aria-busy={pending}>
          {pending ? 'جارٍ الإرسال…' : 'إرسال للمراجعة'}
        </Button>
        <p className="text-caption text-muted">يُرسَل مباشرةً للمراجعة (لا يُحفظ كمسودّة).</p>
      </div>
    </form>
  );
}

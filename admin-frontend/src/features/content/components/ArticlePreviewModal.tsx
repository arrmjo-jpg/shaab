import { useTranslation } from 'react-i18next';
import { Check, Info, AlertTriangle } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Badge } from '@/components/ui/badge';
import { TipTapPreview } from '../lib/tiptap-render';
import { useArticlePreview } from '../hooks';
import type {
  ArticleStatus,
  ArticleType,
  ContentLocale,
  SeoGuidanceItem,
} from '@/types/content.types';

interface Props {
  open: boolean;
  onClose: () => void;
  /** When set (saved article), a TRUE server-rendered preview is fetched. */
  articleId?: number | null;
  title: string;
  subtitle?: string;
  excerpt?: string;
  type: ArticleType;
  status?: ArticleStatus | null;
  locale: ContentLocale;
  author?: { name: string } | null;
  primaryCategory?: { name: string } | null;
  coverUrl?: string | null;
  tags?: string[];
  flags?: { featured: boolean; breaking: boolean; header: boolean };
  doc: unknown;
}

const STATUS_TONES: Record<ArticleStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  submitted: 'default',
  in_review: 'default',
  scheduled: 'default',
  published: 'success',
  rejected: 'destructive',
  archived: 'muted',
};

const SEVERITY: Record<SeoGuidanceItem['severity'], { tone: string; Icon: typeof Check }> = {
  ok: { tone: 'text-emerald-600 dark:text-emerald-400', Icon: Check },
  info: { tone: 'text-muted-foreground', Icon: Info },
  warn: { tone: 'text-amber-600 dark:text-amber-400', Icon: AlertTriangle },
};

function SeoGuidance({ items }: { items: SeoGuidanceItem[] }) {
  const { t } = useTranslation('content');
  if (items.length === 0) return null;
  return (
    <div className="space-y-2 border-t border-border pt-4">
      <p className="text-xs font-bold uppercase text-muted-foreground">
        {t('articles.seoGuidance.title')}
      </p>
      <ul className="space-y-1.5">
        {items.map((item) => {
          const { tone, Icon } = SEVERITY[item.severity];
          return (
            <li key={item.key} className={`flex items-start gap-2 text-xs ${tone}`}>
              <Icon className="mt-0.5 h-3.5 w-3.5 shrink-0" />
              <span>{t(`articles.seoGuidance.${item.key}`, item.detail ?? {})}</span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

export function ArticlePreviewModal(props: Props) {
  const { t } = useTranslation('content');
  const {
    open, onClose, articleId, title, subtitle, excerpt, type, status, locale,
    author, primaryCategory, coverUrl, tags, flags, doc,
  } = props;

  // TRUE preview: server-rendered public payload (+ SEO guidance) for saved articles.
  const previewQ = useArticlePreview(articleId ?? null, open);
  const server = previewQ.data?.preview ?? null;
  const guidance = previewQ.data?.seo_guidance ?? [];

  // Prefer server values when available (exact public render); fall back to live form state.
  const showTitle = server?.title || title || t('articles.preview.untitled');
  const showSubtitle = server?.subtitle ?? subtitle;
  const showExcerpt = server?.excerpt ?? excerpt;
  const showCover = server?.media?.cover?.url ?? server?.media?.cover?.medium ?? coverUrl;
  const showLocale = server?.locale ?? locale;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={t('articles.preview.title')}
      description={
        server ? t('articles.preview.trueHint') : t('articles.preview.hint')
      }
      size="xl"
    >
      <article dir={showLocale === 'ar' ? 'rtl' : 'ltr'} className="space-y-5">
        <header className="space-y-3">
          <div className="flex flex-wrap items-center gap-2 text-xs">
            <Badge variant="muted">{t(`articles.type.${type}`)}</Badge>
            {status ? (
              <Badge variant={STATUS_TONES[status]}>{t(`articles.status.${status}`)}</Badge>
            ) : null}
            {server ? <Badge variant="success">{t('articles.preview.serverBadge')}</Badge> : null}
            {flags?.breaking ? (
              <Badge variant="destructive">{t('articles.form.isBreaking')}</Badge>
            ) : null}
            {flags?.featured ? (
              <Badge variant="success">{t('articles.form.isFeatured')}</Badge>
            ) : null}
            {flags?.header ? <Badge variant="default">{t('articles.form.isHeader')}</Badge> : null}
            <span className="uppercase text-muted-foreground">{showLocale}</span>
            {primaryCategory ? (
              <span className="text-muted-foreground">· {primaryCategory.name}</span>
            ) : null}
            {author ? (
              <span className="text-muted-foreground">
                · {t('articles.preview.byline', { name: author.name })}
              </span>
            ) : null}
          </div>
          <h1 className="text-3xl font-bold leading-tight">{showTitle}</h1>
          {showSubtitle ? <p className="text-lg text-muted-foreground">{showSubtitle}</p> : null}
        </header>

        {showCover ? (
          <div className="overflow-hidden">
            <img src={showCover} alt="" className="h-auto w-full" />
          </div>
        ) : null}

        {showExcerpt ? (
          <p className="border-s-4 border-primary/40 bg-muted/30 ps-4 text-sm italic text-muted-foreground">
            {showExcerpt}
          </p>
        ) : null}

        {/* Body: server-sanitized HTML for saved articles (exact public render),
            else a client-side render of the live editor doc. */}
        {server ? (
          // eslint-disable-next-line react/no-danger
          <div className="ProseMirror" dangerouslySetInnerHTML={{ __html: server.content_html }} />
        ) : (
          <div className="ProseMirror">
            <TipTapPreview doc={doc} />
          </div>
        )}

        {tags && tags.length > 0 ? (
          <div className="flex flex-wrap items-center gap-1.5 border-t border-border pt-4">
            <span className="text-xs uppercase text-muted-foreground">
              {t('articles.preview.tags')}
            </span>
            {tags.map((tag) => (
              <Badge key={tag} variant="muted">
                {tag}
              </Badge>
            ))}
          </div>
        ) : null}

        <SeoGuidance items={guidance} />
      </article>
    </Modal>
  );
}

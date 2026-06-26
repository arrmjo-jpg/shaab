import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Check,
  Copy,
  Facebook,
  Image as ImageIcon,
  Linkedin,
  MessageCircle,
  Radio,
  Send,
  Share2,
  Twitter,
} from 'lucide-react';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/hooks/useAuth';
import { env } from '@/lib/env';
import { useGeneralSettings } from '@/features/settings/hooks';

interface Props {
  title: string;
  excerpt: string;
  coverUrl: string | null;
  /** Backend canonical path: /{locale}/articles/{id}-{slug}. Null before save. */
  canonicalPath: string | null;
}

/**
 * Unified editorial sharing card — merges the social preview and share actions
 * into one cohesive, compact newsroom component (replaces the separate SEO
 * preview + share panels). Canonical sharing uses {id}-{slug} only.
 *
 * The WhatsApp Channel CTA + platform config come from current site settings
 * (GeneralSettings.social), gated by settings.view to avoid noise for editors
 * without that permission.
 */
export function ShareArticleCard({ title, excerpt, coverUrl, canonicalPath }: Props) {
  const { t } = useTranslation('content');
  const { success, error } = useToast();
  const { hasPermission } = useAuth();
  const [copied, setCopied] = useState(false);

  const settings = useGeneralSettings(hasPermission('settings.view'));
  const waChannel = settings.data?.social.whatsapp_channel?.trim() ?? '';

  const url = canonicalPath ? `${env.publicSiteUrl}${canonicalPath}` : null;
  const host = url ? url.replace(/^https?:\/\//, '').split('/')[0] : 'example.com';

  const copy = async () => {
    if (!url) return;
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      success(t('articles.share.copied'));
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      error(t('articles.share.copyFailed'));
    }
  };

  const u = url ? encodeURIComponent(url) : '';
  const tt = encodeURIComponent(title || '');
  const shares: Array<{ key: string; href: string; icon: typeof Twitter; label: string }> = url
    ? [
        { key: 'whatsapp', icon: MessageCircle, label: 'WhatsApp', href: `https://wa.me/?text=${tt}%20${u}` },
        { key: 'x', icon: Twitter, label: 'X', href: `https://twitter.com/intent/tweet?url=${u}&text=${tt}` },
        { key: 'facebook', icon: Facebook, label: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
        { key: 'telegram', icon: Send, label: 'Telegram', href: `https://t.me/share/url?url=${u}&text=${tt}` },
        { key: 'linkedin', icon: Linkedin, label: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}` },
      ]
    : [];

  return (
    <section className="overflow-hidden border border-border bg-background">
      <div className="flex items-center gap-2 border-b border-border px-3 py-2 text-xs font-bold uppercase text-muted-foreground">
        <Share2 className="h-3.5 w-3.5" />
        <span>{t('articles.share.title')}</span>
      </div>

      {/* Compact preview: thumbnail + title + excerpt + canonical URL */}
      <div className="flex gap-3 p-3">
        <div className="h-20 w-32 shrink-0 overflow-hidden border border-border bg-muted/40">
          {coverUrl ? (
            <img src={coverUrl} alt="" className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full w-full items-center justify-center">
              <ImageIcon className="h-5 w-5 text-muted-foreground/50" />
            </div>
          )}
        </div>
        <div className="min-w-0 flex-1 space-y-0.5">
          <p dir="ltr" className="truncate text-[11px] uppercase text-muted-foreground">
            {host}
          </p>
          <p className="line-clamp-1 text-sm font-semibold">
            {title || t('articles.form.seoSnippetEmpty')}
          </p>
          <p className="line-clamp-2 text-xs text-muted-foreground">
            {excerpt || t('articles.form.seoSnippetEmptyDesc')}
          </p>
        </div>
      </div>

      {/* Share actions */}
      {url ? (
        <div className="space-y-2 border-t border-border p-3">
          <div className="flex items-center gap-2">
            <input
              readOnly
              dir="ltr"
              value={url}
              onFocus={(e) => e.currentTarget.select()}
              className="h-8 min-w-0 flex-1 truncate border border-input bg-muted/30 px-2 text-xs text-muted-foreground outline-none"
            />
            <button
              type="button"
              onClick={copy}
              title={t('articles.share.copy')}
              className="inline-flex h-8 items-center gap-1 border border-input bg-background px-2 text-xs hover:border-primary hover:text-primary"
            >
              {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
              {t('articles.share.copy')}
            </button>
          </div>

          <div className="flex flex-wrap items-center gap-1.5">
            {shares.map((s) => {
              const Icon = s.icon;
              return (
                <a
                  key={s.key}
                  href={s.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  title={s.label}
                  className="inline-flex h-8 w-8 items-center justify-center border border-input text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                >
                  <Icon className="h-4 w-4" />
                </a>
              );
            })}
          </div>

          {waChannel ? (
            <a
              href={waChannel}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 border border-emerald-500/40 bg-emerald-500/5 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
            >
              <Radio className="h-3.5 w-3.5" />
              {t('articles.share.followChannel')}
            </a>
          ) : null}
        </div>
      ) : (
        <p className="border-t border-border p-3 text-xs text-muted-foreground">
          {t('articles.share.saveToShare')}
        </p>
      )}
    </section>
  );
}

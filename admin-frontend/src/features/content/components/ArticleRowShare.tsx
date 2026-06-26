import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Copy, Eye, Facebook, MessageCircle, Send, Twitter } from 'lucide-react';
import { useToast } from '@/hooks/useToast';
import { env } from '@/lib/env';
import type { ArticleData } from '@/types/content.types';

/**
 * صف مشاركة مُدمَج أسفل عنوان المقال في الجدول: روابط تواصل + نسخ الرابط +
 * استعراض على الموقع. يعتمد المسار القانوني {id}-{slug} (مثل بطاقة المشاركة).
 */
export function ArticleRowShare({ article }: { article: ArticleData }) {
  const { t } = useTranslation('content');
  const { success, error } = useToast();
  const [copied, setCopied] = useState(false);

  const url = article.canonical_path ? `${env.publicSiteUrl}${article.canonical_path}` : null;
  if (!url) return null;

  const u = encodeURIComponent(url);
  const tt = encodeURIComponent(article.title);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      success(t('articles.share.copied'));
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      error(t('articles.share.copyFailed'));
    }
  };

  const shares: Array<{ key: string; href: string; icon: typeof Twitter; label: string }> = [
    { key: 'whatsapp', icon: MessageCircle, label: 'WhatsApp', href: `https://wa.me/?text=${tt}%20${u}` },
    { key: 'x', icon: Twitter, label: 'X', href: `https://twitter.com/intent/tweet?url=${u}&text=${tt}` },
    { key: 'telegram', icon: Send, label: 'Telegram', href: `https://t.me/share/url?url=${u}&text=${tt}` },
    { key: 'facebook', icon: Facebook, label: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
  ];

  const iconCls =
    'inline-flex h-6 w-6 items-center justify-center text-muted-foreground transition-colors hover:text-primary';

  return (
    <div className="flex items-center gap-0.5">
      {shares.map((s) => {
        const Icon = s.icon;
        return (
          <a
            key={s.key}
            href={s.href}
            target="_blank"
            rel="noopener noreferrer"
            title={s.label}
            onClick={(e) => e.stopPropagation()}
            className={iconCls}
          >
            <Icon className="h-3.5 w-3.5" />
          </a>
        );
      })}
      <button type="button" onClick={copy} title={t('articles.share.copy')} className={iconCls}>
        {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
      </button>
      <a
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        title={t('articles.action.viewOnSite')}
        onClick={(e) => e.stopPropagation()}
        className={iconCls}
      >
        <Eye className="h-3.5 w-3.5" />
      </a>
    </div>
  );
}

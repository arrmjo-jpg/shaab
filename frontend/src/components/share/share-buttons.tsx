'use client';

import { Check, Link2, Share2 } from 'lucide-react';
import { useState } from 'react';

import { FacebookIcon, LinkedinIcon, TelegramIcon, WhatsappIcon, XIcon } from '@/components/icons/social';

// مكوّن مشاركة **واحد قابل لإعادة الاستخدام** لأيّ محتوى (مقال/فيديو/ريلز…) — لا يتكرّر منطق المشاركة بين
// الصفحات. يجمع: مشاركة النظام (Web Share) + نسخ الرابط + روابط نيّة الشبكات (واتساب/فيسبوك/X/تيليجرام/لينكدإن).
// يستهلك الرابط النهائيّ (canonical) المُمرَّر — لا تلفيق، ويعمل قبل الترطيب (لا يعتمد window فقط).
const enc = encodeURIComponent;

type Network = { key: string; label: string; href: string; Icon: typeof FacebookIcon; brand: string };

function networks(url: string, title: string): Network[] {
  const u = enc(url);
  const t = enc(title);
  const tu = enc(`${title} ${url}`);
  return [
    { key: 'whatsapp', label: 'واتساب', href: `https://wa.me/?text=${tu}`, Icon: WhatsappIcon, brand: '#25D366' },
    { key: 'facebook', label: 'فيسبوك', href: `https://www.facebook.com/sharer/sharer.php?u=${u}`, Icon: FacebookIcon, brand: '#1877F2' },
    { key: 'x', label: 'إكس', href: `https://twitter.com/intent/tweet?url=${u}&text=${t}`, Icon: XIcon, brand: '#000000' },
    { key: 'telegram', label: 'تيليجرام', href: `https://t.me/share/url?url=${u}&text=${t}`, Icon: TelegramIcon, brand: '#229ED9' },
    { key: 'linkedin', label: 'لينكدإن', href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}`, Icon: LinkedinIcon, brand: '#0A66C2' },
  ];
}

export function ShareButtons({ url, title, className = '' }: { url: string; title: string; className?: string }) {
  const [copied, setCopied] = useState(false);

  // مشاركة النظام (الجوّال): يُفضَّل الرابط المُمرَّر، ويرجع لرابط الصفحة الحاليّ احتياطاً.
  const nativeShare = async () => {
    const shareUrl = url || (typeof window !== 'undefined' ? window.location.href : '');
    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
      try {
        await navigator.share({ title, url: shareUrl });
      } catch {
        /* ألغى المستخدم */
      }
    }
  };

  const copyLink = async () => {
    const shareUrl = url || (typeof window !== 'undefined' ? window.location.href : '');
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      /* لا حافظة */
    }
  };

  const open = (href: string) =>
    window.open(href, '_blank', 'noopener,noreferrer,width=620,height=560');

  return (
    <div className={`flex flex-wrap items-center gap-2 ${className}`} aria-label="مشاركة">
      <span className="me-1 text-sm font-bold text-muted">مشاركة:</span>

      {networks(url, title).map(({ key, label, href, Icon, brand }) => (
        <button
          key={key}
          type="button"
          onClick={() => open(href)}
          aria-label={`مشاركة عبر ${label}`}
          title={label}
          className="inline-flex size-9 items-center justify-center bg-surface-2 transition-colors hover:bg-surface-3"
          style={{ color: brand }}
        >
          <Icon size={18} />
        </button>
      ))}

      {/* نسخ الرابط */}
      <button
        type="button"
        onClick={copyLink}
        aria-label="نسخ الرابط"
        title="نسخ الرابط"
        className="inline-flex size-9 items-center justify-center bg-surface-2 text-fg transition-colors hover:bg-surface-3"
      >
        {copied ? <Check className="size-[18px] text-success" aria-hidden /> : <Link2 className="size-[18px]" aria-hidden />}
      </button>

      {/* مشاركة النظام (الجوّال) */}
      <button
        type="button"
        onClick={nativeShare}
        aria-label="مشاركة عبر النظام"
        title="مشاركة"
        className="inline-flex size-9 items-center justify-center bg-surface-2 text-fg transition-colors hover:bg-surface-3 sm:hidden"
      >
        <Share2 className="size-[18px]" aria-hidden />
      </button>
    </div>
  );
}

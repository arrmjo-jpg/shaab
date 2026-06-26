import { Mail, MapPin, Phone } from 'lucide-react';

import { socialEntries } from '@/components/layout/social-map';
import { getSiteSettings } from '@/lib/site-settings';

// إحداثيّات صالحة فقط (أرقام منتهية ضمن المدى) — غيرها ⇒ null فلا خريطة (صفر تلفيق).
function parseCoords(lat: string | null | undefined, lng: string | null | undefined) {
  const la = Number.parseFloat(lat ?? '');
  const ln = Number.parseFloat(lng ?? '');
  if (!Number.isFinite(la) || !Number.isFinite(ln)) return null;
  if (la < -90 || la > 90 || ln < -180 || ln > 180) return null;
  return { la, ln };
}

// لوحة «بيانات التواصل» المشتركة (اتصل بنا / أعلن معنا) — كلّ البيانات من إعدادات الموقع
// (الاسم/الوصف/الهاتف/البريد/السوشال/إحداثيّات الخريطة عبر getSiteSettings المُكاش). كلّ صفّ
// يختفي حين لا بيانات له (صفر تلفيق). لاصقة على سطح المكتب بجانب النموذج.
export async function ContactInfoPanel() {
  const settings = await getSiteSettings();
  const siteName = settings?.site_name?.trim() || '';
  const description = settings?.description?.trim() || '';
  const phone = settings?.phone?.trim() || '';
  const email = settings?.email?.trim() || '';
  const social = socialEntries(settings?.social);
  const coords = parseCoords(settings?.latitude, settings?.longitude);

  return (
    <aside className="h-fit overflow-hidden border border-border bg-surface shadow-lg lg:sticky lg:top-24">
      {/* الترويسة — خطّ هويّة سفليّ أحمر */}
      <div className="border-b-2 border-primary bg-surface-2/60 px-6 py-5">
        <h2 className="font-heading text-lg font-extrabold text-fg">بيانات التواصل</h2>
        {siteName ? <p className="mt-1 text-sm font-bold text-primary">{siteName}</p> : null}
        {description ? <p className="mt-2 text-sm leading-7 text-muted">{description}</p> : null}
      </div>

      {/* قنوات مباشرة — روابط فعليّة tel/mailto */}
      {(phone || email) && (
        <ul className="space-y-1 px-3 py-3">
          {phone ? (
            <li>
              <a
                href={`tel:${phone}`}
                className="group flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-surface-2"
              >
                <span
                  className="inline-flex size-10 shrink-0 items-center justify-center bg-primary/10 text-primary"
                  style={{ borderRadius: '10px' }}
                >
                  <Phone className="size-5" aria-hidden />
                </span>
                <span className="min-w-0">
                  <span className="block text-caption text-muted">الهاتف</span>
                  <span dir="ltr" className="block text-sm font-bold text-fg transition-colors group-hover:text-primary">
                    {phone}
                  </span>
                </span>
              </a>
            </li>
          ) : null}
          {email ? (
            <li>
              <a
                href={`mailto:${email}`}
                className="group flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-surface-2"
              >
                <span
                  className="inline-flex size-10 shrink-0 items-center justify-center bg-primary/10 text-primary"
                  style={{ borderRadius: '10px' }}
                >
                  <Mail className="size-5" aria-hidden />
                </span>
                <span className="min-w-0">
                  <span className="block text-caption text-muted">البريد الإلكتروني</span>
                  <span dir="ltr" className="block break-all text-sm font-bold text-fg transition-colors group-hover:text-primary">
                    {email}
                  </span>
                </span>
              </a>
            </li>
          ) : null}
        </ul>
      )}

      {/* السوشال */}
      {social.length > 0 && (
        <div className="border-t border-border px-6 py-4">
          <p className="text-caption font-semibold text-muted">تابعنا على</p>
          <div className="mt-2.5 flex flex-wrap items-center gap-2">
            {social.map(({ key, url, Icon, label }) => (
              <a
                key={key}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                className="inline-flex size-10 items-center justify-center rounded-full bg-surface-2 text-muted transition-colors hover:bg-primary hover:text-white"
              >
                <Icon className="size-5" aria-hidden />
              </a>
            ))}
          </div>
        </div>
      )}

      {/* الخريطة — إحداثيّات الإعدادات (Google embed بلا مفتاح/حزمة)؛ غير مضبوطة ⇒ لا شيء */}
      {coords && (
        <div className="border-t border-border">
          <iframe
            title="موقعنا على الخريطة"
            src={`https://maps.google.com/maps?q=${coords.la},${coords.ln}&z=15&hl=ar&output=embed`}
            className="block h-56 w-full border-0"
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
            allowFullScreen
          />
          <a
            href={`https://www.google.com/maps?q=${coords.la},${coords.ln}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-2 border-t border-border px-6 py-3 text-sm font-bold text-primary transition-colors hover:bg-surface-2"
          >
            <MapPin className="size-4 shrink-0" aria-hidden />
            عرض الاتجاهات في خرائط جوجل
          </a>
        </div>
      )}
    </aside>
  );
}

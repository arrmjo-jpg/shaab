import Script from 'next/script';

import { getSiteSettings } from '@/lib/site-settings';

// Analytics integration layer — ZERO hardcoded IDs. Each tag renders ONLY when its ID exists in
// Site Settings (CMS). Today no IDs are set → nothing is injected (data-honest, infrastructure-ready).
export async function Analytics() {
  const a = (await getSiteSettings())?.analytics;
  if (!a) return null;

  return (
    <>
      {a.gtm_id ? (
        <Script id="gtm" strategy="afterInteractive">
          {`(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${a.gtm_id}');`}
        </Script>
      ) : null}

      {a.google_analytics_id ? (
        <>
          <Script src={`https://www.googletagmanager.com/gtag/js?id=${a.google_analytics_id}`} strategy="afterInteractive" />
          <Script id="ga4" strategy="afterInteractive">
            {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${a.google_analytics_id}');`}
          </Script>
        </>
      ) : null}

      {a.meta_pixel_id ? (
        <Script id="meta-pixel" strategy="afterInteractive">
          {`!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','${a.meta_pixel_id}');fbq('track','PageView');`}
        </Script>
      ) : null}

      {a.snapchat_pixel_id ? (
        <Script id="snap-pixel" strategy="afterInteractive">
          {`(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';var r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u)})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','${a.snapchat_pixel_id}');snaptr('track','PAGE_VIEW');`}
        </Script>
      ) : null}

      {a.tiktok_pixel_id ? (
        <Script id="tiktok-pixel" strategy="afterInteractive">
          {`!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var n='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=n;ttq._t=ttq._t||{};ttq._t[e]=+new Date;var o=d.createElement('script');o.type='text/javascript';o.async=!0;o.src=n+'?sdkid='+e;var a=d.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};ttq.load('${a.tiktok_pixel_id}');ttq.page()}(window,document,'ttq');`}
        </Script>
      ) : null}
    </>
  );
}

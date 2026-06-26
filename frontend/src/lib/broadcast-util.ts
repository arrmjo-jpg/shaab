// أدوات بثّ آمنة للعميل (بلا `server-only`) — تُستعمَل في مكوّنات client (المشغّل) وفي طبقة
// البيانات الخادميّة على السواء. دالّة نقيّة بلا أي اعتماد خادميّ.
export function youtubeIdFrom(url: string | null | undefined): string | null {
  if (!url) return null;
  const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|live\/)|youtu\.be\/)([\w-]{11})/);
  return m ? m[1]! : null;
}

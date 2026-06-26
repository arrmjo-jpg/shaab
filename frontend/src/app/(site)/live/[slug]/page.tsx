import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { BroadcastWatch } from '@/components/broadcast/broadcast-watch';
import { getBroadcast } from '@/lib/broadcast';
import { buildMetadata } from '@/lib/seo';

// صفحة بثّ مباشر (حدث) — /live/{slug}. تعيد استخدام GET /api/v1/live/{slug} (مع playback).
// ISR قصير (البثّ متغيّر). غير موجود/غير عامّ ⇒ 404.
export const revalidate = 30;

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const b = await getBroadcast('live', slug);
  if (!b) return buildMetadata({ title: 'البث المباشر' });
  return buildMetadata({
    title: b.title,
    description: b.excerpt ?? b.description ?? undefined,
    path: b.href,
    image: b.shareImage ?? undefined,
    type: 'article',
  });
}

export default async function LiveBroadcastPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const b = await getBroadcast('live', slug);
  if (!b) notFound();
  return <BroadcastWatch broadcast={b} />;
}

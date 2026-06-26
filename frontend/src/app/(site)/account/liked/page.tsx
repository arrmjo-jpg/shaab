import { ActivityView } from '@/components/account/activity-view';

// «أعجبني» = View النشاط الموحّد بـ activity="liked" (نفس مكوّن المحفوظات تماماً — الفرق الوحيد القيمة).
export default async function LikedPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; page?: string }>;
}) {
  return <ActivityView activity="liked" searchParams={await searchParams} />;
}

import { ActivityView } from '@/components/account/activity-view';

// «المحفوظات» = View النشاط الموحّد بـ activity="saved" (لا منطق خاصّ — الفرق الوحيد القيمة).
export default async function SavedPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; page?: string }>;
}) {
  return <ActivityView activity="saved" searchParams={await searchParams} />;
}

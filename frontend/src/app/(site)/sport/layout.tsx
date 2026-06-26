import type { ReactNode } from 'react';
import { FollowProvider } from '@/lib/follow-context';

// يلفّ كلّ صفحات /sport بمزوّد المتابعة الواحد ⇒ نجوم «تابع» (رؤوس البطولات/صفوف المباريات/صفحات الكيانات)
// تقرأ حالتها من جلبٍ واحد مُجمَّع بدل جلبٍ لكلّ نجمة.
export default function SportLayout({ children }: { children: ReactNode }) {
  return <FollowProvider>{children}</FollowProvider>;
}

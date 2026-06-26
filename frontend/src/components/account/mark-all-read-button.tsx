'use client';

import { markAllNotificationsReadAction } from '@/lib/account-actions';
import { Button } from '@/components/ui/button';

export function MarkAllReadButton() {
  return (
    <form action={markAllNotificationsReadAction}>
      <Button type="submit" variant="outline" size="sm">
        تعليم الكل كمقروء
      </Button>
    </form>
  );
}

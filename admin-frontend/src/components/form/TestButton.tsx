import { PlugZap } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface TestButtonProps {
  label: string;
  loadingLabel: string;
  loading: boolean;
  disabled?: boolean;
  onClick: () => void;
}

/** زر اختبار اتصال موحّد (Email/CDN/Integrations). */
export function TestButton({
  label,
  loadingLabel,
  loading,
  disabled,
  onClick,
}: TestButtonProps) {
  return (
    <Button type="button" variant="outline" onClick={onClick} disabled={loading || disabled}>
      <PlugZap className="h-4 w-4" />
      {loading ? loadingLabel : label}
    </Button>
  );
}

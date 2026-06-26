import { Loader2, Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
  label: string;
  onClick: () => void;
  loading?: boolean;
  disabled?: boolean;
  className?: string;
  title?: string;
}

/** زر مساعد موحّد بأيقونة ✨ + حالة تحميل — خفيف وغير تطفّلي. */
export function AiActionButton({ label, onClick, loading, disabled, className, title }: Props) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled || loading}
      title={title}
      className={cn(
        'inline-flex items-center gap-1.5 border border-primary/30 bg-primary/5 px-2.5 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
    >
      {loading ? (
        <Loader2 className="h-3.5 w-3.5 animate-spin" />
      ) : (
        <Sparkles className="h-3.5 w-3.5" />
      )}
      {label}
    </button>
  );
}

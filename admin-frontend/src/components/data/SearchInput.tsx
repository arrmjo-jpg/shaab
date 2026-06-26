import * as React from 'react';
import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';

interface SearchInputProps {
  value: string;
  onDebouncedChange: (value: string) => void;
  placeholder: string;
  delay?: number;
}

/** حقل بحث مع debounce داخلي. */
export function SearchInput({
  value,
  onDebouncedChange,
  placeholder,
  delay = 350,
}: SearchInputProps) {
  const [local, setLocal] = React.useState(value);

  React.useEffect(() => {
    setLocal(value);
  }, [value]);

  React.useEffect(() => {
    const id = window.setTimeout(() => {
      if (local !== value) onDebouncedChange(local);
    }, delay);
    return () => window.clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [local]);

  return (
    <div className="relative w-full sm:max-w-xs">
      <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
      <Input
        value={local}
        onChange={(e) => setLocal(e.target.value)}
        placeholder={placeholder}
        className="ps-9"
      />
    </div>
  );
}

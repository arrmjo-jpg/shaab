import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { SearchInput } from '@/components/data/SearchInput';
import { Button } from '@/components/ui/button';
import { LoadingState, ErrorState } from '@/components/feedback';
import { usePermissionsGrouped, usePermissionGroups } from '../hooks';

interface Props {
  value: string[];
  onChange: (next: string[]) => void;
  disabled?: boolean;
}

/** إسناد الصلاحيات مدفوع بمجموعات الـ backend — مجموعات قابلة للطي + بحث + تحديد شامل + ملخّص لاصق. */
export function PermissionAssignment({ value, onChange, disabled }: Props) {
  const { t } = useTranslation('users');
  const permsQ = usePermissionsGrouped();
  const groupsQ = usePermissionGroups();
  const [search, setSearch] = useState('');
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  const labelBySlug = useMemo(() => {
    const map: Record<string, string> = {};
    (groupsQ.data ?? []).forEach((g) => {
      map[g.slug] = g.display_name;
    });
    return map;
  }, [groupsQ.data]);

  const blocks = useMemo(() => {
    const data = permsQ.data ?? [];
    const term = search.trim().toLowerCase();
    if (!term) return data;
    return data
      .map((b) => ({
        ...b,
        items: b.items.filter(
          (p) =>
            p.name.toLowerCase().includes(term) ||
            p.display_name.toLowerCase().includes(term),
        ),
      }))
      .filter((b) => b.items.length > 0);
  }, [permsQ.data, search]);

  if (permsQ.isLoading) return <LoadingState />;
  if (permsQ.isError) return <ErrorState onRetry={() => void permsQ.refetch()} />;

  const set = new Set(value);
  const allNames = (permsQ.data ?? []).flatMap((b) => b.items.map((i) => i.name));

  const toggle = (name: string) => {
    if (disabled) return;
    const next = new Set(set);
    next.has(name) ? next.delete(name) : next.add(name);
    onChange([...next]);
  };

  const toggleGroup = (names: string[], allOn: boolean) => {
    if (disabled) return;
    const next = new Set(set);
    names.forEach((n) => (allOn ? next.delete(n) : next.add(n)));
    onChange([...next]);
  };

  const globalAllOn = allNames.length > 0 && allNames.every((n) => set.has(n));

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <SearchInput
          value={search}
          onDebouncedChange={setSearch}
          placeholder={t('roles.perm.search')}
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled}
          onClick={() => onChange(globalAllOn ? [] : allNames)}
        >
          {globalAllOn ? t('roles.perm.clearAll') : t('roles.perm.selectAll')}
        </Button>
      </div>

      <div className="space-y-2">
        {blocks.map((b) => {
          const names = b.items.map((i) => i.name);
          const selectedInGroup = names.filter((n) => set.has(n)).length;
          const allOn = selectedInGroup === names.length && names.length > 0;
          const isCollapsed = collapsed[b.group];
          return (
            <div key={b.group} className="overflow-hidden rounded-2xl border border-border">
              <div className="flex items-center justify-between gap-3 bg-muted/40 px-4 py-2.5">
                <button
                  type="button"
                  onClick={() => setCollapsed((c) => ({ ...c, [b.group]: !c[b.group] }))}
                  className="flex flex-1 items-center gap-2 text-start text-sm font-semibold"
                >
                  <ChevronDown
                    className={cn('h-4 w-4 transition-transform', isCollapsed && '-rotate-90')}
                  />
                  <span>{labelBySlug[b.group] ?? b.group}</span>
                  <span className="text-xs font-normal text-muted-foreground">
                    ({selectedInGroup}/{names.length})
                  </span>
                </button>
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => toggleGroup(names, allOn)}
                  className="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                >
                  {t('roles.perm.groupSelectAll')}
                </button>
              </div>
              {!isCollapsed ? (
                <div className="grid gap-1.5 p-3 sm:grid-cols-2">
                  {b.items.map((p) => (
                    <label
                      key={p.name}
                      className={cn(
                        'flex cursor-pointer items-start gap-2.5 rounded-xl px-2.5 py-2 text-sm transition-colors hover:bg-accent/50',
                        disabled && 'cursor-not-allowed opacity-60',
                      )}
                    >
                      <input
                        type="checkbox"
                        className="mt-0.5 h-4 w-4 accent-primary"
                        checked={set.has(p.name)}
                        onChange={() => toggle(p.name)}
                        disabled={disabled}
                      />
                      <span className="min-w-0">
                        <span className="block font-medium">{p.display_name}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {p.name}
                        </span>
                      </span>
                    </label>
                  ))}
                </div>
              ) : null}
            </div>
          );
        })}
      </div>

      <div className="sticky bottom-0 -mx-6 border-t border-border bg-background/95 px-6 py-2.5 text-sm font-medium backdrop-blur">
        {t('roles.perm.selected', { count: value.length })}
      </div>
    </div>
  );
}

import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { SearchInput } from '@/components/data/SearchInput';
import { LoadingState, ErrorState, EmptyState } from '@/components/feedback';
import { usePermissionsGrouped, usePermissionGroups } from '../hooks';

export default function PermissionsPage() {
  const { t } = useTranslation('users');
  const permsQ = usePermissionsGrouped();
  const groupsQ = usePermissionGroups();
  const [search, setSearch] = useState('');

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

  const total = blocks.reduce((s, b) => s + b.items.length, 0);

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('permissions.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('permissions.subtitle')}</p>
        </div>
        <SearchInput
          value={search}
          onDebouncedChange={setSearch}
          placeholder={t('permissions.searchPlaceholder')}
        />
      </header>

      {total === 0 ? (
        <EmptyState />
      ) : (
        <div className="space-y-4">
          {blocks.map((b) => (
            <div key={b.group} className="overflow-hidden rounded-2xl border border-border bg-background">
              <div className="flex items-center gap-2.5 border-b border-border bg-muted/40 px-4 py-3">
                <ShieldCheck className="h-4.5 w-4.5 text-primary" />
                <h2 className="font-semibold">{labelBySlug[b.group] ?? b.group}</h2>
                <span className="text-xs text-muted-foreground">
                  {t('permissions.count', { count: b.items.length })}
                </span>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[560px] border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                      <th className="px-4 py-2.5 text-start">{t('permissions.col.permission')}</th>
                      <th className="px-4 py-2.5 text-start">{t('permissions.col.name')}</th>
                      <th className="px-4 py-2.5 text-center">{t('permissions.col.guard')}</th>
                      <th className="px-4 py-2.5 text-center">{t('permissions.col.system')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {b.items.map((p) => (
                      <tr key={p.name} className="border-b border-border last:border-0">
                        <td className="px-4 py-3">
                          <p className="font-medium">{p.display_name}</p>
                          {p.description ? (
                            <p className="text-xs text-muted-foreground">{p.description}</p>
                          ) : null}
                        </td>
                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                          {p.name}
                        </td>
                        <td className="px-4 py-3 text-center">
                          <Badge variant="muted">{p.guard}</Badge>
                        </td>
                        <td className="px-4 py-3 text-center">
                          {p.is_system ? (
                            <Badge variant="muted">{t('common.systemBadge')}</Badge>
                          ) : (
                            '—'
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

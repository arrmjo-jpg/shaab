import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PanelLeft, PanelLeftClose, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { BRAND } from '@/lib/constants';
import { navSections, type NavItem } from '@/config/navigation';
import { useAuth } from '@/hooks/useAuth';
import { useNewspaperSettings } from '@/features/epaper/hooks';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

interface SidebarProps {
  collapsed?: boolean;
  onToggle?: () => void;
  onNavigate?: () => void;
}

export function Sidebar({ collapsed = false, onToggle, onNavigate }: SidebarProps) {
  const { t } = useTranslation();
  const { hasPermission } = useAuth();
  const { pathname } = useLocation();

  // مفتاح تفعيل الجريدة الرقمية — يُقرأ فقط لمن يملك epapers.view (وإلا القسم مخفيّ بالصلاحية).
  const newspaperQ = useNewspaperSettings(hasPermission('epapers.view'));
  const newspaperEnabled = newspaperQ.data?.enabled ?? false;

  // تجاوز يدوي مؤقت فقط؛ يُصفّر عند تغيّر المسار لتعود المجموعة
  // للحالة الافتراضية: مفتوحة فقط إذا كانت إحدى صفحاتها نشطة.
  const [overrides, setOverrides] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setOverrides({});
  }, [pathname]);

  // مطابقة بحدود المقاطع — لا يطابق /content/reels مع /content/reels-x.
  const matchPath = (to: string): boolean =>
    to === '/' ? pathname === '/' : pathname === to || pathname.startsWith(`${to}/`);

  // أطول مسار مطابق يفوز — كي لا يبقى العنصر الأب (مثل /content/reels) نشطاً على
  // شقيق أكثر تحديداً (مثل /content/reels/analytics): يُبرَز عنصر واحد فقط.
  const activeTo =
    navSections
      .flatMap((s) => s.items)
      .map((i) => i.to)
      .filter(matchPath)
      .sort((a, b) => b.length - a.length)[0] ?? null;

  const groupActive = (items: NavItem[]) =>
    items.some((i) => i.to !== '/' && matchPath(i.to));

  // افتراضياً تتبع المسار النشط؛ والتجاوز اليدوي يُلغى عند التنقّل
  const isOpen = (key: string) =>
    overrides[key] ??
    groupActive(navSections.find((s) => s.key === key)?.items ?? []);

  const toggleGroup = (key: string) =>
    setOverrides((prev) => ({ ...prev, [key]: !isOpen(key) }));

  const renderItem = (item: NavItem, indent: boolean) => {
    const Icon = item.icon;
    const active = item.to === activeTo;
    const link = (
      <Link
        to={item.to}
        onClick={onNavigate}
        aria-current={active ? 'page' : undefined}
        className={cn(
          'flex items-center gap-3 px-3 py-2.5 text-sm font-medium transition-colors',
          collapsed && 'justify-center px-0',
          indent && !collapsed && 'ps-9',
          active
            ? 'bg-primary/10 text-primary'
            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
        )}
      >
        <Icon className="h-5 w-5 shrink-0" />
        {!collapsed && t(`nav.${item.key}`)}
      </Link>
    );
    if (collapsed) {
      return (
        <Tooltip key={item.key}>
          <TooltipTrigger asChild>{link}</TooltipTrigger>
          <TooltipContent side="left">{t(`nav.${item.key}`)}</TooltipContent>
        </Tooltip>
      );
    }
    return <div key={item.key}>{link}</div>;
  };

  return (
    <aside className="flex h-full flex-col bg-background">
      <div
        className={cn(
          'flex h-16 items-center border-b border-border px-3',
          collapsed ? 'justify-center' : 'justify-between',
        )}
      >
        {!collapsed && (
          <div className="flex items-center gap-2.5 text-lg font-bold">
            <span className="flex h-9 w-9 items-center justify-center bg-primary text-primary-foreground">
              {BRAND.name.charAt(0)}
            </span>
            {BRAND.name}
          </div>
        )}
        {onToggle ? (
          <button
            type="button"
            onClick={onToggle}
            aria-label={t('shell.toggleSidebar')}
            className="hidden h-9 w-9 items-center justify-center text-muted-foreground transition-colors hover:bg-accent hover:text-foreground lg:flex"
          >
            {collapsed ? <PanelLeft className="h-5 w-5" /> : <PanelLeftClose className="h-5 w-5" />}
          </button>
        ) : null}
      </div>

      <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
        {navSections.map((section, si) => {
          // قسم يتطلّب تفعيل وحدة الجريدة — يُخفى ما لم تُفعَّل (إضافةً إلى تقييد الصلاحية).
          if (section.requiresNewspaper && !newspaperEnabled) return null;

          const items = section.items.filter(
            (n) => !n.permission || hasPermission(n.permission),
          );
          if (items.length === 0) return null;

          // مجموعة عامة بلا عنوان (لوحة التحكم)
          if (!section.titleKey || !section.key) {
            return (
              <div key={`sec-${si}`} className="flex flex-col gap-1">
                {items.map((it) => renderItem(it, false))}
              </div>
            );
          }

          // وضع مطوي: عرض العناصر مسطّحة بأيقونات + tooltips (لا مجال للعناوين)
          if (collapsed) {
            return (
              <div key={section.key} className="mt-3 flex flex-col gap-1">
                {items.map((it) => renderItem(it, false))}
              </div>
            );
          }

          // dropdown قابل للطي
          const GroupIcon = section.icon;
          const open = isOpen(section.key);
          return (
            <div key={section.key} className="mt-3">
              <button
                type="button"
                onClick={() => toggleGroup(section.key as string)}
                className="flex w-full items-center gap-3 px-3 py-2.5 text-sm font-semibold text-foreground/80 transition-colors hover:bg-accent"
                aria-expanded={open}
              >
                {GroupIcon ? <GroupIcon className="h-5 w-5 shrink-0" /> : null}
                <span className="flex-1 text-start">{t(`nav.${section.titleKey}`)}</span>
                <ChevronDown
                  className={cn('h-4 w-4 transition-transform', open && 'rotate-180')}
                />
              </button>
              {open ? (
                <div className="mt-1 flex flex-col gap-1">
                  {items.map((it) => renderItem(it, true))}
                </div>
              ) : null}
            </div>
          );
        })}
      </nav>
    </aside>
  );
}

import { useTranslation } from 'react-i18next';
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  ChevronRight,
  Eye,
  FileText,
  MoreHorizontal,
  Pencil,
  Plus,
  Trash2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { CategoryBulkPayload, CategoryData } from '@/types/content.types';

const VIS_KEYS = ['show_in_header', 'show_in_body', 'show_in_footer'] as const;

// أصناف عرض الأعمدة — مشتركة بين الترويسة والصفوف لضمان المحاذاة.
// المخطّط متجاوب: على الموبايل يبقى الاسم + مفتاح الحالة + القائمة فقط؛
// تظهر بقية الأعمدة تدريجياً مع اتّساع الشاشة، وما يُخفى يبقى متاحاً في القائمة.
const COL = {
  articles: 'hidden w-16 shrink-0 items-center justify-center sm:flex',
  vis: 'hidden w-9 shrink-0 items-center justify-center md:flex',
  scope: 'hidden w-16 shrink-0 items-center justify-center lg:flex',
  status: 'flex w-12 shrink-0 items-center justify-center',
  locale: 'hidden w-10 shrink-0 items-center justify-center uppercase md:flex',
  order: 'hidden w-14 shrink-0 items-center justify-center sm:flex',
  actions: 'flex w-9 shrink-0 items-center justify-center',
};

/** ترويسة جدول الأقسام — تطابق أعمدة CategoryTreeNode. */
export function CategoryTreeHeader({ canEdit }: { canEdit: boolean }) {
  const { t } = useTranslation('content');
  return (
    <div className="flex items-center gap-2 border-b border-border bg-muted/40 px-3 py-2 text-[11px] font-bold uppercase text-muted-foreground">
      {canEdit ? <span className="w-4 shrink-0" /> : null}
      <span className="min-w-0 flex-1">{t('categories.col.name')}</span>
      <span className={COL.articles}>{t('categories.col.articles')}</span>
      {VIS_KEYS.map((k) => (
        <span key={k} className={COL.vis} title={t(`categories.form.${k}`)}>
          {t(`categories.visShort.${k}`)}
        </span>
      ))}
      <span className={COL.scope}>{t('categories.col.scope')}</span>
      <span className={COL.status}>{t('categories.col.status')}</span>
      <span className={COL.locale}>{t('categories.col.locale')}</span>
      {canEdit ? <span className={COL.order}>{t('categories.col.order')}</span> : null}
      <span className={COL.actions} />
    </div>
  );
}

interface Props {
  node: CategoryData;
  depth: number;
  siblingIndex: number;
  siblingCount: number;
  canEdit: boolean;
  canCreate: boolean;
  canDelete: boolean;
  collapsed: Set<number>;
  forceOpen: boolean;
  selected: Set<number>;
  onToggleOpen: (id: number) => void;
  onToggleSelect: (id: number) => void;
  onEdit: (n: CategoryData) => void;
  onCreateChild: (parent: CategoryData) => void;
  onDelete: (n: CategoryData) => void;
  onMove: (n: CategoryData, direction: 'up' | 'down') => void;
  onQuickToggle: (n: CategoryData, patch: CategoryBulkPayload) => void;
  onOpenArticles: (n: CategoryData) => void;
}

export function CategoryTreeNode({
  node,
  depth,
  siblingIndex,
  siblingCount,
  canEdit,
  canCreate,
  canDelete,
  collapsed,
  forceOpen,
  selected,
  onToggleOpen,
  onToggleSelect,
  onEdit,
  onCreateChild,
  onDelete,
  onMove,
  onQuickToggle,
  onOpenArticles,
}: Props) {
  const { t } = useTranslation('content');
  const children = Array.isArray(node.children) ? node.children : [];
  const hasChildren = children.length > 0;
  const open = forceOpen || !collapsed.has(node.id);
  const isSelected = selected.has(node.id);
  const isActive = node.status === 'active';

  return (
    <div className="flex flex-col">
      <div
        className={cn(
          'flex items-center gap-2 border-b border-border px-3 py-2.5 hover:bg-accent/40',
          isSelected && 'bg-primary/5',
        )}
      >
        {canEdit ? (
          <input
            type="checkbox"
            checked={isSelected}
            onChange={() => onToggleSelect(node.id)}
            className="h-4 w-4 shrink-0"
            aria-label={t('categories.bulk.select')}
          />
        ) : null}

        {/* عمود الاسم — هو الوحيد الذي يُزاح حسب العمق (بقية الأعمدة محاذاة) */}
        <div
          className="flex min-w-0 flex-1 items-center gap-2"
          style={{ paddingInlineStart: `${depth * 18}px` }}
        >
          {hasChildren ? (
            <button
              type="button"
              onClick={() => onToggleOpen(node.id)}
              className="flex h-6 w-6 shrink-0 items-center justify-center text-muted-foreground hover:text-foreground"
              aria-label={open ? t('categories.collapse') : t('categories.expand')}
            >
              {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
            </button>
          ) : (
            <span className="h-6 w-6 shrink-0" />
          )}
          <div className="min-w-0">
            <p className="truncate font-medium">{node.name}</p>
            <p className="truncate text-xs text-muted-foreground">/{node.slug}</p>
          </div>
        </div>

        {/* أيقونة تنقل لقائمة المقالات مفلترة على هذا القسم (بلا عدّاد — أُسقط الحمل) */}
        <div className={COL.articles}>
          <button
            type="button"
            onClick={() => onOpenArticles(node)}
            title={t('categories.openArticles')}
            className="inline-flex items-center gap-1 border border-border bg-background px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:border-primary hover:text-primary"
          >
            <FileText className="h-3.5 w-3.5" />
          </button>
        </div>

        {/* تبديل الظهور (هيدر/بودي/فوتر) مباشرةً من الجدول */}
        {VIS_KEYS.map((k) => (
          <div key={k} className={COL.vis}>
            <button
              type="button"
              disabled={!canEdit}
              onClick={() => onQuickToggle(node, { [k]: !node[k] })}
              title={
                node[k]
                  ? t('categories.vis.hideFrom', { zone: t(`categories.form.${k}`) })
                  : t('categories.vis.showIn', { zone: t(`categories.form.${k}`) })
              }
              className={cn(
                'inline-flex h-6 w-6 items-center justify-center border text-[11px] font-bold transition-colors disabled:cursor-default',
                node[k]
                  ? 'border-primary bg-primary text-primary-foreground'
                  : 'border-border text-muted-foreground hover:border-primary/60',
              )}
            >
              {t(`categories.visShort.${k}`)}
            </button>
          </div>
        ))}

        <div className={COL.scope}>
          <Badge variant="muted">{t(`categories.scope.${node.scope}`)}</Badge>
        </div>

        {/* مفتاح الحالة (Switch) — تفعيل/إخفاء القسم في مكانه */}
        <div className={COL.status}>
          <button
            type="button"
            role="switch"
            aria-checked={isActive}
            disabled={!canEdit}
            onClick={() => onQuickToggle(node, { status: isActive ? 'hidden' : 'active' })}
            title={t('categories.toggleStatus')}
            className="disabled:cursor-default"
          >
            <span
              className={cn(
                'relative block h-5 w-9 shrink-0 rounded-full transition-colors',
                isActive ? 'bg-primary' : 'bg-muted',
              )}
            >
              <span
                className={cn(
                  'absolute top-0.5 start-0.5 h-4 w-4 rounded-full bg-white shadow transition-all',
                  isActive ? 'rtl:-translate-x-4 ltr:translate-x-4' : '',
                )}
              />
            </span>
          </button>
        </div>

        <div className={COL.locale}>
          <span className="text-xs text-muted-foreground">{node.locale}</span>
        </div>

        {/* إعادة الترتيب ضمن الإخوة */}
        {canEdit ? (
          <div className={COL.order}>
            <button
              type="button"
              onClick={() => onMove(node, 'up')}
              disabled={siblingIndex === 0}
              className="flex h-7 w-7 items-center justify-center text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
              title={t('categories.moveUp')}
            >
              <ArrowUp className="h-4 w-4" />
            </button>
            <button
              type="button"
              onClick={() => onMove(node, 'down')}
              disabled={siblingIndex === siblingCount - 1}
              className="flex h-7 w-7 items-center justify-center text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
              title={t('categories.moveDown')}
            >
              <ArrowDown className="h-4 w-4" />
            </button>
          </div>
        ) : null}

        <div className={COL.actions}>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {canEdit ? (
                <DropdownMenuItem onClick={() => onEdit(node)}>
                  <Pencil className="h-4 w-4" />
                  {t('categories.action.edit')}
                </DropdownMenuItem>
              ) : null}
              {canCreate && depth < 2 ? (
                <DropdownMenuItem onClick={() => onCreateChild(node)}>
                  <Plus className="h-4 w-4" />
                  {t('categories.action.addChild')}
                </DropdownMenuItem>
              ) : null}
              {/* إعادة الترتيب — متاحة من القائمة أيضاً (مفيدة على الموبايل) */}
              {canEdit ? (
                <div className="sm:hidden">
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    disabled={siblingIndex === 0}
                    onClick={() => onMove(node, 'up')}
                  >
                    <ArrowUp className="h-4 w-4" />
                    {t('categories.moveUp')}
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    disabled={siblingIndex === siblingCount - 1}
                    onClick={() => onMove(node, 'down')}
                  >
                    <ArrowDown className="h-4 w-4" />
                    {t('categories.moveDown')}
                  </DropdownMenuItem>
                </div>
              ) : null}
              {canEdit ? (
                <>
                  <DropdownMenuSeparator />
                  {VIS_KEYS.map((k) => (
                    <DropdownMenuItem key={k} onClick={() => onQuickToggle(node, { [k]: !node[k] })}>
                      <Eye className={cn('h-4 w-4', node[k] ? 'text-primary' : 'opacity-40')} />
                      {node[k]
                        ? t('categories.vis.hideFrom', { zone: t(`categories.form.${k}`) })
                        : t('categories.vis.showIn', { zone: t(`categories.form.${k}`) })}
                    </DropdownMenuItem>
                  ))}
                </>
              ) : null}
              {canDelete && !hasChildren ? (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => onDelete(node)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('categories.action.delete')}
                  </DropdownMenuItem>
                </>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      {open && hasChildren ? (
        <div>
          {children.map((c, i) => (
            <CategoryTreeNode
              key={c.id}
              node={c}
              depth={depth + 1}
              siblingIndex={i}
              siblingCount={children.length}
              canEdit={canEdit}
              canCreate={canCreate}
              canDelete={canDelete}
              collapsed={collapsed}
              forceOpen={forceOpen}
              selected={selected}
              onToggleOpen={onToggleOpen}
              onToggleSelect={onToggleSelect}
              onEdit={onEdit}
              onCreateChild={onCreateChild}
              onDelete={onDelete}
              onMove={onMove}
              onQuickToggle={onQuickToggle}
              onOpenArticles={onOpenArticles}
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}

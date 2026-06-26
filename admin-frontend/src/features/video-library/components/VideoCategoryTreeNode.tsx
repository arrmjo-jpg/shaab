import { useTranslation } from 'react-i18next';
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  ChevronRight,
  ListVideo,
  MoreHorizontal,
  Pencil,
  Plus,
  Trash2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { VideoCategoryData } from '@/types/videoLibrary.types';

// أعمدة متجاوبة (تطابق الترويسة) — الاسم يُزاح حسب العمق، البقية محاذاة.
const COL = {
  videos: 'hidden w-16 shrink-0 items-center justify-center sm:flex',
  status: 'flex w-12 shrink-0 items-center justify-center',
  locale: 'hidden w-10 shrink-0 items-center justify-center uppercase md:flex',
  order: 'hidden w-14 shrink-0 items-center justify-center sm:flex',
  actions: 'flex w-9 shrink-0 items-center justify-center',
};

/** أقصى عمق معماري (مرآة VideoCategory::MAX_DEPTH=3) — إضافة ابن متاحة لعمق < 2. */
export const VL_CATEGORY_MAX_CHILD_DEPTH = 2;

export function VideoCategoryTreeHeader({ canEdit }: { canEdit: boolean }) {
  const { t } = useTranslation('videoLibrary');
  return (
    <div className="flex items-center gap-2 border-b border-border bg-muted/40 px-3 py-2 text-[11px] font-bold uppercase text-muted-foreground">
      <span className="min-w-0 flex-1">{t('categories.col.name')}</span>
      <span className={COL.videos}>{t('categories.col.videos')}</span>
      <span className={COL.status}>{t('categories.col.status')}</span>
      <span className={COL.locale}>{t('categories.col.locale')}</span>
      {canEdit ? <span className={COL.order}>{t('categories.col.order')}</span> : null}
      <span className={COL.actions} />
    </div>
  );
}

interface Props {
  node: VideoCategoryData;
  depth: number;
  siblingIndex: number;
  siblingCount: number;
  canEdit: boolean;
  canCreate: boolean;
  canDelete: boolean;
  collapsed: Set<number>;
  forceOpen: boolean;
  onToggleOpen: (id: number) => void;
  onEdit: (n: VideoCategoryData) => void;
  onCreateChild: (parent: VideoCategoryData) => void;
  onDelete: (n: VideoCategoryData) => void;
  onMove: (n: VideoCategoryData, direction: 'up' | 'down') => void;
  onToggleActive: (n: VideoCategoryData) => void;
}

export function VideoCategoryTreeNode({
  node,
  depth,
  siblingIndex,
  siblingCount,
  canEdit,
  canCreate,
  canDelete,
  collapsed,
  forceOpen,
  onToggleOpen,
  onEdit,
  onCreateChild,
  onDelete,
  onMove,
  onToggleActive,
}: Props) {
  const { t } = useTranslation('videoLibrary');
  const children = Array.isArray(node.children) ? node.children : [];
  const hasChildren = children.length > 0;
  const open = forceOpen || !collapsed.has(node.id);
  const count = node.videos_count ?? 0;

  return (
    <div className="flex flex-col">
      <div className="flex items-center gap-2 border-b border-border px-3 py-2.5 hover:bg-accent/40">
        <div className="flex min-w-0 flex-1 items-center gap-2" style={{ paddingInlineStart: `${depth * 18}px` }}>
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

        <div className={COL.videos}>
          <span className="inline-flex items-center gap-1 border border-border bg-background px-2 py-0.5 text-xs text-muted-foreground">
            <ListVideo className="h-3.5 w-3.5" />
            <span className="tabular-nums">{count}</span>
          </span>
        </div>

        <div className={COL.status}>
          <button
            type="button"
            role="switch"
            aria-checked={node.is_active}
            disabled={!canEdit}
            onClick={() => onToggleActive(node)}
            title={t('categories.toggleStatus')}
            className="disabled:cursor-default"
          >
            <span className={cn('relative block h-5 w-9 shrink-0 rounded-full transition-colors', node.is_active ? 'bg-primary' : 'bg-muted')}>
              <span className={cn('absolute top-0.5 start-0.5 h-4 w-4 rounded-full bg-white shadow transition-all', node.is_active ? 'rtl:-translate-x-4 ltr:translate-x-4' : '')} />
            </span>
          </button>
        </div>

        <div className={COL.locale}>
          <span className="text-xs text-muted-foreground">{node.locale}</span>
        </div>

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
              {canCreate && depth < VL_CATEGORY_MAX_CHILD_DEPTH ? (
                <DropdownMenuItem onClick={() => onCreateChild(node)}>
                  <Plus className="h-4 w-4" />
                  {t('categories.action.addChild')}
                </DropdownMenuItem>
              ) : null}
              {canEdit ? (
                <div className="sm:hidden">
                  <DropdownMenuSeparator />
                  <DropdownMenuItem disabled={siblingIndex === 0} onClick={() => onMove(node, 'up')}>
                    <ArrowUp className="h-4 w-4" />
                    {t('categories.moveUp')}
                  </DropdownMenuItem>
                  <DropdownMenuItem disabled={siblingIndex === siblingCount - 1} onClick={() => onMove(node, 'down')}>
                    <ArrowDown className="h-4 w-4" />
                    {t('categories.moveDown')}
                  </DropdownMenuItem>
                </div>
              ) : null}
              {canDelete && !hasChildren ? (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => onDelete(node)} className="text-destructive focus:text-destructive">
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
            <VideoCategoryTreeNode
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
              onToggleOpen={onToggleOpen}
              onEdit={onEdit}
              onCreateChild={onCreateChild}
              onDelete={onDelete}
              onMove={onMove}
              onToggleActive={onToggleActive}
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}

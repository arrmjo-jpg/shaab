import { Node, mergeAttributes } from '@tiptap/core';
import {
  NodeViewWrapper,
  ReactNodeViewRenderer,
  type ReactNodeViewProps,
} from '@tiptap/react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { pollsService } from '@/services/polls.service';
import type { PollData } from '@/types/polls.types';

export interface PollAttrs {
  uuid: string;
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    poll: {
      insertPoll: (attrs: PollAttrs) => ReturnType;
    };
  }
}

/**
 * Block-level leaf node referencing an existing poll by uuid ONLY.
 * Persisted JSON contract (must match TipTapSanitizer allow-list exactly):
 *   { type: 'poll', attrs: { uuid } }
 *
 * The node carries no poll payload — the public renderer (TipTapRenderer.php)
 * emits <figure data-poll-uuid="…"></figure> and the reading frontend hydrates
 * it separately. The in-editor preview resolves the question on demand for UX.
 */
function PollNodeView(props: ReactNodeViewProps) {
  const { t } = useTranslation('content');
  const attrs = props.node.attrs as PollAttrs;
  const uuid = attrs.uuid;

  // Resolve the poll label for the preview only. Failure is non-fatal — we fall
  // back to showing the uuid. Polls are few, so a generous staleTime is fine.
  const q = useQuery({
    queryKey: ['polls', 'preview', uuid],
    queryFn: () =>
      pollsService.list({
        page: 1,
        per_page: 50,
        search: '',
        is_active: '',
        sort: '-id',
        trashed: 'with',
      }),
    enabled: uuid !== '',
    staleTime: 30_000,
  });

  const poll = (q.data?.data ?? []).find((p: PollData) => p.uuid === uuid) ?? null;
  const label = poll?.question ?? uuid;

  return (
    <NodeViewWrapper
      as="div"
      className="my-3 flex items-start gap-3 border border-border bg-muted/30 p-3"
      data-poll-uuid={uuid}
    >
      <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-primary/10 text-primary">
        <BarChart3 className="h-5 w-5" />
      </div>
      <div className="min-w-0 flex-1 space-y-0.5">
        <p className="text-xs font-semibold uppercase text-muted-foreground">
          {t('editor.poll.label')}
        </p>
        <p className="truncate text-sm">{label}</p>
      </div>
      {props.editor.isEditable ? (
        <button
          type="button"
          onClick={() => props.deleteNode()}
          className="flex h-8 w-8 shrink-0 items-center justify-center text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
          aria-label={t('editor.poll.remove')}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      ) : null}
    </NodeViewWrapper>
  );
}

export const Poll = Node.create({
  name: 'poll',
  group: 'block',
  atom: true,
  selectable: true,
  draggable: true,

  addAttributes() {
    return {
      uuid: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-poll-uuid') ?? '',
        renderHTML: (attributes) => {
          const uuid = (attributes as PollAttrs).uuid;
          if (!uuid) return {};
          return { 'data-poll-uuid': uuid };
        },
      },
    };
  },

  parseHTML() {
    return [{ tag: 'figure[data-poll-uuid]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['figure', mergeAttributes(HTMLAttributes)];
  },

  addCommands() {
    return {
      insertPoll:
        (attrs) =>
        ({ commands }) =>
          commands.insertContent({ type: this.name, attrs }),
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(PollNodeView);
  },
});

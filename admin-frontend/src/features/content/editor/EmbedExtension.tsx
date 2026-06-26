import { Node, mergeAttributes } from '@tiptap/core';
import {
  NodeViewWrapper,
  ReactNodeViewRenderer,
  type ReactNodeViewProps,
} from '@tiptap/react';
import { Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Allow-listed embed providers (mirrors backend EmbedProvider enum).
 */
export const EMBED_PROVIDERS = [
  'youtube',
  'vimeo',
  'twitter',
  'facebook',
  'instagram',
] as const;

export type EmbedProvider = (typeof EMBED_PROVIDERS)[number];

export interface EmbedAttrs {
  provider: EmbedProvider;
  embed_url: string;
  id: string | null;
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    embed: {
      insertEmbed: (attrs: EmbedAttrs) => ReturnType;
    };
  }
}

/**
 * Block-level leaf node carrying allow-listed embed data.
 * Persisted JSON contract:
 *   { type: 'embed', attrs: { provider, embed_url, id } }
 *
 * It deliberately does NOT render an iframe in the editor — only a structural
 * placeholder with provider + URL. The public renderer (TipTapRenderer.php) is
 * the source of truth for production HTML output.
 */
function EmbedNodeView(props: ReactNodeViewProps) {
  const { t } = useTranslation('content');
  const attrs = props.node.attrs as EmbedAttrs;

  return (
    <NodeViewWrapper
      as="div"
      className="my-3 flex items-start gap-3 border border-border bg-muted/30 p-3"
      data-embed-provider={attrs.provider}
    >
      <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-primary/10 text-primary">
        <span className="text-xs font-bold uppercase">{attrs.provider.slice(0, 2)}</span>
      </div>
      <div className="min-w-0 flex-1 space-y-0.5">
        <p className="text-xs font-semibold uppercase text-muted-foreground">
          {t(`editor.embed.providers.${attrs.provider}`)}
        </p>
        <p className="truncate text-sm" dir="ltr">
          {attrs.embed_url}
        </p>
      </div>
      {props.editor.isEditable ? (
        <button
          type="button"
          onClick={() => props.deleteNode()}
          className="flex h-8 w-8 shrink-0 items-center justify-center text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
          aria-label={t('editor.embed.remove')}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      ) : null}
    </NodeViewWrapper>
  );
}

export const Embed = Node.create({
  name: 'embed',
  group: 'block',
  atom: true,
  selectable: true,
  draggable: true,

  addAttributes() {
    return {
      provider: { default: 'youtube' },
      embed_url: { default: '' },
      id: { default: null },
    };
  },

  parseHTML() {
    return [{ tag: 'div[data-embed-provider]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes), 0];
  },

  addCommands() {
    return {
      insertEmbed:
        (attrs) =>
        ({ commands }) =>
          commands.insertContent({ type: this.name, attrs }),
    };
  },

  addNodeView() {
    return ReactNodeViewRenderer(EmbedNodeView);
  },
});

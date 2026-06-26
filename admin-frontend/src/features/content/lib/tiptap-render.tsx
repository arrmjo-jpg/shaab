import React from 'react';

/**
 * Client-side preview renderer for TipTap JSON.
 *
 * Strict mirror of app/Support/Content/TipTapRenderer.php — the backend remains
 * the canonical renderer for public HTML output. This file is used only for the
 * in-app preview modal and renders unknown nodes/marks as no-ops, matching the
 * backend's default => '' behavior.
 */

interface TipTapNode {
  type: string;
  text?: string;
  content?: TipTapNode[];
  marks?: Array<{ type: string; attrs?: Record<string, unknown> }>;
  attrs?: Record<string, unknown>;
}

function renderChildren(node: TipTapNode, keyPrefix: string): React.ReactNode {
  if (!node.content) return null;
  return node.content.map((child, idx) => (
    <React.Fragment key={`${keyPrefix}-${idx}`}>
      {renderNode(child, `${keyPrefix}-${idx}`)}
    </React.Fragment>
  ));
}

function renderText(node: TipTapNode): React.ReactNode {
  let element: React.ReactNode = node.text ?? '';
  for (const mark of node.marks ?? []) {
    switch (mark.type) {
      case 'bold':
        element = <strong>{element}</strong>;
        break;
      case 'italic':
        element = <em>{element}</em>;
        break;
      case 'underline':
        element = <u>{element}</u>;
        break;
      case 'strike':
        element = <s>{element}</s>;
        break;
      case 'code':
        element = <code>{element}</code>;
        break;
      case 'link': {
        const href = (mark.attrs?.href as string | undefined) ?? '#';
        element = (
          <a href={href} target="_blank" rel="noopener noreferrer nofollow">
            {element}
          </a>
        );
        break;
      }
      default:
        // Unknown mark — drop silently (parity with backend)
        break;
    }
  }
  return element;
}

function renderNode(node: TipTapNode, key: string): React.ReactNode {
  switch (node.type) {
    case 'paragraph':
      return <p>{renderChildren(node, key)}</p>;
    case 'heading': {
      const level = Math.max(1, Math.min(6, Number(node.attrs?.level ?? 2)));
      const Tag = `h${level}` as keyof JSX.IntrinsicElements;
      return <Tag>{renderChildren(node, key)}</Tag>;
    }
    case 'blockquote':
      return <blockquote>{renderChildren(node, key)}</blockquote>;
    case 'bulletList':
      return <ul>{renderChildren(node, key)}</ul>;
    case 'orderedList':
      return <ol>{renderChildren(node, key)}</ol>;
    case 'listItem':
      return <li>{renderChildren(node, key)}</li>;
    case 'codeBlock':
      return (
        <pre>
          <code>{renderChildren(node, key)}</code>
        </pre>
      );
    case 'horizontalRule':
      return <hr />;
    case 'hardBreak':
      return <br />;
    case 'text':
      return renderText(node);
    case 'image': {
      const src = (node.attrs?.src as string | undefined) ?? '';
      const alt = (node.attrs?.alt as string | undefined) ?? '';
      return src ? <img src={src} alt={alt} /> : null;
    }
    case 'embed': {
      const provider = (node.attrs?.provider as string | undefined) ?? '';
      const url = (node.attrs?.embed_url as string | undefined) ?? '';
      return (
        <figure
          data-embed-provider={provider}
          data-embed-url={url}
          className="my-3 flex items-center justify-center border border-border bg-muted/30 p-6 text-sm text-muted-foreground"
        >
          {provider.toUpperCase()} · {url}
        </figure>
      );
    }
    case 'poll': {
      const uuid = (node.attrs?.uuid as string | undefined) ?? '';
      return (
        <figure
          data-poll-uuid={uuid}
          className="my-3 flex items-center justify-center border border-border bg-muted/30 p-6 text-sm text-muted-foreground"
        >
          POLL · {uuid}
        </figure>
      );
    }
    case 'table':
      return <table>{renderChildren(node, key)}</table>;
    case 'tableRow':
      return <tr>{renderChildren(node, key)}</tr>;
    case 'tableHeader':
      return <th>{renderChildren(node, key)}</th>;
    case 'tableCell':
      return <td>{renderChildren(node, key)}</td>;
    default:
      return null;
  }
}

export function TipTapPreview({ doc }: { doc: unknown }) {
  if (!doc || typeof doc !== 'object' || (doc as TipTapNode).type !== 'doc') {
    return null;
  }
  return <>{renderChildren(doc as TipTapNode, 'root')}</>;
}

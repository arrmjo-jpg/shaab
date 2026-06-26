import { CheckCircle2, XCircle } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ReelData } from '@/types/content.types';
import type { VideoData } from '@/types/videoLibrary.types';

// Shared bits for the Editorial Review Queue tabs (P1.3 S2-B). Keeps the three
// typed tabs thin and avoids triplicated logic.

/** Badge tone per status (string-keyed → safe across Article/Reel/Video unions). */
export const REVIEW_STATUS_VARIANT: Record<string, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  submitted: 'default',
  in_review: 'default',
  scheduled: 'default',
  published: 'success',
  rejected: 'destructive',
  archived: 'muted',
};

export function fmtDate(locale: string, v: string | null): string {
  return v
    ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(v))
    : '—';
}

/** Reel publishable readiness — mirrors backend Reel::hasPublishableMedia(). */
export function isReelReady(r: ReelData): boolean {
  return r.media_asset_id !== null && r.media?.processing_status === 'ready';
}

/** Video publishable readiness — conservative mirror of backend Video::hasPublishableMedia(). */
export function isVideoReady(v: VideoData): boolean {
  return (
    v.media_asset_id !== null &&
    (v.media?.embed_url !== null || v.media?.processing_status === 'ready')
  );
}

export interface ReviewActionLabels {
  publish: string;
  reject: string;
  waitingMedia: string;
  editToFix: string;
}

/**
 * Approve(publish)/Reject cell. Editorial-only (gated by caller). When media is
 * not ready (reels/videos), Publish is disabled and a "awaiting media" badge +
 * Edit link are shown; Reject stays available always. Articles pass ready=true.
 */
export function ReviewActionsCell({
  isEditorial,
  pending,
  ready,
  editHref,
  labels,
  onPublish,
  onReject,
}: {
  isEditorial: boolean;
  pending: boolean;
  ready: boolean;
  editHref?: string;
  labels: ReviewActionLabels;
  onPublish: () => void;
  onReject: () => void;
}) {
  if (!isEditorial) {
    return <span className="text-xs text-muted-foreground">—</span>;
  }

  return (
    <div className="flex flex-wrap items-center justify-end gap-2">
      {!ready ? (
        <>
          <Badge variant="muted">{labels.waitingMedia}</Badge>
          {editHref ? (
            <Link to={editHref} className="text-xs font-medium text-primary hover:underline">
              {labels.editToFix}
            </Link>
          ) : null}
        </>
      ) : null}
      <Button type="button" size="sm" disabled={pending || !ready} onClick={onPublish}>
        <CheckCircle2 className="h-4 w-4" />
        {labels.publish}
      </Button>
      <Button type="button" variant="outline" size="sm" disabled={pending} onClick={onReject}>
        <XCircle className="h-4 w-4" />
        {labels.reject}
      </Button>
    </div>
  );
}

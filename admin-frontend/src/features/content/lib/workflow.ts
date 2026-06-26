import type { ArticleStatus } from '@/types/content.types';

/**
 * Mirror of the backend ArticleWorkflowGuard::TRANSITIONS matrix.
 * Keep in sync with app/Support/Content/ArticleWorkflowGuard.php.
 */
export const TRANSITIONS: Record<ArticleStatus, ArticleStatus[]> = {
  draft: ['submitted', 'in_review', 'scheduled', 'published', 'archived'],
  submitted: ['in_review', 'scheduled', 'published', 'rejected'],
  in_review: ['scheduled', 'published', 'rejected', 'draft'],
  scheduled: ['published', 'draft', 'archived'],
  published: ['archived'],
  rejected: ['draft', 'submitted'],
  archived: ['draft'],
};

/** Writer-only transitions: submit / resubmit. */
const WRITER_ALLOWED: Partial<Record<ArticleStatus, ArticleStatus[]>> = {
  draft: ['submitted'],
  rejected: ['submitted'],
};

/** Editorial roles — mirrors ArticleAuthorizationGuard::EDITORIAL_ROLES. */
const EDITORIAL_ROLES = new Set(['super_admin', 'editor']);

export function isEditorialUser(roles: ReadonlyArray<string>): boolean {
  return roles.some((r) => EDITORIAL_ROLES.has(r));
}

export interface TransitionPermission {
  isEditorial: boolean;
  isOwner: boolean;
  currentStatus: ArticleStatus;
}

export function allowedTransitions({
  isEditorial,
  isOwner,
  currentStatus,
}: TransitionPermission): ArticleStatus[] {
  const all = TRANSITIONS[currentStatus] ?? [];
  if (isEditorial) return all;
  if (!isOwner) return [];
  return WRITER_ALLOWED[currentStatus] ?? [];
}

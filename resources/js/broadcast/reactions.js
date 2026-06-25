// B7 reactions (like/dislike), token-aware.
//
// With a bearer token (localStorage 'alphacms.token'):
//   GET    /broadcasts/{id}/reaction        -> { reaction: 'like'|'dislike'|null, metrics:{likes,dislikes} }
//   POST   /broadcasts/{id}/reaction {reaction}   (toggle: re-click active -> DELETE)
//   DELETE /broadcasts/{id}/reaction
//   403 (banned/closed) -> graceful "تعذّر التفاعل" message.
// Without a token: render a graceful auth CTA instead of active buttons.
//
// NOTE: there is no public web login in this repo. The CTA is a prompt only; the
// token is provisioned by the platform's existing public auth (out of B10 scope).

import { apiRequest, dataOf, hasAuth } from './api.js';

const AUTH_CTA = 'سجّل الدخول للتفاعل';

export function initReactions(config) {
    const { broadcastId } = config;
    const container = document.querySelector('[data-reactions]');
    if (!broadcastId || !container) return;

    const likeBtn = container.querySelector('[data-react="like"]');
    const dislikeBtn = container.querySelector('[data-react="dislike"]');
    const likeCount = container.querySelector('[data-like-count]');
    const dislikeCount = container.querySelector('[data-dislike-count]');
    const feedback = container.querySelector('[data-reactions-feedback]');

    let current = null; // 'like' | 'dislike' | null
    let busy = false;

    const setFeedback = (msg) => {
        if (!feedback) return;
        if (msg) {
            feedback.textContent = msg;
            feedback.classList.remove('hidden');
        } else {
            feedback.textContent = '';
            feedback.classList.add('hidden');
        }
    };

    const reflect = (data) => {
        if (!data) return;
        current = data.reaction ?? null;
        if (data.metrics) {
            if (likeCount && typeof data.metrics.likes === 'number') {
                likeCount.textContent = new Intl.NumberFormat('ar').format(data.metrics.likes);
            }
            if (dislikeCount && typeof data.metrics.dislikes === 'number') {
                dislikeCount.textContent = new Intl.NumberFormat('ar').format(data.metrics.dislikes);
            }
        }
        for (const [btn, kind] of [[likeBtn, 'like'], [dislikeBtn, 'dislike']]) {
            if (!btn) continue;
            const active = current === kind;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('!bg-red-600', active);
            btn.classList.toggle('!border-red-600', active);
            btn.classList.toggle('!text-white', active);
        }
    };

    // No token -> turn the buttons into a single auth prompt (no active state).
    if (!hasAuth()) {
        for (const btn of [likeBtn, dislikeBtn]) {
            if (!btn) continue;
            btn.setAttribute('aria-disabled', 'true');
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                setFeedback(AUTH_CTA);
            });
        }
        return;
    }

    const toggle = async (kind) => {
        if (busy) return;
        busy = true;
        setFeedback(null);
        const method = current === kind ? 'DELETE' : 'POST';
        const result = await apiRequest(method, `/broadcasts/${broadcastId}/reaction`, {
            withAuth: true,
            body: method === 'POST' ? { reaction: kind } : null,
        });
        busy = false;

        if (result.status === 403) {
            setFeedback('تعذّر التفاعل مع هذا البثّ حالياً.');
            return;
        }
        if (result.status === 401) {
            setFeedback(AUTH_CTA);
            return;
        }
        if (!result.ok) {
            setFeedback('تعذّر إتمام العملية. حاول مجدداً.');
            return;
        }
        reflect(dataOf(result));
    };

    if (likeBtn) likeBtn.addEventListener('click', () => toggle('like'));
    if (dislikeBtn) dislikeBtn.addEventListener('click', () => toggle('dislike'));

    // Hydrate current state + counts.
    apiRequest('GET', `/broadcasts/${broadcastId}/reaction`, { withAuth: true })
        .then((result) => {
            if (result.ok) reflect(dataOf(result));
        })
        .catch(() => {});
}

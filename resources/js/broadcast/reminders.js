// B8 reminders for SCHEDULED broadcasts, token-aware.
//
// With a bearer token:
//   GET    /broadcasts/{id}/reminder -> { subscribed: bool }
//   POST   /broadcasts/{id}/reminder  (subscribe)
//   DELETE /broadcasts/{id}/reminder  (unsubscribe)
// Without a token: graceful auth CTA ("سجّل الدخول لتفعيل التذكير").

import { apiRequest, dataOf, hasAuth } from './api.js';

const LABEL_ON = 'سيتم تذكيرك';
const LABEL_OFF = 'ذكّرني عند البدء';
const AUTH_CTA = 'سجّل الدخول لتفعيل التذكير';

export function initReminders(config) {
    const { broadcastId, status } = config;
    if (status !== 'scheduled') return;

    const container = document.querySelector('[data-reminder]');
    if (!broadcastId || !container) return;

    const button = container.querySelector('[data-reminder-toggle]');
    const label = container.querySelector('[data-reminder-label]');
    const feedback = container.querySelector('[data-reminder-feedback]');
    if (!button) return;

    let subscribed = false;
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

    const reflect = () => {
        if (label) label.textContent = subscribed ? LABEL_ON : LABEL_OFF;
        button.setAttribute('aria-pressed', subscribed ? 'true' : 'false');
        button.classList.toggle('!bg-amber-500', subscribed);
        button.classList.toggle('!border-amber-500', subscribed);
        button.classList.toggle('!text-black', subscribed);
    };

    if (!hasAuth()) {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            setFeedback(AUTH_CTA);
        });
        return;
    }

    const toggle = async () => {
        if (busy) return;
        busy = true;
        button.disabled = true;
        setFeedback('جارٍ الحفظ…');

        const method = subscribed ? 'DELETE' : 'POST';
        const result = await apiRequest(method, `/broadcasts/${broadcastId}/reminder`, { withAuth: true });

        busy = false;
        button.disabled = false;

        if (result.status === 404) {
            setFeedback('لم يعد هذا البثّ متاحاً.');
            return;
        }
        if (!result.ok) {
            setFeedback('تعذّر حفظ التذكير. حاول مجدداً.');
            return;
        }
        const data = dataOf(result);
        subscribed = data && typeof data.subscribed === 'boolean' ? data.subscribed : !subscribed;
        reflect();
        setFeedback(subscribed ? 'تم ضبط التذكير قبل بدء البثّ.' : 'تم إلغاء التذكير.');
    };

    button.addEventListener('click', toggle);

    // Hydrate current subscription state.
    apiRequest('GET', `/broadcasts/${broadcastId}/reminder`, { withAuth: true })
        .then((result) => {
            if (result.ok) {
                const data = dataOf(result);
                subscribed = !!(data && data.subscribed);
                reflect();
            }
        })
        .catch(() => {});
}

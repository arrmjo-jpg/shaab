// Source-aware live player. Mounts into [data-player] from the broadcast's
// data-source-type + data-source-url. Only invoked when status === 'live'.
//
//   hls / iptv        -> <video> via hls.js (CDN, dynamic <script>) + native HLS fallback
//   youtube_live      -> YouTube <iframe> embed (watch/youtu.be -> /embed/ID)
//   external_provider -> trusted <iframe> embed of the source URL
//   icecast/shoutcast -> <audio controls> stream
// Errors -> reveal [data-player-error] ("تعذّر تشغيل البثّ").

const HLS_CDN = 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js';
let hlsLoader = null;

function loadHlsJs() {
    if (window.Hls) return Promise.resolve(window.Hls);
    if (hlsLoader) return hlsLoader;
    hlsLoader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = HLS_CDN;
        script.async = true;
        script.onload = () => resolve(window.Hls);
        script.onerror = () => reject(new Error('hls.js failed to load'));
        document.head.appendChild(script);
    });
    return hlsLoader;
}

/** Convert a YouTube watch/short URL to a privacy-friendly embed URL. */
function youtubeEmbedUrl(raw) {
    try {
        const url = new URL(raw, window.location.origin);
        const host = url.hostname.replace(/^www\./, '');

        // Already an embed URL (youtube.com/embed or nocookie) -> use as-is.
        if (url.pathname.startsWith('/embed/')) return raw;

        let id = null;
        if (host === 'youtu.be') {
            id = url.pathname.slice(1);
        } else if (host.endsWith('youtube.com')) {
            id = url.searchParams.get('v') || (url.pathname.startsWith('/live/') ? url.pathname.split('/')[2] : null);
        }
        if (!id) return null;
        return `https://www.youtube.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0`;
    } catch {
        return null;
    }
}

function showError(root) {
    const placeholder = root.querySelector('[data-player-placeholder]');
    if (placeholder) placeholder.remove();
    const stage = root.closest('[data-broadcast-id]') || document;
    const err = stage.querySelector('[data-player-error]');
    if (err) {
        err.classList.remove('hidden');
        err.classList.add('flex');
    }
}

function clearPlaceholder(mount) {
    const placeholder = mount.querySelector('[data-player-placeholder]');
    if (placeholder) placeholder.remove();
}

function makeIframe(src) {
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.className = 'h-full w-full';
    iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
    iframe.setAttribute('allowfullscreen', 'true');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    iframe.setAttribute('title', 'مشغّل البثّ');
    return iframe;
}

async function mountHls(mount, url, poster) {
    const video = document.createElement('video');
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    video.className = 'h-full w-full bg-black';
    if (poster) video.poster = poster;
    clearPlaceholder(mount);
    mount.appendChild(video);

    // Native HLS (Safari / iOS) — preferred when available.
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
        video.addEventListener('error', () => showError(mount), { once: true });
        return;
    }

    try {
        const Hls = await loadHlsJs();
        if (Hls && Hls.isSupported()) {
            const hls = new Hls({ enableWorker: true, lowLatencyMode: true });
            hls.loadSource(url);
            hls.attachMedia(video);
            hls.on(Hls.Events.ERROR, (_event, data) => {
                if (data && data.fatal) {
                    hls.destroy();
                    showError(mount);
                }
            });
        } else {
            showError(mount);
        }
    } catch {
        showError(mount);
    }
}

function mountAudio(mount, url, poster) {
    clearPlaceholder(mount);
    const wrap = document.createElement('div');
    wrap.className = 'flex w-full flex-col items-center gap-5 p-8';

    // Audio-native presentation: cover + animated bars + <audio>.
    if (poster) {
        const img = document.createElement('img');
        img.src = poster;
        img.alt = '';
        img.className = 'h-32 w-32 object-cover shadow-2xl';
        wrap.appendChild(img);
    }

    const bars = document.createElement('div');
    bars.className = 'flex items-end gap-1 h-10';
    bars.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < 7; i++) {
        const bar = document.createElement('span');
        bar.className = 'w-1.5 bg-red-500 animate-pulse';
        bar.style.height = `${30 + ((i * 37) % 70)}%`;
        bar.style.animationDelay = `${i * 0.12}s`;
        bars.appendChild(bar);
    }
    wrap.appendChild(bars);

    const audio = document.createElement('audio');
    audio.controls = true;
    audio.autoplay = true;
    audio.className = 'w-full max-w-md';
    audio.src = url;
    audio.addEventListener('error', () => showError(mount), { once: true });
    wrap.appendChild(audio);

    mount.appendChild(wrap);
}

export function initPlayer(config) {
    const { status, sourceType, sourceUrl } = config;
    if (status !== 'live') return;

    const mount = document.querySelector('[data-player]');
    if (!mount || !sourceUrl) return;

    const poster = mount.getAttribute('data-poster') || null;

    switch (sourceType) {
        case 'hls':
        case 'iptv':
            mountHls(mount, sourceUrl, poster);
            break;
        case 'youtube_live': {
            const embed = youtubeEmbedUrl(sourceUrl);
            if (!embed) {
                showError(mount);
                break;
            }
            clearPlaceholder(mount);
            mount.appendChild(makeIframe(embed));
            break;
        }
        case 'external_provider':
            clearPlaceholder(mount);
            mount.appendChild(makeIframe(sourceUrl));
            break;
        case 'icecast':
        case 'shoutcast':
            mountAudio(mount, sourceUrl, poster);
            break;
        default:
            showError(mount);
    }
}

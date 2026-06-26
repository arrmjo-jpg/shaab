'use client';

import { useEffect, useRef, useState } from 'react';
import { Loader2, Pause, Play, Square, Volume2 } from 'lucide-react';

import { getClientId } from '@/lib/client-id';

// طبقة قراءة مشتركة — «استمع للمقال» عبر Google Gemini TTS (الخادم يولّد الصوت بالمفتاح الخادميّ).
// يُرسَل نصّ المحتوى من DOM (targetId) إلى BFF ‎/api/tts، ويُشغَّل الناتج (WAV) بعنصر <audio>:
// تشغيل/إيقاف مؤقت/متابعة/إيقاف + سرعة. تصميم مُدمج (زرّ رئيسيّ بلون الموقع + ضوابط مسطّحة).
const RATES = [0.8, 1, 1.2, 1.5] as const;

type State = 'idle' | 'loading' | 'playing' | 'paused' | 'error';

export function AudioReader({ targetId }: { targetId: string }) {
  const [state, setState] = useState<State>('idle');
  const [rate, setRate] = useState(1);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  useEffect(() => {
    return () => {
      audioRef.current?.pause();
      audioRef.current = null;
    };
  }, []);

  const ensureAudio = (): HTMLAudioElement => {
    if (!audioRef.current) {
      const audio = new Audio();
      audio.onended = () => setState('idle');
      audio.onerror = () => setState('error');
      audioRef.current = audio;
    }
    return audioRef.current;
  };

  const play = async () => {
    const audio = ensureAudio();
    audio.playbackRate = rate;

    if (audio.src) {
      try {
        await audio.play();
        setState('playing');
      } catch {
        setState('error');
      }
      return;
    }

    const el = document.getElementById(targetId);
    const text = (el?.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 5000);
    if (!text) return;

    setState('loading');
    try {
      const res = await fetch('/api/tts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Client-Id': getClientId() },
        body: JSON.stringify({ text }),
      });
      const data: { success?: boolean; audio?: string | null } = await res.json().catch(() => ({}));
      if (!res.ok || !data.success || !data.audio) {
        setState('error');
        return;
      }
      audio.src = data.audio;
      audio.playbackRate = rate;
      await audio.play();
      setState('playing');
    } catch {
      setState('error');
    }
  };

  const pause = () => {
    audioRef.current?.pause();
    setState('paused');
  };
  const resume = async () => {
    try {
      await audioRef.current?.play();
      setState('playing');
    } catch {
      setState('error');
    }
  };
  const stop = () => {
    const audio = audioRef.current;
    if (audio) {
      audio.pause();
      audio.currentTime = 0;
    }
    setState('idle');
  };
  const changeRate = (r: number) => {
    setRate(r);
    if (audioRef.current) audioRef.current.playbackRate = r;
  };

  const primary =
    'inline-flex items-center gap-1.5 bg-primary px-3 py-1.5 text-sm font-bold text-white transition-colors hover:bg-primary/90';
  const icon =
    'inline-flex size-8 items-center justify-center bg-surface-2 text-fg transition-colors hover:bg-surface-3';

  return (
    <div className="inline-flex items-center gap-1.5">
      {state === 'loading' ? (
        <span className={primary} aria-live="polite">
          <Loader2 className="size-4 animate-spin" aria-hidden /> جارٍ التحضير…
        </span>
      ) : null}

      {state === 'idle' ? (
        <button type="button" onClick={() => void play()} className={primary}>
          <Volume2 className="size-4" aria-hidden /> استمع
        </button>
      ) : null}

      {state === 'error' ? (
        <button
          type="button"
          onClick={() => void play()}
          title="تعذّر توليد الصوت، أعد المحاولة"
          className="inline-flex items-center gap-1.5 bg-danger/10 px-3 py-1.5 text-sm font-bold text-danger transition-colors hover:bg-danger/20"
        >
          <Volume2 className="size-4" aria-hidden /> أعد المحاولة
        </button>
      ) : null}

      {state === 'playing' ? (
        <button type="button" onClick={pause} className={primary}>
          <Pause className="size-4" aria-hidden /> إيقاف مؤقت
        </button>
      ) : null}

      {state === 'paused' ? (
        <button type="button" onClick={() => void resume()} className={primary}>
          <Play className="size-4" aria-hidden /> متابعة
        </button>
      ) : null}

      {state === 'playing' || state === 'paused' ? (
        <button type="button" onClick={stop} className={icon} aria-label="إيقاف">
          <Square className="size-4" aria-hidden />
        </button>
      ) : null}

      <select
        value={rate}
        onChange={(e) => changeRate(Number(e.target.value))}
        className="bg-surface-2 px-1.5 py-1.5 text-sm text-fg"
        aria-label="سرعة القراءة"
      >
        {RATES.map((r) => (
          <option key={r} value={r}>
            {r}×
          </option>
        ))}
      </select>
    </div>
  );
}

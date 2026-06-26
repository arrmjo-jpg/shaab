'use client';

import { useEffect, useRef, useState } from 'react';

import { CloseIcon, SearchIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';

import { Container } from './container';

// Expandable header search (Al Jazeera-style, refined): the search icon toggles a full-width search
// bar below the header — autofocus, Escape + click-outside to close, smooth slide/fade. Submits a
// native GET to /search?q=… (matches the WebSite SearchAction; results page is a later slice).
// When reCAPTCHA is enabled in Site Settings, the standard protection notice appears below the form.
export function HeaderSearch({ recaptchaEnabled = false }: { recaptchaEnabled?: boolean }) {
  const [open, setOpen] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open) return;
    inputRef.current?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open]);

  return (
    <>
      <Button
        variant="ghost"
        size="icon"
        aria-label={open ? 'إغلاق البحث' : 'بحث'}
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
      >
        {open ? <CloseIcon className="size-5" aria-hidden /> : <SearchIcon className="size-5" aria-hidden />}
      </Button>

      {open && (
        <>
          {/* click-outside / dim backdrop */}
          <button
            type="button"
            aria-hidden
            tabIndex={-1}
            onClick={() => setOpen(false)}
            className="fixed inset-x-0 bottom-0 top-16 z-30 cursor-default bg-ink/20 backdrop-blur-sm"
          />
          {/* full-width search panel below the header */}
          <div className="absolute inset-x-0 top-full z-40 border-b border-border bg-surface shadow-xl animate-in fade-in slide-in-from-top-2 duration-200">
            <Container className="py-4">
              <form action="/search" method="get" role="search" className="flex items-center gap-3">
                <div className="flex flex-1 items-center gap-3 rounded-lg bg-surface-2 px-4">
                  <SearchIcon className="size-5 shrink-0 text-muted" aria-hidden />
                  <input
                    ref={inputRef}
                    name="q"
                    type="search"
                    autoComplete="off"
                    placeholder="ابحث في الموقع…"
                    className="h-12 w-full bg-transparent text-base text-fg outline-none placeholder:text-muted"
                  />
                </div>
                <Button type="submit" variant="primary" size="lg" className="shrink-0">
                  بحث
                </Button>
              </form>

              {recaptchaEnabled && (
                <p className="mt-3 text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA</p>
              )}
            </Container>
          </div>
        </>
      )}
    </>
  );
}

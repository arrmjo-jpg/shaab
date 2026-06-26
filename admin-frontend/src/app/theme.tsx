import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { DirectionProvider } from '@radix-ui/react-direction';
import { STORAGE_KEYS } from '@/lib/constants';

type ThemeMode = 'light' | 'dark' | 'system';
type Dir = 'rtl' | 'ltr';
type Lang = 'ar' | 'en';

interface ThemeContextValue {
  mode: ThemeMode;
  resolved: 'light' | 'dark';
  dir: Dir;
  lang: Lang;
  setMode: (m: ThemeMode) => void;
  toggleMode: () => void;
  setLang: (l: Lang) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

function systemPrefersDark(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>(
    () => (localStorage.getItem(STORAGE_KEYS.theme) as ThemeMode) || 'system',
  );
  const [lang, setLangState] = useState<Lang>(
    () => (localStorage.getItem(STORAGE_KEYS.lang) as Lang) || 'ar',
  );

  const resolved: 'light' | 'dark' = mode === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : mode;
  const dir: Dir = lang === 'ar' ? 'rtl' : 'ltr';

  useEffect(() => {
    const root = document.documentElement;
    root.classList.toggle('dark', resolved === 'dark');
    root.setAttribute('dir', dir);
    root.setAttribute('lang', lang);
  }, [resolved, dir, lang]);

  const setMode = useCallback((m: ThemeMode) => {
    setModeState(m);
    localStorage.setItem(STORAGE_KEYS.theme, m);
  }, []);

  const toggleMode = useCallback(() => {
    setMode(resolved === 'dark' ? 'light' : 'dark');
  }, [resolved, setMode]);

  const setLang = useCallback((l: Lang) => {
    setLangState(l);
    localStorage.setItem(STORAGE_KEYS.lang, l);
  }, []);

  const value = useMemo<ThemeContextValue>(
    () => ({ mode, resolved, dir, lang, setMode, toggleMode, setLang }),
    [mode, resolved, dir, lang, setMode, toggleMode, setLang],
  );

  // DirectionProvider يمرّر الاتجاه لمكوّنات Radix حتى المنبثقة عبر Portal
  // (قوائم/نوافذ/قوائم اختيار) فتُحاذى نصوصها وأيقوناتها صحيحاً في RTL.
  return (
    <ThemeContext.Provider value={value}>
      <DirectionProvider dir={dir}>{children}</DirectionProvider>
    </ThemeContext.Provider>
  );
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
  return ctx;
}

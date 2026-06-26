import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useReducer,
  type ReactNode,
} from 'react';
import { authService } from '@/services/auth.service';
import {
  getStoredToken,
  registerForcedLogout,
  setStoredToken,
} from '@/services/http/client';
import type { AdminUser } from '@/types/auth.types';

interface AuthState {
  status: 'idle' | 'loading' | 'authenticated' | 'unauthenticated';
  user: AdminUser | null;
}

type Action =
  | { type: 'HYDRATE_START' }
  | { type: 'AUTHENTICATED'; user: AdminUser }
  | { type: 'UNAUTHENTICATED' };

function reducer(state: AuthState, action: Action): AuthState {
  switch (action.type) {
    case 'HYDRATE_START':
      return { ...state, status: 'loading' };
    case 'AUTHENTICATED':
      return { status: 'authenticated', user: action.user };
    case 'UNAUTHENTICATED':
      return { status: 'unauthenticated', user: null };
  }
}

interface AuthContextValue extends AuthState {
  hydrate: () => Promise<void>;
  setSession: (token: string) => Promise<void>;
  logout: () => Promise<void>;
  hasPermission: (perm: string) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(reducer, { status: 'idle', user: null });

  const hydrate = useCallback(async () => {
    if (!getStoredToken()) {
      dispatch({ type: 'UNAUTHENTICATED' });
      return;
    }
    dispatch({ type: 'HYDRATE_START' });
    try {
      const user = await authService.me();
      dispatch({ type: 'AUTHENTICATED', user });
    } catch {
      setStoredToken(null);
      dispatch({ type: 'UNAUTHENTICATED' });
    }
  }, []);

  const setSession = useCallback(async (token: string) => {
    setStoredToken(token);
    const user = await authService.me();
    dispatch({ type: 'AUTHENTICATED', user });
  }, []);

  const logout = useCallback(async () => {
    try {
      await authService.logout();
    } catch {
      /* تجاهل — سنخرج محلياً بأي حال */
    }
    setStoredToken(null);
    dispatch({ type: 'UNAUTHENTICATED' });
  }, []);

  const hasPermission = useCallback(
    (perm: string) => Boolean(state.user?.permissions?.includes(perm)),
    [state.user],
  );

  // خروج قسري عند 401 من طبقة الـ HTTP
  useEffect(() => {
    registerForcedLogout(() => dispatch({ type: 'UNAUTHENTICATED' }));
  }, []);

  useEffect(() => {
    void hydrate();
  }, [hydrate]);

  const value = useMemo<AuthContextValue>(
    () => ({ ...state, hydrate, setSession, logout, hasPermission }),
    [state, hydrate, setSession, logout, hasPermission],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

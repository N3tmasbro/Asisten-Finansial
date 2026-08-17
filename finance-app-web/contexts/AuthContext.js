'use client';

import { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import api from '../lib/api';

const AuthContext = createContext(null);

/**
 * AuthProvider — Global authentication state.
 *
 * Fetches user profile on mount (if token exists),
 * and exposes user data + auth helpers to all children.
 *
 * This is the SINGLE source of truth for handling expired/invalid tokens.
 * When api.js detects a 401, it clears the token and throws — AuthContext
 * catches it here and redirects to /login exactly once.
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const hasFetched = useRef(false); // Guard: only fetch once on mount
  const router = useRouter();
  const pathname = usePathname();

  // Protected routes that require authentication
  const protectedRoutes = ['/dashboard', '/transactions', '/analytics', '/categories', '/wallets', '/budgets', '/settings'];
  const isProtectedRoute = protectedRoutes.some(
    (route) => pathname === route || pathname.startsWith(route + '/')
  );

  const fetchUser = useCallback(async () => {
    const token = api.getToken();
    if (!token) {
      setUser(null);
      setIsLoading(false);
      return;
    }

    try {
      const data = await api.getProfile();
      setUser(data.user || data);
    } catch (err) {
      // Token is invalid/expired — api.js already cleared it.
      // Just update state; redirect is handled below via effect.
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (hasFetched.current) return;
    hasFetched.current = true;
    fetchUser();
  }, [fetchUser]);

  // Redirect to /login if on a protected route with no user and loading is done
  useEffect(() => {
    if (isLoading) return;
    if (!user && isProtectedRoute) {
      router.replace('/login');
    }
  }, [isLoading, user, isProtectedRoute, router]);

  const login = async (email, password) => {
    const result = await api.login(email, password);
    await fetchUser();
    return result;
  };

  const register = async (data) => {
    const result = await api.register(data);
    await fetchUser();
    return result;
  };

  const logout = async () => {
    try {
      await api.logout();
    } catch (err) {
      // Ignore API errors, clear anyway
    } finally {
      api.clearToken();
      setUser(null);
      router.replace('/login');
    }
  };

  return (
    <AuthContext.Provider value={{ user, isLoading, login, register, logout, refetch: fetchUser }}>
      {children}
    </AuthContext.Provider>
  );
}

/**
 * Hook to access auth context.
 * Must be used within AuthProvider.
 */
export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}


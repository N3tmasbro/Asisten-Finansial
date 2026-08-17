import { NextResponse } from 'next/server';

/**
 * Next.js Proxy — Route Protection
 *
 * Protects dashboard routes by checking for auth_token cookie.
 * Redirects unauthenticated users to /login.
 * Redirects authenticated users away from /login and /register.
 *
 * Migrated from middleware.js → proxy.js (Next.js 16+)
 */

const protectedRoutes = [
  '/dashboard',
  '/transactions',
  '/analytics',
  '/categories',
  '/wallets',
  '/budgets',
  '/settings',
];

const authRoutes = ['/', '/login', '/register'];

export function proxy(request) {
  const { pathname } = request.nextUrl;
  const token = request.cookies.get('auth_token')?.value;

  // Check if current path is a protected route
  const isProtected = protectedRoutes.some(
    (route) => pathname === route || pathname.startsWith(route + '/')
  );

  // Check if current path is an auth route (login/register)
  const isAuthRoute = authRoutes.some(
    (route) => pathname === route || pathname.startsWith(route + '/')
  );

  // Redirect to login if accessing protected route without token
  if (isProtected && !token) {
    const loginUrl = new URL('/login', request.url);
    loginUrl.searchParams.set('redirect', pathname);
    return NextResponse.redirect(loginUrl);
  }

  // Redirect to dashboard if accessing auth routes while already logged in
  if (isAuthRoute && token) {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    '/',
    '/dashboard/:path*',
    '/transactions/:path*',
    '/analytics/:path*',
    '/categories/:path*',
    '/wallets/:path*',
    '/budgets/:path*',
    '/settings/:path*',
    '/login',
    '/register',
  ],
};

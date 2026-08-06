'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const navItems = [
  { href: '/dashboard', icon: '📊', label: 'Dashboard' },
  { href: '/transactions', icon: '💳', label: 'Transaksi' },
  { href: '/analytics', icon: '📈', label: 'Analitik' },
  { href: '/categories', icon: '🏷️', label: 'Kategori' },
  { href: '/wallets', icon: '👛', label: 'Dompet' },
  { href: '/budgets', icon: '🎯', label: 'Budget' },
  { href: '/settings', icon: '⚙️', label: 'Pengaturan' },
];

export default function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="fixed top-0 left-0 w-64 h-screen border-r border-white/[0.06] flex flex-col z-50"
      style={{ background: 'var(--color-bg-secondary)' }}>
      {/* Logo */}
      <div className="px-5 py-6 border-b border-white/[0.06]">
        <Link href="/dashboard" className="flex items-center gap-3 no-underline">
          <div className="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
            style={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)' }}>
            💰
          </div>
          <div>
            <h1 className="text-sm font-bold text-white leading-tight">Asisten Finansial</h1>
            <p className="text-xs text-gray-500 leading-tight">AI-Powered</p>
          </div>
        </Link>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4 overflow-y-auto">
        <div className="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 mb-3">
          Menu Utama
        </div>
        {navItems.map((item) => {
          const isActive = pathname === item.href || pathname?.startsWith(item.href + '/');
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium no-underline mb-0.5 transition-all duration-150
                ${isActive
                  ? 'text-white bg-indigo-500/10'
                  : 'text-gray-400 hover:text-white hover:bg-white/[0.03]'
                }`}
            >
              <span className="text-lg w-6 text-center">{item.icon}</span>
              <span>{item.label}</span>
              {isActive && (
                <div className="ml-auto w-1.5 h-6 rounded-full"
                  style={{ background: 'linear-gradient(to bottom, #6366f1, #8b5cf6)' }} />
              )}
            </Link>
          );
        })}
      </nav>

      {/* User Section */}
      <div className="px-4 py-4 border-t border-white/[0.06]">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold"
            style={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)' }}>
            U
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-white truncate">User</p>
            <p className="text-xs text-gray-500">Free Plan</p>
          </div>
        </div>
      </div>
    </aside>
  );
}

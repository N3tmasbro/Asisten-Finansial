'use client';

import { useState, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';

export default function SettingsPage() {
  const { user, isLoading: authLoading, logout } = useAuth();
  const [loggingOut, setLoggingOut] = useState(false);
  const [bridgeStatus, setBridgeStatus] = useState(null);
  const [bridgeLoading, setBridgeLoading] = useState(true);

  useEffect(() => {
    fetchBridgeStatus();
  }, []);

  async function fetchBridgeStatus() {
    setBridgeLoading(true);
    try {
      const res = await fetch('/api/bridge-status');
      const data = await res.json();
      setBridgeStatus(data);
    } catch (err) {
      setBridgeStatus({ status: 'disconnected', user: null, error: 'Tidak bisa terhubung' });
    } finally {
      setBridgeLoading(false);
    }
  }

  async function handleLogout() {
    setLoggingOut(true);
    await logout();
  }

  const loading = authLoading;
  const profile = user;

  if (loading) {
    return (
      <div className="animate-fade-in flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="text-4xl mb-3 animate-pulse">⚙️</div>
          <p className="text-gray-400">Memuat pengaturan...</p>
        </div>
      </div>
    );
  }

  const phoneVerified = profile?.phone_verified || profile?.is_phone_verified || false;
  const isConnected = bridgeStatus?.status === 'connected';
  const botName = bridgeStatus?.user?.name || null;

  return (
    <div className="animate-fade-in max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-extrabold tracking-tight">Pengaturan</h1>
        <p className="text-gray-400 text-sm mt-1">Kelola profil dan koneksi WhatsApp</p>
      </div>

      {/* Profile Section */}
      <div className="glass-card mb-6">
        <h2 className="text-lg font-bold mb-4">👤 Profil</h2>
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-gray-400 mb-1.5">Nama</label>
            <input type="text" className="input-field" value={profile?.name || ''} readOnly />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-400 mb-1.5">Email</label>
            <input type="email" className="input-field" value={profile?.email || ''} readOnly />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-400 mb-1.5">Nomor WhatsApp</label>
            <div className="flex gap-2">
              <input type="text" className="input-field flex-1"
                value={profile?.phone_number ? `+${profile.phone_number}` : '-'} readOnly />
              {phoneVerified ? (
                <span className="flex items-center gap-1 text-xs font-semibold text-emerald-400 bg-emerald-400/10 px-3 rounded-lg">
                  ✅ Terverifikasi
                </span>
              ) : (
                <span className="flex items-center gap-1 text-xs font-semibold text-amber-400 bg-amber-400/10 px-3 rounded-lg">
                  ⚠️ Belum diverifikasi
                </span>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* WhatsApp Connection — Dynamic */}
      <div className="glass-card mb-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold">📱 Koneksi WhatsApp</h2>
          <button
            onClick={fetchBridgeStatus}
            className="text-xs text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
          >
            🔄 Refresh
          </button>
        </div>
        {bridgeLoading ? (
          <div className="flex items-center gap-3 p-4 rounded-xl" style={{ background: 'rgba(255,255,255,0.02)' }}>
            <div className="w-3 h-3 rounded-full bg-gray-500 animate-pulse" />
            <p className="text-sm text-gray-400">Mengecek status bridge...</p>
          </div>
        ) : isConnected ? (
          <div className="flex items-center gap-4 p-4 rounded-xl"
            style={{ background: 'rgba(16,185,129,0.05)' }}>
            <div className="w-3 h-3 rounded-full bg-emerald-400"
              style={{ boxShadow: '0 0 8px rgba(16,185,129,0.5)' }} />
            <div>
              <p className="text-sm font-semibold text-white">Terhubung ✅</p>
              <p className="text-xs text-gray-400">
                {botName ? `Bot: ${botName}` : 'WhatsApp bridge aktif.'} Kirim pesan ke bot untuk mencatat transaksi.
              </p>
            </div>
          </div>
        ) : (
          <div className="flex items-center gap-4 p-4 rounded-xl"
            style={{ background: 'rgba(244,63,94,0.05)' }}>
            <div className="w-3 h-3 rounded-full bg-rose-400"
              style={{ boxShadow: '0 0 8px rgba(244,63,94,0.5)' }} />
            <div>
              <p className="text-sm font-semibold text-white">Tidak Terhubung ❌</p>
              <p className="text-xs text-gray-400">
                {bridgeStatus?.error || 'WhatsApp bridge tidak aktif. Jalankan `npm start` di folder whatsapp-bridge.'}
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Subscription section hidden for demo */}
      {/* TODO: Restore when payment gateway is integrated
      <div className="glass-card mb-6">
        <h2 className="text-lg font-bold mb-4">⭐ Langganan</h2>
        ...
      </div>
      */}

      {/* Danger Zone */}
      <div className="glass-card border-rose-500/20">
        <h2 className="text-lg font-bold mb-4 text-rose-400">⚠️ Zona Berbahaya</h2>
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-white">Logout</p>
            <p className="text-xs text-gray-400">Keluar dari akun ini</p>
          </div>
          <button
            onClick={handleLogout}
            disabled={loggingOut}
            className="px-4 py-2 rounded-lg text-sm font-semibold text-rose-400 bg-rose-400/10 border border-rose-400/20 hover:bg-rose-400/20 transition-all duration-150 cursor-pointer disabled:opacity-50"
          >
            {loggingOut ? 'Logging out...' : 'Logout'}
          </button>
        </div>
      </div>
    </div>
  );
}


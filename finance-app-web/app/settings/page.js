'use client';

import { useState, useEffect } from 'react';
import api from '../../lib/api';

export default function SettingsPage() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loggingOut, setLoggingOut] = useState(false);

  useEffect(() => {
    fetchProfile();
  }, []);

  async function fetchProfile() {
    try {
      const data = await api.getProfile();
      setProfile(data.user || data);
    } catch (err) {
      console.error('Failed to fetch profile:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await api.logout();
    } catch (err) {
      // Ignore errors, clear token anyway
    } finally {
      api.clearToken();
      window.location.href = '/login';
    }
  }

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

  return (
    <div className="animate-fade-in max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-extrabold tracking-tight">Pengaturan</h1>
        <p className="text-gray-400 text-sm mt-1">Kelola profil, koneksi WhatsApp, dan langganan</p>
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

      {/* WhatsApp Connection */}
      <div className="glass-card mb-6">
        <h2 className="text-lg font-bold mb-4">📱 Koneksi WhatsApp</h2>
        <div className="flex items-center gap-4 p-4 rounded-xl"
          style={{ background: 'rgba(16,185,129,0.05)' }}>
          <div className="w-3 h-3 rounded-full bg-emerald-400"
            style={{ boxShadow: '0 0 8px rgba(16,185,129,0.5)' }} />
          <div>
            <p className="text-sm font-semibold text-white">Terhubung</p>
            <p className="text-xs text-gray-400">
              WhatsApp bridge aktif. Kirim pesan ke bot untuk mencatat transaksi.
            </p>
          </div>
        </div>
      </div>

      {/* Subscription */}
      <div className="glass-card mb-6">
        <h2 className="text-lg font-bold mb-4">⭐ Langganan</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {[
            { tier: 'free', label: 'Free', price: 'Gratis', features: ['Input unlimited via WA', 'Q&A dasar', 'Dashboard sederhana'], active: (profile?.subscription_tier || 'free') === 'free' },
            { tier: 'starter', label: 'Starter', price: 'Rp49.000/bln', features: ['Semua fitur Free', 'Trend analysis', 'Prediksi saldo', 'Export CSV'], active: false },
          ].map((plan) => (
            <div key={plan.tier}
              className={`p-5 rounded-xl border transition-all duration-150 ${
                plan.active
                  ? 'border-indigo-500/30 bg-indigo-500/5'
                  : 'border-white/[0.06] hover:border-white/[0.12]'
              }`}>
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-base font-bold">{plan.label}</h3>
                {plan.active && (
                  <span className="text-xs font-semibold px-2 py-0.5 rounded-full text-indigo-400 bg-indigo-400/10">
                    Aktif
                  </span>
                )}
              </div>
              <p className="text-2xl font-extrabold tracking-tight mb-3">{plan.price}</p>
              <ul className="space-y-2">
                {plan.features.map((f, i) => (
                  <li key={i} className="text-sm text-gray-400 flex items-center gap-2">
                    <span className="text-emerald-400">✓</span> {f}
                  </li>
                ))}
              </ul>
              {!plan.active && (
                <button className="btn-primary text-sm w-full mt-4">Upgrade</button>
              )}
            </div>
          ))}
        </div>
      </div>

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

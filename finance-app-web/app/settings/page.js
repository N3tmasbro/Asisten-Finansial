'use client';

import { useState, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../lib/api';

export default function SettingsPage() {
  const { user, isLoading: authLoading, logout, refetch } = useAuth();
  const [loggingOut, setLoggingOut] = useState(false);
  const [bridgeStatus, setBridgeStatus] = useState(null);
  const [bridgeLoading, setBridgeLoading] = useState(true);

  // OTP verification states
  const [otpStep, setOtpStep] = useState('idle'); // 'idle' | 'sending' | 'input' | 'verifying' | 'success'
  const [otpCode, setOtpCode] = useState('');
  const [otpError, setOtpError] = useState('');
  const [otpSuccess, setOtpSuccess] = useState('');
  const [otpCooldown, setOtpCooldown] = useState(0); // seconds countdown for resend

  useEffect(() => {
    fetchBridgeStatus();
  }, []);

  // Cooldown timer for resend OTP
  useEffect(() => {
    if (otpCooldown <= 0) return;
    const timer = setTimeout(() => setOtpCooldown((prev) => prev - 1), 1000);
    return () => clearTimeout(timer);
  }, [otpCooldown]);

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

  async function handleRequestOtp() {
    setOtpError('');
    setOtpSuccess('');
    setOtpStep('sending');
    try {
      await api.requestPhoneVerification();
      setOtpStep('input');
      setOtpCooldown(60); // 60s cooldown before resend
      setOtpCode('');
    } catch (err) {
      setOtpError(err.message || 'Gagal mengirim OTP. Coba lagi.');
      setOtpStep('idle');
    }
  }

  async function handleVerifyOtp(e) {
    e.preventDefault();
    if (otpCode.length !== 6) {
      setOtpError('Kode OTP harus 6 digit.');
      return;
    }
    setOtpError('');
    setOtpStep('verifying');
    try {
      await api.verifyPhone(otpCode);
      setOtpStep('success');
      setOtpSuccess('Nomor WhatsApp berhasil diverifikasi! 🎉');
      // Refresh user profile to update phone_verified state
      await refetch();
    } catch (err) {
      setOtpError(err.message || 'Kode OTP tidak valid atau sudah kadaluarsa.');
      setOtpStep('input');
    }
  }

  function handleOtpInput(e) {
    const val = e.target.value.replace(/\D/g, '').slice(0, 6);
    setOtpCode(val);
    setOtpError('');
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

      {/* WhatsApp Verification Section — shown only if not yet verified */}
      {!phoneVerified && (
        <div className="glass-card mb-6" style={{ borderColor: 'rgba(99,102,241,0.2)' }}>
          <div className="flex items-start gap-4">
            <div className="text-3xl mt-1">🔐</div>
            <div className="flex-1">
              <h2 className="text-lg font-bold mb-1">Verifikasi Nomor WhatsApp</h2>
              <p className="text-sm text-gray-400 mb-4">
                Verifikasi nomor WhatsApp kamu agar bot bisa mengenali pesanmu dan mencatat transaksi secara otomatis.
              </p>

              {/* Success state */}
              {otpStep === 'success' && (
                <div className="flex items-center gap-3 p-4 rounded-xl mb-4"
                  style={{ background: 'rgba(16,185,129,0.08)', border: '1px solid rgba(16,185,129,0.2)' }}>
                  <span className="text-2xl">🎉</span>
                  <p className="text-sm font-semibold text-emerald-400">{otpSuccess}</p>
                </div>
              )}

              {/* Error message */}
              {otpError && (
                <div className="flex items-center gap-2 p-3 rounded-lg mb-4"
                  style={{ background: 'rgba(244,63,94,0.08)', border: '1px solid rgba(244,63,94,0.2)' }}>
                  <span className="text-sm">⚠️</span>
                  <p className="text-sm text-rose-400">{otpError}</p>
                </div>
              )}

              {/* Step: idle — show Send OTP button */}
              {(otpStep === 'idle' || otpStep === 'sending') && (
                <div className="flex flex-col gap-3">
                  <p className="text-xs text-gray-500">
                    Kode OTP 6 digit akan dikirimkan ke nomor{' '}
                    <span className="text-white font-medium">+{profile?.phone_number}</span>{' '}
                    via WhatsApp.
                  </p>
                  <button
                    id="btn-request-otp"
                    onClick={handleRequestOtp}
                    disabled={otpStep === 'sending'}
                    className="w-full sm:w-auto px-6 py-2.5 rounded-lg text-sm font-semibold transition-all duration-150 cursor-pointer disabled:opacity-50"
                    style={{
                      background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                      color: 'white',
                    }}
                  >
                    {otpStep === 'sending' ? (
                      <span className="flex items-center gap-2 justify-center">
                        <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                        Mengirim OTP...
                      </span>
                    ) : (
                      '📨 Kirim Kode OTP'
                    )}
                  </button>
                </div>
              )}

              {/* Step: input or verifying — show OTP input form */}
              {(otpStep === 'input' || otpStep === 'verifying') && (
                <form onSubmit={handleVerifyOtp} className="flex flex-col gap-4">
                  <div>
                    <label className="block text-xs font-medium text-gray-400 mb-2">
                      Masukkan kode 6 digit dari WhatsApp
                    </label>
                    <input
                      id="otp-code-input"
                      type="text"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      maxLength={6}
                      value={otpCode}
                      onChange={handleOtpInput}
                      placeholder="• • • • • •"
                      autoFocus
                      className="input-field text-center text-2xl font-bold tracking-[0.5em] w-full"
                      style={{ letterSpacing: '0.4em' }}
                    />
                    <p className="text-xs text-gray-500 mt-1.5">
                      Kode berlaku selama 10 menit sejak dikirim.
                    </p>
                  </div>

                  <div className="flex items-center gap-3">
                    <button
                      id="btn-verify-otp"
                      type="submit"
                      disabled={otpCode.length !== 6 || otpStep === 'verifying'}
                      className="px-6 py-2.5 rounded-lg text-sm font-semibold transition-all duration-150 cursor-pointer disabled:opacity-50"
                      style={{
                        background: otpCode.length === 6
                          ? 'linear-gradient(135deg, #6366f1, #8b5cf6)'
                          : 'rgba(255,255,255,0.05)',
                        color: 'white',
                      }}
                    >
                      {otpStep === 'verifying' ? (
                        <span className="flex items-center gap-2">
                          <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                          Memverifikasi...
                        </span>
                      ) : (
                        '✅ Verifikasi'
                      )}
                    </button>

                    {/* Resend button with cooldown */}
                    <button
                      id="btn-resend-otp"
                      type="button"
                      onClick={handleRequestOtp}
                      disabled={otpCooldown > 0 || otpStep === 'verifying'}
                      className="text-sm font-medium transition-colors cursor-pointer disabled:opacity-40"
                      style={{ color: otpCooldown > 0 ? '#6b7280' : '#818cf8' }}
                    >
                      {otpCooldown > 0 ? `Kirim ulang (${otpCooldown}s)` : 'Kirim ulang OTP'}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        </div>
      )}

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

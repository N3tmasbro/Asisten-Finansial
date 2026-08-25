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
      <div style={{ padding: 32, display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 400 }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">⚙️</div>
          <p style={{ color: 'var(--text-secondary)' }}>Memuat pengaturan...</p>
        </div>
      </div>
    );
  }

  const phoneVerified = profile?.phone_verified || profile?.is_phone_verified || false;
  const isConnected = bridgeStatus?.status === 'connected';
  const botName = bridgeStatus?.user?.name || null;

  return (
    <div style={{ padding: '24px 32px', width: '100%', boxSizing: 'border-box' }}>
      {/* Page Header */}
      <div style={{ marginBottom: 28 }}>
        <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Pengaturan</h1>
        <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Kelola profil dan koneksi WhatsApp</p>
      </div>

      {/* Two-column grid on large screens, single column on mobile */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 20, alignItems: 'start' }}>

        {/* ── LEFT COLUMN ── */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>

          {/* Profile Section */}
          <div className="card" style={{ padding: 24 }}>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>👤 Profil</h2>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Nama</label>
                <input type="text" className="input-field" value={profile?.name || ''} readOnly />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Email</label>
                <input type="email" className="input-field" value={profile?.email || ''} readOnly />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Nomor WhatsApp</label>
                <div style={{ display: 'flex', gap: 8 }}>
                  <input type="text" className="input-field" style={{ flex: 1, minWidth: 0 }}
                    value={profile?.phone_number ? `+${profile.phone_number}` : '-'} readOnly />
                  {phoneVerified ? (
                    <span style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, fontWeight: 600, color: 'var(--accent-green)', background: 'var(--accent-green-bg)', padding: '0 12px', borderRadius: 8, whiteSpace: 'nowrap', flexShrink: 0 }}>
                      ✅ Terverifikasi
                    </span>
                  ) : (
                    <span style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, fontWeight: 600, color: 'var(--accent-amber)', background: 'var(--accent-amber-bg)', padding: '0 12px', borderRadius: 8, whiteSpace: 'nowrap', flexShrink: 0 }}>
                      ⚠️ Belum diverifikasi
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* WhatsApp Verification — only if not verified */}
          {!phoneVerified && (
            <div className="card" style={{ padding: 24, borderColor: 'var(--teal-bg)', borderLeft: '3px solid var(--teal)' }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 16 }}>
                <div style={{ fontSize: 28, marginTop: 4 }}>🔐</div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 4px' }}>Verifikasi Nomor WhatsApp</h2>
                  <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '0 0 16px' }}>
                    Verifikasi nomor WhatsApp kamu agar bot bisa mengenali pesanmu dan mencatat transaksi secara otomatis.
                  </p>

                  {/* Success state */}
                  {otpStep === 'success' && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: 16, borderRadius: 12, marginBottom: 16, background: 'var(--accent-green-bg)', border: '1px solid var(--accent-green-bg)' }}>
                      <span style={{ fontSize: 20 }}>🎉</span>
                      <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--accent-green)', margin: 0 }}>{otpSuccess}</p>
                    </div>
                  )}

                  {/* Error message */}
                  {otpError && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: 12, borderRadius: 8, marginBottom: 16, background: 'var(--accent-red-bg)' }}>
                      <span style={{ fontSize: 14 }}>⚠️</span>
                      <p style={{ fontSize: 14, color: 'var(--accent-red)', margin: 0 }}>{otpError}</p>
                    </div>
                  )}

                  {/* Step: idle — show Send OTP button */}
                  {(otpStep === 'idle' || otpStep === 'sending') && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                      <p style={{ fontSize: 13, color: 'var(--text-tertiary)', margin: 0 }}>
                        Kode OTP 6 digit akan dikirimkan ke nomor{' '}
                        <span style={{ color: 'var(--text-primary)', fontWeight: 500 }}>+{profile?.phone_number}</span>{' '}
                        via WhatsApp.
                      </p>
                      <button
                        id="btn-request-otp"
                        onClick={handleRequestOtp}
                        disabled={otpStep === 'sending'}
                        className="btn-primary"
                        style={{ alignSelf: 'flex-start' }}
                      >
                        {otpStep === 'sending' ? (
                          <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ width: 16, height: 16, border: '2px solid rgba(255,255,255,0.3)', borderTopColor: 'white', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
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
                    <form onSubmit={handleVerifyOtp} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                      <div>
                        <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 8 }}>
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
                          className="input-field"
                          style={{ textAlign: 'center', fontSize: 24, fontWeight: 700, letterSpacing: '0.4em', width: '100%' }}
                        />
                        <p style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: '6px 0 0' }}>
                          Kode berlaku selama 10 menit sejak dikirim.
                        </p>
                      </div>

                      <div className="flex flex-col md:flex-row md:items-center gap-3">
                        <button
                          id="btn-verify-otp"
                          type="submit"
                          disabled={otpCode.length !== 6 || otpStep === 'verifying'}
                          className="btn-primary"
                          style={{ opacity: otpCode.length === 6 ? 1 : 0.5 }}
                        >
                          {otpStep === 'verifying' ? (
                            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                              <span style={{ width: 16, height: 16, border: '2px solid rgba(255,255,255,0.3)', borderTopColor: 'white', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
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
                          style={{
                            background: 'none', border: 'none', fontSize: 14, fontWeight: 500,
                            color: otpCooldown > 0 ? 'var(--text-tertiary)' : 'var(--teal)',
                            cursor: otpCooldown > 0 ? 'default' : 'pointer',
                            opacity: otpCooldown > 0 ? 0.6 : 1,
                          }}
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
        </div>

        {/* ── RIGHT COLUMN ── */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>

          {/* WhatsApp Connection */}
          <div className="card" style={{ padding: 24 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
              <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>📱 Koneksi WhatsApp</h2>
              <button
                onClick={fetchBridgeStatus}
                style={{ background: 'none', border: 'none', fontSize: 13, color: 'var(--teal)', fontWeight: 500, cursor: 'pointer' }}
              >
                🔄 Refresh
              </button>
            </div>
            {bridgeLoading ? (
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: 16, borderRadius: 10, background: 'var(--bg-secondary)' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--text-tertiary)', flexShrink: 0 }} className="animate-pulse" />
                <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: 0 }}>Mengecek status bridge...</p>
              </div>
            ) : isConnected ? (
              <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: 16, borderRadius: 10, background: 'var(--accent-green-bg)' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--accent-green)', boxShadow: '0 0 8px var(--accent-green-bg)', flexShrink: 0 }} />
                <div>
                  <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>Terhubung ✅</p>
                  <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '2px 0 0' }}>
                    {botName ? `Bot: ${botName}` : 'WhatsApp bridge aktif.'} Kirim pesan ke bot untuk mencatat transaksi.
                  </p>
                </div>
              </div>
            ) : (
              <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: 16, borderRadius: 10, background: 'var(--accent-red-bg)' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--accent-red)', boxShadow: '0 0 8px var(--accent-red-bg)', flexShrink: 0 }} />
                <div>
                  <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>Tidak Terhubung ❌</p>
                  <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '2px 0 0' }}>
                    {bridgeStatus?.error || 'WhatsApp bridge tidak aktif. Jalankan `npm start` di folder whatsapp-bridge.'}
                  </p>
                </div>
              </div>
            )}
          </div>

          {/* Danger Zone */}
          <div className="card" style={{ padding: 24, borderColor: 'var(--accent-red-bg)', borderLeft: '3px solid var(--accent-red)' }}>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--accent-red)', margin: '0 0 16px' }}>⚠️ Zona Berbahaya</h2>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
              <div>
                <p style={{ fontSize: 14, fontWeight: 500, color: 'var(--text-primary)', margin: 0 }}>Logout</p>
                <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '2px 0 0' }}>Keluar dari akun ini</p>
              </div>
              <button
                onClick={handleLogout}
                disabled={loggingOut}
                style={{
                  padding: '8px 20px', borderRadius: 8, fontSize: 14, fontWeight: 600,
                  color: 'var(--accent-red)', background: 'var(--accent-red-bg)',
                  border: '1px solid var(--accent-red-bg)', cursor: 'pointer',
                  opacity: loggingOut ? 0.5 : 1,
                  transition: 'all 0.15s',
                  flexShrink: 0,
                }}
                className="shrink-0"
              >
                {loggingOut ? 'Logging out...' : 'Logout'}
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}

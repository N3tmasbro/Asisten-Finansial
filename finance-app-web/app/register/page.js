'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '../../contexts/AuthContext';
import { useTheme } from '../../contexts/ThemeContext';

export default function RegisterPage() {
  const router = useRouter();
  const { register } = useAuth();
  const { isDark, toggleTheme } = useTheme();
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (form.password !== form.password_confirmation) {
      setError('Password tidak cocok.');
      return;
    }

    setLoading(true);

    try {
      await register(form);
      router.push('/dashboard');
    } catch (err) {
      setError(err.message || 'Registrasi gagal. Coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="public-shell auth-shell">
      {/* Brand link */}
      <Link href="/" className="auth-brand-link">
        <span className="public-brand">
          <span className="brand-mark">💰</span>
          Asisten <b>Finansial</b>
        </span>
      </Link>

      {/* Theme toggle */}
      <div className="auth-theme-control">
        <button className="public-theme-toggle" onClick={toggleTheme}>
          <span>{isDark ? '🌙' : '☀️'}</span>
          <span>{isDark ? 'Dark' : 'Light'}</span>
        </button>
      </div>

      <div className="auth-main">
        {/* Heading */}
        <div className="auth-heading">
          <div className="auth-icon">💰</div>
          <h1>Buat Akun Baru</h1>
          <p>Daftar gratis, mulai catat keuangan via WhatsApp</p>
        </div>

        {/* Form Card */}
        <div className="auth-card">
          <form onSubmit={handleSubmit}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>
                {error}
              </div>
            )}

            <label>
              Nama Lengkap
              <input
                type="text"
                placeholder="John Doe"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
            </label>

            <label>
              Email
              <input
                type="email"
                placeholder="kamu@email.com"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
            </label>

            <label>
              Nomor WhatsApp
              <input
                type="tel"
                placeholder="08123456789"
                value={form.phone_number}
                onChange={(e) => setForm({ ...form, phone_number: e.target.value })}
                required
              />
              <small>Nomor ini akan digunakan untuk interaksi via WhatsApp</small>
            </label>

            <label>
              Password
              <input
                type="password"
                placeholder="Minimal 8 karakter"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                required
                minLength={8}
              />
            </label>

            <label>
              Konfirmasi Password
              <input
                type="password"
                placeholder="Ulangi password"
                value={form.password_confirmation}
                onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                required
              />
            </label>

            <button
              type="submit"
              disabled={loading}
              className="auth-submit"
              style={{ opacity: loading ? 0.6 : 1 }}
            >
              {loading ? 'Memproses...' : 'Daftar Sekarang 🚀'}
            </button>
          </form>
        </div>

        {/* Switch link */}
        <div className="auth-switch">
          Sudah punya akun?{' '}
          <Link href="/login" style={{ textDecoration: 'none' }}>
            <button type="button">Masuk</button>
          </Link>
        </div>
      </div>
    </div>
  );
}

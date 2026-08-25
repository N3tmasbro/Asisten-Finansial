'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '../../contexts/AuthContext';
import { useTheme } from '../../contexts/ThemeContext';

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuth();
  const { isDark, toggleTheme } = useTheme();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(form.email, form.password);
      router.push('/dashboard');
    } catch (err) {
      setError(err.message || 'Login gagal. Cek email dan password kamu.');
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
          <h1>Masuk ke Akunmu</h1>
          <p>Pantau keuanganmu di dashboard</p>
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
              Password
              <input
                type="password"
                placeholder="••••••••"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                required
              />
            </label>

            <button
              type="submit"
              disabled={loading}
              className="auth-submit"
              style={{ opacity: loading ? 0.6 : 1 }}
            >
              {loading ? 'Memproses...' : 'Masuk'}
            </button>
          </form>
        </div>

        {/* Switch link */}
        <div className="auth-switch">
          Belum punya akun?{' '}
          <Link href="/register" style={{ textDecoration: 'none' }}>
            <button type="button">Daftar gratis</button>
          </Link>
        </div>
      </div>
    </div>
  );
}

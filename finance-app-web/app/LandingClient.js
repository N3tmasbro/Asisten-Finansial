'use client';

import Link from 'next/link';
import { useTheme } from '../contexts/ThemeContext';
import { useAuth } from '../contexts/AuthContext';

const features = [
  { icon: '💬', title: 'Input Natural Language', desc: 'Kirim "isi bensin 200 ribu" — langsung tercatat. Tanpa form, tanpa pilih kategori.' },
  { icon: '🧠', title: 'AI Auto-Kategori', desc: 'AI otomatis mendeteksi kategori transaksi. Kamu tinggal kirim pesan.' },
  { icon: '📊', title: 'Dashboard Analitik', desc: 'Grafik tren pengeluaran, breakdown kategori, dan prediksi saldo di web dashboard.' },
  { icon: '🔮', title: 'Prediksi Saldo', desc: '"Dengan pola saat ini, saldo diperkirakan habis tanggal 26."' },
  { icon: '💡', title: 'Saran Penghematan', desc: '"Jika mengurangi makan di luar 20%, kamu bisa menabung Rp500.000/bulan."' },
  { icon: '⏰', title: 'Pengingat Cerdas', desc: 'Mendeteksi pola pembayaran rutin dan mengingatkan kamu sebelum jatuh tempo.' },
];

export default function LandingClient() {
  const { isDark, toggleTheme } = useTheme();
  const { user, isLoading } = useAuth();

  return (
    <div className="public-shell landing-shell">
      {/* Navbar */}
      <header className="landing-nav">
        <span className="public-brand">
          <span className="brand-mark">💰</span>
          Asisten <b>Finansial</b>
        </span>
        <nav>
          <button className="public-theme-toggle" onClick={toggleTheme}>
            <span>{isDark ? '🌙' : '☀️'}</span>
            <span>{isDark ? 'Dark' : 'Light'}</span>
          </button>
          {!isLoading && user ? (
            <Link href="/dashboard" className="button button-small" style={{ textDecoration: 'none' }}>Dashboard</Link>
          ) : (
            <>
              <Link href="/login" className="nav-text" style={{ textDecoration: 'none' }}>Login</Link>
              <Link href="/register" className="button button-small" style={{ textDecoration: 'none' }}>Register</Link>
            </>
          )}
        </nav>
      </header>

      {/* Hero Section */}
      <section className="hero-section">
        <span className="eyebrow">🚀 Gratis untuk selamanya</span>
        <h1>
          Catat Keuanganmu
          <span>via WhatsApp</span>
        </h1>
        <p>
          Cukup kirim pesan seperti <b>&quot;isi bensin 200 ribu&quot;</b> ke WhatsApp,
          AI kami langsung mencatat. Tanpa install aplikasi baru, tanpa isi form.
        </p>
        <div className="hero-actions">
          {!isLoading && user ? (
            <Link href="/dashboard" className="button" style={{ textDecoration: 'none', padding: '0 24px', minHeight: 44, fontSize: 14 }}>
              Masuk ke Dashboard →
            </Link>
          ) : (
            <Link href="/register" className="button" style={{ textDecoration: 'none', padding: '0 24px', minHeight: 44, fontSize: 14 }}>
              Coba Sekarang →
            </Link>
          )}
          <Link href="#features" className="button button-secondary" style={{ textDecoration: 'none', padding: '0 24px', minHeight: 44, fontSize: 14 }}>
            Lihat Fitur
          </Link>
        </div>

        {/* Chat preview mockup */}
        <div className="message-preview">
          <div className="message-head">
            <div className="chat-avatar">🤖</div>
            <div>
              <strong>Asisten Finansial</strong>
              <small>Online</small>
            </div>
          </div>
          <div className="chat-line outgoing">beli kopi 20rb sama parkir 5rb</div>
          <div className="chat-line incoming">
            <b>Tercatat! ✅</b><br />
            💸 Kopi — Rp20.000 [Makan &amp; Minum]<br />
            💸 Parkir — Rp5.000 [Transport]
          </div>
          <div className="chat-line outgoing">bulan ini aku habis berapa?</div>
          <div className="chat-line incoming">
            📊 Total pengeluaranmu bulan ini: <b>Rp4.250.000</b>. Paling banyak di Makan &amp; Minum (42%) 🍔
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="feature-section">
        <div className="section-heading">
          <h2>Bukan Sekadar Pencatat Keuangan</h2>
          <p>AI yang mengerti bahasa sehari-hari kamu, mencatat otomatis, dan memberikan insight cerdas.</p>
        </div>

        <div className="feature-grid">
          {features.map((feature, i) => (
            <div key={i} className="feature-card">
              <span>{feature.icon}</span>
              <h3>{feature.title}</h3>
              <p>{feature.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="public-cta">
        <h2>Mulai Catat Keuanganmu Sekarang</h2>
        <p>Gratis, tanpa kartu kredit, langsung bisa pakai via WhatsApp.</p>
        <Link href="/register" className="button" style={{ textDecoration: 'none', padding: '0 28px', minHeight: 46, fontSize: 14 }}>
          Daftar Gratis Sekarang 🚀
        </Link>
      </section>

      {/* Footer */}
      <footer>
        <p>© 2026 Asisten Finansial AI. Dibuat dengan <span>❤️</span> di Indonesia.</p>
      </footer>
    </div>
  );
}

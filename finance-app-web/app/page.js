import Link from 'next/link';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';

export default async function LandingPage() {
  // If user has an auth token cookie, redirect straight to dashboard
  const cookieStore = await cookies();
  const token = cookieStore.get('auth_token')?.value;
  if (token) {
    redirect('/dashboard');
  }

  return (
    <div className="min-h-screen" style={{ background: 'var(--color-bg-primary)' }}>
      {/* Navbar */}
      <nav className="fixed top-0 left-0 right-0 z-50 px-6 py-4 flex items-center justify-between"
        style={{ background: 'rgba(10, 14, 26, 0.8)', backdropFilter: 'blur(12px)' }}>
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg flex items-center justify-center text-lg"
            style={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)' }}>
            💰
          </div>
          <span className="text-lg font-bold text-white">Asisten Finansial AI</span>
        </div>
        <div className="flex items-center gap-4">
          <Link href="/login" className="text-sm text-gray-400 hover:text-white transition-colors font-medium no-underline">
            Login
          </Link>
          <Link href="/register" className="btn-primary text-sm no-underline">
            Daftar Gratis
          </Link>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="pt-32 pb-20 px-6 text-center relative overflow-hidden">
        {/* Background gradient orbs */}
        <div className="absolute top-20 left-1/4 w-96 h-96 rounded-full opacity-20 blur-3xl"
          style={{ background: 'radial-gradient(circle, #6366f1, transparent)' }} />
        <div className="absolute top-40 right-1/4 w-80 h-80 rounded-full opacity-15 blur-3xl"
          style={{ background: 'radial-gradient(circle, #8b5cf6, transparent)' }} />

        <div className="relative max-w-4xl mx-auto">
          <div className="badge-income text-sm mb-6 inline-block">🚀 Gratis untuk selamanya</div>
          <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight leading-tight mb-6">
            Catat Keuanganmu{' '}
            <span className="text-gradient">via WhatsApp</span>
          </h1>
          <p className="text-lg md:text-xl text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
            Cukup kirim pesan seperti <span className="text-white font-medium">&quot;isi bensin 200 ribu&quot;</span> ke WhatsApp,
            AI kami langsung mencatat. Tanpa install aplikasi baru, tanpa isi form.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link href="/register" className="btn-primary text-base px-8 py-3 no-underline">
              Mulai Gratis →
            </Link>
            <Link href="#features" className="btn-secondary text-base px-8 py-3 no-underline">
              Lihat Fitur
            </Link>
          </div>
        </div>

        {/* Chat preview mockup */}
        <div className="mt-16 max-w-md mx-auto relative">
          <div className="rounded-2xl p-6 border border-white/[0.06]"
            style={{ background: 'rgba(17, 24, 39, 0.7)' }}>
            <div className="flex items-center gap-3 mb-4 pb-3 border-b border-white/[0.06]">
              <div className="w-10 h-10 rounded-full flex items-center justify-center"
                style={{ background: 'linear-gradient(135deg, #6366f1, #8b5cf6)' }}>
                🤖
              </div>
              <div>
                <p className="text-sm font-semibold text-white">Asisten Finansial</p>
                <p className="text-xs text-emerald-400">Online</p>
              </div>
            </div>
            <div className="space-y-3">
              {/* User message */}
              <div className="flex justify-end">
                <div className="bg-indigo-500/20 text-white px-4 py-2 rounded-2xl rounded-br-md text-sm max-w-xs">
                  beli kopi 20rb sama parkir 5rb
                </div>
              </div>
              {/* Bot response */}
              <div className="flex justify-start">
                <div className="px-4 py-2 rounded-2xl rounded-bl-md text-sm max-w-xs"
                  style={{ background: 'var(--color-surface-1)' }}>
                  <p>Tercatat! ✅</p>
                  <p className="mt-1">💸 Kopi — Rp20.000 [Makan & Minum]</p>
                  <p>💸 Parkir — Rp5.000 [Transport]</p>
                </div>
              </div>
              {/* User query */}
              <div className="flex justify-end">
                <div className="bg-indigo-500/20 text-white px-4 py-2 rounded-2xl rounded-br-md text-sm max-w-xs">
                  bulan ini aku habis berapa?
                </div>
              </div>
              {/* Bot response */}
              <div className="flex justify-start">
                <div className="px-4 py-2 rounded-2xl rounded-bl-md text-sm max-w-xs"
                  style={{ background: 'var(--color-surface-1)' }}>
                  📊 Total pengeluaranmu bulan ini: Rp4.250.000. Paling banyak di Makan & Minum (42%) 🍔
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="py-20 px-6">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight mb-4">
              Bukan Sekadar Pencatat Keuangan
            </h2>
            <p className="text-gray-400 max-w-xl mx-auto">
              AI yang mengerti bahasa sehari-hari kamu, mencatat otomatis, dan memberikan insight cerdas.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[
              { icon: '💬', title: 'Input Natural Language', desc: 'Kirim "isi bensin 200 ribu" — langsung tercatat. Tanpa form, tanpa pilih kategori.' },
              { icon: '🧠', title: 'AI Auto-Kategori', desc: 'AI otomatis mendeteksi kategori transaksi. Kamu tinggal kirim pesan.' },
              { icon: '📊', title: 'Dashboard Analitik', desc: 'Grafik tren pengeluaran, breakdown kategori, dan prediksi saldo di web dashboard.' },
              { icon: '🔮', title: 'Prediksi Saldo', desc: '"Dengan pola saat ini, saldo diperkirakan habis tanggal 26."' },
              { icon: '💡', title: 'Saran Penghematan', desc: '"Jika mengurangi makan di luar 20%, kamu bisa menabung Rp500.000/bulan."' },
              { icon: '⏰', title: 'Pengingat Cerdas', desc: 'Mendeteksi pola pembayaran rutin dan mengingatkan kamu sebelum jatuh tempo.' },
            ].map((feature, i) => (
              <div key={i} className="glass-card group">
                <div className="text-3xl mb-4">{feature.icon}</div>
                <h3 className="text-lg font-bold mb-2">{feature.title}</h3>
                <p className="text-sm text-gray-400 leading-relaxed">{feature.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-20 px-6 text-center relative overflow-hidden">
        <div className="absolute inset-0 opacity-10"
          style={{ background: 'radial-gradient(ellipse at center, #6366f1, transparent 70%)' }} />
        <div className="relative max-w-2xl mx-auto">
          <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight mb-4">
            Mulai Catat Keuanganmu Sekarang
          </h2>
          <p className="text-gray-400 mb-8">
            Gratis, tanpa kartu kredit, langsung bisa pakai via WhatsApp.
          </p>
          <Link href="/register" className="btn-primary text-base px-10 py-4 no-underline">
            Daftar Gratis Sekarang 🚀
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-white/[0.06] py-8 px-6 text-center">
        <p className="text-sm text-gray-500">
          © 2026 Asisten Finansial AI. Dibuat dengan ❤️ di Indonesia.
        </p>
      </footer>
    </div>
  );
}

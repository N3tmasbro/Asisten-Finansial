'use client';

import { useState, useEffect } from 'react';
import { formatRupiah, formatPercent } from '../../lib/utils';
import api from '../../lib/api';

export default function DashboardPage() {
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('this_month');
  const [transactions, setTransactions] = useState([]);
  const [wallets, setWallets] = useState([]);
  const [summary, setSummary] = useState(null);

  useEffect(() => {
    fetchData();
  }, [period]);

  async function fetchData() {
    setLoading(true);
    try {
      const [txRes, walletRes, summaryRes] = await Promise.allSettled([
        api.getTransactions({ limit: 10, sort: 'latest' }),
        api.getWallets(),
        api.getSummary(period),
      ]);

      if (txRes.status === 'fulfilled') {
        const txData = txRes.value;
        setTransactions(Array.isArray(txData) ? txData : (txData.data || []));
      }
      if (walletRes.status === 'fulfilled') {
        const wData = walletRes.value;
        setWallets(Array.isArray(wData) ? wData : (wData.data || []));
      }
      if (summaryRes.status === 'fulfilled') {
        setSummary(summaryRes.value);
      }
    } catch (err) {
      console.error('Dashboard fetch error:', err);
    } finally {
      setLoading(false);
    }
  }

  const totalBalance = wallets.reduce((sum, w) => sum + (w.balance || 0), 0);
  const totalExpense = summary?.total_expense ?? transactions.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0);
  const totalIncome = summary?.total_income ?? transactions.filter(t => t.type === 'income').reduce((s, t) => s + t.amount, 0);
  const reviewCount = transactions.filter(t => !t.is_reviewed).length;

  // Build category breakdown from transactions if summary doesn't have it
  const categoryBreakdown = summary?.by_category || (() => {
    const cats = {};
    transactions.filter(t => t.type === 'expense').forEach(t => {
      const name = t.category?.name || 'Lainnya';
      const icon = t.category?.icon || '📦';
      if (!cats[name]) cats[name] = { category_name: name, category_icon: icon, total: 0 };
      cats[name].total += t.amount;
    });
    const list = Object.values(cats).sort((a, b) => b.total - a.total);
    const max = list[0]?.total || 1;
    return list.map(c => ({ ...c, percentage: Math.round((c.total / (totalExpense || 1)) * 100) }));
  })();

  if (loading) {
    return (
      <div className="animate-fade-in flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="text-4xl mb-3 animate-pulse">📊</div>
          <p className="text-gray-400">Memuat dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Dashboard</h1>
          <p className="text-gray-400 text-sm mt-1">Ringkasan keuangan kamu bulan ini</p>
        </div>
        <div className="flex gap-2">
          {['this_month', 'last_month', 'this_week'].map((p) => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150
                ${period === p
                  ? 'bg-indigo-500/20 text-indigo-400'
                  : 'text-gray-400 hover:text-white hover:bg-white/[0.03]'
                }`}
            >
              {p === 'this_month' ? 'Bulan Ini' : p === 'last_month' ? 'Bulan Lalu' : 'Minggu Ini'}
            </button>
          ))}
        </div>
      </div>

      {/* Onboarding Card — shown when no data yet */}
      {!loading && wallets.length === 0 && transactions.length === 0 && (
        <div className="mb-8 p-6 rounded-2xl border border-indigo-500/20 animate-slide-up"
          style={{ background: 'linear-gradient(135deg, rgba(99,102,241,0.05), rgba(139,92,246,0.05))' }}>
          <div className="flex items-start gap-4">
            <div className="text-4xl">🚀</div>
            <div>
              <h3 className="text-lg font-bold text-white mb-2">Selamat datang di Asisten Finansial!</h3>
              <p className="text-sm text-gray-400 mb-4 leading-relaxed">
                Belum ada data keuangan. Mulai dengan langkah-langkah berikut:
              </p>
              <div className="space-y-2">
                <div className="flex items-center gap-2 text-sm">
                  <span className="w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold">1</span>
                  <span className="text-gray-300">Buat dompet pertamamu di halaman <a href="/wallets" className="text-indigo-400 hover:text-indigo-300 font-medium">Dompet</a></span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <span className="w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold">2</span>
                  <span className="text-gray-300">Kirim pesan ke WhatsApp Bot, contoh: <span className="text-white font-medium">"Beli kopi 15rb"</span></span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <span className="w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold">3</span>
                  <span className="text-gray-300">Atau <a href="/transactions" className="text-indigo-400 hover:text-indigo-300 font-medium">tambah transaksi manual</a> dari web</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div className="stat-card accent">
          <p className="text-xs font-medium text-gray-400 mb-1">Total Saldo</p>
          <p className="text-2xl font-extrabold tracking-tight">{formatRupiah(totalBalance)}</p>
          <p className="text-xs text-gray-500 mt-2">{wallets.length} dompet aktif</p>
        </div>

        <div className="stat-card income">
          <p className="text-xs font-medium text-gray-400 mb-1">Pemasukan</p>
          <p className="text-2xl font-extrabold tracking-tight text-emerald-400">
            {formatRupiah(totalIncome)}
          </p>
          <p className="text-xs text-gray-500 mt-2">Bulan ini</p>
        </div>

        <div className="stat-card expense">
          <p className="text-xs font-medium text-gray-400 mb-1">Pengeluaran</p>
          <p className="text-2xl font-extrabold tracking-tight text-rose-400">
            {formatRupiah(totalExpense)}
          </p>
          <p className="text-xs text-gray-500 mt-2">Bulan ini</p>
        </div>

        <div className="stat-card warning">
          <p className="text-xs font-medium text-gray-400 mb-1">Transaksi</p>
          <p className="text-2xl font-extrabold tracking-tight">
            {transactions.length}
          </p>
          <p className="text-xs text-gray-500 mt-2">total tercatat</p>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Transactions */}
        <div className="lg:col-span-2 glass-card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold">Transaksi Terbaru</h2>
            <a href="/transactions" className="text-xs text-indigo-400 hover:text-indigo-300 font-medium">
              Lihat Semua →
            </a>
          </div>
          {transactions.length === 0 ? (
            <div className="py-8 text-center">
              <p className="text-gray-400 text-sm">Belum ada transaksi.</p>
              <p className="text-gray-500 text-xs mt-1">Kirim pesan ke WhatsApp untuk mencatat!</p>
            </div>
          ) : (
            <div className="space-y-3">
              {transactions.slice(0, 7).map((tx) => (
                <div key={tx.id}
                  className="flex items-center gap-3 p-3 rounded-xl hover:bg-white/[0.02] transition-all duration-150">
                  <div className="w-10 h-10 rounded-xl flex items-center justify-center text-lg"
                    style={{ background: tx.type === 'income' ? 'rgba(16,185,129,0.1)' : 'rgba(244,63,94,0.1)' }}>
                    {tx.category?.icon || '📦'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-white truncate">{tx.description}</p>
                    <p className="text-xs text-gray-500">{tx.category?.name} · {tx.wallet?.name}</p>
                  </div>
                  <div className="text-right">
                    <p className={`text-sm font-bold ${tx.type === 'income' ? 'text-emerald-400' : 'text-rose-400'}`}>
                      {tx.type === 'income' ? '+' : '-'}{formatRupiah(tx.amount)}
                    </p>
                    <p className="text-xs text-gray-500">{new Date(tx.transaction_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}</p>
                  </div>
                  {!tx.is_reviewed && (
                    <span className="badge-warning ml-1">⚠️</span>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Category Breakdown */}
        <div className="glass-card">
          <h2 className="text-lg font-bold mb-4">Pengeluaran per Kategori</h2>
          {categoryBreakdown.length === 0 ? (
            <p className="text-gray-400 text-sm text-center py-4">Belum ada data.</p>
          ) : (
            <div className="space-y-4">
              {categoryBreakdown.map((cat, i) => (
                <div key={i}>
                  <div className="flex items-center justify-between mb-1">
                    <div className="flex items-center gap-2">
                      <span>{cat.category_icon}</span>
                      <span className="text-sm font-medium">{cat.category_name}</span>
                    </div>
                    <span className="text-sm font-bold">{formatRupiah(cat.total)}</span>
                  </div>
                  <div className="progress-bar">
                    <div
                      className={`progress-fill ${cat.percentage > 80 ? 'danger' : cat.percentage > 50 ? 'warning' : ''}`}
                      style={{ width: `${cat.percentage}%` }}
                    />
                  </div>
                  <p className="text-xs text-gray-500 mt-0.5">{cat.percentage}% dari total</p>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Wallets Row */}
      <div className="mt-6">
        <h2 className="text-lg font-bold mb-4">Dompet</h2>
        {wallets.length === 0 ? (
          <p className="text-gray-400 text-sm">Belum ada dompet.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {wallets.map((wallet, i) => (
              <div key={wallet.id || i} className="glass-card">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 rounded-xl flex items-center justify-center text-lg"
                    style={{ background: wallet.type === 'cash' ? 'rgba(16,185,129,0.1)' : wallet.type === 'bank' ? 'rgba(59,130,246,0.1)' : 'rgba(168,85,247,0.1)' }}>
                    {wallet.type === 'cash' ? '💵' : wallet.type === 'bank' ? '🏦' : '📱'}
                  </div>
                  <div>
                    <p className="text-sm font-medium text-white">{wallet.name}</p>
                    <p className="text-xs text-gray-500 capitalize">{wallet.type}</p>
                  </div>
                </div>
                <p className={`text-xl font-extrabold tracking-tight ${wallet.balance < 0 ? 'text-rose-400' : ''}`}>
                  {formatRupiah(wallet.balance)}
                </p>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Review Alert */}
      {reviewCount > 0 && (
        <div className="mt-6 p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 flex items-center gap-3 animate-slide-up">
          <span className="text-2xl">⚠️</span>
          <div>
            <p className="text-sm font-semibold text-amber-400">
              {reviewCount} transaksi perlu direview
            </p>
            <p className="text-xs text-gray-400">
              Beberapa transaksi dari AI punya confidence rendah. Cek di halaman transaksi.
            </p>
          </div>
          <a href="/transactions?needs_review=true"
            className="ml-auto btn-secondary text-xs">
            Review →
          </a>
        </div>
      )}
    </div>
  );
}

'use client';

import { useState, useEffect, useRef } from 'react';
import { formatRupiah, formatPercent } from '../../lib/utils';
import api from '../../lib/api';
import { useAuth } from '../../contexts/AuthContext';
import { getCategoryStyle } from '../../lib/categoryColors';
import StatCard from '../../components/StatCard';

export default function DashboardPage() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('this_month');
  const [transactions, setTransactions] = useState([]);
  const [wallets, setWallets] = useState([]);
  const [summary, setSummary] = useState(null);

  const isFetching = useRef(false);

  useEffect(() => {
    fetchData();
  }, [period]);

  async function fetchData() {
    if (isFetching.current) return;
    isFetching.current = true;
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
        setWallets(wData.wallets || wData.data || (Array.isArray(wData) ? wData : []));
      }
      if (summaryRes.status === 'fulfilled') {
        const sData = summaryRes.value;
        setSummary(sData.summary || sData);
      }
    } catch (err) {
      console.error('Dashboard fetch error:', err);
    } finally {
      setLoading(false);
      isFetching.current = false;
    }
  }

  const totalBalance = wallets.reduce((sum, w) => sum + (w.balance || 0), 0);
  const totalExpense = summary?.total_expense ?? transactions.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0);
  const totalIncome = summary?.total_income ?? transactions.filter(t => t.type === 'income').reduce((s, t) => s + t.amount, 0);
  const reviewCount = transactions.filter(t => !t.is_reviewed).length;

  const phoneVerified = user?.phone_verified || user?.is_phone_verified || false;

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

  const filters = [
    { key: 'this_month', label: 'Bulan Ini' },
    { key: 'last_month', label: 'Bulan Lalu' },
    { key: 'this_week', label: 'Minggu Ini' },
  ];

  const walletColors = {
    cash: 'var(--cat-food-bg)',
    bank: 'var(--accent-teal-bg)',
    ewallet: 'var(--cat-transport-bg)',
    savings: 'var(--cat-bonus-bg)',
  };
  const walletIcons = { cash: '💵', bank: '🏦', ewallet: '📱', savings: '🏧' };

  if (loading) {
    return (
      <div style={{ padding: 32, display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 400 }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">📊</div>
          <p style={{ color: 'var(--text-secondary)' }}>Memuat dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-8 min-h-screen bg-[var(--bg-base)] w-full">
      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-start justify-between mb-7 gap-4">
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, lineHeight: 1.2, letterSpacing: '-0.01em' }}>
            Dashboard
          </h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>
            Visualisasi dan insight dari transaksi yang kamu catat lewat WhatsApp
          </p>
        </div>
        <div style={{ display: 'flex', gap: 6 }}>
          {filters.map(f => (
            <button
              key={f.key}
              onClick={() => setPeriod(f.key)}
              style={{
                padding: '7px 14px', borderRadius: 8, border: '1px solid var(--border)',
                background: period === f.key ? 'var(--color-accent)' : 'var(--bg-card)',
                color: period === f.key ? 'var(--color-accent-text)' : 'var(--text-secondary)',
                fontSize: 13, fontWeight: period === f.key ? 600 : 400,
                cursor: 'pointer', transition: 'all 0.15s',
              }}
            >{f.label}</button>
          ))}
        </div>
      </div>

      {/* WhatsApp Banner */}
      <div className="whatsapp-primary-banner">
        <div className="whatsapp-primary-icon">💬</div>
        <div><strong>Catat transaksi dari WhatsApp</strong><span>Kirim "beli kopi 20rb dan parkir 5rb" — AI akan memisahkan dan mencatat keduanya otomatis.</span></div>
        <div className="whatsapp-primary-tag">Interface utama</div>
      </div>

      {/* WhatsApp Verification Warning Banner */}
      {user && !phoneVerified && (
        <div style={{
          marginBottom: 22, padding: '14px 16px', borderRadius: 10,
          border: '1px solid var(--accent-amber-bg)', backgroundColor: 'var(--accent-amber-bg)',
          display: 'flex', alignItems: 'center', gap: 12,
        }}>
          <span style={{ fontSize: 20 }}>🔐</span>
          <div style={{ flex: 1 }}>
            <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--accent-amber)', margin: 0 }}>
              WhatsApp Belum Terverifikasi
            </p>
            <p style={{ fontSize: 12, color: 'var(--text-secondary)', margin: '2px 0 0' }}>
              Hubungkan nomor WhatsApp kamu agar bot dapat mengenali pesanmu dan mencatat transaksi secara otomatis.
            </p>
          </div>
          <a href="/settings" style={{
            padding: '7px 16px', borderRadius: 8, border: 'none',
            background: 'var(--color-accent)', color: 'var(--color-accent-text)',
            fontSize: 13, fontWeight: 600, textDecoration: 'none', whiteSpace: 'nowrap',
          }}>
            Verifikasi Sekarang →
          </a>
        </div>
      )}

      {/* Onboarding Card — shown when no data yet */}
      {!loading && wallets.length === 0 && transactions.length === 0 && (
        <div className="card" style={{
          marginBottom: 22, padding: 24, borderLeft: '3px solid var(--teal)',
        }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
            <div style={{ fontSize: 32 }}>🚀</div>
            <div>
              <h3 style={{ fontSize: 16, fontWeight: 700, color: 'var(--text-primary)', margin: '0 0 8px' }}>Selamat datang di Asisten Finansial!</h3>
              <p style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 12 }}>
                Belum ada data keuangan. Mulai dengan langkah-langkah berikut:
              </p>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                  <span style={{ width: 22, height: 22, borderRadius: '50%', backgroundColor: 'var(--accent-teal-bg)', color: 'var(--accent-teal)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700 }}>1</span>
                  <span style={{ color: 'var(--text-primary)' }}>Buat dompet pertamamu di halaman <a href="/wallets" style={{ color: 'var(--teal)', fontWeight: 600 }}>Dompet</a></span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                  <span style={{ width: 22, height: 22, borderRadius: '50%', backgroundColor: 'var(--accent-teal-bg)', color: 'var(--accent-teal)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700 }}>2</span>
                  <span style={{ color: 'var(--text-primary)' }}>Kirim pesan ke WhatsApp Bot, contoh: <strong>"Beli kopi 15rb"</strong></span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                  <span style={{ width: 22, height: 22, borderRadius: '50%', backgroundColor: 'var(--accent-teal-bg)', color: 'var(--accent-teal)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700 }}>3</span>
                  <span style={{ color: 'var(--text-primary)' }}>Atau <a href="/transactions" style={{ color: 'var(--teal)', fontWeight: 600 }}>tambah transaksi manual</a> dari web</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <StatCard title="Total Saldo" value={formatRupiah(totalBalance)} subtitle={`${wallets.length} dompet aktif`} color="teal" icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 12V22H4V12" /><path d="M22 7H2v5h20V7z" /><path d="M12 22V7" />
            <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" /><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" />
          </svg>
        } />
        <StatCard title="Pemasukan" value={formatRupiah(totalIncome)} subtitle="Bulan ini" color="green" icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="12" y1="19" x2="12" y2="5" /><polyline points="5 12 12 5 19 12" />
          </svg>
        } />
        <StatCard title="Pengeluaran" value={formatRupiah(totalExpense)} subtitle="Bulan ini" color="red" icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" />
          </svg>
        } />
        <StatCard title="Transaksi" value={String(transactions.length)} subtitle="total tercatat" color="amber" icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" />
            <line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />
          </svg>
        } />
      </div>

      {/* Recent Transactions + Category Breakdown */}
      <div className="flex flex-col lg:flex-row gap-4 mb-7">
        {/* Recent Transactions */}
        <div className="card p-6 w-full lg:w-3/5">
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>Transaksi Terbaru</h2>
            <a href="/transactions" style={{ background: 'none', border: 'none', color: 'var(--color-accent)', fontSize: 13, fontWeight: 600, textDecoration: 'none' }}>
              Lihat Semua →
            </a>
          </div>
          {transactions.length === 0 ? (
            <div style={{ padding: '32px 0', textAlign: 'center' }}>
              <p style={{ color: 'var(--text-secondary)', fontSize: 14 }}>Belum ada transaksi.</p>
              <p style={{ color: 'var(--text-tertiary)', fontSize: 12, marginTop: 4 }}>Kirim pesan ke WhatsApp untuk mencatat!</p>
            </div>
          ) : (
            <div>
              {transactions.slice(0, 7).map((tx) => {
                const cat = getCategoryStyle(tx.category?.name || 'Lainnya');
                return (
                  <div
                    key={tx.id}
                    style={{
                      display: 'flex', alignItems: 'center', gap: 12,
                      padding: '10px 8px', borderRadius: 8, minHeight: 56,
                      borderBottom: '1px solid var(--border-subtle)',
                      transition: 'background 0.15s',
                    }}
                    onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-hover)'}
                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                  >
                    <div style={{
                      width: 36, height: 36, borderRadius: 8, fontSize: 18,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      backgroundColor: cat.bg, flexShrink: 0,
                    }}>
                      {tx.category?.icon || '📦'}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 14, fontWeight: 500, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {tx.description}
                      </div>
                      <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{tx.category?.name || '-'} · {tx.wallet?.name || '-'}</div>
                    </div>
                    <div style={{ textAlign: 'right', flexShrink: 0 }}>
                      <div style={{ fontSize: 14, fontWeight: 600, color: tx.type === 'income' ? 'var(--color-income)' : 'var(--color-expense)' }}>
                        {tx.type === 'income' ? '+' : '-'}{formatRupiah(tx.amount)}
                      </div>
                      <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                        {new Date(tx.transaction_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })}
                      </div>
                    </div>
                    {!tx.is_reviewed && (
                      <span className="badge-warning" style={{ marginLeft: 4 }}>⚠️</span>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Category Breakdown */}
        <div className="card p-6 w-full lg:w-2/5">
          <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>
            Pengeluaran per Kategori
          </h2>
          {categoryBreakdown.length === 0 ? (
            <p style={{ color: 'var(--text-secondary)', fontSize: 14, textAlign: 'center', padding: '16px 0' }}>Belum ada data.</p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              {categoryBreakdown.map((cat, i) => {
                const style = getCategoryStyle(cat.category_name);
                return (
                  <div key={i}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4, alignItems: 'center' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                        <span style={{ fontSize: 13 }}>{cat.category_icon}</span>
                        <span style={{ fontSize: 13, fontWeight: 500, color: 'var(--text-primary)' }}>{cat.category_name}</span>
                      </div>
                      <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{formatRupiah(cat.total)}</span>
                    </div>
                    <div style={{ height: 5, backgroundColor: 'var(--border)', borderRadius: 3, overflow: 'hidden', marginBottom: 2 }}>
                      <div style={{ height: '100%', width: `${cat.percentage}%`, backgroundColor: style.icon, borderRadius: 3, transition: 'width 0.5s ease' }} />
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--text-tertiary)' }}>{cat.percentage}% dari total</div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Wallets */}
      <div>
        <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 14px' }}>Dompet</h2>
        {wallets.length === 0 ? (
          <p style={{ color: 'var(--text-secondary)', fontSize: 14 }}>Belum ada dompet.</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {wallets.map((wallet, i) => (
              <div key={wallet.id || i} className="card" style={{ padding: 20, transition: 'box-shadow 0.2s, transform 0.2s' }}
                onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = 'var(--shadow-md)'; }}
                onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14 }}>
                  <div style={{
                    width: 34, height: 34, borderRadius: 8,
                    backgroundColor: walletColors[wallet.type] || walletColors.cash,
                    display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, fontSize: 18,
                  }}>
                    {walletIcons[wallet.type] || '💳'}
                  </div>
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{wallet.name}</div>
                    <div style={{ fontSize: 12, color: 'var(--text-tertiary)', textTransform: 'capitalize' }}>{wallet.type === 'ewallet' ? 'E-wallet' : wallet.type}</div>
                  </div>
                </div>
                <div className="font-poppins" style={{ fontSize: 20, fontWeight: 700, color: wallet.balance < 0 ? 'var(--color-expense)' : 'var(--text-primary)', letterSpacing: '-0.01em' }}>
                  {formatRupiah(wallet.balance || 0)}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Review Alert */}
      {reviewCount > 0 && (
        <div style={{
          marginTop: 24, padding: '14px 16px', borderRadius: 10,
          border: '1px solid var(--accent-amber-bg)', backgroundColor: 'var(--accent-amber-bg)',
          display: 'flex', alignItems: 'center', gap: 12,
        }}>
          <span style={{ fontSize: 20 }}>⚠️</span>
          <div style={{ flex: 1 }}>
            <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--accent-amber)', margin: 0 }}>
              {reviewCount} transaksi perlu direview
            </p>
            <p style={{ fontSize: 12, color: 'var(--text-secondary)', margin: '2px 0 0' }}>
              Beberapa transaksi dari AI punya confidence rendah. Cek di halaman transaksi.
            </p>
          </div>
          <a href="/transactions?needs_review=true" style={{
            padding: '7px 14px', borderRadius: 8, border: '1px solid var(--border)',
            background: 'var(--bg-card)', color: 'var(--text-secondary)', fontSize: 13, fontWeight: 600, textDecoration: 'none',
          }}>
            Review →
          </a>
        </div>
      )}
    </div>
  );
}

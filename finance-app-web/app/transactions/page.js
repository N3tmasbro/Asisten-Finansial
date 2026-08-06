'use client';

import { useState, useEffect } from 'react';
import { formatRupiah, formatDate } from '../../lib/utils';
import api from '../../lib/api';

export default function TransactionsPage() {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    fetchTransactions();
  }, []);

  async function fetchTransactions() {
    setLoading(true);
    try {
      const res = await api.getTransactions({ limit: 50, sort: 'latest' });
      const txData = Array.isArray(res) ? res : (res.data || []);
      setTransactions(txData);
    } catch (err) {
      console.error('Failed to fetch transactions:', err);
    } finally {
      setLoading(false);
    }
  }

  const filtered = transactions.filter((tx) => {
    if (filter === 'expense' && tx.type !== 'expense') return false;
    if (filter === 'income' && tx.type !== 'income') return false;
    if (filter === 'review' && tx.is_reviewed) return false;
    if (searchQuery && !tx.description.toLowerCase().includes(searchQuery.toLowerCase())) return false;
    return true;
  });

  if (loading) {
    return (
      <div className="animate-fade-in flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="text-4xl mb-3 animate-pulse">💸</div>
          <p className="text-gray-400">Memuat transaksi...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Transaksi</h1>
          <p className="text-gray-400 text-sm mt-1">Riwayat lengkap semua transaksi kamu</p>
        </div>
        <button className="btn-primary" onClick={fetchTransactions}>🔄 Refresh</button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <div className="flex rounded-lg overflow-hidden border border-white/[0.06]"
          style={{ background: 'var(--color-surface-1)' }}>
          {[
            { key: 'all', label: 'Semua' },
            { key: 'expense', label: 'Pengeluaran' },
            { key: 'income', label: 'Pemasukan' },
            { key: 'review', label: '⚠️ Perlu Review' },
          ].map((f) => (
            <button
              key={f.key}
              onClick={() => setFilter(f.key)}
              className={`px-4 py-2 text-xs font-semibold transition-all duration-150
                ${filter === f.key
                  ? 'bg-indigo-500/20 text-indigo-400'
                  : 'text-gray-400 hover:text-white'
                }`}
            >
              {f.label}
            </button>
          ))}
        </div>
        <input
          type="text"
          placeholder="🔍 Cari transaksi..."
          className="input-field max-w-xs text-sm"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
        />
      </div>

      {/* Transaction List */}
      <div className="glass-card p-0 overflow-hidden">
        <table className="w-full">
          <thead>
            <tr style={{ background: 'var(--color-surface-1)' }}>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Transaksi</th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Kategori</th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Dompet</th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Tanggal</th>
              <th className="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Jumlah</th>
              <th className="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((tx) => (
              <tr key={tx.id} className="border-t border-white/[0.04] hover:bg-white/[0.02] transition-all duration-150 cursor-pointer">
                <td className="px-5 py-4">
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-lg flex items-center justify-center text-base"
                      style={{ background: tx.type === 'income' ? 'rgba(16,185,129,0.1)' : 'rgba(244,63,94,0.1)' }}>
                      {tx.category?.icon || '📦'}
                    </div>
                    <div>
                      <span className="text-sm font-medium text-white">{tx.description}</span>
                      {tx.raw_input && tx.raw_input !== tx.description && (
                        <p className="text-xs text-gray-500 mt-0.5">"{tx.raw_input}"</p>
                      )}
                    </div>
                  </div>
                </td>
                <td className="px-5 py-4">
                  <span className="text-sm text-gray-400">{tx.category?.name || '-'}</span>
                </td>
                <td className="px-5 py-4">
                  <span className="text-sm text-gray-400">{tx.wallet?.name || '-'}</span>
                </td>
                <td className="px-5 py-4">
                  <span className="text-sm text-gray-400">{formatDate(tx.transaction_date)}</span>
                </td>
                <td className="px-5 py-4 text-right">
                  <span className={`text-sm font-bold ${tx.type === 'income' ? 'text-emerald-400' : 'text-rose-400'}`}>
                    {tx.type === 'income' ? '+' : '-'}{formatRupiah(tx.amount)}
                  </span>
                </td>
                <td className="px-5 py-4 text-center">
                  {!tx.is_reviewed ? (
                    <span className="badge-warning">⚠️ Review</span>
                  ) : (
                    <span className="text-xs text-gray-500">
                      {tx.ai_confidence ? `${Math.round(tx.ai_confidence * 100)}%` : '✓'}
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {filtered.length === 0 && (
          <div className="py-12 text-center">
            <p className="text-gray-400">{transactions.length === 0 ? 'Belum ada transaksi. Kirim pesan ke WhatsApp untuk mulai!' : 'Tidak ada transaksi ditemukan.'}</p>
          </div>
        )}
      </div>
    </div>
  );
}

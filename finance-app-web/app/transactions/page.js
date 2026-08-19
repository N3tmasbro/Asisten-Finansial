'use client';

import { useState, useEffect } from 'react';
import { formatRupiah, formatDate } from '../../lib/utils';
import api from '../../lib/api';

export default function TransactionsPage() {
  const [transactions, setTransactions] = useState([]);
  const [categories, setCategories] = useState([]);
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [walletFilter, setWalletFilter] = useState('');
  const [periodFilter, setPeriodFilter] = useState('all');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sortKey, setSortKey] = useState('date');
  const [sortOrder, setSortOrder] = useState('desc');
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(15);

  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [form, setForm] = useState({
    description: '',
    amount: '',
    type: 'expense',
    category_id: '',
    wallet_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
  });

  useEffect(() => {
    fetchAll();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [filter, categoryFilter, walletFilter, periodFilter, searchQuery, dateFrom, dateTo]);

  async function fetchAll() {
    setLoading(true);
    try {
      const [txRes, catRes, walletRes] = await Promise.allSettled([
        api.getTransactions({ per_page: 200, sort: 'latest' }),
        api.getCategories(),
        api.getWallets(),
      ]);
      if (txRes.status === 'fulfilled') {
        const txData = txRes.value;
        setTransactions(Array.isArray(txData) ? txData : (txData.data || []));
      }
      if (catRes.status === 'fulfilled') {
        const cats = catRes.value;
        setCategories(cats.categories || cats || []);
      }
      if (walletRes.status === 'fulfilled') {
        const wals = walletRes.value;
        setWallets(wals.wallets || wals || []);
      }
    } catch (err) {
      console.error('Failed to fetch:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.createTransaction({
        description: form.description,
        amount: Number(form.amount),
        type: form.type,
        category_id: form.category_id ? Number(form.category_id) : undefined,
        wallet_id: form.wallet_id ? Number(form.wallet_id) : undefined,
        transaction_date: form.transaction_date,
      });
      showMessage('Transaksi berhasil ditambahkan!');
      setForm({
        description: '',
        amount: '',
        type: 'expense',
        category_id: '',
        wallet_id: '',
        transaction_date: new Date().toISOString().split('T')[0],
      });
      setShowForm(false);
      fetchAll();
    } catch (err) {
      setError(err.message || 'Gagal menambah transaksi.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id) {
    if (!confirm('Yakin ingin menghapus transaksi ini?')) return;
    setDeleting(id);
    try {
      await api.deleteTransaction(id);
      showMessage('Transaksi berhasil dihapus.');
      fetchAll();
    } catch (err) {
      setError(err.message || 'Gagal menghapus transaksi.');
    } finally {
      setDeleting(null);
    }
  }

  function handleSort(key) {
    if (sortKey === key) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortKey(key);
      setSortOrder('desc');
    }
  }

  function showMessage(msg) {
    setSuccess(msg);
    setTimeout(() => setSuccess(''), 3000);
  }

  const filteredCategories = categories.filter(c => c.type === form.type);

  // Apply filters
  const filtered = transactions.filter((tx) => {
    if (filter === 'expense' && tx.type !== 'expense') return false;
    if (filter === 'income' && tx.type !== 'income') return false;
    if (filter === 'review' && tx.is_reviewed) return false;
    if (categoryFilter && Number(tx.category_id) !== Number(categoryFilter)) return false;
    if (walletFilter && Number(tx.wallet_id) !== Number(walletFilter)) return false;

    // Period filter
    if (periodFilter === 'this_month') {
      const now = new Date();
      const y = now.getFullYear();
      const m = String(now.getMonth() + 1).padStart(2, '0');
      const prefix = `${y}-${m}`;
      if (!tx.transaction_date?.startsWith(prefix)) return false;
    } else if (periodFilter === 'last_month') {
      const now = new Date();
      const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      const y = prev.getFullYear();
      const m = String(prev.getMonth() + 1).padStart(2, '0');
      const prefix = `${y}-${m}`;
      if (!tx.transaction_date?.startsWith(prefix)) return false;
    } else if (periodFilter === 'custom') {
      if (dateFrom && tx.transaction_date < dateFrom) return false;
      if (dateTo && tx.transaction_date > dateTo) return false;
    }

    if (searchQuery && !tx.description.toLowerCase().includes(searchQuery.toLowerCase()) &&
        !(tx.raw_input && tx.raw_input.toLowerCase().includes(searchQuery.toLowerCase()))) return false;
    return true;
  });

  // Apply sorting
  const sorted = [...filtered].sort((a, b) => {
    let aVal, bVal;
    switch (sortKey) {
      case 'description':
        aVal = (a.description || '').toLowerCase();
        bVal = (b.description || '').toLowerCase();
        break;
      case 'category':
        aVal = (a.category?.name || '').toLowerCase();
        bVal = (b.category?.name || '').toLowerCase();
        break;
      case 'wallet':
        aVal = (a.wallet?.name || '').toLowerCase();
        bVal = (b.wallet?.name || '').toLowerCase();
        break;
      case 'date':
        aVal = new Date(a.transaction_date).getTime();
        bVal = new Date(b.transaction_date).getTime();
        break;
      case 'amount':
        aVal = a.type === 'income' ? a.amount : -a.amount;
        bVal = b.type === 'income' ? b.amount : -b.amount;
        break;
      default:
        return 0;
    }
    if (aVal < bVal) return sortOrder === 'asc' ? -1 : 1;
    if (aVal > bVal) return sortOrder === 'asc' ? 1 : -1;
    return 0;
  });

  const totalItems = sorted.length;
  const totalPages = Math.ceil(totalItems / perPage) || 1;
  const paginated = sorted.slice((currentPage - 1) * perPage, currentPage * perPage);

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
        <div className="flex gap-2">
          <button className="btn-secondary" onClick={fetchAll}>🔄 Refresh</button>
          <button className="btn-primary" onClick={() => { setShowForm(!showForm); setError(''); }}>
            + Tambah Manual
          </button>
        </div>
      </div>

      {success && (
        <div className="p-3 rounded-lg text-sm text-emerald-400 bg-emerald-400/10 border border-emerald-400/20 mb-4 animate-slide-up">
          {success}
        </div>
      )}

      {/* Add Transaction Form */}
      {showForm && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Tambah Transaksi Manual</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Deskripsi</label>
                <input type="text" className="input-field" placeholder="Makan siang, bensin, dll."
                  value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} required />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Jumlah (Rp)</label>
                <input type="number" className="input-field" placeholder="50000"
                  value={form.amount} onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Tipe</label>
                <select className="input-field" value={form.type}
                  onChange={e => setForm({ ...form, type: e.target.value, category_id: '' })}>
                  <option value="expense">Pengeluaran</option>
                  <option value="income">Pemasukan</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Kategori</label>
                <select className="input-field" value={form.category_id}
                  onChange={e => setForm({ ...form, category_id: e.target.value })}>
                  <option value="">Pilih kategori...</option>
                  {filteredCategories.map(c => (
                    <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Dompet</label>
                <select className="input-field" value={form.wallet_id}
                  onChange={e => setForm({ ...form, wallet_id: e.target.value })}>
                  <option value="">Pilih dompet...</option>
                  {wallets.map(w => (
                    <option key={w.id} value={w.id}>{w.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Tanggal</label>
                <input type="date" className="input-field"
                  value={form.transaction_date} onChange={e => setForm({ ...form, transaction_date: e.target.value })} />
              </div>
            </div>
            <div className="flex gap-2 mt-4">
              <button type="submit" disabled={saving} className="btn-primary text-sm">
                {saving ? 'Menyimpan...' : 'Simpan Transaksi'}
              </button>
              <button type="button" className="btn-secondary text-sm" onClick={() => setShowForm(false)}>Batal</button>
            </div>
          </form>
        </div>
      )}

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

        {/* Period / Time Filter */}
        <select
          className="input-field max-w-xs text-sm py-2 font-medium"
          value={periodFilter}
          onChange={(e) => setPeriodFilter(e.target.value)}
        >
          <option value="all">📅 Semua Waktu</option>
          <option value="this_month">📅 Bulan Ini (Agustus 2026)</option>
          <option value="last_month">📅 Bulan Lalu (Juli 2026)</option>
          <option value="custom">📅 Custom Tanggal...</option>
        </select>

        {periodFilter === 'custom' && (
          <div className="flex items-center gap-2">
            <input
              type="date"
              className="input-field text-xs py-1.5"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
            />
            <span className="text-gray-500 text-xs">s/d</span>
            <input
              type="date"
              className="input-field text-xs py-1.5"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
            />
          </div>
        )}

        {/* Category Filter */}
        <select
          className="input-field max-w-xs text-sm py-2"
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
        >
          <option value="">Semua Kategori</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
          ))}
        </select>

        {/* Wallet Filter */}
        <select
          className="input-field max-w-xs text-sm py-2"
          value={walletFilter}
          onChange={(e) => setWalletFilter(e.target.value)}
        >
          <option value="">Semua Dompet</option>
          {wallets.map((w) => (
            <option key={w.id} value={w.id}>{w.name}</option>
          ))}
        </select>

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
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-white transition-colors"
                onClick={() => handleSort('description')}>
                Transaksi {sortKey === 'description' && (sortOrder === 'asc' ? '▲' : '▼')}
              </th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-white transition-colors"
                onClick={() => handleSort('category')}>
                Kategori {sortKey === 'category' && (sortOrder === 'asc' ? '▲' : '▼')}
              </th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-white transition-colors"
                onClick={() => handleSort('wallet')}>
                Dompet {sortKey === 'wallet' && (sortOrder === 'asc' ? '▲' : '▼')}
              </th>
              <th className="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-white transition-colors"
                onClick={() => handleSort('date')}>
                Tanggal {sortKey === 'date' && (sortOrder === 'asc' ? '▲' : '▼')}
              </th>
              <th className="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-white transition-colors"
                onClick={() => handleSort('amount')}>
                Jumlah {sortKey === 'amount' && (sortOrder === 'asc' ? '▲' : '▼')}
              </th>
              <th className="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider select-none">
                Status
              </th>
              <th className="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider w-16"></th>
            </tr>
          </thead>
          <tbody>
            {paginated.map((tx) => (
              <tr key={tx.id} className="border-t border-white/[0.04] hover:bg-white/[0.02] transition-all duration-150">
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
                <td className="px-5 py-4 text-center">
                  <button
                    onClick={() => handleDelete(tx.id)}
                    disabled={deleting === tx.id}
                    className="text-xs text-rose-400/50 hover:text-rose-400 transition-colors"
                    title="Hapus transaksi"
                  >
                    {deleting === tx.id ? '...' : '🗑️'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {sorted.length === 0 && (
          <div className="py-12 text-center">
            <p className="text-gray-400">{transactions.length === 0 ? 'Belum ada transaksi. Kirim pesan ke WhatsApp untuk mulai!' : 'Tidak ada transaksi ditemukan.'}</p>
          </div>
        )}

        {/* Pagination Footer Controls */}
        {totalItems > 0 && (
          <div className="flex flex-wrap items-center justify-between px-5 py-4 border-t border-white/[0.06] bg-white/[0.01] gap-4">
            <div className="flex items-center gap-3">
              <p className="text-xs text-gray-400">
                Menampilkan <span className="font-semibold text-white">{Math.min((currentPage - 1) * perPage + 1, totalItems)}</span> - <span className="font-semibold text-white">{Math.min(currentPage * perPage, totalItems)}</span> dari <span className="font-semibold text-white">{totalItems}</span> transaksi
              </p>
              <select
                className="input-field text-xs py-1 px-2 w-auto"
                value={perPage}
                onChange={(e) => {
                  setPerPage(Number(e.target.value));
                  setCurrentPage(1);
                }}
              >
                <option value={10}>10 per hal</option>
                <option value={15}>15 per hal</option>
                <option value={25}>25 per hal</option>
                <option value={50}>50 per hal</option>
              </select>
            </div>

            {totalPages > 1 && (
              <div className="flex items-center gap-1.5">
                <button
                  disabled={currentPage === 1}
                  onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                  className="btn-secondary py-1 px-3 text-xs disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  ◀ Sebelumnya
                </button>
                {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                  <button
                    key={page}
                    onClick={() => setCurrentPage(page)}
                    className={`w-7 h-7 rounded-lg text-xs font-semibold flex items-center justify-center transition-all ${
                      currentPage === page
                        ? 'bg-indigo-500 text-white font-bold shadow-lg shadow-indigo-500/20'
                        : 'text-gray-400 hover:bg-white/[0.05] hover:text-white'
                    }`}
                  >
                    {page}
                  </button>
                ))}
                <button
                  disabled={currentPage === totalPages}
                  onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                  className="btn-secondary py-1 px-3 text-xs disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  Selanjutnya ▶
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

'use client';

import { useState, useEffect } from 'react';
import { formatRupiah, formatDate } from '../../lib/utils';
import api from '../../lib/api';
import { getCategoryStyle } from '../../lib/categoryColors';
import ConfidenceBadge from '../../components/ConfidenceBadge';

export default function TransactionsPage() {
  const [transactions, setTransactions] = useState([]);
  const [categories, setCategories] = useState([]);
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  
  // Filtering & Sorting
  const [filter, setFilter] = useState('all'); // 'all' | 'expense' | 'income' | 'review'
  const [categoryFilter, setCategoryFilter] = useState('');
  const [walletFilter, setWalletFilter] = useState('');
  const [sortKey, setSortKey] = useState('date');
  const [sortOrder, setSortOrder] = useState('desc');
  const [searchQuery, setSearchQuery] = useState('');

  // Pagination states
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);

  // Time Period states
  const [timePeriod, setTimePeriod] = useState('all'); // 'all' | 'this_month' | 'last_month' | 'custom'
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  // Forms & Actions
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

  // Fetch static lookups once on mount
  useEffect(() => {
    fetchStaticData();
  }, []);

  // Fetch transactions when page/perPage changes
  useEffect(() => {
    fetchTransactions();
  }, [page, perPage]);

  // Reset to page 1 and fetch when filter/search changes
  useEffect(() => {
    if (page === 1) {
      fetchTransactions();
    } else {
      setPage(1);
    }
  }, [filter, categoryFilter, walletFilter, searchQuery, timePeriod, dateFrom, dateTo]);

  async function fetchStaticData() {
    try {
      const [catRes, walletRes] = await Promise.allSettled([
        api.getCategories(),
        api.getWallets(),
      ]);
      if (catRes.status === 'fulfilled') {
        const cats = catRes.value;
        setCategories(cats.categories || cats || []);
      }
      if (walletRes.status === 'fulfilled') {
        const wals = walletRes.value;
        setWallets(wals.wallets || wals || []);
      }
    } catch (err) {
      console.error('Failed to fetch static data:', err);
    }
  }

  async function fetchTransactions() {
    setLoading(true);
    try {
      const params = {
        page,
        per_page: perPage,
      };

      if (filter !== 'all') {
        if (filter === 'review') {
          params.needs_review = true;
        } else {
          params.type = filter;
        }
      }
      if (categoryFilter) params.category_id = categoryFilter;
      if (walletFilter) params.wallet_id = walletFilter;
      if (searchQuery) params.search = searchQuery;

      if (timePeriod === 'this_month') {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
        params.date_from = firstDay;
        params.date_to = lastDay;
      } else if (timePeriod === 'last_month') {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().split('T')[0];
        const lastDay = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().split('T')[0];
        params.date_from = firstDay;
        params.date_to = lastDay;
      } else if (timePeriod === 'custom') {
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
      }

      const txRes = await api.getTransactions(params);

      // Handle paginated structure from backend
      if (txRes && txRes.data) {
        setTransactions(txRes.data);
        setTotalPages(txRes.last_page || 1);
        setTotalItems(txRes.total || 0);
      } else {
        setTransactions(Array.isArray(txRes) ? txRes : []);
        setTotalPages(1);
        setTotalItems(Array.isArray(txRes) ? txRes.length : 0);
      }
    } catch (err) {
      console.error('Failed to fetch transactions:', err);
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
      fetchTransactions();
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
      fetchTransactions();
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

  // Client-side sorting for current page items
  const sorted = [...transactions].sort((a, b) => {
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

  return (
    <div className="p-4 md:p-8 w-full">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-7">
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Transaksi</h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Riwayat lengkap semua transaksi kamu</p>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          <button className="btn-secondary" onClick={fetchTransactions}>🔄 Refresh</button>
          <button className="btn-primary" onClick={() => { setShowForm(!showForm); setError(''); }}>
            + Tambah Manual
          </button>
        </div>
      </div>

      {success && (
        <div style={{ padding: 12, borderRadius: 10, fontSize: 14, color: 'var(--accent-green)', background: 'var(--accent-green-bg)', border: '1px solid var(--accent-green-bg)', marginBottom: 16 }}>
          {success}
        </div>
      )}

      {/* Add Transaction Form */}
      {showForm && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Tambah Transaksi Manual</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Deskripsi</label>
                <input type="text" className="input-field" placeholder="Makan siang, bensin, dll."
                  value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} required />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Jumlah (Rp)</label>
                <input type="number" className="input-field" placeholder="50000"
                  value={form.amount} onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Tipe</label>
                <select className="input-field" value={form.type}
                  onChange={e => setForm({ ...form, type: e.target.value, category_id: '' })}>
                  <option value="expense">Pengeluaran</option>
                  <option value="income">Pemasukan</option>
                </select>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Kategori</label>
                <select className="input-field" value={form.category_id}
                  onChange={e => setForm({ ...form, category_id: e.target.value })}>
                  <option value="">Pilih kategori...</option>
                  {filteredCategories.map(c => (
                    <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Dompet</label>
                <select className="input-field" value={form.wallet_id}
                  onChange={e => setForm({ ...form, wallet_id: e.target.value })}>
                  <option value="">Pilih dompet...</option>
                  {wallets.map(w => (
                    <option key={w.id} value={w.id}>{w.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Tanggal</label>
                <input type="date" className="input-field"
                  value={form.transaction_date} onChange={e => setForm({ ...form, transaction_date: e.target.value })} />
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>
                {saving ? 'Menyimpan...' : 'Simpan Transaksi'}
              </button>
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={() => setShowForm(false)}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {/* Filters Row 1: Tipe & Search */}
      <div className="flex flex-col md:flex-row flex-wrap md:items-center gap-3 mb-3">
        <div style={{ display: 'flex', borderRadius: 8, overflow: 'hidden', border: '1px solid var(--border)', background: 'var(--bg-card)' }} className="w-full md:w-auto overflow-x-auto">
          {[
            { key: 'all', label: 'Semua' },
            { key: 'expense', label: 'Pengeluaran' },
            { key: 'income', label: 'Pemasukan' },
            { key: 'review', label: '⚠️ Review' },
          ].map((f) => (
            <button
              key={f.key}
              onClick={() => setFilter(f.key)}
              style={{
                padding: '8px 16px', border: 'none', fontSize: 13, fontWeight: 600,
                background: filter === f.key ? 'var(--teal-bg)' : 'transparent',
                color: filter === f.key ? 'var(--teal)' : 'var(--text-secondary)',
                cursor: 'pointer', transition: 'all 0.15s',
              }}
            >{f.label}</button>
          ))}
        </div>

        <select className="input-field" value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}
          style={{ maxWidth: 180, fontSize: 13 }}>
          <option value="">Semua Kategori</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
          ))}
        </select>

        <select className="input-field" value={walletFilter} onChange={(e) => setWalletFilter(e.target.value)}
          style={{ maxWidth: 180, fontSize: 13 }}>
          <option value="">Semua Dompet</option>
          {wallets.map((w) => (
            <option key={w.id} value={w.id}>{w.name}</option>
          ))}
        </select>

        <input
          type="text"
          placeholder="🔍 Cari transaksi..."
          className="input-field"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          style={{ maxWidth: 200, fontSize: 13 }}
        />
      </div>

      {/* Filters Row 2: Time Period (BUG-008) */}
      <div className="flex flex-col md:flex-row flex-wrap md:items-center gap-3 mb-5">
        <div style={{ display: 'flex', borderRadius: 8, overflow: 'hidden', border: '1px solid var(--border)', background: 'var(--bg-card)' }} className="w-full md:w-auto overflow-x-auto">
          {[
            { key: 'all', label: 'Semua Waktu' },
            { key: 'this_month', label: 'Bulan Ini' },
            { key: 'last_month', label: 'Bulan Lalu' },
            { key: 'custom', label: 'Rentang Tanggal' },
          ].map((p) => (
            <button
              key={p.key}
              onClick={() => setTimePeriod(p.key)}
              style={{
                padding: '8px 16px', border: 'none', fontSize: 13, fontWeight: 600,
                background: timePeriod === p.key ? 'var(--teal-bg)' : 'transparent',
                color: timePeriod === p.key ? 'var(--teal)' : 'var(--text-secondary)',
                cursor: 'pointer', transition: 'all 0.15s',
              }}
            >{p.label}</button>
          ))}
        </div>

        {timePeriod === 'custom' && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <input
              type="date"
              className="input-field"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              style={{ maxWidth: 150, fontSize: 13 }}
            />
            <span style={{ fontSize: 13, color: 'var(--text-secondary)' }}>s/d</span>
            <input
              type="date"
              className="input-field"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              style={{ maxWidth: 150, fontSize: 13 }}
            />
          </div>
        )}
      </div>

      {/* Transaction Table */}
      <div className="card overflow-x-auto w-full">
        {loading ? (
          <div style={{ padding: 48, textAlign: 'center' }}>
            <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">💸</div>
            <p style={{ color: 'var(--text-secondary)', fontSize: 14 }}>Memuat transaksi...</p>
          </div>
        ) : (
          <>
            <table className="w-full border-collapse min-w-[800px]">
              <thead>
                <tr style={{ background: 'var(--bg-secondary)' }}>
                  {[
                    { key: 'description', label: 'Transaksi', align: 'left' },
                    { key: 'category', label: 'Kategori', align: 'left' },
                    { key: 'wallet', label: 'Dompet', align: 'left' },
                    { key: 'date', label: 'Tanggal', align: 'left' },
                    { key: 'amount', label: 'Jumlah', align: 'right' },
                  ].map(col => (
                    <th key={col.key}
                      onClick={() => handleSort(col.key)}
                      style={{
                        padding: '12px 20px', textAlign: col.align, fontSize: 12, fontWeight: 600,
                        color: 'var(--text-tertiary)', textTransform: 'uppercase', letterSpacing: '0.05em',
                        cursor: 'pointer', transition: 'color 0.15s', userSelect: 'none',
                      }}
                      onMouseEnter={e => e.currentTarget.style.color = 'var(--text-primary)'}
                      onMouseLeave={e => e.currentTarget.style.color = 'var(--text-tertiary)'}
                    >
                      {col.label} {sortKey === col.key && (sortOrder === 'asc' ? '▲' : '▼')}
                    </th>
                  ))}
                  <th style={{ padding: '12px 20px', textAlign: 'center', fontSize: 12, fontWeight: 600, color: 'var(--text-tertiary)', textTransform: 'uppercase' }}>Status</th>
                  <th style={{ width: 48 }}></th>
                </tr>
              </thead>
              <tbody>
                {sorted.map((tx) => {
                  const cat = getCategoryStyle(tx.category?.name || 'Lainnya');
                  return (
                    <tr key={tx.id} style={{ borderTop: '1px solid var(--border-subtle)', transition: 'background 0.15s' }}
                      onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-hover)'}
                      onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                    >
                      <td style={{ padding: '14px 20px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                          <div style={{
                            width: 36, height: 36, borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: 16, backgroundColor: cat.bg, flexShrink: 0,
                          }}>
                            {tx.category?.icon || '📦'}
                          </div>
                          <div>
                            <span style={{ fontSize: 14, fontWeight: 500, color: 'var(--text-primary)' }}>{tx.description}</span>
                            {tx.raw_input && tx.raw_input !== tx.description && (
                              <p style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: '2px 0 0' }}>"{tx.raw_input}"</p>
                            )}
                          </div>
                        </div>
                      </td>
                      <td style={{ padding: '14px 20px', fontSize: 14, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{tx.category?.name || '-'}</td>
                      <td style={{ padding: '14px 20px', fontSize: 14, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{tx.wallet?.name || '-'}</td>
                      <td style={{ padding: '14px 20px', fontSize: 14, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{formatDate(tx.transaction_date)}</td>
                      <td style={{ padding: '14px 20px', textAlign: 'right', whiteSpace: 'nowrap' }}>
                        <span style={{ fontSize: 14, fontWeight: 700, color: tx.type === 'income' ? 'var(--color-income)' : 'var(--color-expense)' }}>
                          {tx.type === 'income' ? '+' : '-'}{formatRupiah(tx.amount)}
                        </span>
                      </td>
                      <td style={{ padding: '14px 20px', textAlign: 'center' }}>
                        {!tx.is_reviewed ? (
                          <span className="badge-warning">⚠️ Review</span>
                        ) : (
                          tx.ai_confidence ? (
                            <ConfidenceBadge percentage={Math.round(tx.ai_confidence * 100)} />
                          ) : (
                            <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>✓</span>
                          )
                        )}
                      </td>
                      <td style={{ padding: '14px 20px', textAlign: 'center' }}>
                        <button
                          onClick={() => handleDelete(tx.id)}
                          disabled={deleting === tx.id}
                          style={{ background: 'none', border: 'none', fontSize: 14, color: 'var(--accent-red)', opacity: 0.5, cursor: 'pointer', transition: 'opacity 0.15s' }}
                          onMouseEnter={e => e.currentTarget.style.opacity = '1'}
                          onMouseLeave={e => e.currentTarget.style.opacity = '0.5'}
                          title="Hapus transaksi"
                        >
                          {deleting === tx.id ? '...' : '🗑️'}
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

            {sorted.length === 0 && (
              <div style={{ padding: 48, textAlign: 'center' }}>
                <p style={{ color: 'var(--text-secondary)' }}>{transactions.length === 0 ? 'Belum ada transaksi. Kirim pesan ke WhatsApp untuk mulai!' : 'Tidak ada transaksi ditemukan.'}</p>
              </div>
            )}

            {/* Pagination Controls */}
            {totalItems > 0 && (
              <div className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 md:px-5 md:py-4 bg-[var(--bg-secondary)] border-t border-[var(--border-subtle)]">
                <div className="flex items-center gap-2 text-[13px] text-[var(--text-secondary)] w-full md:w-auto justify-center md:justify-start">
                  <span>Tampilkan</span>
                  <select
                    value={perPage}
                    onChange={(e) => setPerPage(Number(e.target.value))}
                    className="px-2 py-1 rounded-md h-8 text-[13px] bg-white border border-[var(--border)] outline-none"
                  >
                    {[10, 15, 25, 50].map((n) => (
                      <option key={n} value={n}>{n}</option>
                    ))}
                  </select>
                </div>

                <div className="flex gap-2 items-center w-full md:w-auto justify-center md:justify-end overflow-x-auto pb-2 md:pb-0">
                  <button
                    type="button"
                    className="btn-secondary whitespace-nowrap"
                    onClick={() => setPage(p => Math.max(p - 1, 1))}
                    disabled={page === 1}
                    style={{ padding: '6px 12px', fontSize: 12, height: 32, opacity: page === 1 ? 0.5 : 1, cursor: page === 1 ? 'default' : 'pointer' }}
                  >
                    ◀
                  </button>
                  
                  {Array.from({ length: totalPages }, (_, i) => i + 1)
                    .filter(p => p === 1 || p === totalPages || Math.abs(p - page) <= 1)
                    .map((p, idx, arr) => {
                      const prev = arr[idx - 1];
                      const showDots = prev && p - prev > 1;

                      return (
                        <div key={p} className="flex gap-2">
                          {showDots && <span className="px-1 text-[var(--text-tertiary)]">...</span>}
                          <button
                            type="button"
                            onClick={() => setPage(p)}
                            style={{
                              minWidth: 32, height: 32, padding: '0 6px', borderRadius: 6,
                              border: p === page ? 'none' : '1px solid var(--border)',
                              background: p === page ? 'var(--color-accent)' : 'transparent',
                              color: p === page ? 'var(--color-accent-text)' : 'var(--text-secondary)',
                              fontSize: 12, fontWeight: 600, cursor: 'pointer',
                            }}
                          >
                            {p}
                          </button>
                        </div>
                      );
                    })}

                  <button
                    type="button"
                    className="btn-secondary whitespace-nowrap"
                    onClick={() => setPage(p => Math.min(p + 1, totalPages))}
                    disabled={page === totalPages}
                    style={{ padding: '6px 12px', fontSize: 12, height: 32, opacity: page === totalPages ? 0.5 : 1, cursor: page === totalPages ? 'default' : 'pointer' }}
                  >
                    ▶
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

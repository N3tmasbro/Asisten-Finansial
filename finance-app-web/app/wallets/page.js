'use client';

import { useState, useEffect } from 'react';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';

const walletIcons = { cash: '💵', bank: '🏦', ewallet: '📱', savings: '🏧' };
const walletColors = {
  cash: 'rgba(16,185,129,0.1)',
  bank: 'rgba(59,130,246,0.1)',
  ewallet: 'rgba(168,85,247,0.1)',
  savings: 'rgba(245,158,11,0.1)',
};

export default function WalletsPage() {
  const [wallets, setWallets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editWallet, setEditWallet] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [form, setForm] = useState({ name: '', type: 'cash', balance: '' });

  useEffect(() => {
    fetchWallets();
  }, []);

  async function fetchWallets() {
    setLoading(true);
    try {
      const data = await api.getWallets();
      setWallets(data.wallets || data || []);
    } catch (err) {
      console.error('Failed to fetch wallets:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.createWallet({
        name: form.name,
        type: form.type,
        balance: Number(form.balance) || 0,
      });
      showMessage('Dompet berhasil ditambahkan!');
      setForm({ name: '', type: 'cash', balance: '' });
      setShowForm(false);
      fetchWallets();
    } catch (err) {
      setError(err.message || 'Gagal menambah dompet.');
    } finally {
      setSaving(false);
    }
  }

  async function handleUpdate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.updateWallet(editWallet.id, {
        name: form.name,
        balance: Number(form.balance) || 0,
      });
      showMessage('Dompet berhasil diperbarui!');
      setEditWallet(null);
      setForm({ name: '', type: 'cash', balance: '' });
      fetchWallets();
    } catch (err) {
      setError(err.message || 'Gagal memperbarui dompet.');
    } finally {
      setSaving(false);
    }
  }

  function openEdit(wallet) {
    setEditWallet(wallet);
    setForm({ name: wallet.name, type: wallet.type, balance: wallet.balance });
    setShowForm(false);
    setError('');
  }

  function cancelForm() {
    setShowForm(false);
    setEditWallet(null);
    setForm({ name: '', type: 'cash', balance: '' });
    setError('');
  }

  function showMessage(msg) {
    setSuccess(msg);
    setTimeout(() => setSuccess(''), 3000);
  }

  const totalBalance = wallets.reduce((sum, w) => sum + (w.balance || 0), 0);

  return (
    <div className="animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Dompet</h1>
          <p className="text-gray-400 text-sm mt-1">Kelola dompet dan saldo kamu</p>
        </div>
        <button className="btn-primary" onClick={() => { setShowForm(!showForm); setEditWallet(null); setError(''); }}>
          + Tambah Dompet
        </button>
      </div>

      {/* Total Balance Card */}
      <div className="stat-card accent mb-8">
        <p className="text-xs font-medium text-gray-400 mb-1">Total Saldo Semua Dompet</p>
        <p className="text-3xl font-extrabold tracking-tight">{formatRupiah(totalBalance)}</p>
        <p className="text-xs text-gray-500 mt-2">{wallets.length} dompet aktif</p>
      </div>

      {success && (
        <div className="p-3 rounded-lg text-sm text-emerald-400 bg-emerald-400/10 border border-emerald-400/20 mb-4">
          {success}
        </div>
      )}

      {/* Add Form */}
      {showForm && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Tambah Dompet Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Nama</label>
                <input type="text" className="input-field" placeholder="BRI, Dana, OVO..." value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })} required />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Tipe</label>
                <select className="input-field" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>
                  <option value="cash">Cash</option>
                  <option value="bank">Bank</option>
                  <option value="ewallet">E-wallet</option>
                  <option value="savings">Tabungan</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Saldo Awal (Rp)</label>
                <input type="number" className="input-field" placeholder="0" value={form.balance}
                  onChange={e => setForm({ ...form, balance: e.target.value })} min="0" />
              </div>
            </div>
            <div className="flex gap-2 mt-4">
              <button type="submit" disabled={saving} className="btn-primary text-sm">{saving ? 'Menyimpan...' : 'Simpan'}</button>
              <button type="button" className="btn-secondary text-sm" onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {/* Edit Form */}
      {editWallet && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Edit Dompet: {editWallet.name}</h3>
          <form onSubmit={handleUpdate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Nama</label>
                <input type="text" className="input-field" value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })} required />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Saldo (Rp)</label>
                <input type="number" className="input-field" value={form.balance}
                  onChange={e => setForm({ ...form, balance: e.target.value })} min="0" />
              </div>
            </div>
            <div className="flex gap-2 mt-4">
              <button type="submit" disabled={saving} className="btn-primary text-sm">{saving ? 'Menyimpan...' : 'Update'}</button>
              <button type="button" className="btn-secondary text-sm" onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center min-h-[200px]">
          <div className="text-center">
            <div className="text-4xl mb-3 animate-pulse">💳</div>
            <p className="text-gray-400">Memuat dompet...</p>
          </div>
        </div>
      ) : wallets.length === 0 ? (
        <div className="glass-card text-center py-12">
          <div className="text-5xl mb-4">💳</div>
          <p className="text-gray-400 mb-2">Belum ada dompet</p>
          <p className="text-gray-500 text-sm">Tambahkan dompet pertamamu untuk mulai mencatat saldo</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {wallets.map((wallet) => (
            <div key={wallet.id} className="glass-card">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                    style={{ background: walletColors[wallet.type] || walletColors.cash }}>
                    {walletIcons[wallet.type] || '💳'}
                  </div>
                  <div>
                    <p className="text-base font-bold text-white">{wallet.name}</p>
                    <p className="text-xs text-gray-500 capitalize">{wallet.type === 'ewallet' ? 'E-wallet' : wallet.type}</p>
                  </div>
                </div>
                {wallet.is_default && (
                  <span className="text-xs font-semibold px-2 py-0.5 rounded-full text-indigo-400 bg-indigo-400/10">
                    Default
                  </span>
                )}
              </div>
              <p className="text-2xl font-extrabold tracking-tight">{formatRupiah(wallet.balance || 0)}</p>
              <div className="flex gap-2 mt-4">
                <button className="btn-secondary text-xs flex-1" onClick={() => openEdit(wallet)}>Edit</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

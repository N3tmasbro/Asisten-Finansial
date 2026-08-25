'use client';

import { useState, useEffect } from 'react';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';
import StatCard from '../../components/StatCard';

const walletIcons = { cash: '💵', bank: '🏦', ewallet: '📱', savings: '🏧' };
const walletColors = {
  cash: 'var(--accent-green-bg)',
  bank: 'var(--accent-teal-bg)',
  ewallet: 'var(--cat-transport-bg)',
  savings: 'var(--accent-amber-bg)',
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
    <div className="p-4 md:p-8 w-full">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-7">
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Dompet</h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Kelola dompet dan saldo kamu</p>
        </div>
        <button className="btn-primary w-full md:w-auto justify-center" onClick={() => { setShowForm(!showForm); setEditWallet(null); setError(''); }}>
          + Tambah Dompet
        </button>
      </div>

      {/* Total Balance Card */}
      <div style={{ marginBottom: 28 }}>
        <StatCard title="Total Saldo Semua Dompet" value={formatRupiah(totalBalance)} subtitle={`${wallets.length} dompet aktif`} color="teal"
          icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 12V22H4V12" /><path d="M22 7H2v5h20V7z" /><path d="M12 22V7" /><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z" /><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" /></svg>} />
      </div>

      {success && (
        <div style={{ padding: 12, borderRadius: 10, fontSize: 14, color: 'var(--accent-green)', background: 'var(--accent-green-bg)', marginBottom: 16 }}>{success}</div>
      )}

      {/* Add Form */}
      {showForm && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Tambah Dompet Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Nama</label>
                <input type="text" className="input-field" placeholder="BRI, Dana, OVO..." value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })} required />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Tipe</label>
                <select className="input-field" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>
                  <option value="cash">Cash</option>
                  <option value="bank">Bank</option>
                  <option value="ewallet">E-wallet</option>
                  <option value="savings">Tabungan</option>
                </select>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Saldo Awal (Rp)</label>
                <input type="number" className="input-field" placeholder="0" value={form.balance}
                  onChange={e => setForm({ ...form, balance: e.target.value })} min="0" />
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>{saving ? 'Menyimpan...' : 'Simpan'}</button>
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {/* Edit Form */}
      {editWallet && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Edit Dompet: {editWallet.name}</h3>
          <form onSubmit={handleUpdate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Nama</label>
                <input type="text" className="input-field" value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })} required />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Saldo (Rp)</label>
                <input type="number" className="input-field" value={form.balance}
                  onChange={e => setForm({ ...form, balance: e.target.value })} min="0" />
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>{saving ? 'Menyimpan...' : 'Update'}</button>
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 200 }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">💳</div>
            <p style={{ color: 'var(--text-secondary)' }}>Memuat dompet...</p>
          </div>
        </div>
      ) : wallets.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: 48 }}>
          <div style={{ fontSize: 48, marginBottom: 16 }}>💳</div>
          <p style={{ color: 'var(--text-secondary)', marginBottom: 8 }}>Belum ada dompet</p>
          <p style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>Tambahkan dompet pertamamu untuk mulai mencatat saldo</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {wallets.map((wallet) => (
            <div key={wallet.id} className="card" style={{ padding: 20, transition: 'transform 0.2s, box-shadow 0.2s' }}
              onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = 'var(--shadow-md)'; }}
              onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; }}
            >
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <div style={{
                    width: 40, height: 40, borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 20, backgroundColor: walletColors[wallet.type] || walletColors.cash, flexShrink: 0,
                  }}>
                    {walletIcons[wallet.type] || '💳'}
                  </div>
                  <div>
                    <p style={{ fontSize: 15, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>{wallet.name}</p>
                    <p style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: 0, textTransform: 'capitalize' }}>{wallet.type === 'ewallet' ? 'E-wallet' : wallet.type}</p>
                  </div>
                </div>
                {wallet.is_default && (
                  <span style={{ fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 999, color: 'var(--teal)', background: 'var(--teal-bg)' }}>
                    Default
                  </span>
                )}
              </div>
              <p className="font-poppins" style={{ fontSize: 22, fontWeight: 700, margin: '0 0 14px', letterSpacing: '-0.01em', color: 'var(--text-primary)' }}>{formatRupiah(wallet.balance || 0)}</p>
              <div style={{ display: 'flex', gap: 8 }}>
                <button className="btn-secondary" style={{ flex: 1, fontSize: 12, justifyContent: 'center' }} onClick={() => openEdit(wallet)}>Edit</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

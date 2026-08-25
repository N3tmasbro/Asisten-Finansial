'use client';

import { useState, useEffect } from 'react';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';
import StatCard from '../../components/StatCard';
import BudgetBar from '../../components/BudgetBar';

export default function BudgetsPage() {
  const [budgets, setBudgets] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editBudget, setEditBudget] = useState(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [form, setForm] = useState({ category_id: '', amount: '' });

  useEffect(() => {
    fetchAll();
  }, []);

  async function fetchAll() {
    setLoading(true);
    try {
      const [budgetsData, categoriesData] = await Promise.all([
        api.getBudgets(),
        api.getCategories(),
      ]);
      setBudgets(budgetsData.budgets || budgetsData || []);
      const allCats = categoriesData.categories || categoriesData || [];
      setCategories(allCats.filter(c => c.type === 'expense'));
    } catch (err) {
      console.error('Failed to load budgets:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.createBudget({
        category_id: Number(form.category_id),
        amount: Number(form.amount),
      });
      showMessage('Budget berhasil ditambahkan!');
      setForm({ category_id: '', amount: '' });
      setShowForm(false);
      fetchAll();
    } catch (err) {
      setError(err.message || 'Gagal menambah budget.');
    } finally {
      setSaving(false);
    }
  }

  async function handleUpdate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.updateBudget(editBudget.id, { amount: Number(form.amount) });
      showMessage('Budget berhasil diperbarui!');
      setEditBudget(null);
      setForm({ category_id: '', amount: '' });
      fetchAll();
    } catch (err) {
      setError(err.message || 'Gagal memperbarui budget.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id) {
    if (!confirm('Hapus budget ini?')) return;
    setDeleting(id);
    try {
      await api.deleteBudget(id);
      showMessage('Budget berhasil dihapus.');
      fetchAll();
    } catch (err) {
      setError(err.message || 'Gagal menghapus budget.');
    } finally {
      setDeleting(null);
    }
  }

  function openEdit(budget) {
    setEditBudget(budget);
    setForm({ category_id: budget.category_id, amount: budget.amount });
    setShowForm(false);
    setError('');
  }

  function cancelForm() {
    setShowForm(false);
    setEditBudget(null);
    setForm({ category_id: '', amount: '' });
    setError('');
  }

  function showMessage(msg) {
    setSuccess(msg);
    setTimeout(() => setSuccess(''), 3000);
  }

  const totalBudget = budgets.reduce((sum, b) => sum + (b.amount || 0), 0);
  const totalUsed = budgets.reduce((sum, b) => sum + (b.used || 0), 0);
  const totalRemaining = totalBudget - totalUsed;

  // Categories that don't already have a budget
  const usedCategoryIds = budgets.map(b => b.category_id);
  const availableCategories = categories.filter(c => !usedCategoryIds.includes(c.id));

  return (
    <div style={{ padding: 32 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 28 }}>
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Budget</h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Tetapkan dan pantau batas pengeluaran bulanan</p>
        </div>
        <button className="btn-primary" onClick={() => { setShowForm(!showForm); setEditBudget(null); setError(''); }}>
          + Tambah Budget
        </button>
      </div>

      {/* Overview */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 28 }}>
        <StatCard title="Total Budget" value={formatRupiah(totalBudget)} subtitle="Per bulan" color="teal"
          icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>} />
        <StatCard title="Terpakai" value={formatRupiah(totalUsed)} subtitle="Bulan ini" color="red"
          icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" /></svg>} />
        <StatCard
          title="Sisa Budget"
          value={formatRupiah(Math.abs(totalRemaining))}
          subtitle={totalRemaining < 0 ? 'Melebihi budget!' : 'Tersisa'}
          color={totalRemaining < 0 ? 'red' : 'green'}
          icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12" /></svg>}
        />
      </div>

      {success && (
        <div style={{ padding: 12, borderRadius: 10, fontSize: 14, color: 'var(--accent-green)', background: 'var(--accent-green-bg)', marginBottom: 16 }}>{success}</div>
      )}

      {/* Add Form */}
      {showForm && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Tambah Budget Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            {availableCategories.length === 0 ? (
              <p style={{ color: 'var(--text-secondary)', fontSize: 14, marginBottom: 16 }}>Semua kategori sudah memiliki budget bulan ini.</p>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                <div>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Kategori</label>
                  <select className="input-field" value={form.category_id}
                    onChange={e => setForm({ ...form, category_id: e.target.value })} required>
                    <option value="">Pilih kategori...</option>
                    {availableCategories.map(c => (
                      <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Batas Bulanan (Rp)</label>
                  <input type="number" className="input-field" placeholder="1000000"
                    value={form.amount} onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
                </div>
              </div>
            )}
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              {availableCategories.length > 0 && (
                <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>
                  {saving ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {/* Edit Form */}
      {editBudget && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Edit Budget: {editBudget.category?.name}</h3>
          <form onSubmit={handleUpdate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            <div>
              <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Batas Bulanan (Rp)</label>
              <input type="number" className="input-field" value={form.amount} style={{ maxWidth: 300 }}
                onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>
                {saving ? 'Menyimpan...' : 'Update'}
              </button>
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 200 }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">💰</div>
            <p style={{ color: 'var(--text-secondary)' }}>Memuat budget...</p>
          </div>
        </div>
      ) : budgets.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: 48 }}>
          <div style={{ fontSize: 48, marginBottom: 16 }}>🎯</div>
          <p style={{ color: 'var(--text-secondary)', marginBottom: 8 }}>Belum ada budget</p>
          <p style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>Buat budget bulanan untuk kategori pengeluaranmu</p>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {budgets.map((budget) => (
            <BudgetBar
              key={budget.id}
              name={budget.category?.name || 'Kategori'}
              category={budget.category?.name || 'Lainnya'}
              spent={budget.used || 0}
              total={budget.amount}
              icon={budget.category?.icon}
              onEdit={() => openEdit(budget)}
              onDelete={() => handleDelete(budget.id)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

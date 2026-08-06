'use client';

import { useState, useEffect } from 'react';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';

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
    <div className="animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Budget</h1>
          <p className="text-gray-400 text-sm mt-1">Tetapkan dan pantau batas pengeluaran bulanan</p>
        </div>
        <button className="btn-primary" onClick={() => { setShowForm(!showForm); setEditBudget(null); setError(''); }}>
          + Tambah Budget
        </button>
      </div>

      {/* Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div className="stat-card accent">
          <p className="text-xs font-medium text-gray-400 mb-1">Total Budget</p>
          <p className="text-2xl font-extrabold tracking-tight">{formatRupiah(totalBudget)}</p>
        </div>
        <div className="stat-card expense">
          <p className="text-xs font-medium text-gray-400 mb-1">Terpakai</p>
          <p className="text-2xl font-extrabold tracking-tight text-rose-400">{formatRupiah(totalUsed)}</p>
        </div>
        <div className="stat-card income">
          <p className="text-xs font-medium text-gray-400 mb-1">Sisa Budget</p>
          <p className={`text-2xl font-extrabold tracking-tight ${totalRemaining < 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
            {formatRupiah(Math.abs(totalRemaining))}
            {totalRemaining < 0 && <span className="text-sm font-normal ml-1">melebihi!</span>}
          </p>
        </div>
      </div>

      {success && (
        <div className="p-3 rounded-lg text-sm text-emerald-400 bg-emerald-400/10 border border-emerald-400/20 mb-4">
          {success}
        </div>
      )}

      {/* Add Form */}
      {showForm && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Tambah Budget Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">{error}</div>
            )}
            {availableCategories.length === 0 ? (
              <p className="text-gray-400 text-sm mb-4">Semua kategori sudah memiliki budget bulan ini.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-400 mb-1.5">Kategori</label>
                  <select className="input-field" value={form.category_id}
                    onChange={e => setForm({ ...form, category_id: e.target.value })} required>
                    <option value="">Pilih kategori...</option>
                    {availableCategories.map(c => (
                      <option key={c.id} value={c.id}>{c.icon} {c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-400 mb-1.5">Batas Bulanan (Rp)</label>
                  <input type="number" className="input-field" placeholder="1000000"
                    value={form.amount} onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
                </div>
              </div>
            )}
            <div className="flex gap-2 mt-4">
              {availableCategories.length > 0 && (
                <button type="submit" disabled={saving} className="btn-primary text-sm">
                  {saving ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
              <button type="button" className="btn-secondary text-sm" onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {/* Edit Form */}
      {editBudget && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Edit Budget: {editBudget.category?.name}</h3>
          <form onSubmit={handleUpdate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">{error}</div>
            )}
            <div>
              <label className="block text-xs font-medium text-gray-400 mb-1.5">Batas Bulanan (Rp)</label>
              <input type="number" className="input-field max-w-xs" value={form.amount}
                onChange={e => setForm({ ...form, amount: e.target.value })} min="1" required />
            </div>
            <div className="flex gap-2 mt-4">
              <button type="submit" disabled={saving} className="btn-primary text-sm">
                {saving ? 'Menyimpan...' : 'Update'}
              </button>
              <button type="button" className="btn-secondary text-sm" onClick={cancelForm}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center min-h-[200px]">
          <div className="text-center">
            <div className="text-4xl mb-3 animate-pulse">💰</div>
            <p className="text-gray-400">Memuat budget...</p>
          </div>
        </div>
      ) : budgets.length === 0 ? (
        <div className="glass-card text-center py-12">
          <div className="text-5xl mb-4">🎯</div>
          <p className="text-gray-400 mb-2">Belum ada budget</p>
          <p className="text-gray-500 text-sm">Buat budget bulanan untuk kategori pengeluaranmu</p>
        </div>
      ) : (
        <div className="space-y-4">
          {budgets.map((budget) => {
            const usagePercent = budget.usage_percentage || 0;
            const remaining = budget.remaining ?? (budget.amount - (budget.used || 0));
            const isOverBudget = usagePercent >= 100;
            const isWarning = usagePercent >= 75;

            return (
              <div key={budget.id} className="glass-card">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl flex items-center justify-center text-lg"
                      style={{ background: 'rgba(99,102,241,0.1)' }}>
                      {budget.category?.icon || '📌'}
                    </div>
                    <div>
                      <p className="text-sm font-bold text-white">{budget.category?.name || 'Kategori'}</p>
                      <p className="text-xs text-gray-500">Budget bulanan</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <div className="text-right">
                      <p className="text-sm font-bold">
                        {formatRupiah(budget.used || 0)}{' '}
                        <span className="text-gray-500 font-normal">/ {formatRupiah(budget.amount)}</span>
                      </p>
                      <p className={`text-xs font-semibold ${isOverBudget ? 'text-rose-400' : isWarning ? 'text-amber-400' : 'text-emerald-400'}`}>
                        {remaining > 0 ? `Sisa ${formatRupiah(remaining)}` : 'Melebihi budget!'}
                      </p>
                    </div>
                    <div className="flex gap-1">
                      <button
                        onClick={() => openEdit(budget)}
                        className="px-2 py-1 text-xs rounded-lg text-indigo-400 bg-indigo-400/10 hover:bg-indigo-400/20 transition-colors"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleDelete(budget.id)}
                        disabled={deleting === budget.id}
                        className="px-2 py-1 text-xs rounded-lg text-rose-400 bg-rose-400/10 hover:bg-rose-400/20 transition-colors"
                      >
                        {deleting === budget.id ? '...' : 'Hapus'}
                      </button>
                    </div>
                  </div>
                </div>
                <div className="progress-bar">
                  <div
                    className={`progress-fill ${isOverBudget ? 'danger' : isWarning ? 'warning' : ''}`}
                    style={{ width: `${Math.min(100, usagePercent)}%` }}
                  />
                </div>
                <p className="text-xs text-gray-500 mt-1">{usagePercent.toFixed(1)}% terpakai</p>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

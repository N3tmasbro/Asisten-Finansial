'use client';

import { useState, useEffect } from 'react';
import api from '../../lib/api';

export default function CategoriesPage() {
  const [categories, setCategories] = useState({ expense: [], income: [] });
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [form, setForm] = useState({ name: '', type: 'expense', icon: '' });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    fetchCategories();
  }, []);

  async function fetchCategories() {
    setLoading(true);
    try {
      const data = await api.getCategories();
      const all = data.categories || data || [];
      const expense = all.filter(c => c.type === 'expense');
      const income = all.filter(c => c.type === 'income');
      setCategories({ expense, income });
    } catch (err) {
      console.error('Failed to fetch categories:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api.createCategory({
        name: form.name,
        type: form.type,
        icon: form.icon || '📌',
      });
      setSuccess('Kategori berhasil ditambahkan!');
      setForm({ name: '', type: 'expense', icon: '' });
      setShowForm(false);
      fetchCategories();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.message || 'Gagal menambah kategori.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id) {
    if (!confirm('Hapus kategori ini?')) return;
    setDeleting(id);
    try {
      await api.request(`/categories/${id}`, { method: 'DELETE' });
      setSuccess('Kategori berhasil dihapus.');
      fetchCategories();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.message || 'Gagal menghapus kategori.');
    } finally {
      setDeleting(null);
    }
  }

  function CategoryCard({ cat }) {
    return (
      <div className="glass-card text-center relative group">
        <div className="text-3xl mb-2">{cat.icon || '📌'}</div>
        <p className="text-sm font-semibold">{cat.name}</p>
        {cat.is_default ? (
          <p className="text-xs text-gray-500 mt-1">Default</p>
        ) : (
          <button
            onClick={() => handleDelete(cat.id)}
            disabled={deleting === cat.id}
            className="mt-2 text-xs text-rose-400 hover:text-rose-300 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            {deleting === cat.id ? '...' : 'Hapus'}
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Kategori</h1>
          <p className="text-gray-400 text-sm mt-1">Kelola kategori pengeluaran dan pemasukan</p>
        </div>
        <button className="btn-primary" onClick={() => { setShowForm(!showForm); setError(''); }}>
          + Tambah Kategori
        </button>
      </div>

      {success && (
        <div className="p-3 rounded-lg text-sm text-emerald-400 bg-emerald-400/10 border border-emerald-400/20 mb-4">
          {success}
        </div>
      )}

      {showForm && (
        <div className="glass-card mb-6 animate-slide-up">
          <h3 className="text-lg font-bold mb-4">Tambah Kategori Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div className="p-3 rounded-lg text-sm text-rose-400 bg-rose-400/10 border border-rose-400/20 mb-4">
                {error}
              </div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Nama</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder="Nama kategori"
                  value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Tipe</label>
                <select
                  className="input-field"
                  value={form.type}
                  onChange={e => setForm({ ...form, type: e.target.value })}
                >
                  <option value="expense">Pengeluaran</option>
                  <option value="income">Pemasukan</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 mb-1.5">Icon (Emoji)</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder="📌"
                  maxLength={4}
                  value={form.icon}
                  onChange={e => setForm({ ...form, icon: e.target.value })}
                />
              </div>
            </div>
            <div className="flex gap-2 mt-4">
              <button type="submit" disabled={saving} className="btn-primary text-sm">
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
              <button type="button" className="btn-secondary text-sm" onClick={() => setShowForm(false)}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center min-h-[300px]">
          <div className="text-center">
            <div className="text-4xl mb-3 animate-pulse">🏷️</div>
            <p className="text-gray-400">Memuat kategori...</p>
          </div>
        </div>
      ) : (
        <>
          {/* Expense Categories */}
          <div className="mb-8">
            <h2 className="text-lg font-bold mb-4 text-rose-400">💸 Pengeluaran</h2>
            {categories.expense.length > 0 ? (
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {categories.expense.map((cat) => (
                  <CategoryCard key={cat.id} cat={cat} />
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-sm">Belum ada kategori pengeluaran.</p>
            )}
          </div>

          {/* Income Categories */}
          <div>
            <h2 className="text-lg font-bold mb-4 text-emerald-400">💰 Pemasukan</h2>
            {categories.income.length > 0 ? (
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {categories.income.map((cat) => (
                  <CategoryCard key={cat.id} cat={cat} />
                ))}
              </div>
            ) : (
              <p className="text-gray-500 text-sm">Belum ada kategori pemasukan.</p>
            )}
          </div>
        </>
      )}
    </div>
  );
}

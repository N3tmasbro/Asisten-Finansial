'use client';

import { useState, useEffect } from 'react';
import api from '../../lib/api';
import { getCategoryStyle } from '../../lib/categoryColors';

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
    const style = getCategoryStyle(cat.name);
    return (
      <div className="card" style={{ padding: 20, textAlign: 'center', transition: 'transform 0.2s, box-shadow 0.2s', cursor: 'default', position: 'relative' }}
        onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = 'var(--card-hover-shadow)'; }}
        onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; }}
      >
        <div style={{
          width: 48, height: 48, borderRadius: 12, margin: '0 auto 10px',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontSize: 24, backgroundColor: style.bg,
        }}>
          {cat.icon || '📌'}
        </div>
        <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>{cat.name}</p>
        {cat.is_default ? (
          <p style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: '6px 0 0' }}>Default</p>
        ) : (
          <button
            onClick={() => handleDelete(cat.id)}
            disabled={deleting === cat.id}
            style={{
              marginTop: 8, fontSize: 12, color: 'var(--accent-red)', background: 'none', border: 'none',
              opacity: 0.6, cursor: 'pointer', transition: 'opacity 0.15s',
            }}
            onMouseEnter={e => e.currentTarget.style.opacity = '1'}
            onMouseLeave={e => e.currentTarget.style.opacity = '0.6'}
          >
            {deleting === cat.id ? '...' : 'Hapus'}
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="p-4 md:p-8 w-full">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-7">
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Kategori</h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Kelola kategori pengeluaran dan pemasukan</p>
        </div>
        <button className="btn-primary w-full md:w-auto justify-center" onClick={() => { setShowForm(!showForm); setError(''); }}>
          + Tambah Kategori
        </button>
      </div>

      {success && (
        <div style={{ padding: 12, borderRadius: 10, fontSize: 14, color: 'var(--accent-green)', background: 'var(--accent-green-bg)', marginBottom: 16 }}>{success}</div>
      )}

      {showForm && (
        <div className="card animate-slide-up" style={{ padding: 24, marginBottom: 20 }}>
          <h3 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Tambah Kategori Baru</h3>
          <form onSubmit={handleCreate}>
            {error && (
              <div style={{ padding: 12, borderRadius: 8, fontSize: 13, color: 'var(--accent-red)', background: 'var(--accent-red-bg)', marginBottom: 16 }}>{error}</div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Nama</label>
                <input type="text" className="input-field" placeholder="Nama kategori"
                  value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Tipe</label>
                <select className="input-field" value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>
                  <option value="expense">Pengeluaran</option>
                  <option value="income">Pemasukan</option>
                </select>
              </div>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 500, color: 'var(--text-secondary)', marginBottom: 6 }}>Icon (Emoji)</label>
                <input type="text" className="input-field" placeholder="📌" maxLength={4}
                  value={form.icon} onChange={e => setForm({ ...form, icon: e.target.value })} />
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
              <button type="submit" disabled={saving} className="btn-primary" style={{ fontSize: 13 }}>
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
              <button type="button" className="btn-secondary" style={{ fontSize: 13 }} onClick={() => setShowForm(false)}>Batal</button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 300 }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">🏷️</div>
            <p style={{ color: 'var(--text-secondary)' }}>Memuat kategori...</p>
          </div>
        </div>
      ) : (
        <>
          {/* Expense Categories */}
          <div style={{ marginBottom: 32 }}>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--color-expense)', margin: '0 0 16px' }}>💸 Pengeluaran</h2>
            {categories.expense.length > 0 ? (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: 16 }}>
                {categories.expense.map((cat) => (
                  <CategoryCard key={cat.id} cat={cat} />
                ))}
              </div>
            ) : (
              <p style={{ color: 'var(--text-tertiary)', fontSize: 14 }}>Belum ada kategori pengeluaran.</p>
            )}
          </div>

          {/* Income Categories */}
          <div>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--color-income)', margin: '0 0 16px' }}>💰 Pemasukan</h2>
            {categories.income.length > 0 ? (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: 16 }}>
                {categories.income.map((cat) => (
                  <CategoryCard key={cat.id} cat={cat} />
                ))}
              </div>
            ) : (
              <p style={{ color: 'var(--text-tertiary)', fontSize: 14 }}>Belum ada kategori pemasukan.</p>
            )}
          </div>
        </>
      )}
    </div>
  );
}

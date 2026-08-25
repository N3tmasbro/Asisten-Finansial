'use client';

import { getCategoryStyle } from '../lib/categoryColors';
import { formatRupiah } from '../lib/utils';

const categoryEmoji = {
  'Makan & Minum': '🍛',
  'Transport': '⛽',
  'Belanja': '🛒',
  'Hiburan': '🎮',
  'Tagihan': '📱',
  'Kesehatan': '💊',
};

export default function BudgetBar({ name, category, spent, total, icon, onEdit, onDelete }) {
  const pct = Math.min((spent / total) * 100, 100);
  const { bg, icon: iconColor } = getCategoryStyle(category || name);
  const remaining = total - spent;

  const barColor = pct >= 90
    ? 'var(--accent-red)'
    : pct >= 70
      ? 'var(--accent-amber)'
      : 'var(--accent-teal)';

  return (
    <div
      className="card"
      style={{ padding: '20px 24px' }}
      onMouseEnter={e => { e.currentTarget.style.boxShadow = 'var(--card-hover-shadow)'; }}
      onMouseLeave={e => { e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
        <div style={{
          width: 34, height: 34, borderRadius: 8,
          backgroundColor: bg, color: iconColor,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          flexShrink: 0, fontSize: 17,
        }}>
          {icon || categoryEmoji[category || name] || '📦'}
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--text-primary)' }}>{name}</div>
          <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>Budget bulanan</div>
        </div>
        <div style={{ textAlign: 'right', marginRight: 12 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>
            {formatRupiah(spent)} / {formatRupiah(total)}
          </div>
          <div style={{ fontSize: 12, color: remaining > 0 ? 'var(--color-income)' : 'var(--color-expense)', fontWeight: 500 }}>
            {remaining > 0 ? `Sisa ${formatRupiah(remaining)}` : 'Melebihi budget!'}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 6 }}>
          {onEdit && (
            <button
              onClick={onEdit}
              style={{
                padding: '5px 12px', borderRadius: 6, border: '1px solid var(--border)',
                background: 'transparent', color: 'var(--text-secondary)', fontSize: 13, fontWeight: 500,
              }}
            >Edit</button>
          )}
          {onDelete && (
            <button
              onClick={onDelete}
              style={{
                padding: '5px 12px', borderRadius: 6,
                border: '1px solid var(--accent-red)',
                background: 'transparent', color: 'var(--accent-red)', fontSize: 13, fontWeight: 500,
              }}
            >Hapus</button>
          )}
        </div>
      </div>
      <div style={{ height: 6, backgroundColor: 'var(--border)', borderRadius: 3, overflow: 'hidden' }}>
        <div style={{
          height: '100%', width: `${pct}%`, backgroundColor: barColor,
          borderRadius: 3, transition: 'width 0.5s ease',
        }} />
      </div>
      <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginTop: 6 }}>
        {pct.toFixed(1)}% terpakai
      </div>
    </div>
  );
}

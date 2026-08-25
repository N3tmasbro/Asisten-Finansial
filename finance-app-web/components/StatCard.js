'use client';

const colorVars = {
  teal:  { accent: 'var(--accent-teal)',  bg: 'var(--accent-teal-bg)' },
  green: { accent: 'var(--accent-green)', bg: 'var(--accent-green-bg)' },
  red:   { accent: 'var(--accent-red)',   bg: 'var(--accent-red-bg)' },
  amber: { accent: 'var(--accent-amber)', bg: 'var(--accent-amber-bg)' },
};

export default function StatCard({ title, value, subtitle, icon, color }) {
  const c = colorVars[color] || colorVars.teal;
  return (
    <div
      className="card"
      style={{ padding: 24, borderTop: `3px solid ${c.accent}` }}
      onMouseEnter={e => {
        e.currentTarget.style.transform = 'translateY(-2px)';
        e.currentTarget.style.boxShadow = 'var(--card-hover-shadow)';
      }}
      onMouseLeave={e => {
        e.currentTarget.style.transform = 'translateY(0)';
        e.currentTarget.style.boxShadow = 'var(--shadow-sm)';
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
        <div style={{
          width: 44, height: 44, borderRadius: 10,
          backgroundColor: c.bg, color: c.accent,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          flexShrink: 0,
        }}>
          {icon}
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 4, fontWeight: 500 }}>
            {title}
          </div>
          <div className="font-poppins" style={{
            fontSize: 24, fontWeight: 700, color: c.accent,
            lineHeight: 1.2, letterSpacing: '-0.01em', marginBottom: 4,
          }}>
            {value}
          </div>
          <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{subtitle}</div>
        </div>
      </div>
    </div>
  );
}

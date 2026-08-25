'use client';

import { useState, useEffect, useRef } from 'react';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar, CartesianGrid } from 'recharts';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';
import StatCard from '../../components/StatCard';

const COLORS = ['#0891b2', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#fbbf24'];

// ─── Period label helpers ──────────────────────────────────────────────────────
function parsePeriod(period) {
  if (period.startsWith('specific_month:')) {
    const [year, month] = period.replace('specific_month:', '').split('-');
    const date = new Date(Number(year), Number(month) - 1, 1);
    const monthName = date.toLocaleString('id-ID', { month: 'long' });
    const prevDate = new Date(Number(year), Number(month) - 2, 1);
    const prevMonthName = prevDate.toLocaleString('id-ID', { month: 'long' });
    const prevYear = prevDate.getFullYear();
    return {
      short: `${monthName} ${year}`,
      prev: `${prevMonthName} ${prevYear !== Number(year) ? prevYear : ''}`.trim(),
      prevFull: `${prevMonthName} ${prevYear !== Number(year) ? prevYear : ''}`.trim(),
      isMonthly: true,
    };
  }
  const map = {
    this_year:  { short: 'Tahun Ini',  prev: 'tahun lalu',   prevFull: 'Tahun Lalu',   isMonthly: false },
    this_month: { short: 'Bulan Ini',  prev: 'bulan lalu',   prevFull: 'Bulan Lalu',   isMonthly: true  },
    this_week:  { short: 'Minggu Ini', prev: 'minggu lalu',  prevFull: 'Minggu Lalu',  isMonthly: false },
    last_month: { short: 'Bulan Lalu', prev: 'dua bulan lalu', prevFull: 'Dua Bulan Lalu', isMonthly: true },
  };
  return map[period] || { short: 'Periode Ini', prev: 'periode lalu', prevFull: 'Periode Lalu', isMonthly: true };
}

// Generate last 12 months for the month picker
function getLast12Months() {
  const months = [];
  const now = new Date();
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const key = `specific_month:${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    const label = d.toLocaleString('id-ID', { month: 'long', year: 'numeric' });
    months.push({ key, label });
  }
  return months;
}

// ─── Tooltip ──────────────────────────────────────────────────────────────────
const CustomTooltip = ({ active, payload, label }) => {
  if (active && payload && payload.length) {
    return (
      <div style={{
        borderRadius: 10, padding: '10px 14px', fontSize: 12,
        border: '1px solid var(--border)', background: 'var(--bg-card)',
        boxShadow: 'var(--shadow-md)',
      }}>
        <p style={{ color: 'var(--text-tertiary)', marginBottom: 4 }}>{label}</p>
        {payload.map((entry, i) => (
          <p key={i} style={{ color: entry.color, fontWeight: 600 }}>
            {entry.name}: {formatRupiah(entry.value)}
          </p>
        ))}
      </div>
    );
  }
  return null;
};

// ─── Main Page ─────────────────────────────────────────────────────────────────
export default function AnalyticsPage() {
  const [period, setPeriod] = useState('this_month');
  const [loading, setLoading] = useState(true);
  const [trends, setTrends] = useState(null);
  const [breakdown, setBreakdown] = useState([]);
  const [summary, setSummary] = useState(null);
  const [prediction, setPrediction] = useState(null);
  const [showMonthPicker, setShowMonthPicker] = useState(false);
  const pickerRef = useRef(null);
  const last12Months = getLast12Months();

  const periodInfo = parsePeriod(period);

  // Close picker when clicking outside
  useEffect(() => {
    function handleClickOutside(e) {
      if (pickerRef.current && !pickerRef.current.contains(e.target)) {
        setShowMonthPicker(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    fetchAll();
  }, [period]);

  async function fetchAll() {
    setLoading(true);
    try {
      const [trendsData, breakdownData, summaryData, predictionData] = await Promise.all([
        api.getTrends(period),
        api.getCategoryBreakdown(period, 'expense'),
        api.getSummary(period),
        api.getBalancePrediction(),
      ]);
      setTrends(trendsData);
      setBreakdown(breakdownData?.categories || []);
      setSummary(summaryData);
      setPrediction(predictionData);
    } catch (err) {
      console.error('Failed to load analytics:', err);
    } finally {
      setLoading(false);
    }
  }

  function selectPeriod(p) {
    setPeriod(p);
    setShowMonthPicker(false);
  }

  const dailyData = trends?.daily_trend?.series?.map(d => ({
    date: d.day_label || d.date,
    expense: d.expense || 0,
    income: d.income || 0,
  })) || [];

  const comparisonData = trends?.category_trend?.categories?.map(c => ({
    category: (c.category_name || c.name || '').length > 10
      ? (c.category_name || c.name || '').substring(0, 10) + '…'
      : (c.category_name || c.name || ''),
    periodIni: c.current || 0,
    periodLalu: c.previous || 0,
  })) || [];

  const pieData = breakdown.map((b, i) => ({
    name: b.category_name || b.name || b.category || 'Lainnya',
    value: Number(b.total || b.amount || 0),
    color: COLORS[i % COLORS.length],
  }));

  const totalExpense = summary?.summary?.total_expense || 0;
  const totalIncome = summary?.summary?.total_income || 0;
  const expenseChange = trends?.comparison?.expense?.change_percent || 0;
  const incomeChange = trends?.comparison?.income?.change_percent || 0;
  const daysLeft = prediction?.days_left_in_month || 0;
  const predictedBalance = prediction?.predicted_month_end_balance || 0;
  const predictionTrend = prediction?.trend || 'unknown';
  const dailyNetBurn = prediction?.daily_net_burn || 0;
  const dailySavingsRate = prediction?.daily_savings_rate || 0;
  const daysUntilEmpty = prediction?.days_until_empty;
  const predictedEmptyDate = prediction?.predicted_empty_date;

  const mainFilters = ['this_year', 'this_month', 'this_week'];
  const isMonthPickerActive = period.startsWith('specific_month:');

  return (
    <div style={{ padding: 32 }}>
      {/* Header & Filter */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 28 }}>
        <div>
          <h1 className="font-poppins" style={{ fontSize: 28, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.01em' }}>Analitik</h1>
          <p style={{ fontSize: 14, color: 'var(--text-secondary)', margin: '4px 0 0' }}>Insight mendalam tentang pola keuanganmu</p>
        </div>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
          {mainFilters.map((p) => (
            <button
              key={p}
              onClick={() => selectPeriod(p)}
              style={{
                padding: '7px 14px', borderRadius: 8, border: '1px solid var(--border)',
                background: period === p ? 'var(--color-accent)' : 'var(--bg-card)',
                color: period === p ? 'var(--color-accent-text)' : 'var(--text-secondary)',
                fontSize: 13, fontWeight: period === p ? 600 : 400,
                cursor: 'pointer', transition: 'all 0.15s',
              }}
            >
              {p === 'this_year' ? 'Tahun Ini' : p === 'this_month' ? 'Bulan Ini' : 'Minggu Ini'}
            </button>
          ))}

          {/* Month Picker Dropdown */}
          <div style={{ position: 'relative' }} ref={pickerRef}>
            <button
              onClick={() => setShowMonthPicker(v => !v)}
              style={{
                display: 'flex', alignItems: 'center', gap: 6,
                padding: '7px 14px', borderRadius: 8, border: '1px solid var(--border)',
                background: isMonthPickerActive ? 'var(--color-accent)' : 'var(--bg-card)',
                color: isMonthPickerActive ? 'var(--color-accent-text)' : 'var(--text-secondary)',
                fontSize: 13, fontWeight: isMonthPickerActive ? 600 : 400,
                cursor: 'pointer', transition: 'all 0.15s',
              }}
            >
              <span>📅</span>
              <span>{isMonthPickerActive ? periodInfo.short : 'Pilih Bulan'}</span>
              <span style={{ fontSize: 10, opacity: 0.6 }}>{showMonthPicker ? '▲' : '▾'}</span>
            </button>

            {showMonthPicker && (
              <div style={{
                position: 'absolute', right: 0, top: '100%', marginTop: 8, zIndex: 50,
                width: 200, borderRadius: 12, border: '1px solid var(--border)',
                overflow: 'hidden', boxShadow: 'var(--shadow-lg)',
                background: 'var(--bg-card)',
              }}>
                <div style={{ maxHeight: 240, overflowY: 'auto', padding: '4px 0' }}>
                  {last12Months.map(({ key, label }) => (
                    <button
                      key={key}
                      onClick={() => selectPeriod(key)}
                      style={{
                        width: '100%', textAlign: 'left', padding: '8px 16px', border: 'none',
                        fontSize: 13, cursor: 'pointer', transition: 'all 0.1s',
                        background: period === key ? 'var(--teal-bg)' : 'transparent',
                        color: period === key ? 'var(--teal)' : 'var(--text-secondary)',
                        fontWeight: period === key ? 600 : 400,
                      }}
                      onMouseEnter={e => { if (period !== key) e.currentTarget.style.background = 'var(--bg-hover)'; }}
                      onMouseLeave={e => { if (period !== key) e.currentTarget.style.background = 'transparent'; }}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {loading ? (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 400 }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontSize: 40, marginBottom: 12 }} className="animate-pulse">📊</div>
            <p style={{ color: 'var(--text-secondary)' }}>Memuat analitik...</p>
          </div>
        </div>
      ) : (
        <>
          {/* Stat Cards */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 28 }}>
            <StatCard
              title={`Pengeluaran ${periodInfo.short}`}
              value={formatRupiah(totalExpense)}
              subtitle={expenseChange !== 0 ? `${expenseChange > 0 ? '▲' : '▼'} ${Math.abs(expenseChange).toFixed(1)}% vs ${periodInfo.prev}` : '—'}
              color="red"
              icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19" /><polyline points="19 12 12 19 5 12" /></svg>}
            />
            <StatCard
              title={`Pemasukan ${periodInfo.short}`}
              value={formatRupiah(totalIncome)}
              subtitle={incomeChange !== 0 ? `${incomeChange > 0 ? '▲' : '▼'} ${Math.abs(incomeChange).toFixed(1)}% vs ${periodInfo.prev}` : '—'}
              color="green"
              icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="19" x2="12" y2="5" /><polyline points="5 12 12 5 19 12" /></svg>}
            />
            {/* Prediction card */}
            {periodInfo.isMonthly ? (
              <StatCard
                title="Prediksi Saldo Akhir Bulan"
                value={predictionTrend === 'unknown' ? '—' : formatRupiah(predictedBalance)}
                subtitle={
                  predictionTrend === 'burning' && daysUntilEmpty ? `⚠️ Habis dalam ${daysUntilEmpty} hari · -${formatRupiah(Math.abs(dailyNetBurn))}/hari`
                  : predictionTrend === 'saving' ? `💰 +${formatRupiah(dailySavingsRate)}/hari · ${daysLeft} hari tersisa`
                  : predictionTrend === 'stable' ? `⚖️ Seimbang · ${daysLeft} hari tersisa`
                  : 'Belum cukup data'
                }
                color={predictionTrend === 'burning' ? 'red' : predictionTrend === 'saving' ? 'green' : 'teal'}
                icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg>}
              />
            ) : (
              <StatCard
                title="Selisih Bersih"
                value={formatRupiah(totalIncome - totalExpense)}
                subtitle="Pemasukan − Pengeluaran"
                color={totalIncome - totalExpense >= 0 ? 'green' : 'red'}
                icon={<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>}
              />
            )}
          </div>

          {/* Charts Row */}
          <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 16, marginBottom: 20 }}>
            {/* Daily Spending Trend */}
            <div className="card" style={{ padding: 24 }}>
              <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Tren Pengeluaran Harian</h2>
              {dailyData.length > 0 ? (
                <div style={{ width: '100%', height: 300 }}>
                  <ResponsiveContainer>
                    <AreaChart data={dailyData}>
                      <defs>
                        <linearGradient id="colorExpense" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#ef4444" stopOpacity={0.3} />
                          <stop offset="95%" stopColor="#ef4444" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="colorIncome" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                          <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke="var(--border-subtle)" />
                      <XAxis dataKey="date" tick={{ fill: '#94a3b8', fontSize: 11 }} axisLine={false} tickLine={false} />
                      <YAxis tick={{ fill: '#94a3b8', fontSize: 11 }} axisLine={false} tickLine={false}
                        tickFormatter={(v) => v >= 1000000 ? `${(v / 1000000).toFixed(1)}jt` : `${(v / 1000).toFixed(0)}rb`} />
                      <Tooltip content={<CustomTooltip />} />
                      <Area type="monotone" dataKey="expense" name="Pengeluaran" stroke="#ef4444" fill="url(#colorExpense)" strokeWidth={2} />
                      <Area type="monotone" dataKey="income" name="Pemasukan" stroke="#10b981" fill="url(#colorIncome)" strokeWidth={2} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              ) : (
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: 256, color: 'var(--text-tertiary)', fontSize: 14 }}>
                  Belum ada data transaksi untuk periode ini
                </div>
              )}
            </div>

            {/* Category Donut */}
            <div className="card" style={{ padding: 24 }}>
              <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>Kategori</h2>
              {pieData.length > 0 ? (
                <>
                  <div style={{ width: '100%', height: 200 }}>
                    <ResponsiveContainer>
                      <PieChart>
                        <Pie data={pieData} cx="50%" cy="50%" innerRadius={60} outerRadius={85}
                          paddingAngle={3} dataKey="value" stroke="none">
                          {pieData.map((entry, i) => (
                            <Cell key={i} fill={entry.color} />
                          ))}
                        </Pie>
                        <Tooltip content={<CustomTooltip />} />
                      </PieChart>
                    </ResponsiveContainer>
                  </div>
                  <div style={{ marginTop: 16, display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {pieData.slice(0, 5).map((cat, i) => (
                      <div key={i} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: 14 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <div style={{ width: 10, height: 10, borderRadius: '50%', background: cat.color }} />
                          <span style={{ color: 'var(--text-secondary)', maxWidth: 100, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{cat.name}</span>
                        </div>
                        <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{formatRupiah(cat.value)}</span>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: 256, color: 'var(--text-tertiary)', fontSize: 14 }}>
                  Belum ada data
                </div>
              )}
            </div>
          </div>

          {/* Category Comparison */}
          <div className="card" style={{ padding: 24 }}>
            <h2 style={{ fontSize: 17, fontWeight: 600, color: 'var(--text-primary)', margin: '0 0 16px' }}>
              Perbandingan {periodInfo.short} vs {periodInfo.prevFull}
            </h2>
            {comparisonData.length > 0 ? (
              <div style={{ width: '100%', height: 300 }}>
                <ResponsiveContainer>
                  <BarChart data={comparisonData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border-subtle)" />
                    <XAxis dataKey="category" tick={{ fill: '#94a3b8', fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fill: '#94a3b8', fontSize: 11 }} axisLine={false} tickLine={false}
                      tickFormatter={(v) => v >= 1000000 ? `${(v / 1000000).toFixed(1)}jt` : `${(v / 1000).toFixed(0)}rb`} />
                    <Tooltip content={<CustomTooltip />} />
                    <Bar dataKey="periodLalu" name={periodInfo.prevFull} fill="#94a3b8" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="periodIni" name={periodInfo.short} fill="#0891b2" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            ) : (
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: 160, color: 'var(--text-tertiary)', fontSize: 14 }}>
                Belum ada data perbandingan
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}

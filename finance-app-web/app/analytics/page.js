'use client';

import { useState, useEffect, useRef } from 'react';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar, CartesianGrid } from 'recharts';
import { formatRupiah } from '../../lib/utils';
import api from '../../lib/api';

const COLORS = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#10b981', '#f59e0b', '#f43f5e'];

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
      <div className="rounded-lg px-3 py-2 text-xs border border-white/10"
        style={{ background: 'rgba(17, 24, 39, 0.95)', backdropFilter: 'blur(8px)' }}>
        <p className="text-gray-400 mb-1">{label}</p>
        {payload.map((entry, i) => (
          <p key={i} style={{ color: entry.color }} className="font-semibold">
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
    <div className="animate-fade-in">
      {/* Header & Filter */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Analitik</h1>
          <p className="text-gray-400 text-sm mt-1">Insight mendalam tentang pola keuanganmu</p>
        </div>
        <div className="flex gap-2 items-center">
          {mainFilters.map((p) => (
            <button
              key={p}
              onClick={() => selectPeriod(p)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150
                ${period === p ? 'bg-indigo-500/20 text-indigo-400' : 'text-gray-400 hover:text-white hover:bg-white/[0.03]'}`}
            >
              {p === 'this_year' ? 'Tahun Ini' : p === 'this_month' ? 'Bulan Ini' : 'Minggu Ini'}
            </button>
          ))}

          {/* Month Picker Dropdown */}
          <div className="relative" ref={pickerRef}>
            <button
              onClick={() => setShowMonthPicker(v => !v)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150
                ${isMonthPickerActive
                  ? 'bg-indigo-500/20 text-indigo-400'
                  : 'text-gray-400 hover:text-white hover:bg-white/[0.03]'}`}
            >
              <span>📅</span>
              <span>{isMonthPickerActive ? periodInfo.short : 'Pilih Bulan'}</span>
              <span className="text-[10px] opacity-60">{showMonthPicker ? '▲' : '▾'}</span>
            </button>

            {showMonthPicker && (
              <div className="absolute right-0 top-full mt-2 z-50 w-48 rounded-xl border border-white/10 overflow-hidden shadow-2xl"
                style={{ background: 'rgba(15, 20, 35, 0.98)', backdropFilter: 'blur(16px)' }}>
                <div className="max-h-60 overflow-y-auto py-1">
                  {last12Months.map(({ key, label }) => (
                    <button
                      key={key}
                      onClick={() => selectPeriod(key)}
                      className={`w-full text-left px-4 py-2 text-xs transition-colors duration-100
                        ${period === key
                          ? 'bg-indigo-500/20 text-indigo-400 font-semibold'
                          : 'text-gray-300 hover:bg-white/[0.06] hover:text-white'}`}
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
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <div className="text-4xl mb-3 animate-pulse">📊</div>
            <p className="text-gray-400">Memuat analitik...</p>
          </div>
        </div>
      ) : (
        <>
          {/* Stat Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <div className="stat-card expense">
              <p className="text-xs font-medium text-gray-400 mb-1">Pengeluaran {periodInfo.short}</p>
              <p className="text-2xl font-extrabold tracking-tight text-rose-400">{formatRupiah(totalExpense)}</p>
              {expenseChange !== 0 && (
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-2 ${expenseChange > 0 ? 'text-rose-400 bg-rose-400/10' : 'text-emerald-400 bg-emerald-400/10'}`}>
                  {expenseChange > 0 ? '▲' : '▼'} {Math.abs(expenseChange).toFixed(1)}% vs {periodInfo.prev}
                </span>
              )}
            </div>
            <div className="stat-card income">
              <p className="text-xs font-medium text-gray-400 mb-1">Pemasukan {periodInfo.short}</p>
              <p className="text-2xl font-extrabold tracking-tight text-emerald-400">{formatRupiah(totalIncome)}</p>
              {incomeChange !== 0 && (
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-2 ${incomeChange > 0 ? 'text-emerald-400 bg-emerald-400/10' : 'text-rose-400 bg-rose-400/10'}`}>
                  {incomeChange > 0 ? '▲' : '▼'} {Math.abs(incomeChange).toFixed(1)}% vs {periodInfo.prev}
                </span>
              )}
            </div>
            {/* Prediction card — trend-aware with dynamic colors */}
            {periodInfo.isMonthly ? (
              <div className="stat-card accent">
                <p className="text-xs font-medium text-gray-400 mb-1">Prediksi Saldo Akhir Bulan</p>
                <p className={`text-2xl font-extrabold tracking-tight ${
                  predictionTrend === 'burning' ? 'text-rose-400'
                  : predictionTrend === 'saving' ? 'text-emerald-400'
                  : predictionTrend === 'stable' ? 'text-sky-400'
                  : 'text-gray-400'
                }`}>
                  {predictionTrend === 'unknown' ? '—' : formatRupiah(predictedBalance)}
                </p>

                {/* Trend-specific detail */}
                {predictionTrend === 'burning' && daysUntilEmpty && (
                  <div className="mt-2">
                    <span className="text-xs font-semibold px-2 py-0.5 rounded-full text-amber-400 bg-amber-400/10">
                      ⚠️ Saldo habis dalam {daysUntilEmpty} hari
                    </span>
                    <p className="text-xs text-gray-500 mt-1.5">
                      Laju: -{formatRupiah(Math.abs(dailyNetBurn))}/hari
                    </p>
                  </div>
                )}
                {predictionTrend === 'saving' && (
                  <div className="mt-2">
                    <span className="text-xs font-semibold px-2 py-0.5 rounded-full text-emerald-400 bg-emerald-400/10">
                      💰 +{formatRupiah(dailySavingsRate)}/hari
                    </span>
                    <p className="text-xs text-gray-500 mt-1.5">{daysLeft} hari tersisa</p>
                  </div>
                )}
                {predictionTrend === 'stable' && (
                  <p className="text-xs text-gray-500 mt-2">⚖️ Keuangan seimbang • {daysLeft} hari tersisa</p>
                )}
                {predictionTrend === 'unknown' && (
                  <p className="text-xs text-gray-500 mt-2">Belum cukup data transaksi</p>
                )}
              </div>
            ) : (
              <div className="stat-card accent">
                <p className="text-xs font-medium text-gray-400 mb-1">Selisih Bersih</p>
                <p className={`text-2xl font-extrabold tracking-tight ${totalIncome - totalExpense >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                  {formatRupiah(totalIncome - totalExpense)}
                </p>
                <p className="text-xs text-gray-500 mt-2">Pemasukan − Pengeluaran</p>
              </div>
            )}
          </div>

          {/* Charts Row */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {/* Daily Spending Trend */}
            <div className="lg:col-span-2 glass-card">
              <h2 className="text-lg font-bold mb-4">Tren Pengeluaran Harian</h2>
              {dailyData.length > 0 ? (
                <div style={{ width: '100%', height: 300 }}>
                  <ResponsiveContainer>
                    <AreaChart data={dailyData}>
                      <defs>
                        <linearGradient id="colorExpense" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#f43f5e" stopOpacity={0.3} />
                          <stop offset="95%" stopColor="#f43f5e" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="colorIncome" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                          <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
                      <XAxis dataKey="date" tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                      <YAxis tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false}
                        tickFormatter={(v) => v >= 1000000 ? `${(v / 1000000).toFixed(1)}jt` : `${(v / 1000).toFixed(0)}rb`} />
                      <Tooltip content={<CustomTooltip />} />
                      <Area type="monotone" dataKey="expense" name="Pengeluaran" stroke="#f43f5e" fill="url(#colorExpense)" strokeWidth={2} />
                      <Area type="monotone" dataKey="income" name="Pemasukan" stroke="#10b981" fill="url(#colorIncome)" strokeWidth={2} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              ) : (
                <div className="flex items-center justify-center h-64 text-gray-500 text-sm">
                  Belum ada data transaksi untuk periode ini
                </div>
              )}
            </div>

            {/* Category Donut */}
            <div className="glass-card">
              <h2 className="text-lg font-bold mb-4">Kategori</h2>
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
                  <div className="mt-4 space-y-2">
                    {pieData.slice(0, 5).map((cat, i) => (
                      <div key={i} className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2">
                          <div className="w-3 h-3 rounded-full" style={{ background: cat.color }} />
                          <span className="text-gray-300 truncate max-w-[100px]">{cat.name}</span>
                        </div>
                        <span className="font-semibold">{formatRupiah(cat.value)}</span>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <div className="flex items-center justify-center h-64 text-gray-500 text-sm">
                  Belum ada data
                </div>
              )}
            </div>
          </div>

          {/* Category Comparison */}
          <div className="glass-card">
            <h2 className="text-lg font-bold mb-4">
              Perbandingan {periodInfo.short} vs {periodInfo.prevFull}
            </h2>
            {comparisonData.length > 0 ? (
              <div style={{ width: '100%', height: 300 }}>
                <ResponsiveContainer>
                  <BarChart data={comparisonData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
                    <XAxis dataKey="category" tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false}
                      tickFormatter={(v) => v >= 1000000 ? `${(v / 1000000).toFixed(1)}jt` : `${(v / 1000).toFixed(0)}rb`} />
                    <Tooltip content={<CustomTooltip />} />
                    <Bar dataKey="periodLalu" name={periodInfo.prevFull} fill="#374151" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="periodIni" name={periodInfo.short} fill="#6366f1" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            ) : (
              <div className="flex items-center justify-center h-40 text-gray-500 text-sm">
                Belum ada data perbandingan
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}

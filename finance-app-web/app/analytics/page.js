'use client';

import { useState, useEffect } from 'react';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar, CartesianGrid } from 'recharts';
import { formatRupiah, formatPercent } from '../../lib/utils';
import api from '../../lib/api';

const COLORS = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#10b981', '#f59e0b', '#f43f5e'];

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

export default function AnalyticsPage() {
  const [period, setPeriod] = useState('this_month');
  const [loading, setLoading] = useState(true);
  const [trends, setTrends] = useState(null);
  const [breakdown, setBreakdown] = useState([]);
  const [summary, setSummary] = useState(null);
  const [prediction, setPrediction] = useState(null);

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

  const dailyData = trends?.daily_trend?.series?.map(d => ({
    date: d.day_label || d.date,
    expense: d.expense || 0,
    income: d.income || 0,
  })) || [];

  const comparisonData = trends?.category_trend?.categories?.map(c => ({
    category: (c.category_name || c.name || '').length > 10
      ? (c.category_name || c.name || '').substring(0, 10) + '…'
      : (c.category_name || c.name || ''),
    bulanIni: c.current || 0,
    bulanLalu: c.previous || 0,
  })) || [];

  const pieData = breakdown.map((b, i) => ({
    name: b.category_name || b.name || b.category || 'Lainnya',
    value: b.total || b.amount || 0,
    color: COLORS[i % COLORS.length],
  }));

  // summary response: { summary: { total_expense, total_income, ... }, dashboard: {...}, recent_transactions: [...] }
  const totalExpense = summary?.summary?.total_expense || 0;
  const totalIncome = summary?.summary?.total_income || 0;
  const expenseChange = trends?.comparison?.expense?.change_percent || 0;
  const incomeChange = trends?.comparison?.income?.change_percent || 0;
  const daysLeft = prediction?.days_left_in_month || 0;
  const predictedBalance = prediction?.predicted_month_end_balance || 0;

  return (
    <div className="animate-fade-in">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-extrabold tracking-tight">Analitik</h1>
          <p className="text-gray-400 text-sm mt-1">Insight mendalam tentang pola keuanganmu</p>
        </div>
        <div className="flex gap-2">
          {['this_month', 'last_month', 'this_week'].map((p) => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-150
                ${period === p ? 'bg-indigo-500/20 text-indigo-400' : 'text-gray-400 hover:text-white hover:bg-white/[0.03]'}`}
            >
              {p === 'this_month' ? 'Bulan Ini' : p === 'last_month' ? 'Bulan Lalu' : 'Minggu Ini'}
            </button>
          ))}
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
          {/* Comparison Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <div className="stat-card expense">
              <p className="text-xs font-medium text-gray-400 mb-1">Pengeluaran Bulan Ini</p>
              <p className="text-2xl font-extrabold tracking-tight text-rose-400">{formatRupiah(totalExpense)}</p>
              {expenseChange !== 0 && (
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-2 ${expenseChange > 0 ? 'text-rose-400 bg-rose-400/10' : 'text-emerald-400 bg-emerald-400/10'}`}>
                  {expenseChange > 0 ? '▲' : '▼'} {Math.abs(expenseChange).toFixed(1)}% vs bulan lalu
                </span>
              )}
            </div>
            <div className="stat-card income">
              <p className="text-xs font-medium text-gray-400 mb-1">Pemasukan Bulan Ini</p>
              <p className="text-2xl font-extrabold tracking-tight text-emerald-400">{formatRupiah(totalIncome)}</p>
              {incomeChange !== 0 && (
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-2 ${incomeChange > 0 ? 'text-emerald-400 bg-emerald-400/10' : 'text-rose-400 bg-rose-400/10'}`}>
                  {incomeChange > 0 ? '▲' : '▼'} {Math.abs(incomeChange).toFixed(1)}% vs bulan lalu
                </span>
              )}
            </div>
            <div className="stat-card accent">
              <p className="text-xs font-medium text-gray-400 mb-1">Prediksi Saldo Akhir Bulan</p>
              <p className="text-2xl font-extrabold tracking-tight">{formatRupiah(predictedBalance)}</p>
              {daysLeft > 0 && <p className="text-xs text-gray-500 mt-2">{daysLeft} hari tersisa</p>}
            </div>
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
                        tickFormatter={(v) => v >= 1000000 ? `${(v/1000000).toFixed(1)}jt` : `${(v/1000).toFixed(0)}rb`} />
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
            <h2 className="text-lg font-bold mb-4">Perbandingan Bulan Ini vs Bulan Lalu</h2>
            {comparisonData.length > 0 ? (
              <div style={{ width: '100%', height: 300 }}>
                <ResponsiveContainer>
                  <BarChart data={comparisonData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" />
                    <XAxis dataKey="category" tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fill: '#6b7280', fontSize: 11 }} axisLine={false} tickLine={false}
                      tickFormatter={(v) => v >= 1000000 ? `${(v/1000000).toFixed(1)}jt` : `${(v/1000).toFixed(0)}rb`} />
                    <Tooltip content={<CustomTooltip />} />
                    <Bar dataKey="bulanLalu" name="Bulan Lalu" fill="#374151" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="bulanIni" name="Bulan Ini" fill="#6366f1" radius={[4, 4, 0, 0]} />
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

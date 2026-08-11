---
tags: [backend, analytics, prediction, documentation]
updated: 2026-08-11
---

# 07 — Balance Prediction Service

Fitur Prediksi Saldo (`BalancePredictionService`) bertanggung jawab memprediksi kondisi keuangan pengguna di masa mendatang menggunakan data historis transaksi 7–30 hari terakhir.

---

## ⚙️ Cara Kerja & Algoritma

Algoritma ini menggunakan pendekatan *Trimmed Daily Moving Average* untuk menghitung laju harian pengeluaran dan pemasukan secara stabil.

### 1. Pembersihan Pencilan (Outlier Trimming)
Untuk mencegah pengeluaran atau pemasukan sesaat (misalnya: beli laptop, bayar THR tahunan) merusak rata-rata harian, laju dihitung dengan **mengabaikan 10% transaksi terbesar** (top 10%) dalam periode pencarian.

### 2. Rentang Hari Pencarian Dinamis (Lookback Period)
Rentang pencarian disesuaikan secara dinamis berdasarkan usia data transaksi user:
* **Pengguna Baru:** Rentang disesuaikan sejak hari transaksi pertama yang tercatat (minimal **7 hari** untuk menghindari volatilitas ekstrim).
* **Pengguna Lama:** Rentang maksimal dibatasi hingga **30 hari** terakhir.

### 3. Klasifikasi Tren Keuangan
Sistem membandingkan laju pengeluaran harian bersih (`daily_net_burn = expense - income`). Tren diklasifikasikan ke dalam 4 kategori:
* **Defisit (`burning`):** `daily_net_burn > Rp5.000`. Saldo diprediksi akan habis pada tanggal tertentu (`predicted_empty_date`).
* **Surplus (`saving`):** `daily_net_burn < -Rp5.000` (atau `daily_savings_rate > Rp5.000`). Saldo diprediksi meningkat hingga akhir bulan.
* **Seimbang (`stable`):** Selisih bersih laju harian berada di rentang `-Rp5.000` s/d `+Rp5.000`.
* **Tidak Jelas (`unknown`):** Terjadi jika pengguna tidak memiliki riwayat transaksi pengeluaran/pemasukan sama sekali dalam rentang lookback.

---

## 📦 Payload Respon API

Endpoint `/api/analytics/balance-prediction` mengembalikan data dengan struktur berikut:

```json
{
  "current_balance": 1000000,
  "daily_avg_expense": 45000,
  "daily_avg_income": 15000,
  "daily_net_burn": 30000,
  "daily_savings_rate": -30000,
  "trend": "burning",
  "days_until_empty": 34,
  "predicted_empty_date": "2026-09-14",
  "predicted_month_end_balance": 10000,
  "days_left_in_month": 20,
  "lookback_days": 30
}
```

---

## 🎨 Integrasi Tampilan

### 1. Asisten AI WhatsApp (Stage 3 Response Formatter)
Gemini Provider (`GeminiProvider.php`) memformat respon prediksi saldo menggunakan template berikut:
* **Burning:** *"Saldo kamu Rp1.000.000 diperkirakan habis dalam 15 hari (tanggal 26 Agu) dengan laju bersih Rp70.000/hari. Coba kurangi pengeluaranmu ya! 💸"*
* **Saving:** *"Keren! Saldo kamu bertambah rata-rata Rp20.000/hari. Akhir bulan ini saldo diprediksi naik menjadi Rp1.500.000. Pertahankan! 💰"*
* **Stable:** *"Keuanganmu stabil bulan ini. Pengeluaran dan pemasukan seimbang! Selisihnya hanya Rp2.000/hari. ⚖️"*
* **Unknown:** *"Aku belum punya cukup data buat prediksi nih 🤔 Coba catat beberapa transaksi dulu ya!"*

### 2. Analytics Dashboard Web (Next.js)
Kartu analitik "Prediksi Saldo Akhir Bulan" pada dashboard Next.js berubah warna secara dinamis:
* **Defisit (burning):** Warna teks merah/oranye (`text-rose-400`), menampilkan peringatan `⚠️ Saldo habis dalam X hari` dan laju harian.
* **Surplus (saving):** Warna teks hijau (`text-emerald-400`), menampilkan laju tabungan harian (`💰 +RpX/hari`).
* **Seimbang (stable):** Warna teks biru (`text-sky-400`), menampilkan label `⚖️ Keuangan seimbang`.
* **Unknown (unknown):** Warna teks abu-abu (`text-gray-400`), menampilkan `Belum cukup data transaksi`.

---

## 🧪 Validasi Pengujian
Unit pengujian ditulis di [BalancePredictionServiceTest.php](../../../tests/Feature/Analytics/BalancePredictionServiceTest.php) untuk memvalidasi:
1. Perhitungan matematis outlier trimming (top 10%).
2. Transisi status trend berdasarkan laju keuangan.
3. Keakuratan lookback days dinamis untuk user baru.

---
tags: [ai, gemini, rate-limiting, stress-test, documentation]
date: 2026-08-26
---

# 04 - API Rate Limit Analysis (Gemini)

Dokumen ini berisi analisis batasan penggunaan (rate limit) dari Google Gemini API pada *Free Tier* (Tingkat Gratis) dan bagaimana arsitektur aplikasi ini mengonsumsinya.

## 1. Spesifikasi Rate Limit (Free Tier)
Berdasarkan data dari Google AI Studio:

- **Gemini 3.1 Flash Lite (Model Utama)**
  - **RPM (Requests Per Minute):** 15
  - **RPD (Requests Per Day):** 500
- **Gemini 3.5 Flash**
  - **RPM:** 15
  - **RPD:** 20 (Sangat kecil, tidak cocok untuk fallback utama)

## 2. Konsumsi API per Transaksi
Arsitektur bot WhatsApp (via `ChatOrchestratorService`) melakukan **2 kali panggilan API** untuk setiap 1 pesan masuk:
1. **Deteksi Niat (Intent Classification)**: Menentukan apakah pengguna ingin menambah transaksi, melihat saldo, atau menghapus riwayat.
2. **Ekstraksi Entitas (Entity Extraction)**: Mengambil data jumlah uang, kategori, dan detail lainnya jika niatnya adalah menambah transaksi.

**Kalkulasi Beban:**
1 Transaksi / Pesan = **2 API Calls**.

## 3. Hasil & Analisis Stress Test
Saat melakukan pengujian *stress test* dengan pengiriman otomatis 100 pesan transaksi:

- **Pengaturan Awal:** Jeda (delay) antar pesan disetel 4 detik.
- **Kecepatan Kirim:** 60 detik / 4 = 15 pesan per menit.
- **Total Request:** 15 pesan × 2 API Call = **30 RPM (Requests Per Minute)** secara teoritis.
- **Hasil:** Aplikasi menabrak limit. Di *dashboard* Google AI Studio, tercatat puncak penggunaan mencapai **18 RPM**, yang mana lebih besar dari limit gratis (15 RPM). Akibatnya, API memblokir permintaan selanjutnya meskipun kuota harian (RPD) masih banyak tersisa (baru terpakai sekitar 134/500).

## 4. Solusi & Rekomendasi
Untuk menghindari *rate limit* (HTTP 429 / Error Quota Exceeded) saat melakukan *stress testing* massal atau pemrosesan antrean panjang:

1. **Gunakan Delay yang Aman**: Atur jeda minimal **10 detik** antar pesan WhatsApp.
   - 60 detik / 10 detik = 6 pesan per menit.
   - 6 pesan × 2 API Call = **12 RPM**. (Aman, berada di bawah limit 15 RPM).
2. **Hindari Fallback ke 3.5 Flash**: Karena model *Gemini 3.5 Flash* hanya memiliki 20 RPD (Requests Per Day) di tier gratis, menggunakannya sebagai *fallback* akan menyebabkan limit harian habis seketika. Pertahankan penggunaan model *Flash Lite* untuk pengujian gratis.
3. **Optimasi Masa Depan**: Jika memungkinkan, gabungkan proses *Intent Classification* dan *Entity Extraction* menjadi 1x panggilan API (menggunakan *Structured JSON Schema Output* pada *System Instruction*), sehingga 1 pesan = 1 API Call. Ini akan menggandakan kapasitas RPM kita.

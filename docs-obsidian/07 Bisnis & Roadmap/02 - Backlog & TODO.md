---
tags: [roadmap, backlog, todo]
---

# 02 — Backlog & TODO

Daftar hal yang **belum selesai** atau **perlu didiskusikan** lebih lanjut.

---

## 🔴 Prioritas Tinggi

- [ ] **Finalisasi harga tier subscription** — *(Ditunda hingga rilis produksi/monetisasi)* Rencana draf lokal & hitungan COGS tersimpan di [[01 - Model Monetisasi]]
- [x] **Strategi testing prompt AI** — 90 test cases (Intent/Extractor/Command/Query), baseline 100% MockAI pass. Gap analysis & improvement backlog di [[../06 AI & Integrasi/03 - Test Cases & Hasil]]

---

## 🟡 Prioritas Sedang

- [x] **Algoritma `BalancePredictionService`** — top 10% outlier trimming, dynamic lookback (7-30 hari), trend classification (burning/saving/stable/unknown), terintegrasi ke prompt WhatsApp AI & dashboard Next.js
- [ ] **Struktur Next.js dashboard yang lebih detail** — state management, integrasi API, komponen chart yang dipakai (recharts/chart.js), belum dibahas mendalam
- [x] **Integrasi API Frontend** — menghubungkan halaman Next.js yang ada ke endpoints Laravel yang baru saja kita bangun

---

## 🟢 Prioritas Rendah / Nanti

- [ ] **Prompt untuk fitur "Pengingat Cerdas"** — deteksi pola transaksi rutin (misal bayar listrik tiap awal bulan) belum dirancang prompt/logic-nya
- [ ] **Prompt & logic untuk "Saran Penghematan"** — bagaimana AI menghitung dan memformat saran (`"jika mengurangi X 20%, bisa menabung Y"`)
- [ ] **Integrasi payment gateway** — pemilihan provider (Midtrans vs lainnya) dan alur `Subscription` lifecycle (upgrade, downgrade, gagal bayar) belum dibahas teknis
- [ ] **Kebijakan migrasi Baileys → WhatsApp Business API resmi** — trigger/threshold kapan harus migrasi (jumlah user? volume pesan?) belum ditentukan angka pastinya

---

## ✅ Selesai

- [x] Arsitektur 3-stage / Multi-Intent AI pipeline
- [x] Provider pattern (AI & WhatsApp)
- [x] Database schema & migrations
- [x] Backend API CRUD endpoints
- [x] WhatsApp Bridge (Baileys) + Logging harian
- [x] Auto-linking WA via LID
- [x] Queue job untuk async processing
- [x] Frontend halaman dasar (login, register, dashboard, transactions, settings)
- [x] **`CorrectionHandlerService` & `DeleteHandlerService`** + algoritma pencarian `CandidateResolverService` + konfirmasi
- [x] **Detail webhook payload contract** antara WA Bridge dan Laravel

---

## 📋 Urutan yang Disarankan untuk Dikerjakan Berikutnya

Berdasarkan progres saat ini:

```
1. Integrasi API Frontend Next.js (Menghubungkan dashboard web ke backend)
2. Algoritma Balance Prediction ("saldo akan habis tanggal berapa?")
3. Fitur AI: Prompt Pengingat Cerdas (Deteksi transaksi rutin)
4. Fitur AI: Prompt Saran Penghematan
5. Integrasi Payment Gateway (Midtrans)
```

---

## 🔗 Lihat Juga
- [[01 - Model Monetisasi]]
- [[../06 AI & Integrasi/02 - Prompt & Pipeline Detail]]

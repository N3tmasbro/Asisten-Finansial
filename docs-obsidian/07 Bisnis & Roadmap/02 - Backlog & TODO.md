---
tags: [roadmap, backlog, todo]
---

# 02 — Backlog & TODO

Daftar hal yang **belum selesai** atau **perlu didiskusikan** lebih lanjut.

---

## 🔴 Prioritas Tinggi

- [ ] **Finalisasi harga tier subscription** — *(Ditunda hingga rilis produksi/monetisasi)* Rencana draf lokal & hitungan COGS tersimpan di [[01 - Model Monetisasi]]
- [x] **Strategi testing prompt AI** — 90 test cases (Intent/Extractor/Command/Query), baseline 100% MockAI pass. Gap analysis & improvement backlog di [[../06 AI & Integrasi/03 - Test Cases & Hasil]]
- [x] **[BUG-001] Seed kategori default saat registrasi** — FIXED: Menjalankan `DefaultCategoriesSeeder` (12 kategori global: 8 expense + 4 income). Validasi `category_id` di `TransactionController` juga diperketat — hanya menerima kategori global atau milik user sendiri.
- [x] **[BUG-002] Migrate `middleware.js` → `proxy.js`** — FIXED: Renamed file dan fungsi dari `middleware` → `proxy` sesuai konvensi Next.js 16+. Deprecation warning sudah hilang dari startup log.
- [x] **[BUG-003] Tambah notifikasi verifikasi WhatsApp di Dashboard** — FIXED: Menambahkan banner/reminder di bagian atas Dashboard (jika `phone_verified` false) yang mengarahkan user ke halaman `/settings` untuk memicu verifikasi WhatsApp OTP.
- [x] **[BUG-004] WhatsApp Bridge Bad MAC Connection Status** — FIXED: Mendeteksi error dekripsi Bad MAC melalui console.error dan memaksa status bridge terlaporkan "Tidak Terhubung" agar user tahu sesi harus di-reconnect.
- [x] **[BUG-005] Infinite Redirect Loop di Frontend** — FIXED: Menghapus redirect paksa dari api.js 401 response dan menyatukannya di AuthContext menggunakan router.replace (client-side routing) agar tidak memicu reload loop.
- [x] **[BUG-006] Profil Kosong di Settings setelah Login/Register** — FIXED: Mengubah halaman login & register agar memanggil auth helper (`login`/`register`) dari `useAuth()` hook dan bukan client API langsung, sehingga data profil di AuthContext langsung tersinkronisasi global tanpa perlu manual refresh.
- [x] **[BUG-009] Security Vulnerability Auto-Link Akun WA (Unregistered Sender)** — FIXED: Menghapus fallback `User::first()` yang berbahaya. Pengirim tak dikenal ditolak (null) & dipandu registrasi.
- [x] **[BUG-010] Spam Pesan dari Pengguna Tidak Terdaftar (Unregistered Senders)** — FIXED: Mengimplementasikan `UnregisteredUserService` dengan 24-hour rate limiting window (greeting 1x per 24 jam per nomor).
- [x] **[BUG-011] Status Bad MAC Latching pada WhatsApp Bridge** — FIXED: Menghapus permanent error latching pada `index.js` bridge sehingga status offline palsu di web teratasi.
- [x] **[BUG-012] Format Tabel ASCII Berantakan di Mobile & Routing Keyword Budget/Dompet** — FIXED: Redesain balasan WA menjadi mobile-responsive bullet-list emoji, memperluas keyword `sisa budget` & `sis budget`, dan mencegat pesan tanpa nominal.
- [x] **[BUG-013] Inkonsistensi Template Kandidat Hapus & Fitur Hapus Semua Transaksi** — FIXED: Menyertakan template emoji bertingkat `1️⃣`-`🔟` pada daftar kandidat hapus/edit, menambah dukungan query rentang tanggal & limit, dan menambahkan fitur *Hapus Semua Transaksi* dengan *strict confirmation prompt*.
- [x] **[BUG-014] Rate Limiter Nomor Tidak Dikenal Gagal (Spam Balasan)** — FIXED: Mengubah `user_id` menjadi `nullable` di tabel `chat_messages` dan memperbaiki *race condition* agar database menyimpan log sebelum mengirim pesan WhatsApp.
- [ ] **[BUG-015] Chatbot Mengirim `{}` ke WhatsApp saat Gemini Rate Limit Habis** — `GeminiProvider::executeGeminiRequest()` me-return string `'{}'` ketika semua fallback model habis kuota (HTTP 429), dan string ini langsung dikirim ke WhatsApp tanpa validasi. Fix: tambahkan guard di `callGemini()` dan `logAndSend()` agar mengembalikan pesan error yang ramah pengguna.
- [ ] **[BUG-016] Intent Classifier & Entity Extractor Gagal pada Frasa Bahasa Indonesia Tertentu** — Ditemukan dari stress test 100 transaksi: 7 kasus *intent unclear* (frasa: `sedekah`, `print skripsi`, `ngopi`, `bayar spotify`, dll.) dan 3 kasus *entity extraction failed* (`bayar fotokopi tugas`, `dapet duit freelance`). Fix: tambah *few-shot examples* di System Prompt Gemini dan investigasi routing bug (pesan dengan confidence 0.99 tetap masuk handler `unclear`).

---

## 🟡 Prioritas Sedang

- [x] **Algoritma `BalancePredictionService`** — top 10% outlier trimming, dynamic lookback (7-30 hari), trend classification (burning/saving/stable/unknown), terintegrasi ke prompt WhatsApp AI & dashboard Next.js
- [x] **Struktur Next.js dashboard yang lebih detail** — Menggunakan App Router, AuthContext, lib/api client, dan Recharts untuk visualisasi data
- [x] **Integrasi API Frontend** — menghubungkan halaman Next.js yang ada ke endpoints Laravel yang baru saja kita bangun

---

## 🟢 Prioritas Rendah / Nanti

- [x] **Prompt untuk fitur "Pengingat Cerdas"** — deteksi pola transaksi rutin otomatis (2 bulan berturut-turut), kirim WA H-3 & H-1 via Laravel Scheduler
- [x] **Prompt & logic untuk "Saran Penghematan"** — top 3 kategori terboros bulan ini vs bulan lalu, saran potensial hemat 20%, dipicu via WA chat (intent: savings_advice)
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
- [x] **Gemini Structured Output Refactor** — Migrasi `GeminiProvider` dari prompt-based JSON parsing (regex stripping) ke native `responseMimeType: application/json` + `responseSchema` untuk `classifyIntent`, `extractTransactions`, dan `parseQuery`. Menambah field baru: `notes`, `needs_clarification`, `clarification_reason` pada `ParsedTransactionDTO` dan kolom `notes` pada tabel `transactions`. `ClaudeProvider` juga diupdate untuk paritas prompt.

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

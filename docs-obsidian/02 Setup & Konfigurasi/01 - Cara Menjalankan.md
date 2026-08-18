---
tags: [setup, running]
---

# 01 — Cara Menjalankan Proyek

> ⚠️ **Pastikan Laragon / MySQL sudah menyala sebelum memulai.**

Project ini terdiri dari **4 layanan** yang harus berjalan bersamaan. Buka terminal terpisah untuk setiap layanan.

---

## Layanan 1 — Backend (Laravel API)

```bash
cd finance-app-api
php artisan serve --port=8000
```

✅ Berjalan di: `http://localhost:8000`

---

## Layanan 2 — Frontend (Next.js Dashboard)

```bash
cd finance-app-web
npm run dev
```

✅ Berjalan di: `http://localhost:3000`

---

## Layanan 3 — WhatsApp Bridge (Node.js)

```bash
cd whatsapp-bridge
npm start
```

✅ Berjalan di: `http://localhost:3001`

> 📱 **Pertama kali:** Scan QR code yang muncul di terminal menggunakan WhatsApp di HP nomor Bot.
> Lihat: [[../05 WhatsApp Bridge/02 - Scan QR & Sesi]]

---

## Layanan 4 — Laravel Queue Worker (Pemroses AI)

```bash
cd finance-app-api
php artisan cache:clear
php artisan config:clear
php artisan queue:listen --tries=3
```

> 💡 Gunakan `queue:listen` (bukan `queue:work`) agar worker tidak mati saat ada error kode.

---

## Urutan Start yang Direkomendasikan

```
1. Laragon (MySQL)        → pastikan MySQL running
2. Backend (Laravel)      → tunggu sampai muncul "Starting Laravel development server"
3. Queue Worker           → jalankan setelah backend ready
4. WhatsApp Bridge        → scan QR jika diperlukan
5. Frontend (Next.js)     → buka http://localhost:3000
```

---

## Akses Aplikasi

| URL | Fungsi |
|---|---|
| `http://localhost:3000` | Web Dashboard (Next.js) |
| `http://localhost:3000/login` | Halaman Login |
| `http://localhost:3000/register` | Halaman Registrasi |
| `http://localhost:3000/settings` | Pengaturan akun & koneksi WA |
| `http://localhost:8000` | API Backend |
| `http://localhost:8000/api/health` | Health check API |
| `http://localhost:3001` | WhatsApp Bridge |

---

## 🔗 Lihat Juga
- [[02 - Konfigurasi Environment]] — Daftar variabel `.env`
- [[03 - Troubleshooting]] — Jika ada masalah saat start

---
tags: [frontend, pages, routing]
---

# 01 — Halaman & Routing (Next.js)

**Framework:** Next.js (App Router)
**URL:** `http://localhost:3000`

---

## 🗺️ Daftar Halaman

| Route | Folder | Fungsi | Auth? |
|---|---|---|---|
| `/` | `app/page.js` | Landing / redirect ke dashboard | ❌ |
| `/login` | `app/login/` | Form login | ❌ |
| `/register` | `app/register/` | Form registrasi akun baru | ❌ |
| `/dashboard` | `app/dashboard/` | Dashboard utama — ringkasan keuangan | ✅ |
| `/transactions` | `app/transactions/` | Daftar & filter transaksi | ✅ |
| `/analytics` | `app/analytics/` | Grafik tren & breakdown kategori | ✅ |
| `/budgets` | `app/budgets/` | Manajemen budget per kategori | ✅ |
| `/categories` | `app/categories/` | Manajemen kategori custom | ✅ |
| `/wallets` | `app/wallets/` | Manajemen dompet | ✅ |
| `/settings` | `app/settings/` | Profil, koneksi WA, subscription | ✅ |

---

## 🔐 Alur Auth

```
User buka URL
  │
  ├── Belum login → redirect ke /login
  │
  └── Sudah login → tampilkan halaman
```

Token Sanctum disimpan di localStorage / cookie.

---

## 📱 Alur Onboarding User Baru

```
1. /register → isi nama, email, nomor WA, password
2. /login → masuk dengan email & password
3. /settings → lihat status koneksi WhatsApp
4. Kirim pesan ke bot dari nomor WA yang didaftarkan
5. Sistem otomatis linking via WA LID
6. Refresh /settings → status "Terhubung"
7. Mulai gunakan bot WA untuk catat keuangan
```

---

## 🧩 Komponen Shared

Lihat: [[02 - Komponen Utama]]

---

## 🔗 Lihat Juga
- [[02 - Komponen Utama]]
- [[../03 Backend/04 - API Endpoints]]
- [[../02 Setup & Konfigurasi/01 - Cara Menjalankan]]

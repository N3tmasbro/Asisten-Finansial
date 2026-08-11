---
tags: [setup, config, env]
---

# 02 — Konfigurasi Environment

## 📁 File `.env` yang Perlu Dikonfigurasi

Terdapat 2 file `.env` dalam proyek ini:

| File | Keterangan |
|---|---|
| `finance-app-api/.env` | **Aktif** — dibaca oleh Laravel & Prisma. Wajib dikonfigurasi |
| `whatsapp-bridge/.env` | Konfigurasi port & URL webhook bridge |

---

## ⚙️ `finance-app-api/.env`

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finance_app        # nama database di MySQL Laragon
DB_USERNAME=root
DB_PASSWORD=                   # biasanya kosong di Laragon
```

### Aplikasi
```env
APP_NAME="Asisten Finansial AI"
APP_ENV=local
APP_KEY=                       # generate dengan: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
```

### Queue (Wajib diubah dari default!)
```env
QUEUE_CONNECTION=database      # BUKAN 'sync' — harus 'database' agar async bekerja
```

### 🤖 AI — Google Gemini
```env
AI_PROVIDER=gemini
GEMINI_API_KEY=AIzaSy...       # Dapatkan dari: https://aistudio.google.com/app/apikey
GEMINI_MODEL=gemini-flash-latest
```

> **Batas API Gemini (Free Tier):**
> - 5 RPM (Requests Per Minute)
> - 20 RPD (Requests Per Day)
> - Efektifnya: **~6 pesan WhatsApp/hari** (karena 1 pesan = 3 API calls)
> 
> Jika limit habis → ubah ke model lite:
> ```env
> GEMINI_MODEL=gemini-3.1-flash-lite   # 500 RPD, jauh lebih banyak
> ```
> Lalu jalankan: `php artisan config:clear`

### WhatsApp Bridge
```env
WHATSAPP_BRIDGE_URL=http://localhost:3001   # URL bridge Node.js
```

---

## ⚙️ `whatsapp-bridge/.env`

```env
PORT=3001                                   # Port bridge berjalan
LARAVEL_WEBHOOK_URL=http://localhost:8000/api/webhook/whatsapp
```

---

## 🔄 Setelah Mengubah `.env`

Selalu bersihkan cache konfigurasi Laravel setelah mengubah `.env`:

```bash
cd finance-app-api
php artisan config:clear
```

---

## 🔗 Lihat Juga
- [[01 - Cara Menjalankan]]
- [[03 - Troubleshooting]]

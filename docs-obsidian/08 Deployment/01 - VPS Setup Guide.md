---
tags: [deployment, vps, prod]
---

# Panduan Deployment VPS (Production)

Dokumen ini berisi panduan cara mem-push proyek ini ke server production (VPS) dan apa saja yang perlu disiapkan, mengingat ada beberapa file lokal yang tidak boleh/bisa di-push ke server.

---

## 🚫 File yang TIDAK Boleh Di-push (Sudah di-Ignore oleh Git)

Sesuai dengan standar keamanan, file-file berikut sudah di-block oleh `.gitignore` dan **tidak akan ikut ter-push** ke repository Anda:

1. **`.env` (di semua folder)** — Berisi API Key, password database, dll.
2. **`node_modules/` & `vendor/`** — File *dependencies* yang ukurannya sangat besar.
3. **`whatsapp-bridge/auth_state/`** — File sesi WhatsApp lokal Anda. Sesi ini hanya berlaku untuk koneksi dari IP lokal Anda.
4. **`finance-app-web/.next/`** — Hasil *build* aplikasi web versi lokal.
5. **`storage/logs/` & `storage/framework/`** — File cache dan log sementara milik Laravel.

---

## 🚀 Langkah-langkah Setup Pertama Kali di VPS

Karena file-file di atas tidak ikut ter-push, Anda harus men-generate ulang file tersebut di VPS. Berikut langkahnya setelah Anda melakukan `git pull` di VPS.

### 1. Persiapan Environment (`.env`)
Anda harus membuat file `.env` baru di masing-masing folder pada VPS:
```bash
# Di folder backend
cd finance-app-api
cp .env.example .env
nano .env  # Isi database production & GEMINI_API_KEY Anda
php artisan key:generate

# Di folder frontend
cd ../finance-app-web
cp .env.local.example .env.local
nano .env.local # Isi NEXT_PUBLIC_API_URL mengarah ke domain backend Anda (misal: https://api.namadomain.com)

# Di folder bridge
cd ../whatsapp-bridge
cp .env.example .env
nano .env # Pastikan API_URL mengarah ke backend Anda
```

### 2. Install Dependencies
Jalankan perintah instalasi di ketiga folder:
```bash
# Backend
cd finance-app-api
composer install --optimize-autoloader --no-dev
php artisan migrate --force

# Frontend
cd ../finance-app-web
npm install
npm run build   # Wajib untuk Next.js production

# WA Bridge
cd ../whatsapp-bridge
npm install
```

### 3. Setup Ulang WhatsApp Bridge
Karena `auth_state` tidak ikut ter-push, Anda **wajib memindai (scan) QR Code ulang** di VPS.
1. Jalankan bridge secara manual pertama kali: `npm start`
2. Buka aplikasi WA di HP Bot Anda, lalu *Linked Devices* -> *Scan QR*.
3. Scan QR code yang muncul di terminal VPS Anda.
4. Setelah berhasil masuk (`Bridge is Ready!`), matikan dengan `Ctrl+C`.

### 4. Menjalankan Layanan secara Permanen (Gunakan PM2/Supervisor)
Di VPS, Anda tidak boleh menggunakan perintah `npm start` atau `php artisan serve` biasa karena akan mati saat Anda menutup terminal. Gunakan **PM2** (Node.js) dan **Supervisor** (Laravel Worker).

**Untuk Next.js & WA Bridge (Via PM2):**
```bash
npm install -g pm2
pm2 start npm --name "finance-web" --cwd "/path/ke/finance-app-web" -- run start
pm2 start npm --name "wa-bridge" --cwd "/path/ke/whatsapp-bridge" -- run start
pm2 save
pm2 startup
```

**Untuk Laravel Backend & Queue Worker:**
Gunakan Nginx/Apache untuk mem-hosting folder `finance-app-api/public`.
Untuk Queue Worker pemroses pesan AI, gunakan **Supervisor** dengan konfigurasi:
```ini
[program:finance-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/ke/finance-app-api/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
```

---

## 🔄 Cara Melakukan Update (Setelah VPS Berjalan)

Jika Anda melakukan perubahan kode di lokal dan nge-push ke repo, jalankan ini di VPS untuk update:

```bash
git pull origin main

# Jika ada update API:
cd finance-app-api
composer install --no-dev
php artisan migrate --force
php artisan queue:restart # PENTING! Agar worker me-load kode AI terbaru

# Jika ada update Web:
cd finance-app-web
npm install
npm run build
pm2 restart finance-web

# Jika ada update WA Bridge:
cd whatsapp-bridge
npm install
pm2 restart wa-bridge
```

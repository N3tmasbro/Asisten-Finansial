---
tags: [whatsapp, bridge]
updated: 2026-08-10
---

# 01 — Cara Kerja WhatsApp Bridge

**Teknologi:** Node.js + Baileys (unofficial WhatsApp library)  
**URL:** `http://localhost:3001`

---

## 🎯 Peran Bridge

Bridge adalah **micro-service terpisah** yang sangat kecil dan sederhana. Tugasnya hanya dua:

1. **Menerima** pesan WA masuk → forward ke webhook Laravel
2. **Mengirim** balasan WA atas perintah dari Laravel

Bridge **tidak** menangani business logic apapun.

---

## 🔄 Alur Komunikasi

```
Pesan masuk dari WA
        │
        ▼
  Baileys (event listener)
        │
        ▼ POST /api/webhooks/whatsapp
  Laravel API Backend
        │
        ├─ Dispatch job ke Queue
        │
        ▼ (setelah pipeline selesai, Laravel request bridge)
  Bridge kirim balasan ke WA
```

---

## 📁 Struktur Folder

```
whatsapp-bridge/
├── src/
│   ├── index.js     Entry point utama — koneksi WA, logging, dan routing
│   ├── webhook.js   Modul forward pesan ke Laravel
│   └── sender.js    Express app untuk terima perintah kirim dari Laravel
├── logs/            📋 Log file harian (bridge-YYYY-MM-DD.log) ← BARU
├── auth_state/      ⚠️ Sesi & kredensial WA tersimpan di sini
│                    (Jangan di-commit ke Git!)
├── .env             Konfigurasi port & URL Laravel
├── .gitignore       Sudah exclude: node_modules/, auth_state/, logs/, .env
├── package.json
└── package-lock.json
```

---

## ⚙️ Konfigurasi (`.env`)

```env
PORT=3001
LARAVEL_WEBHOOK_URL=http://localhost:8000/api/webhooks/whatsapp
BRIDGE_SECRET=your-secret-key-here
```

---

## 📋 File Log Harian

Bridge otomatis menyimpan semua log ke file:
```
whatsapp-bridge/logs/bridge-YYYY-MM-DD.log
```

**Cara monitoring log real-time (tanpa buka terminal npm start):**
```powershell
# Lihat log secara real-time (tail -f style)
Get-Content d:\Gawe\Proyek\keuangan\whatsapp-bridge\logs\bridge-2026-08-10.log -Wait -Tail 30

# Filter error saja
Select-String "\[ERROR\]" d:\Gawe\Proyek\keuangan\whatsapp-bridge\logs\bridge-2026-08-10.log
```

**Manfaat:** Kamu bisa cek riwayat koneksi, error, dan pesan yang masuk tanpa perlu menjalankan bridge secara interaktif.

---

## 🚀 Cara Menjalankan

**Jika ingin memonitor live di terminal:**
```powershell
cd d:\Gawe\Proyek\keuangan\whatsapp-bridge
npm start
```

**Jika bridge sudah berjalan tapi perlu restart (tanpa QR ulang):**
```powershell
# Kill proses yang pakai port 3001, lalu restart
Stop-Process -Id (Get-NetTCPConnection -LocalPort 3001 -EA SilentlyContinue).OwningProcess -Force -EA SilentlyContinue
npm start
```

---

## ⚠️ Peringatan Penting

> **Baileys adalah library tidak resmi.** Menggunakannya berisiko nomor WA diblokir oleh WhatsApp jika:
> - Volume pesan terlalu tinggi
> - Digunakan untuk spam
> - Terlalu banyak akun bot dari satu server

**Rencana migrasi ke produksi:** Gunakan WhatsApp Business API resmi (Twilio / 360dialog / Wati) sebelum launch publik.

---

## 🔗 Lihat Juga
- [[02 - Scan QR & Sesi]]
- [[../02 Setup & Konfigurasi/03 - Troubleshooting]]
- [[../01 Gambaran Umum/02 - Tech Stack]]

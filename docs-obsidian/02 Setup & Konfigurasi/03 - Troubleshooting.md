---
tags: [setup, troubleshooting]
---

# 03 — Troubleshooting

Kumpulan masalah umum yang sering muncul dan cara mengatasinya.

---

## ❌ Masalah 1: Pesan WA Tidak Dibalas / Tidak Masuk ke Database

**Gejala:**
Bot WA online dan tersambung, tapi pesan tidak direspons. Log di terminal WhatsApp Bridge menampilkan:
```
Failed to decrypt message with any known session...
MessageCounterError: Key used already or never filled
```

**Penyebab:**
Bot sempat offline cukup lama sehingga sesi enkripsi E2EE dengan WhatsApp server menjadi kadaluarsa/korup (*stale session*).

**Solusi:**
```powershell
# 1. Matikan proses WhatsApp Bridge (Ctrl+C)

# 2. Hapus folder sesi
cd whatsapp-bridge
Remove-Item -Path "auth_state" -Recurse -Force

# 3. Jalankan ulang dan scan QR baru
npm start
```

---

## ❌ Masalah 2: Error `EADDRINUSE :::3001`

**Gejala:**
```
Error: listen EADDRINUSE: address already in use :::3001
```

**Penyebab:**
Ada proses WhatsApp Bridge lama yang masih berjalan di background dan memakai port 3001.

**Solusi:**
```powershell
# Cari proses yang memakai port 3001
netstat -ano | findstr :3001

# Matikan proses (ganti XXXX dengan PID yang ditemukan)
taskkill /PID XXXX /F

# Atau: matikan background task di IDE sebelum start ulang
```

---

## ❌ Masalah 3: Queue Worker Langsung Berhenti Tanpa Error

**Gejala:**
Saat menjalankan `php artisan queue:work --tries=3`, perintah langsung selesai (exit) tanpa output apapun.

**Penyebab:**
Ada sinyal `queue:restart` usang yang tersimpan di cache. Saat worker hidup, ia langsung membaca perintah untuk mati.

**Solusi:**
```bash
cd finance-app-api
php artisan cache:clear
php artisan config:clear
php artisan queue:listen --tries=3 --sleep=3
```

> 💡 Gunakan `queue:listen` sebagai alternatif yang lebih tangguh — worker tidak pernah mati meski ada error kode.

---

## ❌ Masalah 4: Error 429 — AI Gemini Limit Habis

**Gejala:**
Log Queue Worker menampilkan error `429 Too Many Requests`. Bot berhenti merespons dengan benar.

**Penyebab:**
Kuota harian Google AI Studio untuk model standard habis (20 RPD = ~6 pesan WA efektif/hari).

**Solusi:**
Edit `finance-app-api/.env`:
```env
GEMINI_MODEL=gemini-3.1-flash-lite   # 500 RPD vs 20 RPD standard
```

Kemudian:
```bash
php artisan config:clear
# restart queue worker
```

---

## ❌ Masalah 5: `php artisan` Tidak Dikenali

**Penyebab:** PHP tidak ada di PATH sistem, atau Laragon belum aktif.

**Solusi:**
1. Pastikan Laragon sudah berjalan
2. Gunakan terminal Laragon (sudah otomatis set PATH)
3. Atau tambahkan PHP ke PATH Windows secara manual

---

## ✅ Checklist Debug Cepat

Jika bot tidak merespons, cek urutan ini:

- [ ] Laragon/MySQL menyala?
- [ ] `php artisan serve` berjalan tanpa error di port 8000?
- [ ] Queue Worker (`queue:listen`) berjalan?
- [ ] WhatsApp Bridge (`npm start`) berjalan?
- [ ] QR Code sudah di-scan & status "Connected"?
- [ ] `QUEUE_CONNECTION=database` di `.env` (bukan `sync`)?
- [ ] `GEMINI_API_KEY` valid dan limit belum habis?
- [ ] `php artisan config:clear` sudah dijalankan setelah ubah `.env`?

---

## 🔗 Lihat Juga
- [[01 - Cara Menjalankan]]
- [[02 - Konfigurasi Environment]]
- [[../05 WhatsApp Bridge/02 - Scan QR & Sesi]]

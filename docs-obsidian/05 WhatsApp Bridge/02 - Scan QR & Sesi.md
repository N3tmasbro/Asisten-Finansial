---
tags: [whatsapp, bridge, qr, session]
---

# 02 — Scan QR & Manajemen Sesi

---

## 📱 Cara Pertama Kali Menghubungkan Bot WA

1. Jalankan bridge: `npm start` di folder `whatsapp-bridge/`
2. Terminal akan menampilkan **QR Code** (berupa gambar ASCII / karakter di terminal)
3. Buka WhatsApp di HP nomor Bot
4. Tap **Perangkat Tertaut** → **Tautkan Perangkat**
5. Scan QR Code yang tampil di terminal
6. Tunggu hingga muncul pesan "Connected" atau "Authenticated"
7. Bridge siap menerima & mengirim pesan

---

## 💾 Penyimpanan Sesi

Setelah scan QR, kredensial sesi disimpan di:
```
whatsapp-bridge/auth_state/
```

> ⚠️ **Folder ini sudah ada di `.gitignore`** — jangan pernah di-commit karena berisi kunci enkripsi pribadi akun WA Bot.

---

## 🔄 Sesi Kadaluarsa (Stale Session)

**Gejala:** Bot online tapi pesan tidak dibalas. Error di terminal:
```
Failed to decrypt message with any known session...
MessageCounterError: Key used already or never filled
```

**Kapan terjadi:** Bot offline cukup lama (biasanya >1-2 hari tanpa koneksi).

**Solusi — Reset Sesi:**
```powershell
# 1. Matikan bridge (Ctrl+C)

# 2. Hapus folder sesi
cd whatsapp-bridge
Remove-Item -Path "auth_state" -Recurse -Force

# 3. Start ulang dan scan QR baru
npm start
```

---

## 🔁 Tips Menjaga Sesi Tetap Valid

- Jangan matikan bridge terlalu lama (>1 hari)
- Jika menggunakan VPS/server, gunakan PM2 atau Supervisor untuk menjaga bridge tetap berjalan
- Simpan backup `auth_state` secara periodik (di tempat aman, bukan Git)

---

## 🔗 Lihat Juga
- [[01 - Cara Kerja Bridge]]
- [[../02 Setup & Konfigurasi/03 - Troubleshooting]]

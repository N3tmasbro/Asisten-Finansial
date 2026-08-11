---
tags: [overview, vision]
---

# 01 — Visi & Konsep Produk

> Bukan sekadar aplikasi pencatat pengeluaran. Ini adalah **asisten finansial personal** yang bisa diajak ngobrol.

---

## 🎯 Ide Inti

Pengalaman penggunaan dirancang terasa seperti **"mengobrol dengan akuntan pribadi"**, bukan mengisi formulir.

**Contoh interaksi:**
```
User: "isi bensin 200 ribu"
Bot:  "Sip! Pengeluaran Rp200.000 untuk Transport udah dicatat ✅"

User: "bulan ini aku habis berapa buat makan?"
Bot:  "Bulan Agustus kamu udah keluar Rp1.240.000 buat Makan & Minum — 
       naik 12% dibanding Juli lho 🍜"
```

---

## 🔑 Diferensiator Utama

| Aspek | Pendekatan |
|---|---|
| **Interface Utama** | WhatsApp (zero friction, sudah jadi kebiasaan) |
| **Interface Sekunder** | Web dashboard (analitik detail, monitoring) |
| **Input** | Natural language — tidak ada form, tidak ada pilih kategori manual |
| **AI** | Bukan chatbot biasa — AI sebagai orkestrator, DB sebagai source of truth |

---

## 🎓 Tujuan Ganda Proyek

1. **Portofolio** — Showcase kemampuan: Laravel, AI/NLP, Database Design, Real-time Analytics
2. **Bisnis nyata** — Jika respons pengguna awal positif → dikembangkan jadi produk SaaS

---

## ⚡ Fitur Unggulan

### Via WhatsApp
- **Input Natural Language** — `"beli kopi 20rb sama parkir 5rb"` → 2 transaksi otomatis
- **Tanya Jawab Finansial** — `"bulan ini aku habis berapa buat makan?"`
- **Analisis Kebiasaan** — `"pengeluaranmu untuk kopi naik 35% dibanding bulan lalu"`
- **Prediksi Saldo** — `"dengan pola saat ini, saldo habis tanggal 26"`
- **Saran Penghematan** — `"jika kurangi makan di luar 20%, bisa tabung tambahan Rp500rb/bulan"`
- **Auto-deteksi Kategori** — AI otomatis menentukan kategori, tidak perlu pilih manual
- **Koreksi Pasca-Input** — `"eh yang tadi salah kategorinya"` → AI tahu yang mana

### Via Web Dashboard
- Grafik & tren pengeluaran
- Breakdown kategori detail
- Export laporan (CSV)
- Manajemen kategori custom & budget
- History transaksi lengkap (termasuk yang perlu direview)
- Pengaturan koneksi WhatsApp & subscription

---

## 🧱 Prinsip Desain

1. **Solo dev** → hindari kompleksitas prematur
2. **Zero friction** → tidak ada konfirmasi kategori manual; koreksi dilakukan setelah fakta
3. **AI tidak pernah mengarang angka** → DB selalu jadi source of truth
4. **Provider-agnostic** → WhatsApp & AI provider dibungkus interface untuk mudah migrasi
5. **Chat adalah nyawa produk** → web dashboard hanya pelengkap

---

## 🔗 Lihat Juga
- [[02 - Tech Stack]] — Mengapa Laravel, Next.js, dan Baileys dipilih
- [[03 - Arsitektur Sistem]] — Diagram alur data

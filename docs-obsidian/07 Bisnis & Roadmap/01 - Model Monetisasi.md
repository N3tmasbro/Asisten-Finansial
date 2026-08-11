---
tags: [business, monetization]
---

# 01 — Model Monetisasi

> ⚠️ **Status: Rencana, belum final.** Perlu divalidasi setelah ada data pengguna nyata.

---

## 💰 Tier Subscription (Rencana)

| Tier | Harga | Fitur |
|---|---|---|
| **Free** | Gratis | Input unlimited, Q&A dasar, tanpa analitik/prediksi lanjutan |
| **Starter** | TBD | + Trend analysis, prediksi saldo sederhana, export CSV |
| **Pro** | TBD | + Saran penghematan AI, smart reminder, multi-wallet |
| **Business** | TBD | + Multi-user, forecasting jangka panjang, API access |

---

## 📊 Struktur Biaya yang Perlu Diperhitungkan

Sebelum finalisasi harga, hitung estimasi:

1. **WhatsApp Business API (resmi)** — bersifat *per-conversation*
   - Harga berbeda per negara dan per jenis conversation (user-initiated vs business-initiated)
   - Ini adalah biaya variabel yang signifikan dan *harus* masuk ke kalkulasi margin

2. **AI API (Gemini/OpenAI)** — bersifat *per-token*
   - Estimasi: 3 API calls per pesan WA
   - Dengan tier berbayar, harus pakai model berbayar (bukan free tier)

3. **Hosting** — VPS/server untuk Laravel + Node.js + Database

---

## 🛣️ Rencana Migrasi WhatsApp Provider

| Fase | Provider | Trigger |
|---|---|---|
| **Dev/Testing** | Baileys (gratis, unofficial) | Saat ini |
| **Early Beta** | Baileys (terbatas teman) | Hingga ±50 user |
| **Produksi** | Twilio / 360dialog / Wati (resmi) | Sebelum launch publik |

> Kapan tepatnya migrasi? Belum ditentukan angka pasti (jumlah user / volume pesan). Perlu didiskusikan.

---

## 🔗 Lihat Juga
- [[02 - Backlog & TODO]]
- [[../01 Gambaran Umum/01 - Visi & Konsep]]

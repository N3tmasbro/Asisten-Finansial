---
tags: [overview, tech-stack]
---

# 02 — Tech Stack

## 📦 Stack Utama

| Layer | Teknologi | Alasan |
|---|---|---|
| **Backend API** | Laravel (PHP) | Developer sudah mahir; Eloquent ekspresif untuk query finansial agregat; native DB transaction untuk integritas data uang |
| **Frontend Web** | Next.js (React) | Dashboard analitik & settings; ekosistem React besar untuk hiring masa depan |
| **Interface Chat** | WhatsApp | Sudah jadi kebiasaan pengguna Indonesia; zero friction, tidak perlu install app baru |
| **WA Provider (Dev)** | Baileys (Node.js, unofficial) | Gratis untuk validasi ide & testing dengan teman. ⚠️ Berisiko banned jika volume tinggi |
| **WA Provider (Prod)** | Twilio / 360dialog / Wati | Untuk fase produksi SaaS berbayar |
| **AI Provider (aktif)** | Google Gemini | Cost-effective, ada free tier, output JSON terstruktur |
| **Database** | MySQL | Melalui Laragon di lokal |
| **Auth** | Laravel Sanctum | API token auth untuk Next.js dashboard |
| **Queue** | Laravel Queue + Database driver | Async processing untuk pipeline AI |
| **Realtime Sync** | Polling sederhana (30–60 detik) | User tidak butuh sync instan; WA & web dipakai di waktu berbeda |

---

## 🤔 Mengapa Tidak Node.js Full-stack?

- Solo dev = prioritas kecepatan ship & minim decision fatigue → Laravel convention menang
- Query analitik finansial kompleks (agregasi, GROUP BY) lebih ekspresif di Eloquent
- Integritas data uang → Laravel transaction handling lebih "aman by default"
- Use case AI (extract transaksi + Q&A) tidak butuh orchestration kompleks → HTTP call biasa ke API AI sudah cukup
- Developer sudah mahir PHP → tidak ada biaya belajar dari nol

---

## 🔌 Provider Pattern (Kunci Arsitektur)

Baik **WhatsApp provider** maupun **AI provider** dibungkus di belakang PHP **interface (Contract)**:

```
app/Contracts/
├── AIProviderInterface.php
└── WhatsAppProviderInterface.php
```

**Manfaat:** Migrasi provider di masa depan (Baileys → Twilio, Gemini → Claude) hanya perlu membuat 1 class baru yang implement interface yang sama — **tanpa mengubah business logic sama sekali**.

---

## 📦 Package Laravel Penting

| Package | Fungsi |
|---|---|
| `laravel/sanctum` | Auth API token untuk web dashboard |
| `laravel/horizon` | Monitoring queue (opsional, untuk produksi) |

---

## 🔗 Lihat Juga
- [[01 - Visi & Konsep]]
- [[03 - Arsitektur Sistem]]
- [[../06 AI & Integrasi/01 - AI Provider Pattern]]

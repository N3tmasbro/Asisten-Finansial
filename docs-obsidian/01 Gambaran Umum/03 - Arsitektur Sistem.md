---
tags: [overview, architecture]
---

# 03 — Arsitektur Sistem

## 🗺️ High-Level Flow

```
User
  │
  ▼ (kirim pesan)
WhatsApp
  │
  ▼ (forward via HTTP)
whatsapp-bridge (Node.js + Baileys)
  │
  ▼ (POST ke webhook)
finance-app-api (Laravel)
  │
  ├─► Queue Job dispatched (async)
  │         │
  │         ▼
  │   ChatOrchestratorService
  │         │
  │         ├─► Stage 1: Intent Classifier (AI)
  │         ├─► Stage 2A: Transaction Extractor (AI) ──► Database
  │         ├─► Stage 2B: Query Parser (AI) ──────────► Database (read)
  │         └─► Stage 3: Response Formatter (AI)
  │                   │
  │                   ▼
  │         Balasan dikirim ke WA via Bridge
  │
  └─► REST API tersedia untuk
            │
            ▼
      finance-app-web (Next.js Dashboard)
      (baca data yang sama via REST API)
```

---

## 📁 Struktur Monorepo

```
project-root/  (d:\Gawe\Proyek\keuangan)
├── finance-app-api/      Laravel — Backend, Business Logic, AI Orchestration
│                         → http://localhost:8000
├── finance-app-web/      Next.js — Dashboard, Analitik, Settings
│                         → http://localhost:3000
├── whatsapp-bridge/      Node.js + Baileys — Jembatan Pesan WA
│                         → http://localhost:3001
├── docs-obsidian/        📖 Vault dokumentasi ini
├── spesifikasi-app-finansial-ai.md
└── HOW_TO_START.md
```

---

## 🔄 Alur Data Detail

### Alur Masuk (User → Sistem)

```
1. User kirim pesan WA
2. Baileys (bridge) menerima event
3. Bridge POST ke: POST /api/webhook/whatsapp
4. WhatsAppWebhookController terima payload
5. MessageParserService normalize ke IncomingMessageDTO
6. Dispatch job: ProcessIncomingWhatsAppMessage (masuk ke queue)
7. Queue Worker ambil job → ChatOrchestratorService
8. 3-Stage AI Pipeline berjalan
9. Hasil disimpan ke DB
10. Balasan dikirim ke bridge → user
```

### Alur Keluar (Dashboard → Sistem)

```
1. User buka Next.js di browser
2. Next.js fetch ke REST API (dengan Sanctum token)
3. Controller → Repository → Model → Database
4. Data dikembalikan sebagai JSON Resource
5. Next.js render di UI
```

---

## 🧩 Komponen Utama & Tanggung Jawab

| Komponen | Lokasi | Tanggung Jawab |
|---|---|---|
| `WhatsAppWebhookController` | `Controllers/Webhooks/` | Menerima payload dari bridge |
| `ProcessIncomingWhatsAppMessage` | `Jobs/` | Async job, entry point pipeline AI |
| `ChatOrchestratorService` | `Services/Chat/` | Orkestrator 3-stage AI pipeline |
| `AIProviderInterface` | `Contracts/` | Interface abstrak untuk semua AI provider |
| `GeminiProvider` | `Services/AI/Providers/` | Implementasi konkret untuk Google Gemini |
| `TransactionRepository` | `Repositories/` | Query kompleks untuk transaksi |
| API Controllers | `Controllers/Api/` | CRUD untuk web dashboard |

---

## 🔗 Lihat Juga
- [[../03 Backend/03 - AI Pipeline (3-Stage)]]
- [[../05 WhatsApp Bridge/01 - Cara Kerja Bridge]]
- [[../02 Setup & Konfigurasi/01 - Cara Menjalankan]]

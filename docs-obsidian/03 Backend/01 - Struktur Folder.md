---
tags: [backend, structure]
---

# 01 — Struktur Folder Backend (Laravel)

**Root:** `finance-app-api/`

---

## 📁 Struktur `app/`

```
app/
├── Console/
│   └── Commands/          Scheduled jobs (mis. generate insight bulanan)
│
├── Contracts/             Interface / abstraksi (Provider Pattern)
│   ├── AIProviderInterface.php
│   └── WhatsAppProviderInterface.php
│
├── DTOs/                  Data Transfer Objects — kontrak data antar layer
│   ├── IncomingMessageDTO.php
│   ├── ParsedTransactionDTO.php
│   └── ...
│
├── Enums/                 Enum PHP 8.1+
│   ├── MessageIntent.php       (add_transaction, query_report, correction, ...)
│   ├── TransactionType.php     (income, expense)
│   └── SubscriptionTier.php    (free, starter, pro, business)
│
├── Exceptions/            Custom exception handlers
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/           CRUD controllers untuk web dashboard
│   │   │   ├── AuthController.php
│   │   │   ├── TransactionController.php
│   │   │   ├── WalletController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── BudgetController.php
│   │   │   └── AnalyticsController.php
│   │   └── Webhooks/
│   │       └── WhatsAppWebhookController.php   ← entry point dari bridge
│   ├── Middleware/
│   └── Kernel.php
│
├── Jobs/
│   └── ProcessIncomingWhatsAppMessage.php  ← async job utama
│
├── Models/
│   ├── User.php
│   ├── Transaction.php
│   ├── Wallet.php
│   ├── Category.php
│   ├── Budget.php
│   ├── ChatMessage.php
│   ├── WhatsAppSession.php
│   ├── PhoneVerification.php
│   ├── InsightsCache.php
│   └── Subscription.php
│
├── Providers/
│   └── AppServiceProvider.php   ← binding interface ke implementasi konkret
│
├── Repositories/
│   └── TransactionRepository.php   ← query kompleks dipisah dari Model
│
└── Services/
    ├── AI/
    │   └── Providers/
    │       ├── GeminiProvider.php     ← implementasi aktif
    │       └── MockAIProvider.php     ← untuk testing tanpa API call
    ├── Analytics/                     summary, trend, prediksi saldo
    ├── Chat/
    │   └── ChatOrchestratorService.php  ← "otak" utama pipeline
    ├── Transaction/                   create/update logic, category matcher
    └── WhatsApp/
        └── Providers/                 Baileys bridge client
```

---

## 📁 Struktur `database/migrations/`

| Migrasi | Tabel |
|---|---|
| `create_users_table` | `users` — data akun dasar |
| `add_phone_and_tier_to_users_table` | Tambah `phone_number`, `subscription_tier` ke users |
| `add_wa_lid_to_users_table` | Tambah `wa_lid` untuk auto-linking WhatsApp |
| `create_phone_verifications_table` | `phone_verifications` — OTP verification |
| `create_categories_table` | `categories` — default & custom per user |
| `create_wallets_table` | `wallets` — multi-dompet (Cash, Bank, E-wallet) |
| `create_chat_messages_table` | `chat_messages` — log semua chat masuk/keluar |
| `create_transactions_table` | `transactions` — data transaksi keuangan |
| `create_whatsapp_sessions_table` | `whatsapp_sessions` — status koneksi WA |
| `create_budgets_table` | `budgets` — limit bulanan per kategori |
| `create_insights_cache_table` | `insights_cache` — cache hasil analisis AI |
| `create_subscriptions_table` | `subscriptions` — data langganan |
| `create_jobs_table` | `jobs` — queue jobs (driver database) |

---

## 📁 Struktur `routes/`

```
routes/
├── api.php          REST API endpoints (prefix: /api)
└── web.php          Hanya untuk health check / default Laravel
```

---

## 🔗 Lihat Juga
- [[02 - Database & Skema]]
- [[03 - AI Pipeline (3-Stage)]]
- [[04 - API Endpoints]]
- [[05 - Queue & Jobs]]

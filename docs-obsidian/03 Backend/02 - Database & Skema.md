---
tags: [backend, database, schema]
updated: 2026-08-10
---

# 02 — Database & Skema

**Database:** MySQL (via Laragon)

---

## 📊 Diagram Relasi (ERD Ringkas)

```
users
  │
  ├── phone_verifications (OTP)
  ├── wallets (multi-dompet)
  ├── categories (custom per user)
  ├── budgets (limit per kategori)
  ├── whatsapp_sessions
  ├── chat_messages ← idempotency + konfirmasi + pilihan kandidat
  ├── subscriptions
  └── transactions (SoftDeletes)
         └── chat_messages (via chat_message_id)
```

---

## 🗃️ Detail Tabel

### `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `name` | string | Nama lengkap |
| `email` | string | Email unik |
| `phone_number` | string | Nomor WA (format lokal: `0858...`) |
| `wa_lid` | string | WhatsApp LID untuk auto-linking |
| `subscription_tier` | enum | `free`, `starter`, `pro`, `business` |
| `password` | string | Hash bcrypt |

---

### `transactions`
Tabel paling penting — indeks kritis: `(user_id, transaction_date)`

> ⚠️ **SoftDeletes aktif** — transaksi yang dihapus via WA tidak benar-benar terhapus dari DB, melainkan diisi kolom `deleted_at`. Semua query otomatis mengecualikan baris dengan `deleted_at IS NOT NULL`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK → users | |
| `wallet_id` | FK → wallets | |
| `category_id` | FK → categories | |
| `chat_message_id` | FK → chat_messages | Untuk grouping multi-transaksi & koreksi |
| `type` | enum | `income`, `expense` |
| `amount` | decimal(15,2) | Jumlah transaksi |
| `description` | string | Deskripsi (hasil parsing AI) |
| `raw_input` | text | **Teks asli user** — untuk audit AI |
| `ai_confidence` | float | Tingkat keyakinan AI (0.0 – 1.0) |
| `is_reviewed` | boolean | `false` jika AI confidence rendah |
| `corrected_at` | timestamp | null jika belum dikoreksi |
| `transaction_date` | date | Tanggal transaksi (bukan created_at) |
| `deleted_at` | timestamp | **SoftDelete** — null jika aktif |

---

### `chat_messages`
Log seluruh chat masuk/keluar — indeks kritis: `(user_id, created_at)`

Tabel ini memiliki **3 fungsi kritis:**
1. **Audit log** — riwayat semua pesan masuk & keluar
2. **Idempotency** — cek `wa_message_id` sebelum proses agar tidak double
3. **State machine** — menyimpan `pending_confirmation` dan `pending_selection` untuk multi-turn conversation

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK → users | |
| `direction` | enum | `incoming`, `outgoing` |
| `body` | text | Isi pesan |
| `intent` | string | Intent terdeteksi (`add_transaction`, `correction`, dll) |
| `wa_message_id` | string | ID unik dari WhatsApp (untuk idempotency) |
| `pending_confirmation` | JSON | State konfirmasi hapus yang menunggu jawaban |
| `pending_selection` | JSON | State pilihan kandidat yang menunggu jawaban |

**Struktur `pending_confirmation`:**
```json
{
  "action": "delete_transaction",
  "transaction_id": 123,
  "metadata": { "amount": 80000, "description": "isi bensin" },
  "expires_at": "2026-08-10 22:00:00"
}
```

**Struktur `pending_selection`:**
```json
{
  "action": "correction",
  "candidates": [
    { "id": 19, "description": "beli kopi", "amount": 25000 },
    { "id": 12, "description": "Beli kopi", "amount": 10000 }
  ],
  "changes": { "category": "Hiburan" },
  "expires_at": "2026-08-10 22:00:00"
}
```

---

### `wallets`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK → users | |
| `name` | string | Nama dompet (Cash, BCA, GoPay, dll) |
| `type` | enum | `cash`, `bank`, `ewallet` |
| `balance` | decimal(15,2) | Saldo saat ini — **otomatis diupdate saat transaksi dibuat/dikoreksi/dihapus** |
| `is_default` | boolean | Dompet default untuk transaksi baru |

---

### `categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK → users (nullable) | null = kategori global/default |
| `name` | string | Nama kategori |
| `type` | enum | `income`, `expense` |
| `is_active` | boolean | |

**Kategori Default (Global):**
- Expense: Makan & Minum, Transport, Belanja, Tagihan, Hiburan, Kesehatan, Pendidikan, Lainnya
- Income: Gaji, Bonus/THR, Freelance/Sampingan, Lainnya

---

### `budgets`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `user_id` | FK → users | |
| `category_id` | FK → categories | |
| `amount` | decimal(15,2) | Limit bulanan (kolom `amount`, bukan `amount_limit`) |
| `month` | integer | Bulan (1-12) |
| `year` | integer | Tahun |

---

### `insights_cache`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `user_id` | FK → users | |
| `insight_type` | string | Tipe analisis |
| `period` | string | Periode (misal: `2026-08`) |
| `data` | JSON | Hasil analisis yang di-cache |
| `expires_at` | timestamp | Kapan cache kadaluarsa |

---

### `phone_verifications`
OTP untuk verifikasi & penautkan nomor WA ke akun.

---

### `whatsapp_sessions`
Status koneksi & provider yang dipakai per user.

---

## 🔑 Indeks Penting

| Tabel | Indeks | Tujuan |
|---|---|---|
| `transactions` | `(user_id, transaction_date)` | Query analitik per periode |
| `transactions` | `deleted_at` | SoftDeletes filter |
| `chat_messages` | `(user_id, created_at)` | Audit log & korelasi pesan |
| `chat_messages` | `wa_message_id` | Idempotency check (unique) |

---

## 🔗 Lihat Juga
- [[01 - Struktur Folder]]
- [[../06 AI & Integrasi/02 - Prompt & Pipeline Detail]]

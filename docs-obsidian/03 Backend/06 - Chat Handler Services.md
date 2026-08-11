---
tags: [backend, services, chat]
updated: 2026-08-10
---

# 06 — Chat Handler Services

Dokumentasi semua service yang menangani intent dari pesan WhatsApp.

---

## 🗂️ Daftar Handler

| Handler | Intent | File |
|---|---|---|
| `ChatOrchestratorService` | (semua) — routing utama | `Services/Chat/ChatOrchestratorService.php` |
| `CandidateResolverService` | correction, delete | `Services/Chat/CandidateResolverService.php` |
| `CorrectionHandlerService` | correction | `Services/Chat/CorrectionHandlerService.php` |
| `DeleteHandlerService` | delete_transaction | `Services/Chat/DeleteHandlerService.php` |
| `InspectHandlerService` | inspect_records | `Services/Chat/InspectHandlerService.php` |
| `ManageRecordHandlerService` | manage_records | `Services/Chat/ManageRecordHandlerService.php` |

---

## 🎭 ChatOrchestratorService

**Entry point** dari semua pesan yang masuk. Bertanggung jawab atas:

1. **Idempotency check** — abaikan `wa_message_id` yang sudah ada
2. **Pending confirmation check** — jika ada konfirmasi hapus yang menunggu
3. **Pending selection check** — jika ada pilihan kandidat yang menunggu
4. **Classify intent** — panggil AI untuk menentukan jenis pesan
5. **Route ke handler** — delegate ke handler yang sesuai
6. **Save chat log** — simpan pesan masuk + balasan ke `chat_messages`
7. **Send reply** — kirim balasan ke WhatsApp via bridge

---

## 🔍 CandidateResolverService

Digunakan bersama oleh `CorrectionHandlerService` dan `DeleteHandlerService`.

**Algoritma pencarian:**
1. Ambil transaksi terbaru user (30 hari terakhir)
2. Score setiap transaksi berdasar kecocokan keyword dari pesan user:
   - Match deskripsi (fuzzy) → +3 poin
   - Match kategori → +2 poin
   - Match nominal → +2 poin
   - Semakin baru transaksi → +1 poin (decay)
3. Sort by score DESC
4. Return semua dengan score ≥ threshold

**Output:**
- `1 kandidat` → langsung proses
- `>1 kandidat` → simpan ke `pending_selection`, tampilkan numbered list ke user
- `0 kandidat` → minta user lebih spesifik

---

## ✏️ CorrectionHandlerService

Mengubah field transaksi yang sudah ada.

**Field yang bisa dikoreksi:**
| Field | Contoh pesan |
|---|---|
| `amount` | "yang bensin harusnya 80rb" |
| `category` | "yang kopi harusnya Hiburan" |
| `wallet` | "yang tadi harusnya pakai BCA" |
| `description` | "yang tadi ganti deskripsinya jadi parkir" |
| `type` | "yang tadi harusnya income" |
| `transaction_date` | "yang tadi harusnya tanggal kemarin" |

**Side effect:** Saat nominal atau wallet berubah, saldo wallet **otomatis di-rekalkulasi** (saldo lama dikembalikan, saldo baru dikurangkan/ditambahkan).

---

## 🗑️ DeleteHandlerService

Menghapus transaksi dengan **soft delete** — data tidak benar-benar hilang dari database.

**Alur:**
```
User: "hapus yang bensin tadi"
  │
  ▼
CandidateResolverService.resolve()
  │
  ├── 0 kandidat → "Tidak ketemu, coba lebih spesifik"
  ├── >1 kandidat → pending_selection (pilih dulu)
  └── 1 kandidat → simpan pending_confirmation, tampilkan preview:
                   "isi bensin — Rp80.000 — Cash — 10 Aug. Yakin hapus? Balas 'ya'"
  │
User: "ya"
  │
  ▼
Transaction::delete() → soft delete (deleted_at diisi)
Wallet balance += amount (saldo dikembalikan)
```

**TTL konfirmasi:** 10 menit. Jika tidak dijawab, state dihapus dan user perlu kirim ulang.

---

## 🔎 InspectHandlerService

**0 AI calls** — langsung query database.

**Sub-intent yang ditangani:**

```
"saldo"     / "cek saldo"         → wallet_balances  
"daftar wallet"                   → list_wallets  
"transaksi terakhir" / "riwayat"  → recent_transactions (5 terakhir)
"budget [kategori]"               → budget_status
"daftar kategori"                 → list_categories
```

**Deteksi sub-intent:** keyword matching, bukan AI.

---

## ⚙️ ManageRecordHandlerService

Membuat atau mengubah entitas master (wallet, kategori, budget).

**Action yang didukung:**

| Action | Contoh pesan |
|---|---|
| `create_wallet` | "buat wallet Dana", "tambah dompet GoPay" |
| `rename_wallet` | "rename wallet BCA jadi BCA Digital" |
| `create_category` | "buat kategori Investasi" |
| `rename_category` | "ganti nama kategori Transport jadi Transportasi" |
| `create_budget` | "buat budget makan 2 juta" |
| `update_budget` | "budget makan naikkan jadi 2,5 juta" |

**Jika entitas sudah ada:** tampilkan pesan informatif (tidak error).
**Tipe wallet otomatis dideteksi:** nama mengandung "GoPay/OVO/Dana/DANA/ShopeePay" → `ewallet`, "BCA/BRI/Mandiri/BNI" → `bank`, lainnya → `cash`.

---

## 🔗 Lihat Juga
- [[03 - AI Pipeline (3-Stage)]]
- [[02 - Database & Skema]]
- [[../06 AI & Integrasi/01 - AI Provider Pattern]]

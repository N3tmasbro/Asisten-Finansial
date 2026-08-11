---
tags: [backend, ai, pipeline]
updated: 2026-08-10
---

# 03 — AI Pipeline (Multi-Intent)

> Dipisah menjadi tahap terpisah (bukan 1 prompt raksasa) agar lebih **akurat**, lebih mudah **di-debug**, dan **mencegah AI berhalusinasi angka**.
> 
> 🔑 **Prinsip utama:** Semua angka finansial *harus* berasal dari database. AI hanya bertugas menerjemahkan ke bahasa natural.

---

## 🔄 Alur Pipeline

```
Pesan WA masuk
     │
     ▼
┌──────────────────────────────────┐
│  Idempotency Check (wa_message_id)│  → Abaikan duplikat
└──────────────────────────────────┘
     │
     ▼
┌──────────────────────────────────┐
│  Pending Confirmation Check?     │  → Jika ada konfirmasi hapus yang menunggu
└──────────────────────────────────┘
     │
     ▼
┌──────────────────────────────────┐
│  Pending Selection Check?        │  → Jika ada pilihan kandidat yang menunggu
└──────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────┐
│  Stage 1: Intent Classifier     │  → Tentukan jenis pesan (1 API call)
└─────────────────────────────────┘
     │
     ├──── add_transaction ──────► TransactionService::create()
     │
     ├──── correction ────────────► CorrectionHandlerService
     │                                   → CandidateResolverService (cari tx)
     │                                   → Jika 1 hasil: langsung koreksi
     │                                   → Jika >1 hasil: tampilkan pilihan (pending_selection)
     │
     ├──── delete_transaction ────► DeleteHandlerService
     │                                   → CandidateResolverService (cari tx)
     │                                   → Tampilkan konfirmasi (pending_confirmation)
     │                                   → Setelah "ya": soft delete + balance reversal
     │
     ├──── inspect_records ───────► InspectHandlerService
     │                                   → TANPA AI call, langsung query DB
     │                                   → wallet balances, recent tx, budget status, dll
     │
     ├──── manage_records ────────► ManageRecordHandlerService
     │                                   → Buat/rename wallet, kategori, budget
     │                                   → parseFinancialCommand() (1 AI call)
     │
     ├──── query_report ─────────► Stage 2B: Query Parser
     │                                   → Query database (source of truth)
     │
     └──── greeting/unclear ──────► Respons langsung (formatResponse)
                │
                ▼
     ┌──────────────────────────────┐
     │  Stage 3: Response Formatter │  → Ubah data jadi kalimat natural (1 API call)
     └──────────────────────────────┘
                │
                ▼
         Kirim balasan ke WA
```

---

## Stage 1 — Intent Classifier

**Tugas:** Menentukan jenis pesan sebelum diproses lebih lanjut.

**Output format:**
```json
{
  "intent": "add_transaction",
  "confidence": 0.95
}
```

**Jenis intent yang dikenali:**

| Intent | Contoh pesan |
|---|---|
| `add_transaction` | "isi bensin 200rb", "beli kopi 20rb sama parkir 5rb" |
| `query_report` | "bulan ini aku habis berapa buat makan?" |
| `correction` | "yang tadi salah", "yang kopi harusnya Hiburan", "ubah yang bensin jadi 80rb" |
| `delete_transaction` | "hapus yang bensin tadi", "batalkan yang kopi" |
| `inspect_records` | "saldo semua wallet", "daftar kategori", "transaksi terakhir", "cek budget makan" |
| `manage_records` | "buat wallet Dana", "buat kategori Investasi", "budget makan naikkan jadi 2,5 juta" |
| `greeting_smalltalk` | "halo", "makasih ya" |
| `unclear` | Pesan tidak dapat dipahami |

**Aturan disambiguation yang penting:**
- Kata `"yang tadi"`, `"harusnya"`, `"salah"` → selalu `correction`
- Kata `"saldo"`, `"daftar"`, `"riwayat"`, `"cek"` → selalu `inspect_records`  
- Kata `"hapus"`, `"delete"`, `"hilangkan"` untuk transaksi → selalu `delete_transaction`
- Kata `"buat"`, `"rename"`, `"naikkan"` untuk wallet/kategori/budget → selalu `manage_records`

---

## Stage 2A — Transaction Extractor

**Berjalan jika:** intent = `add_transaction`

**Karakteristik penting:**
- Selalu mengembalikan **array** transaksi (walau hanya 1 item) untuk mendukung multi-transaksi
- Setiap transaksi punya `confidence` masing-masing
- Kategori diambil dari daftar kategori default + kategori custom user (disisipkan dinamis ke prompt)
- Fallback kategori: `"Lainnya"` jika AI tidak yakin
- Default `type` adalah `expense`, kecuali ada kata kunci income

**Aturan parsing angka:**
```
"20rb" = "20ribu" = "20k" = "20.000" → 20000
```

**Kata kunci income:** "gajian", "dapat", "terima", "masuk", "gaji"

**Output format:**
```json
[
  {
    "description": "Isi bensin",
    "amount": 200000,
    "type": "expense",
    "category": "Transport",
    "wallet": null,
    "confidence": 0.92
  }
]
```

**Penanganan confidence rendah:**
- Transaksi **tetap disimpan** (agar tidak hilang)
- Flag `is_reviewed = false` → user bisa klarifikasi via WA atau review di web dashboard

---

## Stage 2B — Query Parser

**Berjalan jika:** intent = `query_report`

**Penting:** AI **tidak** menjawab langsung — hanya menentukan **parameter query**. Laravel yang menjalankan query sesungguhnya ke database.

**Tipe query yang didukung:**

| Query Type | Contoh pertanyaan |
|---|---|
| `total_by_category` | "habis berapa buat makan bulan ini?" |
| `total_by_period` | "total pengeluaran minggu lalu?" |
| `trend_comparison` | "pengeluaran transport bulan ini vs bulan lalu?" |
| `balance_prediction` | "saldo habis tanggal berapa?" |
| `top_spending` | "kategori apa yang paling boros?" |
| `general_summary` | "ringkasan keuangan bulan ini" |

---

## InspectHandlerService — 0 AI Calls

**Berjalan jika:** intent = `inspect_records`

**Tidak memanggil AI sama sekali** — langsung query database dan format respons dengan template hardcoded.

| Sub-intent | Contoh pesan | Response |
|---|---|---|
| `wallet_balances` | "saldo semua wallet", "cek saldo" | Daftar semua wallet + saldo |
| `recent_transactions` | "transaksi terakhir", "riwayat" | 5 transaksi terbaru |
| `budget_status` | "budget makan", "sisa budget" | Status budget bulan ini |
| `list_wallets` | "daftar wallet" | Nama + saldo semua wallet |
| `list_categories` | "daftar kategori" | Semua kategori aktif |

---

## ManageRecordHandlerService — parseFinancialCommand()

**Berjalan jika:** intent = `manage_records`

Memanggil AI satu kali via `parseFinancialCommand()` untuk mengekstrak:

```json
{
  "action": "create_wallet|rename_wallet|delete_wallet|create_category|rename_category|create_budget|update_budget",
  "target": "Dana",
  "changes": { "wallet_type": "ewallet" },
  "data": {}
}
```

---

## DeleteHandlerService — Confirmation Flow

**Berjalan jika:** intent = `delete_transaction`

1. `CandidateResolverService` mencari transaksi yang cocok
2. Jika 1 kandidat: tampilkan preview + simpan ke `pending_confirmation` (TTL 10 menit)
3. User balas `"ya"/"oke"/"sip"` → eksekusi soft delete + balance reversal
4. User balas `"tidak"/"batal"` → batalkan

**Keyword konfirmasi:**
- ✅ Ya: `ya, iya, oke, yes, setuju, yup, bener, benar, ok, sip, sep`
- ❌ Tidak: `tidak, no, cancel, batal, ga jadi, gjd, g jadi, dk jadi, urung, dak, idak, dk`

---

## CandidateResolverService — Multi-Match Handling

Digunakan oleh `correction` dan `delete_transaction`.

**Prioritas pencarian:**
1. Transaksi terbaru user (last N)
2. Match keyword deskripsi (LIKE)
3. Match kategori
4. Match nominal (jika ada angka di pesan)

**Jika >1 kandidat:** tampilkan numbered list, simpan ke `pending_selection`:
```
1. beli kopi — -Rp25.000 — Makan & Minum — Cash — 10 Aug
2. Beli kopi — -Rp10.000 — Makan & Minum — Cash — 8 Aug

Yang mana? Balas nomor atau jelaskan lebih detail.
```

**Keyword pemilihan:** angka 1-10, atau ordinal Indonesia (pertama, kedua, ketiga, ...)

---

## Stage 3 — Response Formatter

**Tugas:** Mengubah data hasil query (angka asli dari DB) menjadi kalimat Bahasa Indonesia yang **ramah, singkat, dan tidak mengubah/menambah angka**.

**Input:** Data JSON dari database  
**Output:** String kalimat natural

**Contoh:**
```
Input DB: { "total": 1240000, "category": "Makan & Minum", "month": "Agustus" }

Output: "Bulan Agustus kamu udah keluar Rp1.240.000 buat Makan & Minum 🍜"
```

---

## 🔧 Idempotency

Setiap pesan masuk dari WhatsApp punya `wa_message_id` unik.  
Sebelum diproses, sistem cek apakah `wa_message_id` sudah ada di tabel `chat_messages`.  
Jika sudah ada → **abaikan langsung** tanpa memproses ulang (mencegah double-record saat WhatsApp retransmit pesan).

---

## 📁 File Terkait di Codebase

| File | Fungsi |
|---|---|
| `Services/Chat/ChatOrchestratorService.php` | Orkestrator utama, routing semua intent |
| `Services/Chat/CandidateResolverService.php` | Cari kandidat transaksi yang cocok |
| `Services/Chat/CorrectionHandlerService.php` | Handle koreksi transaksi |
| `Services/Chat/DeleteHandlerService.php` | Handle hapus transaksi + konfirmasi |
| `Services/Chat/InspectHandlerService.php` | Handle inspect records (0 AI calls) |
| `Services/Chat/ManageRecordHandlerService.php` | Handle buat/ubah wallet/kategori/budget |
| `Services/AI/Providers/GeminiProvider.php` | Implementasi AI Gemini |
| `Services/AI/Providers/ClaudeProvider.php` | Implementasi AI Claude |
| `Services/AI/Providers/MockAIProvider.php` | Mock untuk testing |
| `Contracts/AIProviderInterface.php` | Interface abstrak AI |
| `Jobs/ProcessIncomingWhatsAppMessage.php` | Entry point async (queue) |
| `DTOs/FinancialCommandDTO.php` | DTO unified untuk manage/delete/correction |
| `DTOs/ParsedTransactionDTO.php` | Kontrak data hasil parsing transaksi |

---

## 🔗 Lihat Juga
- [[../06 AI & Integrasi/01 - AI Provider Pattern]]
- [[../06 AI & Integrasi/02 - Prompt & Pipeline Detail]]
- [[02 - Database & Skema]]

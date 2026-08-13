---
tags: [ai, prompt, gemini, claude]
updated: 2026-08-10
---

# 02 — Prompt & Pipeline Detail

---

## 🤖 Model AI yang Digunakan

| Konfigurasi | Nilai |
|---|---|
| Provider Default | Google Gemini |
| Model Default | `gemini-2.0-flash-lite` |
| Provider Alternatif | Anthropic Claude |
| Format Output | JSON (structured) |
| Bahasa | Indonesia |

---

## ⚡ Limit API (Free Tier — Gemini)

| Metrik | Nilai |
|---|---|
| RPM (Requests Per Minute) | 15 |
| RPD (Requests Per Day) | 1500 (flash-lite) |
| API calls per pesan WA | 1–2 (inspect/manage lebih sedikit) |

> 💡 `inspect_records` = **0 API calls** (langsung query DB)  
> `add_transaction` = **2 API calls** (intent + extract)  
> `manage_records` = **2 API calls** (intent + parseFinancialCommand)  
> `correction`/`delete` = **2 API calls** (intent + parseFinancialCommand)  

---

## 📝 Struktur Prompt per Intent

### Stage 1 — Intent Classifier

```
System: Kamu adalah classifier pesan untuk aplikasi pencatat keuangan.
        Tugasmu HANYA menentukan intent dari pesan user.
        
        Intent yang tersedia:
        - "add_transaction": transaksi BARU yang belum ada
        - "correction": MENGUBAH transaksi yang SUDAH ADA (kata kunci: "yang tadi", "harusnya", "salah")
        - "delete_transaction": MENGHAPUS transaksi (kata kunci: "hapus", "delete", "hilangkan")
        - "inspect_records": MELIHAT data (kata kunci: "saldo", "daftar", "riwayat", "cek")
        - "manage_records": MEMBUAT/MENGUBAH wallet/kategori/budget (kata kunci: "buat", "rename", "naikkan")
        - "query_report": laporan/statistik keuangan
        - "greeting_smalltalk": sapaan ringan
        - "unclear": tidak dapat dipahami

User: {pesan_user}

Format output:
{"intent": "...", "confidence": 0.0-1.0}
```

### Stage 2A — Transaction Extractor

```
System: Kamu adalah parser transaksi keuangan.
        Ekstrak transaksi dari pesan Bahasa Indonesia (termasuk bahasa gaul).
        
        Kategori expense: {daftar_kategori_expense_user}
        Kategori income:  {daftar_kategori_income_user}
        Wallet tersedia:  {daftar_wallet_user}
        Wallet default:   {nama_wallet_default}
        
        Aturan:
        - "20rb" = "20ribu" = "20k" = 20000
        - Default type adalah "expense"
        - Kata kunci income: gaji, dapat, terima, masuk, bonus
        - Selalu return array (walau 1 item)
        - Fallback kategori: "Lainnya"
        - Jika wallet tidak disebutkan, gunakan wallet default

User: {pesan_user}

Format output:
[{"description": "...", "amount": 0, "type": "expense|income", "category": "...", "wallet": "...|null", "confidence": 0.0-1.0}]
```

### Stage 2B — Financial Command Parser (correction/delete/manage)

```
System: Kamu adalah parser perintah keuangan.
        Ekstrak aksi yang diminta user.
        
        Konteks tersedia:
        - Wallets: {daftar_wallet}
        - Kategori: {daftar_kategori}
        
        Action yang tersedia:
        - create_wallet, rename_wallet, delete_wallet
        - create_category, rename_category
        - create_budget, update_budget
        - update_amount, update_category, update_wallet, update_description, update_date, update_type

User: {pesan_user}

Format output:
{
  "action": "...",
  "target": "nama target (wallet/kategori/transaksi)",
  "changes": { "field": "nilai_baru" },
  "data": {}
}
```

### Stage 2C — Query Parser

```
System: Kamu adalah parser query keuangan. 
        JANGAN menjawab pertanyaan langsung.
        Hanya tentukan parameter untuk query database.
        
        Hari ini: {tanggal_hari_ini}

User: {pesan_user}

Format output:
{
  "query_type": "total_by_category|total_by_period|trend_comparison|balance_prediction|top_spending|general_summary",
  "period": "today|current_week|current_month|last_month|custom",
  "category_filter": "nama kategori atau null",
  "date_range": {"from": "YYYY-MM-DD", "to": "YYYY-MM-DD"}
}
```

### Stage 3 — Response Formatter

```
System: Kamu adalah asisten finansial yang ramah.
        Ubah data keuangan berikut menjadi kalimat Bahasa Indonesia yang natural & singkat.
        JANGAN mengubah atau menambah angka dari data yang diberikan.
        Gunakan emoji secukupnya. Maksimal 3 kalimat.

User: Data: {json_hasil_query_db}
      Pertanyaan asal user: {pesan_asli_user}
```

---

## 🔍 Evaluasi Kualitas Parsing

Cara mengevaluasi akurasi AI:

```sql
-- Transaksi dengan confidence rendah (perlu review)
SELECT raw_input, description, ai_confidence, category_id
FROM transactions
WHERE ai_confidence < 0.7
ORDER BY created_at DESC;

-- Log chat untuk audit (incoming + outgoing)
SELECT direction, body, intent, created_at
FROM chat_messages
ORDER BY created_at DESC
LIMIT 20;

-- Cek pending confirmation yang belum dijawab
SELECT body, pending_confirmation, created_at
FROM chat_messages
WHERE pending_confirmation IS NOT NULL
  AND direction = 'outgoing'
ORDER BY created_at DESC;
```

---

## ⚠️ Hal yang Perlu Dikembangkan

- [x] Prompt & logic untuk fitur **Pengingat Cerdas** (deteksi pola rutin, kirim WA H-3 & H-1 otomatis via scheduler)
- [ ] Prompt & logic untuk **Saran Penghematan** (`"jika kurangi X 20%, bisa tabung Y"`)
- [x] Algoritma **Prediksi Saldo** (outlier trimming, dynamic lookback, trend classification)
- [ ] Kumpulan test case variasi kalimat nyata (typo, bahasa gaul, tanpa tanda baca)
- [ ] Expire otomatis `pending_confirmation` & `pending_selection` yang sudah melewati `expires_at`

---

## 🔗 Lihat Juga
- [[01 - AI Provider Pattern]]
- [[../03 Backend/03 - AI Pipeline (3-Stage)]]
- [[../07 Bisnis & Roadmap/02 - Backlog & TODO]]

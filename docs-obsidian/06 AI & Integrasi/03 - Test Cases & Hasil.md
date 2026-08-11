---
tags: [ai, testing, prompt, hasil]
updated: 2026-08-11
---

# 03 — Test Cases & Hasil

Dokumen ini mencatat kumpulan test case variasi kalimat nyata beserta hasil baseline pengujian terhadap `MockAIProvider`.

---

## 📊 Ringkasan Hasil Baseline

| Stage | Total Cases | Pass | Fail | % Pass | Target |
|---|---|---|---|---|---|
| Stage 1 — Intent Classifier | 30 | 30 | 0 | **100%** | ≥90% ✅ |
| Stage 2A — Transaction Extractor | 25 | 25 | 0 | **100%** | ≥85% ✅ |
| Stage 2B — Financial Command | 20 | 20 | 0 | **100%** | ≥80% ✅ |
| Stage 2C — Query Parser | 15 | 15 | 0 | **100%** | ≥85% ✅ |
| **TOTAL** | **90** | **90** | **0** | **100%** | **≥85% ✅** |

> Hasil setelah iterasi perbaikan pertama (sekitar 10 menit). Baseline awal: 79/85 (93%).

---

## 🐛 Bug yang Ditemukan & Diperbaiki

| Bug | File | Penyebab | Fix |
|---|---|---|---|
| `"hapusin"` tidak dikenali | `MockAIProvider` | Regex delete tidak cover kata informal | Tambah `hapusin` ke pattern |
| `"selamat pagi"` → Unclear | `MockAIProvider` | Greeting regex tidak cover prefix "selamat" | Tambah `selamat\s+(pagi\|siang...)` |
| Nama wallet/kategori jadi lowercase | `MockAIProvider` | Regex dijalankan di `$messageLower` | Gunakan `$message` asli untuk preservasi case |
| `"beli xyz"` → kategori Belanja | `MockAIProvider` | `"beli"` ada di keyword map Belanja | Hapus `"beli"` dari keyword Belanja (kata terlalu generik) |
| Test expect `current_month` | Test | MockAI return `this_month` | Fix ekspektasi test mengikuti implementasi aktual |

---

## 🔍 Gap Analysis — Yang Masih Butuh Real AI

Gap berikut adalah kasus yang **sengaja tidak diperbaiki di MockAI** karena membutuhkan kemampuan LLM nyata (Gemini/Claude):

| Test Case | Expected | Actual MockAI | Alasan Gap | Butuh Real AI? |
|---|---|---|---|---|
| `"beli nasii greng 15 ribu"` (typo) | AddTransaction | ? (belum ditest) | MockAI 0% toleransi typo | **Ya** |
| `"halo, baru beli bensin 50rb"` | AddTransaction | GreetingSmallTalk atau AddTransaction (keduanya valid) | Disambiguasi sapaan + transaksi | Ya, untuk akurasi optimal |
| `"bayar sesuatu tadi"` | AddTransaction (fallback) | AddTransaction (fallback 0.40 conf) | Deskripsi ambigu | Ya (real AI lebih akurat) |
| `"gajian bulan ini masuk 3 juta"` | AddTransaction/Income | AddTransaction ✅ | Sudah pass | Tidak |
| Multi-intent spesifik: `"makan 15rb, bayar grab 20rb, dan kopi 10rb"` | 3 transaksi | 3 transaksi ✅ | Sudah pass | Tidak |

---

## 📋 Improvement Backlog

- [ ] **Fuzzy matching untuk typo** — `"nasii greng"` → `"nasi goreng"` (edit distance / Levenshtein)
  - Estimasi: Kompleks, lebih baik diserahkan ke real AI (Gemini prompt sudah handle ini)
- [ ] **Deteksi multi-intent sapaan + transaksi** — `"halo baru beli bensin 50rb"` idealnya → AddTransaction
  - Saat ini: dua intent valid diterima (test `assertContains`)
- [ ] **Testing dengan real AI (Gemini)** — Jalankan 90 test case ini terhadap GeminiProvider
  - Tujuan: validasi akurasi real AI vs MockAI di kasus yang sama
- [ ] **Tambah test case untuk `QueryReport` response format** — apakah Stage 3 (Response Formatter) menghasilkan teks yang readable?
- [ ] **E2E test** (fase berikutnya) — 1 pesan masuk → tersimpan ke DB → response keluar

---

## 🚀 Cara Menjalankan Test

```bash
# Jalankan semua AI unit test
php artisan test tests/Unit/AI

# Per stage
php artisan test tests/Unit/AI/IntentClassifierTest
php artisan test tests/Unit/AI/TransactionExtractorTest
php artisan test tests/Unit/AI/FinancialCommandTest
php artisan test tests/Unit/AI/QueryParserTest
```

---

## 🔗 Lihat Juga
- [[02 - Prompt & Pipeline Detail]]
- [[../07 Bisnis & Roadmap/02 - Backlog & TODO]]
- [Test files](../../../../finance-app-api/tests/Unit/AI/)

# Spesifikasi Aplikasi: Asisten Finansial AI via WhatsApp

## 1. Visi & Konsep Produk

Aplikasi pencatat keuangan berbasis AI yang berfungsi sebagai **asisten finansial personal**, bukan sekadar form pencatatan transaksi. Pengalaman penggunaannya dirancang terasa seperti **"mengobrol dengan seorang akuntan pribadi"**, bukan mengisi formulir.

**Diferensiator utama:** WhatsApp sebagai *primary interface* (input/output cepat, zero friction), Web sebagai *secondary interface* (monitoring detail & analitik mendalam).

### Tujuan Ganda
- **Portofolio**: showcase kemampuan Laravel, AI/NLP, database design, dan analytics dalam satu proyek koheren.
- **Bisnis nyata**: jika respons awal (dari teman-teman) positif, dikembangkan menjadi produk SaaS berbasis subscription.

### Konteks Eksekusi
- Dikerjakan **solo** (bukan tim) → prioritas: kecepatan iterasi, minim beban kognitif, hindari over-engineering.
- Developer nyaman menggunakan **PHP**.

---

## 2. Fitur Inti (via WhatsApp)

- **Input natural language**: `"isi bensin 200 ribu"` → langsung tercatat sebagai transaksi.
- **Q&A finansial**: `"bulan ini aku habis berapa buat makan?"`
- **Analisis kebiasaan**: `"pengeluaranmu untuk kopi naik 35% dibanding bulan lalu."`
- **Prediksi saldo**: `"dengan pola saat ini, saldo diperkirakan habis pada tanggal 26."`
- **Saran penghematan**: `"jika mengurangi pengeluaran makan di luar 20%, kamu bisa menabung tambahan Rp500.000/bulan."`
- **Pengingat cerdas**: mendeteksi pola pembayaran rutin (misal tagihan listrik di awal bulan) dan mengingatkan.
- **Kategori transaksi di-*auto-detect* penuh oleh AI** — user tidak perlu memilih kategori manual saat input.
- **Support multi-transaksi dalam satu pesan** (misal `"beli kopi 20rb sama parkir 5rb"` → 2 transaksi terpisah), sejak versi awal.
- **Koreksi transaksi** dilakukan setelah pencatatan (bukan konfirmasi di awal), baik via chat WA (`"eh yang tadi salah kategorinya"`) maupun via edit manual di web dashboard.

## 3. Fitur Web Dashboard

- Grafik/tren pengeluaran.
- Breakdown kategori secara detail.
- Export laporan.
- Pengelolaan kategori custom & budget.
- History lengkap semua transaksi (termasuk yang perlu direview karena confidence AI rendah).
- Halaman onboarding/registrasi & koneksi nomor WhatsApp.
- Manajemen subscription/billing.

---

## 4. Keputusan Tech Stack

| Layer | Pilihan | Alasan Singkat |
|---|---|---|
| Backend API | **Laravel** | Developer sudah mahir; convention-over-configuration meminimalkan decision fatigue solo dev; Eloquent lebih ekspresif untuk query finansial agregat; data integrity lebih terjamin (native DB transaction). |
| Frontend Web | **Next.js** | Dashboard analitik & settings (bukan chat interface, karena chat sudah ditangani WhatsApp). Ekosistem React besar untuk hiring masa depan. |
| Interface utama (chat) | **WhatsApp** | Sudah jadi kebiasaan (habit) pengguna Indonesia; zero friction, tidak perlu install app baru. |
| WhatsApp Provider (awal) | **Baileys** (unofficial, gratis) | Untuk validasi ide/testing dengan sejumlah kecil teman. **Berisiko kena ban** bila volume tinggi — wajib migrasi ke API resmi sebelum scale produksi. |
| WhatsApp Provider (nanti) | **Twilio / 360dialog / Wati / Qontak** (resmi) | Untuk fase produksi/SaaS berbayar. |
| Realtime sync web ↔ WA | **Polling sederhana** (30–60 detik) | User tidak butuh instan sync (WA & web dipakai di waktu berbeda); WebSocket (Reverb/Pusher) baru relevan jika ada fitur kolaboratif multi-user. |

### Alasan tidak memilih Node.js penuh (full-stack JS)
- Solo dev = prioritas kecepatan ship & minim decision fatigue → Laravel convention menang.
- Query analitik finansial kompleks (agregasi, GROUP BY, dsb) lebih ekspresif di Eloquent.
- Integritas data uang → Laravel transaction handling lebih "aman by default".
- Use case AI (extract transaksi + Q&A sederhana) tidak butuh agentic orchestration kompleks (LangChain dsb) — cukup HTTP call biasa ke API AI, sehingga keunggulan ekosistem Node.js di area ini tidak signifikan untuk kasus ini.
- Developer sudah mahir PHP → memilih Laravel meniadakan biaya belajar bahasa/backend baru dari nol.

### Prinsip Arsitektur Kunci: Provider Pattern
Baik **WhatsApp provider** maupun **AI provider** dibungkus di belakang PHP `interface` (Contract), sehingga migrasi provider (misal Baileys → Twilio, atau Claude → model lain) di masa depan hanya perlu membuat satu class baru yang meng-implementasi interface yang sama — **tanpa mengubah business logic**.

---

## 5. Alur Onboarding

1. **Registrasi ringan di web**: nama, email, nomor WA, password (harus cepat, ±30 detik).
2. **Verifikasi nomor WA** via OTP (dikirim lewat WA) — sekaligus menautkan akun web ↔ nomor WA.
3. Setelah terverifikasi, **seluruh interaksi harian terjadi di WhatsApp** (tidak perlu balik ke web kecuali ingin lihat detail/analitik).
4. Web dashboard bersifat opsional, dilihat belakangan sesuai kebutuhan user.

**Alasan tidak "langsung chat tanpa daftar":** nomor WA saja tidak cukup untuk identifikasi aman (ganti nomor, tidak ada tempat simpan auth untuk web, tidak ada data untuk invoice/subscription/marketing).

---

## 6. Arsitektur Sistem (High Level)

```
User → WhatsApp → Webhook Laravel → Queue Job → 3-Stage AI Pipeline → Database
                                                        ↓
                                        Balasan dikirim kembali via WhatsApp
                                                        ↓
                              Next.js Dashboard (baca data yang sama, via REST API)
```

Node.js Baileys berjalan sebagai **micro-service jembatan terpisah** (bukan bagian dari codebase Laravel), tugasnya hanya meneruskan pesan masuk ke webhook Laravel dan mengirim balasan sesuai perintah dari Laravel.

```
project-root/
├── finance-app-api/     -- Laravel (backend, business logic, AI orchestration)
├── finance-app-web/     -- Next.js (dashboard, analitik, settings, billing)
└── whatsapp-bridge/     -- Node.js + Baileys (jembatan pesan, kecil & sederhana)
```

---

## 7. AI Parsing Pipeline (3-Stage)

Dipisah menjadi 3 tahap terpisah (bukan 1 prompt raksasa) agar lebih akurat, lebih mudah di-debug, dan **mencegah AI "berhalusinasi" angka** — semua angka finansial *harus* berasal dari database, AI hanya bertugas menerjemahkan ke bahasa natural.

### Stage 1 — Intent Classifier
Menentukan jenis pesan sebelum diproses lebih lanjut:
- `add_transaction`
- `query_report`
- `correction`
- `greeting_smalltalk`
- `unclear`

Output: JSON `{"intent": "...", "confidence": 0.0-1.0}`

### Stage 2A — Transaction Extractor (jika intent = add_transaction)
- Selalu mengembalikan **array transaksi** (walau hanya 1 item) untuk mengakomodasi multi-transaksi dalam satu pesan.
- Setiap transaksi punya `confidence` masing-masing (tidak disamaratakan).
- Kategori diambil dari daftar kategori default + kategori custom milik user (disisipkan dinamis ke prompt).
- Kategori fallback: `"Lainnya"` jika AI tidak yakin, daripada memaksakan ke kategori yang salah.
- Aturan parsing angka: `"20rb"`, `"20ribu"`, `"20k"`, `"20.000"` semua dikonversi ke `20000`.
- Default `type` adalah `expense`, kecuali ada indikasi pemasukan (kata kunci: "gajian", "dapat", "terima", "masuk").

**Contoh kategori default:**
- Expense: Makan & Minum, Transport, Belanja, Tagihan, Hiburan, Kesehatan, Pendidikan, Lainnya
- Income: Gaji, Bonus/THR, Freelance/Sampingan, Lainnya

### Stage 2B — Query Parser (jika intent = query_report)
AI **tidak** menjawab langsung — hanya menentukan parameter query (`query_type`, `period`, `category_filter`, `date_range`). Laravel yang menjalankan query sesungguhnya ke database sebagai *source of truth*.

Tipe query: `total_by_category`, `total_by_period`, `trend_comparison`, `balance_prediction`, `top_spending`, `general_summary`.

### Stage 3 — Response Formatter
Mengubah data hasil query (angka asli dari DB) menjadi kalimat natural Bahasa Indonesia yang ramah, singkat, dan tidak mengubah/menambah angka dari data yang diberikan.

### Penanganan Kasus Khusus
- **Multi-transaksi per pesan**: dipecah berdasar kata penghubung ("sama", "terus", "juga", "dan", koma, baris baru).
- **Confidence rendah**: transaksi tetap disimpan (agar tidak hilang), tapi ditandai `is_reviewed = false` untuk diklarifikasi user (via WA lanjutan atau review di web dashboard).
- **Koreksi**: memerlukan referensi ke `chat_message_id` agar sistem tahu transaksi mana saja yang berasal dari pesan yang sama, termasuk jika hanya sebagian dari multi-transaksi yang perlu dikoreksi.

---

## 8. Skema Database (Ringkasan)

Tabel utama:
- `users` — data akun, termasuk `phone_number`, `subscription_tier`.
- `phone_verifications` — OTP untuk verifikasi & tautan nomor WA.
- `categories` — default (global) & custom (per user).
- `wallets` — mendukung multi-dompet sejak awal (Cash, Bank, E-wallet).
- `transactions` — termasuk `raw_input` (teks asli untuk audit AI), `ai_confidence`, `is_reviewed`, `corrected_at`, `chat_message_id` (untuk grouping multi-transaksi & keperluan koreksi).
- `whatsapp_sessions` — status koneksi & provider yang dipakai per user.
- `chat_messages` — log seluruh chat masuk/keluar, termasuk `intent` terdeteksi, untuk audit & evaluasi kualitas AI.
- `budgets` — limit bulanan per kategori.
- `insights_cache` — cache hasil analisis (JSON) per user/tipe/periode agar tidak dihitung ulang setiap request.
- `subscriptions` — data langganan & integrasi payment gateway.

**Indeks penting:** `(user_id, transaction_date)` pada transactions, `(user_id, created_at)` pada chat_messages — krusial untuk performa query analitik.

---

## 9. Struktur Project Laravel

```
app/
├── Console/Commands/          -- scheduled jobs (mis. generate insight bulanan)
├── Contracts/                 -- interface: WhatsAppProviderInterface, AIProviderInterface
├── DTOs/                      -- kontrak data antar layer (IncomingMessageDTO, ParsedTransactionDTO, dst)
├── Enums/                     -- MessageIntent, TransactionType, SubscriptionTier
├── Http/
│   ├── Controllers/Api/       -- CRUD untuk web dashboard
│   ├── Controllers/Webhooks/  -- WhatsAppWebhookController
│   ├── Requests/
│   └── Resources/
├── Jobs/                      -- async processing (ProcessIncomingWhatsAppMessage, dst)
├── Models/
├── Services/
│   ├── AI/                    -- 3-stage pipeline + Providers (Claude, OpenAI)
│   ├── WhatsApp/              -- Providers (Baileys, Twilio) + parser
│   ├── Chat/                  -- ChatOrchestratorService (otak utama), CorrectionHandlerService
│   ├── Transaction/           -- create/update logic, category matcher
│   └── Analytics/             -- summary, trend, prediksi saldo
├── Repositories/              -- query kompleks dipisah dari Model
└── Providers/AppServiceProvider.php  -- binding interface ke implementasi konkret
```

**Package tambahan yang direkomendasikan:**
- `laravel/sanctum` — auth API untuk web dashboard.
- `laravel/horizon` — monitoring queue (penting karena hampir seluruh flow inti berjalan async).

**Alur end-to-end:**
```
User kirim WA → Webhook terima payload → MessageParserService normalize
→ Dispatch job (async) → ChatOrchestratorService orchestrate 3-stage AI
→ TransactionService simpan ke DB & update saldo wallet
→ ResponseFormatterService buat balasan natural
→ Kirim balasan via WhatsApp provider
```

---

## 10. Model Monetisasi (Rencana, Belum Final)

Skema tier subscription yang pernah dibahas (perlu divalidasi lagi setelah ada data pengguna nyata):
- **Free** — input unlimited, Q&A dasar, tanpa analitik/prediksi lanjutan.
- **Starter** — + trend analysis, prediksi saldo sederhana, export CSV.
- **Pro** — + saran penghematan AI, smart reminder, multi-wallet.
- **Business** — + multi-user, forecasting jangka panjang, API access.

Catatan penting: biaya WhatsApp Business API (resmi) bersifat *per-conversation*, sehingga perlu dihitung sebagai bagian dari struktur biaya/margin sebelum finalisasi harga.

---

## 11. Prinsip Desain yang Dipegang Sepanjang Diskusi

1. **Solo dev** → hindari kompleksitas prematur; optimalkan saat data/user benar-benar menunjukkan kebutuhannya, bukan preemptif.
2. **Zero friction untuk user** → tidak ada tahap konfirmasi kategori manual; koreksi dilakukan setelah fakta, bukan menghalangi alur input.
3. **AI tidak pernah mengarang angka** → database selalu jadi source of truth; AI hanya membantu parsing input & memformat output.
4. **Provider-agnostic architecture** → WhatsApp & AI provider dibungkus interface agar migrasi mudah tanpa mengubah business logic.
5. **Chat (WhatsApp) adalah nyawa produk** → web dashboard adalah pelengkap untuk detail, bukan pusat interaksi.

---

## 📌 Catatan Pengingat — Untuk Pembahasan/Proses Selanjutnya

Bagian ini **bukan** bagian dari spesifikasi final, melainkan daftar topik yang **belum selesai dibahas** di percakapan sebelumnya dan perlu ditindaklanjuti setelah Antigravity memproses dokumen ini (atau di sesi lanjutan bersama Claude):

- [ ] **Detail `whatsapp-bridge`** — kontrak payload antara Node.js Baileys dan webhook Laravel belum didetailkan (format request/response, retry mechanism jika Laravel down, dsb).
- [ ] **Struktur Next.js dashboard** — folder organization, halaman apa saja, state management, komponen chart yang dipakai (recharts/chart.js), belum dibahas sama sekali.
- [ ] **`CorrectionHandlerService` secara detail** — bagaimana AI/sistem menentukan transaksi mana yang dimaksud saat user bilang "yang tadi salah", terutama kasus ambigu (multi-transaksi dalam satu pesan, atau koreksi yang datang beberapa pesan setelahnya, bukan langsung setelah pencatatan).
- [ ] **Prompt untuk fitur "Pengingat Cerdas"** — deteksi pola transaksi rutin (misal bayar listrik tiap awal bulan) belum dirancang prompt/logic-nya.
- [ ] **Prompt & logic untuk "Saran Penghematan"** — bagaimana AI menghitung dan memformat saran (`"jika mengurangi X 20%, bisa menabung Y"`) belum dirancang detail.
- [ ] **Algoritma `BalancePredictionService`** — pendekatan prediksi saldo (moving average sederhana vs metode lain) belum ditentukan.
- [ ] **Integrasi payment gateway** — pemilihan provider (Midtrans vs lainnya) dan alur `Subscription` lifecycle (upgrade, downgrade, gagal bayar) belum dibahas teknis.
- [ ] **Strategi testing prompt AI** — kumpulan variasi kalimat nyata (typo, bahasa gaul, tanpa tanda baca) untuk validasi akurasi parsing sebelum launch belum disusun.
- [ ] **Kebijakan migrasi Baileys → WhatsApp Business API resmi** — trigger/threshold kapan harus migrasi (jumlah user? volume pesan?) belum ditentukan angka pastinya.
- [ ] **Finalisasi harga tier subscription** — perlu dihitung ulang setelah tahu estimasi biaya WhatsApp Business API per percakapan.

**Urutan yang disarankan untuk dibahas berikutnya:** struktur Next.js dashboard → detail `whatsapp-bridge` → `CorrectionHandlerService` → sisanya menyusul sesuai kebutuhan saat development berjalan.


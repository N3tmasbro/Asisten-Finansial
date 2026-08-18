---
tags: [backend, queue, jobs]
---

# 05 — Queue & Jobs

Hampir seluruh flow inti berjalan secara **async** melalui queue agar webhook dapat merespons cepat tanpa menunggu pipeline AI selesai.

---

## ⚙️ Konfigurasi Queue

```env
QUEUE_CONNECTION=database   # Wajib — bukan 'sync'
```

Tabel `jobs` (dan `failed_jobs`) di-migrate secara otomatis.

---

## 🚀 Menjalankan Worker

### Development (Recommended)
```bash
cd finance-app-api
php artisan queue:listen --tries=3 --sleep=3
```
> `queue:listen` merespawn worker setelah tiap job — lebih tangguh untuk development karena tidak perlu restart manual saat ada perubahan kode.

### Production
```bash
php artisan queue:work --tries=3 --sleep=3 --max-jobs=500
```
> Di produksi, gunakan **Supervisor** atau **Horizon** untuk menjaga worker tetap berjalan.

---

## 📦 Jobs yang Ada

### `ProcessIncomingWhatsAppMessage`
**File:** `app/Jobs/ProcessIncomingWhatsAppMessage.php`

**Trigger:** Dipanggil oleh `WhatsAppWebhookController` setiap ada pesan masuk.

**Alur kerja job ini:**
```
1. Terima IncomingMessageDTO
2. Panggil ChatOrchestratorService
3. Orchestrator jalankan 3-Stage AI Pipeline
4. Simpan hasil ke database
5. Kirim balasan via WhatsApp provider
6. Log pesan & intent ke chat_messages
```

**Retry:** 3x jika gagal (dari `--tries=3`)
**Timeout:** Default Laravel (60 detik)

---

## 📊 Monitoring Queue

### Cek Job Pending (via DB)
```sql
SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10;
```

### Cek Job Gagal
```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC;
```

### Retry Job Gagal
```bash
php artisan queue:retry all
```

### Horizon (Opsional, untuk Produksi)
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```
Akses dashboard di: `http://localhost:8000/horizon`

---

## ⚠️ Tips & Gotchas

1. **Jangan gunakan `QUEUE_CONNECTION=sync`** — semua flow inti tidak akan berjalan async
2. **Setelah ubah kode**, `queue:work` perlu di-restart (pakai `queue:listen` untuk menghindari ini)
3. **Sinyal `queue:restart` usang** bisa menyebabkan worker langsung exit — solusi: `php artisan cache:clear`
4. **Log queue** ada di `storage/logs/laravel.log`

---

## 🔗 Lihat Juga
- [[03 - AI Pipeline (3-Stage)]]
- [[../02 Setup & Konfigurasi/03 - Troubleshooting]]

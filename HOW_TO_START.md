# Panduan Memulai Proyek Asisten Finansial AI

Dokumen ini berisi petunjuk cara mengatur API Key AI, menjalankan project secara lokal, mendaftar akun baru, login, dan menyambungkan bot WhatsApp.

---

## 🤖 Konfigurasi AI (Google Gemini)

Proyek ini telah dikonfigurasi untuk menggunakan model AI Google Gemini (`gemini-flash-latest`) untuk memproses pesan WhatsApp natural.

1. Buka [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Login menggunakan akun Google Anda dan klik tombol **Create API key**.
3. *Copy* API Key yang berformat standar Google (dimulai dengan `AIzaSy...`, pastikan bukan key format lama `AQ...`).
4. Buka file `.env` di folder `finance-app-api` dan set konfigurasi berikut:
   ```env
   AI_PROVIDER=gemini
   GEMINI_API_KEY=AIzaSy... (paste key Anda di sini)
   ```
5. Setiap Anda mengubah API Key di `.env`, bersihkan *cache* konfigurasi Laravel:
   ```bash
   cd finance-app-api
   php artisan config:clear
   ```

### ⚠️ Batasan & Limit API Gemini (Penting!)
Google AI Studio memberikan batasan yang sangat ketat untuk tier gratis pada model eksperimental terbaru:
* **RPM (Requests Per Minute):** Maksimal 5 permintaan per menit.
* **RPD (Requests Per Day):** Maksimal 20 permintaan per hari (akan di-reset harian).
* **Perhitungan Chat WA:** Karena bot memanggil API Gemini sebanyak **3 kali** untuk setiap 1 pesan WA yang masuk (klasifikasi niat, ekstraksi data, dan merangkai balasan), kuota harian Anda secara efektif hanya dapat memproses **sekitar 6 pesan WhatsApp per hari**.
* Jika batas harian terlampaui, API akan mengembalikan error *429 Too Many Requests* dan bot tidak akan merespons pesan WA sampai limit di-reset keesokan harinya.

---

## 🚀 Cara Menjalankan Layanan Project

Project ini terdiri dari 3 layanan utama dan database yang harus berjalan bersamaan. Pastikan **Laragon/MySQL** Anda sudah menyala terlebih dahulu.

Buka terminal baru untuk masing-masing perintah berikut:

### 1. Backend (Laravel API)
Masuk ke direktori backend dan jalankan server Laravel:
```bash
cd finance-app-api
php artisan serve --port=8000
```
*Layanan berjalan di:* `http://localhost:8000`

### 2. Frontend (Next.js Dashboard)
Masuk ke direktori web frontend dan jalankan server development:
```bash
cd finance-app-web
npm run dev
```
*Layanan berjalan di:* `http://localhost:3000`

### 3. WhatsApp Bridge (Node.js)
Masuk ke direktori bridge dan jalankan script-nya:
```bash
cd whatsapp-bridge
npm start
```
*Layanan berjalan di:* `http://localhost:3001`
*(Pertama kali jalan, Anda perlu scan QR code menggunakan aplikasi WhatsApp di handphone nomor Bot).*

### 4. Laravel Queue Worker (Pemroses AI)
Untuk memproses pesan WhatsApp secara otomatis menggunakan Gemini AI di latar belakang, jalankan queue worker:
```bash
cd finance-app-api
php artisan queue:work --tries=3
```
*Catatan: Pastikan Anda menjalankan perintah ini SETELAH melakukan `config:clear` bila baru saja memperbarui API Key.*

---

## 👥 Registrasi & Login Akun

### Pendaftaran Baru (Register)
1. Buka browser dan arahkan ke halaman pendaftaran: `http://localhost:3000/register`.
2. Isi form pendaftaran:
   - **Nama Lengkap**: Nama Anda.
   - **Email**: Email aktif (misal: `kamu@email.com`).
   - **Nomor WhatsApp**: Isi dengan format standar lokal (misal: `085817793293`).
   - **Password**: Minimal 8 karakter.
3. Klik tombol **Daftar Sekarang**.

### Masuk Akun (Login)
1. Arahkan browser ke `http://localhost:3000/login`.
2. Masukkan **Email** dan **Password** yang sudah terdaftar.
3. Klik **Masuk** untuk masuk ke Dashboard.

---

## 📱 Verifikasi & Integrasi WhatsApp

Proyek ini menggunakan auto-linking via WhatsApp LID. Begitu Anda mengirim pesan ke Bot dari nomor WhatsApp yang didaftarkan, sistem akan otomatis menghubungkan nomor tersebut ke akun Anda.

### Cara Menghubungkan Nomor WhatsApp Anda ke Bot:
1. Pastikan **Layanan 1 sampai 4** di atas sudah berjalan semuanya.
2. Pastikan nomor Bot WhatsApp sudah ter-scan di terminal WhatsApp Bridge.
3. Kirim pesan pengeluaran apa saja dari nomor WhatsApp Anda yang didaftarkan ke nomor Bot, contoh:
   ```text
   gaji masuk 10 juta
   ```
4. Sistem di latar belakang akan otomatis memetakan identitas WhatsApp Anda (LID) ke akun pengguna di database.
5. Bot akan membalas pesan Anda ("Sip, transaksi kamu udah berhasil dicatat ya!...") sebagai tanda verifikasi & pencatatan berhasil karena di-parsing oleh AI.
6. Refresh halaman **Dashboard** atau menu **Pengaturan** di browser (`http://localhost:3000/settings`) untuk melihat data profil asli Anda beserta riwayat transaksi yang baru saja dicatat.

---

## 🛠️ Troubleshooting (Masalah Umum & Solusinya)

### 1. Pesan WhatsApp Tidak Dibalas / Tidak Masuk ke Database
* **Gejala:** Bot WA `Online` dan sudah tersambung, tapi pesan tidak direspon dan log di terminal WhatsApp Bridge menunjukkan pesan error seperti:
  `Failed to decrypt message with any known session... MessageCounterError: Key used already or never filled`
* **Penyebab:** Bot sempat offline cukup lama (misal: 2 hari) sehingga sesi enkripsi *end-to-end* (E2EE) dengan WhatsApp server menjadi kadaluarsa atau korup (stale session).
* **Solusi:** 
  1. Matikan proses WhatsApp Bridge (tekan `Ctrl+C`).
  2. Hapus folder sesi penyimpanan kredensial:
     ```bash
     cd whatsapp-bridge
     Remove-Item -Path "auth_state" -Recurse -Force
     ```
  3. Jalankan ulang `npm start` lalu *scan* ulang QR Code baru yang muncul dengan aplikasi WhatsApp di HP Bot Anda.

### 2. Error EADDRINUSE :::3001 Saat Menjalankan WhatsApp Bridge
* **Gejala:** Saat menjalankan `npm start` muncul error `Error: listen EADDRINUSE: address already in use :::3001`.
* **Penyebab:** Ada proses WhatsApp Bridge lama yang masih berjalan di latar belakang (background process) dan sedang memakai port 3001.
* **Solusi:** Matikan proses Node.js yang sedang menggunakan port tersebut, atau matikan *background task* di IDE Anda sebelum menjalankan ulang dari terminal manual.

### 3. Queue Worker (Pemroses AI) Berhenti Sendiri Tanpa Output
* **Gejala:** Saat menjalankan `php artisan queue:work --tries=3`, perintah langsung selesai (exit) tanpa pesan error apapun.
* **Penyebab:** Terdapat sinyal `queue:restart` usang yang tersimpan di cache aplikasi (sehingga saat worker hidup, ia langsung membaca perintah untuk mati).
* **Solusi:** Bersihkan seluruh *cache* Laravel sebelum menjalankan worker:
  ```bash
  php artisan cache:clear
  php artisan config:clear
  php artisan queue:work --tries=3 --sleep=3
  ```
  *(Sebagai alternatif yang lebih tangguh, Anda bisa menggunakan `php artisan queue:listen --tries=3` agar worker tidak pernah mati meskipun terjadi error kode).*

### 4. Limit AI Gemini Habis (429 Too Many Requests)
* **Gejala:** Error `429` di log Queue Worker dan AI berhenti membalas dengan benar.
* **Solusi:**
  Proyek ini sekarang sudah dilengkapi sistem **Auto-Fallback Model**. Ubah konfigurasi `GEMINI_MODEL` di file `.env` ke versi `Lite` yang memiliki limit harian jauh lebih tinggi (500 permintaan/hari dibanding versi standard yang hanya 20/hari):
  ```env
  GEMINI_MODEL=gemini-3.1-flash-lite
  ```
  *(Setelah diubah, selalu ingat untuk menjalankan `php artisan config:clear`).*

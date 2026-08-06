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

# 04 — Setup di VPS (Ubuntu 22.04 LTS)

Panduan lengkap deployment seluruh arsitektur sistem (Laravel Backend, Next.js Frontend, dan Node.js WhatsApp Bridge) pada Virtual Private Server (VPS) Ubuntu 22.04 LTS.

---

## 🏗️ Gambaran Arsitektur Deployment

Sistem akan berjalan dengan konfigurasi port berikut:
- **Backend (Laravel API)**: Berjalan di port lokal `8000` (atau langsung via PHP-FPM) -> Di-expose oleh Nginx di `api.domain.com`.
- **Frontend (Next.js Web)**: Berjalan di port lokal `3000` (diatur oleh PM2) -> Di-expose oleh Nginx di `domain.com` atau `app.domain.com`.
- **WhatsApp Bridge**: Berjalan di port lokal `3001` (diatur oleh PM2) -> Di-proxy atau dihubungkan secara lokal oleh Backend.

---

## 🛠️ Langkah 1: Persiapan Server & Instalan Dependency

Hubungkan ke VPS Anda via SSH, kemudian jalankan perintah pembaruan sistem dan instalasi dependensi berikut:

```bash
sudo apt update && sudo apt upgrade -y
```

### 1. Instalasi Node.js (v18+)
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
sudo npm install -y -g pm2
```

### 2. Instalasi PHP 8.2 & Ekstensi (Untuk Laravel)
```bash
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-gd php8.2-intl -y
```

### 3. Instalasi Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4. Instalasi MySQL Server & Nginx
```bash
sudo apt install mysql-server nginx certbot python3-certbot-nginx git unzip -y
```

---

## 🗄️ Langkah 2: Konfigurasi Database MySQL

Masuk ke MySQL untuk membuat database dan user baru:

```bash
sudo mysql
```

Jalankan perintah SQL berikut:
```sql
CREATE DATABASE keuangan_db;
CREATE USER 'keuangan_user'@'localhost' IDENTIFIED BY 'PasswordKuatAnda123!';
GRANT ALL PRIVILEGES ON keuangan_db.* TO 'keuangan_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 📂 Langkah 3: Setup Project Repo

Disarankan mengkloning repository ke folder `/var/www/keuangan`:

```bash
sudo mkdir -p /var/www/keuangan
sudo chown -R $USER:$USER /var/www/keuangan
git clone https://github.com/N3tmasbro/Asisten-Finansial.git /var/www/keuangan
```

---

## 🖥️ Langkah 4: Setup Laravel Backend

1. Masuk ke folder backend:
   ```bash
   cd /var/www/keuangan/finance-app-api
   ```
2. Instal dependensi:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Salin `.env` dan konfigurasikan:
   ```bash
   cp .env.example .env
   nano .env
   ```
   *Atur konfigurasi berikut:*
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.domain.com
   FRONTEND_URL=https://domain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=keuangan_db
   DB_USERNAME=keuangan_user
   DB_PASSWORD=PasswordKuatAnda123!

   WA_BRIDGE_URL=http://localhost:3001
   WA_BRIDGE_SECRET=SecretTokenKeamananAnda123!
   ```
4. Generate App Key & Jalankan Migrasi + Seeder:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --class=DefaultCategoriesSeeder --force
   ```
5. Atur permission folder storage & cache (agar PHP-FPM dapat menulis data):
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

### Mengatur Supervisor untuk Queue Worker Laravel
Agar job antrean pesan WhatsApp dan pengolah AI berjalan di *background*, kita buat konfigurasi supervisor:
```bash
sudo apt install supervisor -y
sudo nano /etc/supervisor/conf.d/keuangan-worker.conf
```
*Isi berkas konfigurasi:*
```ini
[program:keuangan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/keuangan/finance-app-api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/keuangan/finance-app-api/storage/logs/worker.log
stopwaitsecs=3600
```
*Aktifkan Supervisor:*
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start keuangan-worker:*
```

---

## 🤖 Langkah 5: Setup WhatsApp Bridge

1. Masuk ke folder whatsapp-bridge:
   ```bash
   cd /var/www/keuangan/whatsapp-bridge
   ```
2. Instal dependensi:
   ```bash
   npm install --production
   ```
3. Salin `.env` dan konfigurasikan:
   ```bash
   cp .env.example .env
   nano .env
   ```
   *Isi konfigurasi:*
   ```env
   PORT=3001
   API_URL=https://api.domain.com/api
   BRIDGE_SECRET=SecretTokenKeamananAnda123!
   ```
4. Jalankan menggunakan PM2 agar berjalan di background selamanya:
   ```bash
   pm2 start src/index.js --name "keuangan-wa-bridge"
   pm2 save
   ```

---

## 🎨 Langkah 6: Setup Next.js Frontend

1. Masuk ke folder frontend:
   ```bash
   cd /var/www/keuangan/finance-app-web
   ```
2. Instal dependensi:
   ```bash
   npm install
   ```
3. Salin `.env` dan konfigurasikan:
   ```bash
   cp .env.example .env
   nano .env
   ```
   *Isi konfigurasi:*
   ```env
   # Ganti dengan domain publik VPS Anda
   NEXT_PUBLIC_API_URL=https://api.domain.com/api
   ```
4. Lakukan Build aplikasi untuk produksi:
   ```bash
   npm run build
   ```
5. Jalankan aplikasi menggunakan PM2:
   ```bash
   pm2 start npm --name "keuangan-frontend" -- start
   pm2 save
   ```

*Untuk memastikan PM2 otomatis jalan kembali saat VPS restart:*
```bash
pm2 startup
# Jalankan perintah yang dihasilkan oleh output terminal di atas
```

---

## 🌐 Langkah 7: Konfigurasi Nginx & SSL (Certbot)

Buat blok konfigurasi virtual host di Nginx:

```bash
sudo nano /etc/nginx/sites-available/keuangan
```

*Salin dan sesuaikan konfigurasi berikut (Ganti domain.com dan api.domain.com dengan domain Anda):*

```nginx
# 1. Frontend Nginx Configuration
server {
    listen 80;
    server_name domain.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}

# 2. Backend Laravel Nginx Configuration
server {
    listen 80;
    server_name api.domain.com;
    root /var/www/keuangan/finance-app-api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Aktifkan konfigurasi Nginx tersebut dan reload server:
```bash
sudo ln -s /etc/nginx/sites-available/keuangan /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Install SSL menggunakan Let's Encrypt
```bash
sudo certbot --nginx -d domain.com -d api.domain.com
```
*Ikuti langkah-langkah di layar. Certbot otomatis akan mengubah konfigurasi Nginx menjadi HTTPS.*

---

## 🔒 Langkah 8: Konfigurasi Firewall (UFW)
Untuk keamanan server, pastikan hanya port HTTPS, HTTP, dan SSH yang terbuka ke publik:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 🔄 Pemeliharaan & Update Aplikasi di VPS
Jika ada pembaruan kode dari repositori Git di kemudian hari, lakukan langkah berikut:

```bash
cd /var/www/keuangan
git pull

# Update Backend
cd finance-app-api
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# Update WA Bridge
cd ../whatsapp-bridge
npm install
pm2 restart keuangan-wa-bridge

# Update Frontend
cd ../finance-app-web
npm install
npm run build
pm2 restart keuangan-frontend
```

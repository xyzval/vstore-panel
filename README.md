# AlrelShop Panel - Tutorial Install di aaPanel

Panel manajemen VPN berbasis Laravel untuk mengelola akun VPN di multiple VPS server dengan sistem saldo dan transaksi otomatis.

## 🚀 Fitur Utama

- **Multi VPS Management**: Kelola akun VPN di berbagai server VPS
- **Sistem Saldo**: Top up dan pembayaran otomatis
- **Multiple Protocol**: SSH, VMess, VLess, Trojan, Shadowsocks, OpenVPN
- **Admin Panel**: Manajemen user, server, dan pengaturan
- **Auto Detection**: Deteksi otomatis info VPS server
- **Responsive Design**: Interface modern dan mobile-friendly

## 📋 Requirements

### Server Requirements
- **VPS/Server**: Minimal 1GB RAM, 20GB Storage
- **OS**: Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- **Panel**: aaPanel (LAMP/LNMP Stack)

### Software Requirements
- **PHP**: 8.1+ (dengan ekstensi: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML)
- **MySQL**: 5.7+ atau MariaDB 10.3+
- **Web Server**: Apache 2.4+ atau Nginx 1.18+
- **Composer**: Latest version
- **SSH**: Akses SSH ke VPS panel dan VPS target

## 🛠️ Tutorial Install di aaPanel

### 1. Install aaPanel

**Untuk CentOS/RHEL:**
```bash
yum install -y wget && wget -O install.sh http://www.aapanel.com/script/install_6.0_en.sh && bash install.sh aapanel
```

**Untuk Ubuntu/Debian:**
```bash
wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && sudo bash install.sh aapanel
```

### 2. Setup LAMP/LNMP Stack

1. Login ke aaPanel: `http://SERVER_IP:7800`
2. Install paket software:
   - **Apache** 2.4+ atau **Nginx** 1.18+
   - **MySQL** 5.7+ atau **MariaDB** 10.3+
   - **PHP** 8.1+
   - **phpMyAdmin** (opsional)

### 3. Konfigurasi PHP

1. Di aaPanel → **App Store** → **PHP 8.1** → **Setting**
2. Install ekstensi yang diperlukan:
   ```
   BCMath
   Ctype
   JSON
   Mbstring
   OpenSSL
   PDO
   PDO_MySQL
   Tokenizer
   XML
   Curl
   Zip
   GD
   ```

3. Edit **php.ini**:
   ```ini
   memory_limit = 512M
   max_execution_time = 300
   upload_max_filesize = 100M
   post_max_size = 100M
   ```

### 4. Setup Database

1. Di aaPanel → **Database** → **Add Database**
2. Buat database baru:
   ```
   Database Name: sql_panel_alrels
   Username: panel_user
   Password: [generate secure password]
   ```

### 5. Setup Domain/Subdomain

1. Di aaPanel → **Website** → **Add Site**
2. Isi informasi:
   ```
   Domain: panel.yourdomain.com
   Path: /www/wwwroot/panel.yourdomain.com
   PHP Version: 8.1
   Database: sql_panel_alrels (optional)
   ```

### 6. Download & Install Panel

1. **SSH ke server** dan navigasi ke direktori web:
   ```bash
   cd /www/wwwroot/panel.yourdomain.com
   rm -rf * .*
   ```

2. **Download source code** (pilih salah satu):
   
   **Opsi A: Git Clone**
   ```bash
   git clone https://github.com/yourusername/panel-alrelshop.git .
   ```
   
   **Opsi B: Upload Manual**
   - Upload semua file project ke direktori web
   - Extract jika dalam bentuk zip

3. **Install Composer** (jika belum ada):
   ```bash
   curl -sS https://getcomposer.org/installer | php
   mv composer.phar /usr/local/bin/composer
   chmod +x /usr/local/bin/composer
   ```

4. **Install dependencies**:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

### 7. Konfigurasi Environment

1. **Copy file environment**:
   ```bash
   cp .env.example .env
   ```

2. **Edit konfigurasi** `.env`:
   ```env
   APP_NAME="AlrelShop Panel"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://panel.yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=sql_panel_alrels
   DB_USERNAME=panel_user
   DB_PASSWORD=your_secure_password

   # Panel Configuration
   PANEL_NAME="AlrelShop"
   PANEL_URL="https://panel.yourdomain.com"
   ADMIN_EMAIL="admin@yourdomain.com"
   PRICE_PER_ACCOUNT=18000
   ```

3. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

### 8. Setup Database Schema

1. **Jalankan migrasi database**:
   ```bash
   php artisan migrate --force
   ```

2. **Seed data awal**:
   ```bash
   php artisan db:seed --force
   ```

### 9. Set Permissions

```bash
chown -R www:www /www/wwwroot/panel.yourdomain.com
chmod -R 755 /www/wwwroot/panel.yourdomain.com
chmod -R 775 /www/wwwroot/panel.yourdomain.com/storage
chmod -R 775 /www/wwwroot/panel.yourdomain.com/bootstrap/cache
```

### 10. Konfigurasi Web Server

**Untuk Nginx:**
1. Di aaPanel → **Website** → **panel.yourdomain.com** → **Conf**
2. Tambahkan konfigurasi Laravel:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }

   location ~ \.php$ {
       fastcgi_pass unix:/tmp/php-cgi-81.sock;
       fastcgi_index index.php;
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       include fastcgi_params;
   }
   ```

**Untuk Apache:**
1. Pastikan `.htaccess` file sudah ada di root directory
2. Enable mod_rewrite di aaPanel

### 11. Setup SSL Certificate

1. Di aaPanel → **Website** → **panel.yourdomain.com** → **SSL**
2. Pilih **Let's Encrypt** atau upload **SSL Certificate**
3. Enable **Force HTTPS**

### 12. Setup SSH Keys untuk VPS Target

1. **Generate SSH key pair**:
   ```bash
   ssh-keygen -t rsa -b 4096 -f /var/www/.ssh/alrelshop_panel -N ""
   ```

2. **Set permissions**:
   ```bash
   mkdir -p /var/www/.ssh
   chown -R www:www /var/www/.ssh
   chmod 700 /var/www/.ssh
   chmod 600 /var/www/.ssh/alrelshop_panel*
   ```

3. **Copy public key ke VPS target**:
   ```bash
   ssh-copy-id -i /var/www/.ssh/alrelshop_panel.pub root@VPS_TARGET_IP
   ```

### 13. Setup Cron Jobs

1. Di aaPanel → **Cron** → **Add Task**
2. Tambahkan cron jobs berikut:

   **Update expired accounts (setiap 5 menit):**
   ```bash
   */5 * * * * cd /www/wwwroot/panel.yourdomain.com && php artisan schedule:run >> /dev/null 2>&1
   ```

   **Backup database harian:**
   ```bash
   0 2 * * * /www/server/mysql/bin/mysqldump -u panel_user -p'password' sql_panel_alrels > /www/backup/panel_backup_$(date +\%Y\%m\%d).sql
   ```

### 14. Konfigurasi VPS Target

1. **Login ke admin panel**: `https://panel.yourdomain.com`
   ```
   Username: admin
   Password: admin123
   ```

2. **Ganti password default** di menu Profile

3. **Tambah VPS Server** di menu Admin → Kelola VPS:
   - Gunakan fitur **Auto Detect** dengan IP VPS target
   - Atau input manual informasi VPS

4. **Test koneksi** ke setiap VPS server

## 🔧 Troubleshooting

### Error: Permission Denied

```bash
chown -R www:www /www/wwwroot/panel.yourdomain.com
chmod -R 755 /www/wwwroot/panel.yourdomain.com
chmod -R 775 /www/wwwroot/panel.yourdomain.com/storage
chmod -R 775 /www/wwwroot/panel.yourdomain.com/bootstrap/cache
```

### Error: Database Connection

1. Cek kredensial database di `.env`
2. Pastikan MySQL service berjalan
3. Test koneksi database:
   ```bash
   php artisan tinker
   DB::connection()->getPdo();
   ```

### Error: SSH Connection Failed

1. Cek SSH key permissions:
   ```bash
   chmod 600 /var/www/.ssh/alrelshop_panel
   chown www:www /var/www/.ssh/alrelshop_panel
   ```

2. Test SSH connection manual:
   ```bash
   sudo -u www ssh -i /var/www/.ssh/alrelshop_panel root@VPS_IP
   ```

### Error: Composer Install Failed

1. Update Composer:
   ```bash
   composer self-update
   ```

2. Clear cache:
   ```bash
   composer clear-cache
   composer install --no-cache
   ```

### Error: 500 Internal Server Error

1. Cek log Laravel:
   ```bash
   tail -f /www/wwwroot/panel.yourdomain.com/storage/logs/laravel.log
   ```

2. Cek log web server di aaPanel → **Logs**

3. Enable debug mode sementara:
   ```env
   APP_DEBUG=true
   ```

## 📱 Default Login

Setelah instalasi berhasil, gunakan kredensial berikut untuk login:

```
URL: https://panel.yourdomain.com
Username: admin
Password: admin123
Saldo Awal: Rp 1.000.000
```

**⚠️ PENTING**: Segera ganti password default setelah login pertama!

## 🔒 Keamanan

1. **Ganti password default** admin
2. **Disable debug mode** di production
3. **Setup firewall** untuk port 7800 aaPanel
4. **Regular backup** database dan files
5. **Update** panel dan dependencies secara berkala

## 📞 Support

Jika mengalami kesulitan dalam instalasi:

1. **Cek log error** di aaPanel dan Laravel
2. **Pastikan semua requirements** terpenuhi
3. **Test koneksi** database dan SSH
4. **Hubungi support** jika diperlukan

## 📄 License

This project is licensed under the MIT License.

---

**Happy Coding! 🚀**

*AlrelShop Panel - VPN Management Made Easy*
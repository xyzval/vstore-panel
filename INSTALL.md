# 🚀 AlrelShop Panel - Panduan Instalasi Lengkap

Panel saldo VPN premium yang terintegrasi dengan script AlrelShop VPS.

## 📋 Yang Sudah Dibuat

### ✅ File Panel Lengkap
- `config.php` - Konfigurasi database & VPS target
- `install_db.php` - Installer database otomatis
- `login.php` - Halaman login dengan fitur registrasi & lupa password
- `dashboard.php` - Dashboard utama dengan sidebar toggle
- `create-account.php` - Form pembuatan akun VPN dengan sistem billing
- `logout.php` - Handler logout
- `.htaccess` - Security & URL rewriting
- `install-panel.sh` - Auto installer untuk Ubuntu/Debian
- `setup-ssh.sh` - Setup SSH keys untuk VPS target

### ✅ Fitur Utama
- **Dashboard Modern** dengan hamburger menu (3 garis toggle)
- **Sistem Login/Registrasi** dengan validasi lengkap
- **Manajemen Saldo** dengan pemotongan otomatis Rp 18.000/akun
- **Multi-Server Support** - Kelola beberapa VPS target
- **SSH Integration** - Komunikasi real-time dengan VPS target
- **Responsive Design** - Support mobile & desktop
- **Security Features** - HTTPS, headers, file protection

## 🛠️ Instalasi Cepat

### Metode 1: Auto Installer (Recommended)
```bash
# Upload folder alrelshop-panel ke VPS
# Jalankan auto installer
sudo bash install-panel.sh
```

### Metode 2: Manual Installation
```bash
# 1. Install dependencies
apt update && apt install -y apache2 mysql-server php php-mysql php-curl

# 2. Setup database
mysql -u root -p
CREATE DATABASE alrelshop_panel;
CREATE USER 'alrelshop'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON alrelshop_panel.* TO 'alrelshop'@'localhost';

# 3. Upload files ke /var/www/html/alrelshop-panel/
# 4. Update config.php dengan database credentials
# 5. Akses http://domain.com/alrelshop-panel/install_db.php
# 6. Setup SSH keys dengan setup-ssh.sh
```

## ⚙️ Konfigurasi VPS Target

### 1. Update config.php
```php
$vps_targets = [
    'vps1' => [
        'name' => 'ANYM NETWORK',
        'host' => 'your-domain.com',
        'ip' => '1.2.3.4',
        'ssh_key' => '/var/www/.ssh/alrelshop_panel',
        // ... konfigurasi lainnya
    ]
];
```

### 2. Setup SSH Keys
```bash
# Jalankan setup SSH
bash setup-ssh.sh

# Copy public key ke VPS target
ssh-copy-id root@VPS_TARGET_IP
```

### 3. Test Koneksi
```bash
# Test manual
ssh -i /var/www/.ssh/alrelshop_panel root@VPS_IP "menu"
```

## 🎯 Penggunaan Panel

### Login Default
- **URL**: `http://your-domain.com/alrelshop-panel/`
- **Username**: `admin`
- **Password**: `admin123`
- **Saldo Awal**: Rp 1.000.000

### Menu Utama
1. **Dashboard** - Overview saldo & statistik
2. **Buat Akun VPN** - Pilih server & tipe akun
3. **Akun Saya** - Kelola akun VPN
4. **Top Up Saldo** - Tambah saldo
5. **Riwayat Transaksi** - History transaksi

### Sidebar Toggle
- Klik **hamburger menu (☰)** di kiri atas untuk membuka/tutup sidebar
- Responsive di mobile dan desktop
- Menu tersembunyi secara default

## 💰 Sistem Billing

### Harga Per Akun
- **Semua Tipe VPN**: Rp 18.000 (30 hari)
- **60 Hari**: Rp 36.000
- **90 Hari**: Rp 54.000

### Tipe Akun Tersedia
- SSH Tunnel (WebSocket + SSL/TLS)
- VMess (V2Ray Protocol)
- VLess (V2Ray Protocol)
- Trojan (Trojan-Go)
- Shadowsocks
- OpenVPN

### Proses Pembuatan Akun
1. User pilih server & tipe akun
2. Sistem cek saldo (min Rp 18.000)
3. Potong saldo otomatis
4. Eksekusi command di VPS target via SSH
5. Simpan data akun & transaksi
6. Tampilkan detail akun

## 🔧 Command Integration

Panel akan mengeksekusi command berikut di VPS target:

```bash
# SSH Account
menu-ssh create username password duration

# VMess Account  
menu-vmess create username uuid duration

# VLess Account
menu-vless create username uuid duration

# Trojan Account
menu-trojan create username password duration

# Shadowsocks Account
menu-shadowsocks create username password duration

# OpenVPN Account
menu-openvpn create username password duration
```

## 🚨 Keamanan

### File Protection
- `config.php` - Protected dari akses web
- `install_db.php` - Disabled setelah instalasi
- Backup files - Hidden dari web
- Directory listing - Disabled

### Security Headers
- X-Content-Type-Options
- X-Frame-Options
- X-XSS-Protection
- Strict-Transport-Security
- Content-Security-Policy

### Database Security
- Prepared statements untuk semua query
- Password hashing dengan PHP password_hash()
- Session security dengan httponly cookies

## 🔍 Troubleshooting

### SSH Connection Failed
```bash
# Check SSH key permissions
chmod 600 /var/www/.ssh/alrelshop_panel
chown www-data:www-data /var/www/.ssh/alrelshop_panel

# Test manual connection
sudo -u www-data ssh -i /var/www/.ssh/alrelshop_panel root@VPS_IP
```

### Database Connection Error
```bash
# Check MySQL service
systemctl status mysql

# Test database connection
mysql -u alrelshop -p alrelshop_panel
```

### Permission Issues
```bash
# Fix ownership
chown -R www-data:www-data /var/www/html/alrelshop-panel/

# Fix permissions
find /var/www/html/alrelshop-panel/ -type f -exec chmod 644 {} \;
find /var/www/html/alrelshop-panel/ -type d -exec chmod 755 {} \;
```

### Menu Command Not Found
```bash
# Check if menu exists on VPS target
ssh root@VPS_IP "which menu"
ssh root@VPS_IP "ls -la /usr/local/sbin/menu"

# Check PATH
ssh root@VPS_IP "echo \$PATH"
```

## 📁 Struktur File

```
alrelshop-panel/
├── config.php                 # Konfigurasi utama
├── install_db.php             # Database installer
├── login.php                  # Halaman login/register
├── dashboard.php              # Dashboard utama
├── create-account.php         # Form buat akun VPN
├── logout.php                 # Logout handler
├── .htaccess                  # Security & rewrite rules
├── install-panel.sh           # Auto installer
├── setup-ssh.sh              # SSH setup script
├── README.md                  # Dokumentasi lengkap
└── INSTALL.md                 # Panduan instalasi ini
```

## 🆘 Support

### Kontak
- **Telegram**: [@alrelshop](https://t.me/alrelshop)
- **WhatsApp**: [+62 822-8585-1668](https://wa.me/6282285851668)

### Logs
- **Apache**: `/var/log/apache2/`
- **MySQL**: `/var/log/mysql/`
- **Panel**: Browser console untuk JS errors

## 🎉 Selamat!

Panel AlrelShop siap digunakan! Fitur utama:

✅ **Dashboard modern** dengan sidebar toggle  
✅ **Login system** dengan registrasi  
✅ **Multi-server management**  
✅ **Auto billing** Rp 18k per akun  
✅ **SSH integration** ke VPS target  
✅ **Responsive design** mobile-friendly  
✅ **Security features** lengkap  

---

**© 2025 AlrelShop - Premium VPN Panel Solutions**

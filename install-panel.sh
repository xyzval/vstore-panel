#!/bin/bash

# AlrelShop Panel - Auto Installer Script
# Script otomatis untuk instalasi panel saldo VPN

clear
echo "=================================================="
echo "         AlrelShop Panel - Auto Installer"
echo "=================================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_header() {
    echo -e "${PURPLE}$1${NC}"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Script ini harus dijalankan sebagai root!"
    print_status "Gunakan: sudo $0"
    exit 1
fi

# OS Detection
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    print_error "Cannot detect OS version"
    exit 1
fi

print_status "Detected OS: $OS $VERSION"

# Supported OS check
if [[ "$OS" != "ubuntu" && "$OS" != "debian" ]]; then
    print_error "OS tidak didukung! Hanya support Ubuntu/Debian"
    exit 1
fi

echo ""
print_header "🚀 INSTALASI ALRELSHOP PANEL"
echo ""
print_status "Panel ini akan diinstall terpisah dari script VPS target"
print_status "Panel akan berkomunikasi dengan VPS target via SSH"
echo ""

# Confirmation
read -p "Lanjutkan instalasi? (y/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Instalasi dibatalkan"
    exit 0
fi

echo ""
print_header "📦 MENGINSTALL DEPENDENCIES"
echo ""

# Update system
print_status "Updating system packages..."
apt update -qq && apt upgrade -y -qq

# Install required packages
print_status "Installing required packages..."
apt install -y \
    apache2 \
    mysql-server \
    php \
    php-mysql \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-gd \
    libapache2-mod-php \
    unzip \
    wget \
    curl \
    openssh-client \
    certbot \
    python3-certbot-apache

# Enable Apache modules
print_status "Enabling Apache modules..."
a2enmod rewrite ssl headers

# Start and enable services
print_status "Starting services..."
systemctl start apache2 mysql
systemctl enable apache2 mysql

print_success "Dependencies installed successfully"

echo ""
print_header "🗄️ SETUP DATABASE"
echo ""

# MySQL secure installation
print_status "Configuring MySQL..."

# Generate random password for MySQL root
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
PANEL_DB_PASSWORD=$(openssl rand -base64 16)

# Set MySQL root password
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$MYSQL_ROOT_PASSWORD';"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"

# Create database and user
print_status "Creating database and user..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
CREATE DATABASE alrelshop_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'alrelshop'@'localhost' IDENTIFIED BY '$PANEL_DB_PASSWORD';
GRANT ALL PRIVILEGES ON alrelshop_panel.* TO 'alrelshop'@'localhost';
FLUSH PRIVILEGES;
EOF

print_success "Database setup completed"

echo ""
print_header "📁 SETUP PANEL FILES"
echo ""

# Create web directory
PANEL_DIR="/var/www/html/alrelshop-panel"
print_status "Creating panel directory: $PANEL_DIR"
mkdir -p "$PANEL_DIR"

# Copy current files to web directory (assuming script is in panel folder)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
print_status "Copying panel files..."
cp -r "$SCRIPT_DIR"/* "$PANEL_DIR/"

# Set proper ownership and permissions
print_status "Setting permissions..."
chown -R www-data:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"
chmod 644 "$PANEL_DIR"/.htaccess
chmod 600 "$PANEL_DIR"/config.php

print_success "Panel files setup completed"

echo ""
print_header "⚙️ KONFIGURASI PANEL"
echo ""

# Update config.php with database credentials
print_status "Updating configuration..."
sed -i "s/define('DB_PASS', '');/define('DB_PASS', '$PANEL_DB_PASSWORD');/" "$PANEL_DIR/config.php"

# Get domain/IP
echo ""
print_status "Konfigurasi domain untuk panel..."
echo "1. Gunakan domain (recommended)"
echo "2. Gunakan IP address"
read -p "Pilih opsi (1-2): " -n 1 -r
echo ""

if [[ $REPLY == "1" ]]; then
    read -p "Masukkan domain panel (contoh: panel.alrelshop.com): " PANEL_DOMAIN
    PANEL_URL="https://$PANEL_DOMAIN"
else
    PANEL_DOMAIN=$(curl -s ifconfig.me)
    PANEL_URL="http://$PANEL_DOMAIN"
    print_status "Using IP: $PANEL_DOMAIN"
fi

# Update config with domain
sed -i "s|define('PANEL_URL', 'https://' . \$_SERVER\['HTTP_HOST'\]);|define('PANEL_URL', '$PANEL_URL');|" "$PANEL_DIR/config.php"

print_success "Configuration updated"

echo ""
print_header "🌐 SETUP APACHE VIRTUAL HOST"
echo ""

# Create Apache virtual host
VHOST_FILE="/etc/apache2/sites-available/alrelshop-panel.conf"
print_status "Creating virtual host: $VHOST_FILE"

cat > "$VHOST_FILE" << EOF
<VirtualHost *:80>
    ServerName $PANEL_DOMAIN
    DocumentRoot $PANEL_DIR
    
    <Directory $PANEL_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/alrelshop-panel_error.log
    CustomLog \${APACHE_LOG_DIR}/alrelshop-panel_access.log combined
</VirtualHost>
EOF

# Enable site
a2ensite alrelshop-panel.conf
a2dissite 000-default.conf
systemctl reload apache2

print_success "Virtual host configured"

# SSL Setup
if [[ $REPLY == "1" ]]; then
    echo ""
    read -p "Setup SSL certificate dengan Let's Encrypt? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Setting up SSL certificate..."
        certbot --apache -d "$PANEL_DOMAIN" --non-interactive --agree-tos --email "admin@$PANEL_DOMAIN"
        print_success "SSL certificate installed"
    fi
fi

echo ""
print_header "💾 INSTALASI DATABASE SCHEMA"
echo ""

# Install database schema
print_status "Installing database schema..."
cd "$PANEL_DIR"

# Temporarily allow access to install_db.php
sed -i 's/# Allow from all/Allow from all/' "$PANEL_DIR/.htaccess"

# Run database installation
php -f install_db.php > /tmp/db_install.log 2>&1

if [ $? -eq 0 ]; then
    print_success "Database schema installed"
else
    print_error "Database installation failed"
    cat /tmp/db_install.log
fi

# Secure install_db.php again
sed -i 's/Allow from all/# Allow from all/' "$PANEL_DIR/.htaccess"

echo ""
print_header "🔑 SETUP SSH UNTUK VPS TARGET"
echo ""

print_status "Membuat SSH key untuk koneksi ke VPS target..."

# Create SSH keys for www-data user
sudo -u www-data bash << 'EOF'
SSH_DIR="/var/www/.ssh"
mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"

if [ ! -f "$SSH_DIR/alrelshop_panel" ]; then
    ssh-keygen -t rsa -b 4096 -f "$SSH_DIR/alrelshop_panel" -N "" -C "alrelshop-panel"
    chmod 600 "$SSH_DIR/alrelshop_panel"
    chmod 644 "$SSH_DIR/alrelshop_panel.pub"
fi
EOF

# Update config with SSH key path
sed -i "s|'ssh_key' => '/path/to/ssh/key'|'ssh_key' => '/var/www/.ssh/alrelshop_panel'|g" "$PANEL_DIR/config.php"

print_success "SSH key generated"

# Save credentials
CREDENTIALS_FILE="/root/alrelshop-panel-credentials.txt"
cat > "$CREDENTIALS_FILE" << EOF
=================================================
        AlrelShop Panel - Credentials
=================================================

Panel URL: $PANEL_URL
Admin Login:
  Username: admin
  Password: admin123
  Initial Balance: Rp 1,000,000

Database:
  Host: localhost
  Database: alrelshop_panel
  Username: alrelshop
  Password: $PANEL_DB_PASSWORD

MySQL Root Password: $MYSQL_ROOT_PASSWORD

SSH Public Key (untuk VPS target):
$(cat /var/www/.ssh/alrelshop_panel.pub)

=================================================
        LANGKAH SELANJUTNYA
=================================================

1. Copy SSH public key di atas ke semua VPS target
2. Login ke setiap VPS target dan jalankan:
   echo 'SSH_PUBLIC_KEY' >> ~/.ssh/authorized_keys

3. Update IP/domain VPS target di config.php

4. Test koneksi SSH ke VPS target

5. Login ke panel dan test pembuatan akun

=================================================
EOF

echo ""
print_header "✅ INSTALASI SELESAI"
echo ""

print_success "AlrelShop Panel berhasil diinstall!"
print_status "Panel URL: $PANEL_URL"
print_status "Credentials disimpan di: $CREDENTIALS_FILE"
echo ""

print_warning "PENTING - LANGKAH SELANJUTNYA:"
echo "1. Copy SSH public key ke semua VPS target"
echo "2. Update konfigurasi VPS target di config.php"
echo "3. Test koneksi SSH ke VPS target"
echo "4. Ganti password admin default"
echo ""

# Show credentials
cat "$CREDENTIALS_FILE"

echo ""
print_header "🎉 SELAMAT! PANEL ALRELSHOP SIAP DIGUNAKAN"
echo ""

# Final checks
print_status "Melakukan pengecekan akhir..."

if systemctl is-active --quiet apache2; then
    print_success "✓ Apache2 running"
else
    print_error "✗ Apache2 not running"
fi

if systemctl is-active --quiet mysql; then
    print_success "✓ MySQL running"
else
    print_error "✗ MySQL not running"
fi

if [ -f "$PANEL_DIR/config.php" ]; then
    print_success "✓ Panel files installed"
else
    print_error "✗ Panel files missing"
fi

echo ""
print_status "Instalasi selesai! Akses panel di: $PANEL_URL"
print_status "Login dengan username: admin, password: admin123"
echo ""

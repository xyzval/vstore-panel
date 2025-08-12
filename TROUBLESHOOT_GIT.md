# 🔧 Troubleshooting Git Clone GitHub

## ❌ Masalah: "Password authentication is not supported"

Error ini terjadi karena GitHub sudah tidak lagi mendukung authentication menggunakan password biasa untuk operasi Git sejak Agustus 2021.

## ✅ Solusi 1: Menggunakan Personal Access Token (PAT) - RECOMMENDED

### 1. Buat Personal Access Token di GitHub

1. Login ke GitHub → **Settings** → **Developer settings**
2. Klik **Personal access tokens** → **Tokens (classic)**
3. Klik **Generate new token** → **Generate new token (classic)**
4. Isi form:
   ```
   Note: Panel AlrelShop Deploy
   Expiration: 90 days (atau sesuai kebutuhan)
   
   Permissions yang dicentang:
   ✅ repo (Full control of private repositories)
   ✅ workflow (Update GitHub Action workflows)
   ```
5. Klik **Generate token**
6. **COPY TOKEN** dan simpan di tempat aman (hanya muncul 1x!)

### 2. Clone dengan Personal Access Token

```bash
# Hapus folder yang gagal clone
rm -rf panel-alrelshop

# Clone menggunakan PAT (ganti YOUR_TOKEN dengan token Anda)
git clone https://YOUR_USERNAME:YOUR_TOKEN@github.com/alrel1408/panel-alrelshop.git

# Contoh:
# git clone https://alrel1408:ghp_xxxxxxxxxxxx@github.com/alrel1408/panel-alrelshop.git

# Pindah file ke direktori saat ini
mv panel-alrelshop/* .
mv panel-alrelshop/.* . 2>/dev/null
rmdir panel-alrelshop
```

## ✅ Solusi 2: Menggunakan SSH Key (Lebih Aman)

### 1. Generate SSH Key

```bash
# Generate SSH key baru
ssh-keygen -t ed25519 -C "your-email@example.com" -f ~/.ssh/github_key

# Atau jika sistem tidak support ed25519:
ssh-keygen -t rsa -b 4096 -C "your-email@example.com" -f ~/.ssh/github_key

# Set permission
chmod 600 ~/.ssh/github_key
chmod 644 ~/.ssh/github_key.pub
```

### 2. Tambahkan SSH Key ke GitHub

```bash
# Tampilkan public key
cat ~/.ssh/github_key.pub
```

1. Copy output di atas
2. Login ke GitHub → **Settings** → **SSH and GPG keys**
3. Klik **New SSH key**
4. Paste public key dan beri title "VPS Panel Server"
5. Klik **Add SSH key**

### 3. Configure SSH

```bash
# Buat/edit SSH config
nano ~/.ssh/config

# Tambahkan konfigurasi:
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_key
    IdentitiesOnly yes
```

### 4. Test dan Clone

```bash
# Test SSH connection
ssh -T git@github.com

# Jika berhasil, akan muncul: "Hi username! You've successfully authenticated..."

# Clone dengan SSH
rm -rf panel-alrelshop
git clone git@github.com:alrel1408/panel-alrelshop.git

# Pindah file
mv panel-alrelshop/* .
mv panel-alrelshop/.* . 2>/dev/null
rmdir panel-alrelshop
```

## ✅ Solusi 3: Download Manual ZIP (Tercepat untuk Deploy)

Jika kedua cara di atas ribet, bisa download manual:

```bash
# Download ZIP dari GitHub
wget https://github.com/alrel1408/panel-alrelshop/archive/refs/heads/main.zip

# Extract
unzip main.zip

# Pindah file
mv panel-alrelshop-main/* .
mv panel-alrelshop-main/.* . 2>/dev/null
rm -rf panel-alrelshop-main main.zip
```

## 🔐 Solusi 4: Clone Tanpa Authentication (Jika Repository Public)

Jika repository sudah public:

```bash
# Clone tanpa authentication
git clone https://github.com/alrel1408/panel-alrelshop.git

# Pindah file
mv panel-alrelshop/* .
mv panel-alrelshop/.* . 2>/dev/null
rmdir panel-alrelshop
```

## ⚠️ Catatan Keamanan

1. **Jangan share Personal Access Token** kepada orang lain
2. **Set expiration date** untuk PAT
3. **Gunakan SSH key** untuk keamanan yang lebih baik
4. **Revoke token** jika tidak digunakan lagi

## 🚀 Lanjut Instalasi Setelah Clone Berhasil

Setelah source code berhasil di-clone, lanjut dengan step instalasi:

```bash
# Install dependencies
composer install --optimize-autoloader --no-dev

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Edit database config
nano .env

# Run migration
php artisan migrate --force

# Set permissions
chown -R www:www /www/wwwroot/panel.alrelshop.my.id
chmod -R 755 /www/wwwroot/panel.alrelshop.my.id
chmod -R 775 storage bootstrap/cache
```

---

**Rekomendasi**: Gunakan **Personal Access Token** karena paling mudah untuk deploy sekali saja.

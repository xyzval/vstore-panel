<?php
// AlrelShop Panel Database Installation
// Jalankan file ini untuk setup database panel
require_once 'config.php';

echo "<h2>Instalasi Database AlrelShop Panel</h2>";
echo "<p><strong>PENTING:</strong> Panel ini akan diinstall di VPS yang berbeda dari script VPN target!</p>";

try {
    // Create database if not exists
    $pdo_root = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo_root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database " . DB_NAME . " berhasil dibuat<br>";
    
    // Use the database
    $pdo_root->exec("USE " . DB_NAME);
    
    // Create users table
    $pdo_root->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            balance DECIMAL(15,2) DEFAULT 0.00,
            role ENUM('admin', 'user') DEFAULT 'user',
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            reset_token VARCHAR(100) NULL,
            reset_expires DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Table users berhasil dibuat<br>";
    
    // Create vps_servers table (untuk menyimpan info VPS target)
    $pdo_root->exec("
        CREATE TABLE IF NOT EXISTS vps_servers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            server_key VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            hostname VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            location VARCHAR(100) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            ssh_port INT DEFAULT 22,
            ssh_user VARCHAR(50) DEFAULT 'root',
            ssh_key_path TEXT NULL,
            status ENUM('online', 'offline', 'maintenance') DEFAULT 'online',
            max_accounts INT DEFAULT 100,
            current_accounts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Table vps_servers berhasil dibuat<br>";
    
    // Create vpn_accounts table
    $pdo_root->exec("
        CREATE TABLE IF NOT EXISTS vpn_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            vps_server_id INT NOT NULL,
            account_type ENUM('ssh', 'vmess', 'vless', 'trojan', 'shadowsocks', 'openvpn') NOT NULL,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(100) NOT NULL,
            uuid VARCHAR(100) NULL,
            port INT NULL,
            path VARCHAR(100) NULL,
            domain VARCHAR(255) NULL,
            expired_date DATE NOT NULL,
            status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (vps_server_id) REFERENCES vps_servers(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_server_id (vps_server_id),
            INDEX idx_expired_date (expired_date)
        )
    ");
    echo "✓ Table vpn_accounts berhasil dibuat<br>";
    
    // Create transactions table
    $pdo_root->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('topup', 'purchase', 'refund') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT NOT NULL,
            reference_id VARCHAR(100) NULL,
            status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_status (status)
        )
    ");
    echo "✓ Table transactions berhasil dibuat<br>";
    
    // Create settings table
    $pdo_root->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Table settings berhasil dibuat<br>";
    
    // Insert default admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo_root->exec("
        INSERT IGNORE INTO users (username, email, password, full_name, balance, role) 
        VALUES ('admin', 'admin@alrelshop.com', '$admin_password', 'Administrator AlrelShop', 1000000.00, 'admin')
    ");
    echo "✓ User admin berhasil dibuat<br>";
    
    // Insert VPS servers dari config
    $vps_targets = getVPSTargets();
    foreach ($vps_targets as $key => $vps) {
        $stmt = $pdo_root->prepare("
            INSERT IGNORE INTO vps_servers (server_key, name, hostname, ip_address, location, provider, ssh_port, ssh_user, ssh_key_path, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $key,
            $vps['name'],
            $vps['host'],
            $vps['ip'],
            $vps['location'],
            $vps['provider'],
            $vps['ssh_port'],
            $vps['ssh_user'],
            $vps['ssh_key'],
            $vps['status']
        ]);
    }
    echo "✓ VPS servers berhasil ditambahkan<br>";
    
    // Insert default settings
    $pdo_root->exec("
        INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
        ('panel_name', 'AlrelShop', 'Nama panel'),
        ('account_price', '18000', 'Harga per akun VPN (Rp)'),
        ('max_accounts_per_user', '10', 'Maksimal akun per user'),
        ('default_account_duration', '30', 'Durasi default akun (hari)'),
        ('maintenance_mode', '0', 'Mode maintenance (0=off, 1=on)'),
        ('telegram_bot_token', '', 'Token bot Telegram untuk notifikasi'),
        ('telegram_chat_id', '', 'Chat ID Telegram untuk notifikasi')
    ");
    echo "✓ Settings default berhasil ditambahkan<br>";
    
    echo "<hr>";
    echo "<h3>✅ Instalasi Database Berhasil!</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Informasi Login:</strong><br>";
    echo "Username: <code>admin</code><br>";
    echo "Password: <code>admin123</code><br>";
    echo "Saldo awal: <code>Rp 1.000.000</code><br>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>⚠️ PENTING - Konfigurasi SSH:</strong><br>";
    echo "1. Generate SSH key pair di server panel ini<br>";
    echo "2. Copy public key ke semua VPS target (authorized_keys)<br>";
    echo "3. Update path SSH private key di config.php<br>";
    echo "4. Test koneksi SSH ke setiap VPS target<br>";
    echo "</div>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>📝 Langkah Selanjutnya:</strong><br>";
    echo "1. Konfigurasi SSH keys untuk akses VPS target<br>";
    echo "2. Update domain/IP VPS target di config.php<br>";
    echo "3. Install requirements: php-ssh2, php-curl<br>";
    echo "4. Test koneksi ke VPS target<br>";
    echo "5. <a href='login.php'><strong>Login ke Panel</strong></a><br>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>❌ Error Database:</strong><br>" . $e->getMessage();
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Install AlrelShop Panel</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; }
        hr { margin: 20px 0; }
    </style>
</head>
</html>

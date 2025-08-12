<?php
// AlrelShop Panel Configuration - Terpisah dari Script VPS
date_default_timezone_set('Asia/Jakarta');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '9194595963se');
define('DB_NAME', 'sql_panel_alrels');

// Panel Configuration
define('PANEL_NAME', 'AlrelShop');
define('PANEL_URL', 'https://panel.alrelshop.my.id');
define('ADMIN_EMAIL', 'admin@alrelshop.com');

// Pricing Configuration (dalam Rupiah)
define('PRICE_PER_ACCOUNT', 18000); // 18rb per akun

// VPS Target Configuration - Tambahkan VPS target di sini
$vps_targets = [
    'vps1' => [
        'name' => 'ANYM NETWORK',
        'host' => 'servervip5.alrelshop.my.id', // Sesuaikan dengan domain VPS target
        'ip' => '140.213.202.13',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'ssh_key' => '/root/.ssh/alrelshop_panel', // Path ke SSH key untuk akses VPS
        'location' => 'Indonesia',
        'provider' => 'DigitalOcean',
        'status' => 'online'
    ],
    'vps2' => [
        'name' => 'ARGON DATA 1',
        'host' => 'servervip10.alrelshop.my.id',
        'ip' => '103.150.197.96',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'ssh_key' => '/root/.ssh/alrelshop_panel',
        'location' => 'Indonesia',
        'provider' => 'Vultr',
        'status' => 'online'
    ],
    'vps3' => [
        'name' => 'DIGITAL OCEAN 2',
        'host' => 'sg-vip-9.alrelshop.my.id',
        'ip' => '128.199.104.75',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'ssh_key' => '/root/.ssh/alrelshop_panel',
        'location' => 'Singapura',
        'provider' => 'DigitalOcean',
        'status' => 'online'
    ]
];

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Helper Functions
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// SSH Connection Function untuk VPS Target
function executeSSHCommand($vps_key, $command) {
    global $vps_targets;
    
    if (!isset($vps_targets[$vps_key])) {
        return ['success' => false, 'message' => 'VPS tidak ditemukan'];
    }
    
    $vps = $vps_targets[$vps_key];
    
    // Gunakan SSH2 extension atau exec dengan ssh command
    // Contoh menggunakan exec (pastikan SSH key sudah dikonfigurasi)
    $ssh_command = "ssh -o StrictHostKeyChecking=no -i {$vps['ssh_key']} {$vps['ssh_user']}@{$vps['ip']} -p {$vps['ssh_port']} '{$command}' 2>&1";
    
    $output = [];
    $return_var = 0;
    exec($ssh_command, $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

// Get VPS Targets
function getVPSTargets() {
    global $vps_targets;
    return $vps_targets;
}
?>
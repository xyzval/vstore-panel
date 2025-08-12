<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Get VPS servers from database
$stmt = $pdo->prepare("SELECT * FROM vps_servers WHERE status = 'online' ORDER BY name");
$stmt->execute();
$vps_servers = $stmt->fetchAll();

$error = '';
$success = '';
$selected_server = isset($_GET['server']) ? $_GET['server'] : '';

// Handle account creation
if ($_POST['action'] == 'create_account') {
    $server_key = $_POST['server'];
    $account_type = $_POST['account_type'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $duration = (int)$_POST['duration'];
    
    // Validation
    if (empty($server_key) || empty($account_type) || empty($username) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } else {
        // Check if server exists and is online
        $stmt = $pdo->prepare("SELECT * FROM vps_servers WHERE id = ? AND status = 'online'");
        $stmt->execute([$server_key]);
        $selected_vps = $stmt->fetch();
        
        if (!$selected_vps) {
            $error = 'Server tidak valid atau sedang offline!';
        } elseif ($user['balance'] < PRICE_PER_ACCOUNT) {
        $error = 'Saldo tidak mencukupi! Minimal ' . formatRupiah(PRICE_PER_ACCOUNT);
            } else {
            // Check if username already exists on this server
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM vpn_accounts 
                WHERE username = ? AND vps_server_id = ? AND status = 'active'
            ");
            $stmt->execute([$username, $server_key]);
        
        if ($stmt->fetch()['count'] > 0) {
            $error = 'Username sudah digunakan di server ini!';
        } else {
            $pdo->beginTransaction();
            
            try {
                $server_id = $selected_vps['id'];
                $expired_date = date('Y-m-d', strtotime("+{$duration} days"));
                $uuid = generateUUID();
                
                // Create VPN account record
                $stmt = $pdo->prepare("
                    INSERT INTO vpn_accounts (user_id, vps_server_id, account_type, username, password, uuid, expired_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$user['id'], $server_id, $account_type, $username, $password, $uuid, $expired_date]);
                
                // Deduct balance
                $new_balance = $user['balance'] - PRICE_PER_ACCOUNT;
                $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->execute([$new_balance, $user['id']]);
                
                // Record transaction
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, type, amount, description, status) 
                    VALUES (?, 'purchase', ?, ?, 'success')
                ");
                $stmt->execute([
                    $user['id'], 
                    PRICE_PER_ACCOUNT, 
                    "Pembuatan akun {$account_type} - {$username} di {$selected_vps['name']}"
                ]);
                
                // Execute command on VPS target
                $result = createVPNAccount($selected_vps, $account_type, $username, $password, $duration, $uuid);
                
                if ($result['success']) {
                    $pdo->commit();
                    $success = 'Akun VPN berhasil dibuat! Saldo terpotong ' . formatRupiah(PRICE_PER_ACCOUNT);
                    
                    // Update user session
                    $_SESSION['balance'] = $new_balance;
                } else {
                    // Rollback if VPS command failed
                    $pdo->rollBack();
                    $error = 'Gagal membuat akun di server: ' . $result['message'];
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Function to create VPN account on target VPS
function createVPNAccount($vps_server, $type, $username, $password, $duration, $uuid) {
    
    $commands = [
        'ssh' => "menu-ssh create {$username} {$password} {$duration}",
        'vmess' => "menu-vmess create {$username} {$uuid} {$duration}",
        'vless' => "menu-vless create {$username} {$uuid} {$duration}",
        'trojan' => "menu-trojan create {$username} {$password} {$duration}",
        'shadowsocks' => "menu-shadowsocks create {$username} {$password} {$duration}",
        'openvpn' => "menu-openvpn create {$username} {$password} {$duration}"
    ];
    
    if (!isset($commands[$type])) {
        return ['success' => false, 'message' => 'Tipe akun tidak valid'];
    }
    
    // Execute SSH command
    $ssh_key = '/var/www/.ssh/alrelshop_panel';
    $command = $commands[$type];
    
    $ssh_command = "ssh -o ConnectTimeout=30 -o StrictHostKeyChecking=no -i {$ssh_key} {$vps_server['ssh_user']}@{$vps_server['ip_address']} -p {$vps_server['ssh_port']} '{$command}' 2>&1";
    
    $output = [];
    $return_var = 0;
    exec($ssh_command, $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

// Get account types with prices
$account_types = [
    'ssh' => ['name' => 'SSH Tunnel', 'description' => 'SSH Websocket + SSL/TLS', 'icon' => 'terminal'],
    'vmess' => ['name' => 'VMess', 'description' => 'V2Ray VMess Protocol', 'icon' => 'shield-alt'],
    'vless' => ['name' => 'VLess', 'description' => 'V2Ray VLess Protocol', 'icon' => 'user-shield'],
    'trojan' => ['name' => 'Trojan', 'description' => 'Trojan-Go Protocol', 'icon' => 'mask'],
    'shadowsocks' => ['name' => 'Shadowsocks', 'description' => 'Shadowsocks Protocol', 'icon' => 'eye-slash'],
    'openvpn' => ['name' => 'OpenVPN', 'description' => 'OpenVPN Protocol', 'icon' => 'vpn']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun VPN - <?= PANEL_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        /* Include sidebar styles from dashboard */
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
        }

        .sidebar-header .logo i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .sidebar-header .logo span {
            font-size: 20px;
            font-weight: 700;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: block;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: #3498db;
        }

        .menu-item i {
            width: 20px;
            margin-right: 12px;
        }

        .menu-section {
            padding: 10px 25px 5px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-content {
            margin-left: 0;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .main-content.sidebar-open {
            margin-left: 280px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #f8f9fa;
            color: #333;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .balance-info {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .content {
            padding: 30px;
        }

        .create-form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .form-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .form-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .form-body {
            padding: 40px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .server-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .server-card:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .server-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .server-card input[type="radio"] {
            display: none;
        }

        .server-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .server-location {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .server-status {
            display: inline-block;
            padding: 4px 12px;
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .account-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .account-type-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .account-type-card:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .account-type-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .account-type-card input[type="radio"] {
            display: none;
        }

        .account-type-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 15px;
        }

        .account-type-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .account-type-desc {
            color: #666;
            font-size: 13px;
        }

        .price-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
        }

        .price-amount {
            font-size: 24px;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 5px;
        }

        .price-desc {
            color: #666;
            font-size: 14px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 768px) {
            .main-content.sidebar-open {
                margin-left: 0;
            }
            
            .server-grid,
            .account-type-grid {
                grid-template-columns: 1fr;
            }
            
            .content {
                padding: 20px;
            }
            
            .form-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-shield-alt"></i>
                <span><?= PANEL_NAME ?></span>
            </a>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <div class="menu-section">Server Management</div>
            <a href="servers.php" class="menu-item">
                <i class="fas fa-server"></i> Daftar Server
            </a>
            <a href="create-account.php" class="menu-item active">
                <i class="fas fa-plus-circle"></i> Buat Akun VPN
            </a>
            <a href="my-accounts.php" class="menu-item">
                <i class="fas fa-list"></i> Akun Saya
            </a>
            
            <div class="menu-section">Keuangan</div>
            <a href="topup.php" class="menu-item">
                <i class="fas fa-wallet"></i> Top Up Saldo
            </a>
            <a href="transactions.php" class="menu-item">
                <i class="fas fa-history"></i> Riwayat Transaksi
            </a>
            
            <div class="menu-section">Akun</div>
            <a href="profile.php" class="menu-item">
                <i class="fas fa-user"></i> Profil Saya
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title">
                    <h1>Buat Akun VPN</h1>
                </div>
            </div>
            
            <div class="header-right">
                <div class="balance-info">
                    <i class="fas fa-wallet"></i> <?= formatRupiah($user['balance']) ?>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content">
            <div class="create-form-container">
                <div class="form-header">
                    <h2>Buat Akun VPN Baru</h2>
                    <p>Pilih server dan tipe akun yang diinginkan</p>
                </div>
                
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?= htmlspecialchars($success) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="createAccountForm">
                        <input type="hidden" name="action" value="create_account">
                        
                        <!-- Server Selection -->
                        <div class="form-group">
                            <label><i class="fas fa-server"></i> Pilih Server VPS</label>
                            <div class="server-grid">
                                <?php foreach ($vps_servers as $key => $server): ?>
                                <div class="server-card <?= $selected_server == $key ? 'selected' : '' ?>" onclick="selectServer('<?= $key ?>')">
                                    <input type="radio" name="server" value="<?= $key ?>" <?= $selected_server == $key ? 'checked' : '' ?>>
                                    <div class="server-name"><?= htmlspecialchars($server['name']) ?></div>
                                    <div class="server-location">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($server['location']) ?>
                                    </div>
                                    <div class="server-status">Online</div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Account Type Selection -->
                        <div class="form-group">
                            <label><i class="fas fa-shield-alt"></i> Tipe Akun VPN</label>
                            <div class="account-type-grid">
                                <?php foreach ($account_types as $type => $info): ?>
                                <div class="account-type-card" onclick="selectAccountType('<?= $type ?>')">
                                    <input type="radio" name="account_type" value="<?= $type ?>">
                                    <div class="account-type-icon">
                                        <i class="fas fa-<?= $info['icon'] ?>"></i>
                                    </div>
                                    <div class="account-type-name"><?= $info['name'] ?></div>
                                    <div class="account-type-desc"><?= $info['description'] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Price Info -->
                        <div class="price-info">
                            <div class="price-amount"><?= formatRupiah(PRICE_PER_ACCOUNT) ?></div>
                            <div class="price-desc">Harga per akun VPN (30 hari)</div>
                        </div>
                        
                        <!-- Account Details -->
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username</label>
                            <input type="text" name="username" required placeholder="Masukkan username unik" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" required placeholder="Masukkan password" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Durasi Akun (Hari)</label>
                            <select name="duration" required>
                                <option value="30">30 Hari</option>
                                <option value="60">60 Hari (+<?= formatRupiah(PRICE_PER_ACCOUNT) ?>)</option>
                                <option value="90">90 Hari (+<?= formatRupiah(PRICE_PER_ACCOUNT * 2) ?>)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn" id="submitBtn" disabled>
                            <i class="fas fa-plus-circle"></i> Buat Akun VPN - <?= formatRupiah(PRICE_PER_ACCOUNT) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
        });
        
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            mainContent.classList.remove('sidebar-open');
            sidebarOverlay.classList.remove('active');
        });
        
        // Server Selection
        function selectServer(serverKey) {
            document.querySelectorAll('.server-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.querySelector(`input[name="server"][value="${serverKey}"]`).checked = true;
            
            checkFormValid();
        }
        
        // Account Type Selection
        function selectAccountType(type) {
            document.querySelectorAll('.account-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.querySelector(`input[name="account_type"][value="${type}"]`).checked = true;
            
            checkFormValid();
        }
        
        // Check if form is valid
        function checkFormValid() {
            const server = document.querySelector('input[name="server"]:checked');
            const accountType = document.querySelector('input[name="account_type"]:checked');
            const username = document.querySelector('input[name="username"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            const submitBtn = document.getElementById('submitBtn');
            
            if (server && accountType && username && password) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }
        
        // Form validation
        document.querySelector('input[name="username"]').addEventListener('input', checkFormValid);
        document.querySelector('input[name="password"]').addEventListener('input', checkFormValid);
        
        // Duration change handler
        document.querySelector('select[name="duration"]').addEventListener('change', function() {
            const duration = this.value;
            const submitBtn = document.getElementById('submitBtn');
            const basePrice = <?= PRICE_PER_ACCOUNT ?>;
            
            let totalPrice = basePrice;
            if (duration == '60') totalPrice = basePrice * 2;
            if (duration == '90') totalPrice = basePrice * 3;
            
            const formatted = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(totalPrice);
            
            submitBtn.innerHTML = `<i class="fas fa-plus-circle"></i> Buat Akun VPN - ${formatted}`;
        });
        
        // Form submission
        document.getElementById('createAccountForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membuat Akun...';
        });
        
        // Initialize
        checkFormValid();
    </script>
</body>
</html>

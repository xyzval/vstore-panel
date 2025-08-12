<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Get VPS servers from database
$stmt = $pdo->prepare("SELECT * FROM vps_servers WHERE status = 'online' ORDER BY name");
$stmt->execute();
$vps_servers = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_accounts FROM vpn_accounts WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user['id']]);
$total_accounts = $stmt->fetch()['total_accounts'];

$stmt = $pdo->prepare("SELECT COUNT(*) as expired_accounts FROM vpn_accounts WHERE user_id = ? AND status = 'expired'");
$stmt->execute([$user['id']]);
$expired_accounts = $stmt->fetch()['expired_accounts'];

// Get recent transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$recent_transactions = $stmt->fetchAll();

// Get active accounts
$stmt = $pdo->prepare("
    SELECT va.*, vs.name as server_name, vs.location 
    FROM vpn_accounts va 
    JOIN vps_servers vs ON va.vps_server_id = vs.id 
    WHERE va.user_id = ? AND va.status = 'active' 
    ORDER BY va.created_at DESC 
    LIMIT 10
");
$stmt->execute([$user['id']]);
$active_accounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= PANEL_NAME ?></title>
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

        /* Sidebar Styles */
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

        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .main-content.sidebar-open {
            margin-left: 280px;
        }

        /* Header */
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-info:hover {
            background: #e9ecef;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details .name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-details .balance {
            font-size: 12px;
            color: #666;
        }

        /* Content Area */
        .content {
            padding: 30px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.balance {
            border-left-color: #27ae60;
        }

        .stat-card.accounts {
            border-left-color: #3498db;
        }

        .stat-card.expired {
            border-left-color: #e74c3c;
        }

        .stat-card.servers {
            border-left-color: #f39c12;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-icon.balance { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stat-icon.accounts { background: linear-gradient(135deg, #3498db, #74b9ff); }
        .stat-icon.expired { background: linear-gradient(135deg, #e74c3c, #ff7675); }
        .stat-icon.servers { background: linear-gradient(135deg, #f39c12, #fdcb6e); }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: between;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        /* Server Grid */
        .server-grid {
            display: grid;
            gap: 15px;
        }

        .server-item {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .server-item:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .server-item.online {
            border-left-color: #27ae60;
        }

        .server-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .server-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .server-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .server-status.online {
            background: #d4edda;
            color: #155724;
        }

        .server-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #666;
        }

        /* Transaction List */
        .transaction-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .transaction-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .transaction-icon.topup {
            background: #d4edda;
            color: #155724;
        }

        .transaction-icon.purchase {
            background: #fff3cd;
            color: #856404;
        }

        .transaction-details .desc {
            font-weight: 500;
            font-size: 14px;
        }

        .transaction-details .date {
            font-size: 12px;
            color: #666;
        }

        .transaction-amount {
            font-weight: 600;
        }

        .transaction-amount.positive {
            color: #27ae60;
        }

        .transaction-amount.negative {
            color: #e74c3c;
        }

        /* Account List */
        .account-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .account-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .account-item:last-child {
            border-bottom: none;
        }

        .account-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .account-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .account-type.ssh { background: #e3f2fd; color: #1976d2; }
        .account-type.vmess { background: #f3e5f5; color: #7b1fa2; }
        .account-type.vless { background: #e8f5e8; color: #388e3c; }
        .account-type.trojan { background: #fff3e0; color: #f57c00; }

        .account-details .username {
            font-weight: 500;
            font-size: 14px;
        }

        .account-details .server {
            font-size: 12px;
            color: #666;
        }

        .account-expiry {
            text-align: right;
            font-size: 12px;
        }

        .expiry-date {
            font-weight: 500;
        }

        .expiry-days {
            color: #666;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .header {
                padding: 15px 20px;
            }
            
            .content {
                padding: 20px;
            }
        }

        /* Overlay for mobile */
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
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <div class="menu-section">Server Management</div>
            <a href="servers.php" class="menu-item">
                <i class="fas fa-server"></i> Daftar Server
            </a>
            <a href="create-account.php" class="menu-item">
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
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i> Pengaturan
            </a>
            
            <div class="menu-section">Lainnya</div>
            <a href="support.php" class="menu-item">
                <i class="fas fa-headset"></i> Support
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
                    <h1>Dashboard</h1>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div class="balance"><?= formatRupiah($user['balance']) ?></div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card balance">
                    <div class="stat-header">
                        <div class="stat-icon balance">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= formatRupiah($user['balance']) ?></div>
                    <div class="stat-label">Saldo Tersedia</div>
                </div>
                
                <div class="stat-card accounts">
                    <div class="stat-header">
                        <div class="stat-icon accounts">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_accounts ?></div>
                    <div class="stat-label">Akun Aktif</div>
                </div>
                
                <div class="stat-card expired">
                    <div class="stat-header">
                        <div class="stat-icon expired">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $expired_accounts ?></div>
                    <div class="stat-label">Akun Expired</div>
                </div>
                
                <div class="stat-card servers">
                    <div class="stat-header">
                        <div class="stat-icon servers">
                            <i class="fas fa-server"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= count($vps_servers) ?></div>
                    <div class="stat-label">Server Tersedia</div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Server List -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-server"></i>
                            Server VPS
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="server-grid">
                            <?php foreach ($vps_servers as $server): ?>
                            <div class="server-item online" onclick="location.href='create-account.php?server=<?= $server['id'] ?>'">
                                <div class="server-header">
                                    <div class="server-name"><?= htmlspecialchars($server['name']) ?></div>
                                    <div class="server-status <?= $server['status'] ?>"><?= ucfirst($server['status']) ?></div>
                                </div>
                                <div class="server-info">
                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($server['location']) ?></span>
                                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($server['provider']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Transaksi Terakhir
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="transaction-list">
                            <?php if (empty($recent_transactions)): ?>
                                <div style="text-align: center; padding: 20px; color: #666;">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i>
                                    <p>Belum ada transaksi</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="transaction-icon <?= $transaction['type'] ?>">
                                            <i class="fas fa-<?= $transaction['type'] == 'topup' ? 'plus' : 'minus' ?>"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <div class="desc"><?= htmlspecialchars($transaction['description']) ?></div>
                                            <div class="date"><?= formatDate($transaction['created_at']) ?></div>
                                        </div>
                                    </div>
                                    <div class="transaction-amount <?= $transaction['type'] == 'topup' ? 'positive' : 'negative' ?>">
                                        <?= $transaction['type'] == 'topup' ? '+' : '-' ?><?= formatRupiah($transaction['amount']) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Accounts -->
            <?php if (!empty($active_accounts)): ?>
            <div class="content-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i>
                        Akun VPN Aktif
                    </h3>
                </div>
                <div class="card-body">
                    <div class="account-list">
                        <?php foreach ($active_accounts as $account): ?>
                        <div class="account-item">
                            <div class="account-info">
                                <div class="account-type <?= $account['account_type'] ?>">
                                    <?= strtoupper($account['account_type']) ?>
                                </div>
                                <div class="account-details">
                                    <div class="username"><?= htmlspecialchars($account['username']) ?></div>
                                    <div class="server"><?= htmlspecialchars($account['server_name']) ?> - <?= htmlspecialchars($account['location']) ?></div>
                                </div>
                            </div>
                            <div class="account-expiry">
                                <div class="expiry-date"><?= date('d M Y', strtotime($account['expired_date'])) ?></div>
                                <div class="expiry-days">
                                    <?php
                                    $days_left = floor((strtotime($account['expired_date']) - time()) / (60 * 60 * 24));
                                    echo $days_left > 0 ? $days_left . ' hari lagi' : 'Expired';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
        
        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebarOverlay.classList.remove('active');
            }
        });
        
        // Auto-refresh page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>

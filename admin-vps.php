<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Only admin can access this page
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle VPS operations
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add_vps') {
        $server_key = trim($_POST['server_key']);
        $name = trim($_POST['name']);
        $hostname = trim($_POST['hostname']);
        $ip_address = trim($_POST['ip_address']);
        $location = trim($_POST['location']);
        $provider = trim($_POST['provider']);
        $ssh_port = (int)$_POST['ssh_port'];
        $ssh_user = trim($_POST['ssh_user']);
        
        if (empty($server_key) || empty($name) || empty($ip_address)) {
            $error = 'Server key, nama, dan IP address harus diisi!';
        } else {
            // Check if server key already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vps_servers WHERE server_key = ?");
            $stmt->execute([$server_key]);
            
            if ($stmt->fetch()['count'] > 0) {
                $error = 'Server key sudah digunakan!';
            } else {
                // Insert new VPS
                $stmt = $pdo->prepare("
                    INSERT INTO vps_servers (server_key, name, hostname, ip_address, location, provider, ssh_port, ssh_user, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online')
                ");
                
                if ($stmt->execute([$server_key, $name, $hostname, $ip_address, $location, $provider, $ssh_port, $ssh_user])) {
                    $success = 'VPS berhasil ditambahkan!';
                } else {
                    $error = 'Gagal menambahkan VPS!';
                }
            }
        }
    }
    
    elseif ($action == 'update_vps') {
        $id = (int)$_POST['vps_id'];
        $name = trim($_POST['name']);
        $hostname = trim($_POST['hostname']);
        $location = trim($_POST['location']);
        $provider = trim($_POST['provider']);
        $ssh_port = (int)$_POST['ssh_port'];
        $ssh_user = trim($_POST['ssh_user']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("
            UPDATE vps_servers 
            SET name = ?, hostname = ?, location = ?, provider = ?, ssh_port = ?, ssh_user = ?, status = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $hostname, $location, $provider, $ssh_port, $ssh_user, $status, $id])) {
            $success = 'VPS berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate VPS!';
        }
    }
    
    elseif ($action == 'delete_vps') {
        $id = (int)$_POST['vps_id'];
        
        // Check if VPS has active accounts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vpn_accounts WHERE vps_server_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $active_accounts = $stmt->fetch()['count'];
        
        if ($active_accounts > 0) {
            $error = "Tidak dapat menghapus VPS! Masih ada $active_accounts akun aktif.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vps_servers WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = 'VPS berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus VPS!';
            }
        }
    }
    
    elseif ($action == 'test_connection') {
        $id = (int)$_POST['vps_id'];
        
        $stmt = $pdo->prepare("SELECT * FROM vps_servers WHERE id = ?");
        $stmt->execute([$id]);
        $vps = $stmt->fetch();
        
        if ($vps) {
            $result = testVPSConnection($vps);
            if ($result['success']) {
                $success = "Koneksi ke {$vps['name']} berhasil! Response: " . $result['output'];
            } else {
                $error = "Koneksi ke {$vps['name']} gagal! Error: " . $result['output'];
            }
        }
    }
    
    elseif ($action == 'auto_detect') {
        $ip_address = trim($_POST['ip_address']);
        
        if (empty($ip_address)) {
            $error = 'IP address harus diisi!';
        } else {
            $vps_info = autoDetectVPSInfo($ip_address);
            
            if ($vps_info['success']) {
                $success = 'Info VPS berhasil dideteksi!';
                $_POST = array_merge($_POST, $vps_info['data']); // Pre-fill form
            } else {
                $error = 'Gagal mendeteksi info VPS: ' . $vps_info['message'];
            }
        }
    }
}

// Get all VPS servers
$stmt = $pdo->prepare("SELECT * FROM vps_servers ORDER BY created_at DESC");
$stmt->execute();
$vps_servers = $stmt->fetchAll();

// Function to test VPS connection
function testVPSConnection($vps) {
    $ssh_key = '/var/www/.ssh/alrelshop_panel';
    $command = "echo 'Connection test successful'";
    
    $ssh_command = "ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no -i {$ssh_key} {$vps['ssh_user']}@{$vps['ip_address']} -p {$vps['ssh_port']} '{$command}' 2>&1";
    
    $output = [];
    $return_var = 0;
    exec($ssh_command, $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

// Function to auto-detect VPS info
function autoDetectVPSInfo($ip) {
    $ssh_key = '/var/www/.ssh/alrelshop_panel';
    $info = [
        'success' => false,
        'data' => [],
        'message' => ''
    ];
    
    // Test SSH connection
    $test_command = "ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no -i {$ssh_key} root@{$ip} 'echo \"CONNECTION_OK\"' 2>&1";
    $output = [];
    $return_var = 0;
    exec($test_command, $output, $return_var);
    
    if ($return_var !== 0) {
        $info['message'] = 'SSH connection failed: ' . implode("\n", $output);
        return $info;
    }
    
    // Get system information
    $commands = [
        'hostname' => 'hostname',
        'os_info' => 'cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d \'"\'',
        'location' => 'curl -s ipinfo.io/country 2>/dev/null || echo "Unknown"',
        'provider' => 'curl -s ipinfo.io/org 2>/dev/null | cut -d\' \' -f2- || echo "Unknown"',
        'menu_exists' => 'command -v menu >/dev/null && echo "YES" || echo "NO"',
        'alrelshop_script' => 'ls /usr/local/sbin/menu >/dev/null 2>&1 && echo "YES" || echo "NO"'
    ];
    
    $detected_info = [];
    
    foreach ($commands as $key => $command) {
        $cmd = "ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no -i {$ssh_key} root@{$ip} '{$command}' 2>/dev/null";
        $result = trim(shell_exec($cmd));
        $detected_info[$key] = $result ?: 'Unknown';
    }
    
    // Generate server key
    $server_key = 'vps_' . substr(md5($ip . time()), 0, 8);
    
    // Determine provider from org info
    $provider_map = [
        'digitalocean' => 'DigitalOcean',
        'vultr' => 'Vultr',
        'linode' => 'Linode',
        'amazon' => 'AWS',
        'google' => 'Google Cloud',
        'microsoft' => 'Azure'
    ];
    
    $provider = 'Unknown';
    foreach ($provider_map as $key => $value) {
        if (stripos($detected_info['provider'], $key) !== false) {
            $provider = $value;
            break;
        }
    }
    
    // Map country code to location
    $location_map = [
        'US' => 'United States',
        'SG' => 'Singapore', 
        'ID' => 'Indonesia',
        'JP' => 'Japan',
        'GB' => 'United Kingdom',
        'DE' => 'Germany',
        'FR' => 'France',
        'NL' => 'Netherlands'
    ];
    
    $location = $location_map[$detected_info['location']] ?? $detected_info['location'];
    
    $info['success'] = true;
    $info['data'] = [
        'server_key' => $server_key,
        'name' => strtoupper(str_replace(['-', '.'], ' ', $detected_info['hostname'])) . ' SERVER',
        'hostname' => $detected_info['hostname'],
        'ip_address' => $ip,
        'location' => $location,
        'provider' => $provider,
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'os_info' => $detected_info['os_info'],
        'menu_available' => $detected_info['menu_exists'] === 'YES',
        'alrelshop_script' => $detected_info['alrelshop_script'] === 'YES'
    ];
    
    return $info;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen VPS - <?= PANEL_NAME ?></title>
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

        /* Sidebar styles - same as dashboard */
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

        .content {
            padding: 30px;
        }

        /* VPS Management Styles */
        .page-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #fdcb6e);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #ff7675);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* VPS List Table */
        .vps-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-header h3 {
            font-size: 18px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f1f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }

        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons-table {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* Auto Detect Form */
        .auto-detect-form {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }

        .auto-detect-form h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detect-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: end;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .detect-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 10px 8px;
            }
        }

        /* Modal styles for edit form */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-header h3 {
            color: #2c3e50;
            font-size: 20px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
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
            <a href="admin-vps.php" class="menu-item active">
                <i class="fas fa-server"></i> Kelola VPS
            </a>
            <a href="servers.php" class="menu-item">
                <i class="fas fa-list"></i> Daftar Server
            </a>
            <a href="create-account.php" class="menu-item">
                <i class="fas fa-plus-circle"></i> Buat Akun VPN
            </a>
            <a href="my-accounts.php" class="menu-item">
                <i class="fas fa-user-shield"></i> Akun Saya
            </a>
            
            <div class="menu-section">Keuangan</div>
            <a href="topup.php" class="menu-item">
                <i class="fas fa-wallet"></i> Top Up Saldo
            </a>
            <a href="transactions.php" class="menu-item">
                <i class="fas fa-history"></i> Riwayat Transaksi
            </a>
            
            <div class="menu-section">Admin</div>
            <a href="admin-users.php" class="menu-item">
                <i class="fas fa-users"></i> Kelola User
            </a>
            <a href="admin-settings.php" class="menu-item">
                <i class="fas fa-cogs"></i> Pengaturan
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
                    <h1>Manajemen VPS</h1>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-server"></i> Manajemen VPS Server</h2>
                <p>Kelola semua VPS target untuk pembuatan akun VPN</p>
            </div>
            
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
            
            <!-- Auto Detect VPS -->
            <div class="auto-detect-form">
                <h3><i class="fas fa-magic"></i> Auto Detect VPS Info</h3>
                <p style="margin-bottom: 15px; color: #666;">Masukkan IP VPS untuk otomatis mendeteksi informasi server</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="auto_detect">
                    <div class="detect-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="text" name="ip_address" placeholder="Masukkan IP Address VPS" required 
                                   pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Detect Info
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Add VPS Form -->
            <div class="form-container">
                <h3 style="margin-bottom: 20px; color: #2c3e50;">
                    <i class="fas fa-plus-circle"></i> Tambah VPS Baru
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_vps">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Server Key</label>
                            <input type="text" name="server_key" required placeholder="vps_001" 
                                   value="<?= htmlspecialchars($_POST['server_key'] ?? '') ?>">
                            <small style="color: #666;">Unique identifier untuk VPS ini</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nama Server</label>
                            <input type="text" name="name" required placeholder="DIGITAL OCEAN SG" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Hostname</label>
                            <input type="text" name="hostname" placeholder="sg-vip-01.domain.com" 
                                   value="<?= htmlspecialchars($_POST['hostname'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-network-wired"></i> IP Address</label>
                            <input type="text" name="ip_address" required placeholder="1.2.3.4" 
                                   pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                   value="<?= htmlspecialchars($_POST['ip_address'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Lokasi</label>
                            <input type="text" name="location" placeholder="Singapore" 
                                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Provider</label>
                            <select name="provider">
                                <option value="DigitalOcean" <?= ($_POST['provider'] ?? '') == 'DigitalOcean' ? 'selected' : '' ?>>DigitalOcean</option>
                                <option value="Vultr" <?= ($_POST['provider'] ?? '') == 'Vultr' ? 'selected' : '' ?>>Vultr</option>
                                <option value="Linode" <?= ($_POST['provider'] ?? '') == 'Linode' ? 'selected' : '' ?>>Linode</option>
                                <option value="AWS" <?= ($_POST['provider'] ?? '') == 'AWS' ? 'selected' : '' ?>>Amazon AWS</option>
                                <option value="Google Cloud" <?= ($_POST['provider'] ?? '') == 'Google Cloud' ? 'selected' : '' ?>>Google Cloud</option>
                                <option value="Azure" <?= ($_POST['provider'] ?? '') == 'Azure' ? 'selected' : '' ?>>Microsoft Azure</option>
                                <option value="Other" <?= ($_POST['provider'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-plug"></i> SSH Port</label>
                            <input type="number" name="ssh_port" value="<?= $_POST['ssh_port'] ?? '22' ?>" min="1" max="65535">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> SSH User</label>
                            <input type="text" name="ssh_user" value="<?= $_POST['ssh_user'] ?? 'root' ?>">
                        </div>
                    </div>
                    
                    <?php if (isset($_POST['os_info']) && $_POST['auto_detect']): ?>
                    <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <h4 style="color: #27ae60; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Info Terdeteksi:</h4>
                        <ul style="margin-left: 20px; color: #666;">
                            <li>OS: <?= htmlspecialchars($_POST['os_info']) ?></li>
                            <li>Menu Available: <?= $_POST['menu_available'] ? 'Yes' : 'No' ?></li>
                            <li>AlrelShop Script: <?= $_POST['alrelshop_script'] ? 'Detected' : 'Not Found' ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-plus-circle"></i> Tambah VPS Server
                    </button>
                </form>
            </div>
            
            <!-- VPS List -->
            <div class="vps-table">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Daftar VPS Server (<?= count($vps_servers) ?>)</h3>
                </div>
                
                <?php if (empty($vps_servers)): ?>
                    <div style="text-align: center; padding: 50px; color: #666;">
                        <i class="fas fa-server" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>Belum ada VPS server. Tambahkan VPS pertama Anda!</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Server</th>
                                <th>IP Address</th>
                                <th>Lokasi</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Akun</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vps_servers as $vps): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($vps['name']) ?></strong><br>
                                    <small style="color: #666;"><?= htmlspecialchars($vps['server_key']) ?></small>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($vps['ip_address']) ?></code><br>
                                    <small style="color: #666;">Port: <?= $vps['ssh_port'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($vps['location']) ?></td>
                                <td><?= htmlspecialchars($vps['provider']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $vps['status'] ?>">
                                        <?= ucfirst($vps['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vpn_accounts WHERE vps_server_id = ? AND status = 'active'");
                                    $stmt->execute([$vps['id']]);
                                    $account_count = $stmt->fetch()['count'];
                                    ?>
                                    <strong><?= $account_count ?></strong> aktif
                                </td>
                                <td>
                                    <div class="action-buttons-table">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="test_connection">
                                            <input type="hidden" name="vps_id" value="<?= $vps['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Test Koneksi">
                                                <i class="fas fa-plug"></i>
                                            </button>
                                        </form>
                                        
                                        <button class="btn btn-warning btn-sm" onclick="editVPS(<?= htmlspecialchars(json_encode($vps)) ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus VPS ini?')">
                                            <input type="hidden" name="action" value="delete_vps">
                                            <input type="hidden" name="vps_id" value="<?= $vps['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit VPS Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit VPS Server</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_vps">
                <input type="hidden" name="vps_id" id="edit_vps_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nama Server</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Hostname</label>
                        <input type="text" name="hostname" id="edit_hostname">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Lokasi</label>
                        <input type="text" name="location" id="edit_location">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Provider</label>
                        <select name="provider" id="edit_provider">
                            <option value="DigitalOcean">DigitalOcean</option>
                            <option value="Vultr">Vultr</option>
                            <option value="Linode">Linode</option>
                            <option value="AWS">Amazon AWS</option>
                            <option value="Google Cloud">Google Cloud</option>
                            <option value="Azure">Microsoft Azure</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-plug"></i> SSH Port</label>
                        <input type="number" name="ssh_port" id="edit_ssh_port" min="1" max="65535">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> SSH User</label>
                        <input type="text" name="ssh_user" id="edit_ssh_user">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" id="edit_status">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 20px;">
                    <i class="fas fa-save"></i> Update VPS Server
                </button>
            </form>
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
        
        // Edit VPS Modal
        function editVPS(vps) {
            document.getElementById('edit_vps_id').value = vps.id;
            document.getElementById('edit_name').value = vps.name;
            document.getElementById('edit_hostname').value = vps.hostname;
            document.getElementById('edit_location').value = vps.location;
            document.getElementById('edit_provider').value = vps.provider;
            document.getElementById('edit_ssh_port').value = vps.ssh_port;
            document.getElementById('edit_ssh_user').value = vps.ssh_user;
            document.getElementById('edit_status').value = vps.status;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Auto-detect form enhancement
        document.querySelector('input[name="ip_address"]').addEventListener('input', function() {
            const ip = this.value;
            const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            
            if (ipPattern.test(ip)) {
                this.style.borderColor = '#27ae60';
            } else if (ip.length > 0) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
    </script>
</body>
</html>

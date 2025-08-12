<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username/email atau password salah!';
        }
    }
}

// Handle registration
if (isset($_POST['action']) && $_POST['action'] == 'register') {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $full_name = trim($_POST['reg_fullname']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username atau email sudah digunakan!';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $full_name, $hashed_password])) {
                $success = 'Akun berhasil dibuat! Silakan login.';
            } else {
                $error = 'Gagal membuat akun. Silakan coba lagi.';
            }
        }
    }
}

// Handle forgot password
if (isset($_POST['action']) && $_POST['action'] == 'forgot_password') {
    $email = trim($_POST['forgot_email']);
    
    if (empty($email)) {
        $error = 'Email harus diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $reset_token = generateRandomString(32);
            $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$reset_token, $reset_expires, $user['id']]);
            
            $success = 'Link reset password telah dikirim ke email Anda.';
            // Note: Dalam implementasi nyata, kirim email dengan link reset
        } else {
            $error = 'Email tidak ditemukan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= PANEL_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><radialGradient id="a"><stop offset="0" stop-color="%23fff" stop-opacity=".1"/><stop offset="1" stop-color="%23fff" stop-opacity="0"/></radialGradient></defs><circle cx="50" cy="50" r="50" fill="url(%23a)"/></svg>') repeat;
            opacity: 0.1;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateX(0px) translateY(0px); }
            50% { transform: translateX(-20px) translateY(-20px); }
            100% { transform: translateX(0px) translateY(0px); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 450px;
            max-width: 90vw;
            position: relative;
            z-index: 1;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"><animate attributeName="opacity" values="0.1;0.3;0.1" dur="3s" repeatCount="indefinite"/></circle><circle cx="80" cy="30" r="1.5" fill="white" opacity="0.1"><animate attributeName="opacity" values="0.1;0.4;0.1" dur="4s" repeatCount="indefinite"/></circle><circle cx="60" cy="70" r="2.5" fill="white" opacity="0.1"><animate attributeName="opacity" values="0.1;0.2;0.1" dur="5s" repeatCount="indefinite"/></circle></svg>') repeat;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
        }

        .login-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 16px;
            position: relative;
            z-index: 2;
        }

        .login-body {
            padding: 40px 30px;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 30px;
            border-radius: 12px;
            background: #f8f9fa;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 15px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #666;
            font-size: 14px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
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

        .form-group input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: linear-gradient(135deg, #efe, #dfd);
            color: #363;
            border: 1px solid #cfc;
        }

        .forgot-link {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .version-info {
            position: absolute;
            bottom: 20px;
            right: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1><?= PANEL_NAME ?></h1>
            <p>Panel Saldo VPN Premium</p>
        </div>
        
        <div class="login-body">
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
            
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <button class="tab-btn" onclick="showTab('register')">
                    <i class="fas fa-user-plus"></i> Daftar
                </button>
            </div>
            
            <!-- Login Tab -->
            <div id="login-tab" class="tab-content active">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username atau Email</label>
                        <input type="text" name="username" required placeholder="Masukkan username atau email">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" required placeholder="Masukkan password">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Masuk ke Panel
                    </button>
                </form>
                
                <div class="forgot-link">
                    <a href="javascript:void(0)" onclick="showTab('forgot')">
                        <i class="fas fa-key"></i> Lupa kata sandi?
                    </a>
                </div>
            </div>
            
            <!-- Register Tab -->
            <div id="register-tab" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="reg_username" required placeholder="Pilih username unik">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="reg_email" required placeholder="alamat@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Nama Lengkap</label>
                        <input type="text" name="reg_fullname" required placeholder="Nama lengkap Anda">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="reg_password" required placeholder="Minimal 6 karakter">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Konfirmasi Password</label>
                        <input type="password" name="reg_confirm_password" required placeholder="Ulangi password">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Buat Akun Baru
                    </button>
                </form>
            </div>
            
            <!-- Forgot Password Tab -->
            <div id="forgot-tab" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="action" value="forgot_password">
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Terdaftar</label>
                        <input type="email" name="forgot_email" required placeholder="alamat@email.com">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Kirim Link Reset
                    </button>
                </form>
                
                <div class="forgot-link">
                    <a href="javascript:void(0)" onclick="showTab('login')">
                        <i class="fas fa-arrow-left"></i> Kembali ke Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="version-info">
        AlrelShop Panel v1.0
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        // Auto-focus first input when tab changes
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = document.querySelector('.tab-content.active');
            const firstInput = activeTab.querySelector('input[type="text"], input[type="email"]');
            if (firstInput) firstInput.focus();
        });
    </script>
</body>
</html>

<?php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['customer_id'])) {
    header("Location: c_dashboard.php");
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    // DEBUG
    error_log("=== LOGIN ATTEMPT ===");
    error_log("Email: " . $email);
    
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        $query = "SELECT * FROM customer WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            die("Error query: " . mysqli_error($conn));
        }
        
        // DEBUG
        $num_rows = mysqli_num_rows($result);
        error_log("Rows found: " . $num_rows);
        
        if ($num_rows == 1) {
            $customer = mysqli_fetch_assoc($result);
            
            // DEBUG
            error_log("Customer found: " . $customer['nama_depan']);
            error_log("Password hash: " . substr($customer['password'], 0, 20));
            
            // Verifikasi password
            $password_match = password_verify($password, $customer['password']);
            error_log("Password verify result: " . ($password_match ? 'TRUE' : 'FALSE'));
            
            if ($password_match) {
                // Set session
                $_SESSION['customer_id'] = $customer['id_pelanggan'];
                $_SESSION['customer_nama'] = $customer['nama_depan'] . ' ' . $customer['nama_belakang'];
                
                // DEBUG
                error_log("Session set - ID: " . $_SESSION['customer_id']);
                error_log("Redirecting to c_dashboard.php");
                
                // Redirect ke dashboard
                header("Location: c_dashboard.php");
                exit();
            } else {
                $error = "Email atau password salah! (Password tidak cocok)";
            }
        } else {
            $error = "Email atau password salah! (Email tidak ditemukan)";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Ambil pesan sukses dari session (untuk registrasi)
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CatDogKu Pet Care</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #fcc;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .admin-login {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .admin-login a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .admin-login a:hover {
            text-decoration: underline;
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>CatDogKu</h1>
            <p>Pet Care Service</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="admin-login">
            <a href="a_login.php">Login Sebagai Admin</a>
        </div>
        
        <div class="register-link">
            Belum punya akun? <a href="c_daftar.php">Daftar disini</a>
        </div>
    </div>
</body>
</html>
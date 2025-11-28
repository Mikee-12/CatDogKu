<?php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email dan password harus diisi!";
        header("Location: login.php");
        exit();
    } else {
        $query = "SELECT * FROM admin WHERE email = '$email'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $admin = mysqli_fetch_assoc($result);
            
            if (verify_password($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id_admin'];
                $_SESSION['admin_nama'] = $admin['nama_admin'];
                header("Location: dashboard.php");
                exit();
            } else {
                $_SESSION['error'] = "Email atau password salah!";
                header("Location: login.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Email atau password salah!";
            header("Location: login.php");
            exit();
        }
    }
}

// Ambil pesan dari session
$error = '';
$success = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
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
    <title>Login Admin - CatDogKu</title>
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
            color: #e74c3c;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
            font-weight: 600;
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
            border-color: #e74c3c;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .success-message {
            background: #efe;
            color: #2a2;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-login:hover {
            background: #c0392b;
        }
        
        .customer-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .customer-login a {
            color: #e74c3c;
            text-decoration: underline;
            font-size: 14px;
        }
        
        .customer-login a:hover {
            color: #c0392b;
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
            color: #e74c3c;
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
            <p>ADMIN PANEL</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Admin</label>
                <input type="email" id="email" name="email" placeholder="Masukkan email admin" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="customer-login">
            <a href="../index.php">Login sebagai Customer</a>
        </div>
        
        <div class="register-link">
            Belum punya akun admin? <a href="a_daftar.php">Daftar disini</a>
        </div>
    </div>
</body>
</html>
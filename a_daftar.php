<?php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_admin = clean_input($_POST['nama_admin']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi input
    if (empty($nama_admin) || empty($email) || empty($password)) {
        $_SESSION['error'] = "Semua field wajib diisi!";
        header("Location: a_daftar.php");
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Password dan konfirmasi password tidak sama!";
        header("Location: a_daftar.php");
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter!";
        header("Location: a_daftar.php");
        exit();
    }
    
    // Cek email sudah terdaftar
    $check_email = "SELECT * FROM admin WHERE email = '$email'";
    $result = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "Email sudah terdaftar!";
        header("Location: a_daftar.php");
        exit();
    }
    
    // Hash password
    $hashed_password = hash_password($password);
    
    // Insert ke database
    $query = "INSERT INTO admin (nama_admin, email, password) 
              VALUES ('$nama_admin', '$email', '$hashed_password')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Registrasi admin berhasil! Silakan login.";
        header("Location: a_login.php");
        exit();
    } else {
        $_SESSION['error'] = "Terjadi kesalahan. Silakan coba lagi.";
        header("Location: a_daftar.php");
        exit();
    }
}

// Ambil pesan error dari session
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Admin - CatDogKu</title>
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
        
        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
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
        
        .btn-register {
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
        
        .btn-register:hover {
            background: #c0392b;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>CatDogKu</h1>
            <p>DAFTAR ADMIN BARU</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nama_admin">Nama Lengkap</label>
                <input type="text" id="nama_admin" name="nama_admin" placeholder="Masukkan nama lengkap" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Admin</label>
                <input type="email" id="email" name="email" placeholder="Masukkan email admin" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
            </div>
            
            <button type="submit" class="btn-register">Daftar Admin</button>
        </form>
        
        <div class="login-link">
            Sudah punya akun admin? <a href="a_login.php">Login disini</a>
        </div>
    </div>
</body>
</html>
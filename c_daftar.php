<?php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['customer_id'])) {
    header("Location: customer/dashboard.php");
    exit();
}

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_depan = clean_input($_POST['nama_depan']);
    $nama_belakang = clean_input($_POST['nama_belakang']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $no_telepon = clean_input($_POST['no_telepon']);
    $alamat = clean_input($_POST['alamat']);
    
    // Validasi input
    if (empty($nama_depan) || empty($nama_belakang) || empty($email) || empty($password)) {
        $_SESSION['error'] = "Semua field wajib diisi!";
        header("Location: c_daftar.php");
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Password dan konfirmasi password tidak sama!";
        header("Location: c_daftar.php");
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter!";
        header("Location: c_daftar.php");
        exit();
    }
    
    // Cek email sudah terdaftar
    $check_email = "SELECT * FROM customer WHERE email = '$email'";
    $result = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "Email sudah terdaftar!";
        header("Location: c_daftar.php");
        exit();
    }
    
    // Hash password
    $hashed_password = hash_password($password);
    
    // Insert ke database
    $query = "INSERT INTO customer (nama_depan, nama_belakang, email, password, no_telepon, alamat) 
              VALUES ('$nama_depan', '$nama_belakang', '$email', '$hashed_password', '$no_telepon', '$alamat')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Terjadi kesalahan. Silakan coba lagi.";
        header("Location: c_daftar.php");
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
    <title>Daftar - CatDogKu Pet Care</title>
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
            max-width: 500px;
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
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-register:hover {
            background: #5568d3;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
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
            <p>Daftar Akun Baru</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="nama_depan">Nama Depan</label>
                    <input type="text" id="nama_depan" name="nama_depan" placeholder="Nama depan" required>
                </div>
                
                <div class="form-group">
                    <label for="nama_belakang">Nama Belakang</label>
                    <input type="text" id="nama_belakang" name="nama_belakang" placeholder="Nama belakang" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required>
            </div>
            
            <div class="form-group">
                <label for="no_telepon">No Telepon</label>
                <input 
    type="tel" 
    id="no_telepon" 
    name="no_telepon" 
    placeholder="08xxxxxxxxxx"
    maxlength="13"
    oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
>

            </div>
            
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" placeholder="Masukkan alamat lengkap"></textarea>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
            </div>
            
            <button type="submit" class="btn-register">Daftar</button>
        </form>
        
        <div class="login-link">
            Sudah punya akun? <a href="index.php">Login disini</a>
        </div>
    </div>
</body>
</html>
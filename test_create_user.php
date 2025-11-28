<?php
require_once 'config.php';

echo "<h2>Test Database & Create User</h2>";

// Test koneksi
if ($conn) {
    echo "✅ Koneksi database OK<br><br>";
} else {
    die("❌ Koneksi database GAGAL");
}

// Hapus user test lama jika ada
mysqli_query($conn, "DELETE FROM customer WHERE email = 'test@test.com'");

// Buat password hash
$password = "123456";
$hashed = password_hash($password, PASSWORD_DEFAULT);

echo "<strong>Password Test:</strong> $password<br>";
echo "<strong>Password Hash:</strong> $hashed<br><br>";

// Insert user test
$query = "INSERT INTO customer (nama_depan, nama_belakang, email, password, no_telepon, alamat) 
          VALUES ('Test', 'User', 'test@test.com', '$hashed', '081234567890', 'Jl. Test No. 1')";

if (mysqli_query($conn, $query)) {
    echo "✅ User test berhasil dibuat!<br><br>";
    
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>LOGIN CREDENTIALS:</h3>";
    echo "<strong>Email:</strong> test@test.com<br>";
    echo "<strong>Password:</strong> 123456<br>";
    echo "</div>";
    
    // Test baca user
    $check = mysqli_query($conn, "SELECT * FROM customer WHERE email = 'test@test.com'");
    if (mysqli_num_rows($check) > 0) {
        $user = mysqli_fetch_assoc($check);
        echo "✅ User berhasil dibaca dari database<br>";
        echo "ID: " . $user['id_pelanggan'] . "<br>";
        echo "Nama: " . $user['nama_depan'] . " " . $user['nama_belakang'] . "<br><br>";
        
        // Test verify password
        if (password_verify($password, $user['password'])) {
            echo "✅ Password verification OK<br><br>";
        } else {
            echo "❌ Password verification GAGAL<br><br>";
        }
    }
    
    echo "<a href='test_login.php' style='display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>COBA LOGIN SEKARANG</a>";
    
} else {
    echo "❌ Error: " . mysqli_error($conn);
}
?>
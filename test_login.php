<?php
require_once 'config.php';

echo "<h2>Debug Login Process</h2>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    echo "<strong>Email Input:</strong> $email<br>";
    echo "<strong>Password Input:</strong> $password<br><br>";
    
    $query = "SELECT * FROM customer WHERE email = '$email'";
    echo "<strong>Query:</strong> $query<br><br>";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "❌ Query Error: " . mysqli_error($conn) . "<br>";
    } else {
        echo "✅ Query berhasil<br>";
        echo "Jumlah rows: " . mysqli_num_rows($result) . "<br><br>";
        
        if (mysqli_num_rows($result) == 1) {
            $customer = mysqli_fetch_assoc($result);
            
            echo "✅ User ditemukan:<br>";
            echo "ID: " . $customer['id_pelanggan'] . "<br>";
            echo "Nama: " . $customer['nama_depan'] . " " . $customer['nama_belakang'] . "<br>";
            echo "Email: " . $customer['email'] . "<br>";
            echo "Password Hash: " . substr($customer['password'], 0, 30) . "...<br><br>";
            
            if (password_verify($password, $customer['password'])) {
                echo "✅ Password COCOK!<br><br>";
                
                $_SESSION['customer_id'] = $customer['id_pelanggan'];
                $_SESSION['customer_nama'] = $customer['nama_depan'] . ' ' . $customer['nama_belakang'];
                
                echo "✅ Session berhasil dibuat:<br>";
                echo "customer_id: " . $_SESSION['customer_id'] . "<br>";
                echo "customer_nama: " . $_SESSION['customer_nama'] . "<br><br>";
                
                echo "<a href='c_dashboard.php' style='display: inline-block; background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>BUKA DASHBOARD</a>";
                
            } else {
                echo "❌ Password TIDAK COCOK<br>";
                echo "Kemungkinan password hash salah<br>";
            }
        } else {
            echo "❌ User tidak ditemukan dengan email: $email<br>";
        }
    }
    
} else {
    ?>
    <form method="POST">
        <div style="margin: 20px 0;">
            <label>Email:</label><br>
            <input type="email" name="email" value="test@test.com" style="padding: 8px; width: 300px;">
        </div>
        <div style="margin: 20px 0;">
            <label>Password:</label><br>
            <input type="password" name="password" value="123456" style="padding: 8px; width: 300px;">
        </div>
        <button type="submit" style="background: #667eea; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer;">
            TEST LOGIN
        </button>
    </form>
    <?php
}
?>
<?php
// Start session jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database untuk Laragon
$host = "127.0.0.1:3307";
$user = "root";
$pass = "";
$db   = "catdogku";

// Membuat koneksi
$conn = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset ke utf8
mysqli_set_charset($conn, "utf8");

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Fungsi untuk hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Fungsi untuk verifikasi password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Blok session_start() sudah dipindah ke atas
?>
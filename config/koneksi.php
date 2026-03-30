<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "catdogku_petcare";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// optional: set charset
mysqli_set_charset($conn, "utf8");

?>
<?php
require_once 'config/koneksi.php';

// Update status from pending to in_progress when waktu_mulai is reached
$sql1 = "
    UPDATE reservations
    SET status = 'in_progress'
    WHERE status = 'pending'
      AND waktu_mulai <= NOW()
";

// Update status from in_progress to completed when waktu_selesai is passed
$sql2 = "
    UPDATE reservations
    SET status = 'completed'
    WHERE status = 'in_progress'
      AND waktu_selesai < NOW()
";

$conn->query($sql1);
$conn->query($sql2);

// Optional: Log the updates
echo "Reservation statuses updated at " . date('Y-m-d H:i:s') . "\n";
?>
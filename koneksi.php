<?php
require_once __DIR__ . "/config.php";

$host = "localhost";
$dbname = "polindra_suruh";
$dbUser = "root";
$dbPass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Auto-migration aman untuk memastikan kolom PRD 3.0 tersedia di tabel pesanan
    $existing = $pdo->query("SHOW COLUMNS FROM pesanan")->fetchAll(PDO::FETCH_COLUMN);
    $alter = [];
    if (!in_array('origin_location', $existing)) {
        $alter[] = "ADD COLUMN `origin_location` TEXT NULL AFTER `alamat`";
    }
    if (!in_array('origin_lat', $existing)) {
        $alter[] = "ADD COLUMN `origin_lat` DECIMAL(10,8) NULL";
    }
    if (!in_array('origin_lng', $existing)) {
        $alter[] = "ADD COLUMN `origin_lng` DECIMAL(11,8) NULL";
    }
    if (!in_array('destination_location', $existing)) {
        $alter[] = "ADD COLUMN `destination_location` TEXT NULL";
    }
    if (!in_array('destination_lat', $existing)) {
        $alter[] = "ADD COLUMN `destination_lat` DECIMAL(10,8) NULL";
    }
    if (!in_array('destination_lng', $existing)) {
        $alter[] = "ADD COLUMN `destination_lng` DECIMAL(11,8) NULL";
    }
    if (!in_array('distance_km', $existing)) {
        $alter[] = "ADD COLUMN `distance_km` DECIMAL(5,2) DEFAULT '0.00'";
    }
    if (!in_array('total_harga', $existing)) {
        $alter[] = "ADD COLUMN `total_harga` DECIMAL(10,2) DEFAULT '0.00'";
    }
    if (!in_array('snap_token', $existing)) {
        $alter[] = "ADD COLUMN `snap_token` VARCHAR(255) NULL";
    }
    if (!in_array('payment_status', $existing)) {
        $alter[] = "ADD COLUMN `payment_status` ENUM('pending', 'paid', 'failed', 'cod') NOT NULL DEFAULT 'pending'";
    }
    if (!empty($alter)) {
        $pdo->exec("ALTER TABLE `pesanan` " . implode(", ", $alter));
    }
} catch (PDOException $e) {
    // Jika koneksi gagal saat runtime
    // Catat pesan error di log sistem jika dibutuhkan
    if (!isset($pdo)) {
        exit("Koneksi database gagal.");
    }
} catch (Throwable $t) {
    // Toleransi jika auto-migration sudah berjalan
}

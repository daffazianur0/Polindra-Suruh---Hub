<?php
/**
 * Update Payment Status Endpoint (Client-Side Synchronization)
 * Dipanggil saat pop-up Snap JS onSuccess menyelesaikan pembayaran QRIS/E-Wallet
 */
session_start();
require_once __DIR__ . "/koneksi.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$pesananId = (int) ($data["pesanan_id"] ?? 0);
$userId = (int) $_SESSION["user_id"];
$newPaymentStatus = $data["payment_status"] ?? "paid";

if ($pesananId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID Pesanan tidak valid"]);
    exit;
}

try {
    $stmtCheck = $pdo->prepare("SELECT id, driver_id, status FROM pesanan WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$pesananId, $userId]);
    $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Pesanan tidak ditemukan"]);
        exit;
    }

    $newOrderStatus = !empty($order["driver_id"]) ? "Menunggu Persetujuan Driver" : "Menunggu Driver";

    $stmtUpdate = $pdo->prepare("
        UPDATE pesanan
        SET payment_status = :payment_status,
            status = :status,
            waktu_bayar = NOW()
        WHERE id = :id AND user_id = :user_id
    ");

    $stmtUpdate->execute([
        "payment_status" => $newPaymentStatus === "paid" ? "paid" : "pending",
        "status" => $newOrderStatus,
        "id" => $pesananId,
        "user_id" => $userId
    ]);

    echo json_encode([
        "success" => true,
        "pesanan_id" => $pesananId,
        "payment_status" => $newPaymentStatus,
        "status" => $newOrderStatus,
        "message" => "Status pembayaran dan pesanan berhasil diperbarui."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}


<?php
/**
 * Midtrans Webhook Notification Handler
 * Menerima callback HTTP POST dari Midtrans setelah pelanggan menyelesaikan transaksi
 */

require_once __DIR__ . "/koneksi.php";
require_once __DIR__ . "/includes/Midtrans.php";

header('Content-Type: application/json');

// 1. Ambil payload HTTP POST dari Midtrans
$rawInput = file_get_contents("php://input");
$notification = json_decode($rawInput, true);

if (!$notification) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Payload JSON tidak valid"]);
    exit;
}

// 2. Ekstraksi data callback
$orderId = $notification["order_id"] ?? "";
$statusCode = $notification["status_code"] ?? "";
$grossAmount = $notification["gross_amount"] ?? "";
$inputSignature = $notification["signature_key"] ?? "";
$transactionStatus = $notification["transaction_status"] ?? "";
$fraudStatus = $notification["fraud_status"] ?? "";
$paymentType = $notification["payment_type"] ?? "";

// 3. Verifikasi Signature SHA512
// Formula: SHA512(order_id + status_code + gross_amount + ServerKey)
$isSignatureValid = Midtrans::verifySignature((string) $orderId, (string) $statusCode, (string) $grossAmount, (string) $inputSignature);

if (!$isSignatureValid) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Verifikasi signature Midtrans gagal"]);
    exit;
}

// 4. Ekstraksi ID Pesanan Asli dari order_id (Format: PS-{pesananId}-{timestamp} atau angka biasa)
$pesananId = 0;
if (preg_match('/^PS-(\d+)/', $orderId, $matches)) {
    $pesananId = (int) $matches[1];
} else {
    $pesananId = (int) $orderId;
}

if ($pesananId <= 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "ID pesanan tidak ditemukan"]);
    exit;
}

// 5. Cek apakah pesanan ada di database
$stmtCheck = $pdo->prepare("SELECT id, driver_id, status, payment_status FROM pesanan WHERE id = ?");
$stmtCheck->execute([$pesananId]);
$pesanan = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$pesanan) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Pesanan #" . $pesananId . " tidak ditemukan di sistem"]);
    exit;
}

// 6. Penentuan Status Pembayaran & Status Pesanan
$newPaymentStatus = "pending";
$newOrderStatus = $pesanan["status"];

if ($transactionStatus === "settlement") {
    // Pembayaran sukses (QRIS, GoPay, VA, dll)
    $newPaymentStatus = "paid";
    // Jika sebelumnya menunggu bayar, majukan status
    if (in_array($pesanan["status"], ["Menunggu Pembayaran", "Menunggu Driver", "Menunggu"])) {
        $newOrderStatus = (!empty($pesanan["driver_id"])) ? "Menunggu Persetujuan Driver" : "Menunggu Driver";
    }
} elseif ($transactionStatus === "capture") {
    // Kartu kredit / debit
    if ($fraudStatus === "accept") {
        $newPaymentStatus = "paid";
        if (in_array($pesanan["status"], ["Menunggu Pembayaran", "Menunggu Driver", "Menunggu"])) {
            $newOrderStatus = (!empty($pesanan["driver_id"])) ? "Menunggu Persetujuan Driver" : "Menunggu Driver";
        }
    } else {
        $newPaymentStatus = "pending";
    }
} elseif (in_array($transactionStatus, ["deny", "expire", "cancel", "failure"])) {
    $newPaymentStatus = "failed";
} elseif ($transactionStatus === "pending") {
    $newPaymentStatus = "pending";
}

// 7. Update status pesanan di database
try {
    $stmtUpdate = $pdo->prepare("
        UPDATE pesanan
        SET payment_status = :payment_status,
            status = :status,
            waktu_bayar = CASE WHEN :payment_status = 'paid' THEN NOW() ELSE waktu_bayar END
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        "payment_status" => $newPaymentStatus,
        "status" => $newOrderStatus,
        "id" => $pesananId
    ]);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Notifikasi berhasil diproses",
        "pesanan_id" => $pesananId,
        "payment_status" => $newPaymentStatus
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal memperbarui status di database: " . $e->getMessage()]);
}


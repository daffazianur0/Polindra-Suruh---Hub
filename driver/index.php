<?php
session_start();

require_once '../includes/auth.php';

requireLogin();
requireRole('driver');

// Pastikan yang login adalah driver
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "driver"
) {
    header("Location: ../login.php");
    exit;
}

$driverId = (int) $_SESSION["user_id"];
$namaDriver = $_SESSION["nama"] ?? "Driver";
$notif = $_GET["notif"] ?? "";

// Proses perubahan status Aktif / Off
if (
    ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
    isset($_POST["ubah_status"])
) {
    $statusBaru = (int) ($_POST["status_aktif"] ?? -1);

    if ($statusBaru === 0 || $statusBaru === 1) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET status_aktif = :status
             WHERE id = :id AND role = 'driver'"
        );
        $stmt->execute([
            "status" => $statusBaru,
            "id" => $driverId
        ]);

        $stmtDriver = $pdo->prepare(
            "UPDATE drivers
             SET status_aktif = :status
             WHERE user_id = :id"
        );
        $stmtDriver->execute([
            "status" => $statusBaru,
            "id" => $driverId
        ]);
    }

    header("Location: index.php?notif=status_diubah");
    exit;
}

// Proses update No. WhatsApp Driver
if (
    ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
    isset($_POST["update_whatsapp"])
) {
    $noHpBaru = trim($_POST["no_hp"] ?? "");

    $stmtCheck = $pdo->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $stmtCheck->execute([$driverId]);
    if ($stmtCheck->fetch()) {
        $stmtUpdate = $pdo->prepare("UPDATE drivers SET no_hp = ? WHERE user_id = ?");
        $stmtUpdate->execute([$noHpBaru, $driverId]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO drivers (user_id, nama_driver, no_hp, status_aktif) VALUES (?, ?, ?, 1)");
        $stmtInsert->execute([$driverId, $namaDriver, $noHpBaru]);
    }

    header("Location: index.php?notif=wa_tersimpan");
    exit;
}

// Proses Upload / Perbarui Foto QRIS Driver
if (
    ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
    isset($_POST["upload_qris"])
) {
    if (isset($_FILES["foto_qris"]) && $_FILES["foto_qris"]["error"] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES["foto_qris"]["tmp_name"];
        $fileName = $_FILES["foto_qris"]["name"];
        $fileSize = $_FILES["foto_qris"]["size"];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ["jpg", "jpeg", "png", "webp"];

        if (!in_array($ext, $allowedExtensions)) {
            header("Location: index.php?notif=qris_format_salah");
            exit;
        }

        if ($fileSize > 3 * 1024 * 1024) { // Max 3MB
            header("Location: index.php?notif=qris_terlalu_besar");
            exit;
        }

        // Cek MIME type
        $mime = "";
        if (function_exists("finfo_open")) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fileTmp);
            finfo_close($finfo);
        } elseif (function_exists("mime_content_type")) {
            $mime = mime_content_type($fileTmp);
        }

        $allowedMimes = ["image/jpeg", "image/png", "image/webp", "image/pjpeg"];
        if ($mime && !in_array($mime, $allowedMimes)) {
            header("Location: index.php?notif=qris_format_salah");
            exit;
        }

        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "qris" . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = "qris_driver_" . $driverId . "_" . time() . "." . $ext;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $destPath)) {
            // Hapus file lama jika ada
            $stmtOld = $pdo->prepare("SELECT foto_qris FROM drivers WHERE user_id = ?");
            $stmtOld->execute([$driverId]);
            $oldQris = $stmtOld->fetchColumn();
            if ($oldQris && file_exists($uploadDir . $oldQris)) {
                @unlink($uploadDir . $oldQris);
            }

            // Simpan / update di tabel drivers
            $stmtCheck = $pdo->prepare("SELECT id FROM drivers WHERE user_id = ?");
            $stmtCheck->execute([$driverId]);
            if ($stmtCheck->fetch()) {
                $stmtUpdate = $pdo->prepare("UPDATE drivers SET foto_qris = ? WHERE user_id = ?");
                $stmtUpdate->execute([$newFileName, $driverId]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO drivers (user_id, nama_driver, foto_qris, status_aktif) VALUES (?, ?, ?, 1)");
                $stmtInsert->execute([$driverId, $namaDriver, $newFileName]);
            }

            header("Location: index.php?notif=qris_tersimpan");
            exit;
        } else {
            header("Location: index.php?notif=qris_gagal_upload");
            exit;
        }
    } else {
        header("Location: index.php?notif=qris_pilih_file");
        exit;
    }
}

// Proses Hapus Foto QRIS Driver
if (
    ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
    isset($_POST["hapus_qris"])
) {
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "qris" . DIRECTORY_SEPARATOR;
    $stmtOld = $pdo->prepare("SELECT foto_qris FROM drivers WHERE user_id = ?");
    $stmtOld->execute([$driverId]);
    $oldQris = $stmtOld->fetchColumn();
    if ($oldQris && file_exists($uploadDir . $oldQris)) {
        @unlink($uploadDir . $oldQris);
    }

    $stmtUpdate = $pdo->prepare("UPDATE drivers SET foto_qris = NULL WHERE user_id = ?");
    $stmtUpdate->execute([$driverId]);

    header("Location: index.php?notif=qris_dihapus");
    exit;
}


// Proses Aksi Pesanan: Terima / Tolak / Antar / Selesai
if (
    ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
    isset($_POST["aksi_pesanan"])
) {
    $pesananId = (int) ($_POST["pesanan_id"] ?? 0);
    $aksi = $_POST["aksi_pesanan"];

    if ($aksi === "terima") {
        // Cek metode bayar pesanan ini
        $stmtCekMetode = $pdo->prepare("SELECT metode_bayar, payment_status FROM pesanan WHERE id = :id AND driver_id = :driver_id");
        $stmtCekMetode->execute(["id" => $pesananId, "driver_id" => $driverId]);
        $rowMetode = $stmtCekMetode->fetch(PDO::FETCH_ASSOC);
        // Pesanan yang sudah dibayar via Midtrans atau COD langsung beralih ke status Diproses
        $targetStatus = "Diproses";

        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET status = :status
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute(["status" => $targetStatus, "id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=diterima");
        exit;
    } elseif ($aksi === "ambil_pool") {
        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET driver_id = :driver_id, status = 'Diproses'
            WHERE id = :id AND driver_id IS NULL AND (payment_status = 'paid' OR payment_status = 'cod')
        ");
        $stmt->execute(["id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=order_diambil");
        exit;
    } elseif ($aksi === "konfirmasi_qris") {
        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET qris_diverifikasi = 1,
                alasan_tolak_bayar = NULL,
                status = CASE WHEN status = 'Menunggu Pembayaran' THEN 'Diproses' ELSE status END
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute(["id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=qris_diverifikasi");
        exit;
    } elseif ($aksi === "tolak_qris") {
        $alasanPilihan = trim($_POST["alasan_pilihan"] ?? "");
        $alasanKustom = trim($_POST["alasan_kustom"] ?? "");
        
        $alasanTolak = $alasanPilihan;
        if ($alasanKustom !== "") {
            $alasanTolak = ($alasanPilihan !== "" && $alasanPilihan !== "Lainnya") 
                ? $alasanPilihan . " (" . $alasanKustom . ")" 
                : $alasanKustom;
        }
        if ($alasanTolak === "") {
            $alasanTolak = "Bukti pembayaran tidak sesuai atau dana belum masuk.";
        }

        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET qris_diverifikasi = 2,
                alasan_tolak_bayar = :alasan,
                status = 'Menunggu Pembayaran'
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute([
            "alasan" => $alasanTolak,
            "id" => $pesananId,
            "driver_id" => $driverId
        ]);
        header("Location: index.php?notif=qris_ditolak");
        exit;
    } elseif ($aksi === "tolak") {
        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET driver_id = NULL, status = 'Menunggu Driver'
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute(["id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=ditolak");
        exit;
    } elseif ($aksi === "antar") {
        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET status = 'Dalam Perjalanan'
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute(["id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=antar");
        exit;
    } elseif ($aksi === "selesai") {
        $stmt = $pdo->prepare("
            UPDATE pesanan
            SET status = 'Selesai'
            WHERE id = :id AND driver_id = :driver_id
        ");
        $stmt->execute(["id" => $pesananId, "driver_id" => $driverId]);
        header("Location: index.php?notif=selesai");
        exit;
    }
}

// Ambil status terbaru dan data driver
$stmt = $pdo->prepare(
    "SELECT u.status_aktif, d.no_hp, d.foto_qris
     FROM users u
     LEFT JOIN drivers d ON d.user_id = u.id
     WHERE u.id = :id AND u.role = 'driver'"
);
$stmt->execute(["id" => $driverId]);
$dataDriver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dataDriver) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$statusAktif = (int) $dataDriver["status_aktif"];
$noHpDriver = $dataDriver["no_hp"] ?? "";
$fotoQrisDriver = $dataDriver["foto_qris"] ?? null;

// Ambil semua pesanan yang ditujukan kepada driver ini (Hanya yang lunas via Midtrans atau COD)
$stmt = $pdo->prepare(
    "SELECT p.*, u.nama AS nama_pelanggan, u.email AS email_pelanggan
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     WHERE p.driver_id = :driver_id
       AND (p.payment_status = 'paid' OR p.payment_status = 'cod')
     ORDER BY p.id DESC"
);
$stmt->execute(["driver_id" => $driverId]);
$pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Driver Order Pool (Pesanan terbuka tanpa driver yang sudah lunas atau COD)
$stmtPool = $pdo->prepare(
    "SELECT p.*, u.nama AS nama_pelanggan, u.email AS email_pelanggan
     FROM pesanan p
     JOIN users u ON p.user_id = u.id
     WHERE p.driver_id IS NULL
       AND p.status IN ('Menunggu', 'Menunggu Driver')
       AND (p.payment_status = 'paid' OR p.payment_status = 'cod')
     ORDER BY p.id DESC"
);
$stmtPool->execute();
$pesananPool = $stmtPool->fetchAll(PDO::FETCH_ASSOC);

// Pisahkan pesanan berdasarkan tahapan
$pesananBaru = [];
$pesananBerjalan = [];
$pesananSelesai = [];

foreach ($pesanan as $p) {
    $st = $p["status"];
    if (in_array($st, ["Menunggu Driver", "Menunggu Persetujuan Driver", "Menunggu"])) {
        $pesananBaru[] = $p;
    } elseif (in_array($st, ["Menunggu Pembayaran", "Diproses", "Dalam Perjalanan"])) {
        $pesananBerjalan[] = $p;
    } else {
        $pesananSelesai[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Driver - Polindra Suruh</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #ffd21f, #f7b500);
            min-height: 100vh;
            padding: 25px 20px;
            color: #1e293b;
        }

        .container {
            max-width: 1050px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: clamp(24px, 4vw, 32px);
            color: #0f172a;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logout {
            text-decoration: none;
            background: #dc2626;
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
            transition: background 0.2s;
        }

        .logout:hover {
            background: #b91c1c;
        }

        /* Notifikasi */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .card h2 {
            margin-bottom: 16px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .aktif {
            color: #166534;
            background: #dcfce7;
        }

        .off {
            color: #991b1b;
            background: #fee2e2;
        }

        button {
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: opacity 0.2s;
        }

        button:hover {
            opacity: 0.9;
        }

        .btn-aktif {
            color: white;
            background: #16a34a;
        }

        .btn-off {
            color: white;
            background: #dc2626;
        }

        /* Form WhatsApp */
        .wa-form-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .wa-form-row input {
            padding: 11px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            flex: 1;
            min-width: 200px;
            max-width: 350px;
        }

        .btn-wa-save {
            background: #25D366;
            color: white;
        }

        /* Form & Preview QRIS Driver */
        .qris-driver-card {
            border-left: 6px solid #10b981 !important;
        }

        .qris-driver-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 22px;
            align-items: center;
            margin-top: 15px;
        }

        .qris-preview-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 14px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }

        .qris-preview-card.has-qris {
            border: 2px solid #10b981;
            background: #f0fdf4;
        }

        .qris-preview-card img {
            max-width: 170px;
            max-height: 170px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: #fff;
            padding: 4px;
        }

        .qris-badge-status {
            margin-top: 8px;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .qris-badge-active {
            background: #dcfce7;
            color: #15803d;
        }

        .qris-badge-empty {
            background: #fef3c7;
            color: #92400e;
        }

        .qris-form-box {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .file-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .file-input-custom {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            background: #fff;
            flex: 1;
            min-width: 200px;
        }

        .btn-qris-upload {
            background: #10b981;
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-qris-upload:hover {
            background: #059669;
        }

        .btn-qris-delete {
            background: #ef4444;
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-qris-delete:hover {
            background: #dc2626;
        }

        @media (max-width: 650px) {
            .qris-driver-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Pesanan Cards */
        .pesanan-list {
            display: grid;
            gap: 16px;
        }

        .pesanan-item {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            background: white;
            transition: box-shadow 0.2s;
        }

        .pesanan-item:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }

        .pesanan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .pesanan-item h3 {
            color: #0f172a;
            font-size: 17px;
        }

        .pesanan-item p {
            margin: 6px 0;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            overflow-wrap: anywhere;
        }

        .catatan-highlight {
            background: #f8fafc;
            border-left: 4px solid #f7b500;
            padding: 10px 14px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
        }

        .status-pesanan {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .st-baru {
            background: #fef3c7;
            color: #92400e;
        }

        .st-bayar {
            background: #dbeafe;
            color: #1e40af;
        }

        .st-proses {
            background: #ede9fe;
            color: #6b21a8;
        }

        .st-antar {
            background: #fef08a;
            color: #854d0e;
        }

        .st-selesai {
            background: #dcfce7;
            color: #15803d;
        }

        .action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
        }

        .btn-terima {
            background: #16a34a;
            color: white;
        }

        .btn-tolak {
            background: #dc2626;
            color: white;
        }

        .btn-antar {
            background: #2563eb;
            color: white;
        }

        .btn-selesai {
            background: #059669;
            color: white;
        }

        .kosong {
            color: #64748b;
            padding: 12px 0;
            font-size: 14px;
        }

        /* Kotak Konfirmasi QRIS di Halaman Driver */
        .driver-qris-box {
            margin: 14px 0;
            padding: 16px;
            border-radius: 14px;
            transition: all 0.25s ease;
        }

        .qris-box-verified {
            background: linear-gradient(145deg, #f0fdf4, #ecfdf5);
            border: 1.5px solid #86efac;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.08);
        }

        .qris-box-pending {
            background: linear-gradient(145deg, #fffbeb, #fef3c7);
            border: 1.5px solid #fcd34d;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.08);
        }

        .driver-qris-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .driver-qris-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .driver-qris-title .qris-icon {
            font-size: 24px;
        }

        .driver-qris-title strong {
            font-size: 14px;
            color: #0f172a;
            display: block;
        }

        .driver-qris-subtitle {
            margin-top: 3px;
        }

        .badge-verified {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .badge-claimed {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .badge-waiting {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .verified-pill {
            background: #16a34a;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
        }

        .unverified-pill {
            background: #f59e0b;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
        }

        .driver-qris-body {
            margin-top: 12px;
        }

        .bukti-preview-container {
            display: flex;
            align-items: center;
            gap: 14px;
            background: white;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .bukti-thumb {
            width: 75px;
            height: 75px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .bukti-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .bukti-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .bukti-thumb .bukti-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .bukti-thumb:hover .bukti-overlay {
            opacity: 1;
        }

        .bukti-info {
            flex: 1;
        }

        .bukti-label {
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .bukti-hint {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .btn-lihat-bukti {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-lihat-bukti:hover {
            background: #e2e8f0;
        }

        .btn-lihat-bukti-mini {
            background: white;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-lihat-bukti-mini:hover {
            background: #f1f5f9;
        }

        .bukti-empty-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: white;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
        }

        .driver-qris-actions {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .btn-konfirmasi-qris-driver {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
            transition: background 0.2s, transform 0.2s;
        }

        .btn-konfirmasi-qris-driver:hover {
            background: linear-gradient(135deg, #15803d, #166534);
            transform: translateY(-1px);
        }

        .driver-qris-verified-msg {
            margin-top: 12px;
            padding: 10px 14px;
            background: #dcfce7;
            border-radius: 10px;
            border: 1px solid #bbf7d0;
            color: #166534;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .driver-decision-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width: 600px) {
            .driver-decision-grid {
                grid-template-columns: 1fr;
            }
        }

        .btn-tolak-qris-driver {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            transition: background 0.2s, transform 0.2s;
        }

        .btn-tolak-qris-driver:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        .tolak-form-box {
            display: none;
            background: #fff5f5;
            border: 1.5px solid #fecaca;
            border-radius: 12px;
            padding: 14px;
            margin-top: 12px;
        }

        .tolak-form-box.active {
            display: block;
        }

        .tolak-form-title {
            font-size: 13px;
            font-weight: bold;
            color: #991b1b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tolak-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 8px;
            background: white;
            color: #1e293b;
        }

        .tolak-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
            box-sizing: border-box;
            color: #1e293b;
        }

        .tolak-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-batal-tolak {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit-tolak {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .badge-rejected {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* Modal Lightbox Bukti Transfer */
        .bukti-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .bukti-modal-backdrop.active {
            display: flex;
            opacity: 1;
        }

        .bukti-modal-content {
            background: white;
            border-radius: 18px;
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            0% { transform: scale(0.92); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .bukti-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bukti-modal-header h3 {
            font-size: 15px;
            color: #0f172a;
            margin: 0;
        }

        .bukti-modal-close {
            background: transparent;
            border: none;
            font-size: 22px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .bukti-modal-close:hover {
            color: #0f172a;
            background: #f1f5f9;
        }

        .bukti-modal-body {
            padding: 16px;
            text-align: center;
            background: #f8fafc;
            max-height: 65vh;
            overflow: auto;
        }

        .bukti-modal-body img {
            max-width: 100%;
            max-height: 60vh;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            object-fit: contain;
        }

        .bukti-modal-footer {
            padding: 12px 18px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: white;
        }

        .btn-download-bukti {
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-close-modal {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            .card {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Dashboard Driver</h1>
            <p>Halo, <strong><?= htmlspecialchars($namaDriver) ?></strong>! Siap terima suruhan hari ini?</p>
        </div>

        <div class="header-actions">
            <a class="logout" href="../logout.php">Logout</a>
        </div>
    </div>

    <!-- NOTIFIKASI FEEDBACK -->
    <?php if ($notif === "diterima"): ?>
        <div class="alert alert-success">
            ✅ <strong>Pesanan Diterima!</strong> Status pesanan diubah ke "Menunggu Pembayaran". Pelanggan akan diarahkan untuk membayar via QRIS.
        </div>
    <?php elseif ($notif === "ditolak"): ?>
        <div class="alert alert-warning">
            ❌ <strong>Pesanan Ditolak!</strong> Pesanan telah dikembalikan agar dapat dipilih oleh driver lain.
        </div>
    <?php elseif ($notif === "antar"): ?>
        <div class="alert alert-info">
            🛵 <strong>Status Diperbarui!</strong> Pesanan sekarang berstatus "Dalam Perjalanan".
        </div>
    <?php elseif ($notif === "selesai"): ?>
        <div class="alert alert-success">
            🎉 <strong>Pesanan Berhasil Diselesaikan!</strong> Terima kasih atas kerja kerasmu.
        </div>
    <?php elseif ($notif === "wa_tersimpan"): ?>
        <div class="alert alert-success">
            📱 <strong>Nomor WhatsApp Berhasil Disimpan!</strong> Pelanggan dapat langsung menghubungi nomor ini.
        </div>
    <?php elseif ($notif === "status_diubah"): ?>
        <div class="alert alert-info">
            ℹ️ Status ketersediaan driver berhasil diubah.
        </div>
    <?php elseif ($notif === "qris_tersimpan"): ?>
        <div class="alert alert-success">
            💳 <strong>Foto QRIS Berhasil Disimpan!</strong> Pelanggan yang pesanannya kamu terima akan langsung melakukan scan ke kode QRIS pribadimu.
        </div>
    <?php elseif ($notif === "qris_dihapus"): ?>
        <div class="alert alert-info">
            🗑️ <strong>Foto QRIS Berhasil Dihapus!</strong> Pelanggan akan melihat QRIS default sementara waktu.
        </div>
    <?php elseif ($notif === "qris_format_salah"): ?>
        <div class="alert alert-warning">
            ⚠️ <strong>Format Gambar Tidak Valid!</strong> Harap unggah file foto dengan format JPG, JPEG, PNG, atau WEBP.
        </div>
    <?php elseif ($notif === "qris_terlalu_besar"): ?>
        <div class="alert alert-warning">
            ⚠️ <strong>Ukuran File Terlalu Besar!</strong> Maksimal ukuran file foto QRIS adalah 3 MB.
        </div>
    <?php elseif ($notif === "qris_gagal_upload"): ?>
        <div class="alert alert-warning">
            ❌ <strong>Gagal Mengunggah Foto!</strong> Terjadi kesalahan server saat menyimpan file QRIS. Coba lagi.
        </div>
    <?php elseif ($notif === "qris_pilih_file"): ?>
        <div class="alert alert-warning">
            ⚠️ <strong>Pilih File Terlebih Dahulu!</strong> Silakan pilih file foto QRIS dari perangkat Anda.
        </div>
    <?php elseif ($notif === "qris_diverifikasi"): ?>
        <div class="alert alert-success">
            ✅ <strong>Pembayaran QRIS Terbukti Sudah Bayar!</strong> Anda telah memverifikasi pembayaran pelanggan. Dana dinyatakan sah dan pesanan siap diantar.
        </div>
    <?php elseif ($notif === "qris_ditolak"): ?>
        <div class="alert alert-warning">
            ❌ <strong>Pembayaran QRIS Ditolak!</strong> Anda telah menolak pembayaran pesanan ini. Status pesanan dikembalikan ke "Menunggu Pembayaran" agar pelanggan melakukan transfer ulang / mengirimkan bukti yang sah.
        </div>
    <?php endif; ?>

    <!-- STATUS AKTIF & PROFIL WHATSAPP -->
    <div class="card">
        <h2>⚙️ Status & Kontak Komunikasi</h2>

        <div class="status-row">
            <div>
                <p style="font-size: 14px; margin-bottom: 5px;">Status Ketersediaan Driver:</p>
                <span class="badge <?= $statusAktif === 1 ? 'aktif' : 'off' ?>">
                    <?= $statusAktif === 1 ? '● Aktif (Menerima Pesanan)' : '● Off (Tidak Menerima)' ?>
                </span>
            </div>

            <form method="POST" action="driver.php">
                <input type="hidden" name="ubah_status" value="1">
                <input type="hidden" name="status_aktif" value="<?= $statusAktif === 1 ? 0 : 1 ?>">
                <button type="submit" class="<?= $statusAktif === 1 ? 'btn-off' : 'btn-aktif' ?>">
                    <?= $statusAktif === 1 ? 'Matikan Status (Off)' : 'Aktifkan Status (Online)' ?>
                </button>
            </form>
        </div>

        <div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 14px; font-weight: bold; margin-bottom: 5px;">
                💬 Nomor WhatsApp Driver untuk Komunikasi Pelanggan:
            </p>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 8px;">
                Nomor ini akan muncul di halaman "Pesanan Saya" milik pelanggan untuk tombol WhatsApp.
            </p>
            <form method="POST" action="driver.php" class="wa-form-row">
                <input
                    type="text"
                    name="no_hp"
                    placeholder="Contoh: 081234567890"
                    value="<?= htmlspecialchars($noHpDriver) ?>"
                    required
                >
                <button type="submit" name="update_whatsapp" class="btn-wa-save">
                    Simpan No. WhatsApp
                </button>
            </form>
        </div>
    </div>

    <!-- PENGATURAN QRIS PRIBADI DRIVER -->
    <div class="card qris-driver-card">
        <h2>💳 QRIS Pembayaran Pribadi Driver</h2>
        <p style="font-size: 14px; color: #475569; margin-bottom: 6px;">
            Setiap driver memiliki QRIS masing-masing. Pelanggan yang pesanannya kamu terima akan langsung membayar via scan QRIS pribadimu.
        </p>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">
            Dukung QRIS dari aplikasi apa saja (DANA Bisnis, BCA, GoPay Merchant, OVO, ShopeePay, Bank Mandiri, dll).
        </p>

        <div class="qris-driver-grid">
            <!-- Preview QRIS -->
            <div class="qris-preview-card <?= (!empty($fotoQrisDriver) && file_exists(__DIR__ . '/uploads/qris/' . $fotoQrisDriver)) ? 'has-qris' : '' ?>">
                <?php if (!empty($fotoQrisDriver) && file_exists(__DIR__ . '/uploads/qris/' . $fotoQrisDriver)): ?>
                    <img src="../uploads/qris/<?= htmlspecialchars($fotoQrisDriver) ?>" alt="QRIS Driver <?= htmlspecialchars($namaDriver) ?>">
                    <span class="qris-badge-status qris-badge-active">✅ QRIS Terpasang</span>
                    <form method="POST" action="driver.php" style="margin-top: 5px;">
                        <input type="hidden" name="hapus_qris" value="1">
                        <button type="submit" class="btn-qris-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus foto QRIS ini?');">
                            🗑️ Hapus QRIS
                        </button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 38px; margin-bottom: 6px;">📷</div>
                    <p style="font-size: 13px; color: #64748b; font-weight: bold; margin-bottom: 4px;">Belum Ada QRIS</p>
                    <p style="font-size: 11px; color: #94a3b8; line-height: 1.3;">Unggah foto QRIS pribadimu sekarang</p>
                    <span class="qris-badge-status qris-badge-empty">⚠️ Belum Diunggah</span>
                <?php endif; ?>
            </div>

            <!-- Form Upload QRIS -->
            <div class="qris-form-box">
                <form method="POST" action="driver.php" enctype="multipart/form-data">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: #1e293b;">
                        <?= (!empty($fotoQrisDriver) && file_exists(__DIR__ . '/uploads/qris/' . $fotoQrisDriver)) ? '🔄 Ganti / Perbarui Foto QRIS:' : '📤 Unggah Foto QRIS Baru:' ?>
                    </label>
                    <div class="file-input-wrapper">
                        <input
                            type="file"
                            name="foto_qris"
                            accept="image/png, image/jpeg, image/jpg, image/webp"
                            class="file-input-custom"
                            required
                        >
                        <button type="submit" name="upload_qris" class="btn-qris-upload">
                            <?= (!empty($fotoQrisDriver) && file_exists(__DIR__ . '/uploads/qris/' . $fotoQrisDriver)) ? 'Simpan Perubahan' : 'Unggah QRIS' ?>
                        </button>
                    </div>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 6px;">
                        Format yang didukung: JPG, PNG, WEBP. Ukuran maksimal: 3 MB. Pastikan QR code terlihat jelas dan mudah di-scan.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- 0. DRIVER ORDER POOL (PESANAN TERSEDIA DARI SEMUA PELANGGAN) -->
    <div class="card" style="border-left: 6px solid #10b981; margin-bottom: 25px;">
        <h2>🛵 Order Pool (Pesanan Terbuka untuk Diambil)</h2>
        <p style="font-size: 13px; color: #475569; margin-bottom: 15px;">
            Daftar pesanan dari pelanggan yang belum memilih driver spesifik dan telah <strong>LUNAS via Midtrans</strong> atau <strong>Terkonfirmasi COD</strong>.
        </p>

        <?php if (empty($pesananPool)): ?>
            <p class="kosong">Saat ini belum ada pesanan terbuka di order pool.</p>
        <?php else: ?>
            <div class="pesanan-list">
                <?php foreach ($pesananPool as $pool): ?>
                    <?php
                        $isPaidPool = (($pool["payment_status"] ?? "") === "paid");
                    ?>
                    <div class="pesanan-item" style="border-left: 4px solid #10b981;">
                        <div class="pesanan-header">
                            <h3>Pesanan #<?= (int) $pool["id"] ?></h3>
                            <span class="status-pesanan" style="<?= $isPaidPool ? 'background: #dcfce7; color: #166534;' : 'background: #fef9c3; color: #854d0e;' ?>">
                                <?= $isPaidPool ? '✅ Lunas (Midtrans QRIS)' : '💵 COD (Tagih Tunai)' ?>
                            </span>
                        </div>

                        <p><strong>Pelanggan:</strong> <?= htmlspecialchars($pool["nama_pelanggan"]) ?></p>

                        <!-- DETAIL RUTE & ONGKIR PRD 2.0 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin: 8px 0; font-size: 13px;">
                            <div>📍 <strong>Asal / Jemput:</strong> <?= htmlspecialchars($pool["origin_location"] ?: ($pool["alamat"] ?: "-")) ?></div>
                            <div style="margin-top: 4px;">🏁 <strong>Tujuan / Antar:</strong> <?= htmlspecialchars($pool["destination_location"] ?: ($pool["alamat"] ?: "-")) ?></div>
                            <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center; font-weight: bold;">
                                <span style="color: #2563eb;">📏 Jarak: <?= number_format((float)($pool["distance_km"] ?? 0), 2) ?> KM</span>
                                <span style="color: #16a34a; font-size: 15px;">Ongkir: Rp <?= number_format((int)($pool["total_harga"] ?? 0), 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="catatan-highlight">
                            <strong>Catatan:</strong> <?= nl2br(htmlspecialchars($pool["catatan"] ?? "-")) ?>
                        </div>

                        <div class="action-group" style="margin-top: 12px;">
                            <form method="POST" action="index.php" style="display:inline;">
                                <input type="hidden" name="pesanan_id" value="<?= (int) $pool["id"] ?>">
                                <input type="hidden" name="aksi_pesanan" value="ambil_pool">
                                <button type="submit" class="btn-terima" style="background: #10b981;" onclick="return confirm('Ambil pesanan ini dan mulai kerjakan?');">
                                    🛵 Ambil Pesanan Ini
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 1. PESANAN BARU MASUK (TERIMA / TOLAK) -->
    <div class="card" style="border-left: 6px solid #f59e0b;">
        <h2>📬 Pesanan Baru Masuk (Perlu Konfirmasi)</h2>

        <?php if (empty($pesananBaru)): ?>
            <p class="kosong">Belum ada pesanan baru yang menunggu konfirmasi kamu.</p>
        <?php else: ?>
            <div class="pesanan-list">
                <?php foreach ($pesananBaru as $item): ?>
                    <?php
                        $isPaidItem = (($item["payment_status"] ?? "") === "paid");
                    ?>
                    <div class="pesanan-item">
                        <div class="pesanan-header">
                            <h3>Pesanan #<?= (int) $item["id"] ?></h3>
                            <span class="status-pesanan" style="<?= $isPaidItem ? 'background: #dcfce7; color: #166534;' : 'background: #fef9c3; color: #854d0e;' ?>">
                                <?= $isPaidItem ? '✅ Lunas (Midtrans)' : '💵 COD' ?>
                            </span>
                        </div>

                        <p><strong>Pelanggan:</strong> <?= htmlspecialchars($item["nama_pelanggan"]) ?></p>
                        <p><strong>Layanan:</strong> <?= htmlspecialchars($item["layanan"]) ?></p>
                        
                        <!-- DETAIL RUTE & ONGKIR PRD 2.0 -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin: 8px 0; font-size: 13px;">
                            <div>📍 <strong>Asal / Jemput:</strong> <?= htmlspecialchars($item["origin_location"] ?: ($item["alamat"] ?: "-")) ?></div>
                            <div style="margin-top: 4px;">🏁 <strong>Tujuan / Antar:</strong> <?= htmlspecialchars($item["destination_location"] ?: ($item["alamat"] ?: "-")) ?></div>
                            <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center; font-weight: bold;">
                                <span style="color: #2563eb;">📏 Jarak: <?= number_format((float)($item["distance_km"] ?? 0), 2) ?> KM</span>
                                <span style="color: #16a34a; font-size: 15px;">Ongkir: Rp <?= number_format((int)($item["total_harga"] ?? 0), 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="catatan-highlight">
                            <strong>Catatan / Kebutuhan:</strong><br>
                            <?= nl2br(htmlspecialchars($item["catatan"] ?? "-")) ?>
                        </div>

                        <div class="action-group">
                            <!-- TOMBOL TERIMA -->
                            <form method="POST" action="index.php" style="display:inline;">
                                <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                <input type="hidden" name="aksi_pesanan" value="terima">
                                <button type="submit" class="btn-terima" onclick="return confirm('Terima pesanan ini?');">
                                    ✅ Terima & Proses Pesanan
                                </button>
                            </form>

                            <!-- TOMBOL TOLAK -->
                            <form method="POST" action="index.php" style="display:inline;">
                                <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                <input type="hidden" name="aksi_pesanan" value="tolak">
                                <button type="submit" class="btn-tolak" onclick="return confirm('Apakah Anda yakin ingin menolak pesanan ini?');">
                                    ❌ Tolak Pesanan
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. PESANAN SEDANG BERJALAN -->
    <div class="card" style="border-left: 6px solid #2563eb;">
        <h2>🚀 Pesanan Sedang Berjalan</h2>

        <?php if (empty($pesananBerjalan)): ?>
            <p class="kosong">Tidak ada pesanan yang sedang berjalan saat ini.</p>
        <?php else: ?>
            <div class="pesanan-list">
                <?php foreach ($pesananBerjalan as $item): ?>
                    <div class="pesanan-item">
                        <div class="pesanan-header">
                            <h3>Pesanan #<?= (int) $item["id"] ?></h3>
                            <?php if ($item["status"] === "Menunggu Pembayaran"): ?>
                                <span class="status-pesanan st-bayar">💳 Diterima - Menunggu Pembayaran QRIS</span>
                            <?php elseif ($item["status"] === "Diproses"): ?>
                                <span class="status-pesanan st-proses">⚙️ Pembayaran Dikonfirmasi - Sedang Diproses</span>
                            <?php elseif ($item["status"] === "Dalam Perjalanan"): ?>
                                <span class="status-pesanan st-antar">🛵 Dalam Perjalanan</span>
                            <?php endif; ?>
                        </div>

                        <p><strong>Pelanggan:</strong> <?= htmlspecialchars($item["nama_pelanggan"]) ?></p>
                        <p><strong>Layanan:</strong> <?= htmlspecialchars($item["layanan"]) ?></p>

                        <div class="catatan-highlight">
                            <strong>Catatan / Kebutuhan:</strong><br>
                            <?= nl2br(htmlspecialchars($item["catatan"] ?? "-")) ?>
                        </div>

                        <!-- KOTAK STATUS & KONFIRMASI QRIS UNTUK DRIVER -->
                        <?php
                            $isQrisVerified = ((int)($item["qris_diverifikasi"] ?? 0) === 1);
                            $isQrisRejected = ((int)($item["qris_diverifikasi"] ?? 0) === 2);
                            $hasBuktiBayar = !empty($item["bukti_bayar"]) && file_exists(__DIR__ . "/uploads/bukti_bayar/" . $item["bukti_bayar"]);
                            $buktiBayarSrc = $hasBuktiBayar ? "uploads/bukti_bayar/" . htmlspecialchars($item["bukti_bayar"]) : "";
                        ?>
                        <div class="driver-qris-box <?= $isQrisVerified ? 'qris-box-verified' : ($isQrisRejected ? 'qris-box-pending' : 'qris-box-pending') ?>">
                            <div class="driver-qris-header">
                                <div class="driver-qris-title">
                                    <span class="qris-icon"><?= $isQrisVerified ? '✅' : ($isQrisRejected ? '❌' : '💳') ?></span>
                                    <div>
                                        <strong>Status Bukti Pembayaran QRIS</strong>
                                        <div class="driver-qris-subtitle">
                                            <?php if ($isQrisVerified): ?>
                                                <span class="badge-verified">🎉 QRIS Terbukti Sudah Bayar (Diverifikasi & Sah)</span>
                                            <?php elseif ($isQrisRejected): ?>
                                                <span class="badge-rejected">❌ Pembayaran Ditolak Driver (Menunggu Bayar Ulang)</span>
                                            <?php elseif ($item["status"] === "Diproses"): ?>
                                                <span class="badge-claimed">🔔 Pelanggan Menyatakan Sudah Bayar (Perlu Dicek Driver)</span>
                                            <?php else: ?>
                                                <span class="badge-waiting">⏳ Menunggu Pelanggan Menyelesaikan QRIS</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($isQrisVerified): ?>
                                    <span class="verified-pill">Terverifikasi</span>
                                <?php elseif ($isQrisRejected): ?>
                                    <span style="background: #ef4444; color: white; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;">Ditolak</span>
                                <?php else: ?>
                                    <span class="unverified-pill">Belum Diverifikasi</span>
                                <?php endif; ?>
                            </div>

                            <!-- AREA BUKTI TRANSFER & DETAIL -->
                            <div class="driver-qris-body">
                                <?php if ($isQrisRejected && !empty($item["alasan_tolak_bayar"])): ?>
                                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-size: 12px; color: #991b1b;">
                                        <strong>⚠️ Alasan Penolakan Anda Sebelumnya:</strong> "<?= htmlspecialchars($item["alasan_tolak_bayar"]) ?>"
                                    </div>
                                <?php endif; ?>

                                <?php if ($hasBuktiBayar): ?>
                                    <div class="bukti-preview-container">
                                        <div class="bukti-thumb" onclick="openBuktiModal('<?= $buktiBayarSrc ?>')">
                                            <img src="<?= $buktiBayarSrc ?>" alt="Bukti Pembayaran Pesanan #<?= $item['id'] ?>">
                                            <div class="bukti-overlay">
                                                <span>🔍 Perbesar</span>
                                            </div>
                                        </div>
                                        <div class="bukti-info">
                                            <p class="bukti-label">📸 <strong>Foto Bukti Transfer Pelanggan:</strong></p>
                                            <p class="bukti-hint">Klik foto di samping untuk periksa struk, mutasi, nominal, dan nama pengirim.</p>
                                            <button type="button" class="btn-lihat-bukti" onclick="openBuktiModal('<?= $buktiBayarSrc ?>')">
                                                👁️ Lihat Foto Bukti Pembayaran
                                            </button>
                                        </div>
                                    </div>
                                <?php elseif ($item["status"] === "Diproses"): ?>
                                    <div class="bukti-empty-notice">
                                        <span style="font-size: 20px;">ℹ️</span>
                                        <div>
                                            <strong>Pelanggan telah menekan tombol "Konfirmasi Sudah Bayar".</strong>
                                            <p style="margin-top: 4px; font-size: 12px; color: #64748b;">Pelanggan tidak melampirkan foto struk. Buka mutasi e-wallet / m-Banking QRIS Anda untuk memeriksa saldo.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bukti-empty-notice">
                                        <span style="font-size: 20px;">⏳</span>
                                        <div>
                                            <strong>Menunggu Pelanggan:</strong>
                                            <p style="margin-top: 4px; font-size: 12px; color: #64748b;">Pelanggan sedang melakukan pembayaran melalui kode QRIS yang tersedia di halaman mereka.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- KEPUTUSAN DRIVER: TERIMA / TOLAK PEMBAYARAN -->
                            <?php if (!$isQrisVerified): ?>
                                <div class="driver-qris-actions">
                                    <div class="driver-decision-grid">
                                        <!-- TOMBOL TERIMA PEMBAYARAN -->
                                        <form method="POST" action="driver.php" style="margin: 0;">
                                            <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                            <input type="hidden" name="aksi_pesanan" value="konfirmasi_qris">
                                            <button 
                                                type="submit" 
                                                class="btn-konfirmasi-qris-driver"
                                                onclick="return confirm('Apakah Anda sudah mengecek mutasi e-wallet/rekening atau bukti bayar dan memastikan uang QRIS benar-benar masuk?');"
                                            >
                                                ✅ Terima Pembayaran (Sah)
                                            </button>
                                        </form>

                                        <!-- TOMBOL BUKA FORM TOLAK PEMBAYARAN -->
                                        <button 
                                            type="button" 
                                            class="btn-tolak-qris-driver"
                                            onclick="toggleTolakBox('tolak_box_<?= (int) $item['id'] ?>')"
                                        >
                                            ❌ Tolak Pembayaran
                                        </button>
                                    </div>

                                    <!-- FORM TOLAK PEMBAYARAN -->
                                    <div id="tolak_box_<?= (int) $item['id'] ?>" class="tolak-form-box">
                                        <form method="POST" action="driver.php">
                                            <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                            <input type="hidden" name="aksi_pesanan" value="tolak_qris">
                                            
                                            <div class="tolak-form-title">
                                                <span>⚠️</span>
                                                <span>Tolak Pembayaran QRIS Pelanggan</span>
                                            </div>
                                            <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                                                Pilih alasan penolakan agar pelanggan mengetahui mengapa pembayarannya belum diterima dan dapat melakukan pembayaran ulang:
                                            </p>

                                            <select name="alasan_pilihan" class="tolak-select" required>
                                                <option value="Bukti transfer tidak valid / diduga palsu">Bukti transfer tidak valid / diduga palsu</option>
                                                <option value="Saldo QRIS belum masuk ke e-wallet / mutasi rekening">Saldo QRIS belum masuk ke e-wallet / mutasi rekening</option>
                                                <option value="Nominal transfer tidak sesuai / kurang">Nominal transfer tidak sesuai / kurang</option>
                                                <option value="Salah scan QRIS / nama penerima tidak cocok">Salah scan QRIS / nama penerima tidak cocok</option>
                                                <option value="Lainnya">Alasan lainnya</option>
                                            </select>

                                            <input 
                                                type="text" 
                                                name="alasan_kustom" 
                                                class="tolak-input" 
                                                placeholder="Catatan tambahan untuk pelanggan (opsional, misal: Kurang Rp 5.000)..."
                                            >

                                            <div class="tolak-form-actions">
                                                <button type="button" class="btn-batal-tolak" onclick="toggleTolakBox('tolak_box_<?= (int) $item['id'] ?>')">
                                                    Batal
                                                </button>
                                                <button type="submit" class="btn-submit-tolak" onclick="return confirm('Apakah Anda yakin ingin menolak pembayaran ini? Pesanan akan dikembalikan ke status Menunggu Pembayaran.');">
                                                    Konfirmasi Tolak Pembayaran
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="driver-qris-verified-msg">
                                    <span style="font-size: 18px;">🛡️</span>
                                    <div style="flex: 1;">
                                        <strong>Pembayaran Sah & Terverifikasi!</strong> Uang QRIS telah Anda pastikan masuk. Pesanan siap diantar.
                                    </div>
                                    <button 
                                        type="button" 
                                        style="background: transparent; color: #dc2626; border: 1px solid #fca5a5; padding: 4px 10px; border-radius: 6px; font-size: 11px; cursor: pointer;"
                                        onclick="toggleTolakBox('tolak_box_<?= (int) $item['id'] ?>')"
                                    >
                                        Ubah ke Tolak
                                    </button>
                                </div>

                                <!-- FORM TOLAK JIKA DIBATALKAN KEMBALI -->
                                <div id="tolak_box_<?= (int) $item['id'] ?>" class="tolak-form-box">
                                    <form method="POST" action="driver.php">
                                        <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                        <input type="hidden" name="aksi_pesanan" value="tolak_qris">
                                        
                                        <div class="tolak-form-title">
                                            <span>⚠️</span>
                                            <span>Batalkan Verifikasi & Tolak Pembayaran</span>
                                        </div>

                                        <select name="alasan_pilihan" class="tolak-select" required>
                                            <option value="Bukti transfer tidak valid / diduga palsu">Bukti transfer tidak valid / diduga palsu</option>
                                            <option value="Saldo QRIS belum masuk ke e-wallet / mutasi rekening">Saldo QRIS belum masuk ke e-wallet / mutasi rekening</option>
                                            <option value="Nominal transfer tidak sesuai / kurang">Nominal transfer tidak sesuai / kurang</option>
                                            <option value="Lainnya">Alasan lainnya</option>
                                        </select>

                                        <input 
                                            type="text" 
                                            name="alasan_kustom" 
                                            class="tolak-input" 
                                            placeholder="Catatan tambahan untuk pelanggan (opsional)..."
                                        >

                                        <div class="tolak-form-actions">
                                            <button type="button" class="btn-batal-tolak" onclick="toggleTolakBox('tolak_box_<?= (int) $item['id'] ?>')">
                                                Batal
                                            </button>
                                            <button type="submit" class="btn-submit-tolak">
                                                Tolak Pembayaran
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- AKSI BERTAHAP -->
                        <div class="action-group">
                            <?php if ($item["status"] === "Menunggu Pembayaran"): ?>
                                <p style="font-size: 13px; color: #1e40af; display: flex; align-items: center; gap: 5px;">
                                    ⏳ Menunggu pelanggan menyelesaikan scan & pembayaran QRIS di halaman Pesanan Saya...
                                </p>
                            <?php elseif ($item["status"] === "Diproses"): ?>
                                <form method="POST" action="driver.php" style="display:inline;">
                                    <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                    <input type="hidden" name="aksi_pesanan" value="antar">
                                    <button type="submit" class="btn-antar">
                                        🛵 Mulai Antar (Dalam Perjalanan)
                                    </button>
                                </form>
                            <?php elseif ($item["status"] === "Dalam Perjalanan"): ?>
                                <form method="POST" action="driver.php" style="display:inline;">
                                    <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                    <input type="hidden" name="aksi_pesanan" value="selesai">
                                    <button type="submit" class="btn-selesai" onclick="return confirm('Apakah pesanan ini sudah selesai diantar ke pelanggan?');">
                                        🏁 Selesaikan Pesanan
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. RIWAYAT PESANAN SELESAI -->
    <div class="card">
        <h2>📋 Riwayat Pesanan Selesai</h2>

        <?php if (empty($pesananSelesai)): ?>
            <p class="kosong">Belum ada riwayat pesanan yang selesai.</p>
        <?php else: ?>
            <div class="pesanan-list">
                <?php foreach ($pesananSelesai as $item): ?>
                    <div class="pesanan-item" style="opacity: 0.9;">
                        <div class="pesanan-header">
                            <h3>Pesanan #<?= (int) $item["id"] ?> - <?= htmlspecialchars($item["nama_pelanggan"]) ?></h3>
                            <span class="status-pesanan st-selesai">✅ <?= htmlspecialchars($item["status"]) ?></span>
                        </div>
                        <p><strong>Layanan:</strong> <?= htmlspecialchars($item["layanan"]) ?></p>
                        <p><strong>Catatan:</strong> <?= htmlspecialchars($item["catatan"] ?? "-") ?></p>

                        <?php
                            $selesaiHasBukti = !empty($item["bukti_bayar"]) && file_exists(__DIR__ . "/uploads/bukti_bayar/" . $item["bukti_bayar"]);
                        ?>
                        <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($item["qris_diverifikasi"])): ?>
                                <span style="font-size: 11px; background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 999px; font-weight: bold; border: 1px solid #bbf7d0;">
                                    💳 QRIS Terbukti Sudah Bayar
                                </span>
                            <?php endif; ?>
                            <?php if ($selesaiHasBukti): ?>
                                <button type="button" class="btn-lihat-bukti-mini" onclick="openBuktiModal('uploads/bukti_bayar/<?= htmlspecialchars($item['bukti_bayar']) ?>')">
                                    👁️ Lihat Bukti Pembayaran
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Lightbox Bukti Transfer -->
<div id="modalBukti" class="bukti-modal-backdrop" onclick="closeBuktiModal(event)">
    <div class="bukti-modal-content" onclick="event.stopPropagation()">
        <div class="bukti-modal-header">
            <h3>📸 Bukti Pembayaran QRIS Pelanggan</h3>
            <button type="button" class="bukti-modal-close" onclick="closeBuktiModal()">&times;</button>
        </div>
        <div class="bukti-modal-body">
            <img id="imgBuktiModal" src="" alt="Bukti Pembayaran">
        </div>
        <div class="bukti-modal-footer">
            <a id="linkBuktiDownload" href="" target="_blank" class="btn-download-bukti">📥 Buka Ukuran Asli</a>
            <button type="button" class="btn-close-modal" onclick="closeBuktiModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
function toggleTolakBox(id) {
    var box = document.getElementById(id);
    if (box) {
        box.classList.toggle('active');
    }
}

function openBuktiModal(imageSrc) {
    var modal = document.getElementById('modalBukti');
    var img = document.getElementById('imgBuktiModal');
    var link = document.getElementById('linkBuktiDownload');
    if (modal && img) {
        img.src = imageSrc;
        if (link) link.href = imageSrc;
        modal.classList.add('active');
    }
}

function closeBuktiModal(event) {
    var modal = document.getElementById('modalBukti');
    if (modal) {
        modal.classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBuktiModal();
    }
});
</script>
</body>
</html>
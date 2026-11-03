<?php
session_start();
require_once "koneksi.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?redirect=pesanan_saya");
    exit;
}

$role = $_SESSION["role"] ?? "";

if ($role === "admin") {
    header("Location: admin/index.php");
    exit;
}

if ($role === "driver") {
    header("Location: driver.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$namaPengguna = $_SESSION["nama"] ?? "Pengguna";

// Proses pembatalan pesanan jika diminta oleh user
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["batal_pesanan"])) {
    $batalId = (int) ($_POST["pesanan_id"] ?? 0);

    if ($batalId > 0) {
        $stmtBatal = $pdo->prepare("
            UPDATE pesanan
            SET status = 'Dibatalkan'
            WHERE id = ? AND user_id = ? AND status IN ('Menunggu', 'Menunggu Driver', 'Menunggu Persetujuan Driver')
        ");
        $stmtBatal->execute([$batalId, $userId]);

        header("Location: pesanan_saya.php?pesan=dibatalkan");
        exit;
    }
}

// Proses update / edit pesanan jika masih menunggu konfirmasi driver
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["edit_pesanan"])) {
    $editId = (int) ($_POST["pesanan_id"] ?? 0);
    $catatanBaru = trim($_POST["catatan"] ?? "");

    if ($editId > 0 && $catatanBaru !== "") {
        $stmtEdit = $pdo->prepare("
            UPDATE pesanan
            SET catatan = ?
            WHERE id = ? AND user_id = ? AND status IN ('Menunggu', 'Menunggu Driver', 'Menunggu Persetujuan Driver')
        ");
        $stmtEdit->execute([$catatanBaru, $editId, $userId]);

        header("Location: pesanan_saya.php?id=" . $editId . "&pesan=diedit");
        exit;
    }
}

// Proses konfirmasi pembayaran QRIS oleh pelanggan
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["konfirmasi_bayar"])) {
    $bayarId = (int) ($_POST["pesanan_id"] ?? 0);

    if ($bayarId > 0) {
        $uploadedBukti = null;

        // Cek jika user mengunggah foto bukti transfer
        if (isset($_FILES["bukti_bayar"]) && $_FILES["bukti_bayar"]["error"] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES["bukti_bayar"]["tmp_name"];
            $fileName = $_FILES["bukti_bayar"]["name"];
            $fileSize = $_FILES["bukti_bayar"]["size"];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ["jpg", "jpeg", "png", "webp"];

            if (in_array($ext, $allowedExtensions) && $fileSize <= 5 * 1024 * 1024) {
                $mime = "";
                if (function_exists("finfo_open")) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $fileTmp);
                    finfo_close($finfo);
                } elseif (function_exists("mime_content_type")) {
                    $mime = mime_content_type($fileTmp);
                }

                $allowedMimes = ["image/jpeg", "image/png", "image/webp", "image/pjpeg"];
                if (!$mime || in_array($mime, $allowedMimes)) {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "bukti_bayar" . DIRECTORY_SEPARATOR;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $newFileName = "bukti_" . $bayarId . "_" . time() . "." . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                        $uploadedBukti = $newFileName;
                    }
                }
            }
        }

        if ($uploadedBukti) {
            $stmtBayar = $pdo->prepare("
                UPDATE pesanan
                SET status = 'Diproses',
                    bukti_bayar = ?,
                    qris_diverifikasi = 0,
                    alasan_tolak_bayar = NULL,
                    waktu_bayar = NOW()
                WHERE id = ? AND user_id = ? AND status = 'Menunggu Pembayaran'
            ");
            $stmtBayar->execute([$uploadedBukti, $bayarId, $userId]);
        } else {
            $stmtBayar = $pdo->prepare("
                UPDATE pesanan
                SET status = 'Diproses',
                    qris_diverifikasi = 0,
                    alasan_tolak_bayar = NULL,
                    waktu_bayar = NOW()
                WHERE id = ? AND user_id = ? AND status = 'Menunggu Pembayaran'
            ");
            $stmtBayar->execute([$bayarId, $userId]);
        }

        header("Location: pesanan_saya.php?id=" . $bayarId . "&pesan=bayar_sukses");
        exit;
    }
}

// Ambil semua daftar pesanan milik user ini beserta data nomor HP driver
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.driver_id,
        p.layanan,
        p.alamat,
        p.origin_location,
        p.destination_location,
        p.distance_km,
        p.total_harga,
        p.snap_token,
        p.payment_status,
        p.catatan,
        p.status,
        p.metode_bayar,
        p.bukti_bayar,
        p.qris_diverifikasi,
        p.alasan_tolak_bayar,
        p.waktu_bayar,
        p.created_at,
        u.nama AS nama_driver,
        u.email AS email_driver,
        d.no_hp AS no_hp_driver,
        d.foto_qris AS foto_qris_driver
    FROM pesanan p
    LEFT JOIN users u ON p.driver_id = u.id
    LEFT JOIN drivers d ON d.user_id = u.id
    WHERE p.user_id = :user_id
    ORDER BY p.id DESC
");
$stmt->execute(["user_id" => $userId]);
$pesananList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung ringkasan status
$totalPesanan = count($pesananList);
$totalAktif = 0;
$totalSelesai = 0;

foreach ($pesananList as $item) {
    if (in_array($item["status"], ["Menunggu", "Menunggu Driver", "Menunggu Persetujuan Driver", "Menunggu Pembayaran", "Diproses", "Dalam Perjalanan"])) {
        $totalAktif++;
    } elseif ($item["status"] === "Selesai") {
        $totalSelesai++;
    }
}

$highlightId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$pesanType = $_GET["pesan"] ?? "";

function formatTanggalIndo($datetimeStr) {
    if (!$datetimeStr) return "-";
    $timestamp = strtotime($datetimeStr);
    $bulanIndo = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $d = date('d', $timestamp);
    $m = (int) date('m', $timestamp);
    $y = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    return "$d {$bulanIndo[$m]} $y, $time WIB";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Polindra Suruh</title>

    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- MIDTRANS SNAP JS POP-UP -->
    <script 
        src="<?= htmlspecialchars(MIDTRANS_SNAP_URL) ?>" 
        data-client-key="<?= htmlspecialchars(MIDTRANS_CLIENT_KEY) ?>">
    </script>

    <style>
        .page-container {
            max-width: 960px;
            margin: 35px auto 70px;
            padding: 0 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 25px;
        }

        .header-title-area .badge-sub {
            display: inline-block;
            background: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            color: #111;
        }

        .header-title-area h1 {
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 900;
            color: #111;
            margin: 0;
        }

        .header-title-area p {
            color: #333;
            margin-top: 6px;
            font-size: 14px;
        }

        .btn-order-new {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #111;
            color: #fff;
            padding: 13px 24px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(0,0,0,0.18);
            transition: transform 0.2s, background 0.2s;
        }

        .btn-order-new:hover {
            background: #252525;
            transform: translateY(-2px);
        }

        /* Notifikasi Alert */
        .alert-box {
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.92);
            padding: 18px 20px;
            border-radius: 16px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.7);
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-val {
            font-size: 28px;
            font-weight: 900;
            color: #0f172a;
            margin-top: 4px;
        }

        /* Order Cards List */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .order-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.09);
        }

        .order-card.is-highlighted {
            border: 2px solid #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15), 0 10px 25px rgba(0,0,0,0.08);
        }

        .highlight-tag {
            position: absolute;
            top: -11px;
            right: 25px;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f1f5f9;
        }

        .order-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .order-id-badge {
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
            background: #f1f5f9;
            padding: 5px 12px;
            border-radius: 8px;
        }

        .order-time {
            font-size: 13px;
            color: #64748b;
        }

        /* Status Badges */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .status-bayar {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-proses {
            background: #ede9fe;
            color: #6b21a8;
        }

        .status-perjalanan {
            background: #fef08a;
            color: #854d0e;
        }

        .status-selesai {
            background: #dcfce7;
            color: #15803d;
        }

        .status-batal {
            background: #fee2e2;
            color: #b91c1c;
        }

        .card-body {
            padding: 18px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .card-body {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        .info-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-content {
            font-size: 15px;
            color: #1e293b;
            line-height: 1.6;
        }

        .catatan-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            color: #334155;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 160px;
            overflow-y: auto;
        }

        /* Driver Details inside Card */
        .driver-info-panel {
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .driver-profile {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .driver-profile-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .driver-avatar-mini {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd21f, #f7b500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 18px;
            color: #222;
        }

        .driver-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }

        .driver-sub {
            font-size: 12px;
            color: #16a34a;
            font-weight: 600;
        }

        /* WhatsApp Button */
        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #25D366;
            color: white;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: background 0.2s, transform 0.2s;
            margin-top: 10px;
        }

        .btn-whatsapp:hover {
            background: #1da851;
            transform: translateY(-2px);
        }

        .btn-whatsapp svg {
            width: 17px;
            height: 17px;
            fill: white;
        }

        .driver-missing-box {
            background: #fffbeb;
            border: 1px solid #fef08a;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .driver-missing-text {
            font-size: 13px;
            color: #854d0e;
            font-weight: 600;
        }

        .btn-pick-driver-now {
            background: #f59e0b;
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-pick-driver-now:hover {
            background: #d97706;
        }

        /* =========================================
           KOTAK METODE PEMBAYARAN QRIS (JIKA DITERIMA)
        ========================================= */
        .qris-payment-box {
            background: linear-gradient(135deg, #f0fdf4, #e0f2fe);
            border: 2px solid #86efac;
            border-radius: 16px;
            padding: 24px 20px;
            margin-top: 20px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .qris-box-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .qris-box-header h4 {
            font-size: 17px;
            color: #065f46;
            margin: 0;
            font-weight: 900;
        }

        .qris-desc {
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .qris-content-grid {
            display: flex;
            gap: 25px;
            align-items: center;
            flex-wrap: wrap;
        }

        .qris-image-wrapper {
            background: white;
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            border: 1px solid #e2e8f0;
            max-width: 250px;
            margin: 0 auto;
        }

        .qris-image-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 10px;
            display: block;
        }

        .qris-instructions {
            flex: 1;
            min-width: 260px;
        }

        .qris-steps {
            list-style: none;
            padding: 0;
            margin: 0 0 18px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .qris-steps li {
            font-size: 13px;
            color: #1e293b;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
        }

        .qris-steps .step-num {
            background: #2563eb;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .btn-konfirmasi-qris {
            background: #16a34a;
            color: white;
            border: none;
            padding: 13px 22px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-konfirmasi-qris:hover {
            background: #15803d;
            transform: translateY(-2px);
        }

        /* Upload Bukti Pembayaran di Pesanan Saya */
        .upload-bukti-box {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: border-color 0.2s;
        }

        .upload-bukti-box:hover {
            border-color: #3b82f6;
        }

        .upload-bukti-label {
            display: block;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .file-input-bukti {
            width: 100%;
            font-size: 13px;
            color: #475569;
        }

        .upload-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            line-height: 1.4;
        }

        .bukti-upload-preview {
            margin-top: 10px;
            max-width: 120px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
        }

        .bukti-upload-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Alert Penolakan QRIS oleh Driver */
        .qris-rejected-alert {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.12);
        }

        .rejected-alert-icon {
            font-size: 28px;
            line-height: 1;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .rejected-alert-content h4 {
            color: #991b1b;
            font-size: 15px;
            margin: 0 0 6px 0;
            font-weight: 700;
        }

        .rejected-reason {
            background: #fee2e2;
            color: #b91c1c;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            margin: 6px 0;
            border-left: 4px solid #dc2626;
        }

        .rejected-instruction {
            color: #4b5563;
            font-size: 13px;
            margin-top: 6px;
            line-height: 1.4;
        }

        /* Banner Status Verifikasi QRIS Pelanggan */
        .qris-status-banner {
            padding: 14px 18px;
            border-radius: 12px;
            margin-top: 15px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
            line-height: 1.5;
        }

        .qris-status-banner-verified {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            color: #166534;
        }

        .qris-status-banner-pending {
            background: #fffbeb;
            border: 1.5px solid #fcd34d;
            color: #92400e;
        }

        .btn-lihat-bukti-user {
            margin-top: 8px;
            background: white;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-lihat-bukti-user:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        /* Modal Lightbox Bukti Transfer User */
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
            animation: modalPopUser 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPopUser {
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

        /* Banner Status Tambahan */
        .status-banner-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 13px;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Card Footer */
        .card-footer {
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-edit-pesanan {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit-pesanan:hover {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-batal {
            background: transparent;
            color: #dc2626;
            border: 1px solid #fca5a5;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-batal:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-reorder {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #1e293b;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-reorder:hover {
            background: #e2e8f0;
        }

        /* Empty State */
        .empty-state {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 60px 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            max-width: 550px;
            margin: 40px auto;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 18px;
            display: inline-block;
        }

        .empty-title {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .empty-desc {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        @media (max-width: 600px) {
            .page-container {
                margin: 20px auto 50px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .order-card {
                padding: 18px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .qris-content-grid {
                flex-direction: column;
            }

            .qris-image-wrapper {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav>
    <div class="logo">
        <div class="logo-icon">PS</div>
        <div class="logo-text">
            <span>POLINDRA</span>
            <span>SURUH</span>
        </div>
    </div>

    <div class="menu">
        <a href="index.php">HOME</a>
        <a href="layanan.php">LAYANAN</a>
        <a href="cara_suruh.php">CARA SURUH</a>
        <a href="tentang.php">TENTANG</a>
    </div>

    <div class="nav-account">
        <a href="order.php" class="login-btn">BUAT PESANAN</a>
        <a href="pesanan_saya.php" class="login-btn active">PESANAN SAYA</a>
        <a href="logout.php" class="login-btn">LOGOUT</a>
    </div>
</nav>

<main class="page-container">

    <!-- HEADER -->
    <div class="header-section">
        <div class="header-title-area">
            <span class="badge-sub">DAFTAR AKTIVITAS</span>
            <h1>Pesanan Saya</h1>
            <p>Halo <strong><?= htmlspecialchars($namaPengguna, ENT_QUOTES, "UTF-8") ?></strong>, pantau status pesanan dan komunikasi dengan driver di sini.</p>
        </div>

        <a href="order.php" class="btn-order-new">
            + Buat Pesanan Baru
        </a>
    </div>

    <!-- NOTIFIKASI ALERT -->
    <?php if ($pesanType === "sukses" && $highlightId > 0): ?>
        <div class="alert-box alert-success">
            <span>🎉</span>
            <div>
                <strong>Pesanan Berhasil Dikirim!</strong>
               
            </div>
        </div>
    <?php elseif ($pesanType === "bayar_sukses"): ?>
        <div class="alert-box alert-success">
            <span>✅</span>
            <div>
                <strong>Pembayaran QRIS Berhasil Dikonfirmasi!</strong>
                Driver telah diberitahu dan pesananmu sekarang sedang diproses.
            </div>
        </div>
    <?php elseif ($pesanType === "driver_terpilih" && $highlightId > 0): ?>
        <div class="alert-box alert-success">
            <span>🛵</span>
            <div>
                <strong>Driver Berhasil Dipilih!</strong>
                Driver telah ditugaskan untuk menangani Pesanan #<?= (int) $highlightId ?>.
            </div>
        </div>
    <?php elseif ($pesanType === "dibatalkan"): ?>
        <div class="alert-box alert-info">
            <span>ℹ️</span>
            <div>Pesanan berhasil dibatalkan.</div>
        </div>
    <?php elseif ($pesanType === "diedit"): ?>
        <div class="alert-box alert-success">
            <span>✅</span>
            <div>Rincian catatan pesanan berhasil diperbarui!</div>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN STATISTIK -->
    <?php if ($totalPesanan > 0): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Pesanan</div>
                <div class="stat-val"><?= $totalPesanan ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sedang Berjalan</div>
                <div class="stat-val" style="color: #2563eb;"><?= $totalAktif ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pesanan Selesai</div>
                <div class="stat-val" style="color: #16a34a;"><?= $totalSelesai ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- DAFTAR PESANAN -->
    <?php if (!empty($pesananList)): ?>

        <div class="orders-list">

            <?php foreach ($pesananList as $item): ?>
                <?php
                    $isHighlighted = ($highlightId === (int) $item["id"]);
                    $status = $item["status"];

                    $statusClass = "status-menunggu";
                    $statusIcon = "⏳";
                    $statusText = $status;

                    if (in_array($status, ["Menunggu Driver", "Menunggu"])) {
                        $statusClass = "status-menunggu";
                        $statusIcon = "⏳";
                        $statusText = "Menunggu Driver";
                    } elseif ($status === "Menunggu Persetujuan Driver") {
                        $statusClass = "status-menunggu";
                        $statusIcon = "⏳";
                        $statusText = "Menunggu Persetujuan Driver";
                    } elseif ($status === "Menunggu Pembayaran") {
                        $isCash = ($item["metode_bayar"] ?? "qris") === "cash";
                        if ((int)($item["qris_diverifikasi"] ?? 0) === 2) {
                            $statusClass = "status-batal";
                            $statusIcon = "❌";
                            $statusText = "Pembayaran Ditolak Driver - Perlu Bayar Ulang";
                        } elseif ($isCash) {
                            $statusClass = "status-bayar";
                            $statusIcon = "💵";
                            $statusText = "Pesanan Diterima - Siapkan Pembayaran Cash";
                        } else {
                            $statusClass = "status-bayar";
                            $statusIcon = "💳";
                            $statusText = "Pesanan Diterima - Menunggu Pembayaran QRIS";
                        }
                    } elseif ($status === "Diproses") {
                        $statusClass = "status-proses";
                        $statusIcon = "⚙️";
                        if ((int)($item["qris_diverifikasi"] ?? 0) === 1) {
                            $statusText = "QRIS Terverifikasi - Sedang Diproses";
                        } else {
                            $statusText = "Pembayaran Dikonfirmasi - Menunggu Verifikasi Driver";
                        }
                    } elseif ($status === "Dalam Perjalanan") {
                        $statusClass = "status-perjalanan";
                        $statusIcon = "🛵";
                        $statusText = "Driver Dalam Perjalanan";
                    } elseif ($status === "Selesai") {
                        $statusClass = "status-selesai";
                        $statusIcon = "✅";
                        $statusText = "Pesanan Selesai";
                    } elseif ($status === "Dibatalkan") {
                        $statusClass = "status-batal";
                        $statusIcon = "❌";
                        $statusText = "Dibatalkan";
                    }

                    $canCancel = in_array($status, ["Menunggu", "Menunggu Driver", "Menunggu Persetujuan Driver"]);

                    // Format nomor WhatsApp driver
                    $noHpDriver = $item["no_hp_driver"] ?? "";
                    $waNumber = preg_replace('/[^0-9]/', '', $noHpDriver);
                    if (!empty($waNumber) && substr($waNumber, 0, 1) === '0') {
                        $waNumber = '62' . substr($waNumber, 1);
                    }
                    $waText = rawurlencode("Halo " . ($item["nama_driver"] ?? "Driver") . ", saya pemesan di Polindra Suruh untuk Pesanan #" . $item["id"] . ".");
                    $waUrl = !empty($waNumber) ? "https://wa.me/" . $waNumber . "?text=" . $waText : "#";
                ?>

                <div class="order-card <?= $isHighlighted ? 'is-highlighted' : '' ?>">

                    <?php if ($isHighlighted): ?>
                        <div class="highlight-tag">Pesanan Terbaru</div>
                    <?php endif; ?>

                    <div class="card-top">
                        <div class="order-meta">
                            <span class="order-id-badge">#PESANAN-<?= (int) $item["id"] ?></span>
                            <span class="order-time"><?= formatTanggalIndo($item["created_at"]) ?></span>
                        </div>

                        <span class="status-pill <?= $statusClass ?>">
                            <?= $statusIcon ?> <?= htmlspecialchars($statusText, ENT_QUOTES, "UTF-8") ?>
                        </span>
                    </div>

                    <!-- BADGE METODE BAYAR & STATUS PEMBAYARAN PRD 2.0 -->
                    <?php
                        $metodeBayarItem = $item["metode_bayar"] ?? "qris";
                        $paymentStatus = $item["payment_status"] ?? "pending";
                        $snapToken = $item["snap_token"] ?? "";
                    ?>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; <?= $metodeBayarItem === 'cash' ? 'background:#fef9c3; color:#92400e; border: 1px solid #fde68a;' : 'background:#eff6ff; color:#1e40af; border: 1px solid #bfdbfe;' ?>">
                            <?= $metodeBayarItem === 'cash' ? '💵 Cash / Tunai (COD)' : '📱 QRIS (Midtrans)' ?>
                        </span>

                        <?php if ($paymentStatus === "paid"): ?>
                            <span style="font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                ✅ LUNAS (Midtrans)
                            </span>
                        <?php elseif ($paymentStatus === "cod"): ?>
                            <span style="font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; background: #fefce8; color: #854d0e; border: 1px solid #fef08a;">
                                💵 COD
                            </span>
                        <?php elseif ($paymentStatus === "failed"): ?>
                            <span style="font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                ❌ Pembayaran Gagal
                            </span>
                        <?php else: ?>
                            <span style="font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 8px; background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;">
                                ⏳ Belum Dibayar
                            </span>
                            <?php if (!empty($snapToken)): ?>
                                <button type="button" 
                                        onclick="bayarMidtrans('<?= htmlspecialchars($snapToken) ?>', <?= (int)$item['id'] ?>, <?= (int)($item['total_harga'] ?? 0) ?>)"
                                        style="padding: 4px 12px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 11px; font-weight: 800; cursor: pointer;">
                                    ⚡ Bayar Sekarang
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- RINCIAN RUTE & ONGKIR PRD 2.0 -->
                    <div style="margin-top: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="flex: 1; min-width: 220px; font-size: 12px;">
                            <div style="color: #64748b; font-weight: 600;">📍 Penjemputan:</div>
                            <div style="color: #0f172a; font-weight: 700; margin-bottom: 4px;">
                                <?= htmlspecialchars($item["origin_location"] ?: ($item["alamat"] ?: "-")) ?>
                            </div>
                            <div style="color: #64748b; font-weight: 600;">🏁 Pengantaran:</div>
                            <div style="color: #0f172a; font-weight: 700;">
                                <?= htmlspecialchars($item["destination_location"] ?: ($item["alamat"] ?: "-")) ?>
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Jarak & Ongkir</div>
                            <div style="font-size: 12px; font-weight: 700; color: #2563eb;">
                                📏 <?= number_format((float)($item["distance_km"] ?? 0), 2) ?> KM
                            </div>
                            <div style="font-size: 15px; font-weight: 900; color: #16a34a;">
                                Rp <?= number_format((int)($item["total_harga"] ?? 0), 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div>
                            <div class="info-label">Catatan Kebutuhan</div>
                            <div class="catatan-box">
                                <?= nl2br(htmlspecialchars($item["catatan"] ?? "-", ENT_QUOTES, "UTF-8")) ?>
                            </div>
                        </div>

                        <div>
                            <div class="info-label">Driver Penanggung Jawab</div>

                            <?php if (!empty($item["nama_driver"])): ?>
                                <div class="driver-info-panel">
                                    <div class="driver-profile">
                                        <div class="driver-profile-left">
                                            <div class="driver-avatar-mini">
                                                <?= strtoupper(substr($item["nama_driver"], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="driver-name">
                                                    <?= htmlspecialchars($item["nama_driver"], ENT_QUOTES, "UTF-8") ?>
                                                </div>
                                                <div class="driver-sub">● Siap Menjalankan Tugas</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TOMBOL WHATSAPP UNTUK KOMUNIKASI -->
                                    <div style="margin-top: 12px;">
                                        <?php if (!empty($waNumber)): ?>
                                            <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2M12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19.02L7.55 18.84L4.44 19.66L5.27 16.63L5.07 16.31C4.24 15 3.8 13.48 3.8 11.91C3.8 7.37 7.5 3.67 12.05 3.67M9.53 7.5C9.36 7.5 9.09 7.57 8.87 7.8C8.65 8.04 8.04 8.61 8.04 9.77C8.04 10.93 8.88 12.05 9 12.21C9.12 12.38 10.66 14.75 13.03 15.77C13.6 16.02 14.04 16.16 14.38 16.27C14.95 16.45 15.47 16.43 15.88 16.36C16.34 16.3 17.3 15.79 17.5 15.22C17.7 14.66 17.7 14.18 17.64 14.07C17.58 13.97 17.43 13.91 17.2 13.8C16.97 13.68 15.86 13.14 15.65 13.06C15.45 12.97 15.3 12.93 15.15 13.16C15 13.38 14.56 13.91 14.43 14.07C14.29 14.22 14.16 14.24 13.93 14.13C13.71 14.01 12.98 13.78 12.12 13.01C11.45 12.41 11 11.67 10.87 11.45C10.74 11.22 10.86 11.1 10.97 10.98C11.08 10.88 11.21 10.71 11.33 10.57C11.45 10.43 11.49 10.32 11.56 10.17C11.64 10.03 11.6 9.9 11.54 9.79C11.49 9.68 11.04 8.57 10.85 8.12C10.67 7.68 10.49 7.74 10.35 7.73C10.22 7.73 10.07 7.72 9.92 7.72C9.77 7.72 9.53 7.5 9.53 7.5Z"/>
                                                </svg>
                                                Chat WhatsApp Driver
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #64748b; display: block; margin-top: 6px;">
                                                ℹ️ Kontak WhatsApp belum disetel oleh driver
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="driver-missing-box">
                                    <div class="driver-missing-text">
                                        Belum ada driver yang dipilih.
                                    </div>

                                    <?php if ($canCancel): ?>
                                        <a href="pilih_driver.php?pesanan_id=<?= (int) $item["id"] ?>" class="btn-pick-driver-now">
                                            🛵 Pilih Driver Sekarang
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ========================================================
                         METODE PEMBAYARAN QRIS JIKA PESANAN SUDAH DITERIMA DRIVER
                    ======================================================== -->
                    <?php if ($status === "Menunggu Pembayaran"): ?>
                        <?php
                            $isCashPayment = ($item["metode_bayar"] ?? "qris") === "cash";
                            $driverQrisFile = $item["foto_qris_driver"] ?? "";
                            $hasPersonalQris = !empty($driverQrisFile) && file_exists(__DIR__ . "/uploads/qris/" . $driverQrisFile);
                            $qrisImageSrc = $hasPersonalQris ? "uploads/qris/" . htmlspecialchars($driverQrisFile) : "assets/img/qris.jpeg";
                            $driverDisplayName = !empty($item["nama_driver"]) ? htmlspecialchars($item["nama_driver"]) : "Driver";
                            $isRejected = ((int)($item["qris_diverifikasi"] ?? 0) === 2);
                        ?>

                        <?php if ($isCashPayment): ?>
                            <!-- BLOK CASH PAYMENT -->
                            <div class="qris-payment-box" style="background: linear-gradient(135deg, #fffbeb 0%, #fefce8 100%); border: 1px solid #fde68a;">
                                <div class="qris-box-header">
                                    <span style="font-size: 24px;">💵</span>
                                    <div>
                                        <h4>Bayar Cash ke Driver <?= $driverDisplayName ?>!</h4>
                                        <span style="font-size: 13px; color: #92400e; font-weight: 600;">
                                            Siapkan uang tunai sesuai kesepakatan dengan driver.
                                        </span>
                                    </div>
                                </div>

                                <div style="margin-top: 14px; padding: 14px 16px; background: #fff; border-radius: 12px; border: 1px solid #fde68a; font-size: 13px; line-height: 1.8; color: #78350f;">
                                    <div style="margin-bottom: 8px; font-weight: 800; font-size: 14px; color: #92400e;">📋 Tata Cara Bayar Cash:</div>
                                    <ol style="margin: 0; padding-left: 18px;">
                                        <li><strong>Chat driver</strong> via WhatsApp untuk negosiasi harga & detail pesanan.</li>
                                        <li>Driver akan mengambil/mengerjakan pesananmu terlebih dahulu.</li>
                                        <li><strong>Siapkan uang tunai</strong> sesuai kesepakatan saat driver tiba.</li>
                                        <li>Bayar langsung kepada driver setelah pesanan diterima.</li>
                                    </ol>
                                </div>

                                <?php if (!empty($waNumber)): ?>
                                    <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer"
                                       style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 14px; padding: 12px; background: #25d366; color: #fff; border-radius: 12px; font-weight: bold; font-size: 13px; text-decoration: none; transition: opacity 0.2s;"
                                       onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                        💬 Chat Driver untuk Negosiasi Harga
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>

                        <!-- PERINGATAN JIKA PEMBAYARAN SEBELUMNYA DITOLAK DRIVER -->
                        <?php if ($isRejected): ?>
                            <div class="qris-rejected-alert">
                                <div class="rejected-alert-icon">❌</div>
                                <div class="rejected-alert-content">
                                    <h4>Pembayaran QRIS Ditolak oleh Driver <?= $driverDisplayName ?>!</h4>
                                    <div class="rejected-reason">
                                        <strong>Alasan Penolakan:</strong> "<?= htmlspecialchars($item["alasan_tolak_bayar"] ?: "Bukti transfer tidak sesuai atau saldo belum masuk.") ?>"
                                    </div>
                                    <p class="rejected-instruction">
                                        Silakan lakukan pembayaran ulang melalui kode QRIS di bawah ini dengan nominal yang benar, lalu unggah bukti transfer yang asli & sah agar pesananmu dapat segera diproses.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- MIDTRANS INTEGRATION CARD -->
                        <?php if ($paymentStatus === "paid"): ?>
                            <div style="margin-top: 18px; padding: 18px 20px; background: #f0fdf4; border: 2px solid #86efac; border-radius: 14px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="font-size: 28px;">✅</span>
                                    <div>
                                        <h4 style="margin: 0; color: #166534; font-size: 16px; font-weight: 800;">
                                            Pembayaran Lunas via Midtrans (Rp <?= number_format((int)($item["total_harga"] ?? 0), 0, ',', '.') ?>)
                                        </h4>
                                        <p style="margin: 4px 0 0; font-size: 13px; color: #15803d; line-height: 1.5;">
                                            Pembayaran telah diverifikasi secara otomatis oleh sistem. Driver dapat langsung memproses pesanan Anda.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (!empty($snapToken)): ?>
                            <div class="qris-payment-box" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #93c5fd; margin-top: 18px;">
                                <div class="qris-box-header">
                                    <span style="font-size: 26px;">💳</span>
                                    <div>
                                        <h4 style="color: #1e40af; font-size: 16px;">Selesaikan Pembayaran via Midtrans</h4>
                                        <span style="font-size: 13px; color: #1d4ed8; font-weight: 700;">
                                            Total Tagihan: Rp <?= number_format((int)($item["total_harga"] ?? 0), 0, ',', '.') ?>
                                        </span>
                                    </div>
                                </div>
                                <p class="qris-desc" style="color: #1e3a8a; margin-bottom: 14px;">
                                    Klik tombol di bawah ini untuk membuka pop-up pembayaran resmi Midtrans (mendukung QRIS, GoPay, ShopeePay, dan Virtual Account):
                                </p>
                                <button type="button" 
                                        onclick="bayarMidtrans('<?= htmlspecialchars($snapToken) ?>', <?= (int)$item['id'] ?>, <?= (int)($item['total_harga'] ?? 0) ?>)"
                                        style="padding: 13px 24px; background: #2563eb; color: #fff; border: none; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                                    <span>⚡ Buka Snap Pop-up Midtrans</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="qris-payment-box" style="margin-top: 14px; opacity: 0.95;">
                            <div class="qris-box-header">
                                <span style="font-size: 20px;">ℹ️</span>
                                <div>
                                    <h4 style="font-size: 14px; color: #334155;">Opsi Alternatif: Transfer QRIS Manual Driver</h4>
                                    <span style="font-size: 12px; color: #64748b;">
                                        Jika ingin membayar langsung melalui scan manual QRIS driver:
                                    </span>
                                </div>
                            </div>

                            <p class="qris-desc">
                                <?php if ($hasPersonalQris): ?>
                                    Driver <strong><?= $driverDisplayName ?></strong> telah menyetujui pesananmu. Scan kode QRIS driver di bawah ini menggunakan aplikasi e-wallet atau mobile banking favoritmu untuk menyelesaikan pembayaran.
                                <?php else: ?>
                                    Driver <strong><?= $driverDisplayName ?></strong> telah menyetujui pesananmu. Scan kode QRIS di bawah ini menggunakan aplikasi e-wallet atau mobile banking favoritmu untuk menyelesaikan pembayaran.
                                <?php endif; ?>
                            </p>

                            <div class="qris-content-grid">
                                <div class="qris-image-wrapper">
                                    <img src="<?= $qrisImageSrc ?>" alt="QRIS <?= $hasPersonalQris ? 'Driver ' . $driverDisplayName : 'Polindra Suruh' ?>">
                                    <div style="margin-top: 10px; font-size: 12px; font-weight: bold; padding: 5px 12px; border-radius: 8px; display: inline-block; <?= $hasPersonalQris ? 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;' : 'background: #f1f5f9; color: #475569;' ?>">
                                        <?= $hasPersonalQris ? '💳 QRIS: ' . $driverDisplayName : 'ℹ️ QRIS Polindra Suruh' ?>
                                    </div>
                                </div>

                                <div class="qris-instructions">
                                    <ul class="qris-steps">
                                        <li>
                                            <span class="step-num">!</span>
                                            <span>SEBELUM MELAKUKAN TRANSAKSI SILAHKAN CHAT DRIVER TERLEBIH DAHULU UNTUK MELAKUKAN NEGOISASI</span>
                                       
                                    </ul>

                                    <form method="POST" action="pesanan_saya.php" enctype="multipart/form-data">
                                        <input type="hidden" name="konfirmasi_bayar" value="1">
                                        <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">

                                        <div class="upload-bukti-box">
                                            <label class="upload-bukti-label">
                                                📸 <strong><?= $isRejected ? 'Unggah Bukti Transfer Baru / Sah:' : 'Unggah Bukti Pembayaran ' ?></strong>
                                            </label>
                                            <input 
                                                type="file" 
                                                name="bukti_bayar" 
                                                accept="image/png, image/jpeg, image/jpg, image/webp" 
                                                class="file-input-bukti"
                                                onchange="previewBukti(this, 'preview_user_<?= (int) $item['id'] ?>')"
                                            >
                                            <div id="preview_user_<?= (int) $item['id'] ?>" class="bukti-upload-preview" style="display: none;"></div>
                                           
                                        </div>

                                        <button
                                            type="submit"
                                            class="btn-konfirmasi-qris"
                                            onclick="return confirm('Apakah Anda sudah selesai melakukan pembayaran melalui QRIS?');"
                                        >
                                            <?= $isRejected ? '🔄 Kirim Ulang Bukti Pembayaran (QRIS)' : '✅ Konfirmasi Sudah Bayar (QRIS)' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; // end $isCashPayment ?>
                    <?php elseif ($status === "Diproses"): ?>
                        <?php
                            $userHasBukti = !empty($item["bukti_bayar"]) && file_exists(__DIR__ . "/uploads/bukti_bayar/" . $item["bukti_bayar"]);
                            $userBuktiSrc = $userHasBukti ? "uploads/bukti_bayar/" . htmlspecialchars($item["bukti_bayar"]) : "";
                            $isDriverVerified = ((int)($item["qris_diverifikasi"] ?? 0) === 1);
                            $driverDisplayName = !empty($item["nama_driver"]) ? htmlspecialchars($item["nama_driver"]) : "Driver";
                        ?>
                        <?php if ($isDriverVerified): ?>
                            <div class="qris-status-banner qris-status-banner-verified">
                                <span style="font-size: 26px;">🎉</span>
                                <div>
                                    <strong style="font-size: 14px;">✅ Pembayaran QRIS Terbukti Sah & Diterima Driver!</strong>
                                    <p style="margin-top: 4px; color: #166534;">Driver <strong><?= $driverDisplayName ?></strong> telah memverifikasi bahwa pembayaran QRIS Anda sudah masuk. Pesanan sedang dipersiapkan dan segera diantar.</p>
                                    <?php if ($userHasBukti): ?>
                                        <button type="button" class="btn-lihat-bukti-user" onclick="openBuktiModal('<?= $userBuktiSrc ?>')">
                                            👁️ Lihat Bukti Transfer Anda
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="qris-status-banner qris-status-banner-pending">
                                <span style="font-size: 26px;">⏳</span>
                                <div>
                                    <strong style="font-size: 14px;">Pembayaran Terkonfirmasi - Menunggu Verifikasi Driver</strong>
                                    <p style="margin-top: 4px; color: #92400e;">Anda telah mengonfirmasi pembayaran QRIS. Driver <strong><?= $driverDisplayName ?></strong> sedang memeriksa mutasi saldo dan bukti transfer Anda.</p>
                                    <?php if ($userHasBukti): ?>
                                        <button type="button" class="btn-lihat-bukti-user" onclick="openBuktiModal('<?= $userBuktiSrc ?>')">
                                            👁️ Lihat Bukti Transfer Anda
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($status === "Dalam Perjalanan"): ?>
                        <?php
                            $userHasBuktiDP = !empty($item["bukti_bayar"]) && file_exists(__DIR__ . "/uploads/bukti_bayar/" . $item["bukti_bayar"]);
                        ?>
                        <div class="status-banner-info" style="background: #fefce8; border-color: #eab308; color: #854d0e;">
                            <span style="font-size: 24px;">🛵</span>
                            <div style="flex: 1;">
                                <strong>Sedang Diantar!</strong> Driver sedang dalam perjalanan menyelesaikan pesananmu. Pastikan HP-mu aktif.
                                <?php if (!empty($item["qris_diverifikasi"])): ?>
                                    <div style="margin-top: 4px; font-size: 12px; color: #15803d; font-weight: 600;">
                                        ✓ Pembayaran QRIS Telah Terverifikasi Sah oleh Driver
                                    </div>
                                <?php endif; ?>
                                <?php if ($userHasBuktiDP): ?>
                                    <button type="button" class="btn-lihat-bukti-user" style="margin-top: 6px;" onclick="openBuktiModal('uploads/bukti_bayar/<?= htmlspecialchars($item['bukti_bayar']) ?>')">
                                        👁️ Lihat Bukti Transfer
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-footer">
                        <div>
                            <?php if ($canCancel): ?>
                                <button type="button" class="btn-edit-pesanan" onclick="openEditModal(<?= (int) $item['id'] ?>, '<?= htmlspecialchars(addslashes(str_replace(["\r", "\n"], ["", "\\n"], $item['catatan'] ?? '')), ENT_QUOTES) ?>')">
                                    ✏️ Edit Pesanan
                                </button>
                                <form method="POST" action="pesanan_saya.php" onsubmit="return confirm('Apakah kamu yakin ingin membatalkan Pesanan #<?= (int) $item['id'] ?>?');" style="display: inline;">
                                    <input type="hidden" name="batal_pesanan" value="1">
                                    <input type="hidden" name="pesanan_id" value="<?= (int) $item["id"] ?>">
                                    <button type="submit" class="btn-batal">
                                        Batalkan Pesanan
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div>
                            <a href="order.php" class="btn-reorder">
                                ↺ Pesan Lagi
                            </a>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <!-- EMPTY STATE -->
        <div class="empty-state">
            <div class="empty-icon">🛵💨</div>
            <div class="empty-title">Belum Ada Pesanan</div>
            <p class="empty-desc">
                Kamu belum pernah membuat pesanan di Polindra Suruh.
                Lagi mager beli makan, butuh titip barang, atau bantuan tugas?
                Serahkan pada driver kami!
            </p>
            <a href="order.php" class="btn-order-new">
                + Buat Pesanan Pertama
            </a>
        </div>

    <?php endif; ?>

</main>

<!-- Modal Lightbox Bukti Transfer User -->
<div id="modalBukti" class="bukti-modal-backdrop" onclick="closeBuktiModal(event)">
    <div class="bukti-modal-content" onclick="event.stopPropagation()">
        <div class="bukti-modal-header">
            <h3>📸 Bukti Pembayaran QRIS Anda</h3>
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

<!-- Modal Edit Pesanan User -->
<div id="modalEditPesanan" class="bukti-modal-backdrop" onclick="closeEditModal(event)">
    <div class="bukti-modal-content" onclick="event.stopPropagation()">
        <div class="bukti-modal-header">
            <h3 style="margin: 0; font-size: 16px;">✏️ Edit Rincian Pesanan #<span id="labelEditId"></span></h3>
            <button type="button" class="bukti-modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="pesanan_saya.php">
            <input type="hidden" name="edit_pesanan" value="1">
            <input type="hidden" name="pesanan_id" id="editPesananId">
            <div style="padding: 20px; text-align: left;">
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">Catatan / Barang yang Disuruh</label>
                    <textarea name="catatan" id="editCatatan" rows="4" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; resize: vertical;" required></textarea>
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">Anda dapat mengubah rincian pesanan selama driver belum menyetujui pesanan ini.</small>
                </div>
            </div>
            <div class="bukti-modal-footer">
                <button type="button" class="btn-close-modal" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn-konfirmasi-qris" style="padding: 9px 18px; font-size: 13px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, catatan) {
    var modal = document.getElementById('modalEditPesanan');
    if (!modal) return;
    document.getElementById('editPesananId').value = id;
    document.getElementById('labelEditId').innerText = id;
    document.getElementById('editCatatan').value = catatan;
    modal.classList.add('active');
}

function closeEditModal(event) {
    var modal = document.getElementById('modalEditPesanan');
    if (modal) {
        modal.classList.remove('active');
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

function previewBukti(input, previewId) {
    var preview = document.getElementById(previewId);
    if (!preview) return;
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
}

<!-- MODAL SIMULASI MIDTRANS SNAP SANDBOX SEAMLESS -->
<div id="modalSnapSimulator" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); backdrop-filter:blur(5px); z-index:9999; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">
    <div style="background:#fff; width:100%; max-width:460px; border-radius:20px; box-shadow:0 20px 40px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="background:#f8fafc; padding:18px 22px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="background:#002554; color:#fff; font-weight:900; font-size:12px; padding:4px 8px; border-radius:6px;">MIDTRANS</div>
                <div style="font-weight:800; font-size:14px; color:#1e293b;">Snap Pop-Up Sandbox</div>
            </div>
            <button type="button" onclick="closeSnapModalSaya()" style="background:transparent; border:none; font-size:24px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <div style="padding:24px; text-align:center;">
            <div style="font-size:12px; color:#64748b; font-weight:700;">TOTAL PEMBAYARAN</div>
            <div id="snap-saya-price" style="font-size:24px; font-weight:900; color:#0f172a; margin:4px 0 10px;">Rp -</div>
            <div style="border:2px dashed #cbd5e1; border-radius:16px; padding:18px; background:#f8fafc; margin:14px 0; display:flex; flex-direction:column; align-items:center;">
                <div style="font-size:12px; font-weight:800; color:#1e293b; margin-bottom:8px;">Scan QRIS via GoPay / BCA / DANA / OVO</div>
                <img src="assets/img/qris.jpeg" alt="Midtrans QRIS" style="width:170px; height:170px; object-fit:contain; border-radius:10px; background:#fff; padding:6px; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                <div style="font-size:11px; color:#64748b; margin-top:8px;">NMID: ID1020030040050 • Sandbox Simulator</div>
            </div>
            <button type="button" id="btnSnapConfirmSaya" onclick="confirmSimulatedPaymentSaya()" style="width:100%; padding:14px; border:none; border-radius:12px; background:#10b981; color:#fff; font-weight:800; font-size:15px; cursor:pointer;">
                ⚡ Bayar Sekarang (Simulasi Berhasil)
            </button>
        </div>
    </div>
</div>

<script>
let activeSnapPesananId = 0;

function openSnapModalSaya(pesananId, totalHarga) {
    activeSnapPesananId = pesananId;
    document.getElementById('snap-saya-price').innerText = totalHarga ? ('Rp ' + Number(totalHarga).toLocaleString('id-ID')) : '';
    document.getElementById('modalSnapSimulator').style.display = 'flex';
}

function closeSnapModalSaya() {
    document.getElementById('modalSnapSimulator').style.display = 'none';
}

function confirmSimulatedPaymentSaya() {
    if (activeSnapPesananId <= 0) return;
    document.getElementById('btnSnapConfirmSaya').innerText = '⏳ Memverifikasi Pembayaran...';
    
    fetch('update_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pesanan_id: activeSnapPesananId, payment_status: 'paid' })
    })
    .then(res => res.json())
    .then(res => {
        window.location.href = 'pesanan_saya.php?id=' + activeSnapPesananId + '&pesan=sukses_bayar';
    })
    .catch(() => {
        window.location.reload();
    });
}

function bayarMidtrans(snapToken, pesananId, totalHarga = 0) {
    activeSnapPesananId = pesananId;
    const isMock = !snapToken || snapToken.startsWith('SNAP-SANDBOX');

    if (typeof window.snap !== 'undefined' && snapToken && !isMock) {
        window.snap.pay(snapToken, {
            onSuccess: function(result) {
                fetch('update_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pesanan_id: pesananId, payment_status: 'paid' })
                }).finally(() => {
                    window.location.href = 'pesanan_saya.php?id=' + pesananId + '&pesan=sukses_bayar';
                });
            },
            onPending: function(result) {
                window.location.href = 'pesanan_saya.php?id=' + pesananId + '&pesan=menunggu_bayar';
            },
            onError: function(result) {
                alert('Pembayaran Midtrans gagal atau dibatalkan.');
                window.location.reload();
            },
            onClose: function() {
                window.location.reload();
            }
        });
    } else {
        openSnapModalSaya(pesananId, totalHarga);
    }
}

<?php if (isset($_GET['pay_snap']) && $highlightId > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php
            $targetToken = '';
            $targetPrice = 0;
            foreach ($pesananList as $pl) {
                if ((int)$pl['id'] === $highlightId) {
                    $targetToken = $pl['snap_token'] ?? '';
                    $targetPrice = (int)($pl['total_harga'] ?? 0);
                    break;
                }
            }
        ?>
        const autoToken = '<?= htmlspecialchars($targetToken) ?>';
        const autoPrice = <?= (int)$targetPrice ?>;
        bayarMidtrans(autoToken, <?= (int)$highlightId ?>, autoPrice);
    });
<?php endif; ?>

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBuktiModal();
        closeEditModal();
    }
});
</script>
</body>
</html>

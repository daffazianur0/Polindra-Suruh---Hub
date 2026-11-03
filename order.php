<?php
session_start();
require_once "koneksi.php";
require_once "includes/Midtrans.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?redirect=order");
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

// Ambil info nama dan email user dari database
$stmtUser = $pdo->prepare("SELECT id, nama, email FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
$nama = $currentUser["nama"] ?? ($_SESSION["nama"] ?? "Pengguna");
$email = $currentUser["email"] ?? "user@polindrasuruh.ac.id";

$catatan = "";
$error = "";

// Cek apakah ada driver_id dari query string atau form
$driverId = isset($_GET["driver_id"]) ? (int) $_GET["driver_id"] : (isset($_POST["driver_id"]) && $_POST["driver_id"] !== "" ? (int) $_POST["driver_id"] : null);
$driverTerpilih = null;

if ($driverId) {
    $stmtDriver = $pdo->prepare("SELECT id, nama FROM users WHERE id = ? AND role = 'driver' AND status_aktif = 1");
    $stmtDriver->execute([$driverId]);
    $driverTerpilih = $stmtDriver->fetch(PDO::FETCH_ASSOC);
    if (!$driverTerpilih) {
        $driverId = null;
    }
}

// =========================================================================
// HANDLER ORDER (AJAX & STANDARD POST)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $isAjax = isset($_POST["ajax_order"]) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $catatan = trim($_POST["catatan"] ?? "");
    $origin = trim($_POST["origin_location"] ?? "");
    $destination = trim($_POST["destination_location"] ?? "");
    
    $originLat = !empty($_POST["origin_lat"]) ? (float) $_POST["origin_lat"] : null;
    $originLng = !empty($_POST["origin_lng"]) ? (float) $_POST["origin_lng"] : null;
    $destLat = !empty($_POST["destination_lat"]) ? (float) $_POST["destination_lat"] : null;
    $destLng = !empty($_POST["destination_lng"]) ? (float) $_POST["destination_lng"] : null;
    
    $distanceKm = isset($_POST["distance_km"]) ? (float) $_POST["distance_km"] : 0.0;
    
    // Hitung ulang tarif ongkir di server untuk keamanan sesuai rumus PRD 3.0
    $totalHarga = hitungOngkir($distanceKm);
    
    $metodeBayar = in_array($_POST["metode_bayar"] ?? "", ["qris", "cash"]) ? $_POST["metode_bayar"] : "qris";
    $postedDriverId = !empty($_POST["driver_id"]) ? (int) $_POST["driver_id"] : null;

    if ($catatan === "" || $origin === "" || $destination === "") {
        $errorMsg = "Catatan, lokasi penjemputan, dan lokasi tujuan wajib diisi!";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => $errorMsg]);
            exit;
        }
        $error = $errorMsg;
    } else {
        try {
            // Status pesanan awal:
            // Jika Cash/COD -> langsung 'Menunggu Driver' dan payment_status = 'cod'
            // Jika QRIS/Midtrans -> status = 'Menunggu Pembayaran' dan payment_status = 'pending'
            $statusPesanan = ($metodeBayar === "cash") ? "Menunggu Driver" : "Menunggu Pembayaran";
            $paymentStatus = ($metodeBayar === "cash") ? "cod" : "pending";

            // Siapkan Token Midtrans Snap terlebih dahulu jika metode bayar QRIS
            $snapToken = null;
            $isMockToken = false;

            if ($metodeBayar === "qris") {
                $tempOrderId = "PS-" . time() . "-" . rand(100, 999);
                $params = [
                    'transaction_details' => [
                        'order_id' => $tempOrderId,
                        'gross_amount' => (int) $totalHarga,
                    ],
                    'customer_details' => [
                        'first_name' => $nama,
                        'email' => $email,
                    ],
                    'item_details' => [
                        [
                            'id' => 'ONGKIR',
                            'price' => (int) $totalHarga,
                            'quantity' => 1,
                            'name' => 'Ongkir Polindra Suruh (' . number_format($distanceKm, 1) . ' KM)'
                        ]
                    ]
                ];

                $snapResult = Midtrans::createSnapToken($params);
                $snapToken = $snapResult['token'] ?? null;
                $isMockToken = !empty($snapResult['is_mock']);
            }

            // Simpan pesanan ke database MySQL
            $stmt = $pdo->prepare("
                INSERT INTO pesanan
                    (user_id, driver_id, layanan, alamat, origin_location, origin_lat, origin_lng, destination_location, destination_lat, destination_lng, distance_km, total_harga, catatan, metode_bayar, status, payment_status, snap_token)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $postedDriverId,
                "Jasa Suruh",
                $destination,
                $origin,
                $originLat,
                $originLng,
                $destination,
                $destLat,
                $destLng,
                $distanceKm,
                $totalHarga,
                $catatan,
                $metodeBayar,
                $statusPesanan,
                $paymentStatus,
                $snapToken
            ]);

            $pesananId = (int) $pdo->lastInsertId();

            if ($metodeBayar === "qris") {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        "success" => true,
                        "pesanan_id" => $pesananId,
                        "snap_token" => $snapToken,
                        "is_mock" => $isMockToken,
                        "is_qris" => true,
                        "total_harga" => (int) $totalHarga,
                        "redirect_url" => "pesanan_saya.php?id=" . $pesananId
                    ]);
                    exit;
                }

                header("Location: pesanan_saya.php?id=" . $pesananId . "&pay_snap=1");
                exit;
            } else {
                // JIKA CASH / COD
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        "success" => true,
                        "pesanan_id" => $pesananId,
                        "is_qris" => false,
                        "redirect_url" => "pesanan_saya.php?id=" . $pesananId . "&pesan=sukses"
                    ]);
                    exit;
                }

                header("Location: pesanan_saya.php?id=" . $pesananId . "&pesan=sukses");
                exit;
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errorMsg = "Pesanan gagal disimpan: " . $e->getMessage();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(["success" => false, "message" => $errorMsg]);
                exit;
            }
            $error = $errorMsg;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan & Rute Presisi - Polindra Suruh</title>
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- GOOGLE FONTS (PLUS JAKARTA SANS) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FONT AWESOME 6 (ICONS) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- MIDTRANS SNAP JS SDK (SANDBOX) -->
    <script 
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars(GOOGLE_MAPS_API_KEY) ?>&libraries=places&callback=initGoogleMap"
    async defer
    onerror="handleMapsLoadError(event)">
</script>
<script>
/* Flag indicating if Google Maps API loaded successfully */
let mapsApiLoaded = false;

/* Preserve original initGoogleMap if it exists */
if (typeof initGoogleMap === 'function') {
    window._originalInitGoogleMap = initGoogleMap;
}
function initGoogleMap() {
    if (window._originalInitGoogleMap) {
        window._originalInitGoogleMap();
    }
    mapsApiLoaded = true;
}

/* Auth failure callback (invalid key / quota) */
window.gm_authFailure = function() {
    console.warn('Google Maps Auth Failure – possible invalid key or quota exceeded.');
    handleMapsLoadError(new Error('Auth failure'));
};

/* Global error handling for script load failure */
function handleMapsLoadError(event) {
    console.warn('Google Maps API failed to load.', event);
    mapsApiLoaded = false;
    if (typeof renderFallbackInteractiveMap === 'function') {
        renderFallbackInteractiveMap();
    }
    disableAutocomplete();
    const form = document.getElementById('orderForm');
    if (form) {
        const notice = document.createElement('div');
        notice.style.cssText = "background:#fffbeb;color:#92400e;padding:10px;margin-top:12px;border:1px solid #fde68a;border-radius:8px;text-align:center;";
        notice.innerHTML = '⚠️ Google Maps tidak dapat diakses. Anda dapat mengisi lokasi secara manual.';
        form.parentNode.insertBefore(notice, form);
    }
}

/* Disable Places Autocomplete when API unavailable */
function disableAutocomplete() {
    const origin = document.getElementById('origin_location');
    const dest = document.getElementById('destination_location');
    if (origin) origin.removeAttribute('autocomplete');
    if (dest) dest.removeAttribute('autocomplete');
}
</script>
        src="<?= htmlspecialchars(MIDTRANS_SNAP_URL) ?>" 
        data-client-key="<?= htmlspecialchars(MIDTRANS_CLIENT_KEY) ?>">
    </script>

    <style>
        .order-page-container {
            max-width: 860px;
            margin: 35px auto 70px;
            padding: 0 20px;
        }

        .subtitle {
            color: #111;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 2px;
            display: inline-block;
            background: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 12px;
        }

        h1 {
            margin: 8px 0 14px;
            font-size: clamp(26px, 4.5vw, 40px);
            font-weight: 900;
            color: #111;
        }

        .description {
            color: #333;
            line-height: 1.7;
            font-size: 15px;
            max-width: 680px;
        }

        .card {
            background: rgba(255, 255, 255, 0.96);
            padding: 32px;
            margin-top: 25px;
            border-radius: 22px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.7);
        }

        .driver-info-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .driver-assigned { background: #eff6ff; border: 1px solid #bfdbfe; }
        .driver-unassigned { background: #fefce8; border: 1px solid #fef08a; }

        .driver-detail-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .driver-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: #ffd21f;
            color: #111;
            font-weight: bold;
        }

        .btn-driver-action {
            display: inline-block;
            padding: 7px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-change { background: #fff; color: #2563eb; border: 1px solid #cbd5e1; }
        .btn-pick { background: #111; color: #fff; }

        label {
            display: block;
            margin: 16px 0 6px;
            font-weight: bold;
            font-size: 14px;
            color: #1e293b;
        }

        /* ========================================================
           INPUT LOKASI & FONT AWESOME / BOOTSTRAP ICONS WRAPPER
        ======================================================== */
        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            margin-top: 6px;
        }

        .input-icon-addon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            font-size: 14px;
            pointer-events: none;
            z-index: 5;
            transition: all 0.2s ease;
        }

        .input-icon-addon.origin-addon {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .input-icon-addon.dest-addon {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .form-control-location {
            width: 100% !important;
            padding: 14px 44px 14px 54px !important; /* Ruang aman presisi agar teks tidak bertumpuk dengan ikon */
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 14px !important;
            font-size: 14.5px !important;
            font-family: inherit !important;
            background-color: #ffffff !important;
            background-image: none !important; /* Mencegah ikon background ganda yang di-inject Google Autocomplete */
            box-sizing: border-box !important;
            line-height: 1.5 !important;
            color: #0f172a !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
        }

        .form-control-location:focus {
            outline: none !important;
            border-color: #f7b500 !important;
            box-shadow: 0 0 0 3px rgba(247, 181, 0, 0.25) !important;
            background-color: #ffffff !important;
        }

        .btn-clear-input {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #f1f5f9;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 11px;
            cursor: pointer;
            z-index: 5;
            transition: background 0.2s, color 0.2s;
        }

        .btn-clear-input:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* OVERRIDE GOOGLE PLACES AUTOCOMPLETE AGAR RAMPING & TIDAK MERUSAK UI */
        .pac-target-input {
            background-image: none !important;
        }

        .pac-container {
            background-color: #ffffff !important;
            border-radius: 14px !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid #e2e8f0 !important;
            font-family: inherit !important;
            z-index: 99999 !important;
            margin-top: 8px !important;
            padding: 6px 0 !important;
            overflow: hidden !important;
        }

        .pac-item {
            padding: 10px 16px !important;
            font-size: 13.5px !important;
            color: #334155 !important;
            cursor: pointer !important;
            border-top: 1px solid #f8fafc !important;
            display: flex !important;
            align-items: center !important;
            line-height: 1.4 !important;
            transition: background 0.15s ease !important;
        }

        .pac-item:hover, .pac-item-selected {
            background-color: #fefce8 !important;
        }

        .pac-item-query {
            font-size: 14px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            padding-right: 6px !important;
        }

        .pac-icon {
            margin-top: 0 !important;
            margin-right: 12px !important;
            flex-shrink: 0 !important;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #f7b500;
            box-shadow: 0 0 0 3px rgba(247, 181, 0, 0.25);
        }

        textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            min-height: 110px;
            resize: vertical;
        }

        /* ELEMEN GOOGLE MAPS INTERAKTIF & ANIMASI POLYLINES */
        #map-wrapper {
            margin: 22px 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
            position: relative;
            background: #e5e7eb;
        }

        #map {
            width: 100%;
            height: 380px;
            display: block;
        }

        .map-floating-overlay {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(8px);
            padding: 9px 15px;
            border-radius: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
            border: 1px solid rgba(226, 232, 240, 0.8);
            pointer-events: none;
        }

        .map-badge-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulseDot 1.5s infinite;
        }

        @keyframes pulseDot {
            0% { transform: scale(0.9); opacity: 0.7; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.7; }
        }

        /* KOTAK ESTIMASI JARAK & BIAYA */
        .estimation-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #e2e8f0;
            padding: 20px;
            border-radius: 16px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .estimation-box.active {
            background: linear-gradient(135deg, #fefce8 0%, #fef08a 100%);
            border-color: #facc15;
        }

        .estimation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-top: 12px;
        }

        .estimation-item {
            background: #ffffff;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .estimation-item-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estimation-item-value {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            margin-top: 4px;
        }

        /* METODE BAYAR */
        .metode-bayar-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }

        .metode-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .metode-card input[type="radio"] { display: none; }

        .metode-card.selected {
            border-color: #f7b500;
            background: #fffbeb;
            box-shadow: 0 0 0 3px rgba(247, 181, 0, 0.2);
        }

        .metode-icon { font-size: 28px; line-height: 1; }
        .metode-info strong { display: block; font-size: 14px; color: #0f172a; font-weight: 800; }
        .metode-info span { font-size: 11px; color: #64748b; font-weight: 500; }
        .metode-check { font-size: 18px; color: #f7b500; font-weight: 900; opacity: 0; transition: opacity 0.2s; }
        .metode-card.selected .metode-check { opacity: 1; }

        .btn-submit {
            width: 100%;
            margin-top: 25px;
            padding: 17px;
            border: none;
            border-radius: 14px;
            background: #111;
            color: white;
            font-weight: 800;
            cursor: pointer;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover:not(:disabled) {
            background: #222;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-submit:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        /* MODAL POPUP SIMULASI SNAP MIDTRANS SEAMLESS */
        .snap-modal-backdrop {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            animation: fadeIn 0.3s ease;
        }

        .snap-modal-box {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        .snap-modal-header {
            background: #f8fafc;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .snap-close-btn {
            background: transparent;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            line-height: 1;
        }

        .snap-modal-body {
            padding: 24px;
            text-align: center;
        }

        .snap-qris-card {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 20px;
            background: #f8fafc;
            margin: 16px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .snap-qris-card img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            border-radius: 10px;
            background: #fff;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .btn-snap-confirm {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #10b981;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn-snap-confirm:hover { background: #059669; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 600px) {
            .metode-bayar-wrapper { grid-template-columns: 1fr; }
            .order-page-container { margin: 20px auto 50px; }
            .card { padding: 20px; }
            #map { height: 300px; }
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
        <a href="pesanan_saya.php" class="login-btn">PESANAN SAYA</a>
        <a href="logout.php" class="login-btn">LOGOUT</a>
    </div>
</nav>

<main class="order-page-container">
    <div class="subtitle">BUAT PESANAN PRESISI</div>

    <h1>Mau suruh apa hari ini?</h1>

    <p class="description">
        Pilih titik lokasi penjemputan dan tujuan dengan Google Places Autocomplete. Rute mengemudi dan ongkir dihitung otomatis dengan visual animasi polyline real-time.
    </p>

    <div class="card">

        <?php if ($error !== ""): ?>
            <div style="padding: 14px 18px; border-radius: 12px; background: #fee2e2; color: #b91c1c; margin-bottom: 20px; font-weight: 600;">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <!-- BOX STATUS DRIVER -->
        <?php if ($driverTerpilih): ?>
            <div class="driver-info-box driver-assigned">
                <div class="driver-detail-left">
                    <div class="driver-icon-badge">
                        <?= strtoupper(substr($driverTerpilih["nama"], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">Driver Terpilih:</div>
                        <div style="font-size: 15px; font-weight: bold; color: #0f172a;">
                            <?= htmlspecialchars($driverTerpilih["nama"], ENT_QUOTES, "UTF-8") ?>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="pilih_driver.php" class="btn-driver-action btn-change">
                        Ganti Driver
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="driver-info-box driver-unassigned">
                <div class="driver-detail-left">
                    <div class="driver-icon-badge">🛵</div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">Driver Spesifik:</div>
                        <div style="font-size: 15px; font-weight: bold; color: #0f172a;">Belum Dipilih (Masuk ke Pool Semua Driver)</div>
                    </div>
                </div>
                <div>
                    <a href="pilih_driver.php" class="btn-driver-action btn-pick">
                        + Pilih Driver Sekarang
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <form id="orderForm" method="POST" action="order.php">

            <input type="hidden" name="driver_id" value="<?= $driverId ? (int) $driverId : '' ?>">

            <label for="nama">Nama Pemesan</label>
            <input
                type="text"
                id="nama"
                value="<?= htmlspecialchars($nama, ENT_QUOTES, "UTF-8") ?>"
                readonly
                style="background: #f8fafc; color: #475569; width: 100%; padding: 14px; border: 1px solid #cbd5e1; border-radius: 12px;"
            >

            <!-- LOKASI PENJEMPUTAN (GOOGLE PLACES AUTOCOMPLETE) -->
            <label for="origin_location">🟢 Lokasi Penjemputan / Asal (Google Places Autocomplete)</label>
            <div class="input-group-location">
                <span class="location-icon-prefix">📍</span>
                <input 
                    type="text" 
                    id="origin_location" 
                    name="origin_location" 
                    class="location-input"
                    placeholder="Ketik lokasi penjemputan (Contoh: Kampus Polindra / Kantin Polindra)" 
                    required
                    autocomplete="off"
                >
            </div>
            <input type="hidden" name="origin_lat" id="origin_lat" value="-6.4172">
            <input type="hidden" name="origin_lng" id="origin_lng" value="108.2831">

            <!-- LOKASI TUJUAN (GOOGLE PLACES AUTOCOMPLETE) -->
            <label for="destination_location">🔴 Lokasi Pengantaran / Tujuan (Google Places Autocomplete)</label>
            <div class="input-group-location">
                <span class="location-icon-prefix">🏁</span>
                <input 
                    type="text" 
                    id="destination_location" 
                    name="destination_location" 
                    class="location-input"
                    placeholder="Ketik lokasi tujuan (Contoh: Gedung Pengajaran Polindra / Kos Melati Indramayu)" 
                    required
                    autocomplete="off"
                >
            </div>
            <input type="hidden" name="destination_lat" id="destination_lat" value="-6.4100">
            <input type="hidden" name="destination_lng" id="destination_lng" value="108.2950">

            <!-- ELEMEN VISUAL INTERAKTIF GOOGLE MAPS DENGAN ANIMASI POLYLINE -->
            <div id="map-wrapper">
                <div class="map-floating-overlay">
                    <span class="map-badge-dot"></span>
                    <span id="map-status-text">🗺️ Visual Rute Polindra Suruh</span>
                </div>
                <div id="map"></div>
            </div>

            <!-- KOTAK ESTIMASI JARAK & BIAYA SESUAI RUMUS PRD -->
            <div class="estimation-box active" id="estimationBox">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; font-weight: 800; color: #475569;">
                        📊 ESTIMASI JARAK & ONGKIR TERKALKULASI
                    </span>
                    <span id="route-duration-text" style="font-size: 12px; font-weight: 700; color: #2563eb;">
                        ⚡ Durasi: ~5 Menit
                    </span>
                </div>

                <div class="estimation-grid">
                    <div class="estimation-item">
                        <div class="estimation-item-label">Jarak Tempuh</div>
                        <div class="estimation-item-value" id="val-distance">1.50 KM</div>
                    </div>
                    <div class="estimation-item">
                        <div class="estimation-item-label">Tarif Dasar</div>
                        <div class="estimation-item-value">Rp <?= number_format(BASE_FARE, 0, ',', '.') ?></div>
                    </div>
                    <div class="estimation-item">
                        <div class="estimation-item-label">Total Ongkir</div>
                        <div class="estimation-item-value" id="val-total" style="color: #16a34a;">Rp 7.000</div>
                    </div>
                </div>

                <div style="margin-top: 12px; font-size: 12px; color: #475569;">
                    💡 <em>Rumus: Base Fare (Rp 3.000) + (⌈Jarak KM⌉ × Rp 2.000)</em>
                </div>

                <input type="hidden" name="distance_km" id="distance_km" value="1.50">
                <input type="hidden" name="total_price" id="total_price" value="7000">
            </div>

            <label for="catatan">📝 Detail Pesanan / Titip Apa Saja?</label>
            <textarea
                name="catatan"
                id="catatan"
                maxlength="5000"
                placeholder="Contoh: Tolong belikan Es Teh Jumbo 2 cup dan Nasi Ayam Geprek level 3 di Kantin Belakang Polindra."
                required
            ><?= htmlspecialchars($catatan, ENT_QUOTES, "UTF-8") ?></textarea>

            <!-- PILIH METODE BAYAR -->
            <label style="margin-top: 22px;">💳 Metode Pembayaran</label>
            <div class="metode-bayar-wrapper">
                <label class="metode-card selected" id="card-qris">
                    <input type="radio" name="metode_bayar" value="qris" checked onchange="toggleMetodeBayar()">
                    <div class="metode-icon">📱</div>
                    <div class="metode-info">
                        <strong>QRIS / E-Wallet (Midtrans)</strong>
                        <span>Instan via QRIS, GoPay, ShopeePay, Virtual Account</span>
                    </div>
                    <div class="metode-check">✓</div>
                </label>

                <label class="metode-card" id="card-cash">
                    <input type="radio" name="metode_bayar" value="cash" onchange="toggleMetodeBayar()">
                    <div class="metode-icon">💵</div>
                    <div class="metode-info">
                        <strong>Cash / COD (Bayar di Tempat)</strong>
                        <span>Bayar langsung ke driver saat pesanan tiba</span>
                    </div>
                    <div class="metode-check">✓</div>
                </label>
            </div>

            <div id="cash-note" style="display:none; margin-top: 12px; padding: 12px 16px; background: #fef9c3; border: 1px solid #fde68a; border-radius: 12px; font-size: 13px; color: #92400e; font-weight: 600;">
                ⚠️ <strong>Metode COD:</strong> Harap siapkan uang pas saat driver mengantarkan barang pesanan.
            </div>

            <button type="submit" id="btnSubmitOrder" class="btn-submit">
                <span>LANJUTKAN PESANAN</span>
                <span>→</span>
            </button>

        </form>
    </div>
</main>

<!-- MODAL SIMULASI MIDTRANS SNAP SANDBOX SEAMLESS -->
<div id="modalSnapSimulator" class="snap-modal-backdrop" style="display:none;">
    <div class="snap-modal-box">
        <div class="snap-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="background:#002554; color:#fff; font-weight:900; font-size:12px; padding:4px 8px; border-radius:6px; letter-spacing:1px;">MIDTRANS</div>
                <div style="font-weight:800; font-size:14px; color:#1e293b;">Snap Pop-Up Sandbox</div>
            </div>
            <button type="button" class="snap-close-btn" onclick="closeSnapModal()">&times;</button>
        </div>
        <div class="snap-modal-body">
            <div style="font-size: 12px; color: #64748b; font-weight: 700;">TOTAL PEMBAYARAN</div>
            <div id="snap-modal-price" style="font-size: 24px; font-weight: 900; color: #0f172a; margin: 4px 0 10px;">Rp 7.000</div>
            
            <div class="snap-qris-card">
                <div style="font-size: 12px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Scan QRIS via GoPay / BCA / DANA / OVO</div>
                <img src="assets/img/qris.jpeg" alt="Midtrans QRIS">
                <div style="font-size: 11px; color: #64748b; margin-top: 8px;">NMID: ID1020030040050 • Sandbox Simulator</div>
            </div>

            <p style="font-size: 12px; color: #475569; line-height: 1.5;">
                Sistem mendeteksi transaksi Midtrans Sandbox. Klik tombol di bawah ini untuk mensimulasikan pembayaran berhasil secara instan dan otomatis sinkronisasi ke database:
            </p>

            <button type="button" class="btn-snap-confirm" onclick="confirmSimulatedPayment()">
                ⚡ Bayar Sekarang (Simulasi Berhasil)
            </button>
        </div>
    </div>
</div>

<!-- GOOGLE MAPS API JAVASCRIPT SDK -->
<script 
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars(GOOGLE_MAPS_API_KEY) ?>&libraries=places&callback=initGoogleMap"
    async defer
    onerror="handleMapsLoadError(event)">
</script>
<script>
/* Flag indicating if Google Maps API loaded successfully */
let mapsApiLoaded = false;

/* Preserve original initGoogleMap if it exists */
if (typeof initGoogleMap === 'function') {
    window._originalInitGoogleMap = initGoogleMap;
}
function initGoogleMap() {
    if (window._originalInitGoogleMap) {
        window._originalInitGoogleMap();
    }
    mapsApiLoaded = true;
}

/* Auth failure callback (invalid key / quota) */
window.gm_authFailure = function() {
    console.warn('Google Maps Auth Failure – possible invalid key or quota exceeded.');
    handleMapsLoadError(new Error('Auth failure'));
};

/* Global error handling for script load failure */
function handleMapsLoadError(event) {
    console.warn('Google Maps API failed to load.', event);
    mapsApiLoaded = false;
    if (typeof renderFallbackInteractiveMap === 'function') {
        renderFallbackInteractiveMap();
    }
    disableAutocomplete();
    const form = document.getElementById('orderForm');
    if (form) {
        const notice = document.createElement('div');
        notice.style.cssText = "background:#fffbeb;color:#92400e;padding:10px;margin-top:12px;border:1px solid #fde68a;border-radius:8px;text-align:center;";
        notice.innerHTML = '⚠️ Google Maps tidak dapat diakses. Anda dapat mengisi lokasi secara manual.';
        form.parentNode.insertBefore(notice, form);
    }
}

/* Disable Places Autocomplete when API unavailable */
function disableAutocomplete() {
    const origin = document.getElementById('origin_location');
    const dest = document.getElementById('destination_location');
    if (origin) origin.removeAttribute('autocomplete');
    if (dest) dest.removeAttribute('autocomplete');
}
</script>
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(GOOGLE_MAPS_API_KEY) ?>&libraries=places&callback=initGoogleMap" 
    async defer>
</script>

<script>
const BASE_FARE = <?= (int) BASE_FARE ?>;
const PER_KM_RATE = <?= (int) PER_KM_RATE ?>;

let mapInstance = null;
let originMarker = null;
let destMarker = null;
let activePolyline = null;
let directionsService = null;
let currentPendingOrderId = 0;

// Default Titik Kampus Politeknik Negeri Indramayu
const POLINDRA_COORDS = { lat: -6.4172, lng: 108.2831 };
const DEFAULT_DEST_COORDS = { lat: -6.4100, lng: 108.2950 };

/**
 * Update Tampilan Tarif Ongkir & Form Input
 */
function updateFareDisplay(distanceKm, durationText = null) {
    const km = Math.max(0.1, parseFloat(distanceKm) || 1.0);
    const kmCeil = Math.max(1, Math.ceil(km));
    const totalOngkir = BASE_FARE + (kmCeil * PER_KM_RATE);

    document.getElementById('val-distance').innerText = km.toFixed(2) + ' KM';
    document.getElementById('val-total').innerText = 'Rp ' + totalOngkir.toLocaleString('id-ID');
    document.getElementById('distance_km').value = km.toFixed(2);
    document.getElementById('total_price').value = totalOngkir;

    if (durationText) {
        document.getElementById('route-duration-text').innerText = '⚡ Durasi: ' + durationText;
    }
}

/**
 * Inisialisasi Google Maps & Places Autocomplete
 */
function initGoogleMap() {
    try {
        const mapDiv = document.getElementById('map');
        mapInstance = new google.maps.Map(mapDiv, {
            center: POLINDRA_COORDS,
            zoom: 15,
            mapTypeId: 'roadmap',
            disableDefaultUI: false,
            zoomControl: true,
            streetViewControl: false,
            mapTypeControl: false,
            styles: [
                { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }
            ]
        });

        directionsService = new google.maps.DirectionsService();

        // 🟢 Marker Penjemputan (Hijau)
        originMarker = new google.maps.Marker({
            position: POLINDRA_COORDS,
            map: mapInstance,
            title: "Titik Penjemputan (Asal)",
            icon: {
                url: "https://maps.google.com/mapfiles/ms/icons/green-dot.png",
                scaledSize: new google.maps.Size(42, 42)
            },
            draggable: true
        });

        // 🔴 Marker Pengantaran (Merah)
        destMarker = new google.maps.Marker({
            position: DEFAULT_DEST_COORDS,
            map: mapInstance,
            title: "Titik Pengantaran (Tujuan)",
            icon: {
                url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
                scaledSize: new google.maps.Size(42, 42)
            },
            draggable: true
        });

        // Listener Drag Marker
        originMarker.addListener('dragend', function(e) {
            document.getElementById('origin_lat').value = e.latLng.lat();
            document.getElementById('origin_lng').value = e.latLng.lng();
            calculateAndAnimateRoute();
        });

        destMarker.addListener('dragend', function(e) {
            document.getElementById('destination_lat').value = e.latLng.lat();
            document.getElementById('destination_lng').value = e.latLng.lng();
            calculateAndAnimateRoute();
        });

        // Inisialisasi Places Autocomplete Indonesia
        const originInput = document.getElementById('origin_location');
        const destInput = document.getElementById('destination_location');

        const autocompleteOptions = {
            componentRestrictions: { country: 'id' },
            fields: ['geometry', 'name', 'formatted_address']
        };

        const autoOrigin = new google.maps.places.Autocomplete(originInput, autocompleteOptions);
        const autoDest = new google.maps.places.Autocomplete(destInput, autocompleteOptions);

        autoOrigin.addListener('place_changed', function() {
            const place = autoOrigin.getPlace();
            if (place.geometry && place.geometry.location) {
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                document.getElementById('origin_lat').value = lat;
                document.getElementById('origin_lng').value = lng;
                originMarker.setPosition({ lat, lng });
                calculateAndAnimateRoute();
            }
        });

        autoDest.addListener('place_changed', function() {
            const place = autoDest.getPlace();
            if (place.geometry && place.geometry.location) {
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                document.getElementById('destination_lat').value = lat;
                document.getElementById('destination_lng').value = lng;
                destMarker.setPosition({ lat, lng });
                calculateAndAnimateRoute();
            }
        });

        // Jalankan kalkulasi rute awal
        calculateAndAnimateRoute();

    } catch (err) {
        console.warn("Google Maps error, menggunakan interactive fallback map.", err);
        renderFallbackInteractiveMap();
    }
}

/**
 * Kalkulasi Rute & Animasi Garis Polyline Google Maps
 */
function calculateAndAnimateRoute() {
    if (!directionsService || !originMarker || !destMarker) {
        renderFallbackInteractiveMap();
        return;
    }

    const originPos = originMarker.getPosition();
    const destPos = destMarker.getPosition();

    directionsService.route({
        origin: originPos,
        destination: destPos,
        travelMode: google.maps.TravelMode.DRIVING
    }, function(result, status) {
        if (status === google.maps.DirectionsStatus.OK) {
            const route = result.routes[0];
            const leg = route.legs[0];
            const distanceKm = leg.distance.value / 1000;
            const durationText = leg.duration.text;

            updateFareDisplay(distanceKm, durationText);

            // Bersihkan polyline sebelumnya
            if (activePolyline) {
                activePolyline.setMap(null);
            }

            // Animasi Polylines: gambar secara bertahap dari titik asal ke tujuan
            const overviewPath = route.overview_path;
            activePolyline = new google.maps.Polyline({
                path: [],
                strokeColor: '#2563eb',
                strokeWeight: 6,
                strokeOpacity: 0.9,
                map: mapInstance
            });

            // Pan map to bounds rute
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(originPos);
            bounds.extend(destPos);
            mapInstance.panToBounds(bounds);

            // Langkah animasi koordinat polyline
            let step = 0;
            const totalSteps = overviewPath.length;
            const animatedPoints = [];
            const interval = setInterval(function() {
                if (step < totalSteps) {
                    animatedPoints.push(overviewPath[step]);
                    activePolyline.setPath(animatedPoints);
                    step++;
                } else {
                    clearInterval(interval);
                }
            }, Math.max(10, Math.floor(600 / totalSteps)));

            document.getElementById('map-status-text').innerText = '📍 Rute: ' + distanceKm.toFixed(2) + ' KM (' + durationText + ')';
        } else {
            renderFallbackInteractiveMap();
        }
    });
}

/**
 * Fallback Canvas Map Interaktif (Jika Google Maps API Key belum diaktivasi)
 */
function renderFallbackInteractiveMap() {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;

    mapDiv.innerHTML = `
        <div style="width:100%; height:100%; background: linear-gradient(135deg, #e0f2fe 0%, #f0fdf4 100%); display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; overflow:hidden;">
            <svg width="100%" height="100%" viewBox="0 0 500 250" style="position:absolute; top:0; left:0;">
                <!-- Jalur Rute Polyline Animasi SVG -->
                <path d="M 80,180 Q 220,60 420,100" fill="none" stroke="#93c5fd" stroke-width="8" stroke-linecap="round"/>
                <path d="M 80,180 Q 220,60 420,100" fill="none" stroke="#2563eb" stroke-width="5" stroke-linecap="round" stroke-dasharray="10, 10">
                    <animate attributeName="stroke-dashoffset" from="100" to="0" dur="2s" repeatCount="indefinite" />
                </path>
                
                <!-- Marker Jemput (Hijau) -->
                <circle cx="80" cy="180" r="14" fill="#10b981" stroke="#fff" stroke-width="3"/>
                <text x="80" y="185" font-size="12" font-weight="bold" fill="#fff" text-anchor="middle">A</text>
                
                <!-- Marker Tujuan (Merah) -->
                <circle cx="420" cy="100" r="14" fill="#ef4444" stroke="#fff" stroke-width="3"/>
                <text x="420" y="105" font-size="12" font-weight="bold" fill="#fff" text-anchor="middle">B</text>
            </svg>
            <div style="background:rgba(255,255,255,0.92); padding:10px 18px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.08); z-index:2; text-align:center;">
                <strong style="color:#1e293b; font-size:14px;">🛵 Rute Terdeteksi: Polindra Hub</strong>
                <div style="font-size:12px; color:#2563eb; font-weight:700; margin-top:2px;">Simulasi Jarak: 1.50 KM • Ongkir: Rp 7.000</div>
            </div>
        </div>
    `;
    updateFareDisplay(1.50, '~5 Menit');
}

// Fallback jika Google Maps API gagal memuat
window.gm_authFailure = function() {
    console.warn("Google Maps Auth Failure, mengaktifkan tampilan visual rute canvas.");
    renderFallbackInteractiveMap();
};

window.addEventListener('load', function() {
    if (typeof google === 'undefined' || !google.maps) {
        setTimeout(renderFallbackInteractiveMap, 1000);
    }
});

function toggleMetodeBayar() {
    const cards = document.querySelectorAll('.metode-card');
    cards.forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        card.classList.toggle('selected', radio.checked);
    });
    const isCash = document.querySelector('input[name="metode_bayar"][value="cash"]').checked;
    document.getElementById('cash-note').style.display = isCash ? 'block' : 'none';
}

/**
 * Handle Form Submission & Midtrans Seamless Snap Pop-up
 */
document.getElementById('orderForm').addEventListener('submit', function(e) {
    const metodeBayar = document.querySelector('input[name="metode_bayar"]:checked').value;
    
    // Jika metode Cash, langsung submit normal agar cepat
    if (metodeBayar === 'cash') {
        return true;
    }

    // Jika metode QRIS / Midtrans: Cegah reload dan jalankan Snap Pop-up secara seamless!
    e.preventDefault();

    const submitBtn = document.getElementById('btnSubmitOrder');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>⏳ Memproses Midtrans Snap...</span>';

    const formData = new FormData(this);
    formData.append('ajax_order', '1');

    fetch('order.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;

        if (!data.success) {
            alert('Gagal memproses pesanan: ' + (data.message || 'Terjadi kesalahan sistem.'));
            return;
        }

        currentPendingOrderId = data.pesanan_id;

        // Cek apakah Snap JS resmi tersedia & token bukan mock
        if (typeof window.snap !== 'undefined' && data.snap_token && !data.is_mock) {
            window.snap.pay(data.snap_token, {
                onSuccess: function(result) {
                    syncPaymentSuccess(data.pesanan_id);
                },
                onPending: function(result) {
                    window.location.href = 'pesanan_saya.php?id=' + data.pesanan_id + '&pesan=menunggu_bayar';
                },
                onError: function(result) {
                    alert('Pembayaran Midtrans gagal atau dibatalkan.');
                    window.location.href = 'pesanan_saya.php?id=' + data.pesanan_id + '&pesan=gagal_bayar';
                },
                onClose: function() {
                    window.location.href = 'pesanan_saya.php?id=' + data.pesanan_id;
                }
            });
        } else {
            // Tampilkan Snap Modal Pop-up Seamless (Sandbox Simulator)
            openSnapModal(data.pesanan_id, data.total_harga);
        }
    })
    .catch(err => {
        console.error(err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        alert('Terjadi kesalahan koneksi.');
    });
});

/**
 * Buka Modal Snap Sandbox Seamless
 */
function openSnapModal(pesananId, totalHarga) {
    currentPendingOrderId = pesananId;
    document.getElementById('snap-modal-price').innerText = 'Rp ' + Number(totalHarga).toLocaleString('id-ID');
    document.getElementById('modalSnapSimulator').style.display = 'flex';
}

function closeSnapModal() {
    document.getElementById('modalSnapSimulator').style.display = 'none';
    if (currentPendingOrderId > 0) {
        window.location.href = 'pesanan_saya.php?id=' + currentPendingOrderId;
    }
}

/**
 * Konfirmasi Pembayaran Berhasil & Sinkronisasi Real-Time ke Database
 */
function confirmSimulatedPayment() {
    if (currentPendingOrderId <= 0) return;
    syncPaymentSuccess(currentPendingOrderId);
}

function syncPaymentSuccess(pesananId) {
    const btn = document.querySelector('.btn-snap-confirm');
    if (btn) btn.innerText = '⏳ Memverifikasi Pembayaran...';

    fetch('update_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pesanan_id: pesananId, payment_status: 'paid' })
    })
    .then(res => res.json())
    .then(res => {
        window.location.href = 'pesanan_saya.php?id=' + pesananId + '&pesan=sukses_bayar';
    })
    .catch(() => {
        window.location.href = 'pesanan_saya.php?id=' + pesananId;
    });
}
</script>
</body>
</html>

<?php
session_start();
require_once "koneksi.php";

// Hanya pelanggan yang boleh memilih driver
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?redirect=pilih_driver");
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
$pesananId = isset($_GET["pesanan_id"]) ? (int) $_GET["pesanan_id"] : (isset($_POST["pesanan_id"]) ? (int) $_POST["pesanan_id"] : 0);

// Jika menetapkan driver ke pesanan yang sudah ada
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["pilih_driver_pesanan"])) {
    $targetPesananId = (int) ($_POST["pesanan_id"] ?? 0);
    $selectedDriverId = (int) ($_POST["driver_id"] ?? 0);

    if ($targetPesananId > 0 && $selectedDriverId > 0) {
        $stmtUpdate = $pdo->prepare("
            UPDATE pesanan
            SET driver_id = :driver_id, status = 'Menunggu Driver'
            WHERE id = :pesanan_id AND user_id = :user_id
        ");
        $stmtUpdate->execute([
            "driver_id" => $selectedDriverId,
            "pesanan_id" => $targetPesananId,
            "user_id" => $userId
        ]);

        header("Location: pesanan_saya.php?id=" . $targetPesananId . "&pesan=driver_terpilih");
        exit;
    }
}

// Ambil driver yang sedang aktif beserta info QRIS
$sql = "SELECT u.id, u.nama, d.foto_qris
        FROM users u
        LEFT JOIN drivers d ON d.user_id = u.id
        WHERE u.role = 'driver'
        AND u.status_aktif = 1
        ORDER BY u.nama ASC";

$stmt = $pdo->query($sql);
$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Driver - Polindra Suruh</title>

    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        .pilih-driver {
            min-height: 100vh;
            padding: 60px 7% 60px;
            box-sizing: border-box;
        }

        .driver-heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .driver-heading h1 {
            font-size: clamp(28px, 4vw, 42px);
            margin-bottom: 10px;
        }

        .driver-heading p {
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .pesanan-alert-banner {
            max-width: 650px;
            margin: 0 auto 30px;
            background: #fff;
            border-left: 5px solid #2563eb;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            font-size: 14px;
            color: #1e293b;
            text-align: center;
        }

        .driver-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 22px;
            max-width: 1000px;
            margin: auto;
        }

        .driver-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #eee;
            border-radius: 18px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .driver-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .driver-avatar {
            width: 75px;
            height: 75px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd21f, #f7b500);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            font-weight: bold;
            color: #222;
            box-shadow: 0 4px 12px rgba(247, 181, 0, 0.35);
        }

        .driver-card h3 {
            margin-bottom: 10px;
            color: #0f172a;
        }

        .driver-status {
            display: inline-block;
            color: #16803c;
            background: #dcfce7;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .pilih-button {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #ffd21f, #f7b500);
            color: #222;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s;
        }

        .pilih-button:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        .driver-kosong {
            max-width: 600px;
            margin: auto;
            padding: 30px 25px;
            border-radius: 16px;
            text-align: center;
            background: #fff8df;
            color: #664d03;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .kembali {
            display: inline-block;
            margin: 35px auto 0;
            color: #1e293b;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 25px;
            transition: background 0.2s;
        }

        .kembali:hover {
            background: #ffffff;
        }

        .kembali-wrapper {
            text-align: center;
        }
    </style>
</head>

<body>

    <section class="pilih-driver">

        <div class="driver-heading">
            <h1>Pilih Driver Kamu</h1>
            <p>
                <?= $pesananId > 0
                    ? 'Pilih driver yang tersedia untuk menangani Pesanan #' . (int) $pesananId . '.'
                    : 'Pilih driver yang sedang aktif sebelum membuat pesanan kamu.' ?>
            </p>
        </div>

        <?php if ($pesananId > 0): ?>
            <div class="pesanan-alert-banner">
                🛵 <strong>Memilih Driver untuk Pesanan #<?= (int) $pesananId ?></strong>
            </div>
        <?php endif; ?>

        <?php if (count($drivers) > 0): ?>

            <div class="driver-list">

                <?php foreach ($drivers as $driver): ?>

                    <div class="driver-card">

                        <div class="driver-avatar">
                            <?= strtoupper(
                                substr($driver["nama"], 0, 1)
                            ) ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars(
                                $driver["nama"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </h3>

                        <span class="driver-status">
                            ● Sedang Aktif
                        </span>

                        <?php if (!empty($driver["foto_qris"]) && file_exists(__DIR__ . "/uploads/qris/" . $driver["foto_qris"])): ?>
                            <div style="margin-top: 6px;">
                                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 6px; font-weight: bold; border: 1px solid #a7f3d0;">
                                    💳 QRIS Driver Siap
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($pesananId > 0): ?>
                            <form action="pilih_driver.php" method="POST">
                                <input type="hidden" name="pilih_driver_pesanan" value="1">
                                <input type="hidden" name="pesanan_id" value="<?= (int) $pesananId ?>">
                                <input type="hidden" name="driver_id" value="<?= (int) $driver["id"] ?>">

                                <button type="submit" class="pilih-button">
                                    Pilih Driver Ini
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="order.php" method="GET">
                                <input
                                    type="hidden"
                                    name="driver_id"
                                    value="<?= (int) $driver["id"] ?>"
                                >

                                <button type="submit" class="pilih-button">
                                    Pilih Driver
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="driver-kosong">
                <h3>Belum Ada Driver Aktif</h3>
                <p>
                    Saat ini belum ada driver yang tersedia dan sedang online.
                    Silakan coba lagi beberapa saat.
                </p>
            </div>

        <?php endif; ?>

        <div class="kembali-wrapper">
            <a href="<?= $pesananId > 0 ? 'pesanan_saya.php?id=' . (int) $pesananId : 'index.php' ?>" class="kembali">
                ← <?= $pesananId > 0 ? 'Kembali ke Pesanan Saya' : 'Kembali ke Beranda' ?>
            </a>
        </div>

    </section>

</body>
</html>
<?php
session_start();
require_once "koneksi.php";

$nama = $_SESSION["nama"] ?? null;
$role = $_SESSION["role"] ?? null;
$login = isset($_SESSION["user_id"]);

// Ambil daftar layanan aktif yang diatur oleh Admin
$stmtLayanan = $pdo->query("SELECT * FROM layanan WHERE status_aktif = 1 ORDER BY urutan ASC, id ASC");
$daftarLayanan = $stmtLayanan->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan - Polindra Suruh</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .service p {
            margin-bottom: 8px;
        }
        .admin-banner {
            margin: 0 8% 25px;
            background: rgba(255, 255, 255, 0.9);
            border: 2px dashed #f59e0b;
            border-radius: 16px;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .admin-banner-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-banner-icon {
            font-size: 26px;
        }
        .admin-banner-text strong {
            display: block;
            color: #0f172a;
            font-size: 14px;
        }
        .admin-banner-text p {
            margin: 2px 0 0;
            color: #475569;
            font-size: 12px;
        }
        .admin-banner-btn {
            background: #111;
            color: #fff;
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .admin-banner-btn:hover {
            background: #2563eb;
            color: #fff;
            transform: translateY(-2px);
        }
        .empty-layanan {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            border: 1px dashed rgba(0, 0, 0, 0.15);
        }
        .empty-layanan p {
            font-size: 15px;
            font-weight: 600;
            color: #334155;
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
            <a href="layanan.php" style="color: #ffd21f;">LAYANAN</a>
            <a href="cara_suruh.php">CARA SURUH</a>
            <a href="tentang.php">TENTANG</a>
        </div>

        <div class="nav-account">
            <?php if (!$login): ?>

                <a href="login.php" class="login-btn">E-COREN</a>

            <?php elseif ($role === "admin"): ?>

                <a href="admin/index.php" class="login-btn">DASHBOARD ADMIN</a>
                <a href="logout.php" class="login-btn">LOGOUT</a>

            <?php elseif ($role === "driver"): ?>

                <a href="driver.php" class="login-btn">DASHBOARD DRIVER</a>
                <a href="logout.php" class="login-btn">LOGOUT</a>

            <?php else: ?>

                <a href="order.php" class="login-btn">BUAT PESANAN</a>
                <a href="pesanan_saya.php" class="login-btn">PESANAN SAYA</a>
                <a href="logout.php" class="login-btn">LOGOUT</a>

            <?php endif; ?>
        </div>
    </nav>

    <!-- HEADER -->
    <section class="header">
        <small>POLINDRA SURUH-HUB</small>

        <h1>
            DAFTAR <span>LAYANAN</span>
        </h1>

        <p>
            Berikut adalah berbagai jenis bantuan dan layanan yang dapat dijalankan oleh
            driver Polindra Suruh untuk memudahkan aktivitas kampusmu.
        </p>
    </section>

    <!-- BANNER KHUSUS ADMIN (CRUD SHORTCUT) -->
    <?php if ($login && $role === "admin"): ?>
        <div class="admin-banner">
            <div class="admin-banner-info">
                <span class="admin-banner-icon">⚡</span>
                <div class="admin-banner-text">
                    <strong>Anda masuk sebagai Administrator</strong>
                    <p>Kelola, tambah, edit, atau sembunyikan layanan melalui panel CRUD.</p>
                </div>
            </div>
            <a href="admin/layanan.php" class="admin-banner-btn">
                🛠️ Kelola Layanan (CRUD Admin) &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- SERVICE CARDS (HANYA TAMPILAN INFORMASI, TANPA TOMBOL PESAN) -->
    <section class="services">

        <?php if (empty($daftarLayanan)): ?>
            <div class="empty-layanan">
                <div style="font-size: 38px; margin-bottom: 10px;">📦</div>
                <p>Belum ada layanan yang ditambahkan atau diaktifkan saat ini.</p>
                <?php if ($login && $role === "admin"): ?>
                    <div style="margin-top: 14px;">
                        <a href="admin/layanan.php" class="admin-banner-btn">➕ Tambah Layanan Sekarang</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($daftarLayanan as $index => $item): ?>
                <div class="service">
                    <div class="number"><?= htmlspecialchars($item["nomor"] ?: sprintf("%02d", $index + 1)) ?></div>
                    <div class="icon"><?= htmlspecialchars($item["icon"] ?: "📦") ?></div>

                    <h2><?= htmlspecialchars($item["nama"]) ?></h2>

                    <p>
                        <?= nl2br(htmlspecialchars($item["deskripsi"])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </section>

    <!-- INFO PENUTUP (TAMPILAN SAJA) -->
    <section class="info">
        <div class="info-text" style="max-width: 100%;">
            <h2>Butuh Bantuan Lainnya?</h2>

            <p>
                Semua kebutuhan harian, pembelian, pengantaran, hingga bantuan tugas dapat
                dikoordinasikan langsung bersama driver Polindra Suruh yang siap membantumu.
            </p>
        </div>
    </section>

</body>
</html>
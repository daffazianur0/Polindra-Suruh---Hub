<?php
session_start();

$nama = $_SESSION["nama"] ?? null;
$role = $_SESSION["role"] ?? null;
$login = isset($_SESSION["user_id"]);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cara Suruh - Polindra Suruh</title>

    <link rel="stylesheet" href="assets/css/styles.css">
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
            <a href="cara_suruh.php" style="color: #ffd21f;">CARA SURUH</a>
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
            CARA <span>SURUH</span>
        </h1>

        <p>
            Mager keluar atau butuh bantuan tugas dan belanja?
            Ikuti beberapa langkah mudah berikut untuk memesan jasa
            driver mahasiswa Polindra Suruh.
        </p>
    </section>

    <!-- LANGKAH PEMESANAN -->
    <section class="steps">

        <!-- LANGKAH 1 -->
        <div class="step">
            <div class="step-number">01</div>
            <div class="step-icon">🛵</div>

            <h2>Pilih Driver Aktif</h2>

            <p>
                Buka halaman Pilih Driver untuk melihat driver yang sedang online,
                atau kamu bisa langsung membuat pesanan untuk ditugaskan ke driver yang tersedia.
            </p>
        </div>

        <!-- LANGKAH 2 -->
        <div class="step">
            <div class="step-number">02</div>
            <div class="step-icon">📝</div>

            <h2>Tulis Kebutuhan Pesanan</h2>

            <p>
                Tuliskan secara jelas barang yang ingin dibeli, bantuan yang dibutuhkan,
                atau instruksi khusus pada formulir Buat Pesanan.
            </p>
        </div>

        <!-- LANGKAH 3 -->
        <div class="step">
            <div class="step-number">03</div>
            <div class="step-icon">💳</div>

            <h2>Driver Mengonfirmasi & QRIS</h2>

            <p>
                Driver akan meninjau dan menerima pesananmu. Setelah disetujui,
                kode QRIS pembayaran akan otomatis tampil di halaman Pesanan Saya.
            </p>
        </div>

        <!-- LANGKAH 4 -->
        <div class="step">
            <div class="step-number">04</div>
            <div class="step-icon">📸</div>

            <h2>Bayar & Kirim Bukti QRIS</h2>

            <p>
                Lakukan pembayaran dengan scan QRIS melalui e-wallet atau mobile banking,
                lalu unggah foto bukti transfer untuk diverifikasi oleh driver.
            </p>
        </div>

        <!-- LANGKAH 5 -->
        <div class="step">
            <div class="step-number">05</div>
            <div class="step-icon">📦</div>

            <h2>Driver Menjalankan Pesanan</h2>

            <p>
                Driver segera membelikan dan mengantar pesananmu. Kamu juga bisa langsung
                berkomunikasi secara mudah lewat tombol chat WhatsApp ke driver.
            </p>
        </div>

        <!-- LANGKAH 6 -->
        <div class="step">
            <div class="step-number">06</div>
            <div class="step-icon">✅</div>

            <h2>Pesanan Selesai</h2>

            <p>
                Pesananmu telah sampai dengan aman! Driver akan menandai pesanan selesai
                dan riwayat tersimpan rapi di halaman Pesanan Saya.
            </p>
        </div>

    </section>

    <!-- ALUR PEMESANAN -->
    <section class="flow">
        <div class="flow-title">
            <h2>Alur Pemesanan</h2>
            <p>Alur praktis dari awal pesan hingga barang sampai ke tanganmu.</p>
        </div>

        <div class="flow-list">

            <div class="flow-item">
                <div class="flow-icon">🛵</div>
                <h3>Pilih Driver</h3>
                <p>Pilih driver aktif</p>
            </div>

            <div class="arrow">→</div>

            <div class="flow-item">
                <div class="flow-icon">📝</div>
                <h3>Pesan</h3>
                <p>Isi detail kebutuhan</p>
            </div>

            <div class="arrow">→</div>

            <div class="flow-item">
                <div class="flow-icon">💳</div>
                <h3>Bayar QRIS</h3>
                <p>Scan & kirim bukti</p>
            </div>

            <div class="arrow">→</div>

            <div class="flow-item">
                <div class="flow-icon">📦</div>
                <h3>Diproses</h3>
                <p>Driver memproses pesanan</p>
            </div>

            <div class="arrow">→</div>

            <div class="flow-item">
                <div class="flow-icon">✓</div>
                <h3>Selesai</h3>
                <p>Pesanan tuntas</p>
            </div>

        </div>
    </section>

    <!-- TOMBOL MULAI SURUH -->
    <div class="bottom">
        <a
            href="<?= !$login
                ? 'login.php?redirect=pilih_driver'
                : ($role === 'user'
                    ? 'pilih_driver.php'
                    : ($role === 'admin'
                        ? 'admin/index.php'
                        : 'driver.php')) ?>"
            class="order-button">

            <?= !$login
                ? 'LOGIN UNTUK MULAI SURUH'
                : ($role === 'user'
                    ? 'MULAI SURUH SEKARANG'
                    : 'BUKA DASHBOARD') ?>

        </a>
    </div>

</body>
</html>
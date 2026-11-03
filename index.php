
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

    <title>Polindra Suruh - Hub</title>

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


    <!-- HERO -->
    <section class="hero">

        <div class="circle-one"></div>
        <div class="circle-two"></div>

        <!-- ILUSTRASI MOTOR -->
        <div class="motor">
            <div class="motor-seat"></div>
            <div class="motor-body"></div>
            <div class="motor-wheel wheel-left"></div>
            <div class="motor-wheel wheel-right"></div>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="hero-content">

            <div class="small-title">
                POLINDRA SURUH-HUB
            </div>

            <h1 class="hero-title">
                <span class="white">Mager?</span>
                <br>
                <span class="yellow">Suruh Kami</span>
            </h1>

            <p class="tagline">
                Jasa Suruh Apa Saja
            </p>

            <?php if ($login && $role === "user"): ?>

                <p class="welcome">
                    Halo,
                    <?= htmlspecialchars($nama, ENT_QUOTES, "UTF-8") ?>!
                    Mau suruh apa hari ini?
                </p>

                <a href="pilih_driver.php" class="order-button">
                    MULAI SURUH
                </a>

            <?php elseif (!$login): ?>

                <a href="login.php?redirect=pilih_driver" class="order-button">
                    MULAI SURUH
                </a>

                <p class="info-login">
                    Belum punya akun? Kamu bisa melihat layanan kami
                    dan mendaftar saat ingin melakukan pemesanan.
                </p>

            <?php elseif ($role === "admin"): ?>

                <a href="admin/index.php" class="order-button">
                    BUKA DASHBOARD
                </a>

            <?php elseif ($role === "driver"): ?>

                <a href="driver.php" class="order-button">
                    BUKA DASHBOARD
                </a>

            <?php endif; ?>

        </div>

    </section>

</body>
</html>
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
    <title>Tentang Kami - Polindra Suruh</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        body, * {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
            <a href="tentang.php" style="color: #ffd21f;">TENTANG</a>
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
            TENTANG <span>KAMI</span>
        </h1>

        <p>
            Mengenal lebih dekat platform jasa suruh civitas akademika Politeknik Negeri Indramayu.
            Solusi praktis, cepat, dan andalan mahasiswa untuk memenuhi segala kebutuhan harian.
        </p>
    </section>

    <!-- MAIN ABOUT HERO CARD -->
    <section class="about-main-card">
        <div>
            <div class="about-badge">
                <span class="about-badge-dot"></span>
                <span>PLATFORM SURUH MAHASISWA</span>
            </div>

            <h2 class="about-title">
                Solusi Praktis & Tanggap untuk <span>Segala Kebutuhanmu</span>
            </h2>

            <p class="about-desc">
                <strong>Polindra Suruh</strong> merupakan platform jasa suruh berbasis website yang dirancang
                khusus untuk mempermudah aktivitas mahasiswa, dosen, serta civitas akademika Politeknik Negeri Indramayu.
                Mulai dari membelikan makanan, kebutuhan fotokopi & print tugas, hingga antar jemput barang penting saat kamu sibuk praktikum atau kuliah padat.
            </p>

            <div class="about-features">
                <div class="about-feature-item">
                    <span class="feat-icon">⚡</span>
                    <div>
                        <strong>Cepat & Sigap</strong>
                        <p>Pesanan langsung direspons oleh driver aktif kampus.</p>
                    </div>
                </div>

                <div class="about-feature-item">
                    <span class="feat-icon">🛡️</span>
                    <div>
                        <strong>Mitra Terpercaya</strong>
                        <p>Driver resmi dari mahasiswa Polindra yang amanah.</p>
                    </div>
                </div>

                <div class="about-feature-item">
                    <span class="feat-icon">💳</span>
                    <div>
                        <strong>QRIS Digital</strong>
                        <p>Scan pembayaran praktis via e-wallet & m-banking.</p>
                    </div>
                </div>

                <div class="about-feature-item">
                    <span class="feat-icon">💬</span>
                    <div>
                        <strong>Chat WhatsApp</strong>
                        <p>Koordinasi instruksi secara langsung dengan driver.</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="about-visual-box">
                <div class="about-visual-avatar">🛵</div>
                <h3>Polindra Suruh-Hub</h3>
                <p class="sub">Jasa Suruh Apa Saja Kampus Polindra</p>

                <div class="about-visual-status-pill">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 8px #4ade80;"></span>
                    <span>Driver Aktif & Siap Melayani</span>
                </div>

                <div class="about-visual-stats">
                    <div class="about-visual-stat-item">
                        <strong>100%</strong>
                        <span>Mahasiswa</span>
                    </div>
                    <div class="about-visual-stat-item">
                        <strong>QRIS</strong>
                        <span>Scan Praktis</span>
                    </div>
                    <div class="about-visual-stat-item">
                        <strong>Gercep</strong>
                        <span>Siap Membantu</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3 PILLAR CARDS -->
    <section class="about-grid-three">
        <div class="about-card-pillar">
            <div class="pillar-icon-box">🎯</div>
            <h3>Visi & Misi</h3>
            <p>
                Menjadi platform andalan civitas kampus dalam mempermudah mobilitas dan efisiensi harian,
                sekaligus membuka peluang kerja sampingan yang fleksibel, aman, dan bermanfaat
                bagi mahasiswa Polindra sebagai mitra driver.
            </p>
        </div>

        <div class="about-card-pillar">
            <div class="pillar-icon-box">🤝</div>
            <h3>Solidaritas Kampus</h3>
            <p>
                Dibangun atas dasar semangat saling membantu antar sesama mahasiswa.
                Kami mengutamakan integritas, ketepatan waktu pengantaran, dan transparansi tarif
                yang bersahabat di kantong mahasiswa.
            </p>
        </div>

        <div class="about-card-pillar">
            <div class="pillar-icon-box">📱</div>
            <h3>Kontak & Informasi</h3>
            <div style="margin-top: 14px;">
                <div class="contact-row-item">
                    <span class="contact-row-icon">📍</span>
                    <div>
                        <strong>Kampus Polindra</strong>
                        <div style="font-size: 11px; color: #64748b;">Jl. Raya Lohbener Lama No. 08, Indramayu</div>
                    </div>
                </div>

                <div class="contact-row-item">
                    <span class="contact-row-icon">💬</span>
                    <div>
                        <strong>WhatsApp CS</strong>
                        <div style="font-size: 11px; color: #047857; font-weight: 700;">0895-0729-8091</div>
                    </div>
                </div>

                <div class="contact-row-item">
                    <span class="contact-row-icon">📸</span>
                    <div>
                        <strong>Instagram</strong>
                        <div style="font-size: 11px; color: #64748b;">@polindrasuruh</div>
                    </div>
                </div>

                <div class="contact-row-item">
                    <span class="contact-row-icon">⏰</span>
                    <div>
                        <strong>Jam Layanan</strong>
                        <div style="font-size: 11px; color: #64748b;">Fleksibel mengikuti jadwal aktifitas kampus</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BOTTOM CTA BANNER -->
    <section class="info" style="margin-bottom: 60px;">
        <div class="info-text">
            <h2>Mager Keluar atau Butuh Bantuan Cepat?</h2>
            <p>
                Percayakan kebutuhan belanja kantin, antar jemput barang, dan suruhan harianmu
                kepada driver mahasiswa Polindra Suruh sekarang.
            </p>
        </div>

        <a href="<?= !$login
            ? 'login.php?redirect=pilih_driver'
            : ($role === 'user'
                ? 'pilih_driver.php'
                : ($role === 'admin'
                    ? 'admin/index.php'
                    : 'driver.php')) ?>"
           class="info-button">
            <?= !$login ? 'LOGIN UNTUK PESAN' : 'MULAI SURUH SEKARANG →' ?>
        </a>
    </section>

</body>
</html>
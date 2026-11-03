<?php
/**
 * includes/admin_navbar.php
 * Navbar sidebar admin yang dipakai di semua halaman admin/.
 *
 * Variabel yang harus tersedia sebelum include:
 *   $activeAdminPage (string) - halaman aktif:
 *       'overview'|'driver'|'pesanan'|'user'|'layanan'
 *
 * Variabel opsional (ditampilkan di badge nav):
 *   $totalDrivers, $totalPesananAll, $totalUsers, $totalLayanan
 */

$_activeAdmin   = $activeAdminPage ?? '';
$_totalDrivers  = $totalDrivers ?? '';
$_totalPesanan  = $totalPesananAll ?? '';
$_totalUsers    = $totalUsers ?? '';
$_totalLayanan  = $totalLayanan ?? '';
?>

<header class="admin-navbar">
    <div class="admin-brand">
        <div class="admin-brand-icon">⚡</div>
        <div class="admin-brand-text">
            <h1>Polindra Suruh Admin</h1>
            <p>Halo, <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></strong> (Administrator)</p>
        </div>
    </div>

    <nav class="admin-nav-menu">
        <a href="index.php"
           class="admin-nav-link <?= $_activeAdmin === 'overview' ? 'active' : '' ?>">
            📊 Overview
        </a>

        <a href="driver.php"
           class="admin-nav-link <?= $_activeAdmin === 'driver' ? 'active' : '' ?>">
            🛵 Kelola Driver
            <?php if ($_totalDrivers !== ''): ?>
                <span class="badge-count"><?= (int) $_totalDrivers ?></span>
            <?php endif; ?>
        </a>

        <a href="pesanan.php"
           class="admin-nav-link <?= $_activeAdmin === 'pesanan' ? 'active' : '' ?>">
            📦 Kelola Pesanan
            <?php if ($_totalPesanan !== ''): ?>
                <span class="badge-count"><?= (int) $_totalPesanan ?></span>
            <?php endif; ?>
        </a>

        <a href="user.php"
           class="admin-nav-link <?= $_activeAdmin === 'user' ? 'active' : '' ?>">
            👥 Kelola Pelanggan
            <?php if ($_totalUsers !== ''): ?>
                <span class="badge-count"><?= (int) $_totalUsers ?></span>
            <?php endif; ?>
        </a>

        <a href="layanan.php"
           class="admin-nav-link <?= $_activeAdmin === 'layanan' ? 'active' : '' ?>">
            🛠️ Kelola Layanan
            <?php if ($_totalLayanan !== ''): ?>
                <span class="badge-count"><?= (int) $_totalLayanan ?></span>
            <?php endif; ?>
        </a>

        <a href="../layanan.php" class="admin-nav-link" target="_blank">
            👁️ Halaman Layanan
        </a>

        <a href="../index.php" class="admin-nav-link" target="_blank">
            🌐 Beranda
        </a>

        <a href="../logout.php" class="admin-btn-logout">Logout</a>
    </nav>
</header>

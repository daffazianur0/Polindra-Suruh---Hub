<?php

$_navPrefix  = $navPrefix ?? './';         
$_activePage = $activePage ?? '';
?>

<nav>
    <div class="logo">
        <div class="logo-icon">PS</div>
        <div class="logo-text">
            <span>POLINDRA</span>
            <span>SURUH</span>
        </div>
    </div>

    <div class="menu">
        <a href="<?= $_navPrefix ?>index.php"
           <?= $_activePage === 'home'      ? 'style="color:#ffd21f;"' : '' ?>>HOME</a>
        <a href="<?= $_navPrefix ?>user/layanan.php"
           <?= $_activePage === 'layanan'   ? 'style="color:#ffd21f;"' : '' ?>>LAYANAN</a>
        <a href="<?= $_navPrefix ?>user/cara_suruh.php"
           <?= $_activePage === 'cara_suruh'? 'style="color:#ffd21f;"' : '' ?>>CARA SURUH</a>
        <a href="<?= $_navPrefix ?>user/tentang.php"
           <?= $_activePage === 'tentang'   ? 'style="color:#ffd21f;"' : '' ?>>TENTANG</a>
    </div>

    <div class="nav-account">
        <?php if (!$login): ?>
            <a href="<?= $_navPrefix ?>login.php" class="login-btn">E-COREN</a>

        <?php elseif ($role === 'admin'): ?>
            <a href="<?= $_navPrefix ?>admin/index.php" class="login-btn">DASHBOARD ADMIN</a>
            <a href="<?= $_navPrefix ?>logout.php" class="login-btn">LOGOUT</a>

        <?php elseif ($role === 'driver'): ?>
            <a href="<?= $_navPrefix ?>driver/index.php" class="login-btn">DASHBOARD DRIVER</a>
            <a href="<?= $_navPrefix ?>logout.php" class="login-btn">LOGOUT</a>

        <?php else: ?>
            <a href="<?= $_navPrefix ?>user/order.php" class="login-btn">BUAT PESANAN</a>
            <a href="<?= $_navPrefix ?>user/pesanan_saya.php" class="login-btn">PESANAN SAYA</a>
            <a href="<?= $_navPrefix ?>logout.php" class="login-btn">LOGOUT</a>
        <?php endif; ?>
    </div>
</nav>

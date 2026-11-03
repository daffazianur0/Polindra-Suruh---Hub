<?php
session_start();
require_once '../koneksi.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('admin');

// =========================================================================
// AMBIL METRIK & STATISTIK SISTEM
// =========================================================================
$totalPesananAll      = (int) $pdo->query("SELECT COUNT(*) FROM pesanan")->fetchColumn();
$totalMenunggu        = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status IN ('Menunggu', 'Menunggu Driver', 'Menunggu Persetujuan Driver')")->fetchColumn();
$totalMenungguBayar   = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Menunggu Pembayaran'")->fetchColumn();
$totalVerifikasiQris  = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Diproses' AND bukti_bayar IS NOT NULL AND (qris_diverifikasi = 0 OR qris_diverifikasi IS NULL)")->fetchColumn();
$totalSelesai         = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Selesai'")->fetchColumn();

$totalDrivers  = (int) $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
$driversOnline = (int) $pdo->query("SELECT COUNT(*) FROM drivers WHERE status_aktif = 1")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalLayanan  = (int) $pdo->query("SELECT COUNT(*) FROM layanan")->fetchColumn();

// =========================================================================
// AMBIL 10 PESANAN TERBARU
// =========================================================================
$stmtPesananTerbaru = $pdo->query("
    SELECT
        p.id,
        p.catatan,
        p.status,
        p.bukti_bayar,
        p.qris_diverifikasi,
        p.created_at,
        u.nama  AS nama_pelanggan,
        ud.nama AS nama_driver
    FROM pesanan p
    INNER JOIN users u  ON p.user_id  = u.id
    LEFT  JOIN users ud ON p.driver_id = ud.id
    ORDER BY p.id DESC
    LIMIT 10
");
$pesananTerbaru = $stmtPesananTerbaru->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// AMBIL DAFTAR DRIVER (MAKS 6)
// =========================================================================
$stmtDrivers = $pdo->query("
    SELECT
        d.id AS driver_id,
        d.nama_driver,
        d.no_hp,
        d.status_aktif,
        u.email,
        (SELECT COUNT(*) FROM pesanan WHERE driver_id = d.user_id AND status = 'Selesai') AS total_selesai
    FROM drivers d
    INNER JOIN users u ON d.user_id = u.id
    ORDER BY d.status_aktif DESC, d.id DESC
    LIMIT 6
");
$daftarDrivers = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);

$activeAdminPage = 'overview';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Polindra Suruh</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .quick-action-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 16px 20px;
            box-shadow: var(--shadow-sm);
            flex: 1 1 200px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .quick-action-card strong { display: block; font-size: 14px; }
        .quick-action-card span  { font-size: 12px; color: var(--dark-muted); }
        .quick-action-icon       { font-size: 24px; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <?php include '../includes/admin_navbar.php'; ?>

    <!-- HIGHLIGHT PERHATIAN ADMIN -->
    <?php if ($totalVerifikasiQris > 0): ?>
        <div class="alert alert-warning" style="align-items:center;justify-content:space-between;">
            <div>
                ⚠️ <strong>Ada <?= $totalVerifikasiQris ?> bukti pembayaran QRIS yang memerlukan konfirmasi/verifikasi!</strong>
            </div>
            <a href="pesanan.php?status=Diproses" class="btn btn-warning btn-sm" style="background:#b45309;color:white;">
                🔍 Periksa Pesanan
            </a>
        </div>
    <?php endif; ?>

    <!-- STATS METRICS -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">📦</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalPesananAll) ?></div>
                <div class="stat-label">Total Pesanan Masuk</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">⏳</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalMenunggu + $totalMenungguBayar) ?></div>
                <div class="stat-label">Pesanan Menunggu</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">🛵</div>
            <div class="stat-info">
                <div class="stat-val"><?= $driversOnline ?> / <?= $totalDrivers ?></div>
                <div class="stat-label">Driver Online / Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon primary">👥</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Pelanggan Terdaftar</div>
            </div>
        </div>
    </section>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="driver.php"  class="quick-action-card">
            <div><strong>🛵 Kelola & Tambah Driver</strong><span>Atur akun driver & QRIS</span></div>
            <div class="quick-action-icon">➕</div>
        </a>
        <a href="pesanan.php" class="quick-action-card">
            <div><strong>📦 Kelola & Buat Pesanan</strong><span>Verifikasi QRIS & status kurir</span></div>
            <div class="quick-action-icon">➕</div>
        </a>
        <a href="user.php"    class="quick-action-card">
            <div><strong>👥 Kelola & Tambah Pelanggan</strong><span>Daftar akun pengguna aplikasi</span></div>
            <div class="quick-action-icon">➕</div>
        </a>
        <a href="layanan.php" class="quick-action-card">
            <div><strong>🛠️ Kelola & Tambah Layanan</strong><span>CRUD daftar layanan untuk user</span></div>
            <div class="quick-action-icon">➕</div>
        </a>
    </div>

    <!-- PESANAN TERBARU -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2 class="dash-card-title">📦 Pesanan Terbaru (10 Terakhir)</h2>
            <div class="dash-card-actions">
                <a href="pesanan.php" class="btn btn-secondary btn-sm">Lihat Semua Pesanan &rarr;</a>
            </div>
        </div>

        <?php if (empty($pesananTerbaru)): ?>
            <p style="text-align:center;color:var(--dark-muted);padding:30px 0;">Belum ada riwayat pesanan.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Catatan Pesanan</th>
                            <th>Driver Ditugaskan</th>
                            <th>Status</th>
                            <th>QRIS / Bukti</th>
                            <th>Waktu</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesananTerbaru as $p):
                            $statusClass = 'menunggu';
                            if ($p['status'] === 'Diproses')           $statusClass = 'proses';
                            elseif ($p['status'] === 'Dalam Perjalanan') $statusClass = 'antar';
                            elseif ($p['status'] === 'Selesai')          $statusClass = 'selesai';
                            elseif ($p['status'] === 'Dibatalkan')       $statusClass = 'batal';
                            elseif ($p['status'] === 'Menunggu Pembayaran') $statusClass = 'bayar';
                        ?>
                            <tr>
                                <td><strong>#<?= $p['id'] ?></strong></td>
                                <td><strong><?= htmlspecialchars($p['nama_pelanggan']) ?></strong></td>
                                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;font-size:13px;"
                                    title="<?= htmlspecialchars($p['catatan'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_strimwidth($p['catatan'] ?? '-', 0, 35, '...')) ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['nama_driver'])): ?>
                                        <span class="status-pill proses">🛵 <?= htmlspecialchars($p['nama_driver']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:12px;">Belum ditugaskan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($p['bukti_bayar'])): ?>
                                        <?php if ((int)$p['qris_diverifikasi'] === 1): ?>
                                            <span class="status-pill selesai">✔ Terverifikasi</span>
                                        <?php elseif ((int)$p['qris_diverifikasi'] === 2): ?>
                                            <span class="status-pill batal">❌ Ditolak</span>
                                        <?php else: ?>
                                            <span class="status-pill menunggu">⏳ Cek Bukti</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;color:var(--dark-muted);">
                                    <?= !empty($p['created_at']) ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-' ?>
                                </td>
                                <td style="text-align:right;">
                                    <a href="pesanan.php" class="btn btn-secondary btn-sm">Kelola</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- DRIVER AKTIF & STATUS -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2 class="dash-card-title">🛵 Status Driver Terbaru</h2>
            <div class="dash-card-actions">
                <a href="driver.php" class="btn btn-secondary btn-sm">Kelola Driver Selengkapnya &rarr;</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Nama Driver</th>
                        <th>No. WhatsApp</th>
                        <th>Email Akun</th>
                        <th>Order Selesai</th>
                        <th>Status Operasional</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarDrivers as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['nama_driver']) ?></strong></td>
                            <td><code><?= htmlspecialchars($d['no_hp']) ?></code></td>
                            <td><?= htmlspecialchars($d['email']) ?></td>
                            <td><span class="status-pill selesai">✔ <?= (int)$d['total_selesai'] ?> selesai</span></td>
                            <td>
                                <?php if ((int)$d['status_aktif'] === 1): ?>
                                    <span class="status-pill aktif">🟢 Online / Aktif</span>
                                <?php else: ?>
                                    <span class="status-pill nonaktif">⚪ Nonaktif / Offline</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <a href="driver.php" class="btn btn-secondary btn-sm">Edit / Status</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>

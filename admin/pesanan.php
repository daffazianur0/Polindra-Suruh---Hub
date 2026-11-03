<?php
session_start();
require_once "../koneksi.php";

// Pastikan hanya admin yang dapat mengakses halaman ini
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$activeAdminPage = "pesanan";
$pesan = "";
$jenisPesan = "";

// =========================================================================
// =========================================================================
// 1. UPDATE: ADMIN EDIT PESANAN & STATUS QRIS
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_pesanan"])) {
    $pesananId = (int) ($_POST["pesanan_id"] ?? 0);
    $driverId = !empty($_POST["driver_id"]) ? (int) $_POST["driver_id"] : null;
    $catatan = trim($_POST["catatan"] ?? "");
    $status = $_POST["status"] ?? "Menunggu Driver";
    $paymentStatus = in_array($_POST["payment_status"] ?? "", ["pending", "paid", "failed", "cod"]) ? $_POST["payment_status"] : "pending";
    $qrisDiverifikasi = (int) ($_POST["qris_diverifikasi"] ?? 0);
    $alasanTolak = trim($_POST["alasan_tolak_bayar"] ?? "");

    if ($qrisDiverifikasi !== 2) {
        $alasanTolak = null;
    }

    if ($pesananId > 0 && $catatan !== "") {
        try {
            $stmt = $pdo->prepare("
                UPDATE pesanan
                SET driver_id = ?,
                    catatan = ?,
                    status = ?,
                    payment_status = ?,
                    qris_diverifikasi = ?,
                    alasan_tolak_bayar = ?
                WHERE id = ?
            ");
            $stmt->execute([$driverId, $catatan, $status, $paymentStatus, $qrisDiverifikasi, $alasanTolak, $pesananId]);
            header("Location: pesanan.php?notif=edit_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal memperbarui pesanan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    } else {
        $pesan = "Harap isi catatan pesanan.";
        $jenisPesan = "error";
    }
}

// =========================================================================
// 3. DELETE: ADMIN HAPUS PESANAN
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hapus_pesanan"])) {
    $pesananId = (int) ($_POST["pesanan_id"] ?? 0);

    if ($pesananId > 0) {
        try {
            // Hapus file bukti bayar jika ada
            $stmtBukti = $pdo->prepare("SELECT bukti_bayar FROM pesanan WHERE id = ?");
            $stmtBukti->execute([$pesananId]);
            $fileBukti = $stmtBukti->fetchColumn();
            if ($fileBukti) {
                $path = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "bukti_bayar" . DIRECTORY_SEPARATOR . $fileBukti;
                if (file_exists($path)) @unlink($path);
            }

            $pdo->prepare("DELETE FROM pesanan WHERE id = ?")->execute([$pesananId]);
            header("Location: pesanan.php?notif=hapus_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal menghapus pesanan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

$notif = $_GET["notif"] ?? "";
$filterStatus = $_GET["status"] ?? "";

// Ambil daftar user dan driver untuk form select
$usersList = $pdo->query("SELECT id, nama, email FROM users WHERE role = 'user' ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
$driversList = $pdo->query("SELECT id, nama FROM users WHERE role = 'driver' ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// READ: AMBIL DAFTAR PESANAN
// =========================================================================
$sql = "
    SELECT
        p.*,
        u.nama AS nama_pelanggan,
        u.email AS email_pelanggan,
        ud.nama AS nama_driver,
        d.no_hp AS no_hp_driver
    FROM pesanan p
    INNER JOIN users u ON p.user_id = u.id
    LEFT JOIN users ud ON p.driver_id = ud.id
    LEFT JOIN drivers d ON d.user_id = ud.id
";

$params = [];
if ($filterStatus !== "") {
    $sql .= " WHERE p.status = :status ";
    $params["status"] = $filterStatus;
}
$sql .= " ORDER BY p.id DESC";

$stmtPesanan = $pdo->prepare($sql);
$stmtPesanan->execute($params);
$pesanan = $stmtPesanan->fetchAll(PDO::FETCH_ASSOC);

$totalPesananAll = $pdo->query("SELECT COUNT(*) FROM pesanan")->fetchColumn();
$totalMenunggu = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status IN ('Menunggu', 'Menunggu Driver', 'Menunggu Persetujuan Driver', 'Menunggu Pembayaran')")->fetchColumn();
$totalProses = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status IN ('Diproses', 'Dalam Perjalanan')")->fetchColumn();
$totalSelesai = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Selesai'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Polindra Suruh</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-container">

    <!-- NAVBAR ADMIN -->
    <?php include '../includes/admin_navbar.php'; ?>

    <!-- ALERT FEEDBACK -->
    <?php if ($notif === "tambah_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Pesanan baru berhasil dibuat!</strong></div>
    <?php elseif ($notif === "edit_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Data pesanan & status QRIS berhasil diperbarui!</strong></div>
    <?php elseif ($notif === "hapus_sukses"): ?>
        <div class="alert alert-warning">🗑️ <strong>Data pesanan berhasil dihapus.</strong></div>
    <?php elseif ($pesan !== ""): ?>
        <div class="alert alert-<?= $jenisPesan === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK MINI PESANAN -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">📦</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalPesananAll ?></div>
                <div class="stat-label">Total Semua Pesanan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">⏳</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalMenunggu ?></div>
                <div class="stat-label">Menunggu / Menunggu Bayar</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">🛵</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalProses ?></div>
                <div class="stat-label">Sedang Diproses / Diantar</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalSelesai ?></div>
                <div class="stat-label">Pesanan Selesai</div>
            </div>
        </div>
    </div>

    <!-- TABEL DATA PESANAN (CRUD) -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📋 Daftar Semua Pesanan</span>
            </div>
            <div class="dash-card-actions">
                <!-- Filter Status -->
                <form method="GET" action="pesanan.php" style="display: flex; gap: 8px;">
                    <select name="status" class="form-control" style="padding: 7px 12px; font-size: 13px;" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <option value="Menunggu Driver" <?= $filterStatus === 'Menunggu Driver' ? 'selected' : '' ?>>Menunggu Driver</option>
                        <option value="Menunggu Pembayaran" <?= $filterStatus === 'Menunggu Pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                        <option value="Diproses" <?= $filterStatus === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Dalam Perjalanan" <?= $filterStatus === 'Dalam Perjalanan' ? 'selected' : '' ?>>Dalam Perjalanan</option>
                        <option value="Selesai" <?= $filterStatus === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Dibatalkan" <?= $filterStatus === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </form>
            </div>
        </div>

        <?php if (empty($pesanan)): ?>
            <p style="text-align: center; color: #64748b; padding: 30px;">Tidak ada pesanan yang sesuai filter.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Driver Ditugaskan</th>
                            <th>Rute & Jarak</th>
                            <th>Ongkir</th>
                            <th>Catatan Pesanan</th>
                            <th>Status Bayar (Midtrans)</th>
                            <th>Status Pesanan</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan as $p): ?>
                            <?php
                                $hasBukti = !empty($p["bukti_bayar"]) && file_exists(__DIR__ . "/uploads/bukti_bayar/" . $p["bukti_bayar"]);
                                $buktiSrc = $hasBukti ? "uploads/bukti_bayar/" . htmlspecialchars($p["bukti_bayar"]) : "";
                                $qrisStatus = (int) ($p["qris_diverifikasi"] ?? 0);
                                $st = $p["status"];
                                $payStat = $p["payment_status"] ?? "pending";
                                
                                $pillClass = 'menunggu';
                                if ($st === 'Menunggu Pembayaran') $pillClass = 'bayar';
                                elseif ($st === 'Diproses') $pillClass = 'proses';
                                elseif ($st === 'Dalam Perjalanan') $pillClass = 'antar';
                                elseif ($st === 'Selesai') $pillClass = 'selesai';
                                elseif ($st === 'Dibatalkan') $pillClass = 'batal';
                            ?>
                            <tr>
                                <td><strong>#<?= (int) $p["id"] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($p["nama_pelanggan"]) ?></strong>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($p["email_pelanggan"]) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($p["nama_driver"])): ?>
                                        <div style="color: #047857; font-weight: 700;">🛵 <?= htmlspecialchars($p["nama_driver"]) ?></div>
                                        <?php if (!empty($p["no_hp_driver"])): ?>
                                            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($p["no_hp_driver"]) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">Belum ada driver</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 12px; line-height: 1.4;">
                                        <div>📍 <?= htmlspecialchars(mb_strimwidth($p["origin_location"] ?: ($p["alamat"] ?: "-"), 0, 28, "...")) ?></div>
                                        <div style="margin-top: 2px;">🏁 <?= htmlspecialchars(mb_strimwidth($p["destination_location"] ?: ($p["alamat"] ?: "-"), 0, 28, "...")) ?></div>
                                        <div style="margin-top: 3px; font-weight: 700; color: #2563eb;">
                                            📏 <?= number_format((float)($p["distance_km"] ?? 0), 2) ?> KM
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: #16a34a; font-size: 14px;">
                                        Rp <?= number_format((int)($p["total_harga"] ?? 0), 0, ',', '.') ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #1e293b; max-width: 180px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($p["catatan"] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($p["catatan"] ?? "-", 0, 50, "...")) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($payStat === 'paid'): ?>
                                        <span class="status-pill aktif" title="Otomatis via Midtrans Callback">✅ LUNAS</span>
                                    <?php elseif ($payStat === 'cod'): ?>
                                        <span class="status-pill" style="background: #fef9c3; color: #854d0e; border: 1px solid #fde68a;">💵 COD</span>
                                    <?php elseif ($payStat === 'failed'): ?>
                                        <span class="status-pill batal">❌ Gagal</span>
                                    <?php else: ?>
                                        <span class="status-pill nonaktif">⏳ Menunggu Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= $pillClass ?>">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; color: #64748b;">
                                        <?= date('d M Y, H:i', strtotime($p["created_at"])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button 
                                            type="button" 
                                            class="btn btn-warning btn-sm"
                                            onclick='openModalEdit(<?= json_encode($p) ?>)'
                                        >
                                            ✏️ Edit
                                        </button>

                                        <form method="POST" action="pesanan.php" onsubmit="return confirm('Hapus Pesanan #<?= (int) $p['id'] ?>? Data akan dihapus permanen.');" style="display: inline;">
                                            <input type="hidden" name="pesanan_id" value="<?= (int) $p["id"] ?>">
                                            <input type="hidden" name="hapus_pesanan" value="1">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>



<!-- MODAL EDIT PESANAN -->
<div id="modalEdit" class="modal-backdrop" onclick="closeModalEdit(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Data & Status Pesanan</h3>
            <button type="button" class="modal-close" onclick="closeModalEdit()">&times;</button>
        </div>
        <form method="POST" action="pesanan.php">
            <input type="hidden" name="pesanan_id" id="edit_pesanan_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Tugaskan / Ganti Driver</label>
                        <select name="driver_id" id="edit_driver_id" class="form-control">
                            <option value="">-- Tanpa Driver (Menunggu) --</option>
                            <?php foreach ($driversList as $d): ?>
                                <option value="<?= $d['id'] ?>">🛵 <?= htmlspecialchars($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Pesanan</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="Menunggu Driver">Menunggu Driver</option>
                            <option value="Menunggu Persetujuan Driver">Menunggu Persetujuan Driver</option>
                            <option value="Menunggu Pembayaran">Menunggu Pembayaran</option>
                            <option value="Diproses">Diproses</option>
                            <option value="Dalam Perjalanan">Dalam Perjalanan</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Status Pembayaran (Midtrans / COD)</label>
                        <select name="payment_status" id="edit_payment_status" class="form-control">
                            <option value="pending">⏳ Pending (Menunggu Pembayaran)</option>
                            <option value="paid">✅ Paid / Lunas (Terkonfirmasi Midtrans)</option>
                            <option value="cod">💵 COD (Bayar Tunai di Tempat)</option>
                            <option value="failed">❌ Failed (Gagal / Dibatalkan)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Verifikasi Pembayaran Manual (Opsional)</label>
                        <select name="qris_diverifikasi" id="edit_qris_diverifikasi" class="form-control" onchange="toggleAlasanEdit(this.value)">
                            <option value="0">⏳ Belum Diverifikasi</option>
                            <option value="1">✅ Pembayaran Sah & Terverifikasi</option>
                            <option value="2">❌ Pembayaran Ditolak</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="edit_alasan_group" style="display: none;">
                    <label class="form-label">Alasan Penolakan</label>
                    <input type="text" name="alasan_tolak_bayar" id="edit_alasan_tolak_bayar" class="form-control" placeholder="Contoh: Bukti palsu / saldo belum masuk">
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Kebutuhan</label>
                    <textarea name="catatan" id="edit_catatan" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalEdit()">Batal</button>
                <button type="submit" name="edit_pesanan" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL LIGHTBOX BUKTI BAYAR -->
<div id="modalBukti" class="modal-backdrop" onclick="closeBuktiModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">📸 Bukti Transfer QRIS Pelanggan</h3>
            <button type="button" class="modal-close" onclick="closeBuktiModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; background: #f8fafc;">
            <img id="imgBuktiModal" src="" alt="Bukti Transfer" style="max-width: 100%; max-height: 60vh; border-radius: 8px;">
        </div>
        <div class="modal-footer">
            <a id="linkBuktiDownload" href="" target="_blank" class="btn btn-primary btn-sm">Buka Gambar Penuh</a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeBuktiModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
function openModalEdit(data) {
    document.getElementById('edit_pesanan_id').value = data.id;
    document.getElementById('edit_driver_id').value = data.driver_id || '';
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_payment_status').value = data.payment_status || 'pending';
    document.getElementById('edit_catatan').value = data.catatan || '';
    document.getElementById('edit_qris_diverifikasi').value = data.qris_diverifikasi || '0';
    document.getElementById('edit_alasan_tolak_bayar').value = data.alasan_tolak_bayar || '';
    
    toggleAlasanEdit(data.qris_diverifikasi);
    document.getElementById('modalEdit').classList.add('active');
}
function closeModalEdit() {
    document.getElementById('modalEdit').classList.remove('active');
}

function toggleAlasanEdit(val) {
    var group = document.getElementById('edit_alasan_group');
    if (val === '2' || val === 2) {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
    }
}

function openBuktiModal(src) {
    var modal = document.getElementById('modalBukti');
    var img = document.getElementById('imgBuktiModal');
    var link = document.getElementById('linkBuktiDownload');
    img.src = src;
    link.href = src;
    modal.classList.add('active');
}
function closeBuktiModal() {
    document.getElementById('modalBukti').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModalEdit();
        closeBuktiModal();
    }
});
</script>

</body>
</html>

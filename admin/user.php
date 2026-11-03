<?php
session_start();
require_once "../koneksi.php";

// Pastikan hanya admin yang dapat mengakses halaman ini
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$pesan = "";
$jenisPesan = "";

// =========================================================================
// 1. CREATE: PROSES TAMBAH PELANGGAN BARU
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tambah_user"])) {
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nama === "" || $username === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $pesan = "Data tidak lengkap atau password kurang dari 8 karakter.";
        $jenisPesan = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (nama, username, email, password, role)
                VALUES (?, ?, ?, ?, 'user')
            ");
            $stmt->execute([
                $nama,
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            header("Location: user.php?notif=tambah_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = ($e->getCode() === "23000") ? "Username atau email sudah digunakan akun lain." : "Gagal menambahkan pelanggan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 2. UPDATE: PROSES EDIT PELANGGAN & RESET PASSWORD
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_user"])) {
    $targetUserId = (int) ($_POST["user_id"] ?? 0);
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $passwordBaru = $_POST["password_baru"] ?? "";

    if ($targetUserId <= 0 || $nama === "" || $username === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = "Data nama, username, atau email tidak valid.";
        $jenisPesan = "error";
    } else {
        try {
            if (!empty($passwordBaru)) {
                if (strlen($passwordBaru) < 8) {
                    $pesan = "Password baru minimal 8 karakter.";
                    $jenisPesan = "error";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET nama = ?, username = ?, email = ?, password = ?
                        WHERE id = ? AND role = 'user'
                    ");
                    $stmt->execute([
                        $nama,
                        $username,
                        $email,
                        password_hash($passwordBaru, PASSWORD_DEFAULT),
                        $targetUserId
                    ]);
                    header("Location: user.php?notif=edit_sukses");
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET nama = ?, username = ?, email = ?
                    WHERE id = ? AND role = 'user'
                ");
                $stmt->execute([$nama, $username, $email, $targetUserId]);
                header("Location: user.php?notif=edit_sukses");
                exit;
            }
        } catch (PDOException $e) {
            $pesan = ($e->getCode() === "23000") ? "Username atau email sudah digunakan akun lain." : "Gagal memperbarui data: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 3. DELETE: PROSES HAPUS PELANGGAN (DENGAN PEMBERSIHAN DATA TERKAIT)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hapus_user"])) {
    $targetUserId = (int) ($_POST["user_id"] ?? 0);

    if ($targetUserId > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Ambil semua bukti pembayaran terkait pesanan user ini untuk dihapus dari disk
            $stmtBukti = $pdo->prepare("SELECT bukti_bayar FROM pesanan WHERE user_id = ? AND bukti_bayar IS NOT NULL");
            $stmtBukti->execute([$targetUserId]);
            $buktiList = $stmtBukti->fetchAll(PDO::FETCH_COLUMN);

            $buktiDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "bukti_bayar" . DIRECTORY_SEPARATOR;
            foreach ($buktiList as $buktiFile) {
                if ($buktiFile && file_exists($buktiDir . $buktiFile)) {
                    @unlink($buktiDir . $buktiFile);
                }
            }

            // 2. Hapus pesanan milik pelanggan
            $pdo->prepare("DELETE FROM pesanan WHERE user_id = ?")->execute([$targetUserId]);

            // 3. Hapus akun dari tabel users
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$targetUserId]);

            $pdo->commit();
            header("Location: user.php?notif=hapus_sukses");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pesan = "Gagal menghapus pelanggan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 4. READ: AMBIL DAFTAR PELANGGAN DENGAN STATISTIK PESANAN
// =========================================================================
$search = trim($_GET["search"] ?? "");
$sql = "
    SELECT 
        u.id,
        u.nama,
        u.username,
        u.email,
        u.created_at,
        COUNT(p.id) AS total_pesanan,
        SUM(CASE WHEN p.status = 'Selesai' THEN 1 ELSE 0 END) AS pesanan_selesai,
        SUM(CASE WHEN p.status IN ('Menunggu', 'Menunggu Driver', 'Menunggu Persetujuan Driver', 'Menunggu Pembayaran', 'Diproses', 'Dalam Perjalanan') THEN 1 ELSE 0 END) AS pesanan_aktif
    FROM users u
    LEFT JOIN pesanan p ON p.user_id = u.id
    WHERE u.role = 'user'
";

$params = [];
if ($search !== "") {
    $sql .= " AND (u.nama LIKE :q OR u.username LIKE :q OR u.email LIKE :q) ";
    $params["q"] = "%" . $search . "%";
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC";

$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Metrik ringkasan
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalPesananUsers = (int) $pdo->query("SELECT COUNT(*) FROM pesanan")->fetchColumn();
$totalUserAktif = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM pesanan")->fetchColumn();

$notif = $_GET["notif"] ?? "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelanggan - Polindra Suruh</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-container">

    <!-- NAVBAR ADMIN -->
    <?php include '../includes/admin_navbar.php'; ?>

    <!-- NOTIFIKASI FEEDBACK -->
    <?php if ($notif === "tambah_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Pelanggan baru berhasil ditambahkan!</strong> Akun siap digunakan untuk memesan.</div>
    <?php elseif ($notif === "edit_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Data pelanggan berhasil diperbarui!</strong></div>
    <?php elseif ($notif === "hapus_sukses"): ?>
        <div class="alert alert-success">🗑️ <strong>Pelanggan dan riwayat terkait berhasil dihapus dari sistem!</strong></div>
    <?php endif; ?>

    <?php if ($pesan !== ""): ?>
        <div class="alert <?= $jenisPesan === "error" ? "alert-danger" : "alert-success" ?>">
            <?= $jenisPesan === "error" ? "⚠️" : "✅" ?> <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK KARTU PELANGGAN -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">👥</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Total Akun Pelanggan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">🛍️</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalUserAktif) ?></div>
                <div class="stat-label">Pelanggan Pernah Pesan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">📦</div>
            <div class="stat-info">
                <div class="stat-val"><?= number_format($totalPesananUsers) ?></div>
                <div class="stat-label">Total Semua Pesanan</div>
            </div>
        </div>
    </section>

    <!-- CARD TAMBAH PELANGGAN (CREATE) -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2 class="dash-card-title">➕ Tambah Akun Pelanggan Baru</h2>
        </div>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="tambah_user" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: Budi Santoso" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Contoh: budi123" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Contoh: budi@gmail.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Awal (Min. 8 Karakter)</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                </div>
            </div>
            <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">➕ Simpan Akun Pelanggan</button>
            </div>
        </form>
    </div>

    <!-- CARD DAFTAR PELANGGAN (READ, UPDATE, DELETE) -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2 class="dash-card-title">📋 Daftar Pelanggan Terdaftar (<?= count($users) ?>)</h2>
            <div class="dash-card-actions">
                <form method="GET" style="display: flex; gap: 8px;">
                    <input type="text" name="search" class="form-control" style="width: 240px; padding: 7px 12px; font-size: 13px;" placeholder="Cari nama, user, email..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">🔍 Cari</button>
                    <?php if ($search !== ""): ?>
                        <a href="user.php" class="btn btn-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <p style="text-align: center; color: var(--dark-muted); padding: 30px 0;">Tidak ada data pelanggan ditemukan.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Pelanggan</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Total Pesanan</th>
                            <th>Pesanan Aktif</th>
                            <th>Tgl Terdaftar</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong>#<?= $u["id"] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($u["nama"]) ?></strong>
                                </td>
                                <td><code><?= htmlspecialchars($u["username"]) ?></code></td>
                                <td><?= htmlspecialchars($u["email"]) ?></td>
                                <td>
                                    <span class="status-pill bayar">📦 <?= (int) $u["total_pesanan"] ?> pesanan</span>
                                    <?php if ((int) $u["pesanan_selesai"] > 0): ?>
                                        <span class="status-pill selesai" style="font-size: 10px; margin-left: 4px;">✔ <?= (int) $u["pesanan_selesai"] ?> selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $u["pesanan_aktif"] > 0): ?>
                                        <span class="status-pill proses">⏳ <?= (int) $u["pesanan_aktif"] ?> berjalan</span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--dark-muted);">
                                    <?= !empty($u["created_at"]) ? date("d M Y H:i", strtotime($u["created_at"])) : "-" ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="konfirmasiHapus(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'])) ?>', <?= (int)$u['total_pesanan'] ?>)">
                                        🗑️ Hapus
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL EDIT PELANGGAN -->
<div class="modal-backdrop" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Akun Pelanggan</h3>
            <button class="modal-close" onclick="tutupModal('modalEdit')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" id="editNama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="editUsername" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password (Opsional)</label>
                    <input type="password" name="password_baru" class="form-control" placeholder="Kosongkan jika password tidak ingin diubah" minlength="8">
                    <small class="form-hint">Isi hanya bila ingin mengganti password pelanggan ini.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="tutupModal('modalEdit')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS PELANGGAN -->
<div class="modal-backdrop" id="modalHapus">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger-dark);">🗑️ Konfirmasi Hapus Pelanggan</h3>
            <button class="modal-close" onclick="tutupModal('modalHapus')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="hapus_user" value="1">
            <input type="hidden" name="user_id" id="hapusUserId">
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus akun pelanggan <strong id="hapusUserNama"></strong>?</p>
                <div id="hapusUserPeringatan" class="alert alert-warning" style="margin-top: 14px; display: none;">
                    ⚠️ Pelanggan ini memiliki <strong id="hapusUserPesananCount">0</strong> riwayat pesanan. Semua pesanan dan bukti bayar terkait juga akan ikut terhapus secara otomatis.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="tutupModal('modalHapus')">Batal</button>
                <button type="submit" class="btn btn-danger">Ya, Hapus Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEdit(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editNama').value = user.nama;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('modalEdit').classList.add('active');
}

function konfirmasiHapus(id, nama, pesananCount) {
    document.getElementById('hapusUserId').value = id;
    document.getElementById('hapusUserNama').innerText = nama;
    const warnBox = document.getElementById('hapusUserPeringatan');
    if (pesananCount > 0) {
        document.getElementById('hapusUserPesananCount').innerText = pesananCount;
        warnBox.style.display = 'flex';
    } else {
        warnBox.style.display = 'none';
    }
    document.getElementById('modalHapus').classList.add('active');
}

function tutupModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Tutup modal jika user klik di backdrop
window.onclick = function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('active');
    }
};
</script>

</body>
</html>

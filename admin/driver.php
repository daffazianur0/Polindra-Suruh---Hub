<?php
session_start();
require_once "../koneksi.php";

// Pastikan hanya admin yang dapat mengakses halaman ini
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$activeAdminPage = "driver";
$pesan = "";
$jenisPesan = "";

// =========================================================================
// 1. CREATE: PROSES TAMBAH DRIVER BARU
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tambah_driver"])) {
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nama === "" || $username === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || $no_hp === "" || strlen($password) < 8) {
        $pesan = "Data tidak lengkap atau password kurang dari 8 karakter.";
        $jenisPesan = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Simpan akun login ke tabel users
            $stmtUser = $pdo->prepare("INSERT INTO users (nama, username, email, password, role, status_aktif) VALUES (?, ?, ?, ?, 'driver', 0)");
            $stmtUser->execute([$nama, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $newUserId = (int) $pdo->lastInsertId();

            // 2. Upload QRIS jika ada
            $fotoQrisName = null;
            if (isset($_FILES["foto_qris"]) && $_FILES["foto_qris"]["error"] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES["foto_qris"]["name"], PATHINFO_EXTENSION));
                if (in_array($ext, ["jpg", "jpeg", "png", "webp"]) && $_FILES["foto_qris"]["size"] <= 3 * 1024 * 1024) {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "qris" . DIRECTORY_SEPARATOR;
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fotoQrisName = "qris_driver_" . $newUserId . "_" . time() . "." . $ext;
                    move_uploaded_file($_FILES["foto_qris"]["tmp_name"], $uploadDir . $fotoQrisName);
                }
            }

            // 3. Simpan data profil ke tabel drivers
            $stmtDriver = $pdo->prepare("INSERT INTO drivers (user_id, nama_driver, no_hp, foto_qris, status_aktif) VALUES (?, ?, ?, ?, 0)");
            $stmtDriver->execute([$newUserId, $nama, $no_hp, $fotoQrisName]);

            $pdo->commit();
            header("Location: driver.php?notif=tambah_sukses");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pesan = ($e->getCode() === "23000") ? "Username atau email sudah digunakan akun lain." : "Gagal menambahkan driver: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 2. UPDATE: PROSES EDIT DATA DRIVER
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_driver"])) {
    $targetUserId = (int) ($_POST["user_id"] ?? 0);
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $statusAktif = (int) ($_POST["status_aktif"] ?? 0);
    $passwordBaru = $_POST["password_baru"] ?? "";
    $hapusQris = isset($_POST["hapus_qris"]);

    if ($targetUserId > 0 && $nama !== "" && $username !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $pdo->beginTransaction();

            // 1. Update tabel users
            if (strlen($passwordBaru) >= 8) {
                $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, email = ?, password = ?, status_aktif = ? WHERE id = ? AND role = 'driver'");
                $stmt->execute([$nama, $username, $email, password_hash($passwordBaru, PASSWORD_DEFAULT), $statusAktif, $targetUserId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, email = ?, status_aktif = ? WHERE id = ? AND role = 'driver'");
                $stmt->execute([$nama, $username, $email, $statusAktif, $targetUserId]);
            }

            // 2. Ambil foto QRIS lama
            $stmtOld = $pdo->prepare("SELECT foto_qris FROM drivers WHERE user_id = ?");
            $stmtOld->execute([$targetUserId]);
            $currentQris = $stmtOld->fetchColumn();

            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "qris" . DIRECTORY_SEPARATOR;
            $newQrisName = $currentQris;

            if ($hapusQris && $currentQris) {
                if (file_exists($uploadDir . $currentQris)) @unlink($uploadDir . $currentQris);
                $newQrisName = null;
            } elseif (isset($_FILES["foto_qris"]) && $_FILES["foto_qris"]["error"] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES["foto_qris"]["name"], PATHINFO_EXTENSION));
                if (in_array($ext, ["jpg", "jpeg", "png", "webp"]) && $_FILES["foto_qris"]["size"] <= 3 * 1024 * 1024) {
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    if ($currentQris && file_exists($uploadDir . $currentQris)) @unlink($uploadDir . $currentQris);
                    $newQrisName = "qris_driver_" . $targetUserId . "_" . time() . "." . $ext;
                    move_uploaded_file($_FILES["foto_qris"]["tmp_name"], $uploadDir . $newQrisName);
                }
            }

            // 3. Update tabel drivers
            $stmtDriver = $pdo->prepare("UPDATE drivers SET nama_driver = ?, no_hp = ?, status_aktif = ?, foto_qris = ? WHERE user_id = ?");
            $stmtDriver->execute([$nama, $no_hp, $statusAktif, $newQrisName, $targetUserId]);

            $pdo->commit();
            header("Location: driver.php?notif=edit_sukses");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pesan = "Gagal memperbarui data driver: " . $e->getMessage();
            $jenisPesan = "error";
        }
    } else {
        $pesan = "Periksa kembali data formulir pengeditan.";
        $jenisPesan = "error";
    }
}

// =========================================================================
// 3. TOGGLE STATUS AKTIF CEPAT
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_status"])) {
    $targetUserId = (int) ($_POST["user_id"] ?? 0);
    $statusBaru = (int) ($_POST["status_tujuan"] ?? 0);
    if ($targetUserId > 0) {
        $pdo->prepare("UPDATE users SET status_aktif = ? WHERE id = ? AND role = 'driver'")->execute([$statusBaru, $targetUserId]);
        $pdo->prepare("UPDATE drivers SET status_aktif = ? WHERE user_id = ?")->execute([$statusBaru, $targetUserId]);
        header("Location: driver.php?notif=status_diubah");
        exit;
    }
}

// =========================================================================
// 4. DELETE: PROSES HAPUS DRIVER
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hapus_driver"])) {
    $targetUserId = (int) ($_POST["user_id"] ?? 0);

    if ($targetUserId > 0) {
        try {
            $pdo->beginTransaction();

            // Lepaskan keterikatan driver dari pesanan agar pesanan tidak rusak
            $pdo->prepare("UPDATE pesanan SET driver_id = NULL WHERE driver_id = ?")->execute([$targetUserId]);

            // Hapus file QRIS jika ada
            $stmtOld = $pdo->prepare("SELECT foto_qris FROM drivers WHERE user_id = ?");
            $stmtOld->execute([$targetUserId]);
            $oldQris = $stmtOld->fetchColumn();
            if ($oldQris) {
                $filePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "qris" . DIRECTORY_SEPARATOR . $oldQris;
                if (file_exists($filePath)) @unlink($filePath);
            }

            // Hapus dari tabel drivers lalu users
            $pdo->prepare("DELETE FROM drivers WHERE user_id = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'driver'")->execute([$targetUserId]);

            $pdo->commit();
            header("Location: driver.php?notif=hapus_sukses");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pesan = "Gagal menghapus driver: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// Ambil notifikasi dari query string
$notif = $_GET["notif"] ?? "";

// =========================================================================
// READ: AMBIL DAFTAR LENGKAP DRIVER
// =========================================================================
$stmtDrivers = $pdo->query("
    SELECT
        u.id AS user_id,
        u.nama,
        u.username,
        u.email,
        u.created_at,
        d.id AS driver_id,
        d.nama_driver,
        d.no_hp,
        d.foto_qris,
        d.status_aktif,
        (SELECT COUNT(*) FROM pesanan WHERE driver_id = u.id) AS total_pesanan
    FROM drivers d
    INNER JOIN users u ON d.user_id = u.id
    ORDER BY d.id DESC
");
$drivers = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);
$totalDriver = count($drivers);
$driverOnline = count(array_filter($drivers, fn($d) => (int)$d["status_aktif"] === 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Driver - Polindra Suruh</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-container">

    <!-- NAVBAR ADMIN -->
    <?php include '../includes/admin_navbar.php'; ?>

    <!-- ALERT FEEDBACK -->
    <?php if ($notif === "tambah_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Akun driver baru berhasil dibuat!</strong> Driver kini terdaftar di sistem.</div>
    <?php elseif ($notif === "edit_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Data driver berhasil diperbarui!</strong></div>
    <?php elseif ($notif === "hapus_sukses"): ?>
        <div class="alert alert-warning">🗑️ <strong>Akun driver berhasil dihapus dari sistem.</strong></div>
    <?php elseif ($notif === "status_diubah"): ?>
        <div class="alert alert-info">ℹ️ <strong>Status ketersediaan driver berhasil diubah.</strong></div>
    <?php elseif ($pesan !== ""): ?>
        <div class="alert alert-<?= $jenisPesan === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK MINI DRIVER -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">🛵</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalDriver ?></div>
                <div class="stat-label">Total Driver Terdaftar</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">🟢</div>
            <div class="stat-info">
                <div class="stat-val"><?= $driverOnline ?></div>
                <div class="stat-label">Driver Sedang Online (Aktif)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">⚪</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalDriver - $driverOnline ?></div>
                <div class="stat-label">Driver Offline (Nonaktif)</div>
            </div>
        </div>
    </div>

    <!-- TABEL DATA DRIVER (CRUD) -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📋 Data Driver Polindra Suruh</span>
            </div>
            <div class="dash-card-actions">
                <button type="button" class="btn btn-primary" onclick="openModalTambah()">
                    + Buat Akun Driver Baru
                </button>
            </div>
        </div>

        <?php if ($totalDriver === 0): ?>
            <p style="text-align: center; color: #64748b; padding: 30px;">Belum ada akun driver terdaftar. Klik tombol di atas untuk membuat driver baru.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Foto QRIS</th>
                            <th>Nama Driver</th>
                            <th>Username / Email</th>
                            <th>No. WhatsApp</th>
                            <th>Status Driver</th>
                            <th>Total Antar</th>
                            <th>Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $i => $d): ?>
                            <?php
                                $hasQris = !empty($d["foto_qris"]) && file_exists(__DIR__ . "/uploads/qris/" . $d["foto_qris"]);
                                $qrisSrc = $hasQris ? "../uploads/qris/" . htmlspecialchars($d["foto_qris"]) : "";
                                $isOnline = ((int) $d["status_aktif"] === 1);
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?php if ($hasQris): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <img src="<?= $qrisSrc ?>" alt="QRIS" style="width: 38px; height: 38px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer;" onclick="openBuktiModal('<?= $qrisSrc ?>')">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openBuktiModal('<?= $qrisSrc ?>')">🔍 Lihat</button>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Belum ada</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($d["nama_driver"]) ?></strong></td>
                                <td>
                                    <div><?= htmlspecialchars($d["username"]) ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($d["email"]) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($d["no_hp"])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $d['no_hp'])) ?>" target="_blank" style="color: #047857; font-weight: 600; text-decoration: none;">
                                            💬 <?= htmlspecialchars($d["no_hp"]) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="driver.php" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= (int) $d["user_id"] ?>">
                                        <input type="hidden" name="status_tujuan" value="<?= $isOnline ? 0 : 1 ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <button type="submit" class="status-pill <?= $isOnline ? 'aktif' : 'nonaktif' ?>" style="cursor: pointer; border: none;" title="Klik untuk ubah status">
                                            <?= $isOnline ? '● Online (Aktif)' : '● Offline (Off)' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><strong><?= (int) $d["total_pesanan"] ?></strong> pesanan</td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button 
                                            type="button" 
                                            class="btn btn-warning btn-sm"
                                            onclick='openModalEdit(<?= json_encode($d) ?>)'
                                        >
                                            ✏️ Edit
                                        </button>

                                        <form method="POST" action="driver.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus driver <?= htmlspecialchars($d['nama_driver']) ?>?');" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= (int) $d["user_id"] ?>">
                                            <input type="hidden" name="hapus_driver" value="1">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                🗑️ Hapus
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

<!-- MODAL TAMBAH DRIVER -->
<div id="modalTambah" class="modal-backdrop" onclick="closeModalTambah(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">➕ Tambah Akun Driver Baru</h3>
            <button type="button" class="modal-close" onclick="closeModalTambah()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="tambahDriverForm" method="POST" action="driver.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap Driver *</label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: Budi Santoso" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="budidriver" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="budi@polindra.ac.id" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">No. WhatsApp *</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="081234567890" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password Login *</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Unggah Foto QRIS Pribadi Driver (Opsional)</label>
                    <input type="file" name="foto_qris" accept="image/jpeg,image/png,image/webp" class="form-control">
                    <p class="form-hint">Maksimal 3MB. Format JPG, PNG, WEBP.</p>
                </div>
                <input type="hidden" name="tambah_driver" value="1">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalTambah()">Batal</button>
            <button type="submit" form="tambahDriverForm" class="btn btn-primary">+ Simpan Akun Driver</button>
        </div>
    </div>
</div>


<!-- MODAL EDIT DRIVER -->
<div id="modalEdit" class="modal-backdrop" onclick="closeModalEdit(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Profil & Akun Driver</h3>
            <button type="button" class="modal-close" onclick="closeModalEdit()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editDriverForm" method="POST" action="driver.php" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap Driver *</label>
                    <input type="text" name="nama" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">No. WhatsApp *</label>
                        <input type="text" name="no_hp" id="edit_no_hp" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Ketersediaan</label>
                        <select name="status_aktif" id="edit_status_aktif" class="form-control">
                            <option value="1">Online (Aktif / Menerima Pesanan)</option>
                            <option value="0">Offline (Nonaktif)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Ganti Password (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="password_baru" class="form-control" placeholder="Minimal 8 karakter jika ingin diganti">
                </div>
                <div class="form-group">
                    <label class="form-label">Ganti Foto QRIS Driver (Opsional)</label>
                    <input type="file" name="foto_qris" accept="image/jpeg,image/png,image/webp" class="form-control">
                    <div id="edit_qris_preview_container" style="margin-top: 8px; display: none;">
                        <label style="font-size: 12px; color: #dc2626; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="hapus_qris" value="1">
                            Hapus foto QRIS yang terpasang saat ini
                        </label>
                    </div>
                </div>
                <input type="hidden" name="edit_driver" value="1">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalEdit()">Batal</button>
            <button type="submit" form="editDriverForm" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </div>
</div>

<!-- MODAL LIGHTBOX FOTO QRIS -->
<div id="modalBukti" class="modal-backdrop" onclick="closeBuktiModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">📷 Foto QRIS Driver</h3>
            <button type="button" class="modal-close" onclick="closeBuktiModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; background: #f8fafc;">
            <img id="imgBuktiModal" src="" alt="QRIS" style="max-width: 100%; max-height: 60vh; border-radius: 8px;">
        </div>
        <div class="modal-footer">
            <a id="linkBuktiDownload" href="" target="_blank" class="btn btn-primary btn-sm">Buka Gambar Penuh</a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeBuktiModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
function openModalTambah() {
    document.getElementById('modalTambah').classList.add('active');
}
function closeModalTambah() {
    document.getElementById('modalTambah').classList.remove('active');
}

function openModalEdit(data) {
    document.getElementById('edit_user_id').value = data.user_id;
    document.getElementById('edit_nama').value = data.nama_driver || data.nama;
    document.getElementById('edit_username').value = data.username;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_no_hp').value = data.no_hp || '';
    document.getElementById('edit_status_aktif').value = data.status_aktif;
    
    var qrisContainer = document.getElementById('edit_qris_preview_container');
    if (data.foto_qris) {
        qrisContainer.style.display = 'block';
    } else {
        qrisContainer.style.display = 'none';
    }
    document.getElementById('modalEdit').classList.add('active');
}
function closeModalEdit() {
    document.getElementById('modalEdit').classList.remove('active');
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
        closeModalTambah();
        closeModalEdit();
        closeBuktiModal();
    }
});
</script>

</body>
</html>

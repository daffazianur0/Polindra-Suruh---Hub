<?php
session_start();
require_once "../koneksi.php";

// Pastikan hanya admin yang dapat mengakses halaman ini
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$activeAdminPage = "layanan";
$pesan = "";
$jenisPesan = "";
$notif = $_GET["notif"] ?? "";

// =========================================================================
// 1. CREATE: PROSES TAMBAH LAYANAN BARU
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tambah_layanan"])) {
    $nama = trim($_POST["nama"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $icon = trim($_POST["icon"] ?? "📦");
    $nomor = trim($_POST["nomor"] ?? "");
    $urutan = (int) ($_POST["urutan"] ?? 0);
    $statusAktif = isset($_POST["status_aktif"]) ? 1 : 0;

    if ($nama === "" || $deskripsi === "") {
        $pesan = "Nama layanan dan deskripsi wajib diisi.";
        $jenisPesan = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO layanan (nama, deskripsi, icon, nomor, urutan, status_aktif)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nama, $deskripsi, $icon ?: "📦", $nomor ?: "01", $urutan, $statusAktif]);

            header("Location: layanan.php?notif=tambah_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal menambahkan layanan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 2. UPDATE: PROSES EDIT LAYANAN
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_layanan"])) {
    $layananId = (int) ($_POST["layanan_id"] ?? 0);
    $nama = trim($_POST["nama"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    $icon = trim($_POST["icon"] ?? "📦");
    $nomor = trim($_POST["nomor"] ?? "");
    $urutan = (int) ($_POST["urutan"] ?? 0);
    $statusAktif = isset($_POST["status_aktif"]) ? 1 : 0;

    if ($layananId <= 0 || $nama === "" || $deskripsi === "") {
        $pesan = "Nama layanan dan deskripsi wajib diisi.";
        $jenisPesan = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE layanan
                SET nama = ?, deskripsi = ?, icon = ?, nomor = ?, urutan = ?, status_aktif = ?
                WHERE id = ?
            ");
            $stmt->execute([$nama, $deskripsi, $icon ?: "📦", $nomor ?: "01", $urutan, $statusAktif, $layananId]);

            header("Location: layanan.php?notif=edit_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal memperbarui layanan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 3. TOGGLE STATUS AKTIF / NONAKTIF
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_status"])) {
    $layananId = (int) ($_POST["layanan_id"] ?? 0);
    if ($layananId > 0) {
        try {
            $stmtCek = $pdo->prepare("SELECT status_aktif FROM layanan WHERE id = ?");
            $stmtCek->execute([$layananId]);
            $currentStatus = $stmtCek->fetchColumn();

            if ($currentStatus !== false) {
                $newStatus = ($currentStatus == 1) ? 0 : 1;
                $stmtToggle = $pdo->prepare("UPDATE layanan SET status_aktif = ? WHERE id = ?");
                $stmtToggle->execute([$newStatus, $layananId]);

                header("Location: layanan.php?notif=status_diubah");
                exit;
            }
        } catch (PDOException $e) {
            $pesan = "Gagal mengubah status: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 4. DELETE: PROSES HAPUS LAYANAN
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hapus_layanan"])) {
    $layananId = (int) ($_POST["layanan_id"] ?? 0);
    if ($layananId > 0) {
        try {
            $stmtHapus = $pdo->prepare("DELETE FROM layanan WHERE id = ?");
            $stmtHapus->execute([$layananId]);

            header("Location: layanan.php?notif=hapus_sukses");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal menghapus layanan: " . $e->getMessage();
            $jenisPesan = "error";
        }
    }
}

// =========================================================================
// 5. QUERY DATA LAYANAN DENGAN FILTER & SEARCH
// =========================================================================
$search = trim($_GET["q"] ?? "");
$filterStatus = $_GET["status"] ?? "all";

$sql = "SELECT * FROM layanan WHERE 1=1";
$params = [];

if ($search !== "") {
    $sql .= " AND (nama LIKE ? OR deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterStatus === "aktif") {
    $sql .= " AND status_aktif = 1";
} elseif ($filterStatus === "nonaktif") {
    $sql .= " AND status_aktif = 0";
}

$sql .= " ORDER BY urutan ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarLayanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$totalLayanan = (int) $pdo->query("SELECT COUNT(*) FROM layanan")->fetchColumn();
$layananAktif = (int) $pdo->query("SELECT COUNT(*) FROM layanan WHERE status_aktif = 1")->fetchColumn();
$layananNonaktif = (int) $pdo->query("SELECT COUNT(*) FROM layanan WHERE status_aktif = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Layanan - Polindra Suruh</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-container">

    <!-- NAVBAR ADMIN -->
    <?php include '../includes/admin_navbar.php'; ?>

    <!-- ALERT FEEDBACK -->
    <?php if ($notif === "tambah_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Layanan baru berhasil ditambahkan!</strong> Layanan kini siap ditampilkan di halaman Layanan.</div>
    <?php elseif ($notif === "edit_sukses"): ?>
        <div class="alert alert-success">✅ <strong>Data layanan berhasil diperbarui!</strong></div>
    <?php elseif ($notif === "hapus_sukses"): ?>
        <div class="alert alert-warning">🗑️ <strong>Layanan berhasil dihapus dari sistem.</strong></div>
    <?php elseif ($notif === "status_diubah"): ?>
        <div class="alert alert-info">ℹ️ <strong>Status keterlihatan layanan berhasil diubah.</strong></div>
    <?php elseif ($pesan !== ""): ?>
        <div class="alert alert-<?= $jenisPesan === 'error' ? 'danger' : 'success' ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK MINI LAYANAN -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">🛠️</div>
            <div class="stat-info">
                <div class="stat-val"><?= $totalLayanan ?></div>
                <div class="stat-label">Total Layanan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">🟢</div>
            <div class="stat-info">
                <div class="stat-val"><?= $layananAktif ?></div>
                <div class="stat-label">Layanan Aktif (Tampil)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">⚪</div>
            <div class="stat-info">
                <div class="stat-val"><?= $layananNonaktif ?></div>
                <div class="stat-label">Layanan Nonaktif (Disembunyikan)</div>
            </div>
        </div>
    </div>

    <!-- TABEL DATA LAYANAN (CRUD) -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2 class="dash-card-title">📋 Daftar Layanan Polindra Suruh</h2>
            <div class="dash-card-actions">
                <button type="button" class="btn btn-primary" onclick="openModalTambah()">
                    ➕ Tambah Layanan Baru
                </button>
            </div>
        </div>

        <!-- FORM FILTER & PENCARIAN -->
        <form method="GET" action="layanan.php" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <input 
                type="text" 
                name="q" 
                class="form-control" 
                placeholder="Cari nama atau deskripsi layanan..." 
                value="<?= htmlspecialchars($search) ?>"
                style="flex: 1 1 240px;"
            >
            <select name="status" class="form-control" style="flex: 0 1 180px;">
                <option value="all" <?= $filterStatus === "all" ? "selected" : "" ?>>Semua Status</option>
                <option value="aktif" <?= $filterStatus === "aktif" ? "selected" : "" ?>>🟢 Hanya Aktif</option>
                <option value="nonaktif" <?= $filterStatus === "nonaktif" ? "selected" : "" ?>>⚪ Hanya Nonaktif</option>
            </select>
            <button type="submit" class="btn btn-secondary">🔍 Filter</button>
            <?php if ($search !== "" || $filterStatus !== "all"): ?>
                <a href="layanan.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <?php if (empty($daftarLayanan)): ?>
            <p style="text-align: center; color: var(--dark-muted); padding: 30px;">Tidak ada data layanan yang ditemukan.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Urut</th>
                            <th style="width: 60px;">Icon</th>
                            <th>Nama Layanan</th>
                            <th>Deskripsi</th>
                            <th style="width: 100px;">Nomor</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 180px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftarLayanan as $layanan): ?>
                            <tr>
                                <td><strong>#<?= (int) $layanan["urutan"] ?></strong></td>
                                <td style="font-size: 24px; text-align: center;"><?= htmlspecialchars($layanan["icon"] ?: "📦") ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($layanan["nama"]) ?></strong>
                                    <div style="font-size: 11px; color: var(--dark-muted);">ID: <?= (int) $layanan["id"] ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #334155; max-width: 380px; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars($layanan["deskripsi"])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-size: 13px; background: #f1f5f9; padding: 2px 8px; border-radius: 6px; font-weight: bold;">
                                        <?= htmlspecialchars($layanan["nomor"] ?: "-") ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($layanan["status_aktif"] == 1): ?>
                                        <span class="status-pill aktif">● Tampil Aktif</span>
                                    <?php else: ?>
                                        <span class="status-pill nonaktif">○ Disembunyikan</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <!-- TOGGLE STATUS -->
                                        <form method="POST" action="layanan.php" style="display: inline;">
                                            <input type="hidden" name="layanan_id" value="<?= (int) $layanan["id"] ?>">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <button 
                                                type="submit" 
                                                class="btn <?= $layanan["status_aktif"] == 1 ? 'btn-secondary' : 'btn-success' ?> btn-sm"
                                                title="<?= $layanan["status_aktif"] == 1 ? 'Sembunyikan Layanan' : 'Aktifkan Layanan' ?>"
                                            >
                                                <?= $layanan["status_aktif"] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>

                                        <!-- EDIT -->
                                        <button 
                                            type="button" 
                                            class="btn btn-warning btn-sm"
                                            onclick='openModalEdit(<?= json_encode($layanan, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            title="Edit Layanan"
                                        >
                                            ✏️ Edit
                                        </button>

                                        <!-- HAPUS -->
                                        <form method="POST" action="layanan.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan \'<?= htmlspecialchars(addslashes($layanan['nama'])) ?>\' ?');" style="display: inline;">
                                            <input type="hidden" name="layanan_id" value="<?= (int) $layanan["id"] ?>">
                                            <input type="hidden" name="hapus_layanan" value="1">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus Layanan">
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

<!-- MODAL TAMBAH LAYANAN -->
<div id="modalTambah" class="modal-backdrop" onclick="closeModalTambah(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">➕ Tambah Layanan Baru</h3>
            <button type="button" class="modal-close" onclick="closeModalTambah()">&times;</button>
        </div>
        <form method="POST" action="layanan.php">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nama Layanan</label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Jasa Antar Makanan" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icon / Emoji</label>
                        <input type="text" name="icon" class="form-control" placeholder="Contoh: 🍔, 📦, 🚐, 📝, 🛵" value="📦">
                        <small style="color: var(--dark-muted); font-size: 11px;">Bisa berupa emoji atau simbol.</small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Label Nomor Urut</label>
                        <input type="text" name="nomor" class="form-control" placeholder="Contoh: 01, 02, 05" value="01">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prioritas Urutan Tampil (Angka)</label>
                        <input type="number" name="urutan" class="form-control" placeholder="1, 2, 3..." value="<?= $totalLayanan + 1 ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi Layanan</label>
                    <textarea name="deskripsi" class="form-control" rows="4" placeholder="Jelaskan apa yang ditawarkan pada layanan ini..." required></textarea>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="status_aktif" id="tambah_status_aktif" value="1" checked style="width: 18px; height: 18px;">
                    <label for="tambah_status_aktif" style="font-weight: 600; cursor: pointer;">Tampilkan langsung di halaman Layanan (Status Aktif)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalTambah()">Batal</button>
                <button type="submit" name="tambah_layanan" class="btn btn-primary">Simpan Layanan Baru</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT LAYANAN -->
<div id="modalEdit" class="modal-backdrop" onclick="closeModalEdit(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Data Layanan</h3>
            <button type="button" class="modal-close" onclick="closeModalEdit()">&times;</button>
        </div>
        <form method="POST" action="layanan.php">
            <input type="hidden" name="layanan_id" id="edit_layanan_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nama Layanan</label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icon / Emoji</label>
                        <input type="text" name="icon" id="edit_icon" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Label Nomor Urut</label>
                        <input type="text" name="nomor" id="edit_nomor" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prioritas Urutan Tampil (Angka)</label>
                        <input type="number" name="urutan" id="edit_urutan" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi Layanan</label>
                    <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="status_aktif" id="edit_status_aktif" value="1" style="width: 18px; height: 18px;">
                    <label for="edit_status_aktif" style="font-weight: 600; cursor: pointer;">Tampilkan di halaman Layanan (Status Aktif)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModalEdit()">Batal</button>
                <button type="submit" name="edit_layanan" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModalTambah() {
    document.getElementById('modalTambah').classList.add('active');
}
function closeModalTambah(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('modalTambah').classList.remove('active');
}

function openModalEdit(data) {
    document.getElementById('edit_layanan_id').value = data.id;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_deskripsi').value = data.deskripsi;
    document.getElementById('edit_icon').value = data.icon || '📦';
    document.getElementById('edit_nomor').value = data.nomor || '01';
    document.getElementById('edit_urutan').value = data.urutan || 0;
    document.getElementById('edit_status_aktif').checked = (data.status_aktif == 1);
    
    document.getElementById('modalEdit').classList.add('active');
}
function closeModalEdit(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('modalEdit').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('modalTambah').classList.remove('active');
        document.getElementById('modalEdit').classList.remove('active');
    }
});
</script>

</body>
</html>

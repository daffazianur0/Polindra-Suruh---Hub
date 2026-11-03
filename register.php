
<?php
session_start();
require_once "koneksi.php";

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nama === "" || $username === "" || $email === "" || $password === "") {
        $pesan = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = "Format email tidak valid.";
    } elseif (strlen($password) < 8) {
        $pesan = "Password minimal 8 karakter.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (nama, username, email, password, role)
                 VALUES (?, ?, ?, ?, 'user')"
            );

            $stmt->execute([$nama, $username, $email, $hash]);

            header("Location: login.php?daftar=berhasil");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $pesan = "Username atau email sudah digunakan.";
            } else {
                $pesan = "Registrasi gagal. Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Polindra Suruh</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            overflow: hidden;
        }

        /* ===== LEFT: FORM PANEL (Mirrored from login) ===== */
        .auth-form-panel {
            flex: 0 0 500px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 50px 48px;
            background: #fff;
            position: relative;
            overflow-y: auto;
        }

        .auth-form-panel::before {
            content: "";
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ===== RIGHT: BACKGROUND IMAGE PANEL ===== */
        .auth-visual {
            flex: 1.1;
            position: relative;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
        }

        .auth-visual::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url("assets/img/background.jpeg") center / cover no-repeat;
            transform: scale(1.05);
            filter: brightness(0.6) saturate(1.2);
        }

        .auth-visual::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(15, 23, 42, 0.95) 0%,
                rgba(15, 23, 42, 0.35) 40%,
                rgba(245, 158, 11, 0.1) 100%
            );
        }

        .visual-content {
            position: relative;
            z-index: 2;
            padding: 50px 48px;
            color: #fff;
            max-width: 540px;
        }

        .visual-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            background: rgba(255, 210, 31, 0.15);
            border: 1px solid rgba(255, 210, 31, 0.3);
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            color: #ffd21f;
            letter-spacing: 1px;
            margin-bottom: 18px;
            backdrop-filter: blur(6px);
        }

        .visual-badge span.dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 8px #4ade80;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .5; transform: scale(1.3); }
        }

        .visual-content h1 {
            font-size: clamp(28px, 3vw, 40px);
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 14px;
        }

        .visual-content h1 span {
            color: #ffd21f;
        }

        .visual-content p {
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            line-height: 1.7;
            font-weight: 500;
        }

        .visual-features {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .visual-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
        }

        .visual-feature .icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255, 210, 31, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* ===== SHARED FORM STYLES ===== */
        .auth-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
        }

        .auth-logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffd21f, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        }

        .auth-logo-text {
            font-size: 12px;
            font-weight: 800;
            line-height: 1.15;
            color: #0f172a;
        }

        .auth-logo-text span { display: block; }

        .auth-heading h2 {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .auth-heading p {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 28px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.25s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: #f59e0b;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.12);
        }

        .form-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .form-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffd21f 0%, #f59e0b 100%);
            color: #0f172a;
            font-size: 15px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
            margin-top: 6px;
            letter-spacing: 0.3px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .auth-footer {
            margin-top: 22px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .auth-footer a {
            color: #d97706;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }

        .auth-footer a:hover {
            color: #b45309;
            text-decoration: underline;
        }

        .auth-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            font-size: 12px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-back:hover {
            color: #64748b;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 600;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 960px) {
            body { flex-direction: column-reverse; overflow: auto; }

            .auth-visual {
                min-height: 240px;
                flex: none;
            }

            .visual-content { padding: 30px 28px; }
            .visual-content h1 { font-size: 22px; }
            .visual-features { display: none; }

            .auth-form-panel {
                flex: none;
                padding: 32px 28px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 480px) {
            .auth-visual { min-height: 180px; }
            .visual-content { padding: 20px; }
            .auth-form-panel { padding: 28px 20px; }
        }
    </style>
</head>
<body>

    <!-- LEFT: FORM PANEL -->
    <div class="auth-form-panel">
        <div class="auth-logo">
            <div class="auth-logo-icon">PS</div>
            <div class="auth-logo-text">
                <span>POLINDRA</span>
                <span>SURUH</span>
            </div>
        </div>

        <div class="auth-heading">
            <h2>Buat Akun Baru ✨</h2>
            <p>Daftar sekarang untuk menikmati semua layanan Polindra Suruh.</p>
        </div>

        <?php if ($pesan !== ""): ?>
            <div class="alert">
                ⚠️ <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-input" placeholder="Contoh: Budi Santoso" maxlength="100" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" placeholder="budisantoso" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="budi@email.com" maxlength="100" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Buat password minimal 8 karakter" minlength="8" required>
                <div class="form-hint">Gunakan kombinasi huruf besar, kecil, dan angka untuk keamanan.</div>
            </div>

            <button type="submit" class="btn-submit">Daftar Sekarang →</button>
        </form>

        <div class="divider">atau</div>

        <div class="auth-footer">
            Sudah punya akun?
            <a href="login.php">Masuk di sini</a>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="auth-back">← Kembali ke Beranda</a>
        </div>
    </div>

    <!-- RIGHT: VISUAL PANEL -->
    <div class="auth-visual">
        <div class="visual-content">
            <div class="visual-badge">
                <span class="dot"></span>
                BERGABUNG SEKARANG
            </div>

            <h1>
                Gabung di<br>
                <span>Polindra Suruh</span><br>
                Yuk! 🚀
            </h1>

            <p>
                Daftar gratis dan nikmati berbagai kemudahan layanan
                jasa suruh yang dirancang khusus untuk civitas
                Politeknik Negeri Indramayu.
            </p>

            <div class="visual-features">
                <div class="visual-feature">
                    <div class="icon">🍔</div>
                    <span>Jasa belanja makanan & minuman</span>
                </div>
                <div class="visual-feature">
                    <div class="icon">📦</div>
                    <span>Antar-jemput barang & dokumen</span>
                </div>
                <div class="visual-feature">
                    <div class="icon">🏠</div>
                    <span>Info kost & kontrakan sekitar kampus</span>
                </div>
                <div class="visual-feature">
                    <div class="icon">📝</div>
                    <span>Bantuan tugas & keperluan akademik</span>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

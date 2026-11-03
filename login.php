
<?php
session_start();
require_once "koneksi.php";

$tujuan = [
    "admin" => "admin/index.php",
    "driver" => "driver.php",
    "user" => "index.php"
];

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    $role = $_SESSION["role"];

    if (isset($tujuan[$role])) {
        header("Location: " . $tujuan[$role]);
        exit;
    }
}

$pesan = isset($_GET["daftar"])
    ? "Registrasi berhasil. Silakan login."
    : "";

$pesanType = isset($_GET["daftar"]) ? "success" : "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {
        $pesan = "Username/email dan password wajib diisi.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, nama, username, email, password, role
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1"
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (
            $user &&
            password_verify($password, $user["password"]) &&
            isset($tujuan[$user["role"]])
        ) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["nama"] = $user["nama"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "user" &&
                ($_POST["redirect"] ?? "") === "order") {
                header("Location: order.php");
                exit;
            }

            if ($user["role"] === "user" &&
                ($_POST["redirect"] ?? "") === "pilih_driver") {
                header("Location: pilih_driver.php");
                exit;
            }

            header("Location: " . $tujuan[$user["role"]]);
            exit;
        }

        $pesan = "Username/email atau password salah.";
    }
}

$redirect = $_GET["redirect"] ?? $_POST["redirect"] ?? "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Polindra Suruh</title>

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

        /* ===== LEFT: BACKGROUND IMAGE PANEL ===== */
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
            filter: brightness(0.65) saturate(1.2);
        }

        .auth-visual::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(15, 23, 42, 0.95) 0%,
                rgba(15, 23, 42, 0.4) 40%,
                rgba(245, 158, 11, 0.08) 100%
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
            font-size: clamp(30px, 3.2vw, 42px);
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

        .visual-stats {
            display: flex;
            gap: 28px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .visual-stat strong {
            display: block;
            font-size: 22px;
            font-weight: 900;
            color: #ffd21f;
        }

        .visual-stat span {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
        }

        /* ===== RIGHT: FORM PANEL ===== */
        .auth-form-panel {
            flex: 0 0 480px;
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
            top: -100px;
            right: -100px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 210, 31, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }

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

        /* Alert messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 18px;
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
            padding: 13px 16px;
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
            margin-top: 24px;
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
            margin-top: 16px;
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
            margin: 20px 0;
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
            body { flex-direction: column; overflow: auto; }

            .auth-visual {
                min-height: 280px;
                flex: none;
            }

            .visual-content {
                padding: 30px 28px;
            }

            .visual-content h1 { font-size: 24px; }
            .visual-stats { display: none; }

            .auth-form-panel {
                flex: none;
                padding: 32px 28px;
            }
        }

        @media (max-width: 480px) {
            .auth-visual { min-height: 200px; }
            .visual-content { padding: 24px 20px; }
            .auth-form-panel { padding: 28px 20px; }
        }
    </style>
</head>
<body>

    <!-- LEFT: VISUAL PANEL -->
    <div class="auth-visual">
        <div class="visual-content">
            <div class="visual-badge">
                <span class="dot"></span>
                POLINDRA SURUH-HUB
            </div>

            <h1>
                Jasa Suruh<br>
                <span>Apa Saja</span> <br>
               
            </h1>

            <p>
                POLINDRA SURUH SELALU ADA UNTUK
                KALIAN 
                
            </p>

            <div class="visual-stats">
                <div class="visual-stat">
                    <strong>Gercep</strong>
                    <span>Respons Cepat</span>
                </div>
                
                <div class="visual-stat">
                    <strong>100%</strong>
                    <span>Driver Mahasiswa</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: FORM PANEL -->
    <div class="auth-form-panel">
        <div class="auth-logo">
            <div class="auth-logo-icon">PS</div>
            <div class="auth-logo-text">
                <span>POLINDRA</span>
                <span>SURUH</span>
            </div>
        </div>

        <div class="auth-heading">
            <h2>Selamat Datang! 👋</h2>
            <p>Masuk ke akunmu untuk melanjutkan menggunakan layanan.</p>
        </div>

        <?php if ($pesan !== ""): ?>
            <div class="alert alert-<?= $pesanType ?>">
                <?= $pesanType === 'success' ? '✅' : '⚠️' ?>
                <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="form-group">
                <label class="form-label">Username atau Email</label>
                <input type="text" name="login" class="form-input" placeholder="Masukkan username atau email" autocomplete="username" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-submit">Masuk ke Akun →</button>
        </form>

        <div class="divider">atau</div>

        <div class="auth-footer">
            Belum punya akun?
            <a href="register.php">Daftar Sekarang</a>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="auth-back">← Kembali ke Beranda</a>
        </div>
    </div>

</body>
</html>

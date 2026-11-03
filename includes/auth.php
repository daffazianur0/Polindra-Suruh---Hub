<?php
/**
 * includes/auth.php
 * Helper autentikasi & guard role.
 *
 * Fungsi:
 *   requireLogin()        - Redirect ke login jika belum login
 *   requireRole($role)    - Redirect jika role tidak sesuai
 *   redirectIfLoggedIn()  - Redirect ke dashboard sesuai role jika sudah login
 */

/**
 * Pastikan user sudah login. Jika belum, redirect ke halaman login.
 *
 * @param string $redirect  Query param redirect opsional (contoh: "order")
 */
function requireLogin(string $redirect = ''): void
{
    if (!isset($_SESSION['user_id'])) {
        $url = '../login.php';
        if ($redirect !== '') {
            $url .= '?redirect=' . urlencode($redirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Pastikan user memiliki role tertentu.
 * Jika tidak sesuai, redirect ke halaman yang tepat.
 *
 * @param string $requiredRole  Role yang diperlukan: 'admin'|'driver'|'user'
 */
function requireRole(string $requiredRole): void
{
    $role = $_SESSION['role'] ?? '';

    if ($role === $requiredRole) {
        return;
    }

    // Arahkan ke dashboard sesuai role yang aktif
    $map = [
        'admin'  => '../admin/index.php',
        'driver' => '../driver/index.php',
        'user'   => '../index.php',
    ];

    $target = $map[$role] ?? '../login.php';
    header('Location: ' . $target);
    exit;
}

/**
 * Jika sudah login, redirect ke dashboard sesuai role.
 * Dipakai di halaman login & register.
 */
function redirectIfLoggedIn(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        return;
    }

    $map = [
        'admin'  => 'admin/index.php',
        'driver' => 'driver/index.php',
        'user'   => 'index.php',
    ];

    $role   = $_SESSION['role'];
    $target = $map[$role] ?? 'index.php';
    header('Location: ' . $target);
    exit;
}

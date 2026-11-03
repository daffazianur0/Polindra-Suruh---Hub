<?php
/**
 * Konfigurasi Aplikasi Polindra Suruh
 * Pengaturan API Google Maps, Midtrans Payment Gateway, dan Tarif Ongkir
 */

// --- Base URL Aplikasi ---
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . $host . '/polindrasuruh/');
}

// --- Pengaturan Google Maps API ---
// Dapatkan API Key dari Google Cloud Console dengan mengaktifkan:
// 1. Maps JavaScript API
// 2. Places API
// 3. Distance Matrix API
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'YOUR_GOOGLE_MAPS_API_KEY');
}

// --- Pengaturan Midtrans Payment Gateway ---
// Mode: Sandbox (testing) atau Production (live)
if (!defined('MIDTRANS_IS_PRODUCTION')) {
    define('MIDTRANS_IS_PRODUCTION', false);
}

// Masukkan Server Key & Client Key dari Dashboard Midtrans Sandbox / Production
if (!defined('MIDTRANS_SERVER_KEY')) {
    define('MIDTRANS_SERVER_KEY', getenv('MIDTRANS_SERVER_KEY') ?: 'SB-Mid-server-xWzNnU0gR3tO-y3M2vJ8V0K_');
}

if (!defined('MIDTRANS_CLIENT_KEY')) {
    define('MIDTRANS_CLIENT_KEY', getenv('MIDTRANS_CLIENT_KEY') ?: 'SB-Mid-client-W2_6E6v90nQ_yTa1');
}

if (!defined('MIDTRANS_MERCHANT_ID')) {
    define('MIDTRANS_MERCHANT_ID', getenv('MIDTRANS_MERCHANT_ID') ?: 'G123456789');
}

// Midtrans Snap URL
if (MIDTRANS_IS_PRODUCTION) {
    define('MIDTRANS_SNAP_URL', 'https://app.midtrans.com/snap/snap.js');
    define('MIDTRANS_SNAP_API_URL', 'https://app.midtrans.com/snap/v1/transactions');
} else {
    define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js');
    define('MIDTRANS_SNAP_API_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
}

// --- Pengaturan Tarif Ongkir Sesuai PRD ---
// Rumus: Total Ongkir = Base Fare (Rp 3.000) + (ceil(Distance KM) * Rate Per KM (Rp 2.000))
if (!defined('BASE_FARE')) {
    define('BASE_FARE', 3000); // Rp 3.000
}

if (!defined('PER_KM_RATE')) {
    define('PER_KM_RATE', 2000); // Rp 2.000
}

/**
 * Fungsi pembantu untuk menghitung tarif ongkir sesuai formula PRD
 * Total Fare = Base Fare (Rp 3,000) + (ceil(Distance KM) * Per KM Rate (Rp 2,000))
 * 
 * @param float $distanceKm
 * @return int
 */
function hitungOngkir($distanceKm) {
    $kmBulat = max(1, (int) ceil((float) $distanceKm));
    return (int) (BASE_FARE + ($kmBulat * PER_KM_RATE));
}


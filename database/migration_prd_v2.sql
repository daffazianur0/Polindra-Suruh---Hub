-- =====================================================================
-- Migration Script: Polindra Suruh - PRD 2.0 Distance & Payment Updates
-- Tanggal: 31 Oktober 2026
-- =====================================================================

USE `polindra_suruh`;

-- 1. Tambah kolom origin_location, destination_location, distance_km, total_harga, snap_token, payment_status jika belum ada
ALTER TABLE `pesanan`
    ADD COLUMN IF NOT EXISTS `origin_location` TEXT NULL AFTER `alamat`,
    ADD COLUMN IF NOT EXISTS `destination_location` TEXT NULL AFTER `origin_location`,
    ADD COLUMN IF NOT EXISTS `distance_km` DECIMAL(5,2) DEFAULT 0.00 AFTER `destination_location`,
    ADD COLUMN IF NOT EXISTS `total_harga` INT DEFAULT 0 AFTER `distance_km`,
    ADD COLUMN IF NOT EXISTS `snap_token` VARCHAR(255) NULL AFTER `total_harga`,
    ADD COLUMN IF NOT EXISTS `payment_status` ENUM('pending', 'paid', 'failed', 'cod') DEFAULT 'pending' AFTER `snap_token`;

-- 2. Pastikan nilai default dan indeks
CREATE INDEX IF NOT EXISTS `idx_pesanan_payment_status` ON `pesanan` (`payment_status`);


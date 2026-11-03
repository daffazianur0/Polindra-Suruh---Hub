-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 14, 2026 at 09:49 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `polindra_suruh`
--

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nama_driver` varchar(100) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto_qris` varchar(255) DEFAULT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `user_id`, `nama_driver`, `no_hp`, `foto_qris`, `status_aktif`, `created_at`) VALUES
(4, 14, 'nopal', '083194189185', 'qris_driver_14_1791934578.jpeg', 1, '2026-10-13 23:36:18');

-- --------------------------------------------------------

--
-- Table structure for table `layanan`
--

CREATE TABLE `layanan` (
  `id` int NOT NULL,
  `nama` varchar(150) NOT NULL,
  `deskripsi` text NOT NULL,
  `icon` varchar(50) DEFAULT '?',
  `nomor` varchar(10) DEFAULT '01',
  `urutan` int DEFAULT '0',
  `status_aktif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `layanan`
--

INSERT INTO `layanan` (`id`, `nama`, `deskripsi`, `icon`, `nomor`, `urutan`, `status_aktif`, `created_at`) VALUES
(1, 'Jasa Belanja Makanan', 'Mau makan tapi malas keluar? Kami siap membelikan dan mengantarkan makanan sesuai pesananmu.', '🍔', '01', 1, 1, '2026-10-13 21:22:59'),
(2, 'Antar-Jemput Barang', 'Membutuhkan bantuan untuk mengantar atau mengambil barang maupun dokumen? Kami siap membantu.', '📦', '02', 2, 1, '2026-10-13 21:22:59'),
(3, 'Info Kost/Kontrakan', 'Sedang mencari kost atau kontrakan? Kami bisa membantu mencari informasi sesuai kebutuhanmu.', '🚐', '03', 3, 1, '2026-10-13 21:22:59'),
(4, 'Jasa Tugas', 'Membutuhkan bantuan untuk pekerjaan atau tugas harian? Sampaikan kebutuhanmu kepada Polindra Suruh.', '📝', '04', 4, 1, '2026-10-13 21:22:59');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `layanan` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `origin_location` text,
  `origin_lat` decimal(10,8) DEFAULT NULL,
  `origin_lng` decimal(11,8) DEFAULT NULL,
  `destination_location` text,
  `destination_lat` decimal(10,8) DEFAULT NULL,
  `destination_lng` decimal(11,8) DEFAULT NULL,
  `distance_km` decimal(5,2) DEFAULT '0.00',
  `total_harga` decimal(10,2) DEFAULT '0.00',
  `snap_token` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','cod') NOT NULL DEFAULT 'pending',
  `catatan` text,
  `metode_bayar` enum('qris','cash') NOT NULL DEFAULT 'qris',
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `qris_diverifikasi` tinyint(1) NOT NULL DEFAULT '0',
  `alasan_tolak_bayar` varchar(255) DEFAULT NULL,
  `waktu_bayar` datetime DEFAULT NULL,
  `status` enum('Menunggu','Menunggu Driver','Menunggu Persetujuan Driver','Menunggu Pembayaran','Diproses','Dalam Perjalanan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu Driver',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `driver_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `user_id`, `layanan`, `alamat`, `catatan`, `metode_bayar`, `bukti_bayar`, `qris_diverifikasi`, `alasan_tolak_bayar`, `waktu_bayar`, `status`, `created_at`, `driver_id`) VALUES
(39, 4, '-', '-', 'sss', 'qris', NULL, 0, NULL, NULL, 'Dibatalkan', '2026-10-13 23:37:21', NULL),
(40, 4, '-', '-', 'sssssss', 'qris', NULL, 1, NULL, '2026-10-14 10:25:18', 'Selesai', '2026-10-14 03:24:39', 14),
(41, 16, '-', '-', 'beli kopi', 'qris', NULL, 1, NULL, '2026-10-14 15:22:23', 'Selesai', '2026-10-14 08:20:20', 14);

-- --------------------------------------------------------

--
-- Table structure for table `pesanan_baru`
--

CREATE TABLE `pesanan_baru` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `driver_id` int DEFAULT NULL,
  `layanan` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `catatan` text,
  `status` enum('Menunggu','Diproses','Dalam Perjalanan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','driver','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`, `created_at`, `status_aktif`) VALUES
(1, 'admin', 'admin', 'septianfarel048@gmail.com', '$2y$10$SenznuNJ5Mrb1HkjmGLAWeynbKYR0.lznxc3B97UQpzt0VUxLVCs6', 'admin', '2026-10-12 16:45:48', 0),
(4, 'parel', 'payey', 'vian@gmail.com', '$2y$10$8Em2IhkreAfvxYd3KB.s3eYjP3WAC2bmlTRK.FvVixUgtauEauMX6', 'user', '2026-10-12 16:51:28', 0),
(7, 'rehan', 'rehan', 'rehan@gmail.com', '$2y$10$0/L8ipYppqC7mR6yfIzimu3UM0gTmYUn2.2p/nGRaYo9OufP7tTou', 'user', '2026-10-13 18:39:55', 0),
(14, 'nopal', 'nopal', 'nopak@gmail.com', '$2y$10$IjlJjeC5s08fNpqfew8bg.nYguiIM7Li0HphH7pVPHmjJIuQwKF2.', 'driver', '2026-10-13 23:36:18', 1),
(16, 'viann', 'viann', 'viann@gmail.com', '$2y$10$wqJQZBQVTbSK8kb1feaO4OwEJ0pEqEEf5qVBqBOnMzUk9ZQeoZdTe', 'user', '2026-10-14 08:19:14', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `layanan`
--
ALTER TABLE `layanan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_pesanan_driver` (`driver_id`);

--
-- Indexes for table `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `layanan`
--
ALTER TABLE `layanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `fk_driver_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  ADD CONSTRAINT `pesanan_baru_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pesanan_baru_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

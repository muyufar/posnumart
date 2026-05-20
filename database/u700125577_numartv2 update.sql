-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 20, 2026 at 06:50 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u700125577_numartv2`
--

-- --------------------------------------------------------
--
-- Table structure for table `wa_blast_history`
--

CREATE TABLE `wa_blast_history` (
  `id` int(11) NOT NULL,
  `cabang` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `message_template` text NOT NULL,
  `total_recipients` int(11) DEFAULT 0,
  `blast_type` varchar(50) DEFAULT 'manual',
  `filter_criteria` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wa_blast_history`
--

INSERT INTO `wa_blast_history` (`id`, `cabang`, `user_id`, `message_template`, `total_recipients`, `blast_type`, `filter_criteria`, `created_at`) VALUES
(1, 5, 64, 'BELANJA MURAH', 1, 'below_target', NULL, '2026-05-11 09:34:46'),
(2, 5, 64, 'Halo {nama_customer}! 👋\n\nSudah lama tidak berbelanja di toko kami. Kami kangen dengan Anda! 😊\n\nKunjungi kami untuk melihat produk-produk terbaru.\n\nSalam hangat,\nNumart', 1, 'all', NULL, '2026-05-11 09:36:20'),
(3, 0, 16, 'Halo {nama_customer}! 👋\n\nSudah lama tidak berbelanja di toko kami. Kami kangen dengan Anda! 😊\n\nKunjungi kami untuk melihat produk-produk terbaru.\n\nSalam hangat,\nNumart', 1, 'all', NULL, '2026-05-14 05:26:55'),
(4, 5, 70, 'Halo {nama_customer}! 👋\n\nSudah lama tidak berbelanja di toko kami. Kami kangen dengan Anda! 😊\n\nKunjungi kami untuk melihat produk-produk terbaru.\n\nSalam hangat,\nNumart', 1, 'below_target', NULL, '2026-05-14 09:41:06'),
(5, 1, 33, 'Halo {nama_customer}! 🙏\n\nTerima kasih telah menjadi pelanggan setia kami. Total belanja Anda bulan ini: Rp {total_belanja}\n\nKami sangat menghargai kepercayaan Anda!\n\nSalam,\nNumart', 1, 'all', NULL, '2026-05-14 16:35:01'),
(6, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'below_target', NULL, '2026-05-15 05:38:58'),
(7, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'below_target', NULL, '2026-05-15 05:39:21'),
(8, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'below_target', NULL, '2026-05-15 05:39:56'),
(9, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'below_target', NULL, '2026-05-15 05:40:18'),
(10, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:46:49'),
(11, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:47:18'),
(12, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:48:29'),
(13, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:49:01'),
(14, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:49:25'),
(15, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 05:49:49'),
(16, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik komunitas.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:06:43'),
(17, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:08:17'),
(18, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:12:52'),
(19, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:13:42'),
(20, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:14:16'),
(21, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di NUMART DUKUN Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-15 06:23:38'),
(22, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 24, 'all', NULL, '2026-05-15 06:33:31'),
(23, 1, 33, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 25, 'all', NULL, '2026-05-15 12:15:17'),
(24, 5, 62, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 25, 'all', NULL, '2026-05-18 02:08:39'),
(25, 5, 62, 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, 'all', NULL, '2026-05-18 06:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `wa_blast_recipients`
--

CREATE TABLE `wa_blast_recipients` (
  `id` int(11) NOT NULL,
  `blast_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wa_blast_recipients`
--

INSERT INTO `wa_blast_recipients` (`id`, `blast_id`, `customer_id`, `customer_phone`, `status`, `sent_at`, `created_at`) VALUES
(1, 23, 12359, '628388434269', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(2, 23, 12456, '6281563366358', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(3, 23, 12507, '6282134519118', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(4, 23, 12525, '6281575906683', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(5, 23, 12576, '6285292538181', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(6, 23, 12623, '6285643752590', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(7, 23, 12624, '6285773326484', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(8, 23, 12769, '6287772553346', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(9, 23, 12805, '6282328349551', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(10, 23, 12831, '62895358884177', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(11, 23, 12833, '6281328008987', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(12, 23, 12840, '6281210166478', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(13, 23, 12850, '6285888244138', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(14, 23, 12859, '6282137090957', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(15, 23, 12863, '6281611170141', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(16, 23, 12865, '6285713638330', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(17, 23, 12885, '6281229330078', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(18, 23, 12886, '6285712800616', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(19, 23, 12897, '628886462932', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(20, 23, 12909, '6282220929062', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(21, 23, 12917, '6285801282353', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(22, 23, 12924, '6285713527830', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(23, 23, 12927, '6287736703425', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(24, 23, 12930, '6287842352589', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(25, 23, 12933, '6283195758126', 'sent', '2026-05-15 12:15:17', '2026-05-15 12:15:17'),
(26, 24, 11542, '6285701668699', 'sent', '2026-05-18 02:08:41', '2026-05-18 02:08:41'),
(27, 24, 11544, '6285842997817', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(28, 24, 11545, '6281578133914', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(29, 24, 11549, '6281578509826', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(30, 24, 11550, '6281328516638', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(31, 24, 11555, '6281392468223', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(32, 24, 11557, '6285799250468', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(33, 24, 11559, '62813251077651', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(34, 24, 11560, '6285643955373', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(35, 24, 11564, '6285743999579', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(36, 24, 11566, '6285842707628', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(37, 24, 11569, '6285727121367', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(38, 24, 11571, '6285729562833', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(39, 24, 11574, '628765443567', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(40, 24, 11575, '6289655334512', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(41, 24, 11577, '6285801379049', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(42, 24, 11578, '6285725901012', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(43, 24, 11583, '6289612630176', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(44, 24, 11584, '6285701476098', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(45, 24, 11585, '6282243813939', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(46, 24, 11587, '6285868776345', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(47, 24, 11590, '628122779449', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(48, 24, 11591, '6285866247709', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(49, 24, 11596, '6285800124943', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(50, 24, 11608, '6289649649673', 'sent', '2026-05-18 02:08:43', '2026-05-18 02:08:43'),
(51, 25, 11612, '6285877765944', 'sent', '2026-05-18 06:37:46', '2026-05-18 06:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `wa_blast_send_settings`
--

CREATE TABLE `wa_blast_send_settings` (
  `cabang` int(11) NOT NULL,
  `max_contacts_per_batch` int(11) NOT NULL DEFAULT 25 COMMENT 'Maks kontak per satu kali kirim',
  `min_interval_minutes` int(11) NOT NULL DEFAULT 120 COMMENT 'Jeda minimal antar sesi (menit)',
  `delay_seconds_per_contact` int(11) NOT NULL DEFAULT 3 COMMENT 'Jeda antar nomor dalam satu sesi',
  `last_send_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wa_blast_send_settings`
--

INSERT INTO `wa_blast_send_settings` (`cabang`, `max_contacts_per_batch`, `min_interval_minutes`, `delay_seconds_per_contact`, `last_send_at`, `updated_at`) VALUES
(2, 25, 120, 3, '2026-05-19 12:58:01', '2026-05-19 05:58:01'),
(5, 25, 120, 60, '2026-05-18 13:37:46', '2026-05-18 06:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `wa_templates`
--

CREATE TABLE `wa_templates` (
  `id` int(11) NOT NULL,
  `cabang` int(11) NOT NULL DEFAULT 0,
  `template_name` varchar(100) NOT NULL,
  `template_content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wa_templates`
--

INSERT INTO `wa_templates` (`id`, `cabang`, `template_name`, `template_content`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0, 'Promosi Umum', 'Halo {nama_customer}! 🛒\n\nKami memiliki promo menarik untuk Anda! Kunjungi toko kami untuk mendapatkan diskon spesial.\n\nSalam,\nNumart', 1, '2026-01-20 16:52:38', '2026-01-20 16:52:38'),
(2, 0, 'Reminder Belanja', 'Halo {nama_customer}! 👋\n\nSudah lama tidak berbelanja di toko kami. Kami kangen dengan Anda! 😊\n\nKunjungi kami untuk melihat produk-produk terbaru.\n\nSalam hangat,\nNumart', 1, '2026-01-20 16:52:38', '2026-01-20 16:52:38'),
(3, 0, 'Ucapan Terima Kasih', 'Halo {nama_customer}! 🙏\n\nTerima kasih telah menjadi pelanggan setia kami. Total belanja Anda bulan ini: Rp {total_belanja}\n\nKami sangat menghargai kepercayaan Anda!\n\nSalam,\nNumart', 1, '2026-01-20 16:52:38', '2026-01-20 16:52:38'),
(4, 0, 'Info Stok Baru', 'Halo {nama_customer}! 🆕\n\nKami baru saja mendapatkan stok produk baru yang mungkin Anda sukai!\n\nKunjungi toko kami segera sebelum kehabisan.\n\nSalam,\nNumart', 1, '2026-01-20 16:52:38', '2026-01-20 16:52:38'),
(5, 0, 'Salam Pembuka', 'Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nHalo Sahabat NUMART {nama_customer} 😊\nTerima kasih atas kepercayaan Anda yang telah berbelanja bersama {nama_toko}. Dukungan Anda sangat berarti dalam menggerakkan ekonomi umat dan memperkuat usaha milik Nahdlatul Ulama.\nYuk, terus penuhi kebutuhan sehari-hari Anda di {nama_toko}. Belanja Anda bukan sekadar transaksi, tetapi juga bagian dari kontribusi untuk kemajuan bersama.\nTerima kasih atas loyalitas dan dukungan Anda.\n\nWassalamu’alaikum Warahmatullahi Wabarakatuh.', 1, '2026-05-15 03:10:08', '2026-05-15 06:17:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `toko`
--
ALTER TABLE `toko`
  ADD PRIMARY KEY (`toko_id`);

--
-- Indexes for table `transfer`
--
ALTER TABLE `transfer`
  ADD PRIMARY KEY (`transfer_id`);

--
-- Indexes for table `transfer_produk_keluar`
--
ALTER TABLE `transfer_produk_keluar`
  ADD PRIMARY KEY (`tpk_id`);

--
-- Indexes for table `transfer_produk_masuk`
--
ALTER TABLE `transfer_produk_masuk`
  ADD PRIMARY KEY (`tpm_id`);

--
-- Indexes for table `transfer_select_cabang`
--
ALTER TABLE `transfer_select_cabang`
  ADD PRIMARY KEY (`tsc_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `wa_auto_below_target_sent`
--
ALTER TABLE `wa_auto_below_target_sent`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cabang_cust_period` (`cabang`,`customer_id`,`period_yyyymm`);

--
-- Indexes for table `wa_auto_target_reminder_settings`
--
ALTER TABLE `wa_auto_target_reminder_settings`
  ADD PRIMARY KEY (`cabang`);

--
-- Indexes for table `wa_blast_history`
--
ALTER TABLE `wa_blast_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wa_blast_recipients`
--
ALTER TABLE `wa_blast_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blast_id` (`blast_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `wa_blast_send_settings`
--
ALTER TABLE `wa_blast_send_settings`
  ADD PRIMARY KEY (`cabang`);

--
-- Indexes for table `wa_templates`
--
ALTER TABLE `wa_templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `transfer`
--
ALTER TABLE `transfer`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6859;

--
-- AUTO_INCREMENT for table `transfer_produk_keluar`
--
ALTER TABLE `transfer_produk_keluar`
  MODIFY `tpk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86215;

--
-- AUTO_INCREMENT for table `transfer_produk_masuk`
--
ALTER TABLE `transfer_produk_masuk`
  MODIFY `tpm_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142175;

--
-- AUTO_INCREMENT for table `transfer_select_cabang`
--
ALTER TABLE `transfer_select_cabang`
  MODIFY `tsc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7425;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `wa_auto_below_target_sent`
--
ALTER TABLE `wa_auto_below_target_sent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wa_blast_history`
--
ALTER TABLE `wa_blast_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `wa_blast_recipients`
--
ALTER TABLE `wa_blast_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `wa_templates`
--
ALTER TABLE `wa_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

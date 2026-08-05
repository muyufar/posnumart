-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: u700125577_numartv2
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `laba`
--

DROP TABLE IF EXISTS `laba`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laba` (
  `id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0:in\r\n1:out',
  `jenis_transaksi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `akun_debit` int DEFAULT NULL,
  `akun_kredit` int DEFAULT NULL,
  `nominal` decimal(15,2) DEFAULT '0.00',
  `bunga` decimal(5,2) DEFAULT '0.00',
  `pajak` decimal(5,2) DEFAULT '0.00',
  `total` decimal(15,2) DEFAULT NULL,
  `jumlah` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `keterangan` tinytext COLLATE utf8mb4_unicode_ci,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_lampiran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabang` int NOT NULL,
  `date` datetime DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jenis_transaksi` (`jenis_transaksi`),
  KEY `idx_akun_debit` (`akun_debit`),
  KEY `idx_akun_kredit` (`akun_kredit`),
  KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `laba_kategori`
--

DROP TABLE IF EXISTS `laba_kategori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laba_kategori` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `level` tinyint NOT NULL DEFAULT '4' COMMENT '1:kepala 1 coa,2:kepala 2 coa,3 kepala 3 coa, 4:sub coa',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_name` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '1:kas tunai, 2:kas BRI',
  `kode_akun` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('aktiva','pasiva','modal','pendapatan','beban') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_akun` enum('debit','kredit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo` decimal(15,2) NOT NULL,
  `cabang` int DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cabang` (`cabang`)
) ENGINE=InnoDB AUTO_INCREMENT=4656 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-04 22:56:27

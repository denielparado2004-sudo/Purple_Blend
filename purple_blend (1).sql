-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2026 at 07:43 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `purple_blend`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('manager','staff') NOT NULL DEFAULT 'manager',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Admin', '', '', '$2y$10$S.PH44bvxp2s/0OUZMLeBu0wUxQnAXyxc.gnHmunckbMrlEhMt99y', 'manager', 1, '2026-10-27 01:58:36', '2026-10-27 02:14:34');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `area` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `phone`, `created_at`, `area`) VALUES
(1, 'Deniel', 'Parado', 'paradodeniel81@gmail.com', '$2y$10$OAvwjwY.ALQHlO9j5y8vkeCNu.xDEUQXEkhWHCCVDccYQ9FOwMq42', '09072861965', '2026-10-29 09:43:04', NULL),
(2, 'Deniel', 'Parado', 'dhannelparado@gmail.com', '$2y$10$0SsGMIJoZonU.A73BpdileZKZY6y4pOb7w2GeqJKdthDpdAfz0QQ2', '09072861965', '2026-10-29 09:43:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `variant_group` char(32) DEFAULT NULL,
  `size_label` varchar(20) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `product_code`, `name`, `price`, `category`, `variant_group`, `size_label`, `image_url`, `stock`, `is_available`, `created_at`) VALUES
(1, 'PB-001', 'Spaghetti', 20.00, 'Meryenda / Dessert', NULL, NULL, 'images/21031ed2629624570c0b5b2b29aafaa7.jpg', 2, 1, '2026-10-27 05:25:43'),
(2, 'PB-002', 'Chicken pastil (no rice)', 40.00, 'Ulam', NULL, NULL, 'images/b4bd23a4c797bac89b9d35dbd4657138.jpg', 7, 1, '2026-10-27 14:28:44'),
(3, 'PB-003', 'Pancit', 20.00, 'Meryenda / Dessert', NULL, NULL, 'images/8e331ebef75131079ee9418917840fc5.jpg', 17, 1, '2026-10-27 14:29:51'),
(4, 'PB-004', 'Shanghai', 5.00, 'Meryenda / Dessert', NULL, NULL, 'images/a92f7933d92905c77ca2b64c0e8492ad.jpg', 10, 1, '2026-10-27 14:32:48'),
(5, 'PB-005', 'Bananaque', 20.00, 'Meryenda / Dessert', NULL, NULL, 'images/40052ac89230200ddb396702f65c8018.jpg', 6, 1, '2026-10-27 14:36:43'),
(6, 'PB-006', 'Palamig', 10.00, 'Drinks', NULL, NULL, 'images/ae598ac25c743f53401948d3d0f7ef21.jpg', 0, 1, '2026-10-27 14:39:55'),
(7, 'PB-007', 'Chicken Pastil (with rice)', 55.00, 'Ulam', NULL, NULL, 'images/c0291e5f89ea1e5328d70123a740f27a.jpg', 8, 1, '2026-10-27 14:42:45'),
(9, 'PB-008', 'Coffee Jelly', 10.00, 'Drinks', NULL, NULL, 'images/b5b138c8618f591437381e6af3774584.jpg', 17, 1, '2026-10-27 14:49:50'),
(10, 'PB-009', 'Dinakdakan (with rice)', 55.00, 'Ulam', NULL, NULL, 'images/0c55f983b87d9ff946da85f1ef820d0b.jpg', 17, 1, '2026-10-27 14:51:12'),
(11, 'PB-010', 'Dinakdakan (no rice)', 40.00, 'Ulam', NULL, NULL, 'images/d0f89419b449f849a96195a58799b0ca.jpg', 8, 1, '2026-10-27 14:53:37'),
(12, 'PB-011', 'Siomai', 5.00, 'Meryenda / Dessert', NULL, NULL, 'images/7606f64aa93dde9d235ffed9812c1871.jpg', 2, 1, '2026-10-27 14:55:17'),
(13, 'PB-012', 'Toron', 10.00, 'Meryenda / Dessert', NULL, NULL, 'images/815ff4351fa5b49fbfc9cbc6169571fc.jpg', 13, 1, '2026-10-27 14:56:21'),
(14, 'PB-013', 'Fried Isaw', 5.00, 'Meryenda / Dessert', NULL, NULL, 'images/57a52223fa75098eb1075e3a84111cd1.jpg', 0, 1, '2026-10-27 15:16:27'),
(16, 'PB-014', 'Chocolate', 39.00, 'Milk Tea', '1f0639b5b5aac31b7cdcd23c4d0cbea0', '12 oz', 'images/1855a02705ac0fee93249093ea748507.jpg', 20, 1, '2026-10-28 04:53:02'),
(17, 'PB-015', 'Chocolate', 49.00, 'Milk Tea', '1f0639b5b5aac31b7cdcd23c4d0cbea0', '16 oz', 'images/1855a02705ac0fee93249093ea748507.jpg', 20, 1, '2026-10-28 04:53:02'),
(18, 'PB-016', 'Chocolate', 59.00, 'Milk Tea', '1f0639b5b5aac31b7cdcd23c4d0cbea0', '22 oz', 'images/1855a02705ac0fee93249093ea748507.jpg', 0, 1, '2026-10-28 04:53:02'),
(19, 'PB-017', 'Cokies & Cream', 39.00, 'Milk Tea', 'e192c153c99648f27ee6c1f4e0f1f0e6', '12 oz', 'images/ee644ce7cbb38fb19d74db01f458f436.jpg', 20, 1, '2026-10-28 04:56:46'),
(20, 'PB-018', 'Cokies & Cream', 49.00, 'Milk Tea', 'e192c153c99648f27ee6c1f4e0f1f0e6', '16 oz', 'images/ee644ce7cbb38fb19d74db01f458f436.jpg', 20, 1, '2026-10-28 04:56:46'),
(21, 'PB-019', 'Cokies & Cream', 59.00, 'Milk Tea', 'e192c153c99648f27ee6c1f4e0f1f0e6', '22 oz', 'images/ee644ce7cbb38fb19d74db01f458f436.jpg', 20, 1, '2026-10-28 04:56:46'),
(22, 'PB-020', 'Chocolate Kisses', 30.00, 'Milk Tea', '83c1a9d5d630f30f3e8211801cc7e829', '12 oz', 'images/eb9e54b59b20265de704d19d4610019f.jpg', 20, 1, '2026-10-28 04:57:52'),
(23, 'PB-021', 'Chocolate Kisses', 49.00, 'Milk Tea', '83c1a9d5d630f30f3e8211801cc7e829', '16 oz', 'images/eb9e54b59b20265de704d19d4610019f.jpg', 20, 1, '2026-10-28 04:57:52'),
(24, 'PB-022', 'Chocolate Kisses', 58.85, 'Milk Tea', '83c1a9d5d630f30f3e8211801cc7e829', '22 oz', 'images/eb9e54b59b20265de704d19d4610019f.jpg', 0, 1, '2026-10-28 04:57:52'),
(25, 'PB-023', 'Double Chocolate', 39.00, 'Milk Tea', '0b4f9af946ad5dea62d862c8f4647a5f', '12 oz', 'images/2d31fab11f5a92e468b9b4605b305ca4.jpg', 20, 1, '2026-10-28 04:59:24'),
(26, 'PB-024', 'Double Chocolate', 49.00, 'Milk Tea', '0b4f9af946ad5dea62d862c8f4647a5f', '16 oz', 'images/2d31fab11f5a92e468b9b4605b305ca4.jpg', 20, 1, '2026-10-28 04:59:24'),
(27, 'PB-025', 'Double Chocolate', 59.00, 'Milk Tea', '0b4f9af946ad5dea62d862c8f4647a5f', '22 oz', 'images/2d31fab11f5a92e468b9b4605b305ca4.jpg', 20, 1, '2026-10-28 04:59:24'),
(28, 'PB-026', 'Double Dutch', 39.00, 'Milk Tea', '6281a920efaebd9c89bb141f1c0e7c29', '12 oz', 'images/5126ca6d6706ed7bc009d1590ee22f72.jpg', 20, 1, '2026-10-28 05:00:23'),
(29, 'PB-027', 'Double Dutch', 49.00, 'Milk Tea', '6281a920efaebd9c89bb141f1c0e7c29', '16 oz', 'images/5126ca6d6706ed7bc009d1590ee22f72.jpg', 20, 1, '2026-10-28 05:00:23'),
(30, 'PB-028', 'Double Dutch', 59.00, 'Milk Tea', '6281a920efaebd9c89bb141f1c0e7c29', '22 oz', 'images/5126ca6d6706ed7bc009d1590ee22f72.jpg', 20, 1, '2026-10-28 05:00:23'),
(31, 'PB-029', 'Red Velvet', 39.00, 'Milk Tea', 'd6526543b9d786e0203c8bdb7def86cd', '12 oz', 'images/c339197b3225ba8876ed4e89fcd1446b.jpg', 19, 1, '2026-10-28 05:01:22'),
(32, 'PB-030', 'Red Velvet', 49.00, 'Milk Tea', 'd6526543b9d786e0203c8bdb7def86cd', '16 oz', 'images/c339197b3225ba8876ed4e89fcd1446b.jpg', 20, 1, '2026-10-28 05:01:22'),
(33, 'PB-031', 'Red Velvet', 59.00, 'Milk Tea', 'd6526543b9d786e0203c8bdb7def86cd', '22 oz', 'images/c339197b3225ba8876ed4e89fcd1446b.jpg', 20, 1, '2026-10-28 05:01:22'),
(34, 'PB-032', 'Dark Chocolate', 39.00, 'Milk Tea', '6074b89736088926adfc8e11626e7988', '12 oz', 'images/d3559e617ba48191dcc249ef84b60dc3.jpg', 20, 1, '2026-10-28 05:02:05'),
(35, 'PB-033', 'Dark Chocolate', 49.00, 'Milk Tea', '6074b89736088926adfc8e11626e7988', '16 oz', 'images/d3559e617ba48191dcc249ef84b60dc3.jpg', 20, 1, '2026-10-28 05:02:05'),
(36, 'PB-034', 'Dark Chocolate', 59.00, 'Milk Tea', '6074b89736088926adfc8e11626e7988', '22 oz', 'images/d3559e617ba48191dcc249ef84b60dc3.jpg', 20, 1, '2026-10-28 05:02:05'),
(37, 'PB-035', 'Black Forest', 39.00, 'Milk Tea', 'a25b419f1f5fc31250a9708f3b33af81', '12 oz', 'images/1f8aaa714fdd7559a36b395360f8642c.jpg', 20, 1, '2026-10-28 05:03:30'),
(38, 'PB-036', 'Black Forest', 49.00, 'Milk Tea', 'a25b419f1f5fc31250a9708f3b33af81', '16 oz', 'images/1f8aaa714fdd7559a36b395360f8642c.jpg', 20, 1, '2026-10-28 05:03:30'),
(39, 'PB-037', 'Black Forest', 59.00, 'Milk Tea', 'a25b419f1f5fc31250a9708f3b33af81', '22 oz', 'images/1f8aaa714fdd7559a36b395360f8642c.jpg', 20, 1, '2026-10-28 05:03:30'),
(40, 'PB-038', 'Wintermelon', 39.00, 'Milk Tea', '317088e89fb85ac6c02bad21038a2b8d', '12 oz', 'images/dee6695fe58b8763bd21661a50a53b18.jpg', 19, 1, '2026-10-28 05:04:26'),
(41, 'PB-039', 'Wintermelon', 49.00, 'Milk Tea', '317088e89fb85ac6c02bad21038a2b8d', '16 oz', 'images/dee6695fe58b8763bd21661a50a53b18.jpg', 20, 1, '2026-10-28 05:04:26'),
(42, 'PB-040', 'Wintermelon', 59.00, 'Milk Tea', '317088e89fb85ac6c02bad21038a2b8d', '22 oz', 'images/dee6695fe58b8763bd21661a50a53b18.jpg', 20, 1, '2026-10-28 05:04:26'),
(43, 'PB-041', 'Cheesecake', 39.00, 'Milk Tea', '1953d683bbd797c667c49eb372bdeff8', '12 oz', 'images/ceb46f73a8f4733f88162f77c662363e.jpg', 19, 1, '2026-10-28 05:05:47'),
(44, 'PB-042', 'Cheesecake', 49.00, 'Milk Tea', '1953d683bbd797c667c49eb372bdeff8', '16 oz', 'images/ceb46f73a8f4733f88162f77c662363e.jpg', 20, 1, '2026-10-28 05:05:47'),
(45, 'PB-043', 'Cheesecake', 59.00, 'Milk Tea', '1953d683bbd797c667c49eb372bdeff8', '22 oz', 'images/ceb46f73a8f4733f88162f77c662363e.jpg', 20, 1, '2026-10-28 05:05:47'),
(46, 'PB-044', 'Matcha', 39.00, 'Milk Tea', '1f2bed4fa2386e6ca821847839707acb', '12 oz', 'images/2268c5bc09b2b6143cee28532b0e1509.jpg', 19, 1, '2026-10-28 05:06:35'),
(47, 'PB-045', 'Matcha', 49.00, 'Milk Tea', '1f2bed4fa2386e6ca821847839707acb', '16 oz', 'images/2268c5bc09b2b6143cee28532b0e1509.jpg', 20, 1, '2026-10-28 05:06:35'),
(48, 'PB-046', 'Matcha', 59.00, 'Milk Tea', '1f2bed4fa2386e6ca821847839707acb', '22 oz', 'images/2268c5bc09b2b6143cee28532b0e1509.jpg', 20, 1, '2026-10-28 05:06:35'),
(49, 'PB-047', 'Java Chips', 39.00, 'Milk Tea', '90c9c157a68d975dc43a0ab6cf4a54e5', '12 oz', 'images/bbedc32d33d7cbe7ca6e568a0d16b56e.jpg', 20, 1, '2026-10-28 05:07:16'),
(50, 'PB-048', 'Java Chips', 49.00, 'Milk Tea', '90c9c157a68d975dc43a0ab6cf4a54e5', '16 oz', 'images/bbedc32d33d7cbe7ca6e568a0d16b56e.jpg', 20, 1, '2026-10-28 05:07:16'),
(51, 'PB-049', 'Java Chips', 59.00, 'Milk Tea', '90c9c157a68d975dc43a0ab6cf4a54e5', '22 oz', 'images/bbedc32d33d7cbe7ca6e568a0d16b56e.jpg', 20, 1, '2026-10-28 05:07:16'),
(52, 'PB-050', 'Hokkaido', 39.00, 'Milk Tea', '3bbe93f3f75b38aa081b46717bf56fb2', '12 oz', 'images/aeb8813218ca92ce9e8c5743cfcc7cb2.jpg', 20, 1, '2026-10-28 05:08:23'),
(53, 'PB-051', 'Hokkaido', 49.00, 'Milk Tea', '3bbe93f3f75b38aa081b46717bf56fb2', '16 oz', 'images/aeb8813218ca92ce9e8c5743cfcc7cb2.jpg', 20, 1, '2026-10-28 05:08:23'),
(54, 'PB-052', 'Hokkaido', 59.00, 'Milk Tea', '3bbe93f3f75b38aa081b46717bf56fb2', '22 oz', 'images/aeb8813218ca92ce9e8c5743cfcc7cb2.jpg', 20, 1, '2026-10-28 05:08:23'),
(55, 'PB-053', 'Okinnawa', 39.00, 'Milk Tea', 'a41a4f85e998a992992d05aec5fd758e', '12 oz', 'images/f8369a5b026a4efb1c3f424fae832cf2.jpg', 17, 1, '2026-10-28 05:09:08'),
(56, 'PB-054', 'Okinnawa', 49.00, 'Milk Tea', 'a41a4f85e998a992992d05aec5fd758e', '16 oz', 'images/f8369a5b026a4efb1c3f424fae832cf2.jpg', 20, 1, '2026-10-28 05:09:08'),
(57, 'PB-055', 'Okinnawa', 59.00, 'Milk Tea', 'a41a4f85e998a992992d05aec5fd758e', '22 oz', 'images/f8369a5b026a4efb1c3f424fae832cf2.jpg', 20, 1, '2026-10-28 05:09:08'),
(58, 'PB-056', 'Hershey\'s', 39.00, 'Milk Tea', 'f86239cd9e760db1e0a69aebe32c51af', '12 oz', 'images/859846e61741a882db6fc588fce92787.jpg', 18, 1, '2026-10-28 05:09:56'),
(59, 'PB-057', 'Hershey\'s', 49.00, 'Milk Tea', 'f86239cd9e760db1e0a69aebe32c51af', '16 oz', 'images/859846e61741a882db6fc588fce92787.jpg', 20, 1, '2026-10-28 05:09:56'),
(60, 'PB-058', 'Hershey\'s', 59.00, 'Milk Tea', 'f86239cd9e760db1e0a69aebe32c51af', '22 oz', 'images/859846e61741a882db6fc588fce92787.jpg', 20, 1, '2026-10-28 05:09:56'),
(61, 'PB-059', 'Taro', 39.00, 'Milk Tea', '2c5c8d21ee30fb3811aaf5282c7fc225', '12 oz', 'images/fce77f947eefb6a1407f6525f83791a4.jpg', 13, 1, '2026-10-28 05:10:35'),
(62, 'PB-060', 'Taro', 49.00, 'Milk Tea', '2c5c8d21ee30fb3811aaf5282c7fc225', '16 oz', 'images/fce77f947eefb6a1407f6525f83791a4.jpg', 20, 1, '2026-10-28 05:10:35'),
(63, 'PB-061', 'Taro', 59.00, 'Milk Tea', '2c5c8d21ee30fb3811aaf5282c7fc225', '22 oz', 'images/fce77f947eefb6a1407f6525f83791a4.jpg', 20, 1, '2026-10-28 05:10:35'),
(64, 'PB-062', 'Maxx Red (5 pieces 5 peso)', 5.00, 'Candy\'s', NULL, NULL, 'images/a2da9c516489d431d5f47d8538685a26.jpg', 35, 1, '2026-10-29 13:09:53'),
(65, 'PB-063', 'Champi (5 pieces 5 peso)', 5.00, 'Candy\'s', NULL, NULL, 'images/3d6616c226205ee4e0c57e7c3f178cb9.jpg', 40, 1, '2026-10-29 13:12:13'),
(66, 'PB-064', 'Judge (Red and Green 5 pieces 5 peso)', 5.00, 'Candy\'s', NULL, NULL, 'images/467d0d5b235745dd813824f0612ab01a.jpg', 40, 1, '2026-10-29 13:13:39'),
(67, 'PB-065', 'VFresh (Red and Green 5 pieces 5 peso)', 5.00, 'Candy\'s', NULL, NULL, 'images/b012d5837a9e804240c368821b262b7b.jpg', 30, 1, '2026-10-29 13:15:43'),
(68, 'PB-067', 'Pink Lava', 20.00, 'Biscuits', NULL, NULL, 'images/827de4a3b94b896078c0739c623c1ca5.jpg', 4, 1, '2026-10-29 16:48:41'),
(69, 'PB-068', 'Richoco Cookies & Cream', 20.00, 'Biscuits', NULL, NULL, 'images/1ed7c01d395e7e800dcbcd1c112abe24.jpg', 1, 1, '2026-10-29 16:50:07'),
(70, 'PB-069', 'Wafello Chocolate', 20.00, 'Biscuits', NULL, NULL, 'images/e338a8830574437bd37fe3e612ee6cdb.jpg', 1, 1, '2026-10-29 16:51:22'),
(71, 'PB-070', 'Cal Cheese', 20.00, 'Biscuits', NULL, NULL, 'images/e8798b86324835603f844b3514d3ca33.jpg', 1, 1, '2026-10-29 16:51:55'),
(72, 'PB-071', 'Crossini', 20.00, 'Biscuits', NULL, NULL, 'images/93929cac96b1a2393411f89ea153eb01.jpg', 8, 1, '2026-10-29 16:52:37'),
(73, 'PB-072', 'Rebisco Combi', 10.00, 'Biscuits', NULL, NULL, 'images/42c8c0efd2cb73a39481d55ede913ca9.jpg', 3, 1, '2026-10-29 16:54:26'),
(74, 'PB-073', 'Presto Cookies Vanilla', 10.00, 'Biscuits', NULL, NULL, 'images/5d453b67e42f579c78563312d40eaa30.jpg', 3, 1, '2026-10-29 16:55:29'),
(75, 'PB-074', 'SkyFlakes', 10.00, 'Biscuits', NULL, NULL, 'images/c7da675a6094712fdcac9d23f49831b1.jpg', 4, 1, '2026-10-29 16:56:11'),
(76, 'PB-075', 'Cheese Bar', 15.00, 'Biscuits', NULL, NULL, 'images/d89df94111cca30f3f1226282f55493a.jpg', 6, 1, '2026-10-29 16:57:15'),
(77, 'PB-076', 'Coke', 15.00, 'Beverage', NULL, NULL, 'images/98233b868573e6e0747d9de0579073b4.jpg', 10, 1, '2026-10-29 16:59:22'),
(78, 'PB-077', 'Mountain Dew', 25.00, 'Beverage', NULL, NULL, 'images/06acedccaefa9184942c8b690fab809b.jpg', 10, 1, '2026-10-29 17:00:07'),
(79, 'PB-078', 'Sprite', 15.00, 'Beverage', NULL, NULL, 'images/4d37636fe124ad2496e8f3c97e63e945.jpg', 10, 1, '2026-10-29 17:00:33'),
(80, 'PB-079', 'Royal', 15.00, 'Beverage', NULL, NULL, 'images/bdabbb6256fd5e3866cf8a71d52df029.jpg', 10, 1, '2026-10-29 17:03:21'),
(81, 'PB-080', 'Yakult', 15.00, 'Beverage', NULL, NULL, 'images/31cf12418467cadaf6f8b2c83a5a528a.jpg', 10, 1, '2026-10-29 17:04:25'),
(82, 'PB-081', 'Dutchmill', 20.00, 'Beverage', NULL, NULL, 'images/dbcac3b3c7d34e3b2d865113900a97ca.jpg', 10, 1, '2026-10-29 17:05:46'),
(83, 'PB-082', 'Chuckie', 40.00, 'Beverage', NULL, NULL, 'images/b8a844537ed4b73e0fedf7f48069d7f9.jpg', 10, 1, '2026-10-29 17:06:24'),
(84, 'PB-083', 'Minute Maid', 15.00, 'Beverage', NULL, NULL, 'images/fcb27a0527ccfd43d361496463b3e839.jpg', 10, 1, '2026-10-29 17:07:05'),
(85, 'PB-084', 'Delight', 20.00, 'Beverage', NULL, NULL, 'images/980618d7615546734d4f2b77c30d6c33.jpg', 10, 1, '2026-10-29 17:07:44'),
(86, 'PB-085', 'Piattos Cheese', 25.00, 'Snacks', NULL, NULL, 'images/4ed7287cc02c1df95b7a96e51a68f502.jpg', 9, 1, '2026-10-29 17:19:02'),
(87, 'PB-086', 'Piattos Sour Cream & Onion', 25.00, 'Snacks', NULL, NULL, 'images/15a6fd6254ae49da3b96101c7d8cecb7.jpg', 10, 1, '2026-10-29 17:20:44'),
(88, 'PB-087', 'Mang Juan Sukang Paombong', 15.00, 'Snacks', NULL, NULL, 'images/9a5abc1347fe9e24ec4b80d94cdb089c.jpg', 4, 1, '2026-10-29 17:30:45'),
(89, 'PB-088', 'Mang Juan Suka\'t Sili', 15.00, 'Snacks', NULL, NULL, 'images/67622ce584be6a76cc3ec48bccbaa32c.jpg', 5, 1, '2026-10-29 17:31:36'),
(90, 'PB-066', 'Strawberry', 40.00, 'Fruity Soda', '003eee370a18a24d7f9cebdc6d5e46cc', '12 oz', 'images/109fc5a4ce9c005a4e5509602f87ff59.jpg', 10, 1, '2026-10-29 17:45:06'),
(91, 'PB-089', 'Strawberry', 50.00, 'Fruity Soda', '003eee370a18a24d7f9cebdc6d5e46cc', '16 oz', 'images/109fc5a4ce9c005a4e5509602f87ff59.jpg', 10, 1, '2026-10-29 17:45:06'),
(92, 'PB-090', 'Strawberry', 60.00, 'Fruity Soda', '003eee370a18a24d7f9cebdc6d5e46cc', '22 oz', 'images/109fc5a4ce9c005a4e5509602f87ff59.jpg', 10, 1, '2026-10-29 17:45:06'),
(93, 'PB-091', 'Blueberry', 40.00, 'Fruity Soda', '3cef892d11e5906c800e75731a3e686e', '12 oz', 'images/ed3e9c005fc46231d1bacd885ca070c3.jpg', 10, 1, '2026-10-29 17:45:15'),
(94, 'PB-092', 'Blueberry', 50.00, 'Fruity Soda', '3cef892d11e5906c800e75731a3e686e', '16 oz', 'images/ed3e9c005fc46231d1bacd885ca070c3.jpg', 10, 1, '2026-10-29 17:45:15'),
(95, 'PB-093', 'Blueberry', 60.00, 'Fruity Soda', '3cef892d11e5906c800e75731a3e686e', '22 oz', 'images/ed3e9c005fc46231d1bacd885ca070c3.jpg', 10, 1, '2026-10-29 17:45:15'),
(96, 'PB-094', 'Green Apple', 40.00, 'Fruity Soda', '0e96b53d01fdec7ac5c8272b02b1d8cd', '12 oz', 'images/6e70ce2e1d86b7430cce3ff29759882f.jpg', 10, 1, '2026-10-29 17:45:16'),
(97, 'PB-095', 'Green Apple', 50.00, 'Fruity Soda', '0e96b53d01fdec7ac5c8272b02b1d8cd', '16 oz', 'images/6e70ce2e1d86b7430cce3ff29759882f.jpg', 10, 1, '2026-10-29 17:45:16'),
(98, 'PB-096', 'Green Apple', 60.00, 'Fruity Soda', '0e96b53d01fdec7ac5c8272b02b1d8cd', '22 oz', 'images/6e70ce2e1d86b7430cce3ff29759882f.jpg', 10, 1, '2026-10-29 17:45:16'),
(99, 'PB-097', 'Lychee', 40.00, 'Fruity Soda', 'd0674baf648f6ebe7abea24fdaafd887', '12 oz', 'images/358533216f888c0b02c1128142b47bea.jpg', 10, 1, '2026-10-29 17:45:16'),
(100, 'PB-098', 'Lychee', 50.00, 'Fruity Soda', 'd0674baf648f6ebe7abea24fdaafd887', '16 oz', 'images/358533216f888c0b02c1128142b47bea.jpg', 10, 1, '2026-10-29 17:45:16'),
(101, 'PB-099', 'Lychee', 60.00, 'Fruity Soda', 'd0674baf648f6ebe7abea24fdaafd887', '22 oz', 'images/358533216f888c0b02c1128142b47bea.jpg', 10, 1, '2026-10-29 17:45:16'),
(102, 'PB-100', 'Lemon', 40.00, 'Fruity Soda', '8cb0a20af974e95d32a3e906f7079000', '12 oz', 'images/c457611b8feaa1735ed9fee452e6298f.jpg', 10, 1, '2026-10-29 17:45:16'),
(103, 'PB-101', 'Lemon', 50.00, 'Fruity Soda', '8cb0a20af974e95d32a3e906f7079000', '16 oz', 'images/c457611b8feaa1735ed9fee452e6298f.jpg', 10, 1, '2026-10-29 17:45:16'),
(104, 'PB-102', 'Lemon', 60.00, 'Fruity Soda', '8cb0a20af974e95d32a3e906f7079000', '22 oz', 'images/c457611b8feaa1735ed9fee452e6298f.jpg', 10, 1, '2026-10-29 17:45:16'),
(105, 'PB-103', 'Strawberry Yakult', 50.00, 'Yakult Series', 'cf566723407e3ddabb7a2b47f8e27165', '12 oz', 'images/710e02a4b4f7d49e6b78f8d013c4a3f6.jpg', 10, 1, '2026-10-29 17:46:46'),
(106, 'PB-104', 'Strawberry Yakult', 60.00, 'Yakult Series', 'cf566723407e3ddabb7a2b47f8e27165', '16 oz', 'images/710e02a4b4f7d49e6b78f8d013c4a3f6.jpg', 10, 1, '2026-10-29 17:46:46'),
(107, 'PB-105', 'Strawberry Yakult', 70.00, 'Yakult Series', 'cf566723407e3ddabb7a2b47f8e27165', '22 oz', 'images/710e02a4b4f7d49e6b78f8d013c4a3f6.jpg', 10, 1, '2026-10-29 17:46:46'),
(108, 'PB-106', 'Blueberry + Yakult', 50.00, 'Yakult Series', 'a5ca92a780b2b49c6dbab269c6135a63', '12 oz', 'images/a7384410f57534570e2812deb3019715.jpg', 10, 1, '2026-10-29 17:46:46'),
(109, 'PB-107', 'Blueberry + Yakult', 60.00, 'Yakult Series', 'a5ca92a780b2b49c6dbab269c6135a63', '16 oz', 'images/a7384410f57534570e2812deb3019715.jpg', 10, 1, '2026-10-29 17:46:46'),
(110, 'PB-108', 'Blueberry + Yakult', 70.00, 'Yakult Series', 'a5ca92a780b2b49c6dbab269c6135a63', '22 oz', 'images/a7384410f57534570e2812deb3019715.jpg', 10, 1, '2026-10-29 17:46:46'),
(111, 'PB-109', 'SEN LYCHEE + YAKULT', 50.00, 'Yakult Series', '549989f077385b6731131b9992f09685', '12 oz', 'images/5a8c6a3bbecacd76d3f71c3935db0bcc.jpg', 10, 1, '2026-10-29 17:46:47'),
(112, 'PB-110', 'SEN LYCHEE + YAKULT', 60.00, 'Yakult Series', '549989f077385b6731131b9992f09685', '16 oz', 'images/5a8c6a3bbecacd76d3f71c3935db0bcc.jpg', 10, 1, '2026-10-29 17:46:47'),
(113, 'PB-111', 'SEN LYCHEE + YAKULT', 70.00, 'Yakult Series', '549989f077385b6731131b9992f09685', '22 oz', 'images/5a8c6a3bbecacd76d3f71c3935db0bcc.jpg', 10, 1, '2026-10-29 17:46:47'),
(114, 'PB-112', 'Green Apple + Yakult', 50.00, 'Yakult Series', '4406b780b5c5da6bb730b44cc08c0321', '12 oz', 'images/f32f5e6a6a53d5b47b1d960560dcf0f5.jpg', 10, 1, '2026-10-29 17:46:48'),
(115, 'PB-113', 'Green Apple + Yakult', 60.00, 'Yakult Series', '4406b780b5c5da6bb730b44cc08c0321', '16 oz', 'images/f32f5e6a6a53d5b47b1d960560dcf0f5.jpg', 10, 1, '2026-10-29 17:46:48'),
(116, 'PB-114', 'Green Apple + Yakult', 70.00, 'Yakult Series', '4406b780b5c5da6bb730b44cc08c0321', '22 oz', 'images/f32f5e6a6a53d5b47b1d960560dcf0f5.jpg', 10, 1, '2026-10-29 17:46:48'),
(117, 'PB-115', 'Lemon + Yakult', 50.00, 'Yakult Series', '58a70188eacdf6bdd36d39517c32167d', '12 oz', 'images/250611f84df043da8af09ef2a6c99267.jpg', 10, 1, '2026-10-29 17:46:49'),
(118, 'PB-116', 'Lemon + Yakult', 60.00, 'Yakult Series', '58a70188eacdf6bdd36d39517c32167d', '16 oz', 'images/250611f84df043da8af09ef2a6c99267.jpg', 10, 1, '2026-10-29 17:46:49'),
(119, 'PB-117', 'Lemon + Yakult', 70.00, 'Yakult Series', '58a70188eacdf6bdd36d39517c32167d', '22 oz', 'images/250611f84df043da8af09ef2a6c99267.jpg', 10, 1, '2026-10-29 17:46:49'),
(120, 'PB-118', 'Coke Float', 50.00, 'Coke Float', '7134e090a34a9422c5f5b66ef07b8667', '12 oz', 'images/cb492ec6cdd09fb31f702816d818cf27.jpg', 10, 1, '2026-10-29 17:46:49'),
(121, 'PB-119', 'Coke Float', 60.00, 'Coke Float', '7134e090a34a9422c5f5b66ef07b8667', '16 oz', 'images/cb492ec6cdd09fb31f702816d818cf27.jpg', 10, 1, '2026-10-29 17:46:49'),
(122, 'PB-120', 'Coke Float', 70.00, 'Coke Float', '7134e090a34a9422c5f5b66ef07b8667', '22 oz', 'images/cb492ec6cdd09fb31f702816d818cf27.jpg', 10, 1, '2026-10-29 17:46:49');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_code` varchar(30) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_type` enum('online','walk_in') NOT NULL DEFAULT 'online',
  `dining_option` enum('takeaway','dine_in') NOT NULL DEFAULT 'takeaway',
  `order_status` enum('pending','confirmed','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `customer_name` varchar(201) NOT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `order_type`, `dining_option`, `order_status`, `customer_name`, `customer_email`, `customer_phone`, `pickup_date`, `pickup_time`, `notes`, `subtotal`, `total_amount`, `created_at`, `updated_at`) VALUES
(1, 'PB-261029-3BA5DB', 2, 'online', '', 'preparing', 'Deniel Parado', 'dhannelparado@gmail.com', '09072861965', NULL, NULL, '', 117.00, 117.00, '2026-10-29 09:53:26', '2026-10-29 09:53:53'),
(2, 'PB-261029-2AB6A9', 2, 'online', 'takeaway', 'pending', 'Deniel Parado', 'dhannelparado@gmail.com', '09072861965', NULL, NULL, '', 138.00, 138.00, '2026-10-29 17:35:52', '2026-10-29 17:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `product_code` varchar(100) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `product_code`, `product_name`, `quantity`, `unit_price`, `line_total`, `created_at`) VALUES
(1, 1, 58, 'PB-056', 'Hershey\'s (12 oz)', 2, 39.00, 78.00, '2026-10-29 09:53:26'),
(2, 1, 31, 'PB-029', 'Red Velvet (12 oz)', 1, 39.00, 39.00, '2026-10-29 09:53:26'),
(3, 2, 9, 'PB-008', 'Coffee Jelly', 1, 10.00, 10.00, '2026-10-29 17:35:52'),
(4, 2, 86, 'PB-085', 'Piattos Cheese', 1, 25.00, 25.00, '2026-10-29 17:35:52'),
(5, 2, 88, 'PB-087', 'Mang Juan Sukang Paombong', 1, 15.00, 15.00, '2026-10-29 17:35:52'),
(6, 2, 13, 'PB-012', 'Toron', 1, 10.00, 10.00, '2026-10-29 17:35:52'),
(7, 2, 55, 'PB-053', 'Okinnawa (12 oz)', 1, 39.00, 39.00, '2026-10-29 17:35:52'),
(8, 2, 40, 'PB-038', 'Wintermelon (12 oz)', 1, 39.00, 39.00, '2026-10-29 17:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('cash','online') NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `transaction_reference` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `payment_method`, `payment_status`, `amount`, `provider`, `transaction_reference`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, '', 'pending', 117.00, NULL, NULL, NULL, '2026-10-29 09:53:26', '2026-10-29 09:53:26'),
(2, 2, '', 'pending', 138.00, NULL, NULL, NULL, '2026-10-29 17:35:52', '2026-10-29 17:35:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `idx_menu_items_variant_group` (`variant_group`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_status` (`order_status`),
  ADD KEY `idx_orders_created` (`created_at`),
  ADD KEY `idx_orders_type` (`order_type`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_menu` (`menu_item_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_password_resets_customer` (`customer_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_order` (`order_id`),
  ADD KEY `idx_payments_status` (`payment_status`),
  ADD KEY `idx_payments_reference` (`transaction_reference`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

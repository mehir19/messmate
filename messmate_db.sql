-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2026 at 11:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `messmate_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `request_type` enum('chat','meal_request','amount_request') DEFAULT 'chat',
  `requested_meals` decimal(3,1) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `req_breakfast` decimal(3,1) DEFAULT NULL,
  `req_lunch` decimal(3,1) DEFAULT NULL,
  `req_dinner` decimal(3,1) DEFAULT NULL,
  `req_category` enum('grocery','misc') DEFAULT NULL,
  `req_amount` decimal(10,2) DEFAULT NULL,
  `req_description` varchar(255) DEFAULT NULL,
  `is_processed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `mess_id`, `sender_id`, `target_user_id`, `message`, `request_type`, `requested_meals`, `target_date`, `req_breakfast`, `req_lunch`, `req_dinner`, `req_category`, `req_amount`, `req_description`, `is_processed`, `created_at`) VALUES
(1, 1, 3, 3, 'Please approve my meal for today', 'meal_request', NULL, '2026-11-21', 0.5, 1.0, 0.0, NULL, NULL, NULL, 1, '2026-11-21 07:05:58'),
(2, 1, 3, NULL, 'Merge 1000 taka for grocery cost', 'chat', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-11-21 07:08:42'),
(3, 1, 1, 1, 'GROCERY request: Chat bazar / amount request (৳500.00)', 'amount_request', NULL, NULL, NULL, NULL, NULL, 'grocery', 500.00, 'Chat bazar / amount request', 1, '2026-11-21 07:41:07'),
(4, 1, 3, 3, 'For Fish cost', 'amount_request', NULL, NULL, NULL, NULL, NULL, 'grocery', 900.00, 'For Fish cost', 1, '2026-11-21 08:44:22');

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `entry_type` enum('deposit','correction') DEFAULT 'deposit',
  `note` varchar(255) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `billing_month` varchar(7) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `mess_id`, `user_id`, `amount`, `entry_type`, `note`, `recorded_by`, `billing_month`, `created_at`) VALUES
(1, 1, 3, 5000.00, 'deposit', 'Cash', 1, '2026-11', '2026-11-21 03:01:31'),
(2, 1, 3, 5000.00, 'deposit', 'Cash', 1, '2026-11', '2026-11-21 03:31:06'),
(3, 1, 3, -5000.00, 'correction', 'Fixed Deposit #1 (Was ৳5000.00 → Now ৳0.00): Double entry', 1, '2026-11', '2026-11-21 04:48:25'),
(4, 1, 1, 2000.00, 'deposit', 'bKash', 1, '2026-11', '2026-11-21 05:43:11'),
(5, 1, 4, 6000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 09:44:35'),
(6, 1, 1, 10000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 09:44:46'),
(7, 1, 1, -3000.00, 'correction', 'Fixed Deposit #6 (Was ৳10,000.00 -> Now ৳7,000.00): Wrong input', 1, '2026-11', '2026-11-21 09:52:45'),
(8, 1, 1, -9700.00, 'correction', 'Fixed Deposit #6 (Was ৳10,000.00 -> Now ৳300.00): Mistyped', 1, '2026-11', '2026-11-21 09:53:59'),
(9, 1, 1, 4000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 09:56:44'),
(10, 1, 1, -2000.00, 'correction', 'Fixed Deposit #9 (Was ৳4,000.00 -> Now ৳2,000.00): Wrong', 1, '2026-11', '2026-11-21 09:57:19'),
(11, 1, 1, -3300.00, 'correction', 'Fixed Deposit #9 (Was ৳4,000.00 -> Now ৳700.00): Mis entry', 1, '2026-11', '2026-11-21 09:58:19');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `paid_by_user_id` int(11) NOT NULL,
  `funding_source` enum('manager_cash','member_pocket') NOT NULL DEFAULT 'member_pocket',
  `category` enum('grocery','misc') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `billing_month` varchar(7) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `mess_id`, `paid_by_user_id`, `funding_source`, `category`, `description`, `amount`, `expense_date`, `status`, `billing_month`, `created_at`) VALUES
(1, 1, 1, 'member_pocket', 'grocery', 'Chat bazar / amount request', 500.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 07:45:21'),
(2, 1, 3, 'member_pocket', 'grocery', 'For Fish cost', 900.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 08:44:43'),
(3, 1, 1, 'member_pocket', 'misc', 'House Rent', 14000.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 09:21:28'),
(4, 1, 3, 'member_pocket', 'grocery', 'Rice', 2500.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 09:28:56'),
(5, 1, 1, 'manager_cash', 'grocery', 'Grocery', 3000.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 09:43:58');

-- --------------------------------------------------------

--
-- Table structure for table `meals`
--

CREATE TABLE `meals` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `breakfast` decimal(3,1) DEFAULT 0.0,
  `lunch` decimal(3,1) DEFAULT 0.0,
  `dinner` decimal(3,1) DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meals`
--

INSERT INTO `meals` (`id`, `mess_id`, `user_id`, `meal_date`, `breakfast`, `lunch`, `dinner`) VALUES
(1, 1, 3, '2026-11-21', 0.5, 1.0, 0.0),
(2, 1, 1, '2026-11-21', 0.0, 1.0, 1.0);

-- --------------------------------------------------------

--
-- Table structure for table `messes`
--

CREATE TABLE `messes` (
  `id` int(11) NOT NULL,
  `mess_code` varchar(12) NOT NULL,
  `mess_name` varchar(120) NOT NULL,
  `currency_symbol` varchar(10) DEFAULT 'BDT',
  `subscription_plan` enum('free_trial','starter','pro') DEFAULT 'free_trial',
  `subscription_status` enum('active','expired') DEFAULT 'active',
  `trial_ends_at` datetime DEFAULT NULL,
  `subscription_ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messes`
--

INSERT INTO `messes` (`id`, `mess_code`, `mess_name`, `currency_symbol`, `subscription_plan`, `subscription_status`, `trial_ends_at`, `subscription_ends_at`, `created_at`) VALUES
(1, 'MESS-8511', 'Niribili Mess', '৳', 'free_trial', 'active', '2026-12-05 04:00:12', NULL, '2026-11-21 03:00:12');

-- --------------------------------------------------------

--
-- Table structure for table `shopping_roster`
--

CREATE TABLE `shopping_roster` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `assigned_user_id` int(11) NOT NULL,
  `duty_date` date NOT NULL,
  `items_note` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','completed') DEFAULT 'upcoming'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shopping_roster`
--

INSERT INTO `shopping_roster` (`id`, `mess_id`, `assigned_user_id`, `duty_date`, `items_note`, `status`) VALUES
(1, 1, 1, '2026-11-21', '', 'upcoming');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('manager','member') DEFAULT 'member',
  `previous_balance` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `mess_id`, `member_code`, `full_name`, `email`, `password_hash`, `role`, `previous_balance`, `is_active`, `created_at`) VALUES
(1, 1, 'M-101', 'Mihir Mondal', 'codepedant@gmail.com', '$2y$10$P3hHuRtNU5MTfT4Vl3KLY.U0r3rlwlNlLQcWa5K2MZ.HOyW7vRehC', 'manager', 0.00, 1, '2026-11-21 03:00:12'),
(2, 1, 'M-102', 'Al Imran', NULL, '$2y$10$z/kZK7AGfebz/rrM6J.o3udzhaKEIJhPDJ3Ws/D/8YeIkghdTmbR2', 'member', 600.00, 0, '2026-11-21 03:01:01'),
(3, 1, 'M-103', 'Mr. Al Imran', NULL, '$2y$10$hPNFuLQF72FcwWGgAXa5beirCOoueYiDske.0QaGms.2Y5rbyHyNa', 'member', 600.00, 1, '2026-11-21 03:01:20'),
(4, 1, 'M-104', 'Tanvir', NULL, '$2y$10$gUg9QXKGo7pJsptq0giExeIjqyF01v.UsTu4xvyv.NYWp2TxyQ1S2', 'member', -400.00, 1, '2026-11-21 09:14:47'),
(5, 1, 'M-105', 'Tanvir', NULL, '$2y$10$lXUae07NJztYs56vClWRiuKJDa5t1GW2CDfPAQ1F41/K88T08K9T6', 'member', -400.00, 0, '2026-11-21 09:17:15'),
(6, 1, 'M-106', 'Tanvir', NULL, '$2y$10$JhM.d8DB.feiGRYX/gZTuOTBF88q6POh0c5YUUGVxWw.iLhSNDnTe', 'member', -400.00, 0, '2026-11-21 09:18:23'),
(7, 1, 'M-107', 'Tanvir', NULL, '$2y$10$sJO3q1oD0kWNnHrDaXP07eYZuwE4w6gaPTS6IDsiafmE.ymqURAKe', 'member', -400.00, 0, '2026-11-21 09:18:23'),
(8, 1, 'M-108', 'Ikramul', NULL, '$2y$10$GnYCveO574BfEDpSb5gU/uVvFN/RoyoWujmd5G68wJq2US.1nWlKC', 'member', 0.00, 1, '2026-11-21 09:50:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mess_id` (`mess_id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mess_id` (`mess_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mess_id` (`mess_id`),
  ADD KEY `paid_by_user_id` (`paid_by_user_id`);

--
-- Indexes for table `meals`
--
ALTER TABLE `meals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_meal` (`mess_id`,`user_id`,`meal_date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messes`
--
ALTER TABLE `messes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mess_code` (`mess_code`);

--
-- Indexes for table `shopping_roster`
--
ALTER TABLE `shopping_roster`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mess_id` (`mess_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member_per_mess` (`mess_id`,`member_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `meals`
--
ALTER TABLE `meals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messes`
--
ALTER TABLE `messes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shopping_roster`
--
ALTER TABLE `shopping_roster`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meals`
--
ALTER TABLE `meals`
  ADD CONSTRAINT `meals_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shopping_roster`
--
ALTER TABLE `shopping_roster`
  ADD CONSTRAINT `shopping_roster_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`mess_id`) REFERENCES `messes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

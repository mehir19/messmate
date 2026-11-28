-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2026 at 06:32 PM
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
(4, 1, 3, 3, 'For Fish cost', 'amount_request', NULL, NULL, NULL, NULL, NULL, 'grocery', 900.00, 'For Fish cost', 1, '2026-11-21 08:44:22'),
(5, 1, 1, NULL, 'Cook fish and daal', 'chat', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-11-27 15:48:32'),
(6, 1, 1, 1, 'GROCERY request: Rice (৳200.00)', 'amount_request', NULL, NULL, NULL, NULL, NULL, 'grocery', 200.00, 'Rice', 1, '2026-11-27 15:53:15'),
(7, 1, 1, 1, 'added 2 meals at night', 'meal_request', NULL, '2026-11-27', 0.5, 1.0, 0.0, NULL, NULL, NULL, 1, '2026-11-27 15:53:37'),
(8, 1, 1, NULL, 'দুপুরে সব্জি ও মাংস রান্না হবে', 'chat', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-11-27 15:54:06'),
(9, 1, 13, NULL, 'রাতে খিচুড়ি হলে ভালো হয়', 'chat', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-11-27 15:55:35');

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
(4, 1, 1, 2000.00, 'deposit', 'bKash', 1, '2026-11', '2026-11-21 05:43:11'),
(6, 1, 1, 10000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 09:44:46'),
(7, 1, 1, -3000.00, 'correction', 'Fixed Deposit #6 (Was ৳10,000.00 -> Now ৳7,000.00): Wrong input', 1, '2026-11', '2026-11-21 09:52:45'),
(8, 1, 1, -9700.00, 'correction', 'Fixed Deposit #6 (Was ৳10,000.00 -> Now ৳300.00): Mistyped', 1, '2026-11', '2026-11-21 09:53:59'),
(9, 1, 1, 4000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 09:56:44'),
(10, 1, 1, -2000.00, 'correction', 'Fixed Deposit #9 (Was ৳4,000.00 -> Now ৳2,000.00): Wrong', 1, '2026-11', '2026-11-21 09:57:19'),
(11, 1, 1, -3300.00, 'correction', 'Fixed Deposit #9 (Was ৳4,000.00 -> Now ৳700.00): Mis entry', 1, '2026-11', '2026-11-21 09:58:19'),
(12, 2, 9, 200.00, 'deposit', 'Monthly fund contribution', 9, '2026-11', '2026-11-21 14:19:55'),
(13, 2, 10, 100.00, 'deposit', 'Monthly fund contribution', 9, '2026-11', '2026-11-21 14:20:03'),
(14, 2, 11, 78.00, 'deposit', 'Monthly fund contribution', 9, '2026-11', '2026-11-21 14:20:12'),
(15, 2, 12, 128.00, 'deposit', 'Monthly fund contribution', 9, '2026-11', '2026-11-21 14:20:26'),
(16, 2, 12, -20.00, 'correction', 'Fixed Deposit #15 (Current $128.00 -> New $108.00, Diff $-20.00): Wrong Entry', 9, '2026-11', '2026-11-21 14:21:43'),
(17, 2, 12, -38.00, 'correction', 'Fixed Deposit #15 (Current $108.00 -> New $70.00, Diff $-38.00): Mistype', 9, '2026-11', '2026-11-21 14:22:59'),
(18, 1, 1, 5300.00, 'correction', 'Fixed Deposit #9 (Current ৳-1,300.00 -> New ৳4,000.00, Diff +৳5,300.00): Deposit again', 1, '2026-11', '2026-11-21 16:10:21'),
(19, 1, 1, 7000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-21 16:10:36'),
(25, 1, 13, 5000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 14:49:56'),
(26, 1, 15, 6000.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 14:50:13'),
(27, 1, 14, 700.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 15:17:41'),
(28, 1, 14, -700.00, 'correction', 'Fixed Deposit #27 (Current ৳700.00 -> New ৳0.00, Diff ৳-700.00): wrong entry', 1, '2026-11', '2026-11-22 15:19:10'),
(29, 1, 16, 700.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 15:22:23'),
(30, 1, 1, 700.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 15:52:58'),
(31, 1, 16, 300.00, 'deposit', 'Monthly fund contribution', 1, '2026-11', '2026-11-22 15:54:03');

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
(3, 1, 1, 'member_pocket', 'misc', 'House Rent', 12000.00, '2026-11-21', 'approved', '2026-11', '2026-11-21 09:21:28'),
(6, 2, 9, 'manager_cash', 'misc', 'House Rent', 600.00, '2026-11-22', 'approved', '2026-11', '2026-11-22 14:25:56'),
(8, 1, 14, 'member_pocket', 'grocery', 'Oil + Salt + Rice', 650.00, '2026-11-22', 'approved', '2026-11', '2026-11-22 15:20:05'),
(17, 1, 1, 'manager_cash', 'grocery', 'Grocery', 3000.00, '2026-11-27', 'approved', '2026-11', '2026-11-27 06:25:37'),
(18, 1, 1, 'member_pocket', 'misc', 'Cook Bonus', 1000.00, '2026-11-27', 'approved', '2026-11', '2026-11-27 06:27:07'),
(19, 1, 1, 'member_pocket', 'misc', 'Cook Salary', 3000.00, '2026-11-27', 'approved', '2026-11', '2026-11-27 06:27:55'),
(20, 1, 1, 'member_pocket', 'misc', 'Cook Salary', 3000.00, '2026-11-27', 'approved', '2026-11', '2026-11-27 06:36:19'),
(21, 1, 1, 'member_pocket', 'grocery', 'Rice', 200.00, '2026-11-27', 'approved', '2026-11', '2026-11-27 16:10:52');

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
(2, 1, 1, '2026-11-21', 0.0, 1.0, 1.0),
(4, 1, 1, '2026-11-22', 0.0, 1.0, 1.0),
(5, 1, 13, '2026-11-22', 0.0, 2.0, 2.0),
(6, 1, 14, '2026-11-22', 0.0, 1.0, 1.0),
(7, 1, 15, '2026-11-22', 0.0, 1.0, 1.0),
(8, 1, 16, '2026-11-22', 0.0, 2.0, 2.0),
(9, 1, 17, '2026-11-22', 0.0, 3.0, 1.0),
(10, 1, 1, '2026-11-27', 0.5, 1.0, 0.0),
(11, 1, 13, '2026-11-27', 0.0, 1.0, 4.0),
(12, 1, 14, '2026-11-27', 0.0, 1.0, 1.0),
(13, 1, 15, '2026-11-27', 0.0, 1.0, 1.0),
(14, 1, 16, '2026-11-27', 0.0, 1.0, 4.0),
(15, 1, 17, '2026-11-27', 0.0, 1.0, 1.0);

-- --------------------------------------------------------

--
-- Table structure for table `messes`
--

CREATE TABLE `messes` (
  `id` int(11) NOT NULL,
  `mess_code` varchar(12) NOT NULL,
  `mess_name` varchar(120) NOT NULL,
  `mess_logo` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(10) DEFAULT 'BDT',
  `target_meal_cost` decimal(10,2) NOT NULL DEFAULT 60.00,
  `cook_pin` varchar(32) NOT NULL DEFAULT '1111',
  `subscription_plan` enum('free_trial','starter','pro') DEFAULT 'free_trial',
  `subscription_status` enum('active','expired') DEFAULT 'active',
  `trial_ends_at` datetime DEFAULT NULL,
  `subscription_ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messes`
--

INSERT INTO `messes` (`id`, `mess_code`, `mess_name`, `mess_logo`, `currency_symbol`, `target_meal_cost`, `cook_pin`, `subscription_plan`, `subscription_status`, `trial_ends_at`, `subscription_ends_at`, `created_at`) VALUES
(1, 'MESS-8511', 'Nirjon Mess', 'dashboard/uploads/mess_1_1795799878.jpg', '৳', 50.00, '1111', 'starter', 'active', '2026-12-05 04:00:12', '2026-12-24 18:15:09', '2026-11-21 03:00:12'),
(2, 'MESS-9235', 'Shanti Hostel', NULL, '$', 60.00, '1111', 'free_trial', 'active', '2026-12-05 15:17:42', NULL, '2026-11-21 14:17:42');

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
(1, 1, 1, '2026-11-21', '', 'completed'),
(2, 1, 13, '2026-11-23', '', 'upcoming');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `mess_id` int(11) NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `room_no` varchar(30) DEFAULT NULL,
  `seat_no` varchar(30) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `mess_id`, `member_code`, `room_no`, `seat_no`, `full_name`, `email`, `password_hash`, `role`, `previous_balance`, `is_active`, `created_at`) VALUES
(1, 1, 'M-101', NULL, NULL, 'Mihir Mondal', 'codepedant@gmail.com', '$2y$10$jAeefCz1LseerZFpmTFJ1ut9ai6mbdi1KtorHBCRfl38Yu/QbT0Iy', 'manager', 0.00, 1, '2026-11-21 03:00:12'),
(9, 2, 'M-101', NULL, NULL, 'John Hops', 'johnhops@gmail.com', '$2y$10$QEvc43ecFTPDeai8bmLc4OpWBNPe1uBM.dUmqu3C1tCmXwnR0kzPa', 'manager', 0.00, 1, '2026-11-21 14:17:42'),
(10, 2, 'M-102', NULL, NULL, 'Stuart Braud', NULL, '$2y$10$0A3a0DgPBiNLc.pU9zKPsOLTCC5NP99IuUHLJ1f6aJQDKOg7AwJmK', 'member', 0.00, 1, '2026-11-21 14:18:34'),
(11, 2, 'M-103', NULL, NULL, 'Mack Grath', NULL, '$2y$10$kBphaIJDYRI5EQEi6OxAYexLa6FwnGbTOxn1Zy58Wlk8M7Sq8WqIq', 'member', -30.00, 1, '2026-11-21 14:18:58'),
(12, 2, 'M-104', NULL, NULL, 'Aston Kooper', NULL, '$2y$10$0Mds77PdvYtLg.z9BJsZH.I1L9XEMJRy1j.StTgxBCbJHwL3Z0t.y', 'member', 12.00, 1, '2026-11-21 14:19:40'),
(13, 1, 'M-102', NULL, NULL, 'Al Imran', NULL, '$2y$10$Q7PIf3kWqUxj2F8WOL5zUuI4Gy26ivyORUqX5kBuSueYUjYSBXKam', 'member', 500.00, 1, '2026-11-21 16:06:52'),
(14, 1, 'M-103', NULL, NULL, 'Badhon', NULL, '$2y$10$tfWp8v.jaZIUhr4Yq9GwDe5iga75YZ3saGcUKLL7rTlWuNGYsD8H2', 'member', 0.00, 1, '2026-11-21 16:08:11'),
(15, 1, 'M-104', NULL, NULL, 'Asim', NULL, '$2y$10$kikPcAZKRuChHfWJNryE.e3I3ka6pttrycr0JawN6XivWsnShEgqW', 'member', 0.00, 1, '2026-11-22 14:36:16'),
(16, 1, 'M-105', NULL, NULL, 'Mih Imran', NULL, '$2y$10$ZVe9lPQvUlIgNxPpBEJtguq7wUz/DRNHZYwxnNKhG3ACLs5B76qBu', 'member', 0.00, 1, '2026-11-22 14:36:30'),
(17, 1, 'M-106', NULL, NULL, 'Mahfuz', NULL, '$2y$10$r2NV942hmR7JoS3sW7or9uerASIA/xNTCY5aptA/7bLhXrTNqEyBy', 'member', -2000.00, 1, '2026-11-22 14:37:15');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `channel` enum('email','phone') NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `channel`, `recipient`, `code_hash`, `expires_at`, `verified_at`, `created_at`) VALUES
(1, 'phone', '019223739675', '$2y$10$0gJ0/NL8yV9jDC5uYzbEVeDiM1nMRhcCuQJtlfLZZXUJvr6Ut27zK', '2026-11-27 23:11:55', NULL, '2026-11-27 17:01:55'),
(2, 'phone', '8801922373967', '$2y$10$MG3W3cJ4xjWrBTYxVzCo7OVLlKngaItkWKI3PKu8uLZXf1bj8oZRq', '2026-11-27 23:12:47', NULL, '2026-11-27 17:02:47');

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
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient_channel` (`recipient`,`channel`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `meals`
--
ALTER TABLE `meals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `messes`
--
ALTER TABLE `messes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shopping_roster`
--
ALTER TABLE `shopping_roster`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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

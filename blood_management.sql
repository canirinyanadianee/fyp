-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2025 at 01:36 PM
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
-- Database: `blood_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_anomalies`
--

CREATE TABLE `ai_anomalies` (
  `id` bigint(20) NOT NULL,
  `anomaly_type` varchar(64) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`details`)),
  `score` float DEFAULT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_eligibility_checks`
--

CREATE TABLE `ai_eligibility_checks` (
  `id` bigint(20) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `checked_by` varchar(64) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`features`)),
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`result`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_forecasts`
--

CREATE TABLE `ai_forecasts` (
  `id` bigint(20) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `blood_type` varchar(5) NOT NULL,
  `forecast_date` date NOT NULL,
  `horizon_days` int(11) NOT NULL,
  `predicted_quantity_ml` int(11) NOT NULL,
  `lower_ml` int(11) DEFAULT NULL,
  `upper_ml` int(11) DEFAULT NULL,
  `model_version_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_learning_data`
--

CREATE TABLE `ai_learning_data` (
  `id` int(11) NOT NULL,
  `data_type` enum('usage_pattern','donation_pattern','seasonal_trend','emergency_response') NOT NULL,
  `entity_type` enum('donor','blood_bank','hospital','system') NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `pattern_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`pattern_data`)),
  `confidence_score` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_models`
--

CREATE TABLE `ai_models` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `task_type` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_model_versions`
--

CREATE TABLE `ai_model_versions` (
  `id` bigint(20) NOT NULL,
  `model_id` int(11) NOT NULL,
  `version` varchar(64) NOT NULL,
  `artifact_path` varchar(255) DEFAULT NULL,
  `params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`params`)),
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `trained_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_notifications`
--

CREATE TABLE `ai_notifications` (
  `id` int(11) NOT NULL,
  `entity_type` enum('donor','blood_bank','hospital') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `urgency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_status` tinyint(1) DEFAULT 0,
  `action_taken` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_notifications`
--

INSERT INTO `ai_notifications` (`id`, `entity_type`, `entity_id`, `message`, `blood_type`, `urgency_level`, `created_at`, `read_status`, `action_taken`) VALUES
(1, 'donor', 2, 'Your AB+ blood type is currently in high demand. Please consider scheduling a donation soon.', 'AB+', 'high', '2025-08-21 16:21:31', 1, 0),
(2, 'donor', 2, 'There is a blood drive happening this weekend at the Central Blood Bank.', NULL, 'medium', '2025-08-20 16:21:31', 1, 0),
(3, 'donor', 2, 'Your last donation was very helpful. Thank you for saving lives!', NULL, 'low', '2025-08-18 16:21:31', 1, 0),
(4, 'donor', 2, 'Reminder: You\'re eligible for your next donation. Schedule an appointment today.', NULL, 'medium', '2025-08-16 16:21:31', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `ai_predictions`
--

CREATE TABLE `ai_predictions` (
  `id` bigint(20) NOT NULL,
  `model_version_id` bigint(20) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `prediction` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`prediction`)),
  `confidence` float DEFAULT NULL,
  `horizon_days` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_recommendations`
--

CREATE TABLE `ai_recommendations` (
  `id` bigint(20) NOT NULL,
  `request_id` bigint(20) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `recommended` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recommended`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_bank_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `donor_id`, `blood_bank_id`, `appointment_date`, `status`, `created_at`, `updated_at`) VALUES
(5, 6, 3, '2025-08-28 17:27:00', 'pending', '2025-08-28 15:28:00', '2025-08-28 15:28:00'),
(6, 6, 2, '2025-08-15 17:29:00', 'pending', '2025-08-28 15:28:42', '2025-08-29 08:22:14'),
(7, 6, 2, '2025-08-13 17:29:00', 'pending', '2025-08-28 15:29:25', '2025-08-28 15:29:25'),
(8, 6, 7, '2025-08-13 17:35:00', 'pending', '2025-08-28 15:35:22', '2025-08-28 15:35:22'),
(9, 6, 4, '2025-08-28 17:36:00', 'pending', '2025-08-28 15:36:16', '2025-08-28 15:36:16'),
(10, 6, 7, '2025-08-20 17:45:00', 'pending', '2025-08-28 15:44:19', '2025-08-28 15:44:19'),
(11, 6, 8, '2025-08-14 17:52:00', 'pending', '2025-08-28 15:53:02', '2025-08-28 15:53:02'),
(12, 6, 1, '2025-08-22 17:54:00', 'pending', '2025-08-28 15:54:19', '2025-08-28 15:54:19'),
(13, 6, 5, '2025-08-16 17:55:00', 'pending', '2025-08-28 15:55:22', '2025-08-28 15:55:22'),
(14, 6, 4, '2025-08-28 18:02:00', 'pending', '2025-08-28 16:02:44', '2025-08-28 16:02:44'),
(15, 6, 5, '2025-08-28 19:07:00', 'pending', '2025-08-28 16:06:40', '2025-08-28 16:06:40'),
(16, 6, 7, '2025-08-28 18:12:00', 'pending', '2025-08-28 16:12:10', '2025-08-28 16:12:10'),
(17, 6, 7, '2025-08-13 18:19:00', 'pending', '2025-08-28 16:19:59', '2025-08-28 16:19:59'),
(18, 6, 2, '2025-08-28 18:21:00', 'pending', '2025-08-28 16:21:12', '2025-08-28 16:21:12'),
(19, 6, 8, '2025-08-28 18:34:00', 'pending', '2025-08-28 16:33:24', '2025-08-28 16:33:24'),
(20, 6, 8, '2025-08-28 18:34:00', 'pending', '2025-08-28 16:34:06', '2025-08-28 16:34:06'),
(21, 6, 8, '2025-08-28 21:50:00', 'pending', '2025-08-28 16:46:29', '2025-08-28 16:46:29'),
(22, 6, 1, '2025-08-28 19:11:00', 'pending', '2025-08-28 17:11:00', '2025-08-28 17:11:00'),
(23, 6, 6, '2025-09-02 19:17:00', 'pending', '2025-08-28 17:15:38', '2025-08-28 17:15:38'),
(24, 6, 7, '2025-08-20 09:19:00', 'pending', '2025-08-29 07:14:02', '2025-08-29 07:14:02'),
(25, 6, 6, '2025-08-08 09:46:00', 'pending', '2025-08-29 07:46:02', '2025-08-29 07:46:02'),
(26, 6, 2, '2025-08-15 09:52:00', 'pending', '2025-08-29 07:50:49', '2025-08-29 07:50:49'),
(27, 6, 8, '2025-08-28 09:53:00', 'pending', '2025-08-29 07:51:03', '2025-08-29 07:51:03'),
(28, 6, 8, '2025-08-14 09:55:00', 'pending', '2025-08-29 07:54:46', '2025-08-29 07:54:46'),
(29, 6, 2, '2025-08-08 09:55:00', 'pending', '2025-08-29 07:55:10', '2025-08-29 07:55:10'),
(30, 6, 2, '2025-08-14 10:03:00', 'pending', '2025-08-29 08:02:33', '2025-08-29 08:02:33'),
(31, 6, 8, '2025-08-14 11:10:00', 'pending', '2025-08-29 09:09:25', '2025-08-29 09:09:25'),
(32, 6, 8, '2025-08-08 11:12:00', 'pending', '2025-08-29 09:11:09', '2025-08-29 09:11:09'),
(33, 7, 8, '2025-08-08 12:32:00', 'pending', '2025-08-29 10:30:44', '2025-08-29 10:30:44'),
(34, 8, 1, '2025-09-01 15:26:00', 'pending', '2025-09-01 13:26:42', '2025-09-01 13:26:42'),
(35, 8, 2, '2025-09-01 17:37:00', 'pending', '2025-09-01 15:37:59', '2025-09-01 15:37:59'),
(36, 8, 8, '2025-09-02 17:38:00', 'pending', '2025-09-01 15:38:28', '2025-09-01 15:38:28');

-- --------------------------------------------------------

--
-- Table structure for table `blood_banks`
--

CREATE TABLE `blood_banks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `established_date` date NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 4.0,
  `contact_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_banks`
--

INSERT INTO `blood_banks` (`id`, `user_id`, `name`, `license_number`, `address`, `city`, `state`, `postal_code`, `phone`, `email`, `established_date`, `contact_phone`, `rating`, `contact_email`) VALUES
(1, 4, 'falge', '123', 'MIDUHA', 'KIgali', 'KIgali', '123', '0789736352', 'falge@gmail.com', '2025-08-21', NULL, 4.0, NULL),
(2, 8, 'Central Blood Bank', 'BB0008', '123 Blood Bank Street, City', '', '', '', '+1-555-0123', 'bloodbank@example.com', '0000-00-00', NULL, 4.0, NULL),
(3, 12, 'dianee', 'BB0012', '', '', '', '', '', 'dianee@gmail.com', '0000-00-00', NULL, 4.0, NULL),
(4, 15, 'Test Blood Bank', 'BB0015', '', '', '', '', '', 'testbb1756109292@test.com', '0000-00-00', NULL, 4.0, NULL),
(5, 19, 'Test Blood Bank', 'BB0019', '', '', '', '', '', 'testbb1756109307@test.com', '0000-00-00', NULL, 4.0, NULL),
(6, 23, 'Workflow Blood Bank', 'WF0023', '', '', '', '', '', 'workflow_bb@test.com', '0000-00-00', NULL, 4.0, NULL),
(7, 36, 'ishimwe1', 'BB0036', '', '', '', '', '', '', '0000-00-00', NULL, 4.0, 'ishimwe1@gmail.com'),
(8, 37, 'canira', 'BB0037', '', '', '', '', '', '', '0000-00-00', NULL, 4.0, 'canira@gmail.com'),
(9, 42, 'grace', 'BB0042', '', '', '', '', '', '', '0000-00-00', NULL, 4.0, 'grace@gmail.com'),
(10, 43, 'yvette', 'BB0043', '', '', '', '', '', '', '0000-00-00', NULL, 4.0, 'yvette@gmail.com'),
(11, 46, 'nyana', 'BB0046', '', '', '', '', '', '', '0000-00-00', NULL, 4.0, 'nyana2003@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `blood_donations`
--

CREATE TABLE `blood_donations` (
  `id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_bank_id` int(11) NOT NULL,
  `donation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL DEFAULT 450,
  `status` enum('collected','tested','processed','available','discarded') DEFAULT 'collected',
  `health_notes` text DEFAULT NULL,
  `donor_email` varchar(100) DEFAULT NULL,
  `donor_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_donations`
--

INSERT INTO `blood_donations` (`id`, `donor_id`, `blood_bank_id`, `donation_date`, `blood_type`, `quantity_ml`, `status`, `health_notes`, `donor_email`, `donor_phone`) VALUES
(1, 2, 1, '2025-08-21 22:00:00', 'AB+', 450, '', 'yo are allowed to attend on friday', NULL, NULL),
(2, 2, 1, '2025-08-21 22:00:00', 'AB+', 450, '', 'yo are allowed to attend on friday', NULL, NULL),
(3, 2, 1, '2025-08-21 22:00:00', 'AB+', 450, '', 'yo are allowed to attend on friday', NULL, NULL),
(4, 2, 1, '2025-08-21 22:00:00', 'AB+', 450, '', 'yo are allowed to attend on friday', NULL, NULL),
(5, 1, 1, '2025-08-21 22:00:00', 'O+', 450, '', 'moogame', NULL, NULL),
(6, 2, 1, '2025-08-22 22:00:00', 'AB+', 450, '', 'blood', NULL, NULL),
(7, 4, 7, '2025-08-25 22:00:00', 'O+', 450, '', 'ggggg', NULL, NULL),
(8, 3, 7, '2025-08-25 22:00:00', 'O+', 450, '', 'ftgfvghvgh', NULL, NULL),
(9, 5, 7, '2025-08-25 22:00:00', 'A+', 450, '', 'weffeaf', NULL, NULL),
(10, 3, 7, '2025-08-25 22:00:00', 'O+', 450, '', 'u666tjy', NULL, NULL),
(11, 3, 7, '2025-08-25 22:00:00', 'O+', 450, '', 'drtgrdeds', NULL, NULL),
(12, 6, 3, '2025-08-28 15:27:00', 'A-', 0, '', NULL, NULL, NULL),
(13, 6, 2, '2025-08-15 15:29:00', 'A-', 0, '', NULL, NULL, NULL),
(14, 6, 2, '2025-08-13 15:29:00', 'A-', 0, '', NULL, NULL, NULL),
(15, 6, 7, '2025-08-13 15:35:00', 'A-', 0, '', NULL, NULL, NULL),
(16, 6, 4, '2025-08-28 15:36:00', 'A-', 0, '', NULL, NULL, NULL),
(17, 6, 7, '2025-08-20 15:45:00', 'A-', 0, '', NULL, NULL, NULL),
(18, 6, 8, '2025-08-14 15:52:00', 'A-', 0, '', NULL, NULL, NULL),
(19, 6, 1, '2025-08-22 15:54:00', 'A-', 0, '', NULL, NULL, NULL),
(20, 6, 5, '2025-08-16 15:55:00', 'A-', 0, '', NULL, NULL, NULL),
(21, 6, 4, '2025-08-28 16:02:00', 'A-', 0, '', NULL, NULL, NULL),
(22, 6, 5, '2025-08-28 17:07:00', 'A-', 0, '', NULL, NULL, NULL),
(23, 6, 7, '2025-08-28 16:12:00', 'A-', 0, '', NULL, NULL, NULL),
(24, 6, 7, '2025-08-13 16:19:00', 'A-', 0, '', NULL, NULL, NULL),
(25, 6, 2, '2025-08-28 16:21:00', 'A-', 0, '', NULL, NULL, NULL),
(26, 6, 8, '2025-08-28 16:34:00', 'A-', 0, '', NULL, NULL, NULL),
(27, 6, 8, '2025-08-28 16:34:00', 'A-', 0, '', NULL, NULL, NULL),
(28, 6, 8, '2025-08-28 19:50:00', 'A-', 0, '', NULL, NULL, NULL),
(29, 6, 1, '2025-08-28 17:11:00', 'A-', 0, '', NULL, NULL, NULL),
(30, 6, 6, '2025-09-02 17:17:00', 'A-', 0, '', NULL, NULL, NULL),
(31, 6, 7, '2025-08-20 07:19:00', 'A-', 0, '', NULL, NULL, NULL),
(32, 6, 6, '2025-08-08 07:46:00', 'O+', 0, '', NULL, NULL, NULL),
(33, 6, 2, '2025-08-15 07:52:00', 'O+', 0, '', NULL, NULL, NULL),
(34, 6, 8, '2025-08-28 07:53:00', 'O+', 0, '', NULL, NULL, NULL),
(35, 6, 8, '2025-08-14 07:55:00', 'O+', 0, '', NULL, NULL, NULL),
(36, 6, 2, '2025-08-08 07:55:00', 'O+', 0, '', NULL, NULL, NULL),
(37, 6, 2, '2025-08-14 08:03:00', 'O+', 0, '', NULL, NULL, NULL),
(38, 6, 8, '2025-08-14 09:10:00', 'O+', 0, '', NULL, NULL, NULL),
(39, 6, 8, '2025-08-08 09:12:00', 'O+', 0, '', NULL, NULL, NULL),
(40, 7, 8, '2025-08-08 10:32:00', 'B+', 0, '', NULL, NULL, NULL),
(41, 1, 11, '2025-08-29 22:00:00', 'AB-', 400, '', 'uuyuyuuu', NULL, NULL),
(42, 8, 1, '2025-09-01 13:26:00', 'O+', 0, '', NULL, NULL, NULL),
(43, 8, 2, '2025-09-01 15:37:00', 'O+', 0, '', NULL, NULL, NULL),
(44, 8, 8, '2025-09-02 15:38:00', 'O+', 0, '', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL,
  `blood_bank_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expiry_date` date NOT NULL,
  `status` enum('available','reserved','expired','discarded') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_inventory`
--

INSERT INTO `blood_inventory` (`id`, `blood_bank_id`, `blood_type`, `quantity_ml`, `last_updated`, `expiry_date`, `status`) VALUES
(1, 1, 'AB+', 1800, '2025-08-22 15:38:17', '2025-10-03', 'available'),
(5, 1, 'A-', 12, '2025-08-22 15:47:12', '2025-10-03', 'available'),
(8, 1, 'B+', 50, '2025-08-22 16:11:56', '2025-10-03', 'available'),
(9, 1, 'O+', 6450, '2025-08-22 16:54:06', '2025-10-03', 'available'),
(11, 1, 'AB+', 450, '2025-08-23 10:51:55', '2025-10-04', 'available'),
(13, 7, 'AB+', 14, '2025-08-26 13:29:57', '2025-08-12', 'available'),
(14, 7, 'O+', 1800, '2025-08-26 14:34:21', '2025-10-07', 'available'),
(19, 7, 'A+', 0, '2025-08-26 16:05:49', '2025-08-19', 'available'),
(20, 7, 'AB+', 45, '2025-08-26 16:09:17', '2025-08-26', 'available'),
(22, 11, 'AB-', 400, '2025-08-30 17:25:04', '2025-10-11', 'available'),
(24, 11, 'A+', 10, '2025-09-02 11:30:44', '2025-10-02', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `blood_requests`
--

CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL,
  `urgency` enum('routine','urgent','emergency') DEFAULT 'routine',
  `patient_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','completed','rejected','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_requests`
--

INSERT INTO `blood_requests` (`id`, `hospital_id`, `blood_type`, `quantity_ml`, `urgency`, `patient_name`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'O+', 900, 'urgent', 'John Doe', 'pending', NULL, '2025-08-25 10:47:23', '2025-08-25 10:47:23'),
(2, 1, 'A+', 450, 'routine', 'Jane Smith', 'completed', NULL, '2025-08-25 10:47:23', '2025-08-25 10:47:23'),
(3, 1, 'B-', 450, 'emergency', 'Bob Wilson', 'approved', NULL, '2025-08-25 10:47:23', '2025-08-25 10:47:23'),
(4, 2, 'A-', 450, 'urgent', 'didi', 'pending', 'loose blood', '2025-08-25 11:32:28', '2025-08-25 11:32:28'),
(5, 2, 'AB+', 450, 'routine', 'didi', 'pending', 'fdcxdscx', '2025-08-25 11:40:13', '2025-08-25 11:40:13'),
(6, 2, 'A-', 450, 'routine', 'didi', 'pending', 'xzxzxz', '2025-08-25 11:56:56', '2025-08-25 11:56:56'),
(7, 2, 'A-', 450, 'routine', 'didi', 'pending', 'blood', '2025-08-25 12:21:59', '2025-08-25 12:21:59'),
(8, 2, 'AB+', 450, 'routine', 'didi', 'pending', 'amina', '2025-08-25 12:24:20', '2025-08-25 12:24:20'),
(9, 2, 'B+', 450, 'urgent', 'cana', 'pending', 'hiii', '2025-08-25 12:59:16', '2025-08-25 12:59:16'),
(10, 2, 'A+', 450, 'routine', 'didi', 'pending', 'blood transfussion', '2025-08-25 13:02:26', '2025-08-25 13:02:26'),
(11, 2, 'A-', 450, 'routine', 'didi', 'pending', 'ssss', '2025-08-25 13:04:56', '2025-08-25 13:04:56'),
(12, 2, 'A+', 450, 'urgent', 'didi', 'pending', 'tftfgtgf', '2025-08-25 13:11:19', '2025-08-25 13:11:19'),
(13, 2, 'A-', 450, 'routine', 'didi', 'pending', 'helloo', '2025-08-25 13:16:11', '2025-08-25 13:16:11'),
(14, 2, 'A-', 450, 'routine', 'didi', 'pending', 'dsss', '2025-08-25 13:42:30', '2025-08-25 13:42:30'),
(15, 8, 'AB+', 450, 'emergency', 'diane', 'pending', 'trrtfhftg', '2025-08-27 13:38:29', '2025-08-27 13:38:29'),
(16, 10, 'B+', 450, 'routine', 'diane', 'pending', 'zcwed', '2025-08-29 13:07:47', '2025-08-29 13:07:47'),
(17, 10, 'A+', 450, 'routine', 'diane', 'pending', 'dc', '2025-08-29 13:12:07', '2025-08-29 13:12:07'),
(18, 10, 'A+', 450, 'urgent', 'diane', 'pending', '87ikjik', '2025-08-30 07:16:05', '2025-08-30 07:16:05'),
(19, 10, 'O-', 450, 'emergency', 'canirinyana', 'pending', 'my favorite', '2025-08-30 09:31:37', '2025-08-30 09:31:37'),
(20, 10, 'O-', 450, 'emergency', 'canirinyana', 'pending', 'my favorite', '2025-08-30 09:31:37', '2025-08-30 09:31:37'),
(21, 10, 'B+', 450, 'urgent', 'canirinyana', 'pending', 'hiii', '2025-08-30 09:37:39', '2025-08-30 09:37:39'),
(22, 10, 'A-', 450, 'routine', 'canirinyana', 'pending', 'rxstrsd', '2025-08-30 09:58:25', '2025-08-30 09:58:25'),
(23, 10, 'AB+', 1000, 'routine', 'canirinyana', 'pending', 'gbf fgbngf', '2025-08-30 10:06:17', '2025-08-30 10:06:17'),
(24, 10, 'A+', 2000, 'emergency', 'canirinyana', 'pending', 'ddddd', '2025-08-30 10:08:02', '2025-08-30 10:08:02'),
(25, 10, 'A+', 2000, 'routine', 'canirinyana', 'pending', 'heee', '2025-08-30 10:08:59', '2025-08-30 10:08:59'),
(26, 10, 'O+', 200, 'urgent', 'canirinyana', 'pending', 'dada', '2025-08-30 10:35:09', '2025-08-30 10:35:09'),
(27, 10, 'A+', 450, 'urgent', 'canirinyana', 'pending', 'zcc', '2025-08-30 11:04:36', '2025-08-30 11:04:36'),
(28, 10, 'A-', 200, 'urgent', 'diane', 'pending', 'hiii', '2025-08-30 11:27:52', '2025-08-30 11:27:52'),
(29, 10, 'O+', 500, 'emergency', 'diane', 'pending', '', '2025-08-30 11:47:31', '2025-08-30 11:47:31'),
(30, 10, 'O+', 500, 'emergency', 'diane', 'pending', '', '2025-08-30 11:47:31', '2025-08-30 11:47:31'),
(31, 10, 'A-', 450, 'routine', 'canirinyana', 'pending', '5rty y', '2025-08-30 11:51:03', '2025-08-30 11:51:03'),
(32, 10, 'B-', 450, 'routine', 'fab', 'pending', 'dsf', '2025-08-30 11:51:28', '2025-08-30 11:51:28'),
(33, 10, 'B+', 450, 'routine', 'fab', 'pending', '', '2025-08-30 11:54:01', '2025-08-30 11:54:01'),
(34, 10, 'O-', 450, 'routine', 'canirinyana', 'rejected', 'bhmnhn', '2025-08-30 11:55:31', '2025-08-30 13:38:20'),
(35, 10, 'A+', 450, 'urgent', 'fab', 'pending', 'bbbb', '2025-08-30 14:19:48', '2025-08-30 14:19:48'),
(36, 10, 'A-', 450, 'emergency', 'canirinyana', 'pending', 'loose blood', '2025-08-30 16:16:28', '2025-08-30 16:16:28'),
(37, 11, 'B-', 450, 'urgent', 'canirinyana', 'rejected', 'cfergred', '2025-09-01 12:22:39', '2025-09-01 12:24:23'),
(38, 11, 'B+', 450, 'emergency', 'canirinyana', 'rejected', 'dfxcfg', '2025-09-01 13:18:37', '2025-09-01 13:18:51'),
(39, 11, 'O-', 450, 'routine', 'fab', 'pending', 'refdvbgf', '2025-09-01 13:19:37', '2025-09-01 13:19:37'),
(40, 11, 'A-', 450, 'routine', 'canirinyana', 'rejected', 'dsfvrgtyy', '2025-09-01 13:22:44', '2025-09-01 13:23:00'),
(41, 11, 'AB-', 450, 'routine', 'fab', 'rejected', 'gffghh', '2025-09-01 13:23:56', '2025-09-01 15:40:30'),
(42, 11, 'A+', 450, 'urgent', 'canirinyana', 'rejected', 'vghbjn,', '2025-09-01 15:40:12', '2025-09-01 15:40:23');

-- --------------------------------------------------------

--
-- Stand-in structure for view `blood_stock_status`
-- (See below for the actual view)
--
CREATE TABLE `blood_stock_status` (
`entity_type` varchar(10)
,`entity_id` int(11)
,`entity_name` varchar(100)
,`blood_type` varchar(3)
,`total_quantity_ml` decimal(32,0)
,`min_threshold_ml` int(11)
,`critical_threshold_ml` int(11)
,`stock_status` varchar(8)
,`last_updated` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `blood_transfers`
--

CREATE TABLE `blood_transfers` (
  `id` int(11) NOT NULL,
  `blood_bank_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('requested','approved','in_transit','delivered','rejected') DEFAULT 'requested',
  `request_type` enum('manual','automatic') DEFAULT 'manual',
  `proposal_origin` varchar(64) DEFAULT NULL,
  `proposed_by` varchar(64) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_usage`
--

CREATE TABLE `blood_usage` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `patient_id` varchar(50) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL,
  `usage_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `purpose` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blood_usage`
--

INSERT INTO `blood_usage` (`id`, `hospital_id`, `patient_name`, `patient_id`, `blood_type`, `quantity_ml`, `usage_date`, `purpose`, `notes`) VALUES
(1, 6, 'didi', 'diane', 'B-', 1, '2025-08-24 22:00:00', 'trauma', 'lost blood'),
(2, 6, 'divine', 'divine', 'B-', 1, '2025-08-24 22:00:00', 'routine', 'lost blood'),
(3, 6, 'didi', 'diane', 'O-', 1, '2025-08-24 22:00:00', 'routine', 'weeeee'),
(4, 6, 'cana', '12', 'B+', 1, '2025-08-24 22:00:00', 'transfusion', 'weee'),
(5, 6, 'cana', '21', 'A-', 1, '2025-08-24 22:00:00', 'routine', 'dddd'),
(7, 10, 'diane', '12', 'AB+', 1, '2025-08-28 22:00:00', 'trauma', '100'),
(8, 10, 'canirinyana', '12', 'A-', 1, '2025-08-29 22:00:00', 'transfusion', 'yugyyu');

-- --------------------------------------------------------

--
-- Table structure for table `donation_appointments`
--

CREATE TABLE `donation_appointments` (
  `id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_bank_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('requested','confirmed','rescheduled','completed','cancelled') NOT NULL DEFAULT 'requested',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donation_appointments`
--

INSERT INTO `donation_appointments` (`id`, `donor_id`, `blood_bank_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2025-08-21', '19:58:00', 'rescheduled', 'ccvbvn', '2025-08-21 19:13:37', '2025-08-21 19:58:30'),
(2, 2, 1, '2025-08-21', '19:57:00', 'requested', 'dazed', '2025-08-21 19:58:01', NULL),
(3, 2, 1, '2025-08-14', '19:58:00', 'requested', 'sfsgtre', '2025-08-21 19:59:01', NULL),
(4, 2, 1, '2025-08-21', '20:15:00', 'requested', 'dwaw', '2025-08-21 20:14:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `health_conditions` text DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `donation_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`id`, `user_id`, `first_name`, `last_name`, `dob`, `gender`, `blood_type`, `phone`, `address`, `city`, `state`, `postal_code`, `health_conditions`, `last_donation_date`, `donation_count`) VALUES
(1, 2, 'Diane', 'Diane', '2009-02-12', 'female', 'A+', '0789736352', 'MIDUHA', 'KIgali', 'KIgali', 'KIgali', 'Good', '2025-08-30', 2),
(2, 3, 'alian', 'Irakoze', '2025-08-21', 'female', 'AB+', '0789736352', 'MIDUHA', 'KIgali', 'KIgali', '123', 'normal', '2025-08-23', 2),
(3, 14, 'Test', 'Donor User', '0000-00-00', 'other', 'O+', '', '', '', '', '', NULL, '2025-08-26', 3),
(4, 18, 'Test', 'Donor User', '0000-00-00', 'other', 'O+', '', '', '', '', '', NULL, '2025-08-26', 1),
(5, 22, 'John', 'Workflow', '0000-00-00', 'male', 'A+', '', '', '', '', '', NULL, '2025-08-26', 1),
(6, 41, 'cana Diane', 'canirinyana', '2025-08-28', 'male', 'O+', '0780024303', 'kabusheja', 'kigali nyanza', 'ijn', '224567', 'diane cana', '2025-08-28', 0),
(7, 10, 'Diane', 'canirinyana', '2025-08-28', 'female', 'B+', '0780024303', 'ntyazo', 'kigali nyanza', 'ijn', '224567', 'hii', '2025-08-28', 0),
(8, 48, 'felex', 'Uwayezu', '2025-09-01', 'male', 'O+', '0780024303', 'kabusheja', 'jhnjh', 'ijn', '224567', 'fghj', '2025-09-01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `donor_locations`
--

CREATE TABLE `donor_locations` (
  `donor_id` bigint(20) NOT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hospital_type` enum('public','private','specialized') NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `user_id`, `name`, `hospital_type`, `license_number`, `address`, `city`, `state`, `postal_code`, `phone`, `email`, `contact_email`) VALUES
(1, 5, 'Diane Diane', 'private', '123', 'MIDUHA', 'KIgali', 'KIgali', '123', '0789736352', 'ntyazo@gmail.com', NULL),
(2, 6, 'chuk', 'public', '123', 'MIDUHA', 'KIgali', 'KIgali', '123', '0789736352', 'chuk@gmail.com', NULL),
(3, 9, 'General Hospital', 'public', 'HOS0009', '456 Hospital Avenue, City', '', '', '', '+1-555-0456', 'hospital@example.com', NULL),
(4, 16, 'Test Hospital', 'public', 'HOS0016', '', '', '', '', '', 'testhosp1756109292@test.com', NULL),
(5, 20, 'Test Hospital', 'public', 'HOS0020', '', '', '', '', '', 'testhosp1756109307@test.com', NULL),
(6, 24, 'Workflow Hospital', 'public', 'WH0024', '', '', '', '', '', 'workflow_hospital@test.com', NULL),
(7, 34, 'cana11', 'public', 'HOS0034', '', '', '', '', '', '', 'cana2@gmail.com'),
(8, 40, 'canirinyana', 'public', 'HOS0040', '', '', '', '', '', '', 'canirinyana222@gmail.com'),
(9, 44, 'hospital', 'public', 'HOS0044', '', '', '', '', '', '', 'hospital@gmail.com'),
(10, 45, 'hospital', 'public', 'HOS0045', '', '', '', '', '', '', 'hospitali@gmail.com'),
(11, 47, 'fab', 'public', 'HOS0047', '', '', '', '', '', '', 'fab@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `hospital_blood_inventory`
--

CREATE TABLE `hospital_blood_inventory` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `quantity_ml` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expiry_date` date NOT NULL,
  `status` enum('available','reserved','expired','discarded') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospital_staff`
--

CREATE TABLE `hospital_staff` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospital_staff`
--

INSERT INTO `hospital_staff` (`id`, `hospital_id`, `name`, `email`, `phone`, `role`, `department`, `hire_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, 'Diane Diane', 'Diane@gmail.com', '0789736352', 'Administrator', 'Surgery', '2025-08-25', 'active', '2025-08-25 13:18:03', '2025-08-25 13:18:03'),
(2, 6, 'Diane Diane', 'Diane@gmail.com', '0789736352', 'Administrator', 'Surgery', '2025-08-25', 'active', '2025-08-25 13:18:03', '2025-08-25 13:18:03'),
(3, 45, 'diane canirinyana', 'canirinyanadiane@gmail.com', '0780024303', 'Blood Bank Manager', 'Administration', '2025-08-29', 'active', '2025-08-29 13:10:48', '2025-08-29 13:10:48'),
(4, 45, 'diane canirinyana', 'canirinyanadiane@gmail.com', '0780024303', 'Blood Bank Manager', 'Administration', '2025-08-29', 'active', '2025-08-29 13:10:49', '2025-08-29 13:10:49'),
(5, 47, 'felex', 'flex@gmail.com', '0780024303', 'Administrator', 'Surgery', '2025-09-01', 'active', '2025-09-01 13:21:13', '2025-09-01 13:21:13'),
(6, 47, 'diane', 'diane@gmail.com', '0780024303', 'Technician', 'Administration', '2025-09-01', 'active', '2025-09-01 13:24:31', '2025-09-01 13:24:31');

-- --------------------------------------------------------

--
-- Table structure for table `ml_training_jobs`
--

CREATE TABLE `ml_training_jobs` (
  `id` bigint(20) NOT NULL,
  `model_id` int(11) DEFAULT NULL,
  `job_name` varchar(128) DEFAULT NULL,
  `status` enum('pending','running','success','failed') DEFAULT 'pending',
  `params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`params`)),
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `log` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'donation_interval_days', '90', 'Minimum days between donations for a donor', '2025-08-20 19:12:48'),
(2, 'default_blood_shelf_life_days', '42', 'Default shelf life for blood products in days', '2025-08-20 19:12:48'),
(3, 'ai_monitoring_interval_minutes', '60', 'How often the AI checks inventory levels (in minutes)', '2025-08-20 19:12:48'),
(4, 'critical_notification_repeat_hours', '24', 'How often to repeat critical notifications (in hours)', '2025-08-20 19:12:48'),
(5, 'default_hospital_min_threshold', '1000', 'Default minimum threshold for hospital blood inventory in ml', '2025-08-20 19:12:48'),
(6, 'default_bloodbank_min_threshold', '5000', 'Default minimum threshold for blood bank inventory in ml', '2025-08-20 19:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `threshold_settings`
--

CREATE TABLE `threshold_settings` (
  `id` int(11) NOT NULL,
  `entity_type` enum('blood_bank','hospital') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `min_threshold_ml` int(11) NOT NULL,
  `critical_threshold_ml` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `threshold_settings`
--

INSERT INTO `threshold_settings` (`id`, `entity_type`, `entity_id`, `blood_type`, `min_threshold_ml`, `critical_threshold_ml`, `created_at`, `updated_at`) VALUES
(1, 'blood_bank', 1, 'A+', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(2, 'blood_bank', 1, 'A-', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(3, 'blood_bank', 1, 'B+', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(4, 'blood_bank', 1, 'B-', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(5, 'blood_bank', 1, 'AB+', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(6, 'blood_bank', 1, 'AB-', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(7, 'blood_bank', 1, 'O+', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(8, 'blood_bank', 1, 'O-', 5000, 1000, '2025-08-21 15:16:55', '2025-08-21 15:16:55'),
(9, 'hospital', 1, 'A+', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(10, 'hospital', 1, 'A-', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(11, 'hospital', 1, 'B+', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(12, 'hospital', 1, 'B-', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(13, 'hospital', 1, 'AB+', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(14, 'hospital', 1, 'AB-', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(15, 'hospital', 1, 'O+', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(16, 'hospital', 1, 'O-', 1000, 200, '2025-08-21 15:19:21', '2025-08-21 15:19:21'),
(17, 'hospital', 2, 'A+', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(18, 'hospital', 2, 'A-', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(19, 'hospital', 2, 'B+', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(20, 'hospital', 2, 'B-', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(21, 'hospital', 2, 'AB+', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(22, 'hospital', 2, 'AB-', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(23, 'hospital', 2, 'O+', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32'),
(24, 'hospital', 2, 'O-', 1000, 200, '2025-08-23 11:09:32', '2025-08-23 11:09:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','donor','bloodbank','hospital') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`, `last_login`, `status`) VALUES
(1, 'admin', '$2y$10$aZRx5hjgiOC/ZC8fF7HpReUl.4YdmwFtzFuvrYTOwOtrIbEFfp.vC', 'admin@bloodmanagement.com', 'admin', '2025-08-20 19:12:48', NULL, 'active'),
(2, 'Diane', '$2y$10$tk/B2aX4ulhLkSyL7PnjNOP1OFD0s3xlfZXdEP737Ys/il.W8a4oy', 'Diane@gmail.com', 'admin', '2025-08-20 19:45:22', '2025-08-20 20:38:38', 'active'),
(3, 'aliane', '$2y$10$yjeSaqLqam09ImskTnNu5Oibs1XHaWmXw1egBMIcjhKICRyKmrZLO', 'aliane@gmail.com', 'donor', '2025-08-21 13:57:18', '2025-08-22 18:07:05', 'active'),
(4, 'falge', '$2y$10$y5quSWjQZs9rPio4I7oLseomPRDL.krjqIV/c7RAZT25rhgCbZZ..', 'falge@gmail.com', 'bloodbank', '2025-08-21 15:16:55', '2025-08-23 07:12:38', 'active'),
(5, 'ntyazo', '$2y$10$LApYvV6hWtjKB1QGYNuEGuvgiq2jhsw9qLVvQ0INpoGW3NqpuE8dW', 'ntyazo@gmail.com', 'hospital', '2025-08-21 15:19:21', '2025-08-21 15:19:42', 'active'),
(6, 'chuk', '$2y$10$UpSB9DIApWadXYczYGP1sOSRYJ2hVtpYiI6W/UkBfW4qyLvBj5/72', 'chuk@gmail.com', 'hospital', '2025-08-23 11:09:32', '2025-08-25 11:38:57', 'active'),
(7, 'donor1', '$2y$10$DXrwc4dJCiTr7cPlc2oCYuGfH8tOPFdKZxV8ruojcKwcCunS4w99O', 'donor@example.com', 'donor', '2025-08-25 07:25:34', NULL, 'active'),
(8, 'bloodbank1', '$2y$10$VuVtTxRdd7w8cAQ1TicZvuVpUaaO.HNqvyYyRM6CxhKyXXlfdMgdK', 'bloodbank@example.com', 'bloodbank', '2025-08-25 07:27:09', NULL, 'active'),
(9, 'hospital1', '$2y$10$03GCLOGuJB.Td6PTUsmrb.9V/MoHTue7w3iJwZtK0p7nDokeh67Km', 'hospital@example.com', 'hospital', '2025-08-25 07:27:09', NULL, 'active'),
(10, 'uwase', '$2y$10$C2hD.h2utNQOY5RLl.whWezksOvH7I0L2T.xqap9ET4S2gfu8OIgq', 'uwase@gmail.com', 'donor', '2025-08-25 07:37:01', '2025-08-29 12:54:42', 'active'),
(11, 'aliane@gmail.com', '$2y$10$ssH34VSIMo4sX9FvJnvWD.Z8KyQTED.EKh3QJz5AlZRN0RxxgZngS', 'aliane1@gmail.com', 'bloodbank', '2025-08-25 08:01:22', NULL, 'active'),
(12, 'dianee', '$2y$10$XvwbqN/re8D4d8DVs2I4c.FHHgHnuxKAMtIMOveHa3TVPsgNhgQcK', 'dianee@gmail.com', 'bloodbank', '2025-08-25 08:05:39', '2025-08-25 08:06:20', 'active'),
(13, 'test_admin_1756109292', '$2y$10$lo4XxcFY01LkF2GuUwk8RuHsTQ/4htX7TwA6GwflMXY5.Qpn6j5FK', 'testadmin1756109292@test.com', 'admin', '2025-08-25 08:08:12', NULL, 'active'),
(14, 'test_donor_1756109292', '$2y$10$X1JcbHLdyy6A6ALEmNG6O.VYDAMFKqyqukXYXu1UESnpTtTR3h4xO', 'testdonor1756109292@test.com', 'donor', '2025-08-25 08:08:12', NULL, 'active'),
(15, 'test_bloodbank_1756109292', '$2y$10$gqw3oeMWONr.SkSS0Mk.eO73i.e2GZk1lYsprqcEwqhrOFCeZfoKe', 'testbb1756109292@test.com', 'bloodbank', '2025-08-25 08:08:12', NULL, 'active'),
(16, 'test_hospital_1756109292', '$2y$10$BpV5sTX5giiqZW/CJF0xHOgDZJ.8oq7yyQJmwS4teqqUEgNSaWHL.', 'testhosp1756109292@test.com', 'hospital', '2025-08-25 08:08:12', NULL, 'active'),
(17, 'test_admin_1756109307', '$2y$10$viHDraw5wzrx2PRYl0bl5edCBOFlVoi05y6D6w07PIY4vqVoGaJ3S', 'testadmin1756109307@test.com', 'admin', '2025-08-25 08:08:27', NULL, 'active'),
(18, 'test_donor_1756109307', '$2y$10$aNCx/saehwNNoeWywyphfuulSGHUYb/Ff/AFcShW/ugWZQnCqh2UK', 'testdonor1756109307@test.com', 'donor', '2025-08-25 08:08:27', NULL, 'active'),
(19, 'test_bloodbank_1756109307', '$2y$10$/O4KXq6g4RBRESB1ZikdzOGkYhsbiyhrENjiO1MpaMC3sRM8vrtJO', 'testbb1756109307@test.com', 'bloodbank', '2025-08-25 08:08:27', NULL, 'active'),
(20, 'test_hospital_1756109307', '$2y$10$VRonNQ9JHNLR3nLVMl06k.X4OMD.A1BnLTQbJ6lfDRbM0uJFENEKy', 'testhosp1756109307@test.com', 'hospital', '2025-08-25 08:08:28', NULL, 'active'),
(21, 'workflow_admin', '$2y$10$mlPzrtOHqrOpQ0aVSkXSHOqzeWMovyiPYW3K9PuKN220xHAcpj5Lm', 'workflow_admin@test.com', 'admin', '2025-08-25 08:12:44', NULL, 'active'),
(22, 'workflow_donor', '$2y$10$POsoKBlVuDDSC5eK.P2zg..ttPNup71kiN9Nc8g2jAO.lJ.51N6Lm', 'workflow_donor@test.com', 'donor', '2025-08-25 08:12:44', NULL, 'active'),
(23, 'workflow_bloodbank', '$2y$10$BDzn/399wVtdk./a4m8QJ.dH7XWncSuPZuE8os9O5/LytDTj.KRlm', 'workflow_bb@test.com', 'bloodbank', '2025-08-25 08:12:44', '2025-08-25 08:18:07', 'active'),
(24, 'workflow_hospital', '$2y$10$ZetZP0ClaKYoBhf2CY1E3uQkcVPMFIvkyJj6XQ6n6GfpFtEcZbz1u', 'workflow_hospital@test.com', 'hospital', '2025-08-25 08:12:44', NULL, 'active'),
(25, 'bijou', '$2y$10$.T0Vm5lxhtvKCOMWhAlV5ejBdP/FhgafOVvzOJ4NzhKkGhu8XHlZG', 'bijou@gmail.com', 'donor', '2025-08-25 16:30:49', '2025-08-27 14:28:35', 'active'),
(26, 'aminaa', '$2y$10$ANUS5Mboz0hLTWDOPHk5P.V7xkydPoFcsjnWDnYnAM3W7mH1T1yFO', 'aminaa@gmail.com', 'bloodbank', '2025-08-25 16:32:37', NULL, 'active'),
(27, 'mugisha', '$2y$10$P5zMZWFIWR6fxPRc.W8fQuePc.J.qWw2L6KyhaBtTSdbAKfjskj9e', 'mugisha@gmail.com', 'bloodbank', '2025-08-25 16:40:14', NULL, 'active'),
(28, 'StockMoogame', '$2y$10$8zOLmDYGhc8eBnpDPv84SODCoZnil9rDTbkxVbRfjMKEKoQAvEf7W', 'StockMoogame@gmail.com', 'bloodbank', '2025-08-26 08:56:28', NULL, 'active'),
(29, 'yujuk', '$2y$10$uzDkLUn0LTiABTACEC5Kx.XdGpSVg4k4UZdFoVj9CIl0GgrzGvmgy', 'yuio@gmail.com', 'donor', '2025-08-26 10:46:45', NULL, 'active'),
(30, 'cana', '$2y$10$fdm43lfDG.wMBRPLfOhTy.ibdroj2dOOrYoE7/e6L8rnOsanjIc7O', 'cana@gmail.com', 'hospital', '2025-08-26 10:59:39', NULL, 'active'),
(31, 'cana1', '$2y$10$ZOtlpRN.SLhH9WjvgKKPM.mfE4kn/OpsaHT3uJs36qhrB6Ax3SvfS', 'cana1@gmail.com', 'hospital', '2025-08-26 11:05:25', NULL, 'active'),
(32, 'cana11', '$2y$10$2WIKo5tp9zjLNKEMM8uGFec/E.EZCjXI2xO7L/Y3tK1ge/Io9LlVy', 'cana11@gmail.com', 'hospital', '2025-08-26 11:09:02', NULL, 'active'),
(33, 'cana111', '$2y$10$eUY8FoP5kLXpHYpXwyyaB.bzmeiVrbSj/8n2aRZI2DGzzy1506jjC', 'cana111@gmail.com', 'hospital', '2025-08-26 11:10:42', NULL, 'active'),
(34, 'cana2', '$2y$10$FQqyh6g/cF7v2li1t6QmQugYs.96eaC0D.Qe3dOIvY3ylmn58U7Zi', 'cana2@gmail.com', 'hospital', '2025-08-26 11:12:46', NULL, 'active'),
(35, 'ishimwe', '$2y$10$tSXY/UIpHXFdvR0x1C6.Me2lUlrh60x2tNGMO.y66f7/vwMWBZxI.', 'ishimwe@gmail.com', 'donor', '2025-08-26 11:46:49', '2025-08-26 16:49:45', 'active'),
(36, 'ishimwe1', '$2y$10$bdGKLnjfIDRlgWp1sXP2Iule0XOLXr0TB8dRUsfhR8aTJGYgx9NiG', 'ishimwe1@gmail.com', 'bloodbank', '2025-08-26 11:48:03', NULL, 'active'),
(37, 'canira', '$2y$10$l58HBh9DpxmgDJ/W/F.AA.O.6lrwbE3Gjit5/nEpCC9mb/w64aMXu', 'canira@gmail.com', 'bloodbank', '2025-08-26 17:26:58', NULL, 'active'),
(38, 'canirinyana', '$2y$10$jxKXWftaG75Jw6B7hpbYH.4E1UjoaYIpH6FsIkY25cCX.bhFeWc.6', 'canirinyana@gmail.com', 'donor', '2025-08-26 17:48:43', NULL, 'active'),
(39, 'canirinyana1', '$2y$10$P4YpFCMhfnxScUcpnvKW3ubAwR1T5hzjHq7SAlzTqo2ATwEiqDq5m', 'canirinyana1@gmail.com', 'donor', '2025-08-27 08:16:57', NULL, 'active'),
(40, 'canirinyana222', '$2y$10$HyNVkBU4OB.QMcCukLDKuumJI32WGO6SNcrvoaigUpITHu8vpf9U6', 'canirinyana222@gmail.com', 'hospital', '2025-08-27 13:32:53', '2025-08-27 14:07:27', 'active'),
(41, 'ally', '$2y$10$A8ALMz6Ztiq22HmGdXy66.HcgcTNzKq2d58u.wr7kHUoWUiHfUKri', 'ally@gmail.com', 'donor', '2025-08-28 12:46:21', NULL, 'active'),
(42, 'grace', '$2y$10$pXQqaCpo4ddMzcu0Zq7ZlupJMTGduSdLTb1ig4agfoPWTePuCRmVi', 'grace@gmail.com', 'bloodbank', '2025-08-29 12:57:01', NULL, 'active'),
(43, 'yvette', '$2y$10$RML7KFHrLr476kj6IJ2dX./Kdw0Cny1w8liLBWmlIyP./faoeHLXi', 'yvette@gmail.com', 'bloodbank', '2025-08-29 12:57:56', '2025-08-29 12:58:06', 'active'),
(44, 'hospital', '$2y$10$rTZTdeDmOzzXCe4uIfk1reWBzGXPsYACmlsIVW77b6Pwy2c/fL0rC', 'hospital@gmail.com', 'hospital', '2025-08-29 13:00:23', NULL, 'active'),
(45, 'hospitali', '$2y$10$ft7tlseM81mhi.WOVcOJpeRGoBqYxPdsvO/BvB5Vtm6fXwjJ5u.WC', 'hospitali@gmail.com', 'hospital', '2025-08-29 13:01:28', '2025-08-29 13:01:32', 'active'),
(46, 'nyana', '$2y$10$Ff3CREByBdPQFiC3NgCUM.ZUsLMWVYfr.YLViNtq2sLpavXTkIrf.', 'nyana2003@gmail.com', 'bloodbank', '2025-08-30 17:23:59', '2025-09-01 15:41:10', 'active'),
(47, 'fab', '$2y$10$sdcS1P4cTlyWDqdAKreR2ug3ORBV1Go/nsbTeEOqK7yHbaawsbdbS', 'fab@gmail.com', 'hospital', '2025-09-01 10:04:15', '2025-09-01 15:39:48', 'active'),
(48, 'flex', '$2y$10$8xFmTrRGsrP3Q1jQFFaIaeKZWZnNumrbOiYd2db/fLtVJVeMCjAJi', 'flex@gmail.com', 'donor', '2025-09-01 13:25:19', '2025-09-01 15:39:25', 'active');

-- --------------------------------------------------------

--
-- Structure for view `blood_stock_status`
--
DROP TABLE IF EXISTS `blood_stock_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `blood_stock_status`  AS SELECT 'blood_bank' AS `entity_type`, `bb`.`id` AS `entity_id`, `bb`.`name` AS `entity_name`, `bi`.`blood_type` AS `blood_type`, sum(`bi`.`quantity_ml`) AS `total_quantity_ml`, `ts`.`min_threshold_ml` AS `min_threshold_ml`, `ts`.`critical_threshold_ml` AS `critical_threshold_ml`, CASE WHEN sum(`bi`.`quantity_ml`) < `ts`.`critical_threshold_ml` THEN 'critical' WHEN sum(`bi`.`quantity_ml`) < `ts`.`min_threshold_ml` THEN 'low' ELSE 'normal' END AS `stock_status`, max(`bi`.`last_updated`) AS `last_updated` FROM ((`blood_banks` `bb` join `blood_inventory` `bi` on(`bb`.`id` = `bi`.`blood_bank_id`)) join `threshold_settings` `ts` on(`ts`.`entity_type` = 'blood_bank' and `ts`.`entity_id` = `bb`.`id` and `ts`.`blood_type` = `bi`.`blood_type`)) WHERE `bi`.`status` = 'available' AND `bi`.`expiry_date` > curdate() GROUP BY `bb`.`id`, `bi`.`blood_type`union all select 'hospital' AS `entity_type`,`h`.`id` AS `entity_id`,`h`.`name` AS `entity_name`,`hbi`.`blood_type` AS `blood_type`,sum(`hbi`.`quantity_ml`) AS `total_quantity_ml`,`ts`.`min_threshold_ml` AS `min_threshold_ml`,`ts`.`critical_threshold_ml` AS `critical_threshold_ml`,case when sum(`hbi`.`quantity_ml`) < `ts`.`critical_threshold_ml` then 'critical' when sum(`hbi`.`quantity_ml`) < `ts`.`min_threshold_ml` then 'low' else 'normal' end AS `stock_status`,max(`hbi`.`last_updated`) AS `last_updated` from ((`hospitals` `h` join `hospital_blood_inventory` `hbi` on(`h`.`id` = `hbi`.`hospital_id`)) join `threshold_settings` `ts` on(`ts`.`entity_type` = 'hospital' and `ts`.`entity_id` = `h`.`id` and `ts`.`blood_type` = `hbi`.`blood_type`)) where `hbi`.`status` = 'available' and `hbi`.`expiry_date` > curdate() group by `h`.`id`,`hbi`.`blood_type`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_anomalies`
--
ALTER TABLE `ai_anomalies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_eligibility_checks`
--
ALTER TABLE `ai_eligibility_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`);

--
-- Indexes for table `ai_forecasts`
--
ALTER TABLE `ai_forecasts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`,`blood_type`,`forecast_date`),
  ADD KEY `model_version_id` (`model_version_id`);

--
-- Indexes for table `ai_learning_data`
--
ALTER TABLE `ai_learning_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_models`
--
ALTER TABLE `ai_models`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_model_versions`
--
ALTER TABLE `ai_model_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `model_id` (`model_id`);

--
-- Indexes for table `ai_notifications`
--
ALTER TABLE `ai_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_predictions`
--
ALTER TABLE `ai_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `target_date` (`target_date`),
  ADD KEY `model_version_id` (`model_version_id`);

--
-- Indexes for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `blood_bank_id` (`blood_bank_id`);

--
-- Indexes for table `blood_banks`
--
ALTER TABLE `blood_banks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blood_donations`
--
ALTER TABLE `blood_donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `blood_bank_id` (`blood_bank_id`);

--
-- Indexes for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blood_bank_id` (`blood_bank_id`,`blood_type`,`expiry_date`);

--
-- Indexes for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blood_transfers`
--
ALTER TABLE `blood_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blood_bank_id` (`blood_bank_id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `blood_usage`
--
ALTER TABLE `blood_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `donation_appointments`
--
ALTER TABLE `donation_appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `donor_locations`
--
ALTER TABLE `donor_locations`
  ADD PRIMARY KEY (`donor_id`),
  ADD KEY `latitude` (`latitude`),
  ADD KEY `longitude` (`longitude`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `hospital_blood_inventory`
--
ALTER TABLE `hospital_blood_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hospital_id` (`hospital_id`,`blood_type`,`expiry_date`);

--
-- Indexes for table `hospital_staff`
--
ALTER TABLE `hospital_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `ml_training_jobs`
--
ALTER TABLE `ml_training_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `model_id` (`model_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `threshold_settings`
--
ALTER TABLE `threshold_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_type` (`entity_type`,`entity_id`,`blood_type`);

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
-- AUTO_INCREMENT for table `ai_anomalies`
--
ALTER TABLE `ai_anomalies`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_eligibility_checks`
--
ALTER TABLE `ai_eligibility_checks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_forecasts`
--
ALTER TABLE `ai_forecasts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_learning_data`
--
ALTER TABLE `ai_learning_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_models`
--
ALTER TABLE `ai_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_model_versions`
--
ALTER TABLE `ai_model_versions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_notifications`
--
ALTER TABLE `ai_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ai_predictions`
--
ALTER TABLE `ai_predictions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `blood_banks`
--
ALTER TABLE `blood_banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `blood_donations`
--
ALTER TABLE `blood_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `blood_requests`
--
ALTER TABLE `blood_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `blood_transfers`
--
ALTER TABLE `blood_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_usage`
--
ALTER TABLE `blood_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `donation_appointments`
--
ALTER TABLE `donation_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `hospital_blood_inventory`
--
ALTER TABLE `hospital_blood_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hospital_staff`
--
ALTER TABLE `hospital_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ml_training_jobs`
--
ALTER TABLE `ml_training_jobs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `threshold_settings`
--
ALTER TABLE `threshold_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_forecasts`
--
ALTER TABLE `ai_forecasts`
  ADD CONSTRAINT `ai_forecasts_ibfk_1` FOREIGN KEY (`model_version_id`) REFERENCES `ai_model_versions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ai_model_versions`
--
ALTER TABLE `ai_model_versions`
  ADD CONSTRAINT `ai_model_versions_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_predictions`
--
ALTER TABLE `ai_predictions`
  ADD CONSTRAINT `ai_predictions_ibfk_1` FOREIGN KEY (`model_version_id`) REFERENCES `ai_model_versions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blood_banks`
--
ALTER TABLE `blood_banks`
  ADD CONSTRAINT `blood_banks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blood_donations`
--
ALTER TABLE `blood_donations`
  ADD CONSTRAINT `blood_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blood_donations_ibfk_2` FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD CONSTRAINT `blood_inventory_ibfk_1` FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blood_transfers`
--
ALTER TABLE `blood_transfers`
  ADD CONSTRAINT `blood_transfers_ibfk_1` FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blood_transfers_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blood_usage`
--
ALTER TABLE `blood_usage`
  ADD CONSTRAINT `blood_usage_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `donors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD CONSTRAINT `hospitals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospital_blood_inventory`
--
ALTER TABLE `hospital_blood_inventory`
  ADD CONSTRAINT `hospital_blood_inventory_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospital_staff`
--
ALTER TABLE `hospital_staff`
  ADD CONSTRAINT `hospital_staff_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ml_training_jobs`
--
ALTER TABLE `ml_training_jobs`
  ADD CONSTRAINT `ml_training_jobs_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 15, 2026 at 03:46 PM
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
-- Database: `kids_journey`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` varchar(20) DEFAULT NULL COMMENT 'Denormalized for quick display (e.g. STF-0001)',
  `role` varchar(50) NOT NULL,
  `action` varchar(150) NOT NULL,
  `module` varchar(100) NOT NULL,
  `status` enum('Success','Failed','Pending') NOT NULL DEFAULT 'Success',
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `staff_id`, `role`, `action`, `module`, `status`, `details`, `ip_address`, `created_at`, `target`) VALUES
(1, 2, NULL, 'Human Resources', 'Enrolled Student', 'Student Enrollment', 'Success', 'Enrolled student: Alexis Joy Rosales (STD-0008)', '::1', '2026-03-14 18:59:53', NULL),
(2, 2, NULL, 'Human Resources', 'Enrolled Student', 'Student Enrollment', 'Success', 'Enrolled student: Aldwine Jed Rosales (STD-0009)', '::1', '2026-03-15 12:39:06', NULL),
(3, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:21:29', 'STF-0007 – ivan cuizon'),
(4, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0003 – Junelle Eljon Ursua (Educator)', '::1', '2026-03-15 13:21:35', 'STF-0003 – Junelle Eljon Ursua'),
(5, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:21:40', 'STF-0007 – ivan cuizon'),
(6, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0003 – Junelle Eljon Ursua (Educator)', '::1', '2026-03-15 13:21:42', 'STF-0003 – Junelle Eljon Ursua'),
(7, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0002 – James Ivan Tayabas (Educator)', '::1', '2026-03-15 13:21:53', 'STF-0002 – James Ivan Tayabas'),
(8, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0003 – Junelle Eljon Ursua (Educator)', '::1', '2026-03-15 13:21:56', 'STF-0003 – Junelle Eljon Ursua'),
(9, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:22:03', 'STF-0007 – ivan cuizon'),
(10, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0002 – James Ivan Tayabas (Educator)', '::1', '2026-03-15 13:22:08', 'STF-0002 – James Ivan Tayabas'),
(11, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0003 – Junelle Eljon Ursua (Educator)', '::1', '2026-03-15 13:22:09', 'STF-0003 – Junelle Eljon Ursua'),
(12, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:22:11', 'STF-0007 – ivan cuizon'),
(13, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:29:05', 'STF-0007 – ivan cuizon'),
(14, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:29:07', 'STF-0007 – ivan cuizon'),
(15, 1, 'STF-0000', 'Admin', 'Suspended User Account', 'User Access Management', 'Success', 'Account suspended for: STF-0007 – ivan cuizon (Educator)', '::1', '2026-03-15 13:29:11', 'STF-0007 – ivan cuizon'),
(16, 1, 'STF-0000', 'Admin', 'Suspended User Account', 'User Access Management', 'Success', 'Account suspended for: STF-0003 – Junelle Eljon Ursua (Educator)', '::1', '2026-03-15 13:43:46', 'STF-0003 – Junelle Eljon Ursua'),
(17, 1, 'STF-0000', 'Admin', 'Deactivated User Account', 'User Access Management', 'Success', 'Account deactivated for: STF-0002 – James Ivan Tayabas (Educator)', '::1', '2026-03-15 13:43:50', 'STF-0002 – James Ivan Tayabas'),
(18, 1, 'STF-0000', 'Admin', 'Activated User Account', 'User Access Management', 'Success', 'Account activated for: STF-0002 – James Ivan Tayabas (Educator)', '::1', '2026-03-15 13:52:48', 'STF-0002 – James Ivan Tayabas');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `session_date` date NOT NULL,
  `status` enum('Present','Absent') NOT NULL,
  `rescheduled_to` date DEFAULT NULL COMMENT 'Auto-set when student is Absent',
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_submissions`
--

CREATE TABLE `contact_submissions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `status` enum('Pending','Active','Inactive') NOT NULL DEFAULT 'Pending',
  `enrolled_by` int(11) DEFAULT NULL COMMENT 'user_id of HR who enrolled the student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `service_id`, `status`, `enrolled_by`, `created_at`, `updated_at`, `archived`) VALUES
(1, 1, 2, 'Pending', 2, '2026-03-11 12:23:08', '2026-03-13 13:40:21', 0),
(2, 2, 1, 'Active', 2, '2026-03-13 09:30:42', '2026-03-14 11:59:01', 0),
(3, 3, 2, 'Pending', 2, '2026-03-13 09:31:32', '2026-03-14 17:56:09', 0),
(4, 4, 1, 'Pending', 2, '2026-03-13 09:32:21', '2026-03-14 17:57:09', 0),
(5, 5, 1, 'Pending', 2, '2026-03-13 09:33:17', '2026-03-13 23:00:52', 0),
(6, 6, 1, 'Pending', 2, '2026-03-13 09:34:22', '2026-03-13 23:07:08', 0),
(7, 7, 1, 'Pending', 2, '2026-03-13 09:35:07', '2026-03-13 23:07:24', 0),
(8, 8, 9, 'Pending', 2, '2026-03-14 18:59:53', '2026-03-14 18:59:53', 0),
(9, 9, 9, 'Pending', 2, '2026-03-15 12:39:06', '2026-03-15 12:39:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `guardians`
--

CREATE TABLE `guardians` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `guardian_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guardians`
--

INSERT INTO `guardians` (`id`, `student_id`, `guardian_name`, `contact_number`, `email`, `created_at`) VALUES
(1, 1, 'Maria Masaga', '09983077832', 'rosalesalvinnejoy@gmail.com', '2026-03-11 12:23:08'),
(2, 2, ' Maria Teresa Santos', '0917-345-8214', 'liam.santos@email.com', '2026-03-13 09:30:42'),
(3, 3, ' Carlos Rodriguez', ' 0928-516-7392 ', 'ariana.rodriguez@email.com', '2026-03-13 09:31:32'),
(4, 4, 'Angela Cruz', '0919-774-6531', 'joshua.cruz@email.com', '2026-03-13 09:32:21'),
(5, 5, 'Sophia Nicole Aquino Reyes', '0916-438-5729', 'sophia.reyes@email.com', '2026-03-13 09:33:17'),
(6, 6, 'Ethan Miguel Flores', ' 0916-438-5729 ', 'ethan.flores@email.com', '2026-03-13 09:34:22'),
(7, 7, 'Isabella Grace Mendoza', ' 0927-693-8045 ', 'isabella.mendoza@email.com', '2026-03-13 09:35:07'),
(8, 8, 'Lilia Valle', '09983077832', '2022-103005@rtu.edu.ph', '2026-03-14 18:59:53'),
(9, 9, 'Alvin Rosales', '09983077832', 'bbinne.rosales@gmail.com', '2026-03-15 12:39:06');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 13, '1beda529febf78075109f371da7c5d92b923e4a52bdd7040d8929b96ee3af2c2', '2026-03-13 12:23:05', '2026-03-13 19:23:05');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','GCash','Card') NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL COMMENT 'GCash reference number',
  `payment_status` enum('paid','pending','overdue','installment') DEFAULT 'pending',
  `enrollment_status` enum('Pending','Active','Inactive') DEFAULT 'Pending',
  `installment_plan` enum('1 Month','2 Months','3 Months') DEFAULT NULL,
  `pay_every` enum('7th of the Month','14th of the Month','21st of the Month','28th of the Month') DEFAULT NULL,
  `payment_number` int(11) DEFAULT NULL COMMENT 'e.g. 1st, 2nd, 3rd installment',
  `notes` text DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` int(11) DEFAULT NULL COMMENT 'user_id who recorded the payment',
  `archived` tinyint(1) DEFAULT 0,
  `installment_pay_every` varchar(30) DEFAULT NULL,
  `installment_payment_num` int(11) DEFAULT 1,
  `installment_total_paid` int(11) DEFAULT 0,
  `gcash_account_name` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(20) DEFAULT NULL,
  `installment_paid_count` int(11) DEFAULT 0,
  `installment_amount_per` int(11) DEFAULT 0,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `remaining_balance` decimal(10,2) DEFAULT 0.00,
  `total_service_amount` decimal(10,2) DEFAULT 0.00,
  `installment_next_due` date DEFAULT NULL,
  `last_notified_at` datetime DEFAULT NULL,
  `payment_due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `transaction_id`, `enrollment_id`, `student_id`, `payment_amount`, `payment_method`, `reference_no`, `payment_status`, `enrollment_status`, `installment_plan`, `pay_every`, `payment_number`, `notes`, `payment_date`, `recorded_by`, `archived`, `installment_pay_every`, `installment_payment_num`, `installment_total_paid`, `gcash_account_name`, `gcash_number`, `installment_paid_count`, `installment_amount_per`, `amount_paid`, `remaining_balance`, `total_service_amount`, `installment_next_due`, `last_notified_at`, `payment_due_date`) VALUES
(1, 1, 1, 1, 600.00, 'Cash', '', 'installment', 'Pending', '3 Months', NULL, NULL, '', '2026-03-11 12:23:08', 2, 0, '14th of the Month', 2, 3, '', '', 2, 600, 1200.00, 600.00, 1800.00, '2026-06-14', NULL, NULL),
(2, 2, 2, 2, 2150.00, 'Cash', '', 'installment', 'Pending', '2 Months', NULL, NULL, '', '2026-03-13 09:30:42', 2, 0, '30th of the Month', 1, 2, '', '', 1, 2150, 2150.00, 2150.00, 4300.00, '2026-05-30', '2026-03-15 00:16:06', NULL),
(3, 3, 3, 3, 1800.00, 'Cash', '', 'paid', 'Pending', NULL, NULL, NULL, '', '2026-03-13 09:31:32', 2, 1, NULL, 1, 0, '', '', 0, 0, 1800.00, 0.00, 0.00, NULL, NULL, NULL),
(4, 4, 4, 4, 2500.00, 'Cash', '', 'installment', 'Pending', '2 Months', NULL, NULL, '', '2026-03-13 09:32:21', 2, 0, '14th of the Month', 1, 2, '', '', 0, 1250, 0.00, 2500.00, 2500.00, '2026-04-14', NULL, NULL),
(5, 5, 5, 5, 2500.00, 'Cash', '', 'installment', 'Pending', '2 Months', NULL, NULL, '', '2026-03-13 09:33:17', 2, 0, '30th of the Month', 1, 2, '', '', 0, 1250, 0.00, 2500.00, 2500.00, '2026-04-30', NULL, NULL),
(6, 6, 6, 6, 2500.00, 'GCash', '', 'installment', 'Pending', '2 Months', NULL, NULL, '', '2026-03-13 09:34:23', 2, 0, '30th of the Month', 1, 2, '', '', 0, 1250, 0.00, 2500.00, 2500.00, '2026-04-30', NULL, NULL),
(7, 7, 7, 7, 2500.00, 'Cash', '', 'paid', 'Pending', '2 Months', NULL, NULL, '', '2026-03-13 09:35:08', 2, 1, '30th of the Month', 1, 0, '', '', 0, 0, 2500.00, 0.00, 0.00, NULL, NULL, NULL),
(8, 8, 8, 8, 8000.00, 'Cash', '', 'pending', 'Pending', NULL, NULL, NULL, NULL, '2026-03-14 18:59:53', 2, 0, NULL, 1, 0, NULL, NULL, 0, 0, 0.00, 0.00, 0.00, NULL, NULL, '2026-03-18'),
(9, 9, 9, 9, 6000.00, 'Cash', '', 'pending', 'Pending', NULL, NULL, NULL, NULL, '2026-03-15 12:39:06', 2, 0, NULL, 1, 0, NULL, NULL, 0, 0, 0.00, 0.00, 0.00, NULL, NULL, '2026-03-18');

-- --------------------------------------------------------

--
-- Table structure for table `payment_notifications`
--

CREATE TABLE `payment_notifications` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `guardian_email` varchar(255) NOT NULL,
  `notification_type` enum('manual','automatic') NOT NULL DEFAULT 'manual',
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_notifications`
--

INSERT INTO `payment_notifications` (`id`, `payment_id`, `student_id`, `guardian_email`, `notification_type`, `status`, `sent_at`) VALUES
(1, 1, 0, 'rosalesalvinnejoy@gmail.com', 'manual', 'sent', '2026-03-15 00:00:09'),
(2, 1, 0, 'rosalesalvinnejoy@gmail.com', 'manual', 'sent', '2026-03-15 00:05:48'),
(3, 1, 0, 'rosalesalvinnejoy@gmail.com', 'manual', 'sent', '2026-03-15 00:15:18'),
(4, 2, 0, 'liam.santos@email.com', 'manual', 'sent', '2026-03-15 00:16:06');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `schedule_type` enum('Student','Staff') NOT NULL DEFAULT 'Student',
  `title` varchar(255) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `package` varchar(100) DEFAULT NULL,
  `day_of_week` set('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` enum('SML 1','SML 2','SLL 1','SLL 2','OT 1','SLP 1','ABA 1','TIL 1','Session Hall','Conference Room') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive','Cancelled') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `program_name` varchar(255) DEFAULT NULL COMMENT 'e.g. Sensory Motor Learning',
  `service_name` varchar(255) DEFAULT NULL COMMENT 'e.g. Package A',
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'e.g. Registration, Assessment, Package',
  `price` decimal(10,2) DEFAULT NULL,
  `frequency` enum('One Time','Per Hour','Per 2 Hours','1x a Week','2x a Week','3x a Week','4x a Week','Per Session') DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `program_name`, `service_name`, `description`, `category`, `price`, `frequency`, `status`, `last_updated`, `created_at`, `archived`) VALUES
(1, 'Play Group', 'Play Group Program', '', NULL, 2500.00, '4x a Week', 'active', '2026-03-11 12:18:15', '2026-03-11 11:52:48', 0),
(2, 'Sensory Motor Learning ', 'OT Assessment', '1800/2500', NULL, 1800.00, 'One Time', 'active', '2026-03-11 12:21:25', '2026-03-11 12:21:25', 0),
(3, 'Sensory Motor Learning ', 'Package A', '2x with OT', NULL, 7500.00, '3x a Week', 'active', '2026-03-13 10:26:40', '2026-03-13 09:57:48', 0),
(4, 'Sensory Motor Learning ', 'Package B', '2x with OT', NULL, 5500.00, '2x a Week', 'active', '2026-03-13 10:34:25', '2026-03-13 10:03:29', 0),
(5, 'Sensory Motor Learning ', 'Package C', '2x with OT', NULL, 8100.00, '4x a Week', 'active', '2026-03-13 10:03:52', '2026-03-13 10:03:52', 0),
(6, 'Sensory Motor Learning ', 'Package D', 'Per Session w/ OT', NULL, 1600.00, 'Per Session', 'active', '2026-03-13 10:04:23', '2026-03-13 10:04:23', 0),
(7, 'Speech Language Learning', 'SLP Assessment', '', NULL, 3000.00, 'One Time', 'active', '2026-03-13 10:38:57', '2026-03-13 10:05:07', 0),
(8, 'Speech Language Learning', 'Package A', '2x with SLP', NULL, 4000.00, '1x a Week', 'active', '2026-03-13 10:44:19', '2026-03-13 10:19:39', 0),
(9, 'Speech Language Learning', 'Package B', '2x with SLP', NULL, 6000.00, '2x a Week', 'active', '2026-03-14 17:49:32', '2026-03-14 17:48:09', 0),
(10, 'Speech Language Learning', 'Package C', '', NULL, 2000.00, 'Per Session', 'active', '2026-03-14 17:58:56', '2026-03-14 17:58:56', 0),
(11, 'Applied Behavioral Analysis', 'ABA Assessment', '', '', 2000.00, 'One Time', 'active', '2026-03-14 18:31:38', '2026-03-14 18:31:38', 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Auto-created users account',
  `staff_id` varchar(20) NOT NULL COMMENT 'e.g. STF-0002',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,' ',ifnull(concat(`middle_name`,' '),''),`last_name`)) STORED,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `position` enum('OTPR','RSLP','LPT','RSW') NOT NULL COMMENT 'OTPR=Occupational Therapist, RSLP=Speech Language Pathologist, LPT=Licensed Professional Teacher, RSW=Registered Social Worker',
  `department` enum('Education Department','Therapy Department') NOT NULL,
  `role` enum('Human Resources','Educator') NOT NULL DEFAULT 'Educator',
  `status` enum('Active','Inactive','On Leave','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `staff_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `contact_number`, `email`, `address`, `position`, `department`, `role`, `status`, `created_at`, `updated_at`) VALUES
(2, 10, 'STF-0002', 'James Ivan', 'Labrador', 'Tayabas', '2014-06-12', 'Other', '+63098765432', 'tayabasjamesivan@gmail.com', 'taguig', 'LPT', 'Education Department', 'Educator', 'Active', '2026-03-11 12:39:14', '2026-03-11 12:39:14'),
(5, 13, 'STF-0004', 'Alvinne Joy', 'Masaga', 'Rosales', '1997-07-11', 'Female', '+449983077832', 'rosalesalvinnejoy@gmail.com', 'T.evangilista street', 'RSW', 'Education Department', 'Educator', 'Active', '2026-03-12 18:16:31', '2026-03-12 18:16:31'),
(6, 11, 'STF-0003', 'Junelle Eljon', 'Lerit', 'Ursua', '0000-00-00', 'Male', '09998801374', 'ursuajunelleeljon@gmail.com', 'Paraiso', 'LPT', 'Education Department', 'Educator', 'Active', '2026-03-12 18:29:38', '2026-03-12 18:30:52'),
(7, 14, 'STF-0005', 'Bastian', '', 'Gelua', '1994-06-17', 'Male', '099979797878', 'bastian@gmail.com', 'hulo', 'RSLP', 'Therapy Department', 'Educator', 'Active', '2026-03-12 18:33:20', '2026-03-12 18:33:20'),
(8, 15, 'STF-0006', 'devina', '', 'moral', '1984-06-16', '', '0987664673123', 'devina@gmail.com', 'addition hills', 'RSLP', 'Therapy Department', 'Educator', 'Active', '2026-03-12 18:34:01', '2026-03-12 18:34:02'),
(9, 16, 'STF-0007', 'ivan', '', 'cuizon', '2007-02-13', '', '0978912354', 'cuizon@gmail.com', 'maysilo', 'LPT', 'Education Department', 'Educator', 'Active', '2026-03-12 18:34:47', '2026-03-12 18:34:47');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL COMMENT 'e.g. STD-0001',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (concat(`first_name`,' ',ifnull(concat(`middle_name`,' '),''),`last_name`,ifnull(concat(' ',`suffix`),''))) STORED,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Pending','Inactive') DEFAULT 'Pending' COMMENT 'Pending=awaiting payment, Active=paid/enrolled, Inactive=no longer availing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `date_of_birth`, `gender`, `address`, `status`, `created_at`, `updated_at`, `archived`) VALUES
(1, 'STD-0001', 'Alvinne Joy', 'Masaga', 'Rosales', '', '2024-07-11', 'Female', 'T.evangilista street', 'Pending', '2026-03-11 12:23:08', '2026-03-14 11:43:04', 0),
(2, 'STD-0002', 'Liam Gabriel', 'Ramos', 'Santos', 'Jr.', '2022-02-03', 'Male', '', 'Active', '2026-03-13 09:30:42', '2026-03-14 11:59:01', 0),
(3, 'STD-0003', 'Ariana Mae', 'Lopez', 'Rodriguez', '', '2023-02-13', 'Female', '', 'Pending', '2026-03-13 09:31:32', '2026-03-13 09:31:32', 0),
(4, 'STD-0004', 'Joshua Daniel', 'Perez', 'Cruz', '', '2022-10-15', 'Male', '', 'Pending', '2026-03-13 09:32:21', '2026-03-13 09:32:21', 0),
(5, 'STD-0005', 'Roberto', 'Aquino', 'Reyes', '', '2021-06-19', 'Male', '', 'Pending', '2026-03-13 09:33:17', '2026-03-13 09:33:17', 0),
(6, 'STD-0006', ' Patricia ', 'Castro ', 'Flores', '', '2019-12-25', 'Female', '', 'Pending', '2026-03-13 09:34:22', '2026-03-13 23:07:08', 0),
(7, 'STD-0007', 'Fernando', 'Torres ', 'Mendoza', '', '2020-07-11', 'Male', '', 'Pending', '2026-03-13 09:35:07', '2026-03-13 23:07:24', 0),
(8, 'STD-0008', 'Alexis Joy', 'Masaga', 'Rosales', '', '2022-01-15', 'Female', 'T.evangilista street', 'Pending', '2026-03-14 18:59:53', '2026-03-14 18:59:53', 0),
(9, 'STD-0009', 'Aldwine Jed', 'Masaga', 'Rosales', 'Jr.', '2021-06-10', 'Male', 'Mandaluyong', 'Pending', '2026-03-15 12:39:06', '2026-03-15 12:39:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role_type` enum('Educators','Professionals','Leaders') NOT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `name`, `role_type`, `bio`, `photo`, `created_at`) VALUES
(1, 'Bastian Gelua', 'Educators', 'Dedicated educator with 10 years of experience in special education.', NULL, '2026-03-10 07:49:05'),
(2, 'Junelle Ursua', 'Educators', 'Passionate about creating inclusive learning environments.', NULL, '2026-03-10 07:49:05'),
(3, 'Joshua Macaraeg', 'Educators', 'Specialist in behavioral therapy and child development.', NULL, '2026-03-10 07:49:05');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED,
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `student_id`, `enrollment_id`, `total_amount`, `amount_paid`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1800.00, 0.00, 'unpaid', NULL, '2026-03-11 12:23:08', '2026-03-11 12:23:08'),
(2, 2, 2, 4300.00, 0.00, 'unpaid', NULL, '2026-03-13 09:30:42', '2026-03-13 09:30:42'),
(3, 3, 3, 1800.00, 0.00, 'unpaid', NULL, '2026-03-13 09:31:32', '2026-03-13 09:31:32'),
(4, 4, 4, 2500.00, 0.00, 'unpaid', NULL, '2026-03-13 09:32:21', '2026-03-13 09:32:21'),
(5, 5, 5, 2500.00, 0.00, 'unpaid', NULL, '2026-03-13 09:33:17', '2026-03-13 09:33:17'),
(6, 6, 6, 2500.00, 0.00, 'unpaid', NULL, '2026-03-13 09:34:22', '2026-03-13 09:34:23'),
(7, 7, 7, 2500.00, 0.00, 'unpaid', NULL, '2026-03-13 09:35:07', '2026-03-13 09:35:08'),
(8, 8, 8, 8000.00, 0.00, 'unpaid', NULL, '2026-03-14 18:59:53', '2026-03-14 18:59:53'),
(9, 9, 9, 6000.00, 0.00, 'unpaid', NULL, '2026-03-15 12:39:06', '2026-03-15 12:39:06');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_services`
--

CREATE TABLE `transaction_services` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_services`
--

INSERT INTO `transaction_services` (`id`, `transaction_id`, `service_id`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 2, 1800.00, 1, 1800.00),
(2, 2, 1, 2500.00, 1, 2500.00),
(3, 2, 2, 1800.00, 1, 1800.00),
(4, 3, 2, 1800.00, 1, 1800.00),
(5, 4, 1, 2500.00, 1, 2500.00),
(6, 5, 1, 2500.00, 1, 2500.00),
(7, 6, 1, 2500.00, 1, 2500.00),
(8, 7, 1, 2500.00, 1, 2500.00),
(9, 8, 9, 6000.00, 1, 6000.00),
(10, 8, 11, 2000.00, 1, 2000.00),
(11, 9, 9, 6000.00, 1, 6000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) DEFAULT NULL COMMENT 'e.g. STF-0001, auto-assigned when created',
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Human Resources','Educator') NOT NULL,
  `status` enum('active','inactive','suspended','pending','denied') DEFAULT 'active',
  `is_first_login` tinyint(1) DEFAULT 1 COMMENT '1 = must change password on first login (Educators)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `staff_id`, `full_name`, `username`, `email`, `password`, `role`, `status`, `is_first_login`, `created_at`, `updated_at`) VALUES
(1, 'STF-0000', 'Administrator', 'admin', 'admin@kidsjourney.com', '$2y$10$qnPb2lOmRpfilaWNafjLH.YZIT24UYPGgNbv2nZyzAaHlsrVGNKLq', 'Admin', 'active', 0, '2026-03-10 07:49:02', '2026-03-12 16:00:21'),
(2, 'STF-0001', 'HR Officer', 'hrofficer', 'hr@kidsjourney.com', '$2y$10$QG.7SxkUdkIJDklDmxEs9eAuFoJuLXaqMZKcXKHM7RV0rOlXnCwfC', 'Human Resources', 'active', 0, '2026-03-10 07:49:02', '2026-03-12 18:50:35'),
(10, 'STF-0002', 'James Ivan Tayabas', 'EduJames', 'tayabasjamesivan@gmail.com', '$2y$10$GCPs6ROkN1oPlC5IvTigFerot7/7c/nW4wbKHTVwOVTnil03aJu8W', 'Educator', 'active', 1, '2026-03-11 12:39:14', '2026-03-15 13:52:48'),
(11, 'STF-0003', 'Junelle Eljon Ursua', 'junelle eljon7832', 'ursuajunelleeljon@gmail.com', '$2y$10$17bBDmfYQJLlx/gNldlK6e7UCj.ZMbT8ynM1LT0mE2BihwoUzT48O', 'Educator', 'suspended', 1, '2026-03-12 13:19:37', '2026-03-15 13:43:46'),
(13, 'STF-0004', 'Alvinne Joy Rosales', 'alvinne joy7832', 'rosalesalvinnejoy@gmail.com', '$2y$10$kzyklmbBx2llWy3bnfr3A..pVnHdZYXQaYbNM..JRKYKe/vmXNl0W', 'Educator', 'pending', 1, '2026-03-12 18:16:31', '2026-03-12 18:43:03'),
(14, 'STF-0005', 'Bastian Gelua', 'bastian7878', 'bastian@gmail.com', '$2y$10$ZopNNX.m2GcejwRKriWJouEuMFj1m4zE4IKoJUVLqNV7Wf45bP/7y', 'Educator', 'pending', 1, '2026-03-12 18:33:20', '2026-03-12 18:42:42'),
(15, 'STF-0006', 'devina moral', 'devina3123', 'devina@gmail.com', '$2y$10$88BfI0apXxRX7AEhIGIh9ugLFZllbAHdWznSLy1TeqDEO/6jv3WsW', 'Educator', 'suspended', 1, '2026-03-12 18:34:02', '2026-03-14 17:55:25'),
(16, 'STF-0007', 'ivan cuizon', 'ivan2354', 'cuizon@gmail.com', '$2y$10$4Of1Ixtj6QxAQtoFubbrReFnpRkEC4nLhv9L9uczPsVO1vNAqx91e', 'Educator', 'suspended', 1, '2026-03-12 18:34:47', '2026-03-15 13:29:11'),
(17, 'STF-0008', 'joshua macaraeg', 'joshua9213', 'macaraeg@gmail.com', '$2y$10$SlFBB.hzvwKIHgBCXjEf5eMR0kfmFIxueOEPkNQSlGiK2R3UQRPu6', 'Educator', 'denied', 1, '2026-03-12 18:35:49', '2026-03-14 17:48:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `contact_submissions`
--
ALTER TABLE `contact_submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `enrolled_by` (`enrolled_by`);

--
-- Indexes for table `guardians`
--
ALTER TABLE `guardians`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `payment_notifications`
--
ALTER TABLE `payment_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `transaction_services`
--
ALTER TABLE `transaction_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_submissions`
--
ALTER TABLE `contact_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payment_notifications`
--
ALTER TABLE `payment_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transaction_services`
--
ALTER TABLE `transaction_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`),
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `guardians`
--
ALTER TABLE `guardians`
  ADD CONSTRAINT `guardians_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `prt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`);

--
-- Constraints for table `transaction_services`
--
ALTER TABLE `transaction_services`
  ADD CONSTRAINT `ts_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ts_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

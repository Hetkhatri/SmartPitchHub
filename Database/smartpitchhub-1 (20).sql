-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 12, 2026 at 05:44 AM
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
-- Database: `smartpitchhub-1`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `name`, `email`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@smartpitchhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-09-14 07:59:05', '2025-09-14 07:59:05'),
(2, 'Het', 'hetkhatri22@gmail.com', '$2y$10$Ay/7T9dMOlI5KReYZcktJuCLPt9dSqXMVTFpvpElvYeeKI6hbK4Tm', 'active', '2025-09-14 07:59:05', '2025-09-14 08:00:53'),
(5, 'Het', 'het@smartpitchhub.com', '$2y$10$Ay/7T9dMOlI5KReYZcktJuCLPt9dSqXMVTFpvpElvYeeKI6hbK4Tm', 'active', '2025-09-14 08:06:37', '2025-09-14 08:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `bid_packs`
--

CREATE TABLE `bid_packs` (
  `id` int(11) NOT NULL,
  `pack_name` varchar(100) NOT NULL,
  `bid_count` int(11) NOT NULL,
  `bonus_bids` int(11) DEFAULT 0,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bid_packs`
--

INSERT INTO `bid_packs` (`id`, `pack_name`, `bid_count`, `bonus_bids`, `price`, `status`, `created_at`) VALUES
(1, 'Starter Pack', 10, 2, 199.00, 'active', '2025-12-18 05:01:38'),
(2, 'Pro Pack', 25, 5, 499.00, 'active', '2025-12-18 05:01:38'),
(3, 'Premium Pack', 60, 15, 999.00, 'active', '2025-12-18 05:01:38');

-- --------------------------------------------------------

--
-- Table structure for table `bid_purchase_orders`
--

CREATE TABLE `bid_purchase_orders` (
  `id` int(11) NOT NULL,
  `investor_id` int(11) NOT NULL,
  `pack_id` int(11) NOT NULL,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('created','paid','failed') DEFAULT 'created',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entrepreneurs`
--

CREATE TABLE `entrepreneurs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','suspended','inactive') DEFAULT 'active',
  `kyc_status` enum('verified','pending','rejected') DEFAULT 'pending',
  `total_pitches` int(11) DEFAULT 0,
  `startup_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_shares` int(11) DEFAULT 50000,
  `available_shares` int(11) DEFAULT 50000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entrepreneurs`
--

INSERT INTO `entrepreneurs` (`id`, `name`, `email`, `contact`, `password`, `status`, `kyc_status`, `total_pitches`, `startup_name`, `created_at`, `total_shares`, `available_shares`) VALUES
(1, 'Entrepreneur One', 'ent1@example.com', '8000000001', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'verified', 0, 'Startup One', '2026-02-09 16:31:37', 50000, 45000),
(2, 'Entrepreneur Two', 'ent2@example.com', '8000000002', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'rejected', 0, 'Startup Two', '2026-02-09 16:31:37', 50000, 50000),
(3, 'Entrepreneur Three', 'ent3@example.com', '8000000003', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'verified', 0, 'Startup Three', '2026-02-09 16:31:37', 50000, 50000),
(4, 'Entrepreneur Four', 'ent4@example.com', '8000000004', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'pending', 0, 'Startup Four', '2026-02-09 16:31:37', 50000, 50000);

-- --------------------------------------------------------

--
-- Table structure for table `entrepreneur_kyc_details`
--

CREATE TABLE `entrepreneur_kyc_details` (
  `id` int(11) NOT NULL,
  `entrepreneur_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `dob` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `residential_address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `gov_id_path` varchar(255) DEFAULT NULL,
  `selfie_path` varchar(255) DEFAULT NULL,
  `legal_name` varchar(150) DEFAULT NULL,
  `brand_name` varchar(150) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `industry` varchar(50) DEFAULT NULL,
  `incorporation_date` varchar(50) DEFAULT NULL,
  `startup_stage` varchar(50) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `coi_path` varchar(255) DEFAULT NULL,
  `gst_certificate_path` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `ownership_percentage` decimal(5,2) DEFAULT NULL,
  `is_ubo` varchar(10) DEFAULT NULL,
  `has_other_controllers` varchar(10) DEFAULT NULL,
  `is_pep` varchar(10) DEFAULT NULL,
  `account_holder_name` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT NULL,
  `bank_proof_path` varchar(255) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `tax_residency` varchar(50) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `cin` varchar(50) DEFAULT NULL,
  `agree_accuracy` tinyint(1) DEFAULT NULL,
  `agree_eligibility` tinyint(1) DEFAULT NULL,
  `agree_commission` tinyint(1) DEFAULT NULL,
  `agree_refund` tinyint(1) DEFAULT NULL,
  `agree_background_check` tinyint(1) DEFAULT NULL,
  `submission_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entrepreneur_kyc_details`
--

INSERT INTO `entrepreneur_kyc_details` (`id`, `entrepreneur_id`, `full_name`, `dob`, `nationality`, `residential_address`, `city`, `state`, `country`, `postal_code`, `gov_id_path`, `selfie_path`, `legal_name`, `brand_name`, `business_type`, `industry`, `incorporation_date`, `startup_stage`, `business_address`, `coi_path`, `gst_certificate_path`, `role`, `ownership_percentage`, `is_ubo`, `has_other_controllers`, `is_pep`, `account_holder_name`, `bank_name`, `account_number`, `ifsc_code`, `account_type`, `bank_proof_path`, `pan_number`, `tax_residency`, `gst_number`, `cin`, `agree_accuracy`, `agree_eligibility`, `agree_commission`, `agree_refund`, `agree_background_check`, `submission_date`, `status`, `rejection_reason`) VALUES
(1, 1, 'Entrepreneur One', '1990-01-01', 'Indian', '123 Startup Street', 'Bangalore', 'Karnataka', 'India', '560001', NULL, NULL, 'Startup One', 'Startup One', 'pvt_ltd', 'Technology', '2023-01-01', 'MVP', '456 Business Park', NULL, NULL, 'Founder', 75.50, NULL, NULL, NULL, 'Entrepreneur One', 'HDFC', '1234567890', 'HDFC001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 22:01:37', 'approved', ''),
(2, 2, 'Entrepreneur Two', '1990-01-01', 'Indian', '123 Startup Street', 'Bangalore', 'Karnataka', 'India', '560001', NULL, NULL, 'Startup Two', 'Startup Two', 'pvt_ltd', 'Technology', '2023-01-01', 'MVP', '456 Business Park', NULL, NULL, 'Founder', 75.50, NULL, NULL, NULL, 'Entrepreneur Two', 'HDFC', '1234567890', 'HDFC001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 22:01:37', 'rejected', 'Please specify the documents '),
(3, 3, 'Entrepreneur Three', '1990-01-01', 'Indian', '123 Startup Street', 'Bangalore', 'Karnataka', 'India', '560001', NULL, NULL, 'Startup Three', 'Startup Three', 'pvt_ltd', 'Technology', '2023-01-01', 'MVP', '456 Business Park', NULL, NULL, 'Founder', 75.50, NULL, NULL, NULL, 'Entrepreneur Three', 'HDFC', '1234567890', 'HDFC001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 22:01:37', 'approved', ''),
(4, 4, 'Entrepreneur Four', '1990-01-01', 'Indian', '123 Startup Street', 'Bangalore', 'Karnataka', 'India', '560001', NULL, NULL, 'Startup Four', 'Startup Four', 'pvt_ltd', 'Technology', '2023-01-01', 'MVP', '456 Business Park', NULL, NULL, 'Founder', 75.50, NULL, NULL, NULL, 'Entrepreneur Four', 'HDFC', '1234567890', 'HDFC001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 22:01:37', 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL,
  `investor_id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `shares_bought` int(11) DEFAULT 0,
  `purchase_price` decimal(15,2) DEFAULT 0.00,
  `investment_type` enum('primary','secondary') DEFAULT 'primary',
  `payout_status` enum('escrow','released','refunded') DEFAULT 'escrow',
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `transaction_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investors`
--

CREATE TABLE `investors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','suspended','inactive') DEFAULT 'active',
  `kyc_status` enum('verified','pending','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investors`
--

INSERT INTO `investors` (`id`, `name`, `email`, `contact`, `password`, `status`, `kyc_status`, `created_at`) VALUES
(1, 'Investor One', 'investor1@example.com', '9000000001', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'pending', '2026-02-09 16:31:37'),
(2, 'Investor Two', 'investor2@example.com', '9000000002', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'pending', '2026-02-09 16:31:37'),
(3, 'Investor Three', 'investor3@example.com', '9000000003', '$2y$10$JBfvVD0W9fMz9mYuWvddCejOPvw4CGBcxniPDm86y6P9iwVf/YRsK', 'active', 'pending', '2026-02-09 16:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `investor_bids`
--

CREATE TABLE `investor_bids` (
  `id` int(11) NOT NULL,
  `investor_id` int(11) NOT NULL,
  `total_bids` int(11) DEFAULT 0,
  `used_bids` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investor_kyc_details`
--

CREATE TABLE `investor_kyc_details` (
  `id` int(11) NOT NULL,
  `investor_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `dob` date NOT NULL,
  `nationality` varchar(50) NOT NULL,
  `country_residence` varchar(50) NOT NULL,
  `doc_type` enum('pan','aadhaar','passport') NOT NULL,
  `doc_number` varchar(50) NOT NULL,
  `issuing_country` varchar(50) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `identity_proof_path` varchar(255) DEFAULT NULL,
  `selfie_path` varchar(255) DEFAULT NULL,
  `address_line` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `pincode` varchar(20) NOT NULL,
  `address_proof_path` varchar(255) DEFAULT NULL,
  `account_holder` varchar(100) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `account_type` enum('savings','current') NOT NULL,
  `bank_proof_path` varchar(255) DEFAULT NULL,
  `investor_type` varchar(50) NOT NULL,
  `annual_income` varchar(50) NOT NULL,
  `net_worth` varchar(50) NOT NULL,
  `experience` varchar(50) NOT NULL,
  `typical_amount` varchar(50) NOT NULL,
  `investment_reason` text DEFAULT NULL,
  `pan_number` varchar(20) NOT NULL,
  `tax_residency` varchar(50) NOT NULL,
  `fatca_status` enum('yes','no') DEFAULT 'no',
  `pep_status` enum('yes','no') DEFAULT 'no',
  `status` enum('pending','under_review','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submission_date` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ai_doc_score` decimal(5,2) DEFAULT 0.00,
  `ai_doc_quality` varchar(20) DEFAULT 'Average',
  `ai_fraud_risk_level` varchar(20) DEFAULT 'Low',
  `ai_fraud_score` decimal(5,2) DEFAULT 0.00,
  `ai_behavior_score` decimal(5,2) DEFAULT 0.00,
  `ai_recommendation` varchar(20) DEFAULT 'Review',
  `ai_confidence` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('entrepreneur','investor') NOT NULL,
  `startup_name` varchar(255) DEFAULT NULL,
  `otp` varchar(6) NOT NULL,
  `status` enum('pending','verified') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pitches`
--

CREATE TABLE `pitches` (
  `id` int(11) NOT NULL,
  `entrepreneur_id` int(11) NOT NULL,
  `startup_name` varchar(255) NOT NULL,
  `tagline` varchar(255) NOT NULL,
  `industry` varchar(100) NOT NULL,
  `problem` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `stage` enum('Idea','MVP','Revenue') DEFAULT 'Idea',
  `description` text NOT NULL,
  `location` varchar(100) DEFAULT 'India',
  `funding_goal` decimal(15,2) NOT NULL,
  `amount_raised` decimal(15,2) DEFAULT 0.00,
  `valuation` decimal(15,2) DEFAULT NULL,
  `required_bids` int(11) DEFAULT 2,
  `min_investment` decimal(15,2) DEFAULT 10000.00,
  `pitch_logo` varchar(255) DEFAULT NULL,
  `likes` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `interested_investors` int(11) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `admin_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `round_name` varchar(50) DEFAULT 'Seed',
  `duration_days` int(11) DEFAULT 30,
  `expiry_date` datetime DEFAULT NULL,
  `round_status` enum('active','completed','expired','archived') DEFAULT 'active',
  `round_number` int(11) DEFAULT 1,
  `share_price` decimal(15,2) DEFAULT 0.00,
  `shares_issued` int(11) DEFAULT 0,
  `shares_sold` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pitches`
--

INSERT INTO `pitches` (`id`, `entrepreneur_id`, `startup_name`, `tagline`, `industry`, `problem`, `solution`, `stage`, `description`, `location`, `funding_goal`, `amount_raised`, `valuation`, `required_bids`, `min_investment`, `pitch_logo`, `likes`, `views`, `interested_investors`, `is_approved`, `admin_feedback`, `created_at`, `round_name`, `duration_days`, `expiry_date`, `round_status`, `round_number`, `share_price`, `shares_issued`, `shares_sold`) VALUES
(1, 1, 'NeuroNexus AI', 'NeuroNexus AI revolutionizes edge computing with decentralized neural networks.', 'AI/ML', NULL, NULL, 'Revenue', '<strong>Problem:</strong><br>Edge devices currently rely on heavy cloud processing which causes high latency and privacy risks for enterprise AI applications.<br><br><strong>Solution:</strong><br>NeuroNexus provides a lightweight, decentralized AI layer that processes complex models locally on edge hardware<br><br><strong>USP:</strong><br>NeuroNexus AI offers a uniquely compelling value proposition, leveraging the transformative power of artificial intelligence and machine learning to revolutionize the way businesses operate, making it exceptionally good at driving impactful outcomes, and ultimately redefining the future of industries, by providing unparalleled insights, automation, and optimization capabilities that empower companies to make informed decisions, streamline processes, and unlock new revenue streams, thereby creating a significant competitive advantage in the market.<br><br><strong>Target Market:</strong><br>NeuroNexus AI is poised to revolutionize the burgeoning AI/ML industry by capitalizing on a vast and rapidly expanding target market. With the global artificial intelligence market projected to surpass $150 billion by 2025, our innovative solutions are strategically positioned to capture a significant share of this growth. Our primary target market encompasses a broad range of industries, including healthcare, finance, and technology, where AI-powered applications can drive transformative change and unlock unprecedented value. By focusing on the development of cutting-edge, user-centric AI solutions, NeuroNexus AI is uniquely equipped to address the complex needs of forward-thinking organizations, empowering them to stay ahead of the curve in an increasingly competitive landscape. As we continue to push the boundaries of what is possible with AI, our target market is expected to continue its upward trajectory, driven by the escalating demand for intelligent, automated, and data-driven solutions that can propel businesses towards unparalleled success.<br><br><strong>Revenue:</strong><br>At NeuroNexus AI, our business model is designed to capitalize on the rapidly growing demand for AI and machine learning solutions, with a focus on generating recurring revenue through a combination of subscription-based software licenses and professional services. Our revenue streams will be driven by the adoption of our flagship product, a cutting-edge AI platform that enables enterprises to build, deploy, and manage their own AI models. With a tiered pricing structure, we will offer flexible plans to accommodate the diverse needs of our customers, from small and medium-sized businesses to large enterprises. As we continue to innovate and expand our offerings, we anticipate significant revenue growth, driven by the increasing adoption of AI and ML technologies across industries, and we are well-positioned to become a leading player in the AI/ML market, with a projected revenue trajectory that will enable us to achieve profitability and deliver strong returns on investment for our stakeholders.<br><br><strong>Competitors:</strong><br>UIPath, Zapier Enterprise, and manual BPO services.<br><br><strong>Traction:</strong><br>At NeuroNexus AI, we&#039;ve achieved remarkable traction since our inception, with a growing customer base and increasing revenue streams. Notably, our Annual Recurring Revenue has reached significant milestones, demonstrating the market&#039;s appetite for our innovative AI and ML solutions. With a user acquisition strategy that has yielded impressive results, our platform has seen a substantial surge in adoption, underscoring the value proposition we bring to the table. Key metrics such as customer retention and satisfaction rates have consistently exceeded industry benchmarks, a testament to our unwavering commitment to delivering exceptional user experiences. As we continue to expand our offerings and refine our technology, we&#039;re poised to capitalize on emerging trends and cement our position as a leader in the AI/ML landscape, making us an attractive investment opportunity for forward-thinking venture capital firms.<br><br>', 'Ahmedabad , India', 5000000.00, 0.00, NULL, 2, 10000.00, 'uploads/logos/698a23c162c6b.jpg', 0, 1, 0, 1, '', '2026-02-09 18:13:21', 'Seed Round', 60, '2026-04-10 19:13:21', 'active', 1, 1000.00, 5000, 0);

-- --------------------------------------------------------

--
-- Table structure for table `pitch_documents`
--

CREATE TABLE `pitch_documents` (
  `id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `is_locked` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pitch_documents`
--

INSERT INTO `pitch_documents` (`id`, `pitch_id`, `name`, `type`, `file_url`, `is_locked`) VALUES
(1, 1, 'Pitch Deck', 'PDF • 3.16 MB', 'uploads/documents/698a23c163951.pdf', 1),
(2, 1, 'Business Plan', 'PDF • 0.24 MB', 'uploads/documents/698a23c1646b2.pdf', 1),
(3, 1, 'Financials', 'PDF • 0.72 MB', 'uploads/documents/698a23c1649b2.pdf', 1);

-- --------------------------------------------------------

--
-- Table structure for table `pitch_funds_usage`
--

CREATE TABLE `pitch_funds_usage` (
  `id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `percentage` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pitch_funds_usage`
--

INSERT INTO `pitch_funds_usage` (`id`, `pitch_id`, `label`, `percentage`) VALUES
(1, 1, 'Product Development', 40),
(2, 1, 'Sales & Marketing', 30),
(3, 1, 'Team Expansion', 20),
(4, 1, 'Operations', 10),
(5, 10, 'R&D', 50),
(6, 10, 'Manufacturing', 30),
(7, 10, 'Marketing', 20),
(8, 11, 'Inventory', 60),
(9, 11, 'Marketing', 30),
(10, 11, 'Operations', 10),
(11, 12, 'Lending Capital', 70),
(12, 12, 'Technology', 20),
(13, 12, 'Legal', 10),
(14, 13, 'Tech Development', 50),
(15, 13, 'Sales Team', 30),
(16, 13, 'Office Space', 20),
(17, 14, 'Content Creation', 40),
(18, 14, 'App Development', 30),
(19, 14, 'User Acquisition', 30);

-- --------------------------------------------------------

--
-- Table structure for table `pitch_team`
--

CREATE TABLE `pitch_team` (
  `id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pitch_team`
--

INSERT INTO `pitch_team` (`id`, `pitch_id`, `name`, `role`, `photo_url`) VALUES
(1, 1, 'Priya Sharma', 'CTO', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop'),
(2, 1, 'Rahul Verma', 'Head of Sales', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop'),
(3, 1, 'Ananya Patel', 'Product Lead', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop'),
(4, 10, 'Ramesh Kisan', 'CEO', 'https://randomuser.me/api/portraits/men/32.jpg'),
(5, 10, 'Dr. Aditi Rao', 'Chief Agronomist', 'https://randomuser.me/api/portraits/women/44.jpg'),
(6, 11, 'Sarah Jenkins', 'Founder', 'https://randomuser.me/api/portraits/women/68.jpg'),
(7, 11, 'Mike Ross', 'Head of Supply Chain', 'https://randomuser.me/api/portraits/men/22.jpg'),
(8, 12, 'Amit Verma', 'CEO', 'https://randomuser.me/api/portraits/men/55.jpg'),
(9, 12, 'Suresh Raina', 'CTO', 'https://randomuser.me/api/portraits/men/11.jpg'),
(10, 13, 'Vikram Malhotra', 'Founder', 'https://randomuser.me/api/portraits/men/86.jpg'),
(11, 14, 'Anita Roy', 'CEO', 'https://randomuser.me/api/portraits/women/29.jpg'),
(12, 14, 'Rajesh Koothrappali', 'Content Head', 'https://randomuser.me/api/portraits/men/4.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `saved_pitches`
--

CREATE TABLE `saved_pitches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_pitches`
--

INSERT INTO `saved_pitches` (`id`, `user_id`, `pitch_id`, `saved_at`) VALUES
(1, 6, 11, '2026-01-09 06:55:51'),
(3, 6, 10, '2026-01-27 05:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `secondary_market_orders`
--

CREATE TABLE `secondary_market_orders` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `pitch_id` int(11) NOT NULL,
  `shares_quantity` int(11) NOT NULL,
  `price_per_share` decimal(15,2) NOT NULL,
  `original_investment_id` int(11) NOT NULL,
  `status` enum('available','pending','filled','cancelled') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `valuation_requests`
--

CREATE TABLE `valuation_requests` (
  `id` int(11) NOT NULL,
  `entrepreneur_id` int(11) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `industry` varchar(100) NOT NULL,
  `stage` varchar(50) NOT NULL,
  `incorporation_date` date NOT NULL,
  `ttm_revenue` decimal(15,2) DEFAULT 0.00,
  `projected_revenue` decimal(15,2) DEFAULT 0.00,
  `monthly_burn` decimal(15,2) DEFAULT 0.00,
  `active_users` int(11) DEFAULT 0,
  `growth_rate` decimal(5,2) DEFAULT 0.00,
  `market_size` varchar(50) NOT NULL,
  `team_strength` varchar(50) NOT NULL,
  `valuation_ask` decimal(15,2) NOT NULL,
  `fundraise_amount` decimal(15,2) NOT NULL,
  `previous_capital` decimal(15,2) DEFAULT 0.00,
  `valuation_basis` text DEFAULT NULL,
  `justification` text DEFAULT NULL,
  `deck_path` varchar(255) DEFAULT NULL,
  `financials_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `valuation_requests`
--

INSERT INTO `valuation_requests` (`id`, `entrepreneur_id`, `entity_name`, `industry`, `stage`, `incorporation_date`, `ttm_revenue`, `projected_revenue`, `monthly_burn`, `active_users`, `growth_rate`, `market_size`, `team_strength`, `valuation_ask`, `fundraise_amount`, `previous_capital`, `valuation_basis`, `justification`, `deck_path`, `financials_path`, `status`, `admin_remarks`, `created_at`) VALUES
(2, 15, 'smarttech', 'fintech', 'seed', '2007-12-08', 150000.00, 200000.00, 8000.00, 3000, 20.00, 'niche', 'first-time', 250000.00, 100000.00, 50000.00, 'traction-users,revenue,technology-ip', 'Yes its there edrtfyguhijoklkjhgfdsaedrtfgyhuijoklkjhgyfdsaedrtfyghuijkol', '../uploads/valuations/user_15/deck_1770359040.pdf', '../uploads/valuations/user_15/financials_1770359040.pdf', 'pending', NULL, '2026-02-06 06:24:00'),
(4, 17, 'Nexus AI Solutions PVT LTD', 'ai-ml', 'seed', '0004-12-08', 12.00, 85.00, 2.00, 1500, 45.00, 'large', 'experienced', 7.00, 75.00, 15.00, 'technology-ip', 'Our proprietary optimization engine reduces costs by 60%, providing a significant moat against traditional providers in the AI space.\r\n', '../uploads/valuations/user_17/deck_1770544254.pdf', '../uploads/valuations/user_17/financials_1770544254.pdf', 'verified', '', '2026-02-08 09:50:54'),
(5, 1, 'NeuroNexus AI', 'ai-ml', 'seed', '2024-02-12', 5.00, 25.00, 1.00, 1200, 150.00, 'medium', 'experienced', 50000000.00, 50.00, 25.00, 'traction-users,revenue,technology-ip', 'We have developed a proprietary neural engine that reduces cloud latency by 40%. Our MoR is growing at 15% monthly with a 4.5x LTV/CAC ratio.\"', '../uploads/valuations/user_1/deck_1770660749.pdf', '../uploads/valuations/user_1/financials_1770660749.pdf', 'verified', '', '2026-02-09 18:12:29');

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('investor','entrepreneur','admin') NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','blocked') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `user_role`, `balance`, `status`, `updated_at`) VALUES
(2, 6, 'investor', 5000.00, 'active', '2026-02-08 17:40:19'),
(3, 17, 'entrepreneur', 0.00, 'active', '2026-02-08 16:59:38'),
(4, 15, 'entrepreneur', 0.00, 'active', '2026-02-08 17:49:44'),
(5, 18, 'entrepreneur', 0.00, 'active', '2026-02-09 03:54:49'),
(6, 19, 'entrepreneur', 0.00, 'active', '2026-02-09 15:59:26'),
(7, 1, 'entrepreneur', 0.00, 'active', '2026-02-09 16:29:00'),
(8, 2, 'entrepreneur', 0.00, 'active', '2026-02-09 16:40:38'),
(9, 1, 'admin', 0.00, 'active', '2026-02-10 14:09:26');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('investor','entrepreneur','admin') DEFAULT NULL,
  `txn_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `status` enum('success','pending','failed') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `wallet_id`, `user_id`, `user_role`, `txn_type`, `amount`, `source`, `reference_id`, `status`, `created_at`) VALUES
(1, 2, 6, 'investor', 'credit', 10000.00, 'Razorpay Topup', 'pay_SDiR9Ug2VHFgAn', 'success', '2026-02-08 16:22:32'),
(2, 2, 6, 'investor', 'credit', 10000.00, 'Razorpay Topup', 'pay_SDiTzENEylvUAk', 'success', '2026-02-08 16:25:13'),
(3, 2, 6, 'investor', 'debit', 15000.00, 'Invested in Nexus Quantum AI', 'INV-6988CA83A50D7', 'success', '2026-02-08 17:40:19');

-- --------------------------------------------------------

--
-- Table structure for table `warzone_sessions`
--

CREATE TABLE `warzone_sessions` (
  `id` int(11) NOT NULL,
  `entrepreneur_id` int(11) NOT NULL,
  `pitch_id` int(11) DEFAULT NULL,
  `valuation_request_id` int(11) DEFAULT NULL,
  `chat_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`chat_history`)),
  `survival_score` int(11) DEFAULT 0,
  `threat_level` varchar(20) DEFAULT 'Low',
  `final_verdict` text DEFAULT NULL,
  `status` enum('ongoing','completed') DEFAULT 'ongoing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warzone_sessions`
--

INSERT INTO `warzone_sessions` (`id`, `entrepreneur_id`, `pitch_id`, `valuation_request_id`, `chat_history`, `survival_score`, `threat_level`, `final_verdict`, `status`, `created_at`) VALUES
(1, 1, 1, NULL, '[{\"role\":\"ai\",\"content\":\"System online. Subject identified. Commencing Audit. Prepare for interrogation.\"},{\"role\":\"ai\",\"content\":\"What is the primary application of NeuroNexus AI, and how does it leverage AI\\/ML to solve a specific problem or create a new market opportunity?\"},{\"role\":\"user\",\"content\":\"NeuroNexus AI is a B2B Neurological Diagnostic platform. We leverage Deep Learning to analyze MRI and EEG data 40% faster than human radiologists, specifically targeting early-onset Alzheimer\\u2019s. We are creating a new market in Predictive Geriatrics, turning reactive care into proactive prevention.\"},{\"role\":\"ai\",\"content\":\"How do you plan to address the regulatory hurdles and ensure HIPAA compliance in the handling and analysis of sensitive patient data, particularly in the context of a scalable B2B platform?\"},{\"role\":\"user\",\"content\":\"NeuroNexus AI implements a \'Privacy-by-Design\' framework. We utilize automated At-Edge De-identification protocols to strip all PHI\\/PII before data reaches our primary AI models. For necessary B2B scaling, our infrastructure is hosted on HIPAA-compliant cloud partitions (with signed BAAs). We use AES-256 encryption at rest and TLS 1.3 in transit, governed by strict Role-Based Access Control (RBAC) and immutable audit logs. We are also on track for SOC 2 Type II certification to ensure our enterprise partners have total regulatory assurance\"},{\"role\":\"ai\",\"content\":\"What is your go-to-market strategy, and how do you plan to acquire your first 100 paying customers, considering the high barriers to entry in the healthcare industry and the need to educate potential clients about the benefits of your predictive geriatrics platform?\"},{\"role\":\"user\",\"content\":\"Our GTM strategy targets \'Innovation Hub\' Private Senior Living Networks and Boutique Geriatric Clinics as early adopters to bypass the slower public hospital bureaucracy. We acquire our first 100 customers through a \'Pilot-to-Paid\' model, offering a 60-day efficacy study where we prove a reduction in hospital readmission rates. We are leveraging Strategic Partnerships with medical device distributors who already have trust with our target clients. By focusing on a quantifiable ROI\\u2014showing that predictive prevention is cheaper than reactive care\\u2014we transform NeuroNexus from a \'novelty\' into a \'fiscal necessity\'.\"},{\"role\":\"ai\",\"content\":\"NeuroNexus AI has demonstrated a robust understanding of the market, regulatory requirements, and a viable go-to-market strategy. The \'Pilot-to-Paid\' model and focus on quantifiable ROI are particularly compelling. Funding of \\u20b95,000,000 is approved.\"}]', 92, 'Low', NULL, 'completed', '2026-02-09 18:28:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bid_packs`
--
ALTER TABLE `bid_packs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bid_purchase_orders`
--
ALTER TABLE `bid_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `investor_id` (`investor_id`),
  ADD KEY `pack_id` (`pack_id`),
  ADD KEY `razorpay_order_id` (`razorpay_order_id`);

--
-- Indexes for table `entrepreneurs`
--
ALTER TABLE `entrepreneurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `entrepreneur_kyc_details`
--
ALTER TABLE `entrepreneur_kyc_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entrepreneur_id` (`entrepreneur_id`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `investors`
--
ALTER TABLE `investors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `investor_bids`
--
ALTER TABLE `investor_bids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `investor_id` (`investor_id`);

--
-- Indexes for table `investor_kyc_details`
--
ALTER TABLE `investor_kyc_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `investor_id` (`investor_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pitches`
--
ALTER TABLE `pitches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entrepreneur_id` (`entrepreneur_id`);

--
-- Indexes for table `pitch_documents`
--
ALTER TABLE `pitch_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pitch_id` (`pitch_id`);

--
-- Indexes for table `pitch_funds_usage`
--
ALTER TABLE `pitch_funds_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pitch_id` (`pitch_id`);

--
-- Indexes for table `pitch_team`
--
ALTER TABLE `pitch_team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pitch_id` (`pitch_id`);

--
-- Indexes for table `saved_pitches`
--
ALTER TABLE `saved_pitches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`pitch_id`);

--
-- Indexes for table `secondary_market_orders`
--
ALTER TABLE `secondary_market_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `status` (`status`),
  ADD KEY `pitch_id` (`pitch_id`);

--
-- Indexes for table `valuation_requests`
--
ALTER TABLE `valuation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entrepreneur_id` (`entrepreneur_id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`user_role`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `warzone_sessions`
--
ALTER TABLE `warzone_sessions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bid_packs`
--
ALTER TABLE `bid_packs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bid_purchase_orders`
--
ALTER TABLE `bid_purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entrepreneurs`
--
ALTER TABLE `entrepreneurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `entrepreneur_kyc_details`
--
ALTER TABLE `entrepreneur_kyc_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `investors`
--
ALTER TABLE `investors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `investor_bids`
--
ALTER TABLE `investor_bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `investor_kyc_details`
--
ALTER TABLE `investor_kyc_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pitches`
--
ALTER TABLE `pitches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pitch_documents`
--
ALTER TABLE `pitch_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pitch_funds_usage`
--
ALTER TABLE `pitch_funds_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `pitch_team`
--
ALTER TABLE `pitch_team`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `saved_pitches`
--
ALTER TABLE `saved_pitches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `secondary_market_orders`
--
ALTER TABLE `secondary_market_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `valuation_requests`
--
ALTER TABLE `valuation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `warzone_sessions`
--
ALTER TABLE `warzone_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `entrepreneur_kyc_details`
--
ALTER TABLE `entrepreneur_kyc_details`
  ADD CONSTRAINT `entrepreneur_kyc_details_ibfk_1` FOREIGN KEY (`entrepreneur_id`) REFERENCES `entrepreneurs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `investor_kyc_details`
--
ALTER TABLE `investor_kyc_details`
  ADD CONSTRAINT `fk_investor_kyc` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pitches`
--
ALTER TABLE `pitches`
  ADD CONSTRAINT `pitches_ibfk_1` FOREIGN KEY (`entrepreneur_id`) REFERENCES `entrepreneurs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pitch_documents`
--
ALTER TABLE `pitch_documents`
  ADD CONSTRAINT `pitch_documents_ibfk_1` FOREIGN KEY (`pitch_id`) REFERENCES `pitches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pitch_funds_usage`
--
ALTER TABLE `pitch_funds_usage`
  ADD CONSTRAINT `pitch_funds_usage_ibfk_1` FOREIGN KEY (`pitch_id`) REFERENCES `pitches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pitch_team`
--
ALTER TABLE `pitch_team`
  ADD CONSTRAINT `pitch_team_ibfk_1` FOREIGN KEY (`pitch_id`) REFERENCES `pitches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `secondary_market_orders`
--
ALTER TABLE `secondary_market_orders`
  ADD CONSTRAINT `secondary_market_orders_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `investors` (`id`),
  ADD CONSTRAINT `secondary_market_orders_ibfk_2` FOREIGN KEY (`pitch_id`) REFERENCES `pitches` (`id`);

--
-- Constraints for table `valuation_requests`
--
ALTER TABLE `valuation_requests`
  ADD CONSTRAINT `valuation_requests_ibfk_1` FOREIGN KEY (`entrepreneur_id`) REFERENCES `entrepreneurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

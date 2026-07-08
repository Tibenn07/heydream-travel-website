-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 03:15 AM
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
-- Database: `heydream_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$dM.IWNNDoZsk0EnnKyeWqeBE.WUiLQ2nL6VLq7fSpkU7U4hjom/UC', 'rebancossteven35@gmail.com', 'Super Admin', 'super_admin', '2026-06-25 05:48:18', '2026-06-16 03:12:04');

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` int(11) NOT NULL,
  `type` enum('package') NOT NULL DEFAULT 'package',
  `partner_id` varchar(50) NOT NULL,
  `partner_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`id`, `type`, `partner_id`, `partner_name`, `title`, `description`, `content`, `status`, `submitted_at`, `reviewed_at`, `rejection_reason`, `metadata`, `created_at`) VALUES
(1, 'package', 'partner_1', 'HeyDream Travel Agency', 'Wow package', 'White sand beaches, crystal clear waters, and stunning sunsets', '{\"title\":\"Wow package\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"₱12,000\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:///data/user/0/host.exp.exponent/cache/ImagePicker/d8b6b499-7a5e-48a3-80f3-14024e15f404.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"12000\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"vehicleInfo\":{},\"rentalInfo\":{},\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":{},\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Wow package \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning • Afternoon • Evening\"}', 'approved', '2026-06-22 14:53:12', '2026-06-22 15:43:07', NULL, '{\"title\":\"Wow package\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"\\u20b112,000\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:\\/\\/\\/data\\/user\\/0\\/host.exp.exponent\\/cache\\/ImagePicker\\/d8b6b499-7a5e-48a3-80f3-14024e15f404.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"12000\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"vehicleInfo\":[],\"rentalInfo\":[],\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":[],\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Wow package \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning \\u2022 Afternoon \\u2022 Evening\"}', '2026-06-22 06:53:12'),
(2, 'package', 'partner_1', 'HeyDream Travel Agency', 'Hayyaya', 'White sand beaches, crystal clear waters, and stunning sunsets', '{\"title\":\"Hayyaya\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"₱333\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:///data/user/0/host.exp.exponent/cache/ImagePicker/e632d943-5034-4244-aaaf-d860d63f1e92.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"333\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Foreign\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"vehicleInfo\":{},\"rentalInfo\":{},\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":{},\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Hayyaya\",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning • Afternoon • Evening\"}', 'approved', '2026-06-22 15:27:45', '2026-06-22 16:34:26', NULL, '{\"title\":\"Hayyaya\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"\\u20b1333\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:\\/\\/\\/data\\/user\\/0\\/host.exp.exponent\\/cache\\/ImagePicker\\/e632d943-5034-4244-aaaf-d860d63f1e92.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"333\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Foreign\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"vehicleInfo\":[],\"rentalInfo\":[],\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":[],\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Hayyaya\",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning \\u2022 Afternoon \\u2022 Evening\"}', '2026-06-22 07:27:45'),
(4, 'package', 'partner_1', 'HeyDream Travel Agency', 'Kkk', 'White sand beaches, crystal clear waters, and stunning sunsets', '{\"title\":\"Kkk\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"₱222\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:///data/user/0/host.exp.exponent/cache/ImagePicker/46d47b7d-2db5-49bd-960c-7eb36eb3ee09.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"222\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":{},\"rentalInfo\":{},\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":{},\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Testing\",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning • Afternoon • Evening\"}', 'approved', '2026-06-22 16:46:20', '2026-06-22 16:47:00', NULL, '{\"title\":\"Kkk\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"\\u20b1222\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:\\/\\/\\/data\\/user\\/0\\/host.exp.exponent\\/cache\\/ImagePicker\\/46d47b7d-2db5-49bd-960c-7eb36eb3ee09.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"222\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":[],\"rentalInfo\":[],\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":[],\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Testing\",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning \\u2022 Afternoon \\u2022 Evening\"}', '2026-06-22 08:46:20'),
(5, 'package', 'partner_1', 'HeyDream Travel Agency', 'Heydream packages', 'White sand beaches, crystal clear waters, and stunning sunsets', '{\"title\":\"Heydream packages\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"₱99,999\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:///data/user/0/host.exp.exponent/cache/ImagePicker/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"99999\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":{},\"rentalInfo\":{},\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":{},\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Heydream packages \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning • Afternoon • Evening\"}', 'approved', '2026-06-22 16:54:52', '2026-06-24 10:12:45', NULL, '{\"title\":\"Heydream packages\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"\\u20b199,999\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:\\/\\/\\/data\\/user\\/0\\/host.exp.exponent\\/cache\\/ImagePicker\\/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"99999\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":[],\"rentalInfo\":[],\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":[],\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Heydream packages \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning \\u2022 Afternoon \\u2022 Evening\"}', '2026-06-22 08:54:52'),
(6, 'package', 'partner_1', 'HeyDream Travel Agency', 'Heydream packages', 'White sand beaches, crystal clear waters, and stunning sunsets', '{\"title\":\"Heydream packages\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"₱99,999\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:///data/user/0/host.exp.exponent/cache/ImagePicker/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"99999\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":{},\"rentalInfo\":{},\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":{},\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Heydream packages \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning • Afternoon • Evening\"}', 'approved', '2026-06-22 17:08:43', '2026-06-23 14:34:17', NULL, '{\"title\":\"Heydream packages\",\"partnerId\":\"partner_1\",\"partnerName\":\"HeyDream Travel Agency\",\"partnerLogo\":\"\",\"destination\":\"Boracay, Aklan\",\"address\":\"Station 1, Boracay Island, Malay, Aklan, Philippines\",\"duration\":\"\",\"price\":\"\\u20b199,999\",\"availableSlots\":25,\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"],\"image\":\"file:\\/\\/\\/data\\/user\\/0\\/host.exp.exponent\\/cache\\/ImagePicker\\/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg\",\"gallery\":[],\"flightDetails\":\"White sand beaches, crystal clear waters, and stunning sunsets\",\"itinerary\":[],\"pricingTiers\":[{\"type\":\"Adult\",\"price\":\"99999\"}],\"latitude\":11.9674,\"longitude\":121.9247,\"packageType\":\"Package\",\"destinationType\":\"Local\",\"featured\":false,\"category\":\"Package\",\"categories\":[\"Package\"],\"approved\":false,\"pendingApproval\":false,\"vehicleInfo\":[],\"rentalInfo\":[],\"vehicleRequirements\":[],\"serviceInfo\":{\"coverageDetails\":[],\"policyInfo\":[],\"visaTypes\":[],\"serviceIncludes\":[],\"serviceExcludes\":[]},\"cruiseInfo\":{\"cruiseName\":\"Heydream packages \",\"itinerary\":[],\"inclusions\":[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"],\"exclusions\":[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]},\"showInclusions\":true,\"showExclusions\":true,\"showItinerary\":true,\"showSchedule\":true,\"scheduleText\":\"Morning \\u2022 Afternoon \\u2022 Evening\"}', '2026-06-22 09:08:43');

-- --------------------------------------------------------

--
-- Table structure for table `customer_reports`
--

CREATE TABLE `customer_reports` (
  `id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL DEFAULT 'general',
  `category` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `reported_by` varchar(100) NOT NULL,
  `reported_email` varchar(255) NOT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('open','in_review','resolved') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `partner_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `screenshot_path` varchar(500) DEFAULT NULL,
  `reported_by_email` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_reports`
--

INSERT INTO `customer_reports` (`id`, `report_type`, `category`, `title`, `description`, `reported_by`, `reported_email`, `priority`, `status`, `created_at`, `resolved_at`, `partner_name`, `subject`, `screenshot_path`, `reported_by_email`, `updated_at`) VALUES
(20, 'account_problem', '', '', 'Did u hack my account?', 'User', '', 'Medium', 'resolved', '2026-06-25 06:27:19', NULL, '', 'Hack account', 'report_20260625_082719_5681.jpeg', 'user@example.com', '2026-06-25 07:16:41');

-- --------------------------------------------------------

--
-- Table structure for table `customer_report_comments`
--

CREATE TABLE `customer_report_comments` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_report_comments`
--

INSERT INTO `customer_report_comments` (`id`, `report_id`, `admin_id`, `admin_name`, `comment`, `created_at`) VALUES
(1, 20, 1, 'Super Admin', 'what happen?', '2026-06-25 07:16:05');

-- --------------------------------------------------------

--
-- Table structure for table `flight_packages`
--

CREATE TABLE `flight_packages` (
  `id` varchar(50) NOT NULL,
  `partner_id` varchar(50) NOT NULL,
  `partner_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `price` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `inclusions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inclusions`)),
  `exclusions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`exclusions`)),
  `itinerary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`itinerary`)),
  `pricing_tiers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pricing_tiers`)),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `package_type` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flight_packages`
--

INSERT INTO `flight_packages` (`id`, `partner_id`, `partner_name`, `title`, `destination`, `address`, `duration`, `price`, `description`, `image`, `gallery`, `inclusions`, `exclusions`, `itinerary`, `pricing_tiers`, `latitude`, `longitude`, `package_type`, `category`, `approved`, `created_at`, `updated_at`) VALUES
('PKG-20260622-1728', 'partner_1', 'HeyDream Travel Agency', 'Kkk', 'Boracay, Aklan', 'Station 1, Boracay Island, Malay, Aklan, Philippines', '', '₱222', 'White sand beaches, crystal clear waters, and stunning sunsets', 'file:///data/user/0/host.exp.exponent/cache/ImagePicker/46d47b7d-2db5-49bd-960c-7eb36eb3ee09.jpeg', '[]', '[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"]', '[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]', '[]', '[{\"type\":\"Adult\",\"price\":\"222\"}]', 11.96740000, 121.92470000, 'Package', 'Package', 1, '2026-06-22 08:47:00', '2026-06-22 08:47:00'),
('PKG-20260622-5097', 'partner_1', 'HeyDream Travel Agency', 'Hayyaya', 'Boracay, Aklan', 'Station 1, Boracay Island, Malay, Aklan, Philippines', '', '₱333', 'White sand beaches, crystal clear waters, and stunning sunsets', 'file:///data/user/0/host.exp.exponent/cache/ImagePicker/e632d943-5034-4244-aaaf-d860d63f1e92.jpeg', '[]', '[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"]', '[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]', '[]', '[{\"type\":\"Adult\",\"price\":\"333\"}]', 11.96740000, 121.92470000, 'Package', 'Package', 1, '2026-06-22 08:34:26', '2026-06-22 08:34:26'),
('PKG-20260622-9077', 'partner_1', 'HeyDream Travel Agency', 'Testing', 'Boracay, Aklan', 'Station 1, Boracay Island, Malay, Aklan, Philippines', '', '₱9,999', 'White sand beaches, crystal clear waters, and stunning sunsets', 'file:///data/user/0/host.exp.exponent/cache/ImagePicker/2d4941ec-2fc4-43f5-8b25-f97d27c788bb.jpeg', '[]', '[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"]', '[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]', '[]', '[{\"type\":\"Adult\",\"price\":\"9999\"}]', 11.96740000, 121.92470000, 'Package', 'Package', 1, '2026-06-22 08:19:16', '2026-06-22 08:19:16'),
('PKG-20260623-5339', 'partner_1', 'HeyDream Travel Agency', 'Heydream packages', 'Boracay, Aklan', 'Station 1, Boracay Island, Malay, Aklan, Philippines', '', '₱99,999', 'White sand beaches, crystal clear waters, and stunning sunsets', 'file:///data/user/0/host.exp.exponent/cache/ImagePicker/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg', '[]', '[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"]', '[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]', '[]', '[{\"type\":\"Adult\",\"price\":\"99999\"}]', 11.96740000, 121.92470000, 'Package', 'Package', 1, '2026-06-23 06:34:17', '2026-06-23 06:34:17'),
('PKG-20260624-5908', 'partner_1', 'HeyDream Travel Agency', 'Heydream packages', 'Boracay, Aklan', 'Station 1, Boracay Island, Malay, Aklan, Philippines', '', '₱99,999', 'White sand beaches, crystal clear waters, and stunning sunsets', 'file:///data/user/0/host.exp.exponent/cache/ImagePicker/65c9fe67-9e79-4f3b-8d3d-8d1fcc293239.jpeg', '[]', '[\"Hotel Accommodation\",\"Daily Breakfast\",\"Airport Transfers\"]', '[\"Airfare\",\"Travel Insurance\",\"Personal Expenses\"]', '[]', '[{\"type\":\"Adult\",\"price\":\"99999\"}]', 11.96740000, 121.92470000, 'Package', 'Package', 1, '2026-06-24 02:12:45', '2026-06-24 02:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `partnership_reports`
--

CREATE TABLE `partnership_reports` (
  `id` int(11) NOT NULL,
  `report_id` varchar(20) NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `reported_by` varchar(100) NOT NULL,
  `reported_email` varchar(255) NOT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `status` enum('open','in_review','resolved') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partnership_reports`
--

INSERT INTO `partnership_reports` (`id`, `report_id`, `category`, `title`, `description`, `reported_by`, `reported_email`, `priority`, `status`, `created_at`, `resolved_at`) VALUES
(1, 'PR-001', 'Aggressive Behavior', 'Verbal abuse towards staff', 'Customer repeatedly used offensive language', 'Golden Hotel', 'manager@golden.com', 'High', 'open', '2024-05-20 02:30:00', NULL),
(2, 'PR-002', 'No-Show', 'Repeated no-show appointments', 'Customer booked 5 sessions and never showed', 'Wellness Center', 'contact@wellness.com', 'Medium', 'in_review', '2024-05-21 06:15:00', NULL),
(3, 'PR-003', 'Payment Dispute', 'Fraudulent chargeback attempt', 'Customer issued chargeback after service delivered', 'Freelance Studio', 'billing@freelance.com', 'High', 'open', '2024-05-22 01:45:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `partner_applications`
--

CREATE TABLE `partner_applications` (
  `id` int(11) NOT NULL,
  `application_id` varchar(20) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `person_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `business_permit_filename` varchar(255) DEFAULT NULL,
  `business_permit_path` varchar(500) DEFAULT NULL,
  `dti_filename` varchar(255) DEFAULT NULL,
  `dti_path` varchar(500) DEFAULT NULL,
  `sec_filename` varchar(255) DEFAULT NULL,
  `sec_path` varchar(500) DEFAULT NULL,
  `dot_filename` varchar(255) DEFAULT NULL,
  `business_id_filename` varchar(255) DEFAULT NULL,
  `face_verification_filename` varchar(255) DEFAULT NULL,
  `face_verification_path` varchar(500) DEFAULT NULL,
  `dot_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partner_applications`
--

INSERT INTO `partner_applications` (`id`, `application_id`, `business_name`, `person_name`, `email`, `phone`, `business_type`, `address`, `latitude`, `longitude`, `message`, `business_permit_filename`, `business_permit_path`, `dti_filename`, `dti_path`, `sec_filename`, `sec_path`, `dot_filename`, `business_id_filename`, `face_verification_filename`, `face_verification_path`, `dot_path`, `status`, `rejection_reason`, `submitted_at`, `reviewed_at`) VALUES
(21, 'APP-20260618-6026', 'Hey Dream Travel and Tours', 'Steven Rebancos', 'heydreamtravelandtours@gmail.com', '09919612457', 'Travel Agency', 'Lot 17, Santan, Taguig, Metro Manila, Philippines', '14.5367772', '121.0896933', 'Application from Hey Dream Travel and Tours  - Travel Agency ', 'business_permit_20260618_090042_4028.jpeg', NULL, 'dti_20260618_090042_5989.jpeg', NULL, '', NULL, '', 'business_id_20260618_090042_7927.jpeg', 'face_verification_20260618_090042_6921.jpg', NULL, NULL, 'approved', NULL, '2026-06-18 07:00:42', '2026-06-25 07:06:25');

-- --------------------------------------------------------

--
-- Table structure for table `system_reports`
--

CREATE TABLE `system_reports` (
  `id` int(11) NOT NULL,
  `report_id` varchar(20) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('High','Medium','Low') DEFAULT 'Medium',
  `reported_by` varchar(100) DEFAULT NULL,
  `reported_by_type` enum('customer','partnership','system') DEFAULT 'system',
  `status` enum('open','in_progress','resolved') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_reports`
--

INSERT INTO `system_reports` (`id`, `report_id`, `type`, `title`, `description`, `severity`, `reported_by`, `reported_by_type`, `status`, `created_at`, `resolved_at`) VALUES
(1, 'SR-001', 'API Timeout', 'Payment gateway timeout', 'POST /payments/charge: 504 error. Affected 12 users', 'High', NULL, 'customer', 'open', '2024-05-22 02:23:00', NULL),
(2, 'SR-002', 'Crash', 'React Native navigation crash', 'Android deep link caused white screen', 'Medium', NULL, 'customer', 'in_progress', '2024-05-21 07:10:00', NULL),
(3, 'SR-003', 'Data Issue', 'Partnership package not synced', 'Some packages missing from search index', 'Low', NULL, 'partnership', 'open', '2024-05-20 00:45:00', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_partner` (`partner_id`),
  ADD KEY `idx_submitted` (`submitted_at`);

--
-- Indexes for table `customer_reports`
--
ALTER TABLE `customer_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_report_type` (`report_type`);

--
-- Indexes for table `customer_report_comments`
--
ALTER TABLE `customer_report_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `flight_packages`
--
ALTER TABLE `flight_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner` (`partner_id`),
  ADD KEY `idx_approved` (`approved`);

--
-- Indexes for table `partnership_reports`
--
ALTER TABLE `partnership_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `partner_applications`
--
ALTER TABLE `partner_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`);

--
-- Indexes for table `system_reports`
--
ALTER TABLE `system_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_reports`
--
ALTER TABLE `customer_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `customer_report_comments`
--
ALTER TABLE `customer_report_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `partnership_reports`
--
ALTER TABLE `partnership_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `partner_applications`
--
ALTER TABLE `partner_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `system_reports`
--
ALTER TABLE `system_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_report_comments`
--
ALTER TABLE `customer_report_comments`
  ADD CONSTRAINT `customer_report_comments_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `customer_reports` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

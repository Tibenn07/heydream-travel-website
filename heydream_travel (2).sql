-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2026 at 04:52 AM
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
-- Database: `heydream_travel`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_registration_requests`
--

CREATE TABLE `admin_registration_requests` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','editor','sales') DEFAULT 'admin',
  `request_token` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_registration_requests`
--

INSERT INTO `admin_registration_requests` (`id`, `username`, `email`, `full_name`, `password`, `role`, `request_token`, `status`, `requested_at`, `processed_at`, `processed_by`, `rejection_reason`) VALUES
(12, 'Tibenn', 'rebancossteven35@gmail.com', 'Steven Rebancos', '$2y$10$QJUpXFLaLcrXXzv.bIZKreOpT8P9RjdstlOsYqUWwpI309mTlg33C', 'admin', 'c8c105a252931cacbcdcb96ce687e5ac7184c48b09a8a8201ebab96b5730625f', 'approved', '2026-03-25 02:37:35', '2026-03-25 02:38:28', 1, NULL),
(18, 'tester', 'tester@example.com', 'Tester Admin', '$2y$10$lwI8O0e4/tftOtG4GkGav.5tBbt/oVinvpKphQvpjAgiBBKm5iP7G', 'admin', '9f6a49ee1977a6471b34aeb6776acca5f5ca605dc2b8a2b9c0fe46282abb2f5f', 'rejected', '2026-04-13 06:41:29', '2026-05-26 08:29:44', 1, ''),
(21, 'kos', 'jkasuela@gmail.com', 'John Kostya Asuela', '$2y$10$d67aJCwgvaVmauwtr59V/u6g4tGEOfIX9cfpwanqNQRbW4s4yItxK', 'sales', '1722ce08ae025289b5a266901cffafcfac28566dfe64759b9bcafe1b387f9a27', 'approved', '2026-05-26 05:58:58', '2026-05-26 08:49:42', 1, NULL),
(23, 'gela', 'gelabean05@gmail.com', 'Angela Lou  Dela Cruz', '$2y$10$o.opNFFsOEj.7GjJNCLCxO0avVyGnqussEhXzna3SYk4N9niF5fCG', 'admin', '982c9cba9ac37be96d074be15c859db6f89ff84e3fbdbd96449c06923c5dcdde', 'approved', '2026-05-26 08:43:37', '2026-06-01 11:36:23', 1, NULL),
(25, 'hmm', 'hmm11@gmail.com', 'hmmm', '$2y$10$aDnmIfk9JrhOvh6vQJzRRejczvRA08gdpuh4Ze7J5vXyNcavz0s8O', 'sales', '99ea9f506a3de6e21d904cd20908968ceb83e572587642c52347b0df07e04d07', 'rejected', '2026-06-01 12:05:28', '2026-06-01 12:17:59', 1, ''),
(26, 'Gelo', 'angelomarc@gmail.com', 'Angelo', '$2y$10$S1cHbF6gsBWjoTzTMEPgAuP1qIYrlSbh07w59mOktFV3uEaztcCXK', 'sales', 'ea7f7d8f18b825bba887319086d3d972af954c9180cb7b49b59b8d9d42e2ecf8', 'pending', '2026-06-01 12:06:33', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','editor','sales') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `approved` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `last_password_reset` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `is_active`, `approved`, `last_login`, `reset_token`, `reset_token_expires`, `last_password_reset`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', 'heydreamtravelandtours', '$2y$10$egjHNeezJpc/I3ezli1jJuuRWBa9JMbkL7gd2nfKMMWFv5/ZLdaSe', 'Super Administrator', 'super_admin', 1, 1, '2026-06-11 13:15:01', NULL, NULL, NULL, '2026-03-23 05:21:35', '2026-06-11 05:15:01'),
(7, 'Tibenn', 'rebancossteven35@gmail.com', '$2y$10$Dd8r.3wclnUSY.1hIf04nuAw3QuAr4II5Wxzxekfn4sdKlj3sP7iW', 'Steven Rebancos', 'admin', 1, 1, '2026-05-19 11:22:03', 'c6e86cb2600a91f352cce8dcdf3048ee901c1db8ada0128231a20e3d7d940ee29d447fc021c9cb1c4eb98f17f2eca784d3e4', '2026-04-17 04:56:41', '2026-04-17 08:48:42', '2026-03-25 02:38:28', '2026-06-01 12:21:14'),
(8, 'Kostya', 'johnkostya@gmail.com', '$2y$10$ni3cdgdJ5R3gg2G9VV4xue89z363o63I.AUDPuHxuVY/v8Ppr/tfy', 'John Kostya Asuela', 'editor', 0, 0, '2026-03-25 10:55:26', NULL, NULL, NULL, '2026-03-25 02:40:46', '2026-05-25 08:10:49'),
(11, 'Angela Lou', 'angelalou@gmail.com', '$2y$10$R3mHFlbfGhECqa1I7FyYkeLO5zjJfv1aNkJspDH0hNJXsAUZtyT6O', 'Angela Lou Dela Cruz', 'sales', 0, 0, '2026-03-29 13:23:06', NULL, NULL, NULL, '2026-03-29 05:22:41', '2026-05-26 08:30:14'),
(12, 'asdasd', 'asfs@gmail.com', '$2y$10$xkffYAQs7zlSwbrP7jfZEugvXyTAI/8WyAFDhhaw76oOK6l.9G0By', 'asc', 'admin', 0, 0, NULL, NULL, NULL, NULL, '2026-05-25 08:11:21', '2026-05-26 05:40:55'),
(13, 'Leonard', 'leonardrebancos2004@gmail.com', '$2y$10$Pya/OevsufkmQLNGZd7h2e3/0Zy/tNVhQjx6Rkyq9Z4NEOIfdN4yC', 'Leonard Rebancos', 'admin', 0, 0, NULL, NULL, NULL, NULL, '2026-05-26 05:18:53', '2026-05-26 05:37:16'),
(14, 'kos', 'jkasuela@gmail.com', '$2y$10$d67aJCwgvaVmauwtr59V/u6g4tGEOfIX9cfpwanqNQRbW4s4yItxK', 'John Kostya Asuela', 'admin', 1, 1, NULL, NULL, NULL, NULL, '2026-05-26 08:49:42', '2026-06-01 12:21:05'),
(15, 'gela', 'gelabean05@gmail.com', '$2y$10$o.opNFFsOEj.7GjJNCLCxO0avVyGnqussEhXzna3SYk4N9niF5fCG', 'Angela Lou  Dela Cruz', 'admin', 1, 1, NULL, NULL, NULL, NULL, '2026-06-01 11:36:23', '2026-06-01 11:36:23');

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_messages`
--

CREATE TABLE `ai_chat_messages` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `sender` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_seen` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_chat_messages`
--

INSERT INTO `ai_chat_messages` (`id`, `session_id`, `sender`, `message`, `timestamp`, `admin_seen`) VALUES
(639, 'hd_float_t4etlmscwuo_1779200190412', 'ai', '👋 Hi there Steven Rebancos! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-19 16:00:46', 0),
(640, 'hd_float_t4etlmscwuo_1779200190412', 'ai', '👋 Hi there Steven Rebancos! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-19 16:00:47', 0),
(641, 'hd_float_t4etlmscwuo_1779200190412', 'customer', 'hello', '2026-05-19 16:00:50', 1),
(642, 'hd_float_t4etlmscwuo_1779200190412', 'ai', 'Hello again, Steven Rebancos! 👋 I\'m excited to help you plan your next adventure. What travel inspiration can I provide for you today?', '2026-05-19 16:00:59', 0),
(644, 'hd_float_t4etlmscwuo_1779200190412', 'customer', 'How can I pay for the package?', '2026-05-20 02:18:00', 1),
(645, 'hd_float_t4etlmscwuo_1779200190412', 'ai', 'You have several convenient ways to pay for your dream package! We accept secure payments via GCash, PayMaya, BPI, BDO, and Metrobank transfers. You can also use your Visa, Mastercard, or JCB credit/debit cards directly through our website checkout.', '2026-05-20 02:18:04', 0),
(647, 'hd_float_jvhhk3kpcle_1779180804654', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 02:31:57', 0),
(648, 'hd_form_i0dnw4bl4_1779204896442', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 02:32:22', 0),
(649, 'hd_float_jvhhk3kpcle_1779180804654', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 02:33:12', 0),
(650, 'hd_form_i0dnw4bl4_1779204896442', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 02:34:35', 0),
(651, 'hd_float_jvhhk3kpcle_1779180804654', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 02:35:01', 0),
(662, 'hd_idx_s8f3pb2z0_1779255911889', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 05:45:12', 0),
(663, 'hd_idx_s8f3pb2z0_1779255911889', 'customer', 'What destinations do you offer?', '2026-05-20 05:45:42', 1),
(664, 'hd_idx_s8f3pb2z0_1779255911889', 'ai', 'We\'d love to help you explore the world! HeyDream Travel and Tours offers a variety of breathtaking destinations, perfect for every kind of traveler.<br><br>\n\n<strong>Local Gems in the Philippines:</strong><br>\n1.  <strong>Boracay</strong><br>\n    <img src=\\', '2026-05-20 05:45:49', 0),
(665, 'hd_idx_s8f3pb2z0_1779255911889', 'customer', 'how do I book?', '2026-05-20 05:46:14', 1),
(666, 'hd_idx_s8f3pb2z0_1779255911889', 'ai', 'To book a package, get a custom itinerary, or customize your dream trip, please visit our <a href=\'inquiry/inquire.php\' style=\'color:#003580; font-weight:bold; text-decoration:underline;\'>Inquiry Page</a>. This is where our team can best assist you with your booking needs!\n\nWhat destination are you dreaming of for your next adventure?\nWould you like to see our featured local destinations?\nCan I help you with visa assistance information?', '2026-05-20 05:46:17', 0),
(673, 'hd_idx_s8f3pb2z0_1779255911889', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-20 06:00:40', 0),
(681, 'test_session_12345', 'customer', 'Do you have local tour packages?', '2026-05-20 06:57:29', 1),
(682, 'test_session_12345', 'ai', 'Yes, Guest! We have amazing local packages to the most beautiful islands in the Philippines. Here are our top featured local destinations:<br><br><strong>1. Boracay</strong><br><img src=\"images/boracay.jpg\" alt=\"Boracay\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\">White-sand beaches, water sports, and vibrant nightlife.<br><br><strong>2. Siargao</strong><br><img src=\"images/siargao.jpg\" alt=\"Siargao\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\">The surf capital of the Philippines with breathtaking island hopping.<br><br><strong>3. El Nido</strong><br><img src=\"images/elnido.jpg\" alt=\"El Nido\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\">Stunning lagoons and a true pristine island paradise.<br><br>Ready to plan your getaway? You can easily customize your trip on our <a href=\'inquiry/inquire.php\' style=\'color:#003580; font-weight:bold; text-decoration:underline;\'>Inquiry Page</a>!', '2026-05-20 06:57:34', 0),
(691, 'hd_form_uqadoukhp_1779181288137', 'customer', 'How do I fill out this inquiry form?', '2026-05-20 08:07:25', 1),
(692, 'hd_form_uqadoukhp_1779181288137', 'ai', 'Filling out our Inquiry Form is incredibly easy, Guest! Just enter your basic contact info, preferred travel dates, and destination. <br><br>Don\'t worry—no passport details are needed right now, and you can add any custom requests in the \'Special Requests\' field. Once you click <strong>\'Plan My Trip\'</strong>, our experts will send you a free, personalized quote within minutes!', '2026-05-20 08:07:28', 0),
(693, 'hd_form_uqadoukhp_1779181288137', 'customer', 'What destinations can I choose?', '2026-05-20 08:50:34', 1),
(694, 'hd_form_uqadoukhp_1779181288137', 'ai', 'We offer a beautiful selection of handpicked destinations, Guest! Here are our most popular packages:<br><br><strong>🏝️ Local Paradises (Philippines):</strong><br>• <strong>Boracay:</strong> White-sand beaches and vibrant nightlife.<br><img src=\"../images/boracay.jpg\" alt=\"Boracay\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>• <strong>Siargao:</strong> The ultimate surf capital and island-hopping haven.<br><img src=\"../images/siargao.jpg\" alt=\"Siargao\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>• <strong>El Nido:</strong> Stunning limestone lagoons and pristine beaches.<br><img src=\"../images/elnido.jpg\" alt=\"El Nido\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br><strong>✈️ Dream Foreign Gateways:</strong><br>• <strong>Japan:</strong> Cherry blossoms, incredible food, and modern culture.<br><img src=\"../images/japan.jpg\" alt=\"Japan\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>• <strong>Korea:</strong> K-culture, street food, and historic palaces.<br><img src=\"../images/korea.jpg\" alt=\"Korea\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br>• <strong>Vietnam:</strong> Ha Long Bay and delicious local cuisine.<br><img src=\"../images/vietnam.jpg\" alt=\"Vietnam\" style=\"width:100%; max-width:260px; border-radius:10px; margin:6px 0; display:block; border: 1.5px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.12);\"><br><br>If you want to go somewhere else, just select \"Others\" on the form and write your dream destination in the special requests!', '2026-05-20 08:50:39', 0),
(695, 'hd_form_08bxc5nm1_1779237708230', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-21 01:15:20', 0),
(696, 'hd_idx_ako7c8krd_1779325141197', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-21 02:49:40', 0),
(697, 'hd_idx_01vwol9w9_1779411183512', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-22 00:53:03', 0),
(698, 'hd_idx_qvzdx5iia_1779411283534', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-22 00:54:43', 0),
(699, 'hd_idx_ako7c8krd_1779325141197', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-22 01:40:58', 0),
(700, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 03:58:55', 0),
(701, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 06:08:41', 0),
(702, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 06:35:05', 0),
(703, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 06:41:14', 0),
(704, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 06:44:19', 0),
(705, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 06:50:37', 0),
(706, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 07:18:33', 0),
(707, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 07:41:28', 0),
(708, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 07:54:40', 0),
(709, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-25 08:04:43', 0),
(710, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-26 03:40:11', 0),
(711, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-26 08:17:07', 0),
(712, 'hd_idx_zy1m40ozi_1779689878511', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-28 05:29:33', 0),
(713, 'hd_idx_2kfpuc4kf_1779952682369', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-28 07:18:03', 0),
(714, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-29 01:22:33', 0),
(715, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-05-29 06:27:53', 0),
(716, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 00:16:43', 0),
(717, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 01:37:48', 0),
(718, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 03:16:01', 0),
(719, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 03:17:57', 0),
(720, 'hd_float_zxdhpaydil_1779237139818', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 03:35:40', 0),
(721, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 05:15:26', 0),
(722, 'hd_idx_ltndezg93_1779673189255', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-01 08:15:19', 0),
(723, 'hd_idx_8u4afqkxx_1779411800353', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-02 03:33:17', 0),
(724, 'hd_idx_lbw6jywsu_1779247727906', 'ai', '👋 Hi there! Welcome to <strong>HeyDream Travel and Tours!</strong><br>I\'m <strong>HeyDream AI</strong>, your personal travel assistant. 😊<br><br>How can I help you today? Do you have a destination in mind, or would you like to know more about our packages? ✈️🌏', '2026-06-02 08:25:25', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ai_chat_sessions`
--

CREATE TABLE `ai_chat_sessions` (
  `session_id` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `assigned_agent_id` int(11) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_chat_sessions`
--

INSERT INTO `ai_chat_sessions` (`session_id`, `customer_name`, `customer_email`, `status`, `assigned_agent_id`, `last_activity`, `created_at`) VALUES
('hd_float_jvhhk3kpcle_1779180804654', 'Guest', '', 'active', NULL, '2026-05-20 02:35:01', '2026-05-20 02:31:57'),
('hd_float_t4etlmscwuo_1779200190412', 'Guest', '', 'active', NULL, '2026-05-20 02:18:00', '2026-05-19 16:00:46'),
('hd_float_zxdhpaydil_1779237139818', 'Guest', '', 'active', NULL, '2026-06-01 03:35:40', '2026-06-01 03:35:40'),
('hd_form_08bxc5nm1_1779237708230', 'Guest', '', 'active', NULL, '2026-05-21 01:15:20', '2026-05-21 01:15:20'),
('hd_form_i0dnw4bl4_1779204896442', 'Guest', '', 'active', NULL, '2026-05-20 02:34:35', '2026-05-20 02:32:22'),
('hd_form_uqadoukhp_1779181288137', 'Guest', '', 'active', NULL, '2026-05-20 08:50:34', '2026-05-20 08:07:25'),
('hd_idx_01vwol9w9_1779411183512', 'Guest', '', 'active', NULL, '2026-05-22 00:53:03', '2026-05-22 00:53:03'),
('hd_idx_2kfpuc4kf_1779952682369', 'Guest', '', 'active', NULL, '2026-05-28 07:18:03', '2026-05-28 07:18:03'),
('hd_idx_8u4afqkxx_1779411800353', 'Guest', '', 'active', NULL, '2026-06-02 03:33:17', '2026-06-02 03:33:17'),
('hd_idx_ako7c8krd_1779325141197', 'Guest', '', 'active', NULL, '2026-05-22 01:40:58', '2026-05-21 02:49:40'),
('hd_idx_lbw6jywsu_1779247727906', 'Guest', '', 'active', NULL, '2026-06-02 08:25:25', '2026-06-02 08:25:25'),
('hd_idx_ltndezg93_1779673189255', 'Guest', '', 'active', NULL, '2026-06-01 08:15:19', '2026-05-25 03:58:55'),
('hd_idx_qvzdx5iia_1779411283534', 'Guest', '', 'active', NULL, '2026-05-22 00:54:43', '2026-05-22 00:54:43'),
('hd_idx_s8f3pb2z0_1779255911889', 'Guest', '', 'active', NULL, '2026-05-20 06:00:40', '2026-05-20 05:45:12'),
('hd_idx_zy1m40ozi_1779689878511', 'Guest', '', 'active', NULL, '2026-05-28 05:29:33', '2026-05-28 05:29:33'),
('test_session_12345', 'Guest', '', 'active', NULL, '2026-05-20 06:57:29', '2026-05-20 06:57:29');

-- --------------------------------------------------------

--
-- Table structure for table `block_unlock_requests`
--

CREATE TABLE `block_unlock_requests` (
  `id` int(11) NOT NULL,
  `admin_username` varchar(100) NOT NULL,
  `block_type` varchar(50) NOT NULL,
  `status` enum('pending','approved','locked') DEFAULT 'pending',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `block_unlock_requests`
--

INSERT INTO `block_unlock_requests` (`id`, `admin_username`, `block_type`, `status`, `expires_at`, `created_at`) VALUES
(1, 'Tibenn', 'header', 'locked', NULL, '2026-05-18 06:05:30');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `booking_number` varchar(50) NOT NULL,
  `destination_name` varchar(100) DEFAULT NULL,
  `package_name` varchar(100) DEFAULT NULL,
  `package_duration` varchar(50) DEFAULT NULL,
  `price_per_person` decimal(10,2) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `travel_date` date NOT NULL,
  `number_of_travelers` int(11) NOT NULL DEFAULT 1,
  `special_requests` text DEFAULT NULL,
  `flight_details` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT '₱',
  `booking_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_processed` tinyint(1) DEFAULT 0,
  `travel_documents` tinyint(1) DEFAULT 0,
  `ready_for_travel` tinyint(1) DEFAULT 0,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visa_status` varchar(50) DEFAULT 'PENDING',
  `marketing_consent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `booking_number`, `destination_name`, `package_name`, `package_duration`, `price_per_person`, `full_name`, `email`, `phone`, `address`, `travel_date`, `number_of_travelers`, `special_requests`, `flight_details`, `admin_notes`, `total_amount`, `currency`, `booking_status`, `payment_status`, `payment_method`, `payment_reference`, `payment_proof`, `payment_processed`, `travel_documents`, `ready_for_travel`, `reminder_sent`, `created_at`, `updated_at`, `visa_status`, `marketing_consent`) VALUES
(42, 6, 'FO-89118920260512', 'Foreign Destination', '4D3N EXPLORE SINGAPORE F&E', '4D/3N', 265.00, 'Angela Lou G. Dela Cruz', 'angelaloudelacruz7@gmail.com', '+639079128442', NULL, '2026-05-20', 1, '', '', '', 265.00, '$', 'completed', 'paid', 'gcash', '12345', 'uploads/receipts/REC_6a0386588f6a3_20260512.jpg', 0, 1, 1, 0, '2026-05-12 11:58:16', '2026-05-25 07:03:03', 'APPROVED', 1),
(43, 2, 'FL-C676E120260512', 'Flash Deal', '3D2N PPS Free & Easy', '3D/2N', 2894.00, 'Steven Rebancos', 'rebancossteven35@gmail.com', '09091236650', NULL, '2026-05-22', 1, '', '', '', 2894.00, '₱', 'pending', 'paid', 'gcash', '89077', 'uploads/receipts/REC_6a03944c64dd7_20260512.jpg', 0, 0, 0, 0, '2026-05-12 12:57:48', '2026-05-18 07:04:51', 'APPROVED', 1),
(44, 3, 'LO-96E54320260512', 'Local Package', '3D2N BORACAY Free and Easy', '3D/2N', 4644.00, 'John Kostya Asuela', 'asuelajohnkostya@gmail.com', '09091236650', NULL, '2026-05-25', 1, '', '', '', 4644.00, '₱', 'pending', 'paid', 'gcash', '9999', 'uploads/receipts/REC_6a0399595cf04_20260512.jpg', 0, 0, 0, 0, '2026-05-12 13:19:21', '2026-05-18 07:04:51', 'APPROVED', 1),
(46, 6, 'VI-F5DAEE20260513', 'Visa Assistance', 'Singapore', 'Regular', 999.00, 'Angela Lou Dela Cruz', 'angelaloudelacruz7@gmail.com', '09463435820', NULL, '2026-05-17', 1, 'Passport: P1234567A, DOB: 2003-11-05, Address: angelaloudelacruz7@gmail.com, Destination: Singapore, Embassy: manila, Occupation: , Travel History: ', '', '', 999.00, '₱', 'completed', 'paid', 'Manual Agent Approval', 'PENDING_AGENT', NULL, 0, 1, 1, 0, '2026-05-12 16:08:15', '2026-05-25 07:05:58', 'APPROVED', 1),
(48, 6, 'FO-FA655F20260513', 'Foreign Destination', '3D2N HKG FREE 7 EASY (HKFE-3D)', '3D2N', 105.00, 'Angela Lou Dela Cruz', 'angelaloudelacruz7@gmail.com', '09091236650', NULL, '2026-05-20', 1, '', '', '', 105.00, '$', 'completed', 'paid', 'gcash', '12345', 'uploads/receipts/REC_6a03caefa4358_20260513.jpg', 0, 1, 1, 0, '2026-05-12 16:50:55', '2026-05-25 07:04:45', 'APPROVED', 1),
(49, NULL, 'INQ-0AE1A320260514', 'tour-package', '', 'N/A', 0.00, 'Steven Fernandez Rebancos', 'rebancossteven35@gmail.com', '+639919612457', NULL, '2026-05-14', 0, 'Destination: \nTravel Type: tour-package\nBudget: Not specified\nHotel Preference: Not specified\n\nHow did you hear about us: Facebook', NULL, NULL, 0.00, '₱', 'pending', 'unpaid', 'Inquiry Only', NULL, NULL, 0, 0, 0, 1, '2026-05-13 18:28:16', '2026-05-21 02:11:03', 'PENDING', 1),
(51, NULL, 'INQ-389F0F20260514', 'tour-package', '', 'N/A', 0.00, 'John Kostya Asuela', 'asuelajohnkostya@gmail.com', '+63991961245897', NULL, '2026-05-14', 0, 'Destination: \nTravel Type: tour-package\nBudget: Not specified\nHotel Preference: Not specified\n\nHow did you hear about us: Twitter', NULL, NULL, 0.00, '₱', 'pending', 'unpaid', 'Inquiry Only', NULL, NULL, 0, 0, 0, 0, '2026-05-13 19:26:27', '2026-05-18 07:04:51', 'PENDING', 1),
(0, 16, 'VI-D22FD620260526', 'Visa Assistance', 'Singapore', 'Regular', 999.00, 'Angela Lou G. Dela Cruz', 'agdelacruz@paterostechnologicalcollege.edu.ph', '+639079128442', NULL, '2026-05-26', 1, 'Applicants: 1, Passport: P1234567A, DOB: 2003-11-05, Address: angelaloudelacruz7@gmail.com, Destination: Singapore, Embassy: manila, Occupation: , Travel History: ', '', '', 999.00, '₱', 'pending', 'unpaid', 'Manual Agent Approval', 'PENDING_AGENT', NULL, 0, 0, 0, 0, '2026-05-26 02:59:25', '2026-05-26 08:16:17', 'FOR_RELEASING', 0),
(0, 16, 'FO-18F67620260526', 'Foreign Destination', '3D2N HKG FREE 7 EASY (HKFE-3D)', '3D2N', 999.00, 'Angela Lou G. Dela Cruz', 'agdelacruz@paterostechnologicalcollege.edu.ph', '+639079128442', NULL, '2026-05-28', 1, '', '', '', 999.00, '$', 'pending', 'unpaid', 'gcash', '12345', 'uploads/receipts/REC_6a150ec18e411_20260526.png', 0, 0, 0, 0, '2026-05-26 03:08:49', '2026-05-26 08:11:53', 'PENDING', 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_documents`
--

CREATE TABLE `booking_documents` (
  `id` int(11) NOT NULL,
  `booking_number` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_documents`
--

INSERT INTO `booking_documents` (`id`, `booking_number`, `file_path`, `file_name`, `uploaded_at`) VALUES
(10, 'FO-34734620260429', 'uploads/booking_docs/FO-34734620260429_1777442942_69f1a07ec5bf3.png', 'PORTRAIT POSTERCUSTOMER PIC.png', '2026-04-29 06:09:02');

-- --------------------------------------------------------

--
-- Table structure for table `cruises`
--

CREATE TABLE `cruises` (
  `id` int(11) NOT NULL,
  `cruise_code` varchar(50) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `gallery` text DEFAULT NULL,
  `departure_port` varchar(200) DEFAULT NULL,
  `destinations` text DEFAULT NULL,
  `route` text DEFAULT NULL,
  `ship_name` varchar(200) DEFAULT NULL,
  `cruise_line` varchar(200) DEFAULT NULL,
  `room_types` text DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `itinerary` text DEFAULT NULL,
  `ship_description` text DEFAULT NULL,
  `base_price` decimal(15,2) DEFAULT 0.00,
  `price_per_person` decimal(15,2) DEFAULT 0.00,
  `promo_price` decimal(15,2) DEFAULT 0.00,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `booking_deadline` date DEFAULT NULL,
  `available_slots` int(11) DEFAULT 0,
  `status` enum('Available','Full','Cancelled') DEFAULT 'Available',
  `required_documents` text DEFAULT NULL,
  `travel_requirements` text DEFAULT NULL,
  `health_requirements` text DEFAULT NULL,
  `cancellation_policy` text DEFAULT NULL,
  `refund_policy` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `destination_type` varchar(100) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `promo_text` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `feedback_count` int(11) DEFAULT 0,
  `is_published` tinyint(4) DEFAULT 1,
  `is_featured` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cruises`
--

INSERT INTO `cruises` (`id`, `cruise_code`, `title`, `short_description`, `full_description`, `duration`, `featured_image`, `gallery`, `departure_port`, `destinations`, `route`, `ship_name`, `cruise_line`, `room_types`, `amenities`, `itinerary`, `ship_description`, `base_price`, `price_per_person`, `promo_price`, `inclusions`, `exclusions`, `departure_date`, `return_date`, `booking_deadline`, `available_slots`, `status`, `required_documents`, `travel_requirements`, `health_requirements`, `cancellation_policy`, `refund_policy`, `terms_conditions`, `category`, `destination_type`, `tags`, `highlights`, `promo_text`, `rating`, `feedback_count`, `is_published`, `is_featured`, `created_at`, `updated_at`) VALUES
(4, 'CRS-2026', 'Royal Caribbean Wonder of the Seas', '', 'mnbvcxz', '3D/2N', 'uploads/cruise_1779419748.jpg', '[\"uploads\\/cruise_gallery_1779076069_0.jpg\",\"uploads\\/cruise_gallery_1779076069_1.jpg\",\"uploads\\/cruise_gallery_1779076069_2.jpg\",\"uploads\\/cruise_gallery_1779076095_0.jpg\",\"uploads\\/cruise_gallery_1779076095_1.png\",\"uploads\\/cruise_gallery_1779076095_2.jpg\"]', '', '', '', 'zxcvbgd', 'vghmnx', '', 'Food', 'Day 1: Japan\r\nDay 2: China', '', 10000.00, 0.00, 1000.00, '', '', '2026-05-19', '2026-05-20', '2026-05-21', 0, 'Available', '', '', '', '', '', '', 'Cruise Package', 'International', 'fas fa-ship', '', '', 0.0, 0, 1, 0, '2026-05-18 02:37:49', '2026-05-22 05:41:31'),
(5, 'CR-HK-004', 'Hong Kong Ocean Adventure', '', '', '3D2N', 'uploads/cruise_1779431054.jpg', '[]', 'Kai Tak Cruise Terminal, Hong Kong', '', '', 'Dream Cruises – Genting Dream', '', '', '', NULL, '', 42999.00, 0.00, 42000.00, '', '', NULL, NULL, NULL, 0, 'Available', '', '', '', '', '', '', '', 'International', 'Ocean View', '', '', 0.0, 0, 1, 0, '2026-05-22 06:24:14', '2026-05-22 06:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `cruise_itinerary`
--

CREATE TABLE `cruise_itinerary` (
  `id` int(11) NOT NULL,
  `cruise_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cruise_itinerary`
--

INSERT INTO `cruise_itinerary` (`id`, `cruise_id`, `day_number`, `title`, `description`) VALUES
(36, 4, 1, 'Day 1 ARRIVAL', 'arrival in kdsjks station'),
(37, 4, 2, 'Day 2 Hesoyam', 'full of life experience'),
(38, 4, 3, 'Aezakmi', 'swimming with sharks'),
(40, 5, 1, 'Departure', 'Embarkation and welcome on board.');

-- --------------------------------------------------------

--
-- Table structure for table `customer_conversations`
--

CREATE TABLE `customer_conversations` (
  `id` int(11) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `message_type` enum('Tour Package Inquiry','Flight Booking','Visa Assistance','General Chat') DEFAULT 'General Chat',
  `status` enum('Active','Archived','Resolved') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `customer_last_typing` datetime DEFAULT NULL,
  `admin_last_typing` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_conversations`
--

INSERT INTO `customer_conversations` (`id`, `customer_email`, `customer_name`, `message_type`, `status`, `created_at`, `updated_at`, `customer_last_typing`, `admin_last_typing`) VALUES
(1, 'rebancossteven35@gmail.com', 'Steven Rebancos', 'General Chat', 'Active', '2026-05-28 14:49:34', '2026-06-02 11:54:43', NULL, '2026-06-02 11:54:41'),
(2, 'asuelajohnkostya@gmail.com', 'John Kostya Asuela', 'General Chat', 'Active', '2026-05-28 15:16:14', '2026-06-02 11:35:44', NULL, '2026-06-02 11:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `customer_messages`
--

CREATE TABLE `customer_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_type` enum('Customer','Admin','Staff') NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_messages`
--

INSERT INTO `customer_messages` (`id`, `conversation_id`, `sender_type`, `sender_name`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'Customer', 'Steven Rebancos', 'hiii', 1, '2026-05-28 14:49:42'),
(2, 1, 'Admin', 'superadmin', 'hello there how can i help you today?', 0, '2026-05-28 14:50:08'),
(3, 1, 'Admin', 'superadmin', 'how dare you!!', 0, '2026-05-28 15:03:55'),
(4, 1, 'Customer', 'Steven Rebancos', 'whaaat', 1, '2026-05-28 15:04:12'),
(5, 1, 'Customer', 'Steven Rebancos', 'dont you dare', 1, '2026-05-28 15:04:30'),
(6, 1, 'Admin', 'superadmin', 'ok', 0, '2026-05-28 15:04:57'),
(7, 1, 'Customer', 'Steven Rebancos', 'heyy', 1, '2026-05-28 15:07:49'),
(8, 1, 'Admin', 'superadmin', 'are you bading dong', 0, '2026-05-28 15:08:39'),
(9, 1, 'Customer', 'Steven Rebancos', 'yess i am', 1, '2026-05-28 15:08:51'),
(10, 2, 'Customer', 'John Kostya Asuela', 'hello hehehe', 1, '2026-05-28 15:16:22'),
(11, 2, 'Admin', 'superadmin', 'heloltzg', 0, '2026-05-28 15:17:56'),
(12, 2, 'Admin', 'superadmin', 'hi', 0, '2026-06-02 11:35:44'),
(13, 1, 'Customer', 'Steven Rebancos', 'hi', 1, '2026-06-02 11:54:43');

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('foreign','local') DEFAULT 'foreign',
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `location_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `short_description` varchar(200) DEFAULT NULL,
  `activities_count` int(11) DEFAULT 0,
  `image_path` varchar(500) DEFAULT NULL,
  `image2_path` varchar(500) DEFAULT NULL,
  `image3_path` varchar(500) DEFAULT NULL,
  `image4_path` varchar(500) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `badge_text` varchar(100) DEFAULT NULL,
  `package_price` decimal(10,2) DEFAULT NULL,
  `package_duration` varchar(20) DEFAULT NULL,
  `collage_type` enum('half','top-bottom','three','grid') DEFAULT 'three',
  `category` varchar(50) DEFAULT 'beach',
  `price` decimal(10,2) DEFAULT 0.00,
  `duration` varchar(50) DEFAULT '3D/2N',
  `group_size` varchar(50) DEFAULT '2-15 pax',
  `best_season` varchar(100) DEFAULT 'Year Round',
  `itinerary` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `currency` varchar(10) DEFAULT '₱',
  `hotels` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `promo_start` date DEFAULT NULL,
  `promo_end` date DEFAULT NULL,
  `blocked_months` text DEFAULT NULL,
  `highlight_duration` int(11) DEFAULT 1,
  `blocked_dates` text DEFAULT NULL,
  `image_gallery` text DEFAULT NULL,
  `booked_count` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinations`
--

INSERT INTO `destinations` (`id`, `name`, `type`, `country`, `city`, `location_name`, `description`, `image_url`, `is_active`, `created_at`, `short_description`, `activities_count`, `image_path`, `image2_path`, `image3_path`, `image4_path`, `display_order`, `badge_text`, `package_price`, `package_duration`, `collage_type`, `category`, `price`, `duration`, `group_size`, `best_season`, `itinerary`, `inclusions`, `exclusions`, `currency`, `hotels`, `remarks`, `promo_start`, `promo_end`, `blocked_months`, `highlight_duration`, `blocked_dates`, `image_gallery`, `booked_count`) VALUES
(1, 'South Korea', 'foreign', 'South Korea', 'Seoul', NULL, 'Experience the vibrant culture, delicious food, and stunning landscapes of South Korea.', 'images/korea.jpg', 1, '2026-03-21 09:59:25', NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(2, 'Japan', 'foreign', 'Japan', 'Tokyo', NULL, 'Discover the perfect blend of ancient traditions and futuristic technology in Japan.', 'images/japan.jpg', 1, '2026-03-21 09:59:25', NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(3, 'Thailand', 'foreign', 'Thailand', 'Bangkok', NULL, 'Explore beautiful temples, pristine beaches, and amazing street food in Thailand.', 'images/thailand.jpg', 1, '2026-03-21 09:59:25', NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(4, 'Singapore', 'foreign', 'Singapore', 'Singapore', NULL, 'A modern city-state with amazing architecture, shopping, and food.', 'images/singapore.jpg', 1, '2026-03-21 09:59:25', NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(5, 'Vietnam', 'foreign', 'Vietnam', 'Hanoi', NULL, 'Rich history, stunning landscapes, and delicious cuisine.', 'images/vietnam.jpg', 1, '2026-03-21 09:59:25', NULL, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(6, 'South Korea', 'foreign', 'South Korea', 'Seoul', NULL, 'Experience the vibrant culture, delicious food, and stunning landscapes of South Korea.', NULL, 1, '2026-03-23 08:43:50', 'Seoul • Busan • Jeju', 47, NULL, NULL, NULL, NULL, 0, NULL, 45999.00, '5D/4N', 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(7, 'Japan', 'foreign', 'Japan', 'Tokyo', NULL, 'Discover the perfect blend of ancient traditions and futuristic technology in Japan.', NULL, 1, '2026-03-23 08:43:50', 'Tokyo • Osaka • Kyoto', 52, NULL, NULL, NULL, NULL, 0, NULL, 68500.00, '6D/5N', 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(8, 'Thailand', 'foreign', 'Thailand', 'Bangkok', NULL, 'Explore beautiful temples, pristine beaches, and amazing street food in Thailand.', NULL, 1, '2026-03-23 08:43:50', 'Bangkok • Phuket', 38, NULL, NULL, NULL, NULL, 0, NULL, 32999.00, '4D/3N', 'three', 'beach', 0.00, '3D/2N', '2-15 pax', 'Year Round', NULL, NULL, NULL, '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(29, '3D2N BORACAY Free and Easy', 'local', 'Philippines', 'Boracay', 'Boracay, Bamboo Beach Resort Station 3, Beach Front', '\"Explore the island life and magical shores of Boracay.\"', NULL, 1, '2026-04-11 10:51:42', '', 12, 'uploads/1776008112_69dbbbb04baf1.jpg', NULL, NULL, NULL, 63, 'Bamboo Beach Resort Station', 0.00, '', 'three', 'beach', 4644.00, '3D/2N', 'Per Person', 'Year Round', '[{\"day\":1,\"title\":\"Day 1: Arrival in Boracay\",\"activities\":[\"Upon arrival at Caticlan Airport, please proceed to the Southwest Counter located outside the arrival area. Our representatives will assist you with your land and sea transfer service to the island. Enjoy your first evening with an overnight stay at the hotel.dv\",\"asv\"]},{\"day\":2,\"title\":\"Day 2: Free and Easy\",\"activities\":[\"Meals: Breakfast (B)\",\"Start your day with a delicious breakfast at the hotel.\",\"Enjoy the rest of the day at your own leisure. Explore the island, try local delicacies, or simply soak up the sun on White Beach.\",\"Overnight stay at the hotel.\"]},{\"day\":3,\"title\":\"Day 3: Departure from Boracay\",\"activities\":[\"Day 3: Departure from Boracay\",\"Meals: Breakfast (B)\",\"Enjoy breakfast at the hotel and some final free time at your own leisure.\",\"Please proceed with hotel check-out.\",\"Transfer to Caticlan Airport for your flight back home.\",\"🕒 Departure Reminder\",\"Please be at the hotel lobby 15 minutes prior to your scheduled departure transfer pick-up time to ensure a smooth trip to the airport.\",\"** End of Service **\",\"Note: Itinerary is subject to change without prior notice based on flight details, local weather, and traffic conditions.\"]}]', '[\"Round-trip Airport Transfers: Shared seat-in-coach via Caticlan Airport (Includes Terminal & Environmental Fees for local tourists)\",\"Accommodations: 3 Days & 2 Nights stay\",\"Dining: Daily hotel breakfast\",\"Travel Insurance: Complimentary coverage (up to 75 years old)\"]', '[\"Airfare and applicable airline taxes\",\"Gratuities and tipping\",\"Foreign Market Surcharge: Php 150.00\\/pax\",\"Kalibo Airport Surcharge: Php 200.00\\/pax\",\"Peak Season surcharges\",\"Personal expenses, optional tours, and extra meals\",\"Items not specifically listed in inclusions\"]', '₱', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(33, '3D2N BORACAY Free and Easy', 'local', 'Philippines', 'Boracay', 'Boracay, Secret Garden Resort', '\"Book your getaway and discover the Boracay magic.\"', NULL, 1, '2026-04-13 01:43:09', '', 32, 'uploads/1776044900_69dc4b64b4af8.jpg', '', '', NULL, 0, 'Secret Garden Resort', 0.00, '', 'three', 'beach', 4444.00, '3D/2N', 'Per Person', '', '[{\"day\":1,\"title\":\"Day 1: ARRIVAL IN BORACAY (No Meals)\",\"activities\":[\"Arrive at Caticlan Airport. Please approach the Southwest Counter outside the arrival area for your land and sea transfer service.\",\"Overnight stay at the hotel.\",\"Please take note:\",\"Guests who do not show up at the arrival counter, meet-up location, or pick-up station for departures shall be charged in full; no refunds will be issued.\",\"Guests must be at the meet-up location at the scheduled time. Otherwise, seat allocation will be forfeited in favor of waitlisted guests.\",\"Departing guests must be at the embarkation point at least 30 minutes before the scheduled departure.\",\"Porterage service is NOT included in the transfer fee.\",\"Each passenger is allowed two (2) pieces of hand-carried luggage. Excess luggage will be charged ₱250.00 per piece.\"]},{\"day\":2,\"title\":\"Day 2: FREE AND EASY (B)\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy free time on your own leisure.\",\"Overnight stay at the hotel.\"]},{\"day\":3,\"title\":\"Day 3: DEPART BORACAY (B)\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy free time on your own leisure until hotel check-out and transfer to Caticlan Airport.\",\"Note: Please be at the hotel lobby 15 minutes prior to the pick-up time of your departure transfer.\",\"** End of Service **\",\"(Itinerary is subject to change depending on flight details, local weather, and traffic conditions without prior notice.)\"]}]', '[\"Round trip airport transfers based on seat in coach via Caticlan Airport with Terminal and Environmental Fees (local tourists only)\",\"2N \\/ 3D Room Accommodation\",\"Daily hotel breakfast\",\"Complimentary travel insurance (up to 75 years old only)\"]', '[\"Any airfare & tax\",\"Any kind of tipping\",\"Foreign surcharge: Php 150.00 per person (airport transfer only; foreign market may be subject to additional surcharge depending on the hotel) - to be collected upon confirmation\",\"For arrival & departure via Kalibo Airport, an additional surcharge of Php 200.00 per person shall apply.\",\"Peak Season Surcharges (to be collected by VIA)\",\"Anything that is not specifically mentioned in the INCLUSIONS is on pax account\",\"Any kind of personal expenses or Optional tours\\/extra meals ordered by the guests\"]', '₱', '[]', '', '2026-04-01', '2026-09-30', '', 3, '', '[]', ''),
(34, '4D3N PPS Free & Easy', 'local', 'Philippines', 'Palawan', 'Puerta Princesa, Citystate Asturias Hotel Palawan', '', NULL, 1, '2026-04-13 01:56:13', '', 0, 'uploads/1776047218_69dc5472adb10.jpg', '', '', NULL, 0, 'Citystate Asturias Hotel', 0.00, '', 'three', 'beach', 3894.00, '4D/3N', 'Per Person', '', '[{\"day\":1,\"title\":\"Day 1: ARRIVAL IN PUERTO PRINCESA (NO MEALS)\",\"activities\":[\"Arrive at Puerto Princesa International Airport.\",\"Meet and transfer to hotel to check in.\",\"Overnight stay at the hotel.\"]},{\"day\":2,\"title\":\"Day 2: FREE AND EASY (B)\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy whole day free time or join an optional tour.\",\"Overnight stay at the hotel.\"]},{\"day\":3,\"title\":\"Day 3: FREE AND EASY (B)\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy whole day free time or join an optional tour.\",\"Overnight stay at the hotel.\"]},{\"day\":4,\"title\":\"Day 4: DEPART PUERTO PRINCESA (B)\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy free time on own leisure until check out from hotel and transfer to Puerto Princesa International Airport.\"]},{\"day\":5,\"title\":\"Remarks\",\"activities\":[\"Please be at the hotel lobby at least 15 minutes prior to pick up time.\",\"For SIC tours & transfers, please expect some delays due to the driver has to pick up other guests from different hotels too.\",\"Please be on time for the pick up to avoid inconvenience with the other guests.\"]}]', '[\"Round trip airport transfers based on seat in coach via Puerto Princesa International Airport\",\"3N \\/ 4D Room Accommodation\",\"Daily hotel breakfast\",\"Complimentary travel insurance (up to 75 years old only)\"]', '[\"Any airfare & tax\",\"Any kind of tipping\",\"Peak Season Surcharges (To be collected by VIA)\",\"Foreign Passport Surcharge (TBA upon confirmation of booking)\",\"Anything that is not specifically mentioned in the INCLUSIONS is on pax account\",\"Any kind of personal expenses or Optional tours\\/extra meals ordered by the guests\"]', '₱', '[]', '', NULL, NULL, '', 1, '', '[]', ''),
(35, '3D2N PPS Free & Easy', 'local', 'Philippines', 'Palawan', 'Puerta Princesa, Citystate Asturias Hotel Palawan', '', NULL, 1, '2026-04-13 02:40:34', '', 3433, 'uploads/1776048328_69dc58c8408ca.jpg', '', '', NULL, 0, 'Citystate Asturias Hotel', 0.00, '', 'three', 'beach', 2894.00, '3D/2N', 'Per Person', '', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"Arrive at Puerto Princesa International Airport.\",\"Meet and transfer to hotel to check in.\",\"Overnight Stay at the hotel.\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy whole day free time or join an optional tour.\",\"Overnight stay at the hotel.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"Breakfast at the hotel.\",\"Enjoy free time on own leisure until check out from hotel and transfer to Puerto Princesa International Airport.\"]}]', '[\"Round trip airport transfers based on seat in coach via Puerto Princesa International Airport\",\"2N \\/ 3D Room Accommodation\",\"Daily hotel breakfast\",\"Complimentary travel insurance (up to 75 years old only)\"]', '[\"Any airfare & tax\",\"Any kind of tipping\",\"Peak Season Surcharges (To be collected by VIA)\",\"Foreign Passport Surcharge (TBA upon confirmation of booking)\",\"Anything that is not specifically mentioned in the INCLUSIONS is on pax account\",\"Any kind of personal expenses or Optional tours\\/extra meals ordered by the guests\"]', '₱', '[{\"name\":\"John Hotel\",\"price\":344,\"stars\":1},{\"name\":\"Steve Hotel\",\"price\":699,\"stars\":3},{\"name\":\"Angge Hotel\",\"price\":9999,\"stars\":5},{\"name\":\"Yolo\",\"price\":0,\"stars\":0}]', 'Please be at the hotel lobby at least 15 minutes prior to pick up time.\r\nFor SIC tours & transfers, please expect some delays due to the driver has to pick up other guests from different hotels too.\r\nPlease be on time for the pick up to avoid inconvenience with the other guests.\r\n** End of Service **\r\n(Itinerary is subject to change depending on the flight details, local weather, and traffic condition without prior notice.)', '2026-04-01', '2026-05-31', '1,2,3,6,7,8,9,10,11,12', 3, '', '[]', '');

-- --------------------------------------------------------

--
-- Table structure for table `destinations_enhanced`
--

CREATE TABLE `destinations_enhanced` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('foreign','local') DEFAULT 'foreign',
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(200) DEFAULT NULL,
  `activities_count` int(11) DEFAULT 0,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flash_deals`
--

CREATE TABLE `flash_deals` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `original_price` decimal(10,2) DEFAULT 0.00,
  `discount_percent` int(11) DEFAULT 0,
  `duration` varchar(50) DEFAULT '3D/2N',
  `group_size` varchar(50) DEFAULT '2-15 pax',
  `best_season` varchar(100) DEFAULT 'Year Round',
  `rating` decimal(3,1) DEFAULT 0.0,
  `reviews` int(11) DEFAULT 0,
  `booked_count` varchar(50) DEFAULT NULL,
  `badge_text` varchar(100) DEFAULT NULL,
  `itinerary` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `image2_path` varchar(500) DEFAULT NULL,
  `image3_path` varchar(500) DEFAULT NULL,
  `collage_type` varchar(20) DEFAULT 'three',
  `is_active` tinyint(4) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency` varchar(10) DEFAULT '₱',
  `hotels` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `promo_start` date DEFAULT NULL,
  `promo_end` date DEFAULT NULL,
  `blocked_months` text DEFAULT NULL,
  `highlight_duration` int(11) DEFAULT 1,
  `blocked_dates` text DEFAULT NULL,
  `image_gallery` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flash_deals`
--

INSERT INTO `flash_deals` (`id`, `title`, `short_description`, `category`, `location`, `description`, `price`, `original_price`, `discount_percent`, `duration`, `group_size`, `best_season`, `rating`, `reviews`, `booked_count`, `badge_text`, `itinerary`, `inclusions`, `exclusions`, `image_path`, `image2_path`, `image3_path`, `collage_type`, `is_active`, `display_order`, `created_at`, `updated_at`, `currency`, `hotels`, `remarks`, `promo_start`, `promo_end`, `blocked_months`, `highlight_duration`, `blocked_dates`, `image_gallery`) VALUES
(1, '3D2N PPS Free & Easy', '', 'Beach activities', 'PUERTO PRINCESA', 'Experience the beauty of Puerto Princesa', 2894.00, 0.00, 0, '3D/2N', '2-20 pax', 'Year Round', 4.5, 12633, '400K+ booked', 'Citystate Asturias Hotel Pala...', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"Arrive at Puerto Princesa International Airport (NO MEALS). Meet and transfer to hotel to check in. Overnight Stay at the hotel.\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"Breakfast at the hotel. Enjoy whole day free time or join an optional tour. Overnight stay at the hotel.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"Breakfast at the hotel. Enjoy free time on own leisure until check out from hotel and transfer to Puerto Princesa International Airport.\",\"Remarks:\",\"*Please be at the hotel lobby at least 15 minutes prior to pick up time.\",\"*For SIC tours & transfers, please expect some delays due to the driver has to pick up other guests from different hotels too.\",\"*Please be on time for the pick up to avoid inconvenience with the other guests.\"]}]', '[\"Round trip airport transfers based on seat in coach via Puero Proncesa Internation Airport\",\"2D \\/ 3D Room Accommodation\",\"Daily Hotel Breakfast\",\"Complimentary travel insurance (up to 75 years old only)\"]', '[\"Any airfare & tax Puerto Princesa International Airport\",\"Any kind of tipping 2N \\/3D Room Accommodation\",\"Peak Season Surcharges (To be collected by VIA) VIEW HOTEL DETAILS\",\"Foreign Passport Surcharge (TBA upon confirmation of booking) Daily hotel breakfast\",\"Anything that is not specifically mentioned in the INCLUSIONS is on pax account\",\"Complimentary travel insurance (up to 75 years old only)\",\"Any kind of personal expenses or Optional tours\\/extra meals ordered by the guests\"]', 'uploads/1776004324_69dbace4dc499.jpg', 'uploads/1776004324_69dbace4dc60f.jpg', 'uploads/1776004324_69dbace4dc7b3.webp', 'three', 1, 1, '2026-03-28 02:29:29', '2026-05-22 05:24:59', '₱', '[{\"name\":\"Wow Hotel\",\"price\":500,\"stars\":3},{\"name\":\"Nuks Hotel\",\"price\":0},{\"name\":\"Loving Hotel\",\"price\":700,\"stars\":4}]', '', '2026-04-07', '2026-08-31', '1,2,3,9,10,11,12', 3, '2026-08-31, 2026-08-30', '[]'),
(4, '3D2N HKG Free & Easy (HKFE-3D)', '', 'Cultural tours', 'HONGKONG', 'Travel smarter with this affordable Hong Kong getaway', 1250.00, 0.00, 0, '07 April 2026 - 31 Aug 2026', '2-15 pax', 'Year Round', 4.7, 1732, '20K+ booked', 'O Hotel Hongkong', '[{\"day\":1,\"title\":\"Day 1: ARRIVAL IN HONG KONG\",\"activities\":[\"Arrive at Hong Kong International Airport. Proceed to the assigned meet up point then transfer to the hotel to check-in. Overnight in Hong Kong. (Standard check in time: 1500 hrs) Please take note:\",\"Airport transfers are available only from 8:00am to 10:00pm (Hong Kong Time).\",\"Airport transfer will only wait for 90 minutes after the given arrival flight time. Failure to show up in the designated post, pax need to go to the hotel on their own arrangement, and no refund for the transfer fee.\"]},{\"day\":2,\"title\":\"Day 2: SIC HALF DAY HONG KONG CITY TOUR (B)\",\"activities\":[\"Breakfast outside the hotel.\",\"Pick up and proceed to the compulsory city tour to visit the West Kowloon Art Park, free time taking photos of HK Palace Museum & Avenue of Stars, and two shopping stops (compulsory visit). Guests to have lunch on their own arrangement and account. In the afternoon, dismiss then enjoy free time on own leisure. Note:\",\"Guests are required to go back to their hotel on their own arrangement after city tour.\",\"Compulsory tipping of HKD50/pax will be collected after the city tour.\",\"The guests must join the city tour to be able to have FREE breakfast. If the guests will not join the Hong Kong city tour, FREE breakfast will be forfeited.\",\"Complimentary breakfast outside + city tour is fixed on 2nd day.\",\"2 shopping stops after city tour is compulsory. If skip shopping stop, surcharge of USD 30/pax will apply.\",\"Failure to join the compulsory city tour will incur charges that will be collected directly from the guests on the spot. If the guests refuse to pay, it will automatically be charged to the account of the agent.\"]},{\"day\":3,\"title\":\"Day 3: DEPART HONG KONG (B)\",\"activities\":[\"Breakfast outside the hotel.\",\"Enjoy free time on your own leisure until check out and transfer to Hong Kong International Airport. Please Take Note:\",\"Airport transfers are available only from 8:00am to 10:00pm (Hong Kong Time).\",\"Please be at the hotel lobby for at least 15 minutes before pick-up time\"]}]', '[\"Round trip airport transfers based on seat in coach via Hong Kong International Airport.\",\"2N \\/ 3D Room Accommodation\",\"Daily outside breakfast (breakfast coupon only)\",\"Compulsory half day Hong Kong city tour based on seat in coach with complimentary outside breakfast (fixed on day 2)\",\"Complimentary travel insurance (up to 75 years old only)\"]', '[\"Any visa (if required)\",\"Any airfare & taxes\",\"Compulsory tipping for driver & tour guide at HK$50\\/pax (or US$7\\/pax) to pay directly to the guide\",\"Midnight transfer surcharge between 2200 hrs to 0800 hrs (if there is any)\",\"Foreign passport, student and religious group surcharge: USD 30 per person\",\"Flight change notice less than 24 hours will incur surcharge of USD 15\\/pax for the re-arrangement of SIC transfer\",\"Weekend and peak season surcharges (to be collected manually by VIA)\",\"Anything that is not specifically mentioned in the INCLUSIONS is on pax account\",\"Any kind of personal expenses or Optional tours\\/extra meals ordered by the guests\"]', 'uploads/1776006331_69dbb4bb577b7.jpg', 'uploads/1776006331_69dbb4bb57913.jpg', 'uploads/1776006331_69dbb4bb579ad.jpg', 'three', 1, 4, '2026-03-28 02:29:29', '2026-05-22 05:25:17', '$', '[]', '', NULL, NULL, '', 1, '', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `flash_deals_fixed`
--

CREATE TABLE `flash_deals_fixed` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `original_price` decimal(10,2) DEFAULT 0.00,
  `discount_percent` int(11) DEFAULT 0,
  `duration` varchar(50) DEFAULT '3D/2N',
  `group_size` varchar(50) DEFAULT '2-15 pax',
  `best_season` varchar(100) DEFAULT 'Year Round',
  `rating` decimal(3,1) DEFAULT 0.0,
  `reviews` int(11) DEFAULT 0,
  `booked_count` varchar(50) DEFAULT NULL,
  `badge_text` varchar(100) DEFAULT NULL,
  `itinerary` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `hotels` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `blocked_dates` text DEFAULT NULL,
  `promo_start` date DEFAULT NULL,
  `promo_end` date DEFAULT NULL,
  `blocked_months` text DEFAULT NULL,
  `highlight_duration` int(11) DEFAULT 1,
  `image_path` varchar(500) DEFAULT NULL,
  `image2_path` varchar(500) DEFAULT NULL,
  `image3_path` varchar(500) DEFAULT NULL,
  `image_gallery` text DEFAULT NULL,
  `collage_type` varchar(20) DEFAULT 'three',
  `is_active` tinyint(4) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency` varchar(10) DEFAULT '₱'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flight_booking_data`
--

CREATE TABLE `flight_booking_data` (
  `id` int(11) NOT NULL,
  `destination_key` varchar(50) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `month_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flight_booking_settings`
--

CREATE TABLE `flight_booking_settings` (
  `id` int(11) NOT NULL,
  `destination_key` varchar(50) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `month_january_low` int(11) DEFAULT NULL,
  `month_january_high` int(11) DEFAULT NULL,
  `month_january_airline` varchar(100) DEFAULT NULL,
  `month_february_low` int(11) DEFAULT NULL,
  `month_february_high` int(11) DEFAULT NULL,
  `month_february_airline` varchar(100) DEFAULT NULL,
  `month_march_low` int(11) DEFAULT NULL,
  `month_march_high` int(11) DEFAULT NULL,
  `month_march_airline` varchar(100) DEFAULT NULL,
  `month_april_low` int(11) DEFAULT NULL,
  `month_april_high` int(11) DEFAULT NULL,
  `month_april_airline` varchar(100) DEFAULT NULL,
  `month_may_low` int(11) DEFAULT NULL,
  `month_may_high` int(11) DEFAULT NULL,
  `month_may_airline` varchar(100) DEFAULT NULL,
  `month_june_low` int(11) DEFAULT NULL,
  `month_june_high` int(11) DEFAULT NULL,
  `month_june_airline` varchar(100) DEFAULT NULL,
  `month_july_low` int(11) DEFAULT NULL,
  `month_july_high` int(11) DEFAULT NULL,
  `month_july_airline` varchar(100) DEFAULT NULL,
  `month_august_low` int(11) DEFAULT NULL,
  `month_august_high` int(11) DEFAULT NULL,
  `month_august_airline` varchar(100) DEFAULT NULL,
  `month_september_low` int(11) DEFAULT NULL,
  `month_september_high` int(11) DEFAULT NULL,
  `month_september_airline` varchar(100) DEFAULT NULL,
  `month_october_low` int(11) DEFAULT NULL,
  `month_october_high` int(11) DEFAULT NULL,
  `month_october_airline` varchar(100) DEFAULT NULL,
  `month_november_low` int(11) DEFAULT NULL,
  `month_november_high` int(11) DEFAULT NULL,
  `month_november_airline` varchar(100) DEFAULT NULL,
  `month_december_low` int(11) DEFAULT NULL,
  `month_december_high` int(11) DEFAULT NULL,
  `month_december_airline` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flight_booking_settings`
--

INSERT INTO `flight_booking_settings` (`id`, `destination_key`, `destination_name`, `month_january_low`, `month_january_high`, `month_january_airline`, `month_february_low`, `month_february_high`, `month_february_airline`, `month_march_low`, `month_march_high`, `month_march_airline`, `month_april_low`, `month_april_high`, `month_april_airline`, `month_may_low`, `month_may_high`, `month_may_airline`, `month_june_low`, `month_june_high`, `month_june_airline`, `month_july_low`, `month_july_high`, `month_july_airline`, `month_august_low`, `month_august_high`, `month_august_airline`, `month_september_low`, `month_september_high`, `month_september_airline`, `month_october_low`, `month_october_high`, `month_october_airline`, `month_november_low`, `month_november_high`, `month_november_airline`, `month_december_low`, `month_december_high`, `month_december_airline`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'baguio', 'Baguio', 5120, 11450, 'PAL, Cebu Pac', 4890, 10230, 'AirAsia', 4220, 8980, 'PAL, Cebu Pac', 5460, 12500, 'PAL', 4820, 10610, 'Cebu Pac, AirAsia', 6535, 14970, 'PAL, JAL', 8680, 18455, 'Peak Season', 7685, 16055, 'Multiple', 5555, 11730, 'Cebu Pac', 4990, 10450, 'AirAsia, PAL', 4780, 9890, 'Cebu Pac', 9250, 22800, 'Holiday Peak', 1, 0, '2026-03-23 08:19:26');

-- --------------------------------------------------------

--
-- Table structure for table `foreign_destinations`
--

CREATE TABLE `foreign_destinations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `location_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `duration` varchar(50) DEFAULT '4D/3N',
  `activities_count` int(11) DEFAULT 0,
  `group_size` varchar(50) DEFAULT '2-15 pax',
  `best_season` varchar(100) DEFAULT 'Year Round',
  `itinerary` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'asia',
  `badge_text` varchar(100) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dest_key` varchar(50) NOT NULL DEFAULT '',
  `image2_path` varchar(500) DEFAULT NULL,
  `image3_path` varchar(500) DEFAULT NULL,
  `collage_type` varchar(20) DEFAULT 'three',
  `location` varchar(200) DEFAULT NULL,
  `currency` varchar(10) DEFAULT '₱',
  `hotels` text DEFAULT NULL,
  `promo_start` date DEFAULT NULL,
  `promo_end` date DEFAULT NULL,
  `blocked_months` text DEFAULT NULL,
  `highlight_duration` int(11) DEFAULT 1,
  `blocked_dates` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `image_gallery` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `foreign_destinations`
--

INSERT INTO `foreign_destinations` (`id`, `name`, `country`, `city`, `location_name`, `description`, `short_description`, `price`, `duration`, `activities_count`, `group_size`, `best_season`, `itinerary`, `inclusions`, `exclusions`, `image_path`, `category`, `badge_text`, `display_order`, `is_active`, `created_at`, `updated_at`, `dest_key`, `image2_path`, `image3_path`, `collage_type`, `location`, `currency`, `hotels`, `promo_start`, `promo_end`, `blocked_months`, `highlight_duration`, `blocked_dates`, `remarks`, `image_gallery`) VALUES
(11, '3D2N HKG FREE 7 EASY (HKFE-3D)', 'HONGKONG', 'HONGKONG', NULL, 'Explore Hong Kong – adventure awaits around every corner!', '', 105.00, '3D2N', 10, '2-15 pax', '', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"ARRIVAL IN HONG KONG (No Meals)\",\"Arrive at Hong Kong International Airport. Proceed to the assigned meet-up point, then transfer to the hotel for check-in. \",\"Overnight in Hong Kong. (Standard check-in time: 15:00)\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"SIC HALF DAY HONG KONG CITY TOUR (B)\",\"Breakfast outside the hotel. Pick up and proceed to the compulsory city tour to visit West Kowloon Art Park, with free time for photos at the Hong Kong Palace Museum and Avenue of Stars, plus two shopping stops (compulsory). \",\"Guests to have lunch on their own arrangement and account. In the afternoon, guests are dismissed to enjoy free time at their own leisure.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"DEPART HONG KONG (B)\",\"Breakfast outside the hotel. Enjoy free time at your own leisure until check-out, then transfer to Hong Kong International Airport.\"]}]', '[]', '[]', 'uploads/1776043663_69dc468fe3e4b.jpg', 'city', 'O HOTEL HONGKONG', 1, 1, '2026-04-11 10:08:22', '2026-05-21 07:03:42', '3d2n-hkg-free-7-easy-(hkfe-3d)', '', '', 'three', 'HONGKONG', '$', '[]', NULL, NULL, '', 1, '', '', '[]'),
(12, '3D2N HKG FREE 7 EASY (HKFE-3D)', 'HONGKONG', 'HONGKONG', NULL, 'Uncover the energy and elegance of Hong Kong!', '', 115.00, '3D2N', 10, '2-15 pax', '', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"ARRIVAL IN HONG KONG (No Meals)\",\"Arrive at Hong Kong International Airport. Proceed to the assigned meet-up point, then transfer to the hotel for check-in. \",\"Overnight in Hong Kong. (Standard check-in time: 15:00)\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"SIC HALF DAY HONG KONG CITY TOUR (B)\",\"Breakfast outside the hotel. Pick up and proceed to the compulsory city tour to visit West Kowloon Art Park, with free time for photos at the Hong Kong Palace Museum and Avenue of Stars, plus two shopping stops (compulsory). \",\"Guests to have lunch on their own arrangement and account. In the afternoon, guests are dismissed to enjoy free time at their own leisure.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"DEPART HONG KONG (B)\",\"Breakfast outside the hotel. Enjoy free time at your own leisure until check-out, then transfer to Hong Kong International Airport.\"]}]', '[]', '[]', 'uploads/1779419566_6a0fc9ae5ed37.jpg', 'city', 'SILKA TSUEN WAN', 2, 1, '2026-04-12 14:46:05', '2026-05-22 03:12:46', '3d2n-hkg-free-7-easy-(hkfe-3d)', '', '', 'three', 'HONGKONG', '$', '[]', NULL, NULL, '', 4, '', '', '[]'),
(13, '4D3N HKG FREE & EASY (HKFE-4D)</', 'HONGKONG', 'HONGKONG', NULL, 'Discover Hong Kong\'s energy, elegance, and endless adventures daily!', '', 130.00, '4D3N', 10, '2-15 pax', 'Year Round', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"ARRIVAL IN HONG KONG (No Meals)\",\"Arrive at Hong Kong International Airport. Proceed to the assigned meet-up point, then transfer to the hotel for check-in. Overnight in Hong Kong. (Standard check-in time: 15:00)\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"SIC HALF DAY HONG KONG CITY TOUR (B)\",\"Breakfast outside the hotel. Pick up and proceed to the compulsory city tour to visit West Kowloon Art Park, with free time for photos at the Hong Kong Palace Museum and Avenue of Stars, plus two shopping stops (compulsory). \",\"Guests to have lunch on their own arrangement and account. In the afternoon, guests are dismissed to enjoy free time at their own leisure.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"FREE AND EASY (B)\",\"Breakfast outside the hotel. Enjoy free time at your own leisure or join an optional tour.\"]},{\"day\":4,\"title\":\"Day 4\",\"activities\":[\"DEPART HONG KONG (B)\",\"Breakfast outside the hotel. Enjoy free time at your own leisure until check-out, then transfer to Hong Kong International Airport.\"]}]', '[]', '[]', 'uploads/1779418517_6a0fc595aba1a.jpg', 'city', 'O HOTEL HONGKONG', 3, 1, '2026-04-13 01:01:30', '2026-05-22 02:55:17', '4d3n-hkg-free---easy-(hkfe-4d)<-', '', '', 'three', 'HONGKONG', '$', '[]', NULL, NULL, '', 1, '', '', '[]'),
(14, '3D2N EXPLORE SINGAPORE F&E', 'SINGAPORE', 'SINGAPORE', NULL, 'Uncover the magic of Singapore at your own pace!', '', 164.00, '3D/2N', 10, '2-10', '', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"ARRIVAL IN SINGAPORE (No Meals)\",\"Arrive at Changi International Airport. Meet the driver and transfer to the hotel for check-in. Waiting time: 1 hour after flight lands.\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"FREE AND EASY (No Meals)\",\"Enjoy free time on your own leisure or join an optional tour.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"DEPART SINGAPORE (No Meals)\",\"Enjoy free time on your own leisure until check-out, then transfer to Changi International Airport.\"]}]', '[]', '[]', 'uploads/1779419162_6a0fc81a9e67e.jpg', 'city', 'IBIS BUDGET SINGAPORE SELEGIE', 4, 1, '2026-04-13 01:04:21', '2026-05-22 03:06:02', '3d2n-explore-singapore-f-e', '', '', 'three', 'SINGAPORE', '$', '[]', NULL, NULL, '', 1, '', '', '[]'),
(15, '4D3N EXPLORE SINGAPORE F&E', 'SINGAPORE', 'SINGAPORE', NULL, 'Capture picture-perfect moments in one of Asia\'s most vibrant cities!', '', 265.00, '4D/3N', 10, '2-10', '', '[{\"day\":1,\"title\":\"Day 1\",\"activities\":[\"ARRIVAL IN SINGAPORE (No Meals)\",\"Arrive at Changi International Airport. \",\"Meet the driver and transfer to the hotel for check-in. \",\"Waiting time: 1.5 hours after flight lands.\"]},{\"day\":2,\"title\":\"Day 2\",\"activities\":[\"FREE AND EASY (B)\",\"Breakfast at the hotel. \",\"Enjoy free time on your own leisure or join an optional tour.\"]},{\"day\":3,\"title\":\"Day 3\",\"activities\":[\"FREE AND EASY (B)\",\"Breakfast at the hotel. \",\"Enjoy free time on your own leisure or join an optional tour.\"]},{\"day\":4,\"title\":\"Day 4\",\"activities\":[\"DEPART SINGAPORE (B)\",\"Breakfast at the hotel. \",\"Enjoy free time on your own leisure until check-out, then transfer to Changi International Airport.\"]}]', '[]', '[]', 'uploads/1776045418_69dc4d6ab4e35.jpg', 'city', 'IBIS BUDGET SINGAPORE SELEGIE', 5, 1, '2026-04-13 01:55:18', '2026-04-13 03:18:30', '4d3n-explore-singapore-f-e', '', '', 'three', 'SINGAPORE', '$', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `global_settings`
--

CREATE TABLE `global_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `global_settings`
--

INSERT INTO `global_settings` (`setting_key`, `setting_value`) VALUES
('visa_checklist', '[\"Valid Passport (6 months validity)\",\"Completed Application Form\",\"2x2 Photos\",\"Flight Itinerary\",\"Hotel Booking\",\"Bank Statement\"]'),
('visa_disclaimer', 'Completing the application does not provide a 100% guarantee of approval.');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_booking_data`
--

CREATE TABLE `hotel_booking_data` (
  `id` int(11) NOT NULL,
  `destination_key` varchar(50) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `month_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hotel_booking_settings`
--

CREATE TABLE `hotel_booking_settings` (
  `id` int(11) NOT NULL,
  `destination_key` varchar(50) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `month_january_low` int(11) DEFAULT NULL,
  `month_january_high` int(11) DEFAULT NULL,
  `month_january_hotel` varchar(100) DEFAULT NULL,
  `month_february_low` int(11) DEFAULT NULL,
  `month_february_high` int(11) DEFAULT NULL,
  `month_february_hotel` varchar(100) DEFAULT NULL,
  `month_march_low` int(11) DEFAULT NULL,
  `month_march_high` int(11) DEFAULT NULL,
  `month_march_hotel` varchar(100) DEFAULT NULL,
  `month_april_low` int(11) DEFAULT NULL,
  `month_april_high` int(11) DEFAULT NULL,
  `month_april_hotel` varchar(100) DEFAULT NULL,
  `month_may_low` int(11) DEFAULT NULL,
  `month_may_high` int(11) DEFAULT NULL,
  `month_may_hotel` varchar(100) DEFAULT NULL,
  `month_june_low` int(11) DEFAULT NULL,
  `month_june_high` int(11) DEFAULT NULL,
  `month_june_hotel` varchar(100) DEFAULT NULL,
  `month_july_low` int(11) DEFAULT NULL,
  `month_july_high` int(11) DEFAULT NULL,
  `month_july_hotel` varchar(100) DEFAULT NULL,
  `month_august_low` int(11) DEFAULT NULL,
  `month_august_high` int(11) DEFAULT NULL,
  `month_august_hotel` varchar(100) DEFAULT NULL,
  `month_september_low` int(11) DEFAULT NULL,
  `month_september_high` int(11) DEFAULT NULL,
  `month_september_hotel` varchar(100) DEFAULT NULL,
  `month_october_low` int(11) DEFAULT NULL,
  `month_october_high` int(11) DEFAULT NULL,
  `month_october_hotel` varchar(100) DEFAULT NULL,
  `month_november_low` int(11) DEFAULT NULL,
  `month_november_high` int(11) DEFAULT NULL,
  `month_november_hotel` varchar(100) DEFAULT NULL,
  `month_december_low` int(11) DEFAULT NULL,
  `month_december_high` int(11) DEFAULT NULL,
  `month_december_hotel` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel_booking_settings`
--

INSERT INTO `hotel_booking_settings` (`id`, `destination_key`, `destination_name`, `month_january_low`, `month_january_high`, `month_january_hotel`, `month_february_low`, `month_february_high`, `month_february_hotel`, `month_march_low`, `month_march_high`, `month_march_hotel`, `month_april_low`, `month_april_high`, `month_april_hotel`, `month_may_low`, `month_may_high`, `month_may_hotel`, `month_june_low`, `month_june_high`, `month_june_hotel`, `month_july_low`, `month_july_high`, `month_july_hotel`, `month_august_low`, `month_august_high`, `month_august_hotel`, `month_september_low`, `month_september_high`, `month_september_hotel`, `month_october_low`, `month_october_high`, `month_october_hotel`, `month_november_low`, `month_november_high`, `month_november_hotel`, `month_december_low`, `month_december_high`, `month_december_hotel`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'baguio', 'Baguio', 3800, 8500, 'Peak Season', 3000, 6800, 'Flower Festival', 2500, 5800, '3-star, 4-star', 3200, 7500, '4-star, Resort', 2800, 6200, '3-star, Boutique', 2200, 4800, 'Budget, 3-star', 2000, 4500, 'Budget Friendly', 2100, 4700, 'Budget, 3-star', 2300, 5000, '3-star', 2600, 5500, '3-star, 4-star', 2800, 6000, '4-star', 4500, 12000, 'Peak Season', 1, 0, '2026-03-23 08:19:26');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaigns`
--

CREATE TABLE `marketing_campaigns` (
  `id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `audience` varchar(50) DEFAULT 'all',
  `blocks` text DEFAULT NULL,
  `body` text DEFAULT NULL,
  `sent_count` int(11) DEFAULT 0,
  `open_count` int(11) DEFAULT 0,
  `click_count` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'sent',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_campaigns`
--

INSERT INTO `marketing_campaigns` (`id`, `template_id`, `subject`, `audience`, `blocks`, `body`, `sent_count`, `open_count`, `click_count`, `status`, `scheduled_at`, `created_at`) VALUES
(17, NULL, 'Exclusive for you!!!!!!', 'all', NULL, '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a limited time only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=17&url=\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=17&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 3, 4, 4, 'sent', NULL, '2026-05-07 08:26:17'),
(18, NULL, 'TRAVEL', 'all', NULL, '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a limited time only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=18&url=\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=18&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 3, 0, 0, 'sent', NULL, '2026-05-07 08:34:57'),
(19, NULL, 'This is for everyone!', 'all', NULL, '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a limited time only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=19&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 10 7.5/admin/api/track_click.php?cid=19&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 3, 2, 2, 'sent', NULL, '2026-05-08 00:56:47'),
(20, NULL, '✨ Boracay Dreams: Your Tropical Escape Awaits', 'all', '[{\"type\":\"header\",\"id\":\"block-1779087162217toflu\",\"custom_label\":\"try\",\"text\":\"Hey Dream Travel and Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1779087162219s736y\",\"custom_label\":\"<i class=\",\"text\":\"Hi there {first_name},\\n\\nDreaming of a perfect island getaway? \\ud83c\\udf0a\\u2728\\nNow\\u2019s your chance to travel for LESS!\\n\\nAt Hey Dream Travel and Tours, we\\u2019re offering exclusive promo packages for Boracay and Puerto Princesa \\u2014 perfect for your next vacation.\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"text\",\"id\":\"block-1779087162219vpiz7\",\"custom_label\":\"<i class=\",\"text\":\"\\ud83c\\udf81 LIMITED-TIME OFFER!\\n\\n\\u2714 Reserve now with LOW down payment\\n\\u2714 Flexible travel dates available\\n\\u2714 Special group discounts (4+ persons)\\n\\n\\ud83d\\udc49 Promo valid for a Consider alternative wording only \\u2014 book early to secure your slot!\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779087162220yrc3s\",\"custom_label\":\"<i class=\"},{\"type\":\"image\",\"id\":\"block-1779087162222yeu99\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udfdd\\ufe0f Boracay Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b14,999 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"image\",\"id\":\"block-17790871622235fw2w\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udf34 Puerto Princesa Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b15,499 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779087162225j4k0w\",\"custom_label\":\"<i class=\"},{\"type\":\"button\",\"id\":\"block-1779087162226kw5pz\",\"custom_label\":\"<i class=\",\"text\":\"Book Now\",\"link\":\"\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"footer\",\"id\":\"block-1779087162227pzyjd\",\"custom_label\":\"<i class=\",\"text\":\"You received this email because you expressed interest in travel with HeyDream.\",\"align\":\"center\"}]', '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a Consider alternative wording only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 11.5/admin/api/track_click.php?cid=20&url=&e={{email}}\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 11.5/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 11.5/admin/api/track_click.php?cid=20&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 8, 1, 1, 'sent', NULL, '2026-05-18 06:52:52'),
(21, NULL, '☀️ Sizzle into Summer: Hot Beach Deals Just for You', 'all', '[{\"type\":\"header\",\"id\":\"block-1779698830544e2ps7\",\"custom_label\":\"try\",\"text\":\"Hey Dream Travel and Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1779698830544bqzpv\",\"custom_label\":\"<i class=\",\"text\":\"Hi there {first_name},\\n\\nDreaming of a perfect island getaway? \\ud83c\\udf0a\\u2728\\nNow\\u2019s your chance to travel for LESS!\\n\\nAt Hey Dream Travel and Tours, we\\u2019re offering exclusive promo packages for Boracay and Puerto Princesa \\u2014 perfect for your next vacation.\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"text\",\"id\":\"block-1779698830545y7kq7\",\"custom_label\":\"<i class=\",\"text\":\"\\ud83c\\udf81 LIMITED-TIME OFFER!\\n\\n\\u2714 Reserve now with LOW down payment\\n\\u2714 Flexible travel dates available\\n\\u2714 Special group discounts (4+ persons)\\n\\n\\ud83d\\udc49 Promo valid for a Consider alternative wording only \\u2014 book early to secure your slot!\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779698830545h54ia\",\"custom_label\":\"<i class=\"},{\"type\":\"image\",\"id\":\"block-177969883054608n7c\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udfdd\\ufe0f Boracay Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b14,999 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"image\",\"id\":\"block-1779698830546kuk23\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udf34 Puerto Princesa Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b15,499 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779698830547i2gck\",\"custom_label\":\"<i class=\"},{\"type\":\"button\",\"id\":\"block-1779698830547toqim\",\"custom_label\":\"<i class=\",\"text\":\"Book Now\",\"link\":\"\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"footer\",\"id\":\"block-1779698830548t7q98\",\"custom_label\":\"<i class=\",\"text\":\"You received this email because you expressed interest in travel with HeyDream.\",\"align\":\"center\"},{\"type\":\"header\",\"id\":\"msg-block-17796987388609008j\",\"custom_label\":\"\",\"text\":\"HeyDream Travel & Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"msg-block-1779698738861saus2\",\"custom_label\":\"\",\"text\":\"Dear Valued Customer,\\n\\nWe are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.\\n\\nLet us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.\",\"size\":\"16\",\"color\":\"#334155\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"button\",\"id\":\"msg-block-1779698738863kopuz\",\"custom_label\":\"\",\"text\":\"Explore Packages\",\"link\":\"https:\\/\\/heydreamtravel.kesug.com\\/\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"divider\",\"id\":\"msg-block-1779698738864vp3f5\",\"custom_label\":\"\"},{\"type\":\"footer\",\"id\":\"msg-block-1779698738864yqijf\",\"custom_label\":\"\",\"text\":\"You received this email because you are a registered customer or partner of HeyDream Travel and Tours.\",\"align\":\"center\"}]', '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a Consider alternative wording only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=21&url=&e={{email}}\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=21&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div><div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>HeyDream Travel & Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #334155; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Dear Valued Customer,<br />\n<br />\nWe are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.<br />\n<br />\nLet us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.</p></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=21&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Explore Packages</a></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you are a registered customer or partner of HeyDream Travel and Tours.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=21&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 8, 0, 0, 'sent', NULL, '2026-05-25 08:47:42'),
(22, NULL, '🍹 Sunset & Sand: Grab Our Boracay Promo Now!', 'all', '[{\"type\":\"header\",\"id\":\"block-17797553514273o28x\",\"custom_label\":\"try\",\"text\":\"Hey Dream Travel and Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-177975535142970afp\",\"custom_label\":\"<i class=\",\"text\":\"Hi there {first_name},\\n\\nDreaming of a perfect island getaway? \\ud83c\\udf0a\\u2728\\nNow\\u2019s your chance to travel for LESS!\\n\\nAt Hey Dream Travel and Tours, we\\u2019re offering exclusive promo packages for Boracay and Puerto Princesa \\u2014 perfect for your next vacation.\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"text\",\"id\":\"block-1779755351431fdslq\",\"custom_label\":\"<i class=\",\"text\":\"\\ud83c\\udf81 LIMITED-TIME OFFER!\\n\\n\\u2714 Reserve now with LOW down payment\\n\\u2714 Flexible travel dates available\\n\\u2714 Special group discounts (4+ persons)\\n\\n\\ud83d\\udc49 Promo valid for a Consider alternative wording only \\u2014 book early to secure your slot!\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-177975535143270on5\",\"custom_label\":\"<i class=\"},{\"type\":\"image\",\"id\":\"block-1779755351432qkji8\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udfdd\\ufe0f Boracay Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b14,999 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"image\",\"id\":\"block-17797553514334zssw\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udf34 Puerto Princesa Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b15,499 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779755351433tkjlj\",\"custom_label\":\"<i class=\"},{\"type\":\"button\",\"id\":\"block-1779755351434jjufc\",\"custom_label\":\"<i class=\",\"text\":\"Book Now\",\"link\":\"\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"footer\",\"id\":\"block-1779755351435pdi7h\",\"custom_label\":\"<i class=\",\"text\":\"You received this email because you expressed interest in travel with HeyDream.\",\"align\":\"center\"},{\"type\":\"header\",\"id\":\"block-17797553514365jk8i\",\"custom_label\":\"\",\"text\":\"HeyDream Travel & Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1779755351437mramd\",\"custom_label\":\"\",\"text\":\"Dear Valued Customer,\\n\\nWe are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.\\n\\nLet us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.\",\"size\":\"16\",\"color\":\"#334155\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"button\",\"id\":\"block-1779755351438paw9m\",\"custom_label\":\"\",\"text\":\"Explore Packages\",\"link\":\"https:\\/\\/heydreamtravel.kesug.com\\/\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"divider\",\"id\":\"block-1779755351439qya2p\",\"custom_label\":\"\"},{\"type\":\"footer\",\"id\":\"block-1779755351441xihg9\",\"custom_label\":\"\",\"text\":\"You received this email because you are a registered customer or partner of HeyDream Travel and Tours.\",\"align\":\"center\"}]', '<div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>Hey Dream Travel and Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Hi there {first_name},<br />\n<br />\nDreaming of a perfect island getaway? 🌊✨<br />\nNow’s your chance to travel for LESS!<br />\n<br />\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.</p></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #64748b; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>🎁 LIMITED-TIME OFFER!<br />\n<br />\n✔ Reserve now with LOW down payment<br />\n✔ Flexible travel dates available<br />\n✔ Special group discounts (4+ persons)<br />\n<br />\n👉 Promo valid for a Consider alternative wording only — book early to secure your slot!</p></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🏝️ Boracay Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱4,999 per person</div></div><div style=\'padding: 20px 30px; text-align: center;\'><div style=\'width: 72%; margin: 0 auto;\'><img src=\'https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\' style=\'width: 100%; border-radius: 15px; display: block;\' alt=\'Promo\'></div><div style=\'color: #64748b; font-size: 14px; font-weight: 400; line-height: 1.5; padding: 10px; text-align: center;\'>🌴 Puerto Princesa Promo Deals<br />\n3 Days / 2 Nights Package<br />\n💸 STARTING AT: ₱5,499 per person</div></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=22&url=&e={{email}}\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Book Now</a></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you expressed interest in travel with HeyDream.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=22&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div><div style=\'background: #003580; padding: 20px; text-align: center;\'><h2 style=\'color: #ffffff; margin: 0; font-family: sans-serif;\'>HeyDream Travel & Tours</h2></div><div style=\'padding: 20px 30px; text-align: left;\'><p style=\'color: #334155; font-size: 16px; font-weight: 400; line-height: 1.6; font-family: sans-serif; margin: 0;\'>Dear Valued Customer,<br />\n<br />\nWe are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.<br />\n<br />\nLet us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.</p></div><div style=\'text-align: center; padding: 20px 30px;\'><a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=22&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'display: inline-block; background: #003580; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-family: sans-serif;\'>Explore Packages</a></div><div style=\'padding: 0 30px;\'><hr style=\'border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;\'></div><div style=\'padding: 30px; text-align: center; background: #f8fafc;\'>\n                    <p style=\'font-size: 12px; color: #94a3b8; font-family: sans-serif; margin: 0;\'>You received this email because you are a registered customer or partner of HeyDream Travel and Tours.</p>\n                    <div style=\'margin-top: 15px; font-size: 12px; font-family: sans-serif;\'>\n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/unsubscribe.php\' style=\'color: #003580; text-decoration: none;\'>Unsubscribe</a> | \n                        <a href=\'http://localhost/HeyDream Website - anti gravity 12.3-20260525T033602Z-3-001/HeyDream Website - anti gravity 12.4/admin/api/track_click.php?cid=22&url=https%3A%2F%2Fheydreamtravel.kesug.com%2F&e={{email}}\' style=\'color: #003580; text-decoration: none;\'>View Website</a>\n                    </div>\n                </div>', 8, 0, 0, 'sent', NULL, '2026-05-26 00:30:25'),
(23, NULL, '🕶️ Summer Vibes: Your Beach Holiday is One Click Away!', 'inquiries', '[{\"type\":\"header\",\"id\":\"block-1779755443784xp00a\",\"custom_label\":\"try\",\"text\":\"Hey Dream Travel and Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1779755443786s7cci\",\"custom_label\":\"<i class=\",\"text\":\"Hi there {first_name},\\n\\nDreaming of a perfect island getaway? \\ud83c\\udf0a\\u2728\\nNow\\u2019s your chance to travel for LESS!\\n\\nAt Hey Dream Travel and Tours, we\\u2019re offering exclusive promo packages for Boracay and Puerto Princesa \\u2014 perfect for your next vacation.\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"text\",\"id\":\"block-1779755443787l4twv\",\"custom_label\":\"<i class=\",\"text\":\"\\ud83c\\udf81 LIMITED-TIME OFFER!\\n\\n\\u2714 Reserve now with LOW down payment\\n\\u2714 Flexible travel dates available\\n\\u2714 Special group discounts (4+ persons)\\n\\n\\ud83d\\udc49 Promo valid for a Consider alternative wording only \\u2014 book early to secure your slot!\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779755443787ai4y1\",\"custom_label\":\"<i class=\"},{\"type\":\"image\",\"id\":\"block-1779755443788f40nx\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udfdd\\ufe0f Boracay Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b14,999 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"image\",\"id\":\"block-1779755443788f0b3a\",\"custom_label\":\"<i class=\",\"url\":\"https:\\/\\/www.image2url.com\\/r2\\/default\\/images\\/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"\\ud83c\\udf34 Puerto Princesa Promo Deals\\n3 Days \\/ 2 Nights Package\\n\\ud83d\\udcb8 STARTING AT: \\u20b15,499 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1779755443789vyshq\",\"custom_label\":\"<i class=\"},{\"type\":\"button\",\"id\":\"block-1779755443791vnk19\",\"custom_label\":\"<i class=\",\"text\":\"Book Now\",\"link\":\"\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"footer\",\"id\":\"block-177975544379264r21\",\"custom_label\":\"<i class=\",\"text\":\"You received this email because you expressed interest in travel with HeyDream.\",\"align\":\"center\"},{\"type\":\"header\",\"id\":\"block-17797554437963ymc9\",\"custom_label\":\"\",\"text\":\"HeyDream Travel & Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1779755443797dr0mv\",\"custom_label\":\"\",\"text\":\"Dear Valued Customer,\\n\\nWe are excited to share the latest travel updates from HeyDream! As we welcome the new season, we have curated exclusive, high-value packages for flights, luxury cruises, and hotels tailored just for you.\\n\\nLet us help you turn your next travel dream into a reality. Click the button below to browse our latest custom packages.\",\"size\":\"16\",\"color\":\"#334155\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"button\",\"id\":\"block-1779755443800sb3jq\",\"custom_label\":\"\",\"text\":\"Explore Packages\",\"link\":\"https:\\/\\/heydreamtravel.kesug.com\\/\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"divider\",\"id\":\"block-1779755443802uhhqx\",\"custom_label\":\"\"},{\"type\":\"footer\",\"id\":\"block-17797554438051n450\",\"custom_label\":\"\",\"text\":\"You received this email because you are a registered customer or partner of HeyDream Travel and Tours.\",\"align\":\"center\"}]', NULL, 0, 0, 0, 'sending', NULL, '2026-05-26 00:31:18');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_templates`
--

CREATE TABLE `marketing_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `hero_title` varchar(200) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `hero_image` varchar(500) DEFAULT NULL,
  `cta_buttons` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_templates`
--

INSERT INTO `marketing_templates` (`id`, `name`, `subject`, `hero_title`, `body`, `hero_image`, `cta_buttons`, `created_at`) VALUES
(6, 'Local Destination Promo', NULL, NULL, '[{\"type\":\"header\",\"id\":\"block-1778141146531cdev4\",\"custom_label\":\"try\",\"text\":\"Hey Dream Travel and Tours\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"align\":\"center\"},{\"type\":\"text\",\"id\":\"block-1778141146532fkyqa\",\"custom_label\":\"<i class=\",\"text\":\"Hi there {first_name},\\n\\nDreaming of a perfect island getaway? 🌊✨\\nNow’s your chance to travel for LESS!\\n\\nAt Hey Dream Travel and Tours, we’re offering exclusive promo packages for Boracay and Puerto Princesa — perfect for your next vacation.\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"text\",\"id\":\"block-1778141146534xlhxp\",\"custom_label\":\"<i class=\",\"text\":\"🎁 LIMITED-TIME OFFER!\\n\\n✔ Reserve now with LOW down payment\\n✔ Flexible travel dates available\\n✔ Special group discounts (4+ persons)\\n\\n👉 Promo valid for a limited time only — book early to secure your slot!\",\"size\":\"16\",\"color\":\"#64748b\",\"align\":\"left\",\"weight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1778141146534xxhys\",\"custom_label\":\"<i class=\"},{\"type\":\"image\",\"id\":\"block-1778141146535lk8vs\",\"custom_label\":\"<i class=\",\"url\":\"https://www.image2url.com/r2/default/images/1778140877778-1893a49d-0bd4-4ce2-ab54-618e7946e1b3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"🏝️ Boracay Promo Deals\\n3 Days / 2 Nights Package\\n💸 STARTING AT: ₱4,999 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"image\",\"id\":\"block-17781411465361flje\",\"custom_label\":\"<i class=\",\"url\":\"https://www.image2url.com/r2/default/images/1778141060087-b30aa2ae-0fa7-4b1c-a8c6-6fed02b41db3.jpg\",\"width\":\"72\",\"radius\":\"15\",\"align\":\"center\",\"caption\":\"🌴 Puerto Princesa Promo Deals\\n3 Days / 2 Nights Package\\n💸 STARTING AT: ₱5,499 per person\",\"capSize\":\"14\",\"capColor\":\"#64748b\",\"capWeight\":\"400\"},{\"type\":\"divider\",\"id\":\"block-1778141146538283v0\",\"custom_label\":\"<i class=\"},{\"type\":\"button\",\"id\":\"block-17781411465417qz7g\",\"custom_label\":\"<i class=\",\"text\":\"Book Now\",\"link\":\"\",\"color\":\"#ffffff\",\"bg\":\"#003580\",\"size\":\"16\",\"padding\":\"12\",\"width\":\"auto\",\"weight\":\"600\",\"align\":\"center\"},{\"type\":\"footer\",\"id\":\"block-17781411465424l43u\",\"custom_label\":\"<i class=\",\"text\":\"You received this email because you expressed interest in travel with HeyDream.\",\"align\":\"center\"}]', NULL, NULL, '2026-05-07 08:06:32');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_tracking`
--

CREATE TABLE `marketing_tracking` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `action` enum('open','click') NOT NULL,
  `url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_tracking`
--

INSERT INTO `marketing_tracking` (`id`, `campaign_id`, `email`, `action`, `url`, `created_at`) VALUES
(1, 20, 'rebancossteven35@gmail.com', 'click', '', '2026-05-18 07:11:55');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `destination_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `duration` varchar(50) DEFAULT '',
  `price` decimal(10,2) DEFAULT 0.00,
  `activities_count` int(11) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES
(10, 'rebancossteven35@gmail.com', 'ef0a4913b43e17d6391200a9489413b85f52a2b93e202f765733212b1f6b7368', '2026-03-30 08:29:52', 0, '2026-03-30 05:29:52');

-- --------------------------------------------------------

--
-- Table structure for table `reported_issues`
--

CREATE TABLE `reported_issues` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `severity` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_itinerary`
--

CREATE TABLE `service_itinerary` (
  `id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `day_number` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_itinerary`
--

INSERT INTO `service_itinerary` (`id`, `service_id`, `day_number`, `title`, `description`, `created_at`) VALUES
(7, 4, 1, 'day 1', 'fgj fhj', '2026-05-12 04:01:28'),
(8, 4, 2, 'day 2', 'aewcvawdvsw', '2026-05-12 04:01:28'),
(34, 6, 1, 'sdcs', 'sdcs', '2026-05-22 03:29:11'),
(35, 6, 2, 'sdcsd', 'sdcs', '2026-05-22 03:29:11'),
(36, 6, 3, 'sdcs', 'sdcdc', '2026-05-22 03:29:11'),
(37, 5, 1, 'SC', 'SDcS', '2026-05-22 05:34:18'),
(38, 5, 2, 'SDc', 'ASCSc', '2026-05-22 05:34:18'),
(39, 8, 1, 'ascasc', '', '2026-06-11 03:23:01');

-- --------------------------------------------------------

--
-- Table structure for table `site_services`
--

CREATE TABLE `site_services` (
  `id` int(11) NOT NULL,
  `service_type` enum('cruise','flight','premium','experience') NOT NULL,
  `title` varchar(200) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `service_code` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `badge_text` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `required_documents` text DEFAULT NULL,
  `travel_requirements` text DEFAULT NULL,
  `cancellation_policy` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `image_gallery` text DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT 'fas fa-ship',
  `price` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT '₱',
  `duration` varchar(100) DEFAULT NULL,
  `available_slots` int(11) DEFAULT 0,
  `booking_deadline` date DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status_text` varchar(50) DEFAULT 'Available',
  `is_active` tinyint(4) DEFAULT 1,
  `is_featured` tinyint(4) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_services`
--

INSERT INTO `site_services` (`id`, `service_type`, `title`, `short_description`, `service_code`, `category`, `tags`, `badge_text`, `description`, `full_description`, `highlights`, `inclusions`, `exclusions`, `required_documents`, `travel_requirements`, `cancellation_policy`, `terms_conditions`, `image_gallery`, `amenities`, `featured_image`, `icon_class`, `price`, `currency`, `duration`, `available_slots`, `booking_deadline`, `departure_date`, `return_date`, `status_text`, `is_active`, `is_featured`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'premium', 'Luxury Vietnam & Da Nang Escape', NULL, '', '', '', '', '', '', '3 Days 2 Nights premium stay\r\nBeachfront 4-5 star hotel\r\nGuided city & cultural tours\r\nBana Hills & Golden Bridge visit\r\nAirport transfers included\r\nDaily breakfast', 'vvv', 'vvv', 'vvv', '', '', '', '[]', '', 'uploads/service_f_1778187658.jpg', 'fas fa-crown', 25988.00, '₱', '3D/2N', 0, NULL, NULL, NULL, 'Available', 1, 0, 0, '2026-05-07 21:00:26', '2026-05-22 05:35:01'),
(2, 'flight', 'Manila to Da Nang – Central Vietnam Gateway', NULL, '', '', '', 'Multiple Destinations', '', '', 'Baggage Allowance: Included (standard)\r\nClass: Economy / Business\r\nMeals: Available on request', 'vvvv', 'cvvv', 'Paasport', '', '', '', '[]', '', 'uploads/service_f_1778188769.jpg', 'fas fa-plane', 145.00, '₱', '2D/1N', 0, NULL, NULL, NULL, 'Available', 1, 0, 0, '2026-05-07 21:13:43', '2026-05-22 05:34:41'),
(3, 'experience', 'Phong Nha Underground', NULL, 'VN-PN-CAVE-003', '', '', '', '', '', 'Boat + Trek + Swim Combo\r\nHidden Chambers\r\nAncient Cham Scripts\r\nUnderground River Beach\r\nAbsolute Darkness & Silence', 'vvv', 'sss', 'vvv', '', '', '', '[\"uploads\\/service_g_1_1778459465.jpg\",\"uploads\\/service_g_2_1778459635.jpg\",\"uploads\\/service_g_3_1778460156.jpg\",\"uploads\\/service_g_4_1778460206.jpg\",\"uploads\\/service_g_1778556114_5.jpg\"]', NULL, 'uploads/service_f_1778188850.jpg', 'fas fa-star', 2500.00, '₱', '1 DAY ONLY', 0, NULL, NULL, NULL, 'Available', 1, 0, 0, '2026-05-07 21:19:29', '2026-05-14 03:34:16'),
(5, 'flight', 'Japan Airlines', NULL, 'F-0001', '', '', '', '', '', 'sdv', 'sdz', 'ASC', '', '', '', '', '[]', '', 'uploads/service_f_1779419951.jpg', 'fas fa-plane', 10000.00, '₱', '3D/2N', 0, '2026-05-19', '2026-05-20', '2026-05-20', 'Limited Slots', 1, 0, 0, '2026-05-18 01:16:12', '2026-05-22 05:34:18'),
(6, 'premium', 'Marina Bay Sands', NULL, 'MBS-2026', 'Hotel Package', 'romantic vacation', 'PREMIUM EXPERIENCE', '', 'wsd', 'Complimentary Buffet Breakfast\r\nFree Airport Pickup & Drop-off\r\nRooftop Fine Dining Experience\r\nFree Access to Swimming Pool & Fitness Gym\r\nRelaxing Spa & Wellness Session\r\nFree Wi-Fi & Smart Room Features\r\nGuided Night City Tour', 'sdcs', 'dcsdc', '', '', '', '', '[\"uploads\\/service_g_1779067491_0.jpg\",\"uploads\\/service_g_1779067505_0.png\",\"uploads\\/service_g_1779067883_0.jpg\",\"uploads\\/service_g_1779067883_1.png\",\"uploads\\/service_g_1779067883_2.jpg\",\"uploads\\/service_g_1779067883_3.jpg\",\"uploads\\/service_g_1779067883_4.jpg\",\"uploads\\/service_g_1779067883_5.jpg\",\"uploads\\/service_g_1779068682_0.jpg\"]', 'WI-FI \r\nFood\r\nPool\r\nGame Room', 'uploads/service_f_1779420551.jpg', 'fas fa-hotel', 18999.00, '₱', '3D/2N', 0, '2026-05-20', '2026-05-28', '2026-05-31', 'Available', 1, 0, 0, '2026-05-18 01:17:36', '2026-05-22 03:29:11'),
(7, 'experience', 'Bali Tropical Escape', NULL, 'ID-BL-002', 'Adventure', 'Summer, Beach', 'HOT DEAL', '', '', 'Roundtrip Airport Transfers\r\nHotel Accommodation with Breakfast\r\nVisit Tanah Lot Temple\r\nUbud Cultural Tour\r\nBali Swing Experience\r\nIsland Hopping Adventure', '', '', '', '', '', '', '[]', '', 'uploads/service_f_1779429048.jpg', 'fas fa-umbrella-beach', 100.00, '$', '2D1N', 0, NULL, NULL, NULL, 'Available', 1, 0, 2, '2026-05-22 05:50:48', '2026-05-22 05:51:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `provider` enum('email','google','facebook','apple') DEFAULT 'email',
  `provider_id` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `marketing_consent` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `title`, `email`, `password`, `phone`, `dob`, `country`, `profile_pic`, `provider`, `provider_id`, `email_verified`, `verification_token`, `is_active`, `created_at`, `updated_at`, `marketing_consent`) VALUES
(2, 'Steven Rebancos', '', 'rebancossteven35@gmail.com', '$2y$10$GxuJuUtpxP.stJPhOSXGV.EsCyNCSN4opDVzEcruDNmJQiQkAAZXy', '09919612457', '0000-00-00', '', 'images/profiles/user_2_1777279274.jpg', 'google', '103132773064888483350', 1, NULL, 1, '2026-04-01 18:15:03', '2026-06-11 05:36:58', 1),
(3, 'John Kostya Asuela', NULL, 'asuelajohnkostya@gmail.com', '$2y$10$mtRsiYJDEslAWU534p1byea1tmtjDPH5Q.4Shs8G1i8JRrFiuNjZ6', '09121597845', NULL, NULL, NULL, 'email', NULL, 0, '30756736abad42cc2303eddcf80a8ce6a0d21c2068d25a021d2e32e94590ca81', 1, '2026-04-01 19:30:59', '2026-05-18 06:47:39', 1),
(4, 'Mark Steve Socnaber', NULL, 'stevenrebancos860@gmail.com', '$2y$10$Dkrjpz0bMnw597IjkS4OzehzWMLoLy4IkyIIqgpBBqMUJCAIsC5fa', '09121597845', NULL, NULL, NULL, 'google', '102253844366003475698', 0, 'a92b28575bc5d03bc7261ec436d9d971d56afbfbb2e7715b1786f193bee1ee64', 1, '2026-04-01 19:33:57', '2026-05-25 04:00:29', 1),
(5, 'Tibenn', NULL, 'sfrebancos@paterostechnologicalcollege.edu.php', '$2y$10$UaJTrp9MW9pgy6upPNOg.OxuPGK6HYr8xpALViI1jC4qBToDMgL.C', '09919612457', NULL, NULL, NULL, 'email', NULL, 0, 'e5847d243105a2d7e3b61234a9d4a79a61c3547a173e221a730cd24482f5d90a', 1, '2026-04-01 19:40:52', '2026-05-18 06:47:39', 1),
(6, 'Angela Lou Dela Cruz', '', 'angelaloudelacruz7@gmail.com', '$2y$10$9pddPqsf9Uf9ocdBFnqvKOX.UdLv3lPSk4oe04pn.LCUEcCg2xKqq', '09463435820', '0000-00-00', '', 'images/profiles/user_6_1778614317.jpg', 'google', '105239986787392675136', 0, '0aa9d9ea2485349895b13bc4448c31d4167813b7fcf173186575dca734786a14', 1, '2026-05-12 19:29:19', '2026-05-22 06:36:46', 1),
(16, '', 'Ms', 'agdelacruz@paterostechnologicalcollege.edu.ph', NULL, '+639079128442', '2003-11-05', 'Philippines', NULL, 'google', '108742890139848989640', 1, '3dda8e5a1da0d8c5dcad4e0363adff3ead8e15aa1278deab55bbb0698b70c083', 1, '2026-05-26 02:55:24', '2026-05-26 02:56:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(1, 2, '8f63161fb7cf601fac272e420ea647ca21eead5fe2bb0abf4d95c4251e38e866', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-14 15:14:34', '2026-04-13 13:14:34'),
(6, 2, '597f25b58145e820df75460680a814ae8e628f3cad863398b6eb6678b99cba64', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 20:16:34', '2026-04-01 18:16:34'),
(7, 2, 'beb77b07a235ca6dd146d105f9fc8f302c188d2aa69829ceed2887b4d61c4bac', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-02 20:20:21', '2026-04-01 18:20:21'),
(8, 4, '380440a91794c1be5da8ff4f5169d1f5d3502fe46e6daa2131a75189f8361f6c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-02 21:43:13', '2026-04-01 19:43:13'),
(9, 3, '5c8fd6d4ca31eca3a773ff5dcd89c7245c8a8133fd9493aa40441d78faa77c74', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 11:02:40', '2026-04-03 09:02:40'),
(10, 2, 'edbf8886414827ec4554b915cbe98b15b2762eafda40aeaf3c493e85290e9ed5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:00:13', '2026-04-04 03:00:13'),
(11, 2, 'b860361d7b672e22ad5a227d85d6643c97e37d141a15dd166075b04ecba08f26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:32:50', '2026-04-04 03:32:50'),
(12, 2, 'c07d2136b9fc85172d66d3e3c13e52ca7417fdbeb17dbf3a7680b9620b6cf3e1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 06:39:13', '2026-04-04 04:39:13'),
(14, 2, '4a9f61e9241547be4fab4d28955c0ca8209c768aa31e832c559d111f6d53540c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-12 11:59:21', '2026-04-11 09:59:21'),
(15, 2, '7cc1c982cd3ac46a43415e18a486ef34f94b8f4ed614ef2f7135630ff1683256', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-04-12 13:01:13', '2026-04-11 11:01:13'),
(16, 2, '135832ad8b809b8d6471eeda3fea7a7d11aa84e951eb9193b463947c0df763b4', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-04-12 13:32:10', '2026-04-11 11:32:10'),
(17, 2, '75e8dc2d20de8306ecbde253dd125dad008ad0af5698d5e92ea34fcc67539e3d', '112.210.225.10', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-13 09:40:45', '2026-04-12 13:40:44'),
(18, 2, 'cc4a9e0554aeae221dc9aef317367d89eab84929de59e431090c9144ae0ccf58', '112.210.225.10', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-13 09:54:25', '2026-04-12 13:54:24'),
(19, 2, '7f487379d31ddab133de4839d5fb3e7307d0c457482c06c1e839fa4911905833', '27.49.15.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-13 10:41:53', '2026-04-12 14:41:53'),
(20, 2, 'c715dc4072f8edf2b16c0a697b157e40d0fb7a5936e005de63f2efab22e1e28d', '112.202.191.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-13 20:20:33', '2026-04-13 00:20:34'),
(21, 2, '9dce97753e93e936df9877536d994ab85a23e3d189cfec31f6758b51107d05ce', '112.202.191.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-04-13 21:16:44', '2026-04-13 01:16:45'),
(22, 2, 'e73aeea8333176888be90e6cf52da89b809aa76a23372b27d0dde2beed29ec16', '112.202.191.143', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.5 Mobile/15E148 Safari/604.1', '2026-04-13 21:22:40', '2026-04-13 01:22:40'),
(23, 2, 'e1d3aa3a0e895e3f2c9763693970467eee7bf4ec4c7bd80a5f0bc7197a73a3fe', '112.202.191.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-13 21:41:41', '2026-04-13 01:41:41'),
(24, 2, '90c3068f6e99d6d7acd9ca4b8f8cca7690144dea7e66ab88540c1a18652db0f6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-14 15:35:36', '2026-04-13 13:35:36'),
(25, 2, '1b04d26b4ff84fb33a8f748d2e5ec1522e84ccf6884605fe046c00e7b8add071', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-14 15:48:39', '2026-04-13 13:48:39'),
(26, 2, 'dfb9d9ca720c96074d92130441c9792bf294a06619f86eff9d3590c78b95698b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-14 15:50:39', '2026-04-13 13:50:39'),
(27, 2, 'a8ae17d386c74286fb09d4689a172779954833a3f655481d473c8fb21c8c2e6b', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/147.0.0.0', '2026-04-16 09:50:15', '2026-04-15 07:50:15'),
(28, 2, 'cbc8e7a7108ba9ea9a5160116f0bb2871f33d04e5c7809873dcdb455c8041b66', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/147.0.0.0', '2026-04-18 09:13:35', '2026-04-17 07:13:35'),
(30, 2, '79ba16bf2c4eff4120e5ce8039f4d30ab793b98615924da10c1e59bfd0beb2e5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-21 05:14:34', '2026-04-20 03:14:34'),
(31, 2, 'a62ae565dae0f4fb82e9a85dc8d8739ccfe19e1363358e1226c9cfa31ed9219c', '::1', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-24 02:33:14', '2026-04-23 00:33:14'),
(32, 2, 'cb569057c060f81a570a871466cd14d38786acd181ff711d331a92fe78e00d3a', '::1', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-25 03:17:24', '2026-04-24 01:17:24'),
(39, 2, '9fb450347bba2e106bde9b3389ed648ed52cbf3e6fc852c375f8a5fa145966f7', '112.210.229.175', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-25 15:02:32', '2026-04-24 19:02:33'),
(42, 2, '3c96e8680b6f7ee83c97a68713231d28648ea88ea2d2b8b961af6718b9367f8f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 08:19:26', '2026-04-27 06:19:26'),
(43, 2, '7a5ae7b890caf30a3f9d8260385024acd97dfc34e53f085a70e0ee6919fd832a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/147.0.0.0', '2026-04-28 10:41:02', '2026-04-27 08:41:02'),
(46, 2, '3b715e655bfc53398eb66026ba39db7da8bbb6343415aca4c5b22a0ec150097a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/147.0.0.0', '2026-04-29 07:48:50', '2026-04-28 05:48:50'),
(49, 2, '559aaa18599d7fb7368392da26f6fec2e8d107eedf39f7671b867620c6ce4027', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/147.0.0.0', '2026-04-30 06:37:01', '2026-04-29 04:37:01'),
(50, 2, '2300204a382807a88f1e66baceac82830fd07794cc17cc70fc018af0782d3e3f', '::1', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-05-07 04:54:47', '2026-05-06 02:54:47'),
(51, 2, '939be32a4adeddc8ce04d168116db5583e488c67b0c12e1e92016d0220f1f560', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-08 04:53:14', '2026-05-07 02:53:14'),
(52, 2, 'f996b1c1a27d1df1cb4f94da15b2708e9df2ccb312a48b6a3b0096e6bcf1713f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-08 23:58:53', '2026-05-07 21:58:53'),
(53, 2, 'a9f2d5f677fd936017b5dbbc1c29570f0ac3cc5df2c1c74b2192047035b266cf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-11 21:51:53', '2026-05-10 19:51:53'),
(59, 6, 'a4fe2ce1e34fe3b334fa6666c7f693fecdd067b7ec13a85173582261c6e1e2d8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-13 23:22:11', '2026-05-12 21:22:11'),
(60, 2, '6f59e507b6aac81e3c6baff6330faa5c94b42ebc0dcaf99d305a3a98103c4fc3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-15 04:41:05', '2026-05-14 02:41:05'),
(61, 2, '50dbb2d028907629286ac8ca4eb5083a668f5a84d738065fb89cfc6c0c472f83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-18 21:21:49', '2026-05-17 19:21:49'),
(64, 2, '0e246c4da71208c75ca6ae40e019fb51f7a6c5c865976a10e03607c8e8555e24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-19 09:57:46', '2026-05-18 07:57:46'),
(66, 2, '0807fc7ce6ad7f36f703a2d9e35d981e2d1519ad52f3ac7e327161b170890876', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-20 09:15:48', '2026-05-19 07:15:48'),
(73, 6, 'cc1632f3a46c3703671108de2e5db7e1d5bb49d1d1cf81f4f114f52a33a6ee17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 09:39:33', '2026-05-22 07:39:33'),
(75, 6, '305fa46bf9c478cdfb08b4c5034564736d06361f0557040a43d41a70613d9213', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-26 08:08:33', '2026-05-25 06:08:33'),
(88, 16, '10d5f4585817390a030d5d2817b2c955c7e61917053ab01f5181471f192ffa79', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-27 04:55:24', '2026-05-26 02:55:24'),
(93, 16, 'fc38160b40ea1593517c84c797d07e122160d57726087c520ff3ee70d89513a0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-27 09:41:06', '2026-05-26 07:41:06'),
(94, 16, '1b12b19b241ac50dff128d2931451220e24f0239e3c2681f98449742301cd19d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-27 10:40:42', '2026-05-26 08:40:42'),
(95, 16, '3cbce622ba8ae7f33cac939fcb848ef7dfd1005735f89fd1abe3d74885c186bb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-27 10:52:19', '2026-05-26 08:52:19'),
(120, 16, '4e37c9ce57ae424fb54d64bc822e45db9441d3069fdf2ccc0e031da416777082', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-30 10:03:41', '2026-05-29 08:03:41'),
(133, 16, '6465c3e8724e7bd06b18d7905f3becc3bce2c067ab654fdf9790b6280b09a219', '::1', 'Mozilla/5.0 (Linux; Android 13; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36 Edg/148.0.0.0', '2026-06-02 04:18:51', '2026-06-01 02:18:51'),
(155, 6, 'a89b16f8919f3789663914a51126872c06a3477903e4d7a05ba4b8b04b6f68a9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-06-02 10:53:21', '2026-06-01 08:53:21');

-- --------------------------------------------------------

--
-- Table structure for table `visas`
--

CREATE TABLE `visas` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'international',
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT '₱',
  `processing_time` varchar(50) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `icon_type` varchar(50) DEFAULT 'image',
  `icon_value` varchar(255) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `disclaimer` text DEFAULT NULL,
  `important_notes` text DEFAULT NULL,
  `visa_status` varchar(20) DEFAULT 'required'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visas`
--

INSERT INTO `visas` (`id`, `title`, `category`, `description`, `price`, `currency`, `processing_time`, `requirements`, `icon_type`, `icon_value`, `is_active`, `display_order`, `created_at`, `updated_at`, `disclaimer`, `important_notes`, `visa_status`) VALUES
(1, 'Singapore', 'Asia', 'e-Arrival Card (SGAC) & complete entry document assistance.', 999.00, '₱', 'Visa-Free (30 Days)', '[\"Passport valid for 6 months\",\"SG Arrival Card (SGAC)\",\"Confirmed Round-trip Ticket\",\"Confirmed Hotel Booking\",\"Proof of Sufficient Funds\"]', 'image', 'https://flagcdn.com/w80/sg.png', 1, 1, '2026-04-14 14:09:05', '2026-04-14 14:47:52', NULL, NULL, 'required'),
(2, 'Malaysia', 'Asia', 'Digital Arrival Card (MDAC) and entry proof preparation.', 999.00, '₱', 'Visa-Free (30 Days)', '[\"Passport valid for 6 months\",\"Malaysia Digital Arrival Card (MDAC)\",\"Confirmed Round-trip Ticket\",\"Confirmed Hotel Booking\"]', 'image', 'https://flagcdn.com/w80/my.png', 1, 2, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required'),
(3, 'Thailand', 'Asia', 'Proof of funds documentation and entry assistance.', 999.00, '₱', 'Visa-Free (30 Days)', '[\"Passport valid for 6 months\",\"Confirmed Round-trip Ticket\",\"Confirmed Hotel Booking\",\"Proof of Funds (Min. 10,000 THB equivalent)\"]', 'image', 'https://flagcdn.com/w80/th.png', 1, 3, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required'),
(4, 'Indonesia', 'Asia', 'Electronic Customs Declaration (e-CD) & entry proofs.', 999.00, '₱', 'Visa-Free (30 Days)', '[\"Passport valid for 6 months\",\"e-Customs Declaration (e-CD)\",\"Confirmed Round-trip Ticket\",\"Confirmed Hotel Booking\"]', 'image', 'https://flagcdn.com/w80/id.png', 1, 4, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required'),
(5, 'Vietnam', 'Asia', 'Complete entry document preparation and proofs.', 999.00, '₱', 'Visa-Free (21 Days)', '[\"Passport valid for 6 months\",\"Confirmed Round-trip Ticket\",\"Confirmed Hotel\\/Tour Booking\"]', 'image', 'https://flagcdn.com/w80/vn.png', 1, 5, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required'),
(6, 'Schengen Visa', 'Europe', 'Travel to 27 European countries with a single visa. Expert assistance with documentation.', 8999.00, '₱', 'Standard Processing', '[\"Passport valid for 6 months\",\"2x2 photo with white background\",\"Detailed Flight Itinerary\",\"Confirmed Hotel Booking\",\"Bank Certificate (3-6 Months)\",\"Certificate of Employment\",\"Travel Insurance\"]', 'image', 'https://flagcdn.com/w80/eu.png', 1, 6, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required'),
(7, 'US Visa', 'North America', 'B1/B2 tourist visa assistance. Interview preparation and DS-160 form assistance.', 12999.00, '₱', 'Regular Processing', '[\"Passport valid for 6 months\",\"2x2 photo (Recent, white background)\",\"DS-160 Confirmation\",\"Proof of strong ties to Philippines\",\"Financial Documents\",\"Employment Documents\"]', 'image', 'https://flagcdn.com/w80/us.png', 1, 7, '2026-04-14 14:09:05', '2026-04-14 14:09:05', NULL, NULL, 'required');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_registration_requests`
--
ALTER TABLE `admin_registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `request_token` (`request_token`),
  ADD KEY `idx_token` (`request_token`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `ai_chat_messages`
--
ALTER TABLE `ai_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `ai_chat_sessions`
--
ALTER TABLE `ai_chat_sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `block_unlock_requests`
--
ALTER TABLE `block_unlock_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_admin_block` (`admin_username`,`block_type`);

--
-- Indexes for table `booking_documents`
--
ALTER TABLE `booking_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_number` (`booking_number`);

--
-- Indexes for table `cruises`
--
ALTER TABLE `cruises`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cruise_code` (`cruise_code`);

--
-- Indexes for table `cruise_itinerary`
--
ALTER TABLE `cruise_itinerary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cruise_id` (`cruise_id`);

--
-- Indexes for table `customer_conversations`
--
ALTER TABLE `customer_conversations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_messages`
--
ALTER TABLE `customer_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `flash_deals`
--
ALTER TABLE `flash_deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indexes for table `flash_deals_fixed`
--
ALTER TABLE `flash_deals_fixed`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `flight_booking_settings`
--
ALTER TABLE `flight_booking_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `destination_key` (`destination_key`);

--
-- Indexes for table `foreign_destinations`
--
ALTER TABLE `foreign_destinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `global_settings`
--
ALTER TABLE `global_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `hotel_booking_settings`
--
ALTER TABLE `hotel_booking_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `destination_key` (`destination_key`);

--
-- Indexes for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marketing_templates`
--
ALTER TABLE `marketing_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marketing_tracking`
--
ALTER TABLE `marketing_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign` (`campaign_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_packages_destination` (`destination_id`),
  ADD KEY `idx_packages_active` (`is_active`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `reported_issues`
--
ALTER TABLE `reported_issues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_itinerary`
--
ALTER TABLE `service_itinerary`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_services`
--
ALTER TABLE `site_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_type` (`service_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_provider` (`provider`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_session_token` (`session_token`);

--
-- Indexes for table `visas`
--
ALTER TABLE `visas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_registration_requests`
--
ALTER TABLE `admin_registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `ai_chat_messages`
--
ALTER TABLE `ai_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=725;

--
-- AUTO_INCREMENT for table `block_unlock_requests`
--
ALTER TABLE `block_unlock_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_documents`
--
ALTER TABLE `booking_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `cruises`
--
ALTER TABLE `cruises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cruise_itinerary`
--
ALTER TABLE `cruise_itinerary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `customer_conversations`
--
ALTER TABLE `customer_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer_messages`
--
ALTER TABLE `customer_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `flash_deals`
--
ALTER TABLE `flash_deals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `flash_deals_fixed`
--
ALTER TABLE `flash_deals_fixed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flight_booking_settings`
--
ALTER TABLE `flight_booking_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `foreign_destinations`
--
ALTER TABLE `foreign_destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `hotel_booking_settings`
--
ALTER TABLE `hotel_booking_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `marketing_templates`
--
ALTER TABLE `marketing_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `marketing_tracking`
--
ALTER TABLE `marketing_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reported_issues`
--
ALTER TABLE `reported_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_itinerary`
--
ALTER TABLE `service_itinerary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `site_services`
--
ALTER TABLE `site_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `visas`
--
ALTER TABLE `visas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_chat_messages`
--
ALTER TABLE `ai_chat_messages`
  ADD CONSTRAINT `ai_chat_messages_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `ai_chat_sessions` (`session_id`) ON DELETE CASCADE;

--
-- Constraints for table `cruise_itinerary`
--
ALTER TABLE `cruise_itinerary`
  ADD CONSTRAINT `cruise_itinerary_ibfk_1` FOREIGN KEY (`cruise_id`) REFERENCES `cruises` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_messages`
--
ALTER TABLE `customer_messages`
  ADD CONSTRAINT `customer_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `customer_conversations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

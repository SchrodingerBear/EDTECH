-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 07, 2025 at 02:50 PM
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
-- Database: `u467106394_numuseum`
--

-- --------------------------------------------------------

--
-- Table structure for table `artworks`
--

CREATE TABLE `artworks` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `date` date DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `artist_name` varchar(100) DEFAULT NULL,
  `img` varchar(100) NOT NULL,
  `artist_id` int(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `artist_type` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `artworks`
--

INSERT INTO `artworks` (`id`, `type`, `date`, `title`, `artist_name`, `img`, `artist_id`, `email`, `contact`, `artist_type`, `description`) VALUES
(7, 'Featured', '2024-05-01', 'Takbo Bata', 'Carlos Legaspi', 'LANDSCAPE_LAYOUT1.JPG', 2, NULL, NULL, NULL, 'The captivating landscape photography of Luneta Park. Discover the charm and cultural significance of this iconic Filipino landmark through images that celebrate the heart of the Pinoy.'),
(8, 'Featured', '2024-05-01', 'Kambal', 'Carlos Legaspi', 'LANDSCAPE_LAYOUT3.JPG', 2, NULL, NULL, NULL, NULL),
(9, 'Exhibit', '2024-05-01', 'Sorbetes', 'Carlos Legaspi', 'TRAVEL_LAYOUT1.JPG', 2, NULL, NULL, NULL, NULL),
(10, 'Approved', '2024-05-01', 'Maniniyot', 'Carlos Legaspi', 'TRAVEL_LAYOUT2.JPG', 2, NULL, NULL, NULL, NULL),
(11, 'Exhibit', '2024-05-01', 'Kalesa', 'Carlos Legaspi', 'TRAVEL_LAYOUT3.JPG', 2, NULL, NULL, NULL, NULL),
(12, 'Exhibit', '2024-05-01', 'Cat Rally', 'Lee Anne Parafina', 'Cat Rally.png', 1, NULL, NULL, NULL, NULL),
(13, 'Exhibit', '2024-05-01', 'If Shooting Stars Were Real, I\'d Wish They Never Existed', 'Lee Anne Parafina', 'If Shooting Stars Were Real, I_d Wish They Never Existed.png', 1, NULL, NULL, NULL, NULL),
(15, 'Featured', '2024-05-01', 'Pagkain at Lupa', 'Lee Anne Parafina', 'Pagkain at Lupa.png', 1, NULL, NULL, NULL, 'Explore \"Sa Sining Sisigaw\" and immerse yourself in the bold and evocative world of Lee Anne Parafina\'s digital artistry. This artwork not only highlights the artist\'s exceptional talent but also serves as a powerful reminder of the impact of art.'),
(16, 'Archive', '2024-05-01', 'Remote Control', 'Lee Anne Parafina', 'Remote Control.png', 1, NULL, NULL, NULL, NULL),
(17, 'Exhibit', '2024-05-01', 'There Is No POGO In Bamban, Tarlac', 'Lee Anne Parafina', 'There Is No POGO In Bamban, Tarlac.png', 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `title` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `description`, `title`) VALUES
(1, 'Explore the festive moments from Pagdiriwang sa Kalye Jhocson, where students, faculty, and alumni came together to honor our UAAP Season 86 champions and commemorate the unveiling of the Bulldog. It was a day filled with joy, pride, and community spirit.', 'The captivating landscape photography of Luneta Park. Discover the charm and cultural significance of this iconic Filipino landmark through images that celebrate the heart of the Pinoy.'),
(2, 'In case you missed it, here are the highlights from Pagdiriwang sa Kalye Jhocson, where we joyously celebrated the triumph of our UAAP Season 86 champions and unveiled the majestic Bulldog, symbolizing our team\'s spirit and unity. It was a day filled with pride, camaraderie, and unforgettable moments as we honored our athletes\' dedication and achievements on and off the field.', 'The captivating landscape photography of Luneta Park. Discover the charm and cultural significance of this iconic Filipino landmark through images that celebrate the heart of the Pinoy.');

-- --------------------------------------------------------

--
-- Table structure for table `thoughts`
--

CREATE TABLE `thoughts` (
  `id` int(11) NOT NULL,
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thoughts`
--

INSERT INTO `thoughts` (`id`, `message`) VALUES
(1, 'Test');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'admin', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `artworks`
--
ALTER TABLE `artworks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `thoughts`
--
ALTER TABLE `thoughts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `artworks`
--
ALTER TABLE `artworks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `thoughts`
--
ALTER TABLE `thoughts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

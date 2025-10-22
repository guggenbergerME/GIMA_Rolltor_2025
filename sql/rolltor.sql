-- phpMyAdmin SQL Dump 
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Erstellungszeit: 22. Okt 2025 um 13:08
-- Server-Version: 11.8.3-MariaDB-ubu2404
-- PHP-Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `rolltor`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `relais_log`
--

CREATE TABLE `relais_log` (
  `id` int(11) NOT NULL,
  `device_ip` varchar(32) DEFAULT NULL,
  `ist` char(4) DEFAULT NULL,
  `soll` char(4) DEFAULT NULL,
  `changed` tinyint(1) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Daten für Tabelle `relais_log`
--

INSERT INTO `relais_log` (`id`, `device_ip`, `ist`, `soll`, `changed`, `created_at`) VALUES
(1, '10.140.1.10', '1010', '1010', 0, '2025-10-22 14:20:11'),
(2, '10.140.1.10', '0000', '1010', 1, '2025-10-22 14:20:31'),
(3, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:24:26'),
(4, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:25:46'),
(5, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:25:52'),
(6, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:26:52'),
(7, '10.140.1.10', '0100', '0000', 1, '2025-10-22 14:27:52'),
(8, '10.140.1.10', '0000', '0100', 1, '2025-10-22 14:28:35'),
(9, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:28:40'),
(10, '10.140.1.10', '0000', '0000', 0, '2025-10-22 14:29:40'),
(11, '10.140.1.10', '1011', '0000', 1, '2025-10-22 14:30:40'),
(12, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:31:40'),
(13, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:32:40'),
(14, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:33:40'),
(15, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:34:40'),
(16, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:35:40'),
(17, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:36:40'),
(18, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:37:40'),
(19, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:38:40'),
(20, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:39:40'),
(21, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:40:40'),
(22, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:41:40'),
(23, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:42:40'),
(24, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:43:40'),
(25, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:44:40'),
(26, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:45:40'),
(27, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:46:40'),
(28, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:47:40'),
(29, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:48:40'),
(30, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:49:40'),
(31, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:50:40'),
(32, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:51:40'),
(33, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:52:40'),
(34, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:53:40'),
(35, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:54:40'),
(36, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:55:40'),
(37, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:56:40'),
(38, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:57:40'),
(39, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:58:40'),
(40, '10.140.1.10', '1011', '1011', 0, '2025-10-22 14:59:40'),
(41, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:00:40'),
(42, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:01:40'),
(43, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:02:40'),
(44, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:03:40'),
(45, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:04:40'),
(46, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:05:40'),
(47, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:06:40'),
(48, '10.140.1.10', '1011', '1011', 0, '2025-10-22 15:07:40');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `relais_status`
--

CREATE TABLE `relais_status` (
  `id` int(11) NOT NULL,
  `device_ip` varchar(32) NOT NULL,
  `r1` tinyint(1) NOT NULL DEFAULT 0,
  `r2` tinyint(1) NOT NULL DEFAULT 0,
  `r3` tinyint(1) NOT NULL DEFAULT 0,
  `r4` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Daten für Tabelle `relais_status`
--

INSERT INTO `relais_status` (`id`, `device_ip`, `r1`, `r2`, `r3`, `r4`, `updated_at`) VALUES
(1, '10.140.1.10', 1, 0, 1, 1, '2025-10-22 13:07:40');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sondertage`
--

CREATE TABLE `sondertage` (
  `datum` date NOT NULL,
  `status` enum('offen','geschlossen') NOT NULL,
  `kommentar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `steuerzeiten`
--

CREATE TABLE `steuerzeiten` (
  `id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `startzeit` time NOT NULL,
  `endzeit` time NOT NULL,
  `aktion` enum('öffnen','schließen') NOT NULL,
  `kommentar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `torzeiten`
--

CREATE TABLE `torzeiten` (
  `id` int(11) NOT NULL,
  `modus` enum('sommer','winter') NOT NULL,
  `startzeit` time NOT NULL,
  `endzeit` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Daten für Tabelle `torzeiten`
--

INSERT INTO `torzeiten` (`id`, `modus`, `startzeit`, `endzeit`) VALUES
(1, 'sommer', '06:22:00', '17:00:00'),
(2, 'winter', '07:00:00', '16:30:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tor_status`
--

CREATE TABLE `tor_status` (
  `id` int(11) NOT NULL,
  `status` enum('offen','geschlossen') NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Daten für Tabelle `tor_status`
--

INSERT INTO `tor_status` (`id`, `status`, `timestamp`) VALUES
(1, 'offen', '2025-10-17 15:12:01'),
(2, 'geschlossen', '2025-10-17 15:22:01'),
(3, 'offen', '2025-10-20 06:31:01'),
(4, 'geschlossen', '2025-10-20 15:21:01'),
(5, 'offen', '2025-10-21 06:31:01'),
(6, 'geschlossen', '2025-10-21 17:01:01'),
(7, 'offen', '2025-10-22 06:31:01');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `relais_log`
--
ALTER TABLE `relais_log`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `relais_status`
--
ALTER TABLE `relais_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_ip` (`device_ip`);

--
-- Indizes für die Tabelle `sondertage`
--
ALTER TABLE `sondertage`
  ADD PRIMARY KEY (`datum`);

--
-- Indizes für die Tabelle `steuerzeiten`
--
ALTER TABLE `steuerzeiten`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `torzeiten`
--
ALTER TABLE `torzeiten`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `tor_status`
--
ALTER TABLE `tor_status`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `relais_log`
--
ALTER TABLE `relais_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT für Tabelle `relais_status`
--
ALTER TABLE `relais_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `steuerzeiten`
--
ALTER TABLE `steuerzeiten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `torzeiten`
--
ALTER TABLE `torzeiten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `tor_status`
--
ALTER TABLE `tor_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Erstellungszeit: 22. Okt 2025 um 13:17
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
  `ip` varchar(45) NOT NULL,
  `reported_state` char(4) NOT NULL,
  `desired_state` char(4) NOT NULL,
  `action_taken` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `relais_status`
--

CREATE TABLE `relais_status` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `desired_state` char(4) NOT NULL,
  `current_state` char(4) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `relais_status`
--
ALTER TABLE `relais_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
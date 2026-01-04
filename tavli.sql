-- Δημιουργία / Επιλογή Βάσης
CREATE DATABASE IF NOT EXISTS tavli;
USE tavli;

-- 1. ΠΙΝΑΚΑΣ BOARD (Ταμπλό)
DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  `x` tinyint(4) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `piece_count` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Γέμισμα με κενές θέσεις (1-24)
INSERT INTO `board` (`x`, `piece_count`, `piece_color`) VALUES 
(1,0,null),(2,0,null),(3,0,null),(4,0,null),(5,0,null),(6,0,null),
(7,0,null),(8,0,null),(9,0,null),(10,0,null),(11,0,null),(12,0,null),
(13,0,null),(14,0,null),(15,0,null),(16,0,null),(17,0,null),(18,0,null),
(19,0,null),(20,0,null),(21,0,null),(22,0,null),(23,0,null),(24,0,null);

-- 2. ΠΙΝΑΚΑΣ PLAYERS (Παίκτες)
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `token` varchar(50) DEFAULT NULL,
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. ΠΙΝΑΚΑΣ GAME_STATUS (Κατάσταση Παιχνιδιού)
DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','first_roll','initialized','started','ended','aborted') NOT NULL DEFAULT 'not active',
  `p_turn` enum('B','W') DEFAULT NULL,
  `result` enum('B','W','D') DEFAULT NULL,
  `dice1` tinyint DEFAULT NULL,
  `dice2` tinyint DEFAULT NULL,
  `w_off` tinyint DEFAULT 0, 
  `b_off` tinyint DEFAULT 0,
  `score_w` tinyint DEFAULT 0, 
  `score_b` tinyint DEFAULT 0,
  `last_change` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Αρχική κατάσταση: 'not active'
INSERT INTO `game_status` 
(`status`, `p_turn`, `result`, `dice1`, `dice2`, `w_off`, `b_off`, `score_w`, `score_b`) VALUES 
('not active', NULL, NULL, NULL, NULL, 0, 0, 0, 0);

-- 4. ΔΙΑΔΙΚΑΣΙΕΣ (STORED PROCEDURES)

DELIMITER //

-- clean_board: Καθαρίζει και στήνει το παιχνίδι για ΦΕΥΓΑ
DROP PROCEDURE IF EXISTS clean_board//
CREATE PROCEDURE clean_board()
BEGIN
    -- Καθαρισμός
    UPDATE board SET piece_count = 0, piece_color = null;

    -- ΣΤΗΣΙΜΟ ΦΕΥΓΑ
    -- Άσπρα (W): 15 πούλια στη θέση 24 (Πίνακας)
    UPDATE board SET piece_count = 15, piece_color = 'W' WHERE x = 24;
    
    -- Μαύρα (B): 15 πούλια στη θέση 12 (Πίνακας)
    UPDATE board SET piece_count = 15, piece_color = 'B' WHERE x = 12;
    
    -- Ενημέρωση Status: Το παιχνίδι ξεκινάει, παίζει ο Άσπρος
    UPDATE game_status 
    SET status='started', p_turn='W', dice1=NULL, dice2=NULL, result=NULL;
END //

-- clear_game: Μηδενίζει τα πάντα (Reset)
DROP PROCEDURE IF EXISTS clear_game//
CREATE PROCEDURE clear_game()
BEGIN
    UPDATE board SET piece_count = 0, piece_color = null;
    UPDATE game_status 
    SET status='not active', p_turn=NULL, dice1=NULL, dice2=NULL, result=NULL;
END //

DELIMITER ;
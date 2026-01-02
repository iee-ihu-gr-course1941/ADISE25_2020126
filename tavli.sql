-- ΔΗΜΙΟΥΡΓΙΑ ΒΑΣΗΣ
DROP DATABASE IF EXISTS tavli;
CREATE DATABASE tavli;
USE tavli;

-- 1. ΠΙΝΑΚΑΣ ΤΑΜΠΛΟ
CREATE TABLE `board` (
  `x` tinyint(4) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `piece_count` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 2. ΠΙΝΑΚΑΣ ΠΑΙΚΤΩΝ
CREATE TABLE `players` (
  `username` varchar(20) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `token` varchar(32) DEFAULT NULL,
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Γέμισμα θέσεων (Αρχικά άδειες)
INSERT INTO `board` (`x`, `piece_count`, `piece_color`) VALUES 
(1,0,null),(2,0,null),(3,0,null),(4,0,null),(5,0,null),(6,0,null),
(7,0,null),(8,0,null),(9,0,null),(10,0,null),(11,0,null),(12,0,null),
(13,0,null),(14,0,null),(15,0,null),(16,0,null),(17,0,null),(18,0,null),
(19,0,null),(20,0,null),(21,0,null),(22,0,null),(23,0,null),(24,0,null);


-- 3. ΠΙΝΑΚΑΣ ΚΑΤΑΣΤΑΣΗΣ ΠΑΙΧΝΙΔΙΟΥ
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

-- Αρχική εγγραφή
INSERT INTO `game_status` 
(`status`, `p_turn`, `result`, `dice1`, `dice2`, `w_off`, `b_off`, `score_w`, `score_b`) VALUES 
('not active', NULL, NULL, NULL, NULL, 0, 0, 0, 0);


-- ΔΙΑΔΙΚΑΣΙΕΣ (PROCEDURES)
DELIMITER //

-- Διαδικασία: Στήσιμο ΦΕΥΓΑ (Start Game)
DROP PROCEDURE IF EXISTS clean_board//

CREATE PROCEDURE clean_board()
BEGIN
    DECLARE start_turn ENUM('W','B') DEFAULT 'W';
    DECLARE d1 TINYINT;
    DECLARE d2 TINYINT;
    
    SELECT dice1, dice2 INTO d1, d2 FROM game_status LIMIT 1;
    
    IF (d1 IS NOT NULL AND d2 IS NOT NULL AND d2 > d1) THEN
        SET start_turn = 'B';
    ELSE
        SET start_turn = 'W';
    END IF;

    UPDATE board SET piece_count = 0, piece_color = null;
    UPDATE board SET piece_count = 15, piece_color = 'W' WHERE x = 24;
    UPDATE board SET piece_count = 15, piece_color = 'B' WHERE x = 12;
    
    UPDATE game_status 
    SET status='started', 
        p_turn=start_turn, 
        dice1=NULL, 
        dice2=NULL, 
        result=NULL, 
        w_off=0, 
        b_off=0;
END //

-- Διαδικασία: Καθαρισμός Τραπεζιού (Reset/Waiting)
DROP PROCEDURE IF EXISTS clear_game//
CREATE PROCEDURE clear_game()
BEGIN
    UPDATE board SET piece_count = 0, piece_color = null;
    UPDATE game_status 
    SET status='not active', 
        p_turn=NULL, 
        dice1=NULL, 
        dice2=NULL, 
        result=NULL, 
        w_off=0, 
        b_off=0;
END //

DELIMITER ;
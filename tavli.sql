-- 1. Πίνακας Παικτών (Players)
-- ==========================================
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL, -- B=Black, W=White
  `token` varchar(32) DEFAULT NULL, -- Κρυφός κωδικός για ασφάλεια (session token)
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ==========================================
-- 2. Πίνακας Κατάστασης Παιχνιδιού (Game Status)
-- ==========================================
DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','initialized','started','ended','aborted') NOT NULL DEFAULT 'not active',
  `p_turn` enum('B','W') DEFAULT NULL, -- Ποιανού σειρά είναι
  `result` enum('B','W','D') DEFAULT NULL, -- Αποτέλεσμα
  `dice1` tinyint DEFAULT NULL, -- Ζάρι 1
  `dice2` tinyint DEFAULT NULL, -- Ζάρι 2
  
  -- ΝΕΕΣ ΣΤΗΛΕΣ: Για να μετράμε πόσα πούλια μάζεψε ο καθένας
  `w_off` tinyint DEFAULT 0, 
  `b_off` tinyint DEFAULT 0,
  
  `last_change` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Εισάγουμε μια αρχική γραμμή (πάντα θα υπάρχει μόνο μία γραμμή εδώ)
INSERT INTO `game_status` 
(`status`, `p_turn`, `result`, `dice1`, `dice2`, `w_off`, `b_off`) VALUES 
('not active', NULL, NULL, NULL, NULL, 0, 0);

-- ==========================================
-- 3. Πίνακας Ταμπλό (Board)
-- ==========================================
DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  `x` tinyint(4) NOT NULL, -- Η θέση στο ταμπλό (1 έως 24)
  `piece_color` enum('B','W') DEFAULT NULL, -- Τι χρώμα έχει η θέση
  `piece_count` tinyint(4) DEFAULT 0, -- Πόσα πούλια έχει η θέση
  PRIMARY KEY (`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Γεμίζουμε τον πίνακα με τις 24 θέσεις (αρχικά κενές)
INSERT INTO `board` (`x`, `piece_count`, `piece_color`) VALUES 
(1,0,null),(2,0,null),(3,0,null),(4,0,null),(5,0,null),(6,0,null),
(7,0,null),(8,0,null),(9,0,null),(10,0,null),(11,0,null),(12,0,null),
(13,0,null),(14,0,null),(15,0,null),(16,0,null),(17,0,null),(18,0,null),
(19,0,null),(20,0,null),(21,0,null),(22,0,null),(23,0,null),(24,0,null);

-- ==========================================
-- 4. Διαδικασία Reset / Εκκίνησης (Stored Procedure)
-- ==========================================
DROP PROCEDURE IF EXISTS `clean_board`;

DELIMITER //
CREATE PROCEDURE `clean_board`()
BEGIN
    -- Βήμα 1: Καθαρίζουμε όλο το ταμπλό από προηγούμενα παιχνίδια
	UPDATE `board` SET piece_color=null, piece_count=0;

    -- Βήμα 2: Στήνουμε την αρχική θέση της ΦΕΥΓΑΣ
    -- Ο Παίκτης W (Άσπρα) ξεκινάει με 15 πούλια στη θέση 1
    UPDATE `board` SET piece_color='W', piece_count=15 WHERE x=1;
    
    -- Ο Παίκτης B (Μαύρα) ξεκινάει με 15 πούλια στη θέση 13 (διαγώνια απέναντι)
    UPDATE `board` SET piece_color='B', piece_count=15 WHERE x=13;
    
    -- Βήμα 3: Επαναφέρουμε την κατάσταση του παιχνιδιού (Status & Score)
    UPDATE `game_status` SET 
        status='started', 
        p_turn='W',       -- Ξεκινάνε πάντα τα Άσπρα (ή κάνε το τυχαίο στην PHP)
        dice1=null, 
        dice2=null, 
        result=null,
        w_off=0,          -- Μηδενίζουμε τα μαζεμένα πούλια
        b_off=0;
END //
DELIMITER ;
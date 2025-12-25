-- 1. Πίνακας Παικτών (Players)
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `username` varchar(20) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `token` varchar(32) DEFAULT NULL,
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 2. Πίνακας Κατάστασης Παιχνιδιού (Game Status)
DROP TABLE IF EXISTS `game_status`;
CREATE TABLE `game_status` (
  `status` enum('not active','initialized','started','ended','aborted') NOT NULL DEFAULT 'not active',
  `p_turn` enum('B','W') DEFAULT NULL,
  `result` enum('B','W','D') DEFAULT NULL,
  `dice1` tinyint DEFAULT NULL,
  `dice2` tinyint DEFAULT NULL,
  `w_off` tinyint DEFAULT 0, 
  `b_off` tinyint DEFAULT 0,
  `last_change` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Αρχική εγγραφή
INSERT INTO `game_status` 
(`status`, `p_turn`, `result`, `dice1`, `dice2`, `w_off`, `b_off`) VALUES 
('not active', NULL, NULL, NULL, NULL, 0, 0);

-- 3. Πίνακας Ταμπλό (Board)
DROP TABLE IF EXISTS `board`;
CREATE TABLE `board` (
  `x` tinyint(4) NOT NULL,
  `piece_color` enum('B','W') DEFAULT NULL,
  `piece_count` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`x`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Γέμισμα θέσεων
INSERT INTO `board` (`x`, `piece_count`, `piece_color`) VALUES 
(1,0,null),(2,0,null),(3,0,null),(4,0,null),(5,0,null),(6,0,null),
(7,0,null),(8,0,null),(9,0,null),(10,0,null),(11,0,null),(12,0,null),
(13,0,null),(14,0,null),(15,0,null),(16,0,null),(17,0,null),(18,0,null),
(19,0,null),(20,0,null),(21,0,null),(22,0,null),(23,0,null),(24,0,null);

-- 4. Διαδικασία Reset (ΤΟ ΔΙΟΡΘΩΜΕΝΟ ΚΟΜΜΑΤΙ)
DELIMITER //

DROP PROCEDURE IF EXISTS clean_board//

CREATE PROCEDURE clean_board()
BEGIN
    -- Α. Καθαρισμός Ταμπλό
    UPDATE board SET piece_count = 0, piece_color = null;

    -- Β. Στήσιμο ΦΕΥΓΑ
    -- ΛΕΥΚΑ: Κάτω Αριστερά -> Θέση 12
    UPDATE board SET piece_count = 15, piece_color = 'W' WHERE x = 12;
    -- ΜΑΥΡΑ: Πάνω Δεξιά -> Θέση 24
    UPDATE board SET piece_count = 15, piece_color = 'B' WHERE x = 24;
    
    -- Γ. Ενημέρωση Status (ΑΥΤΟ ΕΛΕΙΠΕ)
    -- Ορίζουμε ότι το παιχνίδι ξεκίνησε, παίζουν τα Άσπρα, και μηδενίζουμε τα ζάρια
    UPDATE game_status 
    SET status='started', p_turn='W', dice1=NULL, dice2=NULL, result=NULL;
    
END //

DELIMITER ;

-- Προσθέτουμε στήλες για το σκορ
ALTER TABLE `game_status` 
ADD COLUMN `score_w` tinyint DEFAULT 0, 
ADD COLUMN `score_b` tinyint DEFAULT 0;

DELIMITER //

-- Διαδικασία που ΑΔΕΙΑΖΕΙ τελείως το τραπέζι (κατάσταση αναμονής)
DROP PROCEDURE IF EXISTS clear_game//
CREATE PROCEDURE clear_game()
BEGIN
    -- 1. Σβήνουμε όλα τα πούλια
    UPDATE board SET piece_count = 0, piece_color = null;

    -- 2. Βάζουμε το status σε 'not active'
    UPDATE game_status 
    SET status='not active', p_turn=NULL, dice1=NULL, dice2=NULL, result=NULL;
END //

DELIMITER ;
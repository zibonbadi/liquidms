CREATE DATABASE IF NOT EXISTS `liquidms`;
CREATE TABLE `servers` (
  `host` VARCHAR(64) NOT NULL,
  `port` SMALLINT(6) unsigned NOT NULL,
  `servername` VARCHAR(64) NOT NULL,
  `version` VARCHAR(16) NOT NULL,
  `roomname` VARCHAR(32) DEFAULT NULL,
  `origin` VARCHAR(64) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host`,`port`)
);

CREATE EVENT IF NOT EXISTS serverlist_cleanup
   ON SCHEDULE EVERY 3 MINUTE
   COMMENT 'Removes server entries older than 10 minutes'
   DO DELETE FROM servers WHERE updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE);

CREATE TABLE `rooms` (
  `roomname` varchar(32) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`roomname`)
);

CREATE TABLE `versions` (
  `_id` int(11) NOT NULL AUTO_INCREMENT,
  `gameid` int(11) NOT NULL DEFAULT 1,
  `name` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`_id`)
);

-- Bans will be handled through subnet ranges.
-- Default duration: 24h.
-- Timestamp NULL == permaban

CREATE TABLE `bans` (
  `_id` INT(11) NOT NULL AUTO_INCREMENT,
  `host` VARCHAR(64) NOT NULL,
  `subnetmask` VARCHAR(64) DEFAULT "255.255.255.255",
  `expire` DATETIME DEFAULT adddate(CURRENT_TIMESTAMP,1),
  PRIMARY KEY (`_id`)
);

CREATE EVENT banlist_cleanup
   ON SCHEDULE EVERY 3 MINUTE
   COMMENT 'Removes expired ban entries'
   DO DELETE FROM servers WHERE updated_at < CURRENT_TIMESTAMP;

-- Data section
INSERT INTO `versions` (`_id`, `gameid`,`name`) VALUES
(20,1,'2.2.9' ),
(19,1,'1.2.0' ),
(18,50,'2.2.9' ),
(17,7,'v1.3' ),
(16,1,'mirrormode-v1' ),
(14,1,'TD v1.0.0'),
(12,30,'v2.1.25'),
(11,3,'v1.10.7'),
(10,110,'v1.1'),
(9,1,'2.46.5'),
(8,1,'v2.0.5.1'),
(7,7,'v2.5.2'),
(6,1,'Z-000'),
(5,2,'B-001'),
(4,1,'S-000'),
(3,10,'vX-010'),
(2,1,'v0.22'),
(1,207,'v2.0.7')
;

-- Launching the server

-- Enabling event scheduler
SET GLOBAL event_scheduler = ON

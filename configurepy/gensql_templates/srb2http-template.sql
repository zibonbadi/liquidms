-- LiquidMS - distributable SRB2 master server
-- Copyright (C) 2021-2025 Zibon Badi et al.
-- 
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as
-- published by the Free Software Foundation, either version 3 of the
-- License, or (at your option) any later version.
-- 
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
-- 
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.


CREATE DATABASE IF NOT EXISTS `$dbname`;
USE `$dbname`;

-- server list with all automations
CREATE TABLE IF NOT EXISTS `$servtabname` (
  `host` VARBINARY(16) NOT NULL,
  `port` SMALLINT(6) unsigned NOT NULL,
  `servername` VARCHAR(256) NOT NULL,
  `version` VARCHAR(16) NOT NULL,
  `roomname` VARCHAR(32) DEFAULT NULL,
  `origin` VARCHAR(64) NOT NULL DEFAULT 'localhost',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host`,`port`)
);


-- Room list with all automations
CREATE TABLE IF NOT EXISTS `$roomtabname` (
  `_id` INT(11) NOT NULL UNIQUE,
  `roomname` VARCHAR(32) NOT NULL,
  `origin` VARCHAR(32) NOT NULL DEFAULT 'localhost',
  `description` text DEFAULT "Powered by liquidMS: DO NOT REGISTER NETGAMES HERE.",
  PRIMARY KEY (`roomname`,`origin`)
);

CREATE TABLE IF NOT EXISTS `$versiontabname` (
  `modid` INT(11) NOT NULL AUTO_INCREMENT,
  `gameid` INT(11) NOT NULL DEFAULT 1,
  `name` VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (`modid`)
);

-- Bans will be handled through IP ranges.
-- IPv4 will be handled through use of IPv4-Mapped IPv6
-- Default duration: 24h.
-- Timestamp NULL == permaban
-- Comment is reserved for administration and remains unused

CREATE TABLE IF NOT EXISTS `$bantabname` (
  `_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ip_start` VARBINARY(16) NOT NULL,
  `ip_end` VARBINARY(16) NOT NULL,
  `expire` DATETIME DEFAULT adddate(CURRENT_TIMESTAMP,1),
  `comment` VARCHAR(128),
  PRIMARY KEY (`_id`)
);

-- Data section
INSERT INTO `$versiontabname` (`modid`, `gameid`,`name`) VALUES
(20,1,'2.2.9' ),
(19,1,'1.3.2' ),
-- (18,51,'v2.2.10' ),
-- (18,52,'v2.2.11' ),
-- (18,52,'v2.2.13' ),
(18,56,'v2.2.15' ),
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
ON DUPLICATE KEY UPDATE
`modid`=VALUES(`modid`), `gameid`=VALUES(`gameid`), `name`=VALUES(`name`);


-- Behaviour

DELIMITER #

CREATE PROCEDURE IF NOT EXISTS liquidms.rebuild_roomlist ()
BEGIN
DELETE FROM `$roomtabname` WHERE _id > 99;
INSERT INTO `$roomtabname` (`_id`,`roomname`,`origin`) SELECT DISTINCT ROW_NUMBER() OVER ()+100 AS `_id`,`roomname`,`origin` FROM `$servtabname` WHERE `origin` <> 'localhost' GROUP BY `roomname`;
DELETE FROM `$roomtabname` WHERE roomname = '' OR origin = '' ;
END#

CREATE TRIGGER IF NOT EXISTS `roomlist_rebuild_insert`
   AFTER INSERT ON `$servtabname` FOR EACH ROW
BEGIN
CALL rebuild_roomlist;
END
#

CREATE TRIGGER IF NOT EXISTS `roomlist_rebuild_update`
   AFTER UPDATE ON `$servtabname` FOR EACH ROW
BEGIN
CALL rebuild_roomlist;
END
#

CREATE TRIGGER IF NOT EXISTS `roomlist_rebuild_delete`
   AFTER DELETE ON `$servtabname` FOR EACH ROW
BEGIN
CALL rebuild_roomlist;
END
#

CREATE EVENT IF NOT EXISTS serverlist_cleanup
   ON SCHEDULE EVERY 1 MINUTE
   COMMENT 'Removes server entries older than 20 minutes'
DO BEGIN
DELETE FROM `$servtabname` WHERE updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 MINUTE);
DELETE `$servtabname` FROM `$servtabname` JOIN `bans` WHERE INET6_ATON(`$servtabname`.`host`) =  INET6_ATON(`bans`.`host`);
END#

-- 'Removes expired ban entries'
CREATE TRIGGER IF NOT EXISTS banlist_cleanup
   BEFORE INSERT ON `$servtabname` FOR EACH ROW -- BEFORE to support temp bans
   BEGIN
   DELETE FROM `$bantabname` WHERE expire < CURRENT_TIMESTAMP AND expire <> NULL;
   END#

DELIMITER ;


-- Custom data
$customroomlist

$custompermabans

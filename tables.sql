-- liquidMS - distributable SRB2 master server
-- Copyright (C) 2021-2022 Zibon Badi et al.
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



CREATE DATABASE IF NOT EXISTS `liquidms`;
USE `liquidms`;

-- server list with all automations
CREATE TABLE IF NOT EXISTS `servers` (
  `host` INET6 NOT NULL,
  `port` SMALLINT(6) unsigned NOT NULL,
  `servername` VARCHAR(256) NOT NULL,
  `version` VARCHAR(16) NOT NULL,
  `roomname` VARCHAR(32) DEFAULT NULL,
  `origin` VARCHAR(64) NOT NULL DEFAULT 'localhost',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host`,`port`)
);


-- Room list with all automations
CREATE TABLE IF NOT EXISTS `rooms` (
  `_id` INT(11) NOT NULL UNIQUE,
  `roomname` VARCHAR(32) NOT NULL,
  `origin` VARCHAR(32) NOT NULL DEFAULT 'localhost',
  `description` text DEFAULT "Powered by liquidMS: DO NOT REGISTER NETGAMES HERE.",
  PRIMARY KEY (`roomname`,`origin`)
);
-- CREATE EVENT IF NOT EXISTS roomlist_rebuild
   -- ON SCHEDULE EVERY MINUTE
   -- COMMENT 'Restructures the room table'
   -- DO BEGIN
   -- TRUNCATE rooms;
   -- INSERT INTO rooms(`_id`,`roomname`,`origin`) SELECT DISTINCT ROW_NUMBER() OVER ()+1 AS `_id`,`roomname`,`origin` FROM `servers` GROUP BY `roomname`;
   -- END;


CREATE TABLE IF NOT EXISTS `versions` (
  `_id` INT(11) NOT NULL AUTO_INCREMENT,
  `gameid` INT(11) NOT NULL DEFAULT 1,
  `name` VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (`_id`)
);

-- Bans will be handled through subnet ranges.
-- IPv4 will be handled through use of IPv4-Mapped IPv6
-- Default duration: 24h.
-- Timestamp NULL == permaban

CREATE TABLE IF NOT EXISTS `bans` (
  `_id` INT(11) NOT NULL AUTO_INCREMENT,
  `host` INET6 NOT NULL,
  `subnetmask` INET6 DEFAULT "FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF",
  `expire` DATETIME DEFAULT adddate(CURRENT_TIMESTAMP,1),
  PRIMARY KEY (`_id`)
);

-- Data section
INSERT INTO `versions` (`_id`, `gameid`,`name`) VALUES
(20,1,'2.2.9' ),
(19,1,'1.3.2' ),
(18,51,'v2.2.10' ),
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
`_id`=VALUES(`_id`), `gameid`=VALUES(`gameid`), `name`=VALUES(`name`);


-- Behaviour

DELIMITER #
CREATE EVENT IF NOT EXISTS banlist_cleanup
   ON SCHEDULE EVERY 1 MINUTE
   COMMENT 'Removes expired ban entries'
   DO DELETE FROM bans WHERE expire < CURRENT_TIMESTAMP AND expire <> NULL#

CREATE EVENT IF NOT EXISTS serverlist_cleanup
   ON SCHEDULE EVERY 1 MINUTE
   COMMENT 'Removes server entries older than 20 minutes'
   DO BEGIN
   DELETE FROM servers WHERE updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 MINUTE);
   DELETE `servers` FROM `servers` JOIN `bans` WHERE INET6_ATON(`servers`.`host`) =  INET6_ATON(`bans`.`host`);
   END#

-- DROP TRIGGER IF EXISTS `serverslist_bancleanup_insert`#
-- CREATE OR REPLACE TRIGGER `serverslist_bancleanup_insert`
   -- AFTER INSERT
   -- ON `servers` FOR EACH ROW
   -- BEGIN
   -- -- Delete based on direct IP match for now (blame MariaDB's lack of bitwise byte operations)
   -- -- DELETE `servers` FROM `servers` JOIN `bans` WHERE ( INET6_ATON(`servers`.`host`) & INET6_ATON(`bans`.`subnetmask`) ) = ( INET6_ATON(`bans`.`host`) & INET6_ATON(`bans`.`subnetmask`) );
   -- DELETE `servers` FROM `servers` JOIN `bans` WHERE INET6_ATON(`servers`.`host`) =  INET6_ATON(`bans`.`host`);
   -- END
   -- #

-- DROP TRIGGER IF EXISTS `serverslist_bancleanup_insert`#
-- CREATE OR REPLACE TRIGGER `serverslist_bancleanup_update`
   -- AFTER UPDATE
   -- ON `servers` FOR EACH ROW
   -- BEGIN
   -- -- Delete based on direct IP match for now (blame MariaDB's lack of bitwise byte operations)
   -- -- DELETE `servers` FROM `servers` JOIN `bans` WHERE ( INET6_ATON(`servers`.`host`) & INET6_ATON(`bans`.`subnetmask`) ) = ( INET6_ATON(`bans`.`host`) & INET6_ATON(`bans`.`subnetmask`) );
   -- DELETE `servers` FROM `servers` JOIN `bans` WHERE INET6_ATON(`servers`.`host`) =  INET6_ATON(`bans`.`host`);
   -- END
   -- #

CREATE OR REPLACE TRIGGER `roomlist_rebuild_insert`
   AFTER INSERT
   ON `servers` FOR EACH ROW
   BEGIN
   DELETE FROM `rooms` WHERE _id > 99;
   INSERT INTO `rooms` (`_id`,`roomname`,`origin`) SELECT DISTINCT ROW_NUMBER() OVER ()+100 AS `_id`,`roomname`,`origin` FROM `servers` WHERE `origin` <> 'localhost' GROUP BY `roomname`;
   DELETE FROM `rooms` WHERE roomname = '' OR origin = '' ;
   END
   #

CREATE OR REPLACE TRIGGER `roomlist_rebuild_update`
   AFTER UPDATE
   ON `servers` FOR EACH ROW
   BEGIN
   DELETE FROM `rooms` WHERE _id > 99;
   INSERT INTO `rooms` (`_id`,`roomname`,`origin`) SELECT DISTINCT ROW_NUMBER() OVER ()+100 AS `_id`,`roomname`,`origin` FROM `servers` WHERE `origin` <> 'localhost' GROUP BY `roomname`;
   DELETE FROM `rooms` WHERE roomname = '' OR origin = '' ;
   END
   #

CREATE OR REPLACE TRIGGER `roomlist_rebuild_delete`
   AFTER DELETE
   ON `servers` FOR EACH ROW
   BEGIN
   DELETE FROM `rooms` WHERE _id > 99;
   INSERT INTO `rooms` (`_id`,`roomname`,`origin`) SELECT DISTINCT ROW_NUMBER() OVER ()+100 AS `_id`,`roomname`,`origin` FROM `servers` WHERE `origin` <> 'localhost' GROUP BY `roomname`;
   DELETE FROM `rooms` WHERE roomname = '' OR origin = '' ;
   END
   #

DELIMITER ;

-- Launching the server
INSERT INTO `rooms` (`_id`, `roomname`, `description`) VALUES (2, 'liquid', 'Default liquidMS room');
-- Enabling event scheduler
SET GLOBAL event_scheduler = ON


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
  `game` VARCHAR(32) DEFAULT NULL,
  `version` VARCHAR(16) NOT NULL,
  `origin` VARCHAR(64) NOT NULL DEFAULT 'localhost',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`host`,`port`)
);


CREATE TABLE IF NOT EXISTS `$versiontabname` (
  `game` VARCHAR(64) NOT NULL,
  `version_id` INT(11) NOT NULL,
  `version_name` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`game`)
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

-- Behaviour

DELIMITER #

CREATE EVENT IF NOT EXISTS $prefix_serverlist_cleanup
   ON SCHEDULE EVERY 1 MINUTE
   COMMENT 'Removes server entries older than 20 minutes'
DO BEGIN
DELETE FROM `$servtabname` WHERE updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 MINUTE);
DELETE `$servtabname` FROM `$servtabname` JOIN `$bantabname` WHERE INET6_ATON(`$servtabname`.`host`) BETWEEN  INET6_ATON(`$bantabname`.`ip_start`) and INET6_ATON(`$bantabname`.`ip_end`) ;
END#

-- 'Removes expired ban entries'
CREATE TRIGGER IF NOT EXISTS $prefix_banlist_cleanup
   BEFORE INSERT ON `$servtabname` FOR EACH ROW -- BEFORE to support temp bans
   BEGIN
   DELETE FROM `$bantabname` WHERE expire < CURRENT_TIMESTAMP AND expire <> NULL;
   END#

DELIMITER ;

-- Custom data
$gamelist

$custompermabans

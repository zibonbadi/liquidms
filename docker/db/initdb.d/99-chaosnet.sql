-- LiquidMS - federated master server
-- Copyright (C) 2021-2026 Zibon Badi et al.
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

USE `liquidms`;

CREATE TABLE IF NOT EXISTS `chaosnet_netgames` (
  `id`              VARCHAR(64)  NOT NULL,
  `host`            VARCHAR(45)  NOT NULL,
  `port`            SMALLINT UNSIGNED NOT NULL,
  `api_name`        VARCHAR(32)  NOT NULL,
  `api_data`        JSON         DEFAULT NULL,
  `external_origin` VARCHAR(256) DEFAULT NULL,
  `origin_node`     VARCHAR(256) DEFAULT NULL,
  `path`            JSON         DEFAULT NULL,
  `updated_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_synced_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_updated` (`last_synced_at` DESC),
  INDEX `idx_api` (`api_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chaosnet_follows` (
  `actor_uri`    VARCHAR(256) NOT NULL,
  `inbox_uri`    VARCHAR(256) NOT NULL,
  `state`        ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `direction`    ENUM('inbound','outbound') NOT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`actor_uri`, `direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chaosnet_outbox` (
  `id`           VARCHAR(256) NOT NULL,
  `type`         VARCHAR(32)  NOT NULL,
  `actor`        VARCHAR(256) NOT NULL,
  `object`       JSON         NOT NULL,
  `published`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_published` (`published` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER #

CREATE EVENT IF NOT EXISTS chaosnet_netgames_cull
ON SCHEDULE EVERY 1 MINUTE
COMMENT 'Removes chaosnet netgame entries not synced within 20 minutes'
DO
BEGIN
   DELETE FROM chaosnet_netgames WHERE last_synced_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 20 MINUTE);
END#

DELIMITER ;

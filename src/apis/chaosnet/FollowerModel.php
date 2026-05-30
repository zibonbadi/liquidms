<?php
# LiquidMS - distributable SRB2 master server
# Copyright (C) 2021-2025 Zibon Badi et al.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

namespace LiquidMS;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/ConfigModel.php';
require_once __DIR__.'/DBSingleton.php';

use LiquidMS\DBSingleton;
use LiquidMS\ConfigModel;

class FollowerModel{

	public static function addFollower(string $actorUri, string $inboxUri, string $direction): array{
		$query = "INSERT INTO chaosnet_follows (actor_uri, inbox_uri, state, direction, created_at)
		          VALUES (:actor_uri, :inbox_uri, 'accepted', :direction, NOW())
		          ON DUPLICATE KEY UPDATE state = 'accepted', inbox_uri = VALUES(inbox_uri)";
		return DBSingleton::execute($query, [
			":actor_uri" => $actorUri,
			":inbox_uri" => $inboxUri,
			":direction" => $direction,
		]);
	}

	public static function removeFollower(string $actorUri, string $direction): array{
		return DBSingleton::execute(
			"DELETE FROM chaosnet_follows WHERE actor_uri = :actor_uri AND direction = :direction",
			[":actor_uri" => $actorUri, ":direction" => $direction]
		);
	}

	public static function acceptFollow(string $followId): array{
		return DBSingleton::execute(
			"UPDATE chaosnet_follows SET state = 'accepted' WHERE actor_uri = :actor_uri AND direction = 'inbound'",
			[":actor_uri" => $followId]
		);
	}

	public static function rejectFollow(string $followId): array{
		return DBSingleton::execute(
			"UPDATE chaosnet_follows SET state = 'rejected' WHERE actor_uri = :actor_uri AND direction = 'inbound'",
			[":actor_uri" => $followId]
		);
	}

	public static function getFollowers(): array{
		$result = DBSingleton::execute(
			"SELECT actor_uri, inbox_uri FROM chaosnet_follows WHERE direction = 'inbound' AND state = 'accepted'"
		);
		if($result === false || $result["error"] != 0){
			return ["error" => 0, "data" => []];
		}
		return $result;
	}

	public static function getFollowing(): array{
		$result = DBSingleton::execute(
			"SELECT actor_uri, inbox_uri FROM chaosnet_follows WHERE direction = 'outbound' AND state = 'accepted'"
		);
		if($result === false || $result["error"] != 0){
			return ["error" => 0, "data" => []];
		}
		return $result;
	}

	public static function getFollowerUris(): array{
		$result = self::getFollowers();
		$uris = [];
		foreach($result["data"] as $row){
			$uris[] = $row["actor_uri"];
		}
		return $uris;
	}

	public static function getInboxUris(): array{
		$result = self::getFollowers();
		$uris = [];
		foreach($result["data"] as $row){
			$uris[] = $row["inbox_uri"];
		}
		return $uris;
	}
}

?>

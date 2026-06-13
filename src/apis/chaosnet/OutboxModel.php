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

class OutboxModel{

	public static function recordActivity(string $type, $actor, $object, string $apiName): array{
		$config = ConfigModel::getConfig();
		$base = ConfigModel::getApiActorUri($apiName);
		$actorId = is_string($actor) ? $actor : ($actor["id"] ?? $base);
		$activityId = uniqid("{$base}/activities/", true);

		$activity = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => $activityId,
			"type" => $type,
			"actor" => $actorId,
			"object" => $object,
			"published" => date("c"),
		];

		$objectJson = json_encode($activity);

		return DBSingleton::execute(
			"INSERT INTO chaosnet_outbox (id, type, actor, api_name, object, published) VALUES (:id, :type, :actor, :api_name, :object, NOW())",
			[
				":id" => $activityId,
				":type" => $type,
				":actor" => $actorId,
				":api_name" => $apiName,
				":object" => $objectJson,
			]
		);
	}

	public static function getOutbox(string $apiName, int $page = 1, int $pageSize = 20): array{
		$offset = ($page - 1) * $pageSize;
		$result = DBSingleton::execute(
			"SELECT * FROM chaosnet_outbox WHERE api_name = :api_name ORDER BY published DESC LIMIT :limit OFFSET :offset",
			[":api_name" => $apiName, ":limit" => $pageSize, ":offset" => $offset]
		);

		if($result === false || $result["error"] != 0){
			return ["error" => 1, "message" => "Database query failed", "data" => [], "rows" => 0];
		}

		$countResult = DBSingleton::execute(
			"SELECT COUNT(*) AS cnt FROM chaosnet_outbox WHERE api_name = :api_name",
			[":api_name" => $apiName]
		);
		$total = 0;
		if($countResult !== false && $countResult["error"] == 0){
			$total = (int)$countResult["data"][0]["cnt"];
		}

		$items = [];
		foreach($result["data"] as $row){
			$obj = json_decode($row["object"], true);
			if($obj === null){ continue; }
			$items[] = $obj;
		}

		$config = ConfigModel::getConfig();
		$base = ConfigModel::getApiActorUri($apiName);

		return [
			"error" => 0,
			"data" => [
				"@context" => "https://www.w3.org/ns/activitystreams",
				"id" => "{$base}/outbox?page={$page}",
				"type" => "OrderedCollectionPage",
				"partOf" => "{$base}/outbox",
				"totalItems" => $total,
				"orderedItems" => $items,
			],
			"rows" => count($items),
		];
	}
}

?>
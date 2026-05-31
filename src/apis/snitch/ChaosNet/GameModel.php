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

namespace LiquidMS\ChaosNet;

require_once __DIR__.'/../ConfigModel.php';
require_once __DIR__.'/../DBSingleton.php';

use LiquidMS\DBSingleton;
use LiquidMS\ConfigModel;

class GameModel{

	public static function generateId(string $host, int $port, string $apiName): string{
		return hash("sha256", "{$host}|{$port}|{$apiName}");
	}

	public static function normalizeName(string $name): string{
		$name = urldecode($name);
		$name = preg_replace('/[[:cntrl:]]/', '', $name);
		$name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
		return $name;
	}

	public static function getAllGames(int $page = 1, int $pageSize = 50): array{
		$offset = ($page - 1) * $pageSize;
		$query = "SELECT * FROM chaosnet_netgames ORDER BY updated_at DESC, host ASC, port ASC LIMIT :limit OFFSET :offset";
		$result = DBSingleton::execute($query, [":limit" => $pageSize, ":offset" => $offset]);
		if($result === false || $result["error"] != 0){
			return ["error" => 1, "message" => "Database query failed", "data" => [], "rows" => 0];
		}

		$games = [];
		foreach($result["data"] as $row){
			$apiData = json_decode($row["api_data"] ?? "{}", true);
			$name = self::normalizeName($apiData["name"] ?? "");
			$games[] = self::rowToGameObject($row, $name);
		}

		return [
			"error" => 0,
			"data" => $games,
			"rows" => count($games),
			"total" => self::count(),
		];
	}

	public static function getSRB2Games(int $page = 1, int $pageSize = 50): array{
		$offset = ($page - 1) * $pageSize;
		$query = "SELECT host, port, ".
						"CASE WHEN api_name = 'srb2http' THEN JSON_UNQUOTE(JSON_EXTRACT(api_data,'$.servername')) WHEN api_name = 'srb2kart' THEN JSON_UNQUOTE(JSON_EXTRACT(api_data, '$.contact')) END AS servername, ".
						"CASE WHEN api_name = 'srb2http' THEN JSON_UNQUOTE(JSON_EXTRACT(api_data,'$.version')) WHEN api_name = 'srb2kart' THEN 'SRB2Kart' END AS version, ".
						"CASE WHEN api_name = 'srb2http' THEN JSON_UNQUOTE(JSON_EXTRACT(api_data,'$.roomname')) WHEN api_name = 'srb2kart' THEN JSON_UNQUOTE(JSON_EXTRACT(api_data, '$.game')) END AS roomname, ".
						"external_origin AS origin ".
					"FROM chaosnet_netgames ".
					"WHERE api_name = 'srb2http' OR api_name = 'srb2kart' ".
					"ORDER BY updated_at DESC, host ASC, port ASC;";

		$result = DBSingleton::execute($query);
		if($result === false || $result["error"] != 0){
			return ["error" => 1, "message" => "Database query failed", "data" => [], "rows" => 0];
		}

		// Skip rowToGameObject() because the table already looks like we want it to. 

		return [
			"error" => 0,
			"data" => $result["data"],
			"rows" => count($result["data"]),
			"total" => self::count(),
		];
	}


	public static function count(): int{
		$result = DBSingleton::execute("SELECT COUNT(*) AS cnt FROM chaosnet_netgames");
		if($result === false || $result["error"] != 0){ return 0; }
		return (int)$result["data"][0]["cnt"];
	}

	public static function upsertGame(array $game, string $originNode, ?string $externalOrigin = null, array $path = []): array{
		$host = $game["host"] ?? "";
		$port = (int)($game["port"] ?? 0);
		$apiName = $game["api_name"] ?? "unknown";
		$id = self::generateId($host, $port, $apiName);

		$apiData = $game["api_data"] ?? [];
		
		/*
		foreach($game as $k => $v){
			if(!in_array($k, ["host", "port", "api_name", "_api", "_origin", "external_origin", "origin_node", "path", "id"])){
				$apiData[$k] = $v;
			}
		}

		/*
		$name = self::normalizeName($apiData["name"] ?? $apiData["servername"] ?? "");
		$apiData["name"] = $name;
		*/

		$pathJson = json_encode($path);
		$apiDataJson = json_encode($apiData);

		$existing = DBSingleton::execute("SELECT origin_node, path FROM chaosnet_netgames WHERE id = :id", [":id" => $id]);
		$finalOriginNode = $originNode;
		$finalPath = $path;
		if($existing !== false && $existing["error"] == 0 && $existing["rows"] > 0){
			$finalOriginNode = $existing["data"][0]["origin_node"] ?? $originNode;
			$existingPath = json_decode($existing["data"][0]["path"] ?? "[]", true);
			if(is_array($existingPath)){
				foreach($existingPath as $hop){
					if(!in_array($hop, $finalPath)){
						$finalPath[] = $hop;
					}
				}
			}
			$pathJson = json_encode($finalPath);
		}

		$query = "INSERT INTO chaosnet_netgames (id, host, port, api_name, api_data, external_origin, origin_node, path)
		          VALUES (:id, :host, :port, :api_name, :api_data, :external_origin, :origin_node, :path)
		          ON DUPLICATE KEY UPDATE
		            host = VALUES(host),
		            port = VALUES(port),
		            api_name = VALUES(api_name),
		            api_data = VALUES(api_data),
		            external_origin = VALUES(external_origin),
		            origin_node = VALUES(origin_node),
		            path = VALUES(path)";

		return DBSingleton::execute($query, [
			":id" => $id,
			":host" => $host,
			":port" => $port,
			":api_name" => $apiName,
			":api_data" => $apiDataJson,
			":external_origin" => $externalOrigin ?? $game["external_origin"] ?? NULL,
			":origin_node" => $finalOriginNode,
			":path" => $pathJson,
		]);
	}

	public static function deleteGame(string $host, int $port, string $apiName): array{
		$id = self::generateId($host, $port, $apiName);
		return DBSingleton::execute("DELETE FROM chaosnet_netgames WHERE id = :id", [":id" => $id]);
	}

	public static function deleteGameById(string $id): array{
		return DBSingleton::execute("DELETE FROM chaosnet_netgames WHERE id = :id", [":id" => $id]);
	}

	public static function rowToGameObject(array $row, string $name): array{
		$apiData = json_decode($row["api_data"] ?? "{}", true);
		$path = json_decode($row["path"] ?? "[]", true);
		$scheme = ($_SERVER["REQUEST_SCHEME"] ?? "https");
		$host = ($_SERVER["HTTP_HOST"] ?? "localhost");

		$obj = [
			"type" => "Game",
			"id" => "{$scheme}://{$host}{$row["id"]}",
			"name" => $name,
			"game_host" => $row["host"],
			"game_port" => (int)$row["port"],
			"game_api_name" => $row["api_name"],
			"game_api_data" => $apiData,
			"updated" => $row["updated_at"],
		];

		if(!empty($row["external_origin"])){
			$obj["external_origin"] = $row["external_origin"];
		}
		if(!empty($row["origin_node"])){
			$obj["origin_node"] = $row["origin_node"];
		}
		if(!empty($path)){
			$obj["path"] = $path;
		}

		return $obj;
	}
}

?>

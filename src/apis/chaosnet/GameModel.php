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

class GameModel{

	public static function generateId(string $host, int $port, string $apiName): string{
		return hash("sha256", "{$host}|{$port}|{$apiName}");
	}

	public static function normalizeName(string $name): string{
		$name = urldecode($name);
		$name = preg_replace('/[[:cntrl:]]/', '', $name);
		$name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
		$name = rawurlencode($name);
		return $name;
	}

	public static function getAllGames(int $page = 1, int $pageSize = 50, array $filters = []): array{
		$offset = ($page - 1) * $pageSize;
		[$whereSql, $whereParams] = self::buildFilterWhere($filters);
		$query = "SELECT * FROM chaosnet_netgames WHERE state IN ('new', 'active'){$whereSql} ORDER BY updated_at DESC, host ASC, port ASC LIMIT :limit OFFSET :offset";
		$params = array_merge($whereParams, [":limit" => $pageSize, ":offset" => $offset]);
		$result = DBSingleton::execute($query, $params);
		if($result === false || $result["error"] != 0){
			return ["error" => 1, "message" => "Database query failed", "data" => [], "rows" => 0];
		}

		$games = [];
		foreach($result["data"] as $row){
			$apiData = json_decode($row["api_data"] ?? "{}", true);
			$name = self::normalizeName($row["name"] ?? "");
			$games[] = self::rowToGameObject($row, $name);
		}

		return [
			"error" => 0,
			"data" => $games,
			"rows" => count($games),
			"total" => self::count($filters),
		];
	}

	public static function count(array $filters = []): int{
		[$whereSql, $whereParams] = self::buildFilterWhere($filters);
		$result = DBSingleton::execute("SELECT COUNT(*) AS cnt FROM chaosnet_netgames WHERE state IN ('new', 'active'){$whereSql}", $whereParams);
		if($result === false || $result["error"] != 0){ return 0; }
		return (int)$result["data"][0]["cnt"];
	}

	public static function upsertGame(array $game, string $originNode, ?string $externalOrigin = null, array $path = [], string $state = 'active'): array{
		
		$host = $game["game_host"] ?? "";
		$port = (int)($game["game_port"] ?? 0);
		$name = self::normalizeName($game["name"]) ?? "";
		$apiName = $game["game_api_name"] ?? "unknown";
		$id = self::generateId($host, $port, $apiName);

		$apiData = $game["game_api_data"] ?? [];

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

		$query = "INSERT INTO chaosnet_netgames (id, host, port, name, api_name, api_data, external_origin, origin_node, path, state)
		          VALUES (:id, :host, :port, :name, :api_name, :api_data, :external_origin, :origin_node, :path, :state)
		          ON DUPLICATE KEY UPDATE
		            host = VALUES(host),
		            port = VALUES(port),
		            name = VALUES(name),
		            api_name = VALUES(api_name),
		            api_data = VALUES(api_data),
		            external_origin = VALUES(external_origin),
		            origin_node = VALUES(origin_node),
		            path = VALUES(path),
		            state = VALUES(state)";

		return DBSingleton::execute($query, [
			":id" => $id,
			":host" => $host,
			":port" => $port,
			":name" => $name,
			":api_name" => $apiName,
			":api_data" => $apiDataJson,
			":external_origin" => $externalOrigin ?? $game["external_origin"] ?? NULL,
			":origin_node" => $finalOriginNode,
			":path" => $pathJson,
			":state" => $state,
		]);
	}

	public static function deleteGame(string $host, int $port, string $apiName): array{
		$id = self::generateId($host, $port, $apiName);
		return self::deleteGameById($id);
	}

	public static function deleteGameById(string $id): array{
		return DBSingleton::execute("UPDATE chaosnet_netgames SET state = 'deleted', last_synced_at = NOW() WHERE id = :id", [":id" => $id]);
	}

	public static function updateGameState(string $id, string $state): array{
		return DBSingleton::execute(
			"UPDATE chaosnet_netgames SET state = :state, last_synced_at = NOW() WHERE id = :id",
			[":id" => $id, ":state" => $state]
		);
	}

	private static function buildFilterWhere(array $filters): array{
		if(empty($filters)){
			return ["", []];
		}

		$columnMap = [
			'name' => 'name',
			'game_host' => 'host',
			'game_port' => 'port',
			'game_api_name' => 'api_name',
			'external_origin' => 'external_origin',
			'origin_node' => 'origin_node',
		];

		$clauses = [];
		$params = [];
		$idx = 0;

		foreach($filters as $filter){
			$path = $filter["path"] ?? "";
			$value = $filter["value"] ?? "";
			$idx++;

			$column = null;
			$isJson = false;

			if(str_starts_with($path, 'game_api_data.')){
				$column = 'api_data';
				$isJson = true;
			}elseif(isset($columnMap[$path])){
				$column = $columnMap[$path];
			}

			if($column === null){
				$clauses[] = "1=0";
				continue;
			}

			$paramKey = ":filter_{$idx}";
			$hasWildcard = str_contains($value, '*');

			if($isJson){
				$jsonSubPath = substr($path, strlen('game_api_data.'));
				if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $jsonSubPath)){
					$clauses[] = "1=0";
					continue;
				}
				$jsonExpr = "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$jsonSubPath}'))";
				if($hasWildcard){
					$clauses[] = "{$jsonExpr} LIKE {$paramKey}";
					$params[$paramKey] = str_replace('*', '%', $value);
				}else{
					$clauses[] = "{$jsonExpr} = {$paramKey}";
					$params[$paramKey] = $value;
				}
			}else{
				if($hasWildcard){
					$clauses[] = "{$column} LIKE {$paramKey}";
					$params[$paramKey] = str_replace('*', '%', $value);
				}else{
					if($column === 'port'){
						$params[$paramKey] = (int)$value;
					}else{
						$params[$paramKey] = $value;
					}
					$clauses[] = "{$column} = {$paramKey}";
				}
			}
		}

		return [" AND " . implode(" AND ", $clauses), $params];
	}

	public static function rowToGameObject(array $row, string $name): array{
		$base = \LiquidMS\ConfigModel::getConfig()["basepath"];
		$apiData = json_decode($row["api_data"] ?? "{}", true);
		$path = json_decode($row["path"] ?? "[]", true);
		$scheme = ($_SERVER["REQUEST_SCHEME"] ?? "https");
		$host = ($_SERVER["HTTP_HOST"] ?? "localhost");

		$obj = [
			"type" => "Game",
			"id" => "{$scheme}://{$host}{$base}/collection/{$row["id"]}",
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

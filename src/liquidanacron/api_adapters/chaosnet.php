<?php
# LiquidMS - federated master server
# Copyright (C) 2021-2026 Zibon Badi et al.
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

namespace LiquidAnacron\API\chaosnet;

function fetch(array $config, array $job = []){
	$rVal = [];

	$stream_context = null;
	if(array_key_exists("http-header",$job)){
		$stream_context = \LiquidAnacron\fetch_mkContext("GET", $job["http-header"]);
	}else{
		$stream_context = \LiquidAnacron\fetch_mkContext("GET");
	}

	$baseUrl = rtrim($job["host"], "/");
	$page = 1;
	$pagesize = 200;
	$seen = [];

	do{
		$url = "{$baseUrl}/collection?page={$page}&pagesize={$pagesize}";
		$response = file_get_contents($url, false, $stream_context);
		if($response === FALSE){
			echo "[".date(\DateTime::ISO8601, time())."] ERROR: HTTP request failed for {$url}\n";
			break;
		}

		$collection = json_decode($response, true);
		if($collection === NULL){
			echo "[".date(\DateTime::ISO8601, time())."] ERROR: Invalid JSON from {$url}\n";
			break;
		}

		$items = $collection["items"] ?? [];
		if(!is_array($items) || empty($items)){
			break;
		}

		foreach($items as $item){
			if(!is_array($item)){ continue; }

			$host = $item["game_host"] ?? "";
			$port = $item["game_port"] ?? 0;
			$gameName = $item["name"] ?? "unknown";
			$apiName = $item["game_api_name"] ?? "unknown";
			$apiData = $item["game_api_data"] ?? [];
			
			// Skip redundant entries //
			$dedupKey = "{$host}:{$port}:{$apiName}";
			if(isset($seen[$dedupKey])){ continue; }
			$seen[$dedupKey] = true;

			// Now that we know the dedupKey, fill in the SHA256 ID
			$gameId = $item["id"] ?? hash("sha256", "{$host}|{$host}|{$apiName}");

			// Use full ChaosNet object for completeness' sake
			$row = $item;
			// Fill in critical fields
			$row["id"] = $gameId;
			$row["name"] = $gameName;
			$row["game_host"] = $host;
			$row["game_port"] = $port;
			$row["game_api_name"] = $apiName;
			$row["game_api_data"] = $apiData;
			//$row["updated"] is being handled by $row = $item;

			/*
			$row = [
				"_api" => $apiName,
				"_origin" => $item["external_origin"] ?? $item["origin_node"] ?? parse_url($job["host"])["host"] ?? "",
				"host" => $host,
				"port" => $port,
				"servername" => $item["name"] ?? $apiData["name"] ?? $apiData["servername"] ?? "Unknown",
				"version" => $apiData["version"] ?? $item["game_api_version"] ?? "",
				"roomname" => $apiData["roomname"] ?? $apiData["room"] ?? "",
			];
			foreach($item as $k => $v){
				if(!in_array($k, ["host", "port", "api_name", "name", "external_origin", "origin_node", "path", "updated", "type", "id", "game_host", "game_port", "game_api_name", "game_api_data", "game_api_version"])){
					$row[$k] = $v;
				}
			}
			*/

			$rVal[] = $row;
		}

		$page++;
		$totalItems = $collection["totalItems"] ?? 0;
		$maxPages = max(1, (int)ceil($totalItems / $pagesize));
	}while($page <= $maxPages);

	return $rVal;
}

function snitch(Array $data, String $url){
	$config = \LiquidMS\ConfigModel::getConfig();

	$nodeActor = $config["node_actor_uri"] ?? null;
	if($nodeActor === null){
		$nodeHost = $config["node_host"] ?? null;
		# Later: Skip "id" when no node_host available
		if($nodeHost !== NULL)
			$nodeActor = "https://{$nodeHost}";
	}

	$items = [];
	foreach($data as $netgame){
		$gameHost = $netgame["game_host"] ?? "";
		$gamePort = $netgame["game_port"] ?? 0;
		$apiName = $netgame["game_api_name"] ?? "unknown";
		$name = $netgame["name"] ?? "Unknown";

		$apiData = $netgame["game_api_data"];

		$game = [
			"type" => "Game",
			"id" => hash("sha256", "{$gameHost}|{$gamePort}|{$apiName}"),
			"name" => $name,
			"game_host" => $gameHost,
			"game_port" => (int)$gamePort,
			"game_api_name" => $apiName,
			"game_api_data" => $apiData,
			"updated" => $netgame["updated"] ?? date(\DateTime::ISO8601, time())
		];

		$ext_origin = $netgame["external_origin"] ?? null;
		if($ext_origin !== null && $ext_origin !== "" && $ext_origin !== "localhost"){
			$game["external_origin"] = $ext_origin;
		}
		
		$origin_node = $netgame["origin_node"] ?? null;
		if($origin_node !== null && $origin_node !== "" && $origin_node !== "localhost"){
			$game["origin_node"] = $origin_node;
		}

		$items[] = $game;
	}

	if(empty($items)){
		return "[".date(\DateTime::ISO8601, time())."] No netgames to snitch to \"{$url}\".\n";
	}

	$deliverActivity = [
		"@context" => "https://www.w3.org/ns/activitystreams",
		"type" => "Deliver",
		"object" => [
			"type" => "OrderedCollection",
			"items" => $items,
		],
	];

	# Skip "id" field when no node_host available
	if($nodeActor !== null && $nodeActor !== "" && $nodeActor !== "localhost"){
		$deliverActivity["actor"] = $nodeActor;
	}

	$payload = json_encode($deliverActivity, JSON_UNESCAPED_SLASHES);

	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $payload,
		CURLOPT_HTTPHEADER => [
			"Content-Type: application/activity+json",
			"Content-Length: " . strlen($payload),
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 30,
	]);

	$http_response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return "[".date(\DateTime::ISO8601, time())."] ChaosNet snitch to {$url} returned {$http_code}: {$http_response}\n";
}

?>

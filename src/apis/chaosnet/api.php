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

require_once __DIR__.'/ActorModel.php';
require_once __DIR__.'/CollectionModel.php';
require_once __DIR__.'/InboxModel.php';
require_once __DIR__.'/OutboxModel.php';
require_once __DIR__.'/FollowerModel.php';

use LiquidMS\ActorModel;
use LiquidMS\CollectionModel;
use LiquidMS\InboxModel;
use LiquidMS\OutboxModel;
use LiquidMS\FollowerModel;
use LiquidMS\ConfigModel;

$basepath = "/";
if(LiquidMS\ConfigModel::getConfig()["basepath"]){
	$basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/");
}

$router->with("{$basepath}", function() use ($router){

	$router->respond('GET', '/?', function($request, $response){
		$response->header('Content-Type', 'application/activity+json');
		$actor = ActorModel::getActorDocument();
		$response->json($actor);
	});

	$router->respond('POST', '/inbox', function($request, $response){
		$body = $request->body();
		if(empty($body)){
			$response->code(400);
			$response->json(["error" => 400, "message" => "Empty request body"]);
			return;
		}

		$activity = json_decode($body, true);
		if($activity === null){
			$response->code(400);
			$response->json(["error" => 400, "message" => "Invalid JSON"]);
			return;
		}

		$result = InboxModel::processActivity($activity);

		if(($result["error"] ?? 0) != 0){
			$response->code(($result["error"] >= 400 && $result["error"] < 600) ? $result["error"] : 500);
			$response->json($result);
			return;
		}

		$response->code(202);
		$response->json($result);
	});

	$router->respond('GET', '/inbox', function($request, $response){
		$response->header('Content-Type', 'application/activity+json');
		$config = ConfigModel::getConfig();
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";

		$response->json([
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => "{$base}/inbox",
			"type" => "OrderedCollection",
			"totalItems" => 0,
			"orderedItems" => [],
		]);
	});

	$router->respond('GET', '/outbox', function($request, $response){
		$page = (int)($request->param("page", 1));
		$pageSize = (int)($request->param("pagesize", 20));
		if($pageSize > 100){ $pageSize = 100; }

		$result = OutboxModel::getOutbox($page, $pageSize);

		$response->header('Content-Type', 'application/activity+json');
		if($result["error"] != 0){
			$response->code(500);
			$response->json($result);
			return;
		}

		$response->json($result["data"]);
	});

	$router->respond('GET', '/collection', function($request, $response){
		$page = (int)($request->param("page", 1));
		$pageSize = (int)($request->param("pagesize", 50));
		if($pageSize > 200){ $pageSize = 200; }

		// Manually parsing query strings to get around PHP being stupid
		$query_params = [];
		foreach(explode('&', $request->server()['QUERY_STRING'] ?? '') as $pair){
			if($pair === ''){continue;}
			$parts = explode('=', $pair, 2);
			$key = urldecode($parts[0]);
			$value = isset($parts[1]) ? urldecode($parts[1]): '';
			$query_params[$key] = $value;
		}

		$filters = [];
		foreach($query_params as $key => $value){
			if(str_starts_with($key, 'json.')){
				$filters[] = ['path' => substr($key, 5), 'value' => $value];
			}
		}

		$result = CollectionModel::getCollection($page, $pageSize, $filters);

		$response->header('Content-Type', 'application/activity+json');
		if($result["error"] != 0){
			$response->code(500);
			$response->json($result);
			return;
		}

		$response->json($result["data"]);
	});

	$router->respond('GET', '/following', function($request, $response){
		$response->header('Content-Type', 'application/activity+json');
		$config = ConfigModel::getConfig();
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";

		$following = FollowerModel::getFollowing();
		$items = [];
		foreach($following["data"] as $row){
			$items[] = $row["actor_uri"];
		}

		$response->json([
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => "{$base}/following",
			"type" => "Collection",
			"totalItems" => count($items),
			"items" => $items,
		]);
	});

	$router->respond('GET', '/followers', function($request, $response){
		$response->header('Content-Type', 'application/activity+json');
		$config = ConfigModel::getConfig();
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";

		$followers = FollowerModel::getFollowers();
		$items = [];
		foreach($followers["data"] as $row){
			$items[] = $row["actor_uri"];
		}

		$response->json([
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => "{$base}/followers",
			"type" => "Collection",
			"totalItems" => count($items),
			"items" => $items,
		]);
	});
});

?>

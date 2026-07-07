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
require_once __DIR__.'/GameModel.php';
require_once __DIR__.'/FollowerModel.php';
require_once __DIR__.'/OutboxModel.php';

use LiquidMS\DBSingleton;
use LiquidMS\ConfigModel;

class InboxModel{

	const MAX_FORWARD_PER_INBOX = 50;

	public static function processActivity(array $activity, string $apiName): array{
		$type = $activity["type"] ?? null;
		if($type === null){
			return ["error" => 400, "message" => "Missing activity type"];
		}

		$actor = $activity["actor"] ?? null;
		$object = $activity["object"] ?? null;

		if($actor === null){
			return ["error" => 400, "message" => "Missing actor"];
		}

		self::processPendingRows(true);

		if(self::actorInPath($activity, $apiName)){
			$config = ConfigModel::getConfig();
			if($config["loglevel"] == "verbose"){
				error_log("Chaosnet: skipping activity {$type} from {$actor} — already in path");
			}
			return ["error" => 0, "message" => "Skipped (already in path)"];
		}

		switch($type){
		case "Create":
			return self::handleCreate($actor, $object, $activity, $apiName);
		case "Update":
			return self::handleUpdate($actor, $object, $activity, $apiName);
		case "Delete":
			return self::handleDelete($actor, $object, $activity, $apiName);
		case "Deliver":
			return self::handleDeliver($actor, $object, $activity, $apiName);
		case "Follow":
			return self::handleFollow($actor, $object, $activity, $apiName);
		case "Accept":
			return self::handleAccept($actor, $object, $activity, $apiName);
		case "Reject":
			return self::handleReject($actor, $object, $activity, $apiName);
		case "Undo":
			return self::handleUndo($actor, $object, $activity, $apiName);
		default:
			return ["error" => 400, "message" => "Unsupported activity type: {$type}"];
		}
	}

	public static function processPendingRows(bool $forward = true): array{
		$config = ConfigModel::getConfig();
		$limit = $forward ? self::MAX_FORWARD_PER_INBOX : 1000;
		$result = DBSingleton::execute(
			"SELECT * FROM chaosnet_netgames WHERE state IN ('new', 'stale') ORDER BY last_synced_at ASC LIMIT :limit",
			[":limit" => $limit]
		);
		if($result === false || $result["error"] != 0 || $result["rows"] == 0){
			return ["error" => 0, "processed" => 0];
		}

		$count = 0;
		$logLines = [];
		foreach($result["data"] as $row){
			$gameId = $row["id"];
			$apiName = $row["api_name"];
			$thisActor = ConfigModel::getApiActorUri($apiName);
			$gameObj = GameModel::rowToGameObject($row, GameModel::normalizeName($row["name"] ?? ""));

			if($row["state"] === 'new'){
				$priorResult = DBSingleton::execute(
					"SELECT COUNT(*) AS cnt FROM chaosnet_outbox WHERE JSON_EXTRACT(object, '$.id') = :id AND type IN ('Create', 'Update') AND api_name = :api_name",
					[":id" => $gameObj["id"], ":api_name" => $apiName]
				);
				$hasPrior = ($priorResult !== false && $priorResult["error"] == 0 && ($priorResult["data"][0]["cnt"] ?? 0) > 0);
				$activityType = $hasPrior ? "Update" : "Create";
				OutboxModel::recordActivity($activityType, $thisActor, $gameObj, $apiName);
				if($forward){
					$activity = ["type" => $activityType, "actor" => $thisActor, "object" => $gameObj];
					self::forwardActivityToFollowers($activity, $apiName);
				}
				GameModel::updateGameState($gameId, 'active');
			}elseif($row["state"] === 'stale'){
				OutboxModel::recordActivity("Delete", $thisActor, $gameObj, $apiName);
				if($forward){
					$activity = ["type" => "Delete", "actor" => $thisActor, "object" => $gameObj];
					self::forwardActivityToFollowers($activity, $apiName);
				}
				GameModel::updateGameState($gameId, 'deleted');
			}
			$count++;
		}

		if($config["loglevel"] == "verbose"){
			error_log("Chaosnet: processPendingRows processed {$count} rows (forward=" . ($forward ? "true" : "false") . ")");
		}

		return ["error" => 0, "processed" => $count];
	}

	private static function actorInPath(array $activity, string $apiName): bool{
		$thisActor = ConfigModel::getApiActorUri($apiName);

		$object = $activity["object"] ?? [];
		if(isset($object["path"]) && is_array($object["path"])){
			if(in_array($thisActor, $object["path"])){
				return true;
			}
		}
		if($activity["type"] === "Deliver" && isset($object["items"]) && is_array($object["items"])){
			foreach($object["items"] as $item){
				if(isset($item["path"]) && is_array($item["path"]) && in_array($thisActor, $item["path"])){
					return true;
				}
			}
		}
		return false;
	}

	private static function getOriginNode(array $activity, string $apiName): string{
		$config = ConfigModel::getConfig();
		$defaultActor = ConfigModel::getApiActorUri($apiName);
		$actor = $activity["actor"] ?? $defaultActor;
		return is_string($actor) ? $actor : ($actor["id"] ?? json_encode($actor));
	}

	private static function getExternalOrigin(array $object, array $activity): ?string{
		return $object["external_origin"] ?? $activity["external_origin"] ?? $object["_origin"] ?? null;
	}

	private static function getPath(array $object, array $activity, string $apiName): array{
		$thisActor = ConfigModel::getApiActorUri($apiName);
		$path = $object["path"] ?? $activity["path"] ?? [];
		if(!is_array($path)){ $path = []; }
		if(!in_array($thisActor, $path)){
			$path[] = $thisActor;
		}
		return $path;
	}

	private static function validateApiMatch(array $object, string $apiName): bool{
		$objectApi = $object["game_api_name"] ?? null;
		if($objectApi !== null && $objectApi !== $apiName){
			return false;
		}
		return true;
	}

	private static function handleCreate($actor, $object, array $activity, string $apiName): array{
		if($object === null || !is_array($object)){
			return ["error" => 400, "message" => "Create requires an object"];
		}
		if(!self::validateApiMatch($object, $apiName)){
			return ["error" => 400, "message" => "Activity object game_api_name does not match this actor's API"];
		}
		$originNode = self::getOriginNode($activity, $apiName);
		$externalOrigin = self::getExternalOrigin($object, $activity);
		$path = self::getPath($object, $activity, $apiName);
		$result = GameModel::upsertGame($object, $originNode, $externalOrigin, $path);
		if(($result["error"] ?? 1) == 0){
			OutboxModel::recordActivity("Create", $actor, $object, $apiName);
			self::forwardActivityToFollowers($activity, $apiName);
		}
		return $result;
	}

	private static function handleUpdate($actor, $object, array $activity, string $apiName): array{
		if($object === null || !is_array($object)){
			return ["error" => 400, "message" => "Update requires an object"];
		}
		if(!self::validateApiMatch($object, $apiName)){
			return ["error" => 400, "message" => "Activity object game_api_name does not match this actor's API"];
		}
		$originNode = self::getOriginNode($activity, $apiName);
		$externalOrigin = self::getExternalOrigin($object, $activity);
		$path = self::getPath($object, $activity, $apiName);
		$result = GameModel::upsertGame($object, $originNode, $externalOrigin, $path);
		if(($result["error"] ?? 1) == 0){
			OutboxModel::recordActivity("Update", $actor, $object, $apiName);
			self::forwardActivityToFollowers($activity, $apiName);
		}
		return $result;
	}

	private static function handleDelete($actor, $object, array $activity, string $apiName): array{
		if($object === null || !is_array($object)){
			return ["error" => 400, "message" => "Delete requires an object"];
		}
		$host = $object["host"] ?? $object["game_host"] ?? "";
		$port = $object["port"] ?? $object["game_port"] ?? 0;
		$objectApiName = $object["api_name"] ?? $object["game_api_name"] ?? $apiName;
		$id = $object["id"] ?? null;
		if($id){
			$result = GameModel::deleteGameById($id);
		}else{
			$result = GameModel::deleteGame($host, (int)$port, $objectApiName);
		}
		if(($result["error"] ?? 1) == 0){
			OutboxModel::recordActivity("Delete", $actor, $object, $apiName);
			self::forwardActivityToFollowers($activity, $apiName);
		}
		return $result;
	}

	private static function handleDeliver($actor, $object, array $activity, string $apiName): array{
		if($object === null || !is_array($object)){
			return ["error" => 400, "message" => "Deliver requires an object"];
		}
		$items = $object["items"] ?? $object["netgames"] ?? [$object];
		if(!is_array($items)){ $items = [$items]; }

		$originNode = self::getOriginNode($activity, $apiName);
		$count = 0;
		$errors = [];

		foreach($items as $item){
			if(!is_array($item)){ continue; }
			if(!self::validateApiMatch($item, $apiName)){
				$errors[] = ["error" => 400, "message" => "Item game_api_name does not match this actor's API"];
				continue;
			}
			$externalOrigin = self::getExternalOrigin($item, $activity);
			$path = self::getPath($item, $activity, $apiName);
			$result = GameModel::upsertGame($item, $originNode, $externalOrigin, $path);
			if($result !== false && ($result["error"] ?? 1) == 0){
				$count++;
				$forwardActivity = [
					"type" => "Update",
					"actor" => $actor,
					"object" => $item,
				];
				self::forwardActivityToFollowers($forwardActivity, $apiName);
			}else{
				$errors[] = $result;
			}
		}

		OutboxModel::recordActivity("Deliver", $actor, ["items_count" => $count, "total" => count($items)], $apiName);

		if(!empty($errors)){
			return ["error" => 1, "message" => "{$count}/" . count($items) . " games upserted with errors", "errors" => $errors];
		}
		return ["error" => 0, "message" => "{$count} games upserted", "rows" => $count];
	}

	private static function handleFollow($actor, $object, array $activity, string $apiName): array{
		if($object === null){
			return ["error" => 400, "message" => "Follow requires an object"];
		}
		$targetId = is_string($object) ? $object : ($object["id"] ?? null);
		if($targetId === null){
			return ["error" => 400, "message" => "Follow target must have an id"];
		}

		$thisActor = ConfigModel::getApiActorUri($apiName);

		$actorId = is_string($actor) ? $actor : ($actor["id"] ?? json_encode($actor));
		$inboxUri = is_string($actor) ? rtrim($actor, "/") . "/inbox" : ($actor["inbox"] ?? (rtrim($actorId, "/") . "/inbox"));

		$result = FollowerModel::addFollower($actorId, $inboxUri, $apiName, "inbound");
		OutboxModel::recordActivity("Follow", $actor, $object, $apiName);

		$config = ConfigModel::getConfig();
		if($config["loglevel"] == "verbose"){
			error_log("Chaosnet: auto-accepting follow from {$actorId} for API {$apiName}");
		}

		self::sendAccept($actorId, $inboxUri, $activity["id"] ?? null, $activity, $apiName);

		return $result;
	}

	private static function handleAccept($actor, $object, array $activity, string $apiName): array{
		$followId = null;
		if(is_array($object) && isset($object["object"])){
			$followId = is_string($object["object"]) ? $object["object"] : ($object["object"]["id"] ?? null);
		}elseif(is_array($object)){
			$followId = $object["id"] ?? null;
		}

		if($followId){
			return FollowerModel::acceptFollow($followId);
		}
		return ["error" => 0, "message" => "Accept recorded"];
	}

	private static function handleReject($actor, $object, array $activity, string $apiName): array{
		$followId = null;
		if(is_array($object) && isset($object["object"])){
			$followId = is_string($object["object"]) ? $object["object"] : ($object["object"]["id"] ?? null);
		}elseif(is_array($object)){
			$followId = $object["id"] ?? null;
		}

		if($followId){
			return FollowerModel::rejectFollow($followId);
		}
		return ["error" => 0, "message" => "Reject recorded"];
	}

	private static function handleUndo($actor, $object, array $activity, string $apiName): array{
		if($object === null || !is_array($object)){
			return ["error" => 400, "message" => "Undo requires an object"];
		}
		$undoType = $object["type"] ?? null;
		if($undoType === "Follow"){
			$targetId = is_string($object["object"]) ? $object["object"] : ($object["object"]["id"] ?? null);
			if($targetId){
				$actorId = is_string($actor) ? $actor : ($actor["id"] ?? json_encode($actor));
				return FollowerModel::removeFollower($actorId, $apiName, "inbound");
			}
		}
		return ["error" => 0, "message" => "Undo recorded (no action needed)"];
	}

	private static function sendAccept(string $followerActor, string $followerInbox, ?string $followActivityId, array $originalActivity, string $apiName): void{
		$thisActor = ConfigModel::getApiActorUri($apiName);

		$accept = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"type" => "Accept",
			"actor" => $thisActor,
			"object" => [
				"type" => "Follow",
				"actor" => $followerActor,
				"object" => $thisActor,
			],
		];

		if($followActivityId){
			$accept["object"]["id"] = $followActivityId;
		}

		$payload = json_encode($accept);
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $followerInbox,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => [
				"Content-Type: application/activity+json",
				"Content-Length: " . strlen($payload),
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
		]);
		curl_exec($ch);
		curl_close($ch);
	}

	private static function forwardActivityToFollowers(array $activity, string $apiName): void{
		$config = ConfigModel::getConfig();
		$thisActor = ConfigModel::getApiActorUri($apiName);
		$logVerbose = ($config["loglevel"] ?? "quiet") == "verbose";

		$followerUris = FollowerModel::getInboxUris($apiName);
		if(empty($followerUris)){
			return;
		}

		$object = $activity["object"] ?? [];
		if(!is_array($object)){ $object = []; }
		$path = $object["path"] ?? [];
		if(!is_array($path)){ $path = []; }
		if(!in_array($thisActor, $path)){
			$path[] = $thisActor;
		}
		$object["path"] = $path;

		$forwardActivity = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"type" => $activity["type"],
			"actor" => $thisActor,
			"object" => $object,
			"published" => date("c"),
		];

		$payload = json_encode($forwardActivity);

		foreach($followerUris as $inboxUri){
			if($logVerbose){
				error_log("Chaosnet: forwarding {$activity["type"]} to {$inboxUri} (API: {$apiName})");
			}
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $inboxUri,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $payload,
				CURLOPT_HTTPHEADER => [
					"Content-Type: application/activity+json",
					"Content-Length: " . strlen($payload),
				],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 10,
			]);
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if($httpCode < 200 || $httpCode >= 300){
				error_log("Chaosnet: forward to {$inboxUri} returned {$httpCode}: {$response}");
			}
			curl_close($ch);
		}
	}
}

?>
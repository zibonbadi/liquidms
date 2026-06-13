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

class ActorModel{

	public static function getRootDocument(){
		$config = ConfigModel::getConfig();
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";

		$items = [];
		foreach($config["apis"] ?? [] as $apiName){
			$items[] = [
				"type" => "Service",
				"id" => ConfigModel::getApiActorUri($apiName),
				"name" => $apiName,
			];
		}

		return [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => $base,
			"type" => "Collection",
			"name" => "LiquidMS node at {$config["node_host"]}",
			"items" => $items,
		];
	}

	public static function getActorDocument(string $apiName){
		$config = ConfigModel::getConfig();
		$base = ConfigModel::getApiActorUri($apiName);

		return [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"type" => "Service",
			"id" => $base,
			"name" => "{$apiName} netgames at {$config["node_host"]}",
			"inbox" => "{$base}/inbox",
			"outbox" => "{$base}/outbox",
			"following" => "{$base}/following",
			"followers" => "{$base}/followers",
			"generator" => [
				"type" => "Application",
				"name" => "LiquidMS Chaosnet",
			],
			"url" => "{$base}/collection",
		];
	}
}

?>
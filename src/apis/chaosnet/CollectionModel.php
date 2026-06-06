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
require_once __DIR__.'/GameModel.php';
require_once __DIR__.'/InboxModel.php';

use LiquidMS\ConfigModel;

class CollectionModel{

	public static function getCollection(int $page = 1, int $pageSize = 50): array{
		$config = ConfigModel::getConfig();
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";

		InboxModel::processPendingRows(false);

		$gamesResult = GameModel::getAllGames($page, $pageSize);
		if($gamesResult["error"] != 0){
			return $gamesResult;
		}

		$total = $gamesResult["total"] ?? 0;
		$totalPages = max(1, (int)ceil($total / $pageSize));

		$collection = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id" => "{$base}/collection?page={$page}",
			"type" => "OrderedCollectionPage",
			"partOf" => "{$base}/collection",
			"totalItems" => $total,
			"page" => $page,
			"pageSize" => $pageSize,
		];

		$links = [
			"self" => "{$base}/collection?page={$page}&pagesize={$pageSize}",
			"current" => "{$base}/collection?page={$page}&pagesize={$pageSize}",
		];

		if($page > 1){
			$links["first"] = "{$base}/collection?page=1&pagesize={$pageSize}";
			$links["prev"] = "{$base}/collection?page=" . ($page - 1) . "&pagesize={$pageSize}";
		}
		if($page < $totalPages){
			$links["last"] = "{$base}/collection?page={$totalPages}&pagesize={$pageSize}";
			$links["next"] = "{$base}/collection?page=" . ($page + 1) . "&pagesize={$pageSize}";
		}

		$collection["links"] = $links;
		$collection["items"] = $gamesResult["data"];

		return [
			"error" => 0,
			"data" => $collection,
		];
	}
}

?>

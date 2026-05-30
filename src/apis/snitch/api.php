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

require_once __DIR__.'/ConfigModel.php';
require_once __DIR__.'/ChaosNet/GameModel.php';


use \LiquidMS\ConfigModel;

$basepath = "/"; // For (shared) hosting in subdirectories
if(LiquidMS\ConfigModel::getConfig()["basepath"]){ $basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/"); }

// Namespace for extended 
$router->with("{$basepath}", function() use ($router){

	$router->respond('GET', '/?', function($request, $response){
		$response->header('X-LiquidMS-Deprecated', 'true');
		$response->header('X-LiquidMS-Deprecation-Message', 'Snitch V1 is deprecated. Use Chaosnet (the Snitch V2 ActivityPub API) instead. See /api/chaosnet');
		$response->header('Deprecation', 'version="1", sunset="Sun, 01 Jan 2028 00:00:00 GMT"');
		
		// Get all known netgames as a CSV table (e.g. for snitching to other nodes)
		$response->header('Content-Type','text/csv;header=absent');
		$apiver = $request->headers()->get("X-liquidms-snitch-version");

		$netgames = LiquidMS\ChaosNet\GameModel::getSRB2Games();

		// Check database errors
		if($netgames["error"] != 0){
			$response->code(500);
			return;
		}

		// No header; -> legacy API
		$out = fopen('php://output', 'w');
		
		foreach($netgames["data"] as $netgame){
			if(($netgame["origin"] == "localhost") || ($netgame["origin"] == "127.0.0.1")){ $netgame["origin"] = $_SERVER["SERVER_NAME"]; }
			// Reordering to guarantee API-compliant output
			fputcsv($out, [
				"host"			=>	$netgame["host"],
				"port"			=>	$netgame["port"],
				"servername"	=>	$netgame["servername"],
				"version"		=>	$netgame["version"],
				"roomname"		=>	$netgame["roomname"],
				"origin"		=>	$netgame["origin"],
			],
			",", '"', "\\" 
			);
		}
	});

	$router->respond('POST', '/?', function($request, $response, $service){
		// DEPRECATED: Use Chaosnet (/api/chaosnet/inbox) instead
		$response->header('X-LiquidMS-Deprecated', 'true');
		$response->header('X-LiquidMS-Deprecation-Message', 'Snitch V1 is deprecated. Use Chaosnet (the Snitch V2 ActivityPub API) instead. See /api/chaosnet');
		$response->header('Deprecation', 'version="1", sunset="Sun, 01 Jan 2028 00:00:00 GMT"');

		// Provide some CSV text and it'll get parsed into tables
		//$csvdata[] = str_getcsv($request->body());
		$csvdata = [];
		$files = $request->files();
		$settings = ConfigModel::getConfig();

		if($settings["loglevel"] == "verbose"){ error_log($request->ip()." provided the following files:\n".yaml_emit($files->all())); }

		if(empty($files) ||
		$files == NULL){
			$response->code(400);
			$response->json( [
			"status" => $response->code(),
			"message" => "No valid data provided"
			] );
			return;
		}

		// Construct snitch V1 data array from CSV
		foreach( $files as $fileId => $file){

			//Split CSV data by line, then parse the line array
			$csvlines = explode("\n",rtrim(file_get_contents($file['tmp_name']),"\n"));
			foreach($csvlines as $i => $row){
				
				# Parse current CSV line into a nice and tidy array
				$netgame_arr = str_getcsv($row, ',', '"','\\');
			
				# Sanitize null
				if( count($netgame_arr) < 1 || ($netgame_arr[0] == null) ){ continue; }
				$csvdata[] = [
					"host" => $netgame_arr[0],
					"port" => $netgame_arr[1],
					"servername" => $netgame_arr[2],
					"version" => $netgame_arr[3],
					"roomname" => $netgame_arr[4],
					"origin" => $netgame_arr[5] ?? NULL,
				];
			}

		}

		// Iterate previously-constructed data array
		$games = [];
		foreach($csvdata as $netgameId => $netgame){
			// Check entries. Keep halal ones, skip the rest
			if(
				($netgame["host"] == "localhost") ||
				($netgame["host"] == "127.0.0.1") ||
				($netgame["host"] == $_SERVER["HTTP_HOST"]) ||
				($netgame["origin"] == $_SERVER["HTTP_HOST"]) ||
				($netgame["host"] == $_SERVER["SERVER_NAME"]) ||
				($netgame["origin"] == $_SERVER["SERVER_NAME"]) ||
				($netgame["host"] == "0.0.0.0") ||
				($netgame["origin"] == "0.0.0.0") ||
				($netgame["origin"] == "localhost") ||
				($netgame["origin"] == "127.0.0.1")
			){
				if($settings["loglevel"] == "verbose"){ error_log("Removing invalid netgame \"{$netgame["servername"]}\""); }
				continue;
			}

			$game_obj = NULL;
			if($netgame["version"] == "SRB2Kart"){
				// SRB2Kart Game
				$game_obj = [
					"name" => LiquidMS\ChaosNet\GameModel::normalizeName($netgame["servername"]),
					"host"		 => $netgame["host"],
					"port"		 => $netgame["port"],
					"api_name" => "srb2kart",
					"api_data" => [
						"host"		=> $netgame["host"],
						"port"		=> $netgame["port"],
						"contact" 	=> $netgame["servername"],
						"game"	 	=> $netgame["roomname"],
					],
					"external_origin" => $netgame["origin"] ?? NULL,
					"origin_node" => $settings["node_actor_uri"] ?? NULL,
				];
			}else{
				// SRB2HTTP game				
				$game_obj = [
					"name" => LiquidMS\ChaosNet\GameModel::normalizeName($netgame["servername"]),
					"host"		 => $netgame["host"],
					"port"		 => $netgame["port"],
					"api_name" => "srb2http",
					"api_data" => [
						"host"		 => $netgame["host"],
						"port"		 => $netgame["port"],
						"servername" => $netgame["servername"],
						"version"	 => $netgame["version"],
						"roomname"	 => $netgame["roomname"],
					],
					"external_origin" => $netgame["origin"] ?? NULL,
					"origin_node" => $settings["node_actor_uri"] ?? NULL,
				];
			}

			// Add to collection if valid netgame
			if($game_obj != NULL){ $games[] = $game_obj; }

		}

		if(empty($games) || $games == NULL){
			$response->code(400);
			$response->json( [
			"status" => $response->code(),
			"message" => "No valid data provided"
			] );
			return;
		}

		if($settings["loglevel"] == "verbose"){ error_log($request->ip()." snitched the following netgames:\n".json_encode($games)); }

		// Upsert all valid netgames
		foreach($games as $i => $netgame){
			// Empty string actor to make it fail to default
			$dbresponse = LiquidMS\ChaosNet\GameModel::upsertGame($netgame, $netgame["origin_node"] ?? "localhost");
		}

		// Check for database funkiness
		if( $dbresponse["error"] == 0 ){
			if( $dbresponse["rows"] > 0 ){
				// For now, just mirror what got parsed for testing
				$response->json( [
				"status" => $response->code(),
				"message" => "Success",
				] );
			}else{
				$response->code(403);
				$response->json( [
				"status" => $response->code(),
				"message" => "Can't add those\n",
				] );
			}
		}else{
			file_put_contents('php://stderr', "{$dbresponse["message"]}");
			$response->code(500);
			$response->json( [
			"status" => $response->code(),
			"message" => $service->render(__DIR__."/modules/ErrorView.php", ["response" => $dbresponse]),
			] );
		}

	});
});

?>

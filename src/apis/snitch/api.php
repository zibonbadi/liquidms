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
require_once __DIR__.'/SRB2HTTP/NetgameModel.php'; 
require_once __DIR__.'/SRB2Kart/NetgameModel.php'; 
/*
if( in_array("srb2http", array_map('strtolower',$config["apis"])) ) { require_once __DIR__.'/modules/SRB2HTTP/NetgameModel.php'; }
if( in_array("srb2kart", array_map('strtolower',$config["apis"])) { require_once __DIR__.'/modules/SRB2Kart/NetgameModel.php'; }
if( in_array("srb2legacy", array_map('strtolower',$config["apis"])) {
	# TODO: Figure out how to pull legacy server data
	# (Most likely saved in DB, but requires custom NetgameModel)
}
*/

use \LiquidMS\ConfigModel;

$basepath = "/"; // For (shared) hosting in subdirectories
if(LiquidMS\ConfigModel::getConfig()["basepath"]){ $basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/"); }

// Namespace for extended 
$router->with("{$basepath}", function() use ($router){

	$router->respond('GET', '/?', function($request, $response){
		$response->header('X-LiquidMS-Deprecated', 'true');
		$response->header('X-LiquidMS-Deprecation-Message', 'Snitch V1 is deprecated. Use Chaosnet (the Snitch V2 ActivityPub API) instead. See /api/chaosnet');
		$response->header('Deprecation', 'version="1", sunset="Sun, 01 Jan 2028 00:00:00 GMT"');
		
		$use_srb2http = array_key_exists("srb2http", LiquidMS\ConfigModel::getConfig()["apis"]);
		$use_srb2kart = array_key_exists("srb2kart", LiquidMS\ConfigModel::getConfig()["apis"]);
		
		// Get all known netgames as a CSV table (e.g. for snitching to other nodes)
		$response->header('Content-Type','text/csv;header=absent');
		$apiver = $request->headers()->get("X-liquidms-snitch-version");
		if($use_srb2http){
			$servers_srb2http = LiquidMS\SRB2HTTP\NetgameModel::getInstance()->getServers();
			if($servers_srb2http["error"] != 0){
				$response->code(500);
				return;
			}
		}

		if($use_srb2kart){
			$servers_srb2kart = LiquidMS\SRB2Kart\NetgameModel::getInstance()->getServers();
			if($servers_srb2kart["error"] != 0){
				$response->code(500);
				return;
			}
		}

		switch($apiver){
		case "2":{
			// new API -> check version
			$response->body($apiver);
			break;
		}
		case "1":
		case NULL:
		default:{
			// No header; -> legacy API
			$out = fopen('php://output', 'w');
				
			if($use_srb2http && $servers_srb2http["rows"] > 0){
				foreach($servers_srb2http["data"] as $server){
					if(($server["origin"] == "localhost") || ($server["origin"] == "127.0.0.1")){ $server["origin"] = $_SERVER["SERVER_NAME"]; }
					// Reordering to guarantee API-compliant output
					fputcsv($out, [
						"host" => $server["host"],
						"port" => $server["port"],
						"servername" => $server["servername"],
						"version" => $server["version"],
						"roomname" => $server["roomname"],
						"origin" => $server["origin"],
					]
					);
				}
				//$response->json($servers["data"]);
			}
			if($use_srb2kart && $servers_srb2kart["rows"] > 0){
				foreach($servers_srb2kart["data"] as $server){
					if(($server["origin"] == "localhost") || ($server["origin"] == "127.0.0.1")){ $server["origin"] = $_SERVER["SERVER_NAME"]; }
					// Reordering to guarantee API-compliant output
					fputcsv($out, [
						"host" => $server["host"],
						"port" => $server["port"],
						"servername" => $server["servername"],
						"version" => "SRB2Kart",
						"roomname" => $server["game"],
						"origin" => $server["origin"],
						"_api" => "srb2kart"
					]
					);
				}
				//$response->json($servers["data"]);
			}
			/*
			else{
				#$response->code(404);
				$response->body("");
			}
			*/
			break;
		}
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

		foreach( $files as $fileId => $file){
			//Formatting
			$csvlines = explode("\n",rtrim(file_get_contents($file['tmp_name']),"\n"));
			$csvdata_raw = array_map('str_getcsv', $csvlines);
			foreach($csvdata_raw as $csvnetgameId => $csvnetgame){
				# Sanitize null
				if( count($csvnetgame) < 1 || ($csvnetgame[0] == null) ){ continue; }
				$csvdata[] = [
					"host" => $csvnetgame[0],
					"port" => $csvnetgame[1],
					"servername" => $csvnetgame[2],
					"version" => $csvnetgame[3],
					"roomname" => $csvnetgame[4],
					"origin" => $csvnetgame[5],
				];
			}
		}

		foreach($csvdata as $netgameId => $netgame){
			// Check entries. Keep halal ones, discard the rest
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
				unset($csvdata[$netgameId]);
			}
		}

		if(empty($csvdata) || $csvdata == NULL){
			$response->code(400);
			$response->json( [
			"status" => $response->code(),
			"message" => "No valid data provided"
			] );
			return;
		}

		if($settings["loglevel"] == "verbose"){ error_log($request->ip()." snitched the following netgames:\n".yaml_emit($csvdata)); }

		$csvdata_srb2kart = array_filter($csvdata, function($v){ return strtolower($v["version"]) == "_srb2kart";});
		$csvdata_srb2http = array_filter($csvdata, function($v){ return strtolower($v["version"]) != "_srb2kart";});

		// SRB2HTTP logic
		if(array_key_exists("srb2http", $settings["apis"]) && count($csvdata_srb2http) > 0){
			// I'll think of something
			$dbresponse = LiquidMS\SRB2HTTP\NetgameModel::getInstance()->pushServers($csvdata_srb2http);
		}
		// SRB2Kart logic
		if(array_key_exists("srb2kart", $settings["apis"]) && count($csvdata_srb2kart) > 0){
			// I'll think of something
			$dbresponse = LiquidMS\SRB2Kart\NetgameModel::getInstance()->pushServers($csvdata_srb2kart);
		}
		
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

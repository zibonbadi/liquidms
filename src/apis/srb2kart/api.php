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
require_once __DIR__.'/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\SRB2Kart\NetgameModel;

$basepath = ""; // For (shared) hosting in subdirectories
if(ConfigModel::getConfig()["basepath"]){ $basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/"); }

/* Games API */
$router->with("{$basepath}/games", function() use ($router){

	$router->respond('GET', '/?', function($request, $response, $service){
			/* Version guard */
			$apiversion = $request->param('v');
			if(!str_ends_with($apiversion, "-liquid")){
				switch($apiversion){
				case "2.2":
				case "2":
					{ break; }
				default:{
					$response->code(404);
					return "Unknown API version\n";
					break;
				}
				}
			}
			
			$import = NetgameModel::getInstance()->getVersions(NULL);
			if( $import["error"] == 0 ){
				// Secret LiquidMS-specific game support listing.
				// Might be useful for snitching some day...
				$maincontent = "";
				foreach($import["data"] as $ver_index => $ver_value){
						$maincontent .= $ver_value["game"]." ".
										$ver_value["version_id"]." ".
										$ver_value["version_name"]."\n";
				}
				if($import["rows"] < 1){
						$response->code(404);
						$maincontent = "No such version\n";
				}
				return "${maincontent}";
			}else{
				$response->code(500);
				$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
			}

	});

	$router->respond('GET', '/[:gameId]/version', function($request, $response){
			/* Version guard */
			$apiversion = $request->param('v');
			switch($apiversion){
			case "2.2":
			case "2":
				{ break; }
			default:{
				$response->code(404);
				return "Unknown API version\n";
				break;
			}
			}

			#$versionstring = yaml_parse_file("config.yaml.example")["versions"][$request->gameId]; // Local var kludge
			#echo "Versionizer is here {$request->gameId}\n";
			$maincontent = "";
			$import = NetgameModel::getInstance()->getVersions($request->gameId);
			if( $import["error"] == 0 ){
				// Technically an unspecified room would blurt out all. The
				// router takes care of it, but that's actually non-compliant.
				foreach($import["data"] as $ver_index => $ver_value){
					$maincontent .= $ver_value["version_id"]." ".
									$ver_value["version_name"]."\n";
				}
				if($import["rows"] < 1){
					$response->code(404);
					$maincontent = "No such version\n";
				}
				return "${maincontent}";
			}else{
				$response->code(500);
				$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
			}
	});
		
	$router->respond('GET', '/games/[:gameId]/servers', function($request, $response, $service){
		/* TODO: Add LiquidMS-extended full game query */
		/* Version guard */
		$apiversion = $request->param('v', "");
		if(!str_ends_with($apiversion, "-liquid")){
			switch($apiversion){
			case "2.2":
			case "2":{
				$response->code(400);
				return "Unknown Action\n";
				break;
			}
			default:{
				$response->code(404);
				return "Unknown API version\n";
				break;
			}
			}
		}

		/* LiquidMS mode? NOW we're talkin' */

		$response->code(400);
		
		$servers = NetgameModel::getInstance()->getServers($request->gameId, NULL);
		if( $servers["error"] == 0 ){
			$service->render(__DIR__."/KartView.php", ["data" => $servers, "room" => $request->gameId]);
		}else{
			$response->code(500);
			$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
		}

	});

	$router->respond('GET', '/[:gameId]/[:versionId]/servers', function($request, $response, $service){
		/* Version guard */
		$apiversion = $request->param('v');
		switch($apiversion){
		case "2.2":
		case "2":
			{ break; }
		default:{
			$response->code(404);
			return "Unknown API version\n";
			break;
		}
		}

		$servers = NetgameModel::getInstance()->getServers($request->gameId, $request->versionId);

		if( $servers["error"] == 0 ){
			$service->render(__DIR__."/KartView.php", ["data" => $servers, "room" => $request->gameId]);
		}else{
			$response->code(500);
			$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
		}
	});


	/* POST */
	$router->respond('POST', '/[:gameId]/[:gameVersion]/servers/register', function($request, $response){
			// Register Server and put ID here.  ID format is not specified; Vanilla 
			// returns numbers, we will return a random base64 string for security.
			
			$servers = NetgameModel::getInstance()->getServers($request->gameId, $request->gameVersion);
			
			if( $servers["error"] == 0 ){
				parse_str($request->body(), $info);
				// Stupid linear search bc we got no IP filter to pass to the query
				$server_exists = false;
				foreach( $servers["data"] as $sv_name => $sv ){
					if($sv["host"] == $request->ip && $sv["port"] == $info['port']){ $server_exists = true;}
				}

				if(!$server_exists){
					// Server doesn't yet exist -> Create
					NetgameModel::getInstance()->changeServer("create", $request->ip(),  "{$request->ip()}:{$info['port']}", rawurlencode($info['contact']), $request->gameId);
				}
				return "{$request->ip()}:{$info['port']}\n";
			}else{
				$response->code(403);
				$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
			}
	});
});

/* Servers API */
$router->with("{$basepath}/servers", function() use ($router){

	$router->respond('GET', '/?', function($request, $response, $service){
		/* TODO: Add LiquidMS-extended full game query */
		/* Version guard */
		$apiversion = $request->param('v', "");
		if(!str_ends_with($apiversion, "-liquid")){
			switch($apiversion){
			case "2.2":
			case "2":{
				$response->code(400);
				return "Missing server id\n";
				break;
			}
			default:{
				$response->code(404);
				return "Unknown API version\n";
				break;
			}
			}
		}

		/* LiquidMS mode? NOW we're talkin' */

		$response->code(400);
		
		$servers = NetgameModel::getInstance()->getServers(NULL, NULL);

		if( $servers["error"] == 0 ){
			$service->render(__DIR__."/KartView.php", ["data" => $servers]);
		}else{
			$response->code(500);
			$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
		}

	});


	/* POST */
	$router->respond('POST', '/[:serverid]?/update', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();

			$new_title = NULL;
			if(array_key_exists('title', $info)){ $new_title = rawurlencode($info['title']); }
			if(array_key_exists('contact', $info)){ $new_title = rawurlencode($info['contact']); }

			$response = NetgameModel::getInstance()->changeServer("update", $request->ip(), $request->serverid, $new_title, null);
			if( $response["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});

	$router->respond('POST', '/[:serverid]?/unlist', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			$rooms = NetgameModel::getInstance()->changeServer("delete", $request->ip(), $request->serverid, null, null);
			if( $rooms["rows"] > 0 ){
				// No Response body
				return;
			}else{
				$response->code(404);
				return "No such server\n";
			}
	});



});

$router->respond('GET', "{$basepath}/rules", function($request, $response){
		$apiversion = $request->param('v', "");
		if(str_ends_with($apiversion, "-liquid")){
			return ConfigModel::getConfig()["motd"]."\n\n"; // Two LFs to match spec
		}
		switch($apiversion){
		case "2.2":{
			return ConfigModel::getConfig()["motd"]."\n\n"; // Two LFs to match spec
			break;
		}
		case "2":{
			$response->code(400);
			return "Unknown Action\n";
			break;
		}
		default:{
			$response->code(404);
			return "Unknown API version\n";
			break;
		}
		}
});

$router->respond('GET', "{$basepath}/*?", function($request, $response){
		$response->code(400);
		return "Unknown action\n";
});
?>

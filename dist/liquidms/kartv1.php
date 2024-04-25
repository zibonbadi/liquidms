<?php
# LiquidMS - distributable SRB2 master server
# Copyright (C) 2021-2024 Zibon Badi et al.
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

require_once __DIR__.'/modules/ConfigModel.php';
require_once __DIR__.'/modules/SRB2Kart/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\SRB2Kart\NetgameModel;

LiquidMS\SRB2Kart\NetgameModel::init(ConfigModel::getConfig());

// Game-specific endpoints
$router->with('/srb2kart/games/SRB2Kart', function() use ($router){
	$router->respond('GET', '/version', function($request, $response, $service){
		$response->header('Content-Type','text/plain');
		$qparams=$request->params();
		if(!array_key_exists('v', $qparams)){
			// Redundant but replicates vanilla behavior
			return "Missing API version";
		}

		switch($qparams['v']){
		case "2":{
			// Kart v1.3 (API version 2.0)
			return "2 v1.3";
			break;
		}
		case "2.2":{
			// Kart v1.6 (API version 2.2)
			return "2.2 v1.6";
			break;
		}
		default:{
			return "Unknown API version";
			break;
		}
		}
	});

	$router->respond('GET', '/[:modId]/servers', function($request, $response, $service){
		$response->header('Content-Type','text/plain');
		$qparams=$request->params();
		if(!array_key_exists('v', $qparams)){
			// Redundant but replicates vanilla behavior
			return "Missing API version";
		}

		switch($qparams['v']){
		case "2":
		case "2.2":{
			// All OK
			break;
		}
		default:{
			return "Unknown API version";
			break;
		}
		}


		$servers = NetgameModel::getServers($request->roomId);

		if( $servers["error"] == 0 ){
		   $service->render(__DIR__."/modules/SRB2Kart/KartView.php", ["data" => $servers]);
		}else{
		   $response->code(500);
		   $service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
		}

	});


	////////
	// POST
	////////

	$router->respond('POST', '/[:modid]/register', function($request, $response){
		// Register Server and put ID here.  ID format is not specified; Vanilla 
		// returns numbers, we will return a random base64 string for security.
		$rooms = NetgameModel::getRooms($request->roomId);
		if( $rooms["error"] == 0 ){
			if( $rooms["rows"] > 0 ){
				parse_str($request->body(), $info);
				NetgameModel::changeServer("create", $request->ip(),  "{$request->ip()}:{$info['port']}", rawurlencode($info['title']), $info['version'], $rooms["data"][0]['roomname']);
				return "{$request->ip()}:{$info['port']}";
			}else{
				$response->code(404);
				return "No such room\n";
			}
		}else{
			$response->code(403);
			$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
		}
	});

});

// "Global" stuff
$router->with('/srb2kart', function() use ($router){
	$router->respond('GET', '/rules', function($request, $response, $service){
		$response->header('Content-Type','text/plain;syntax=markdown');
		return file_get_contents(__DIR__."/../LICENSE.md");
	});

	$router->respond('POST', '/servers/[:serverid]?/update', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			$response = NetgameModel::changeServer("update", $request->ip(), $request->serverid, rawurlencode($info['title']), null, null);
			if( $response["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});
	$router->respond('POST', '/servers/[:serverid]?/unlist', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			$rooms = NetgameModel::changeServer("delete", $request->ip(), $request->serverid, null, null, null);
			if( $rooms["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});
});
?>

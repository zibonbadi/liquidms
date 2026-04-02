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

require_once __DIR__.'/ConfigModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

$basepath = ""; // For (shared) hosting in subdirectories
if(ConfigModel::getConfig()["basepath"]){ $basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/"); }

$router->respond('GET', "{$basepath}/?", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		#$netgames = NetgameModel::getServers();
		$service->render(__DIR__."/templates/main.php", [
			"config" => $config,
			]);
});
// Three separate resource routes for capsuled security
$router->respond('GET', "{$basepath}/img/[**:path]/?", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		$response->file(__DIR__."/img/".$request->path);
});
$router->respond('GET', "{$basepath}/static/[**:path]/?{$basepath}", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		$response->header('Content-Type', 'text/html');
		$response->sendHeaders(true);
		#$response->file(rtrim($config["sbpath"], "/")."/static/".$request->path);
		$service->render(__DIR__."/static/".$request->path);
});
$router->respond('GET', "{$basepath}/css/[**:path]/?", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		$response->header('Content-Type', 'text/css');
		$response->sendHeaders(true);
		#$response->file(rtrim($config["sbpath"], "/")."/css/".$request->path);
		$service->render(__DIR__."/css/".$request->path.'.php', [
			"config" => $config,
		]);
});
$router->respond('GET', "{$basepath}/js/[**:path]/?", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		$response->header('Content-Type', 'application/javascript');
		$response->sendHeaders(true);
		#$response->file(rtrim($config["sbpath"], "/")."/js/".$request->path);
		$service->render(__DIR__."/js/".$request->path);
});


# API routes
$router->with("$basepath/api", function() use ($router){

	$router->respond('GET', "/dbquery/[:service]/?", function($request, $response, $service){
		$config = ConfigModel::getConfig();
		$response->header('Content-Type', 'application/javascript');
		$response->sendHeaders(true);

		if(!array_key_exists($request->service, $config["services"])){
			$response->code(404);
			$response->json(NULL);
			return;
		}
		switch($config["services"][$request->service]["_api"]){
			case "SRB2HTTP": {			
				require_once __DIR__.'/SRB2HTTP/NetgameModel.php';
				$servers = \LiquidMS\SRB2HTTP\NetgameModel::getInstance()->getServers($config["services"][$request->service]);
				$response->json($servers["data"]);
				break;
			}
			case "SRB2Kart": {
				require_once __DIR__.'/SRB2Kart/NetgameModel.php';
				$servers = \LiquidMS\SRB2Kart\NetgameModel::getInstance()->getServers($config["services"][$request->service]);
				$response->json($servers["data"]);
				break;
			}
			default:{
				$response->code(404);
				$response->json(NULL);
				break;
			}
		}

	});

	# SRB2Query endpoint
	$router->respond('GET', '/srb2query/?', function($request, $response){
		if( $request->hostname != NULL &&
			$request->port != NULL &&
			intval($request->port) > 1){

			// Set up SRB2Query
			require_once __DIR__.'/modules_vendor/srb2query.php';

			$srb2conn = new SRB2Query;
			$ng_hdl = null;

			function urlsanitize($input) {
				if(is_array($input)) {
					foreach($input as $i => $value) { $input[$i] = urlsanitize($value); }
				}else if (is_string($input)) {
					return urlencode($input);
				}
				return $input;
			}

			$srb2conn->Ask($request->hostname, intval($request->port));
			$netgame = $srb2conn->Info($ng_hdl);

			#error_log("SRB2QUERY: ".$request->hostname.' '.$request->port."\n".yaml_emit($netgame)."\n".yaml_emit($ng_hdl));

			// Add hostname to data, just in case
			#$netgame["hostname"] = $ng_hdl;

			// Guarantee form, fill with dummy data
			$out = [
				"hostname" => "127.0.0.1",
				"port" => "5029",
				"cheats" => false,
				"dedicated" => false,
				"gametype" => "Query Failure",
				"level" => [
					"md5sum" => 00000,
				"level" => "Query failure",
				],
				"title" => "Query failure",
				"mods" => false,
				"players" => [
					"max" => 0,
				"list" => [],
				],
				"version" => [
					"major" => 0,
				"minor" => 0,
				"patch" => 0,
				"name" => "No contest",
				],
				];
			if($netgame){ $out = $netgame; }
			else{ $response->code(404); }

			$response->json(urlsanitize($out));
		}else{
			$response->code(400);
			$response->json([
				"?" => [
					"hostname" => $request->hostname,
					"port" => $request->port,
					],
				]);
		}
	});


});


$router->respond('GET', '/license/?', function($request, $response, $service){
	$response->header('Content-Type','text/plain;syntax=markdown');
	return file_get_contents(__DIR__."/../LICENSE.md");
});

?>

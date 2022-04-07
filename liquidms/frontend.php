<?php
# liquidMS - distributable SRB2 master server
# Copyright (C) 2021-2022 Zibon Badi et al.
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
require_once __DIR__.'/modules/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

$router->with('/liquidms/browse', function() use ($router){
	$router->respond('GET', '/?', function($request, $response, $service){
			$servers = NetgameModel::getServers();
			$rooms = NetgameModel::getRooms();
			$config = ConfigModel::getConfig();
				if( ($servers["error"] == 0) && ($rooms["error"] == 0) ){
					#$service->render(__DIR__."/../public/browse.phtml", ["motd" => $config["motd"]]);
					$service->render(rtrim($config["sbpath"], "/")."/index.php", ["motd" => $config["motd"]]);
				}else{
					$response->code(403);
					if( ($servers["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]); };
					if( ($rooms["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $rooms]); };
				}
	});
	// Three separate resource routes for capsuled security
	$router->respond('GET', '/img/[**:path]', function($request, $response, $service){
			$config = ConfigModel::getConfig();
			$response->file(rtrim($config["sbpath"], "/")."/img/".$request->path);
	});
	$router->respond('GET', '/static/[**:path]', function($request, $response, $service){
			$config = ConfigModel::getConfig();
			$response->file(rtrim($config["sbpath"], "/")."/static/".$request->path);
			$response->header('Content-type', 'text/html');
			$response->sendHeaders(true);
	});
	$router->respond('GET', '/css/[**:path]', function($request, $response, $service){
			$config = ConfigModel::getConfig();
			$response->file(rtrim($config["sbpath"], "/")."/css/".$request->path);
			$response->header('Content-type', 'text/css');
			$response->sendHeaders(true);
	});
	$router->respond('GET', '/js/[**:path]', function($request, $response, $service){
			$config = ConfigModel::getConfig();
			$response->file(rtrim($config["sbpath"], "/")."/js/".$request->path);
			$response->header('Content-type', 'application/javascript');
			$response->sendHeaders(true);
	});
});

/* SRB2Query routes */
$router->with('/liquidms/SRB2Query', function() use ($router){
	$router->respond('GET', '/?', function($request, $response){
		if( $request->hostname != NULL &&
			$request->port != NULL){

			// Set up SRB2Query
			require_once __DIR__.'/modules_vendor/srb2query.php';

			$srb2conn = new SRB2Query;
			$srb2conn->Ask($request->hostname, $request->port);
			$netgame = $srb2conn->Info($addr);

			function utf8sanitize($input) {
				if(is_array($input)) {
					foreach($input as $i => $value) { $input[$i] = utf8sanitize($value); }
				}else if (is_string($input)) {
					return utf8_encode($input);
				}
				return $input;
			}

			// Add hostname to data, just in case
			$netgame["hostname"] = $addr;
			$response->json(utf8sanitize($netgame));
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

?>

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

use LiquidMS\ConfigModel;
use LiquidMS\SRB2HTTP\NetgameModel;

$basepath = ""; // For (shared) hosting in subdirectories
if(ConfigModel::getConfig()["basepath"]){ $basepath = '/'.trim(ConfigModel::getConfig()["basepath"], "/"); }

$router->with("{$basepath}/servers", function() use ($router){
	$router->respond('GET', '/?', function($request, $response, $service){
			// Server test kludge. The game seems to ping every listed server and
			// filter by response. Listing dummy servers is thus not possible.
			$servers = NetgameModel::getInstance()->getServers();
			$rooms = NetgameModel::getInstance()->getRooms();
				if( ($servers["error"] == 0) && ($rooms["error"] == 0) ){ $service->render(__DIR__."/SRB2HTTP/MultiroomView.php", ["data" => $servers, "rooms" => $rooms]);
				}else{
					$response->code(403);
					if( ($servers["error"] != 0)){ $service->render(__DIR__."/ErrorView.php", ["response" => $servers]); };
					if( ($rooms["error"] != 0)){ $service->render(__DIR__."/ErrorView.php", ["response" => $rooms]); };
				}
	});
	$router->respond('POST', '/[:serverid]?/update', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			$response = NetgameModel::getInstance()->changeServer("update", $request->ip(), $request->serverid, rawurlencode($info['title']), null, null);
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
			$rooms = NetgameModel::getInstance()->changeServer("delete", $request->ip(), $request->serverid, null, null, null);
			if( $rooms["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});
});

$router->with("{$basepath}/versions", function() use ($router){
		$router->respond('GET', '/[:versionId]', function($request, $response){
				#$versionstring = yaml_parse_file("config.yaml.example")["versions"][$request->versionId]; // Local var kludge
				#echo "Versionizer is here {$request->versionId}\n";
				$maincontent = "";
				$import = NetgameModel::getInstance()->getVersions(intval($request->versionId));
				if( $import["error"] == 0 ){
					// Technically an unspecified room would blurt out all. The
					// router takes care of it, but that's actually non-compliant.
					foreach($import["data"] as $ver_index => $ver_value){
						$maincontent .= $ver_value["gameid"]." ".
										$ver_value["name"]."\n";
					}
					if($import["rows"] < 1){
						$response->code(404);
						$maincontent = "No such version\n";
					}
					return "{$maincontent}";
				}else{
					$response->code(500);
					$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
				}
		});
});


/* POST API */
$router->with("{$basepath}/rooms", function() use ($router){
		$router->respond('POST', '/[:roomId]/register', function($request, $response){
				// Register Server and put ID here.  ID format is not specified; Vanilla 
				// returns numbers, we will return a random base64 string for security.
				if( intval($request->roomId) == 1){
						$response->code(403);
						return "403 Forbidden";
				}else{
						$rooms = NetgameModel::getInstance()->getRooms($request->roomId);
						if( $rooms["error"] == 0 ){
							if( $rooms["rows"] > 0 ){
								parse_str($request->body(), $info);
								NetgameModel::getInstance()->changeServer("create", $request->ip(),  "{$request->ip()}:{$info['port']}", rawurlencode($info['title']), $info['version'], $rooms["data"][0]['roomname']);
								return "{$request->ip()}:{$info['port']}";
							}else{
								$response->code(404);
								return "No such room\n";
							}
						}else{
							$response->code(403);
							$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
						}
				}
		});
		$router->respond('GET', '/?', function($request, $response){
				// The rooms Universe(0) and World(1) are technical and should always
				// be added with automatically generated MOTDs to indicate function
				//
				// Since network adresses too small to append to name (blame bitmap font),
				// the name of the fetch server shall be added as "@[address]" into
				// the first line of the MOTD.

				// This is a demo mirror. Put DB queries here.
				$rooms = NetgameModel::getInstance()->getRooms();
				if( $rooms["error"] == 0 ){
					$maincontent = "";

					foreach($rooms["data"] as $room_index => $room_value){
						if($room_value["origin"] != 'localhost'){
							$roomname_token = "@{$room_value["roomname"]}";
							$description_token = "@{$room_value["origin"]}\n{$room_value["roomname"]}\nThis room is not local to your LiquidMS node. Registering Netgames here will be useless.";
						}else{
							$roomname_token = "{$room_value["roomname"]}";
							$description_token = "{$room_value["description"]}";
						}

						$maincontent .= $room_value["roomid"]."\n".
										"$roomname_token\n".
										"$description_token\n\n\n";
					}
					$motd = ConfigModel::getConfig()["motd"]; // Local var kludge
					return <<<END
							0
							Universe
							Powered by liquidMS

							This room queries all available rooms, local and remote.

							=MOTD=

							{$motd}


							1
							World
							Powered by liquidMS

							This room queries all available rooms local to the node.
							YOU CANNOT REGISTER NETGAMES HERE!

							=MOTD=

							{$motd}


							{$maincontent}
							END;
				}else{
					$response->code(500);
					$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
				}
		});

		$router->respond('GET', '/[:roomId]', function($request, $response){
				$rooms = NetgameModel::getInstance()->getRooms($request->roomId);
				if( $rooms["error"] == 0 ){
					if( $rooms["rows"] > 0 ){
						$maincontent = "";

						foreach($rooms["data"] as $room_index => $room_value){
							if($room_value["origin"] != 'localhost'){
								$roomname_token = "@{$room_value["roomname"]}";
								$description_token = "@{$room_value["origin"]}\n{$room_value["roomname"]}\n{$room_value["description"]}";
							}else{
								$roomname_token = "{$room_value["roomname"]}";
								$description_token = "{$room_value["description"]}";
							}

							$maincontent .= $room_value["roomid"]."\n".
											"$roomname_token\n".
											"$description_token\n\n\n";
						}
						return <<<END
								{$maincontent}

								END;
					}else{
						$response->code(404);
						return "No such room\n";
					}
				}else{
					$response->code(500);
					$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
				}

		});

		$router->respond('GET', '/[:roomId]/servers', function($request, $response, $service){
			$servers = NetgameModel::getInstance()->getServers($request->roomId);

			if( intval($request->roomId) == 1){
				#var_dump($servers);
				$rooms = NetgameModel::getInstance()->getWorldRooms();
				if( ($servers["error"] == 0) && ($rooms["error"] == 0) ){
					if( ($servers["rows"] > 0) && ($rooms["rows"] > 0) ){
						$service->render(__DIR__."/SRB2HTTP/MultiroomView.php", ["data" => $servers, "rooms" => $rooms]);
					}else{
						//$response->code(404);
						return "{$request->roomId}\n\n";
					}
				}else{
					$response->code(500);
					if( ($servers["error"] != 0)){ $service->render(__DIR__."/ErrorView.php", ["response" => $servers]); };
					if( ($rooms["error"] != 0)){ $service->render(__DIR__."/ErrorView.php", ["response" => $rooms]); };
				}
			}else{
				if( $servers["error"] == 0 ){
					$service->render(__DIR__."/SRB2HTTP/SingleroomView.php", ["data" => $servers, "room" => $request->roomId]);
				}else{
					$response->code(500);
					$service->render(__DIR__."/ErrorView.php", ["response" => $servers]);
				}
			}
		});
});

$router->respond('GET', "{$basepath}/*?", function($request, $response){
		$response->code(400);
		return "Unknown action\n";
});
?>

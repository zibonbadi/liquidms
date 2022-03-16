<?php
require_once __DIR__.'/modules/ConfigModel.php';
require_once __DIR__.'/modules/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

$router->with('/servers', function() use ($router){
	$router->respond('GET', '/?', function($request, $response, $service){
			// Server test kludge. The game seems to ping every listed server and
			// filter by response. Listing dummy servers is thus not possible.
			$servers = NetgameModel::getServers();
			$rooms = NetgameModel::getRooms();
				if( ($servers["error"] == 0) && ($rooms["error"] == 0) ){ $service->render(__DIR__."/modules/MultiroomView.php", ["data" => $servers, "rooms" => $rooms]);
				}else{
					$response->code(403);
					if( ($servers["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]); };
					if( ($rooms["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $rooms]); };
				}
	});
	$router->respond('POST', '/[:serverid]?/update', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			NetgameModel::changeServer(2, $request->ip(), $request->serverid, $info['title'], null, null);
			if( $rooms["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});

	$router->respond('POST', '/[:serverid]?/unlist', function($request, $response){
			parse_str($request->body(), $info);
			$request->ip();
			NetgameModel::changeServer(0, $request->ip(), $request->serverid, null, null, null);
			if( $rooms["rows"] > 0 ){
				// No Response body
				return;
			}else{
				return "No such server\n";
			}
	});
});

$router->with('/versions', function() use ($router){
		$router->respond('GET', '/[:versionId]', function($request, $response){
				#$versionstring = yaml_parse_file("config.yaml.example")["versions"][$request->versionId]; // Local var kludge
				#echo "Versionizer is here {$request->versionId}\n";
				$maincontent = "";
				$import = NetgameModel::getVersions(intval($request->versionId));
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
					return "${maincontent}";
				}else{
					$response->code(500);
					$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
				}
		});
});


/* POST API */
$router->with('/rooms', function() use ($router){
		$router->respond('POST', '/[:roomId]/register', function($request, $response){
				// Register Server and put ID here.  ID format is not specified; Vanilla 
				// returns numbers, we will return a random base64 string for security.
				if( intval($request->roomId) == 1){
						$response->code(403);
						return "403 Forbidden";
				}else{
						$rooms = NetgameModel::getRooms($request->roomId);
						if( $rooms["error"] == 0 ){
							if( $rooms["rows"] > 0 ){
								parse_str($request->body(), $info);
								NetgameModel::changeServer(1, $request->ip(), $info['port'], urlencode($info['title']), $info['version'], $rooms["data"][0]['roomname']);
								return $info['port'];
							}else{
								$response->code(404);
								return "No such room\n";
							}
						}else{
							$response->code(403);
							$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
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
				$rooms = NetgameModel::getRooms();
				if( $rooms["error"] == 0 ){
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
					$motd = ConfigModel::getConfig()["motd"]; // Local var kludge
					return <<<END
							0
							Universe
							Powered by liquidMS

							This room queries all available rooms, local and remote.

							=MOTD=

							${motd}


							1
							World
							Powered by liquidMS

							This room queries all available rooms local to the node.
							YOU CANNOT REGISTER NETGAMES HERE!

							=MOTD=

							${motd}


							{$maincontent}
							END;
				}else{
					$response->code(500);
					$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
				}
		});

		$router->respond('GET', '/[:roomId]', function($request, $response){
				$rooms = NetgameModel::getRooms($request->roomId);
				if( $rooms["error"] == 0 ){
					if( $rooms["rows"] > 0 ){
						$maincontent = "";

						foreach($rooms as $room_index => $room_value){
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
					$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
				}

		});

		$router->respond('GET', '/[:roomId]/servers', function($request, $response, $service){
			$servers = NetgameModel::getServers($request->roomId);

			if( intval($request->roomId) == 1){
				#var_dump($servers);
				$rooms = NetgameModel::getWorldRooms();
				if( ($servers["error"] == 0) && ($rooms["error"] == 0) ){
					if( ($servers["rows"] > 0) && ($rooms["rows"] > 0) ){
						$service->render(__DIR__."/modules/MultiroomView.php", ["data" => $servers, "rooms" => $rooms]);
					}else{
						//$response->code(404);
						return "{$request->roomId}\n\n";
					}
				}else{
					$response->code(500);
					if( ($servers["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]); };
					if( ($rooms["error"] != 0)){ $service->render(__DIR__."/modules/ErrorView.php", ["response" => $rooms]); };
				}
			}else{
				if( $servers["error"] == 0 ){
					$service->render(__DIR__."/modules/SingleroomView.php", ["data" => $servers, "room" => $request->roomId]);
				}else{
					$response->code(500);
					$service->render(__DIR__."/modules/ErrorView.php", ["response" => $servers]);
				}
			}
		});
});

$router->with('/', function() use ($router){
		$router->respond('GET', '*', function($request, $response){
				$response->code(400);
				return "Unknown action\n";
		});
});
?>

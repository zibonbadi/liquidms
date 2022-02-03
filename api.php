<?php
require_once __DIR__.'/src/ConfigModel.php';
require_once __DIR__.'/src/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;


$router->with('/servers', function() use ($router){
	$router->respond('GET', '/?', function($request, $response){
			// Server test kludge. The game seems to ping every listed server and
			// filter by response. Listing dummy servers is thus not possible.
			$import = NetgameModel::getServers();
			$maincontent = "";

		  $servers_sorted = [];
			foreach($import as $import_index => $import_value){
				// Re-sort into server numbers
				$slot["host"] = $import_value["host"];
				$slot["port"] = $import_value["port"];
				$slot["servername"] = $import_value["servername"];
				$slot["version"] = $import_value["version"];

				$servers_sorted[$import_value["roomid"]][] = $slot;
			}

			foreach($servers_sorted as $cat_index => $cat_data){
					// Generate content string
					$maincontent .= $cat_index."\n";
					foreach($cat_data as $server_index => $server_value){
					// Generate content string
							$maincontent .= $server_value["host"]." ".
									$server_value["port"]." ".
									$server_value["servername"]." ".
									$server_value["version"].
									"\n";
							#$maincontent .= $server_value."\n";
					}
					$maincontent .= "\n";
			}
			// Cut off newline for V1 compliance
			$maincontent = substr($maincontent,0,-1);
			return <<<END
			${maincontent}
			END;
	});
	$router->respond('POST', '/[:serverid]?/update', function($request, $response){
			// No Response body
			return;
	});

	$router->respond('POST', '/([serverId]*)/unlist', function($request, $response){
			// No Response body
			return;
	});
});

$router->with('/versions', function() use ($router){
		$router->respond('GET', '/[:versionId]', function($request, $response){
				#$versionstring = yaml_parse_file("config.yaml.example")["versions"][$request->versionId]; // Local var kludge
				#echo "Versionizer is here {$request->versionId}\n";
				$maincontent = "";
				$import = NetgameModel::getVersions(intval($request->versionId));
				// Technically an unspecified room would blurt out all. The
				// router takes care of it, but that's actually non-compliant.
				foreach($import as $ver_index => $ver_value){
					$maincontent .= $ver_value["gameid"]." ".
									$ver_value["name"]."\n";
				}
				return "${maincontent}";
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
						NetgameModel::publishServer($request);
						return "42";
				}
		});
		$router->respond('GET', '/?', function($request, $response){
				// The rooms Universe(0) and World(1) are technical and should always
				// be added with automatically generated MOTDs to indicate function
				//
				// Since network adresses too small to append to name (blame bitmap font),
				// the name of the fetch server shall be added as "@[address]" into
				// the first line of the MOTD.
				// Example: see dummy response

				// This is a demo mirror. Put DB queries here.
					$rooms = NetgameModel::getRooms();
					$maincontent = "";

					foreach($rooms as $room_index => $room_value){
						if($room_value["origin"] != ''){
							$roomname_token = "@{$room_value["roomname"]}";
							$description_token = "@{$room_value["origin"]}\n{$room_value["description"]}";
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

						=MOTD=

						${motd}


						{$maincontent}
						END;
		});

		$router->respond('GET', '/[:roomId]', function($request, $response){
					$rooms = NetgameModel::getRooms($request->roomId);
					$maincontent = "";

					foreach($rooms as $room_index => $room_value){
						if($room_value["origin"] != ''){
							$roomname_token = "@{$room_value["roomname"]}";
							$description_token = "@{$room_value["origin"]}\n{$room_value["description"]}";
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
		});

		$router->respond('GET', '/[:roomId]/servers', function($request, $response){
			$servers = NetgameModel::getServers($request->roomId);
			$maincontent = "";

			foreach($servers as $server_index => $server_value){
			  #echo "Querying server '{$server_index}': {$server_value["host"]}\n";
				$maincontent .= $server_value["host"]." ".
												$server_value["port"]." ".
												$server_value["servername"]." ".
												$server_value["version"].
												"\n";
			}
				return <<<END
						{$request->roomId}
						{$maincontent}
						END;
		});
});

$router->with('/', function() use ($router){
		$router->respond('GET', '*', function($request, $response){
				$response->code(404);
				return "Unknown action\n";
		});
});
?>

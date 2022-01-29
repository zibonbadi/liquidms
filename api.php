<?php
$router->with('/servers', function() use ($router){
	$router->respond('GET', '/?', function($request, $response){
			// Server test kludge. The game seems to ping every listed server and
			// filter by response. Listing dummy servers is thus not possible.
			$maincontent = file_get_contents("https://mb.srb2.org/MS/0/servers");
			return <<<END
			42
			127.0.0.1 5029 Dummy%20server 2.2.9

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
				$router->respond('GET', '/[:versionId]?', function($request, $response){
								$versionstring =
								yaml_parse_file("config.yaml.example")["versions"][$request->versionId]; // Local var kludge
								return "${versionstring}\n";
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
						$motd = yaml_parse_file("config.yaml.example")["motd"]; // Local var kludge
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


42
Dummy Room
Powered by liquidMS

You cannot do anything in this, it
merely serves to test the liquidMS API.


99
Dummy Room@
@dummynet.local
Powered by liquidMS

You cannot do anything in this, it
merely serves to test the liquidMS API.



END;
						//return "Bananarama";
				});

				$router->respond('GET', '/[:roomId]', function($request, $response){
								return <<<END
{$request->roomId}
Dummy Room
This is a dummy response.

You cannot do anything in this, it
merely serves to test the liquidMS API.


END;
								});

				$router->respond('GET', '/[:roomId]/servers', function($request, $response){
								return <<<END
{$request->roomId}
127.0.0.1 5029 Dummy%20server v2.2.9

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

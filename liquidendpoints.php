<?php
require_once __DIR__.'/src/ConfigModel.php';
require_once __DIR__.'/src/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

// Namespace for extended 
$router->with('/liquidms', function() use ($router){
	$router->respond('GET', '/?', function($request, $response, $service){
		$response->json($router);
	});

	$router->respond('PUT', '/?', function($request, $response){
		// Dumb JSON mirror for no reason
		// PUT is idempotent; Screw POST
		parse_str($request->body(), $reqdata);
		$response->json($reqdata);
	});

	$router->respond('GET', '/snitch', function($request, $response){
		// Get all known netgames as a CSV table (e.g. for snitching to other nodes)
		$response->header('Content-Type','text/csv;header=absent');
		$servers = NetgameModel::getServers();
		if($servers["error"] == 0){
			if($servers["rows"] > 0){
				$out = fopen('php://output', 'w');
				foreach($servers["data"] as $server){
					if(($server["origin"] == "localhost") || ($server["origin"] == "127.0.0.1")){ $server["origin"] = $_SERVER["SERVER_NAME"]; }
					fputcsv($out, $server);
				}
				//$response->json($servers["data"]);
			}else{
				$response->code(404);
				$response->json($servers["data"]);
			}
		}else{
			$response->code(500);
			$response->json($servers);
			//return "\n";
		}
	});

	$router->respond('POST', '/snitch', function($request, $response){
		// Provide some CSV text and it'll get parsed into tables
		//$csvdata[] = str_getcsv($request->body());
		$csvdata = [];
		$files = $request->files();
		foreach( $files["files"]["tmp_name"] as $fileId => $file){
			#echo(file_get_contents($file));
			$csvlines = explode("\n",rtrim(file_get_contents($file),"\n"));
			$csvdata = array_merge( $csvdata, array_map('str_getcsv', $csvlines) );
		}
		// Keep kosher entries, discard the rest

		// For now, just mirror what got parsed for testing
		$response->json($csvdata);
	});
});

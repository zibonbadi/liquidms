<?php
require_once __DIR__.'/src/ConfigModel.php';
require_once __DIR__.'/src/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

// Namespace for extended 
$router->with('/liquidms', function() use ($router){
	$router->respond('GET', '/?', function($request, $response, $service){
		$response->json([
		"routes" => [
			"/license",
			"/snitch",
			]
		]);
	});

	$router->respond('GET', '/license/?', function($request, $response, $service){
		$response->header('Content-Type','text/plain;syntax=markdown');
		return file_get_contents(__DIR__."/LICENSE.md");
	});

	$router->respond('PUT', '/?', function($request, $response){
		// Dumb JSON mirror for no reason
		// PUT is idempotent; Screw POST
		parse_str($request->body(), $reqdata);
		$response->json($reqdata);
	});

	$router->respond('GET', '/snitch/?', function($request, $response){
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
				return "";
			}
		}else{
			$response->code(500);
			$response->json($servers);
			//return "\n";
		}
	});

	$router->respond('POST', '/snitch', function($request, $response, $service){
		// Provide some CSV text and it'll get parsed into tables
		//$csvdata[] = str_getcsv($request->body());
		$csvdata = [];
		$files = $request->files();

		foreach( $files["files"]["tmp_name"] as $fileId => $file){
			//Formatting
			$csvlines = explode("\n",rtrim(file_get_contents($file),"\n"));
			$csvdata_raw = array_map('str_getcsv', $csvlines);
			foreach($csvdata_raw as $csvnetgameId => $csvnetgame){
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

		#var_dump($csvdata);
		foreach($csvdata as $netgameId => $netgame){
			// Check entries. Keep halal ones, discard the rest
			echo $netgame["host"];
			if(
				($netgame["host"] == "localhost") ||
				($netgame["host"] == "127.0.0.1") ||
				($netgame["origin"] == "localhost") ||
				($netgame["origin"] == "127.0.0.1")
			){
				unset($csvdata[$netgameId]);
			}
		}

		// I'll think of something
		$dbresponse = NetgameModel::pushServers($csvdata);

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
			$response->code(500);
			$response->json( [
			"status" => $response->code(),
			"message" => $service->render(__DIR__."/src/ErrorView.php", ["response" => $dbresponse]),
			] );
		}

	});
});

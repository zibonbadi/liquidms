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

function normalizeName(string $name): string{
	$name = urldecode($name);
	$name = preg_replace('/[[:cntrl:]]/', '', $name);
	$name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
	return $name;
}

function fetchUpdate_mkContext(String $method, array|null $headers = []){
   // Emergency exit; return null in case the data is bad
   if(gettype($headers) == "NULL" || empty($headers)){
	   return null;
   }

   // Stream context for HTTP fetch
   $stream_context = null;
   $stream_context_opts = ["http" => [ "method" => $method] ];

   // sco = Stream Context Option
   $sco_header = "";
   foreach($headers as $sco_key => $sco_value ){
	   $sco_header .= "{$sco_key}: {$sco_value}\r\n";
   }

   // Construct context
   if($sco_header != ""){
	   $stream_context_opts["http"]["header"] = $sco_header;
   }

   $stream_context = stream_context_create($stream_context_opts);
   return $stream_context;
}

function fetchUpdate(array $config, array $jobs = []){
	// Internal data is gonna (somewhat) resemble the JSON data from ChaosNet's
	// Game objects, just without the ActivityPub stuff.
	//
	// ```JSON
	// [{
	// 	"name": "My Server" # non-printable chars stripped, URL-encoded
	// 	"game_host": "203.0.113.42" # non-printable chars stripped, URL-encoded
	// 	"game_port": 5029
	// 	"game_api_name": "whateverapi" # internal codename
	// 	"game_api_data": {} # API-dependent. Handled by adapter.
	// 	"external_origin": "hostname" | null # non-printable chars stripped, URL-encoded
	// 	"origin_node": "node URI" # non-printable chars stripped, URL-encoded
	//	"updated": "ISO 8601 timestamp" # When in doubt, just add time()
	// }, ...]
	// ```
	//
	// Reason for this is that it handles api-specific
	// data structures agnostically.
	//
	// Instead, we let each fetch_*/snitch_* adapter handle the data structure.
	
	$rVal = []; // Return value

	foreach($jobs as $jobname => $jobval) {
		echo "[".date(DateTime::ISO8601, time())." {$jobname}] Fetching \"{$jobval["host"]}\"...\n";
		$sv_new = [];
		$currentjob = $config["src"][$jobname];
		switch($jobval["api"]){
		case "chaosnet":{ $sv_new = fetchUpdate_chaosnet($config ,$currentjob); break; }
		case "snitch":{ $sv_new = fetchUpdate_snitchapi($config ,$currentjob); break; }
		case "srb2kart":{ $sv_new = fetchUpdate_srb2kart($config ,$currentjob); break; }
		case "srb2http": { $sv_new = fetchUpdate_srb2http($config ,$currentjob); break; }
		case "srb2legacy": { $sv_new = fetchUpdate_srb2legacy($config ,$currentjob); break; }
		default: {
			echo "[".date(DateTime::ISO8601, time())."] Invalid API \"{$jobval["api"]}\". Job skipped.\n";
			$sv_new = [];
			break;
			}
		}
		$rVal = array_merge($rVal, $sv_new);
	}

   return $rVal;
}

//// FETCH functions ////

function fetchUpdate_snitchapi(array $config, array $job = []){

	// === Snitch API source ===
	// "game_api_name" => "snitch" is not to be treated as a real API name.
	// Instead the adapters should try to resolve
	// the netgames' API to `srb2http` or `srb2kart`

	$rVal = [];
	if (($handle = fopen(rtrim($job["host"], "/"), "r")) !== FALSE) {
		while(($data = fgetcsv($handle, null, ",", '"', "\\")) !== FALSE) {
			if ($data != null and $data[0] != NULL) {
				$game_obj = [
					"name" => normalizeName($data[2]),
					"game_host"		 => $data[0],
					"game_port"		 => $data[1],
					# Making Snitch transparent to ChaosNet
					# Are we dealing with hax (version == "SRB2Kart") ?
					"game_api_name"	=> ($data[3] == "SRB2Kart") ? "srb2kart": "srb2http",
					# Make Snitch transparent -> treat Snitch as origin_node
					"external_origin" => $data[5] ?? NULL,
					"origin_node"	  => rtrim($job["host"], "/"),
				];

				if($data[3] == "SRB2Kart"){
					// SRB2Kart Game
					$game_obj["game_api_data"] = [
						"host"		 => $data[0],
						"port"		 => $data[1],
						"contact" => $data[2],
						"game"	 => $data[4],
					];
				}else{
					// SRB2HTTP game
					$game_obj["game_api_data"] = [
						"host"		 => $data[0],
						"port"		 => $data[1],
						"servername" => $data[2],
						"version"	 => $data[3],
						"roomname"	 => $data[4],
					];
				}

				$rVal[] = $game_obj;
			}
		}
		fclose($handle);
	}

	return $rVal;
}


function fetchUpdate_chaosnet(array $config, array $job = []){
	$rVal = [];

	$stream_context = null;
	if(array_key_exists("http-header",$job)){
		$stream_context = fetchUpdate_mkContext("GET", $job["http-header"]);
	}else{
		$stream_context = fetchUpdate_mkContext("GET");
	}

	$baseUrl = rtrim($job["host"], "/");
	$page = 1;
	$pagesize = 200;
	$seen = [];

	do{
		$url = "{$baseUrl}/collection?page={$page}&pagesize={$pagesize}";
		$response = file_get_contents($url, false, $stream_context);
		if($response === FALSE){
			echo "[".date(DateTime::ISO8601, time())."] ERROR: HTTP request failed for {$url}\n";
			break;
		}

		$collection = json_decode($response, true);
		if($collection === NULL){
			echo "[".date(DateTime::ISO8601, time())."] ERROR: Invalid JSON from {$url}\n";
			break;
		}

		$items = $collection["items"] ?? [];
		if(!is_array($items) || empty($items)){
			break;
		}

		foreach($items as $item){
			if(!is_array($item)){ continue; }

			$host = $item["game_host"] ?? "";
			$port = $item["game_port"] ?? 0;
			$gameName = $item["name"] ?? "unknown";
			$apiName = $item["game_api_name"] ?? "unknown";
			$apiData = $item["game_api_data"] ?? [];
			
			// Skip redundant entries //
			$dedupKey = "{$host}:{$port}:{$apiName}";
			if(isset($seen[$dedupKey])){ continue; }
			$seen[$dedupKey] = true;

			// Now that we know the dedupKey, fill in the SHA256 ID
			$gameId = $item["id"] ?? hash("sha256", "{$host}|{$host}|{$apiName}");

			// Use full ChaosNet object for completeness' sake
			$row = $item;
			// Fill in critical fields
			$row["id"] = $gameId;
			$row["name"] = $gameName;
			$row["game_host"] = $host;
			$row["game_port"] = $port;
			$row["game_api_name"] = $apiName;
			$row["game_api_data"] = $apiData;
			//$row["updated"] is being handled by $row = $item;

			/*
			$row = [
				"_api" => $apiName,
				"_origin" => $item["external_origin"] ?? $item["origin_node"] ?? parse_url($job["host"])["host"] ?? "",
				"host" => $host,
				"port" => $port,
				"servername" => $item["name"] ?? $apiData["name"] ?? $apiData["servername"] ?? "Unknown",
				"version" => $apiData["version"] ?? $item["game_api_version"] ?? "",
				"roomname" => $apiData["roomname"] ?? $apiData["room"] ?? "",
			];
			foreach($item as $k => $v){
				if(!in_array($k, ["host", "port", "api_name", "name", "external_origin", "origin_node", "path", "updated", "type", "id", "game_host", "game_port", "game_api_name", "game_api_data", "game_api_version"])){
					$row[$k] = $v;
				}
			}
			*/

			$rVal[] = $row;
		}

		$page++;
		$totalItems = $collection["totalItems"] ?? 0;
		$maxPages = max(1, (int)ceil($totalItems / $pagesize));
	}while($page <= $maxPages);

	return $rVal;
}

function fetchUpdate_srb2http(array $config, array $job = []){
   $rVal = []; // Return value

   // Get stream context for header configs
   $stream_context = null;
   if(array_key_exists("http-header",$job)){ 
	   $stream_context = fetchUpdate_mkContext("GET", $job["http-header"]);
   }else{
	   $stream_context = fetchUpdate_mkContext("GET");
   }
   #var_dump($stream_context);

   $res_rooms = file_get_contents(
		   rtrim($job["host"], "/")."/rooms",
		   false,
		   $stream_context
		   );
   $res_server = file_get_contents(
		   rtrim($job["host"], "/")."/servers",
		   false,
		   $stream_context
		   );

   // Regex match room list into a group array:
   // - - "[Entire match]"
   //   - "[Room # as string]"
   //   - "[Room name]"
   //
   // Regex explained:
   // "/regex/m": multiline
   // "([0-9]+)\n": Capture room number (line with sole number on it)
   // "((?:[^\n]+\n)": Capture Room name
   preg_match_all("/([0-9]+)\n([^\n]+)\n/m", $res_rooms, $rooms, PREG_SET_ORDER);

   // Regex match server list into a group array:
   // - - "[Entire match]"
   //   - "[Room # as string]"
   //   - "[Multiline server table]"
   //
   // Regex explained:
   // "/regex/m": multiline
   // "([0-9]+)\n": Capture room number (line with sole number on it)
   // "((?:.*\n)*?": Capture server lines as block; Lines require additional filtering
   // "(?:\n|\Z)": Don't capture; match end of block (either \n or EOT)
   preg_match_all('/^([0-9]+)\n((?:.*\n)*?(?:\n|\Z))/m', $res_server, $servers, PREG_SET_ORDER);

   foreach($servers as $roomid =>  $roomdata){
	   // Break Server lists into Lines and feed the array

	   // Filter server block into distinct value arrays (step 2)
	   $serversplit = explode("\n",rtrim($roomdata[2],"\n"));

	   foreach($serversplit as $rowid =>  $rowdata){
		   $newrow = [];
		   $rowfields = explode(" ",$rowdata);

		   // Figure out server name
		   $roomname = NULL;
		   foreach($rooms as $r_infoid =>  $r_infodata){
			   if($roomdata[1] == $r_infodata[1]){
				   $roomname = $r_infodata[2];
				   break;
			   }
		   }

		   // Build Game object
		   $game_obj = [
				"name" => normalizeName($rowfields[2]),
				"game_host"		 => $rowfields[0],
				"game_port"		 => $rowfields[1],
				"game_api_name"	=> "srb2http",
				"game_api_data" => [
					"host"		 => $rowfields[0],
					"port"		 => $rowfields[1],
					"servername" => $rowfields[2],
					"version"	 => $rowfields[3],
					"roomname"	 => $roomname,
				],
				# Make Snitch transparent -> treat Snitch as origin_node
				"external_origin" => parse_url($job["host"])["host"],
				"origin_node"	  => NULL,
			];

			// Insert Game into list
			$rVal[] = $game_obj;
		}
   }

   return $rVal;
}

function fetchUpdate_srb2legacy(array $config, array $job = []){
   $rooms = []; // Room list
   $rVal = []; // Return value

   // Settings:
   //
   // $job["protocol_version"] = 12 | 21 => 1.09/2.1 protocol
   // $job["server_msg"]
   //   = GET_SERVER_MSG | GET_SERVERv2_MSG | GET_SHORT_SERVER_MSG | GET_EXT_SERVER_MSG
   //   => Server message type. GET_EXT_SERVER_MSG changes response data structures to enable IPv6 support

   $get_server_msg = 0; // Invalid message
   $ip_length = 16; // IPv4 by default

   switch($job["protocol_version"]){
	case 12:{
		// 1.09 protocol
		break;
	}
	case 21:{
		// 2.1 protocol
		break;
	}
	default:{
		echo "[".date(DateTime::ISO8601, time())."] Unsupported protocol version. Skipping...\n";
		return [];
	}
   }
   
   switch($job["server_msg"]){
	case "GET_SERVER_MSG":{			$get_server_msg = 200; $ip_length = 16; break;	}
	case "GET_SHORT_SERVER_MSG":{	$get_server_msg = 205; $ip_length = 16; break;	}
	case "GET_EXT_SERVER_MSG":{		$get_server_msg = 217; $ip_length = 40; break;	}	// Hacked elsewhere in for IPv6 support
	default:{
		echo "[".date(DateTime::ISO8601, time())."] Unsupported server message type. Skipping...\n";
		return [];
	}
   }

   // Establish connection to Legacy server
   $conn = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
   if(!socket_connect($conn,$job["host"], $job["port"])){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }
   
   // Get room data
   $get_rooms_packet = pack("NNNNa*", 0,210,0,1, "\0" ); # ID=0 TYPE=GET_ROOMS_MSG ROOM=0 LENGTH=0
   socket_send($conn, $get_rooms_packet, strlen($get_rooms_packet), 0);

   do{
		$res = [];
		// Part 1: Receive head
		$err = socket_recv($conn, $res_head, 16, 0);
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		
		$res = unpack("Nid/Nmsgtype/Nroom/Nlength", $res_head);
		$bytes_recv = $res["length"]; // Loop breaker
		if( $res["length"] <= 0){break;} # Breakout on empty message

		// Part 2: Receive body
		$err = socket_recv($conn, $res_body, 16+4+32+255, 0);
		if($err == false){ 	error_log(socket_strerror(socket_last_error($conn))); break; }
		$res["body"] = unpack("A16header/Vroomid/A32roomname/A255roomdesc", $res_body);

		# Add Room to internal room list
		if($res["body"]["roomid"] != 0){
			// Filter out standard room
			$rooms[$res["body"]["roomid"]] = [
				"_id" => $res["body"]["roomid"],
				"name" => $res["body"]["roomname"],
				"description" => $res["body"]["roomdesc"],
			];
		}
		
   }while($bytes_recv > 0);
   
   // Reconnect socket for servers
   socket_shutdown($conn);
   socket_close($conn);
   unset($conn);
   $conn = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
   if(!socket_connect($conn,$job["host"], $job["port"])){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }
   

   print("=== SERVERS ===\n");
   // Get Server Data
   #$get_servers_packet = pack("VVVV", 0,200, $i,0 ); # ID=0 TYPE=GET_SERVER_MSG ROOM=i LENGTH=0
   $get_servers_packet = pack("NNNNa*", 0,$get_server_msg, 0,0, "\0" ); # ID=0 TYPE=<see above> ROOM=0 (All rooms) LENGTH=0

   socket_send($conn, $get_servers_packet, strlen($get_servers_packet), 0);
 
   do{
		// Part 1: Head
		$err = socket_recv($conn, $res_head, 16, 0);
		
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		#$res["body"] = unpack("Vid/Vmsgtype/Vroom/Vlength/A16msgheader/A16ip/A8port/A32servername/Vroom/A8version", $res_rooms);
		$res = unpack("Nid/Nmsgtype/Nroom/Nlength", $res_head);
		$bytes_recv = $res["length"]; // Loop breaker
		if($res["length"] <= 0){break;} # Breakout on empty message

		// Part 2: Body
		$err = socket_recv($conn, $res_body, 16+$ip_length+8+32+4+8, 0);
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		$res["body"] = unpack("A16header/A{$ip_length}ip/A8port/A32servername/Vroom/A8version", $res_body);

		// Below: return value structure in YAML format (one server).
		// Defaults and examples are noted in paretheses:
		//
		// ---
		// - host: "[Server IP address (127.0.0.1)]"
		//   port: [Port (5029)]
		//   servername: "[Server name (SRB2%20server)]"
		//   version: "[Server version (2.2.9)]"
		//   roomname: "[Room name ("Casual", "World", etc.)]"
		//   origin: "[Room origin (mb.srb2.org)]"
		// ...
		//
		// The field "origin" is optional. If empty, it indicates a server
		// registered to the node's world.

		$rVal[] = [
		   "_api" => "srb2legacy",
		   "host" => $res["body"]["ip"],
		   "port" => $res["body"]["port"],
		   "servername" => urlencode(urldecode($res["body"]["servername"])),
		   "version" => $res["body"]["version"],
		   "roomname" => $rooms[$res["body"]["room"]]["name"],
		   "_origin" => $job["host"], // Extract hostname from URL
		];

		$game_obj = [
			"name" => normalizeName(res["body"]["servername"]),
			"game_host"		 => $res["body"]["ip"],
			"game_port"		 => $res["body"]["port"],
			# SRB2Legacy's data structure is identical to SRB2HTTP
			"game_api_name"	=> "srb2http",
			"game_api_data" => [
				"host"		 => $res["body"]["ip"],
				"port"		 => $res["body"]["port"],
				"servername" => $res["body"]["servername"],
				"version"	 => $res["body"]["version"],
				"roomname"	 => $rooms[$res["body"]["room"]]["name"],
			],
			# Make Snitch transparent -> treat Snitch as origin_node
			"external_origin" => $job["jost"],
			"origin_node"	  => NULL,
		];
		$rVal[] = $game_obj;

   }while($bytes_recv > 0);

   // Close connection
   socket_close($conn);
   return $rVal;
}


function fetchUpdate_srb2kart(array $config, array $job = []){
   $rVal = []; // Return value

   // Get stream context for header configs
   $stream_context = null;
   if(array_key_exists("http-header",$job)){ 
	   $stream_context = fetchUpdate_mkContext("GET", $job["http-header"]);
   }else{
	   $stream_context = fetchUpdate_mkContext("GET");
   }

   $res_server = file_get_contents(
		   rtrim($job["host"], "/")."?v=".$job["api_version"],
		   false,
		   $stream_context
		   );

	$res_server = explode("\n", $res_server);
	

	foreach($res_server as $rowid =>  $rowdata){

		$newrow = [];
		#$rowfields = explode(" ",$rowdata);
		preg_match_all('/^([^\s]*)\s+([^\s]*)\s+(.*)/', $rowdata, $rowfields, PREG_SET_ORDER);

		// Build Game object for return value
		$game_obj = [
			"name" => normalizeName($rowfields[0][3]),
			"game_host"		 => $rowfields[0][1],
			"game_port"		 => $rowfields[0][2],
			"game_api_name"	=> "srb2kart",
			"game_api_data" => [
				"host"		 => $rowfields[0][1],
				"port"		 => $rowfields[0][2],
				"contact" => $rowfields[0][3],
				"game"	 => $job["srb2kart_game"],
			],
			# Make Snitch transparent -> treat Snitch as origin_node
			"external_origin" => parse_url($job["host"])["host"],
			"origin_node"	  => NULL,
		];
		$rVal[] = $game_obj;
	}

   return $rVal;
}


/*
 * SNITCH functions
 */

function snitch(Array $data, Array $dests){

	$rowCount = count($data);
	$srb2http_count= 0;
	$srb2legacy_count= 0;
	$srb2kart_count= 0;

	echo "[".date(DateTime::ISO8601, time())."] Processing {$rowCount} rows of data...\n";

	if($rowCount < 1){
		echo "[".date(DateTime::ISO8601, time())."] No data to propagate. Skipping...\n";
		return;
	}


	/**
	 * Count cached netgames by MS API
	 */
	$api_counter = [];
	foreach($data as $netgame_i => $netgame_v){
		if(!array_key_exists($netgame_v["game_api_name"], $api_counter)){
			$api_counter[$netgame_v["game_api_name"]] = 0;
		}
		$api_counter[$netgame_v["game_api_name"]]++;
	}

	// List API cache counters
	foreach($api_counter as $api => $count){
		echo "[".date(DateTime::ISO8601, time())."] Cached ".$count." netgames from API \"".$api."\".\n";
	}

	foreach($dests as $dest_i => $dest_v){
		switch($dest_v["api"]){
		case "chaosnet":{
			echo "[".date(DateTime::ISO8601, time())."] PUSH -> CHAOSNET \"{$dest_v["host"]}\"...\n";
			echo snitch_chaosnet($data, $dest_v["host"]);
			break;
		}
		case "snitch":{
			echo "[".date(DateTime::ISO8601, time())."] PUSH -> SNITCH \"{$dest_v["host"]}\"...\n";
			echo snitch_snitchapi($data, $dest_v["host"]);
			break;
		}
		default: {
			echo "[".date(DateTime::ISO8601, time())."] [{$dest_i}] Invalid SNITCH API \"{$dest_v["api"]}\". Job skipped.\n";
			break;
			}
		}

	}

}

function snitch_snitchapi(Array $data, String $url){
	$csvContent = "";
	$http_response = "";
	$multipart_boundary = '--------------------------'.microtime(true);
	$multipart_fieldname = 'data';
	$multipart_filename = 'snitch.csv';

	foreach($data as $dataIndex => $dataRow){
		// Create data
		#echo "[".date(DateTime::ISO8601, time())."] Processing row {$dataIndex}...\n";

		$dRow_clean = NULL;
		switch($dataRow["game_api_name"]){
			case "srb2http":{
				// Clean, beautiful SRB2HTTP rows
				$dRow_clean = [
					"host" => $dataRow["game_api_data"]["host"] ?? $dataRow["game_host"],
					"port" => $dataRow["game_api_data"]["port"] ?? $dataRow["game_port"],
					"servername" => $dataRow["game_api_data"]["servername"] ?? ["name"],
					"version" => $dataRow["game_api_data"]["version"],
					"roomname" => $dataRow["game_api_data"]["roomname"],
					"origin" => $dataRow["external_origin"],
				];
				break;
			}
			case "srb2kart":{	
				// Hacky SRB2Kart rows	
				$dRow_clean = [
					"host" => $dataRow["game_api_data"]["host"] ?? $dataRow["game_host"],
					"port" => $dataRow["game_api_data"]["port"] ?? $dataRow["game_port"],
					"servername" => $dataRow["game_api_data"]["contact"] ?? ["name"],
					"version" => "SRB2Kart",
					"rommname" => $dataRow["game_api_data"]["game"],
					"origin" => $dataRow["external_origin"],
				];
				break;
			}
			default:{
				// Unsupported API -> Skip
				continue 2;
				break;
			}
		}


		// Write CSV to var. Iterative opening may
		// be slower, but guarantees clean output
		if($dRow_clean != NULL){
			$tmp = fopen('php://memory', 'r+');
			if( !fputcsv($tmp, $dRow_clean, ",", '"', "\\") ){ continue; }
			rewind($tmp);
			$csvContent .= stream_get_contents($tmp);
			fclose($tmp);
		}
	}
	$csvContent = rtrim($csvContent, "\n");

	/*/ NEW METHOD: cURL /*/

	$cStringFile = new CurlStringFile($csvContent, $multipart_filename, "text/csv; header=absent");

	$creq = curl_init();

	curl_setopt_array($creq, [
		CURLOPT_URL =>  $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => [$cStringFile],
		CURLOPT_HTTPHEADER =>[ "Content-type: multipart/form-data"]
		]);

	$http_response = curl_exec($creq);
	curl_close($creq);

	return $http_response."\n";
}

function snitch_chaosnet(Array $data, String $url){
	$config = \LiquidMS\ConfigModel::getConfig();

	$nodeActor = $config["node_actor_uri"] ?? null;
	if($nodeActor === null){
		$nodeHost = $config["node_host"] ?? null;
		# Later: Skip "id" when no node_host available
		if($nodeHost !== NULL)
			$nodeActor = "https://{$nodeHost}";
	}

	$items = [];
	foreach($data as $netgame){
		$gameHost = $netgame["game_host"] ?? "";
		$gamePort = $netgame["game_port"] ?? 0;
		$apiName = $netgame["game_api_name"] ?? "unknown";
		$name = $netgame["name"] ?? "Unknown";

		$apiData = $netgame["game_api_data"];

		$game = [
			"type" => "Game",
			"id" => hash("sha256", "{$gameHost}|{$gamePort}|{$apiName}"),
			"name" => $name,
			"game_host" => $gameHost,
			"game_port" => (int)$gamePort,
			"game_api_name" => $apiName,
			"game_api_data" => $apiData,
			"updated" => $netgame["updated"] ?? date(DateTime::ISO8601, time())
		];

		$ext_origin = $netgame["external_origin"] ?? null;
		if($ext_origin !== null && $ext_origin !== "" && $ext_origin !== "localhost"){
			$game["external_origin"] = $ext_origin;
		}
		
		$origin_node = $netgame["origin_node"] ?? null;
		if($origin_node !== null && $origin_node !== "" && $origin_node !== "localhost"){
			$game["origin_node"] = $origin_node;
		}

		$items[] = $game;
	}

	if(empty($items)){
		return "[".date(DateTime::ISO8601, time())."] No netgames to snitch to \"{$url}\".\n";
	}

	$deliverActivity = [
		"@context" => "https://www.w3.org/ns/activitystreams",
		"type" => "Deliver",
		"object" => [
			"type" => "OrderedCollection",
			"items" => $items,
		],
	];

	# Skip "id" field when no node_host available
	if($nodeActor !== null && $nodeActor !== "" && $nodeActor !== "localhost"){
		$deliverActivity["actor"] = $nodeActor;
	}

	$payload = json_encode($deliverActivity, JSON_UNESCAPED_SLASHES);

	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $payload,
		CURLOPT_HTTPHEADER => [
			"Content-Type: application/activity+json",
			"Content-Length: " . strlen($payload),
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 30,
	]);

	$http_response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return "[".date(DateTime::ISO8601, time())."] ChaosNet snitch to {$url} returned {$http_code}: {$http_response}\n";
}

?>

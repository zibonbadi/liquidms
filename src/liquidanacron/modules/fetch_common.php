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
	// Internal data is gonna (somewhat) resemble the raw data from within
	// "netgames" of the SnitchV2 specification. Reason for this is that it
	// handles api-specific data structures agnostically.
	//
	// ```YAML
	// - _api: blah # Required
	//   _origin: local.invalid # Not required; absence implies world
	//   someapispecificthing: 127.0.0.1
	// - ...
	// ```
	
	$rVal = []; // Return value

	foreach($jobs as $jobname => $jobval) {
		echo "[".date(DateTime::ISO8601, time())." {$jobname}] Fetching \"{$jobval["host"]}\"...\n";
		$sv_new = [];
		$currentjob = $config["src"][$jobname];
		switch($jobval["api"]){
		case "snitchv2":{ $sv_new = fetchUpdate_snitchv2($config ,$currentjob); break; }
		case "snitch":{ $sv_new = fetchUpdate_snitchv1($config ,$currentjob); break; }
		case "srb2kart":{ $sv_new = fetchUpdate_srb2kart($config ,$currentjob); break; }
		case "srb2http": { $sv_new = fetchUpdate_v1($config ,$currentjob); break; }
		case "srb2legacy": { $sv_new = fetchUpdate_srb2legacy($config ,$currentjob); break; }
		default: {
			echo "[".date(DateTime::ISO8601, time())."] Invalid API \"{$jobval["api"]}\". Job skipped.\n";
			$sv_new = [];
			break;
			}
		}
		$rVal = array_merge($rVal, $sv_new);
	}

   // Below: return value structure in YAML format (one server).
   // Defaults and examples are noted in parentheses:
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
   return $rVal;
}

function fetchUpdate_snitchv1(array $config, array $job = []){

	$rVal = [];
	if (($handle = fopen(rtrim($job["host"], "/"), "r")) !== FALSE) {
		while(($data = fgetcsv($handle, null, ",")) !== FALSE) {
			if ($data != null and $data[0] != NULL) {
				$row = [];
				$row["host"] = $data[0];
				$row["port"] = $data[1];
				$row["servername"] = $data[2];
				$row["version"] = $data[3];
				$row["roomname"] = $data[4];
				$row["origin"] = $data[5];
				$row["_origin"] = $data[5];
				$row["_api"] = "snitch";
				$rVal[] = $row;
			}
		}
		fclose($handle);
	}
	#var_dump($rVal);

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
	return $rVal;

}

function fetchUpdate_snitchv2(array $config, array $job = []){

   // SETUP: Stream context & vars
   $rVal = []; // Empty dataset -> safe to pass on
   $stream_context = null;
   if(array_key_exists("http-header",$job)){ 
	   $stream_context = fetchUpdate_mkContext("GET", $job["http-header"]);
   }else{
	   $stream_context = fetchUpdate_mkContext("GET");
   }

	// 1. Get JSON from SnitchV2
   $response = file_get_contents(
		   rtrim($job["host"], "/"),
		   false,
		   $stream_context
		   );
	if ($response !== FALSE) {
	
		// 2. Parse into ~~object~~ associative array
		$res_o = json_decode($response, true);
		
		if ($res_o !== NULL) {
			// 3a. Validate API
			switch($res_o["api_version"]){
			case "2.0":{
				// 3b. Unwrap
				$rVal = $res_o["netgames"];
				break;
			}
			default:{break;}
			}
		}else{
			echo "[".date(DateTime::ISO8601, time())."] ERROR: Invalid response JSON. Job skipped. \n";
		}
	}else{
		echo "[".date(DateTime::ISO8601, time())."] ERROR: HTTP request failed. Job skipped.\n";
	}

	// 4. Profit
	// The field "origin/"_origin" is optional. If empty, it indicates a server
	// registered to the node's world.
	return $rVal;

}

function fetchUpdate_v1(array $config, array $job = []){
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
	   // - - "[server line]"
	   //   - "[IP]"
	   //   - "[port]"
	   //   - "[name]"
	   //   - "[version]"
	   $serversplit = explode("\n",rtrim($roomdata[2],"\n"));

	   foreach($serversplit as $rowid =>  $rowdata){
		   $newrow = [];
		   $rowfields = explode(" ",$rowdata);

		   // Figure out server name
		   $roomname = "Dummy name";
		   foreach($rooms as $r_infoid =>  $r_infodata){
			   if($roomdata[1] == $r_infodata[1]){
				   $roomname = $r_infodata[2];
				   break;
			   }
		   }

		   // Build return value conforming entry
		   $newrow["_api"] = "srb2http";
		   $newrow["host"] = $rowfields[0];
		   $newrow["port"] = intval($rowfields[1]);
		   $newrow["servername"] = $rowfields[2];
		   $newrow["version"] = $rowfields[3];
		   $newrow["roomname"] = $roomname;
		   $newrow["_origin"] = parse_url($job["host"])["host"]; // Extract hostname from URL

		   // Insert entry
		   $rVal[] = $newrow;
	   }
   }

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

		// Build return value conforming entry
		$newrow["_api"] = "srb2kart";
		$newrow["host"] = $rowfields[0][1];
		$newrow["port"] = intval($rowfields[0][2]);
		$newrow["servername"] = $rowfields[0][3];
		$newrow["version"] = "_srb2kart";
		$newrow["roomname"] = $job["srb2kart_game"];
		$newrow["_origin"] = parse_url($job["host"])["host"]; // Extract hostname from URL

		// Insert entry
		$rVal[] = $newrow;
	}

   // Below: return value structure in YAML format (one server).
   // Defaults and examples are noted in paretheses:
   //
   // ---
   // - host: "[Server IP address (127.0.0.1)]"
   //   port: [Port (5029)]
   //   servername: "[Server name (SRB2kart%20server)]"
   //   version: "_srb2kart" to denote it being from the Kart API
   //   roomname: Game name
   //   origin: "[Room origin (ms.kartkrew.org)]"
   // ...
   //
   // The field "origin" is optional. If empty, it indicates a server
   // registered to the node's world.
   return $rVal;
}


/*
 * SNITCH functions
 */

function snitch(Array $data, Array $dests){

	$rowCount = count($data);
	$srb2http_count= 0;
	$srb2kart_count= 0;

	echo "[".date(DateTime::ISO8601, time())."] Processing {$rowCount} rows of data...\n";

	if($rowCount < 1){
		echo "[".date(DateTime::ISO8601, time())."] No data to propagate. Skipping...\n";
		return;
	}

	/**
	 * Cache SRB2-filtered Netgames (for Snitch V1/legacy)
	**/
	foreach($data as $netgame_i => $netgame_v){
		echo "NETGAME API #$netgame_i => ".$netgame_v["_api"]."\n";
		if($netgame_v["_api"] === "srb2http" || $netgame_v["_api"] === "srb2legacy")
			$srb2http_count++;
		if($netgame_v["_api"] === "srb2kart" || $netgame_v["_api"] === "srb2legacy")
			$srb2kart_count++;
	}
	echo "[".date(DateTime::ISO8601, time())."] Cached SRB2HTTP-related netgames (".$srb2http_count." netgames)\n";
	echo "[".date(DateTime::ISO8601, time())."] Cached SRB2Kart-related netgames (".$srb2kart_count." netgames)\n";


	foreach($dests as $dest_i => $dest_v){
		switch($dest_v["api"]){
		case "snitch_v2":{
			echo "[".date(DateTime::ISO8601, time())."] SNITCH SnitchV2 is not implemented yet!\n";
			#echo snitch_snitchv2();
			break;
		}
		case "snitch_v1":
		case "snitch":{
			echo "[".date(DateTime::ISO8601, time())."] SNITCH \"{$dest_v["host"]}\"...\n";
			echo snitch_snitchv1($data, $dest_v["host"]);
			break;
		}
		default: {
			echo "[".date(DateTime::ISO8601, time())."] [{$dest_i}] Invalid SNITCH API \"{$dest_v["api"]}\". Job skipped.\n";
			break;
			}
		}

	}

}

function snitch_snitchv1(Array $data, String $url){
	$csvContent = "";
	$http_response = "";
	$multipart_boundary = '--------------------------'.microtime(true);
	$multipart_fieldname = 'data';
	$multipart_filename = 'snitch.csv';

	foreach($data as $dataIndex => $dataRow){
		// Create data
		#echo "[".date(DateTime::ISO8601, time())."] Processing row {$dataIndex}...\n";

		// Write CSV to var. Iterative opening may
		// be slower, but guarantees clean output
		
		// Cleanup bc the intermediate array changed
		$dRow_clean = [
			"host" => $dataRow["host"],
			"port" => $dataRow["port"],
			"servername" => $dataRow["servername"],
			"version" => $dataRow["version"],
			"rommname" => $dataRow["roomname"],
			"origin" => $dataRow["_origin"],
		];
		$tmp = fopen('php://temp', 'r+');
		$csvChars = fputcsv($tmp, $dRow_clean);
		rewind($tmp);
		$csvContent .= fread($tmp, $csvChars);
		fclose($tmp);
	}
	rtrim($csvContent, "\n");

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

?>

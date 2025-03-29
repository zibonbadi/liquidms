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
		#case "kart":{ $sv_new = fetchUpdate_kart($config ,$currentjob); break; }
		case "srb2http": { $sv_new = fetchUpdate_v1($config ,$currentjob); break; }
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
		while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
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

/*
 * SNITCH functions
 */

function snitch(Array $data, Array $dests){

	$rowCount = count($data);
	$srb2http_cache = [];

	echo "[".date(DateTime::ISO8601, time())."] Processing {$rowCount} rows of data...\n";

	if($rowCount < 1){
		echo "[".date(DateTime::ISO8601, time())."] No data to propagate. Skipping...\n";
		return;
	}

	/**
	 * Cache SRB2-filtered Netgames (for Snitch V1/legacy)
	**/
	foreach($data as $netgame_i => $netgame_v){
		if($netgame_v["_api"] === "srb2http")
			$srb2http_cache[] = $netgame_v;
	}

	echo "[".date(DateTime::ISO8601, time())."] Cached SRB2HTTP-related netgames (".count($srb2http_cache)." netgames)\n";

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
			echo snitch_snitchv1($srb2http_cache, $dest_v["host"]);
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
		$tmp = fopen('php://temp', 'r+');
		$csvChars = fputcsv($tmp, $dataRow);
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

	curl_exec($creq);
	curl_close($creq);

	return $http_response."\n";
}

?>

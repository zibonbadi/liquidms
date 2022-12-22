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
	$rVal = []; // Return value

	foreach($jobs as $jobname => $jobval) {
		echo "[".date(DateTime::ISO8601, time())." {$jobname}] Fetching \"{$jobval["host"]}\"...\n";
		$sv_new = [];
		$currentjob = $config["fetch"][$jobname];
		#var_dump($jobval);
		#var_dump($currentjob);
		switch($jobval["api"]){
		case "snitch":{ $sv_new = fetchUpdate_snitch($config ,$currentjob); break; }
		case "v1":
		default:{ $sv_new = fetchUpdate_v1($config ,$currentjob); break; }
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

function fetchUpdate_snitch(array $config, array $job = []){

	$rVal = [];
	if (($handle = fopen(rtrim($job["host"], "/")."/liquidms/snitch", "r")) !== FALSE) {
		while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			$row = [];
			$row["host"] = $data[0];
			$row["port"] = $data[1];
			$row["servername"] = $data[2];
			$row["version"] = $data[3];
			$row["roomname"] = $data[4];
			$row["origin"] = $data[5];
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

function fetchUpdate_v1(array $config, array $job = []){
   $rVal = []; // Return value

   // Get stream context for header configs
   $stream_context = null;
   $stream_context = fetchUpdate_mkContext("GET", $job["http-header"]);
   var_dump($stream_context);

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
		   $newrow["host"] = $rowfields[0];
		   $newrow["port"] = intval($rowfields[1]);
		   $newrow["servername"] = $rowfields[2];
		   $newrow["version"] = $rowfields[3];
		   $newrow["roomname"] = $roomname;
		   $newrow["origin"] = parse_url($job["host"])["host"]; // Extract hostname from URL

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

function db_execute(string $query, array $config){

   // The following YAML structure will be used from `config.yaml`.
   //
   // db: # liquidMS DB connection settings
   //    dsn: # ODBC data source name
   //    user: # database user
   //    password: # database password

   // Sanity check
   if( !array_key_exists("dsn", $config["db"]) ){ echo 'No DSN string given in config.'; return false; }

   $odbcstring = $config["db"]["dsn"];

   $connection = odbc_connect(
	 $odbcstring, 
	 $config["db"]["user"],
	 $config["db"]["password"] );

   if($connection){
      $result = odbc_exec($connection, $query);
      if($result == false){ 
	 return [
	    "error" => odbc_error(), 
	    "message" => odbc_errormsg(),
	    "query" => $query,
	 ];
      }else{
	 $rTable = [
	    "error" => 0,
	    "message" => "Successfully executed.",
	    "data" => [],
	    "rows" => odbc_num_rows($result),
	 ];

	    // Checking for multiple results. Basically a hotfix
	    // for INSERT and UPDATE queries
	    $n_results = 0;
	    while(odbc_next_result($result)){ $n_results++; }

	    if( $n_results > 0 ){
	       while($row = odbc_fetch_array($result)) {
		  $rTable["data"][] = $row;
	       }
	    }
	    return $rTable;
      }
   }else{
      return [
	 "error" => odbc_error(), 
	 "message" => odbc_errormsg(),
	 "query" => $query,
      ];
   }
}

function snitch(Array $data, Array $finsters){
	// Couldn't come up with a better var name for peers to snitch to, so I referenced Recess.
	$rowCount = count($data);
	$csvContent = "";
	$http_response = "";
	$multipart_boundary = '--------------------------'.microtime(true);
	$multipart_fieldname = 'data';
	$multipart_filename = 'snitch.csv';

	echo "[".date(DateTime::ISO8601, time())."] Processing {$rowCount} rows of data...\n";

	if($rowCount < 1){
		echo "[".date(DateTime::ISO8601, time())."] No data to propagate. Skipping...\n";
		return;
	}

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

	//echo $csvContent;

	$httpcontent =  "--{$multipart_boundary}\r\n".
		"Content-Disposition: form-data; name=\"{$multipart_fieldname}\"; filename=\"{$multipart_filename}\"\r\n".
		"Content-Type: text/csv; header=absent\r\n\r\n".
		$csvContent."\r\n";

	// signal end of request (note the trailing "--")
	$httpcontent .= "--{$multipart_boundary}--\r\n";

	//echo $httpcontent."\n";

	$http_context = stream_context_create([
		"http" => [
			"method"  => "POST",
			// Request headers here
			"header"  => "Content-type: multipart/form-data; boundary={$multipart_boundary}",
			"content" => $httpcontent,
		]
	]);

	foreach($finsters as $finster){
		$url = rtrim($finster,'/')."/liquidms/snitch";
		echo "[".date(DateTime::ISO8601, time())."] Snitching to \"{$url}\"...\n";
		$response_tmp = file_get_contents( $url, false, $http_context);
		if($response_tmp !== false){
			$http_response .= $response_tmp;
		}
	}
	echo $http_response."\n";
}
?>

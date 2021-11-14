<?php
$config = yaml_parse_file(__DIR__."/config.yaml", -1);

// Merge all yaml configs together
$tmp = [];
foreach($config as $docname => $doc) {
   $tmp = array_merge_recursive($tmp, $doc);
}
$config = $tmp;


function fetchUpdate(array $config){
   $rVal = []; // Return value
   $response = "";
   foreach($config["fetch"] as $hostid => $hostname) {
      $res_rooms = file_get_contents(rtrim($hostname, "/")."/rooms");
      $res_server = file_get_contents(rtrim($hostname, "/")."/servers");

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
	    $newrow["origin"] = parse_url($hostname)["host"]; // Extract hostname from URL

	    // Insert entry
	    $rVal[] = $newrow;
	 }
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

function db_excecute(string $query, array $config){

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
	 #if( > 0 ){
	    while($row = odbc_fetch_array($result)) {
	       $rTable["data"][] = $row;
	    }
	 #}
	 return $rTable;
      }
   }else{
      return [
	 "error" => odbc_error(), 
	 "message" => odbc_errormsg(),
	 "query" => $query,
	 ];
   }

   #return $odbcstring;
}

$fetchdata = fetchUpdate($config);

// Generate insert values
$fetchsql = "";
foreach( $fetchdata as $serv_index => $serv_val){
   $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
}
$fetchsql = rtrim($fetchsql,", \n\r\t");

// Upsert database (yes that's a real word)
echo yaml_emit( db_excecute(
    "INSERT INTO servers (host, port, servername, version, roomname, origin)
    VALUES {$fetchsql}
    ON DUPLICATE KEY UPDATE
    host=VALUES(host), port=VALUES(port), servername=VALUES(servername), version=VALUES(version), roomname=VALUES(roomname), origin=VALUES(origin)",
   $config) );

/* 
// Reserved upsert for when MySQL actually supports MERGE from SQL:2003
echo yaml_emit( db_excecute(
    "MERGE INTO servers AS lms
       USING VALUES {$fetchsql} AS new (host, port, servername, version, roomname, origin)
       ON lms.host = new.host AND lms.port = new.port 
       WHEN MATCHED THEN
	  UPDATE host=VALUES(host), port=VALUES(port), servername=VALUES(servername), version=VALUES(version), roomname=VALUES(roomname), origin=VALUES(origin)
       WHEN NOT MATCHED THEN
	  INSERT INTO servers (host, port, servername, version, roomname, origin) VALUES (host, port, servername, version, roomname, origin)
    ",
   $config) );
*/

#echo yaml_emit( db_excecute( "SELECT * FROM servers", $config) );
?>

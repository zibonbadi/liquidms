<?php
include_once('clichecks.php');
include_once('fetch_common.php');

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
   //    server: # ODBC database server
   //    driver: # ODBC database driver
   //    user: # database user
   //    password: # database password
   //    database: # database to access

   // Sanity check
   if( !array_key_exists("db", $config) ){ echo 'No section "db" in config file. Please contact your administrator.'; return false; }
   if( !array_key_exists("server", $config["db"]) ){ echo 'No server given in database config. Please contact your administrator.'; return false; }
   if( !array_key_exists("driver", $config["db"]) ){ echo 'No driver given in database config. Please contact your administrator.'; return false; }
   if( !array_key_exists("user", $config["db"]) ){ echo 'No user given in database config. Please contact your administrator.'; return false; }
   if( !array_key_exists("password", $config["db"]) ){ echo 'No password given in database config. Please contact your administrator.'; return false; }
   if( !array_key_exists("database", $config["db"]) ){ echo 'No database given in database config. Please contact your administrator.'; return false; }

   $odbcstring = "DRIVER={$config["db"]["driver"]};SERVER={$config["db"]["server"]};DATABASE={$config["db"]["database"]};";

   $connection = odbc_connect(
	 $odbcstring, 
	 $config["db"]["user"],
	 $config["db"]["password"] );

   echo $odbcstring;

   if($connection){
      return odbc_exec($query);
   }else{
      return false;
      #throw new Exception("ODBC connection failed");
   }

   #return $odbcstring;
}

echo yaml_emit( fetchUpdate($config) );
echo db_excecute("SELECT * FROM `servers`", $config);
?>

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

      // Regex match server list into a group array:
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

echo yaml_emit( fetchUpdate($config) );
?>

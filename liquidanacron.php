<?php
require_once __DIR__.'/src/ConfigModel.php';
include_once(__DIR__.'/src/fetch_common.php');

use LiquidMS\ConfigModel;

// Get job list
ConfigModel::init();
$config = ConfigModel::getConfig(); // Local var kludge
var_dump($config);
$fetchjobs = array_keys($config["fetch"]);

#var_dump($fetchjobs);
// Preemptive cleanup
foreach( $fetchjobs as $job_i => $job_v){
   if(	 array_key_exists("day", $config["fetch"][$job_v]) ||
	 array_key_exists("hour", $config["fetch"][$job_v]) ||
	 array_key_exists("minute", $config["fetch"][$job_v])
     ){ continue; } // Skip working ones
   #echo $job_v." REMOVED!\n";
   unset($job_v); // Delete rest
}
// Start "daemon"
while(true){
   foreach( $fetchjobs as $job_i => $job_v){
      $currentjob = $config["fetch"][$job_v];
      $fetchdata = fetchUpdate($config, [$job_v]);

      // Generate insert values
      $fetchsql = "";
      foreach( $fetchdata as $serv_index => $serv_val){
	 $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
      }
      $fetchsql = rtrim($fetchsql,", \n\r\t");

      // Upsert database (yes that's a real word)
      $dbresponse = db_execute(
	  "INSERT INTO servers (host, port, servername, version, roomname, origin)
	  VALUES {$fetchsql}
	  ON DUPLICATE KEY UPDATE
	  host=VALUES(host), port=VALUES(port),
	  servername=VALUES(servername), version=VALUES(version),
	  roomname=VALUES(roomname), origin=VALUES(origin),
	  updated_at=CURRENT_TIMESTAMP",
	 $config);

      // Only report failures
      if($dbresponse["error"] != 0){ echo yaml_emit( $dbresponse ); }
      else{ echo $dbresponse["rows"]." rows upserted.\n"; }

      sleep(30);
      /* 
      // Reserved upsert for when MySQL actually supports MERGE from SQL:2003
      echo yaml_emit( db_execute(
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
   }
}
?>

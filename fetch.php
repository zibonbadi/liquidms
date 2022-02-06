<?php
require_once __DIR__.'/src/ConfigModel.php';
require_once __DIR__.'/src/TimestampModel.php';
require_once __DIR__.'/src/fetch_common.php';

use LiquidMS\ConfigModel;
use LiquidMS\TimestampModel;

ConfigModel::init();
TimestampModel::init();
$timestamps = TimestampModel::getData();
$config = ConfigModel::getConfig(); // Local var kludge

// Parse args into jobnames
$fetchjobs = [];
foreach( $argv as $argno => $argval ){
   if($argval === $argv[0]){ continue; } // Skip filename invocation
   if(array_key_exists($argval, $config["fetch"])){ $fetchjobs[$argval] = $config["fetch"][$argval]; }
}
if( empty($fetchjobs)){ $fetchjobs = $config["fetch"]; }

foreach( $fetchjobs as $jobId => $job ){
	$timestamps["fetch"][$jobId]["updated_at"] = date(DateTime::ISO8601, time());
}
TimestampModel::setData($timestamps);
TimestampModel::dumpData();

$fetchdata = fetchUpdate($config, $fetchjobs);

// Generate insert values
$fetchsql = "";
foreach( $fetchdata as $serv_index => $serv_val){
   $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
}
$fetchsql = rtrim($fetchsql,", \n\r\t");

switch($config["fetchmode"]){
	case "fetch":{
		// Upsert database (yes that's a real word)
		echo yaml_emit( db_execute(
			"INSERT INTO servers (host, port, servername, version, roomname, origin)
			VALUES {$fetchsql}
			ON DUPLICATE KEY UPDATE
			host=VALUES(host), port=VALUES(port), servername=VALUES(servername), version=VALUES(version), roomname=VALUES(roomname), origin=VALUES(origin)",
		   $config) );

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
		break;
	}case "snitch":{
		echo yaml_emit( snitch($fetchdata, $config["snitch"]) );
		break;
	}
}

?>

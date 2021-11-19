<?php
include_once('clichecks.php');
include_once('fetch_common.php');

// Parse args into jobnames
$fetchjobs = [];
foreach( $argv as $argno => $argval ){
   if($argval === $argv[0]){ continue; } // Skip filename invocation
   $fetchjobs[] = $argval;
}

$fetchdata = fetchUpdate($config, $fetchjobs);

// Generate insert values
$fetchsql = "";
foreach( $fetchdata as $serv_index => $serv_val){
   $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
}
$fetchsql = rtrim($fetchsql,", \n\r\t");

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

#echo yaml_emit( db_execute( "SELECT * FROM servers", $config) );
?>

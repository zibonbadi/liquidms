<?php
include_once('clichecks.php');
include_once('fetch_common.php');

// Parse args into jobnames
$fetchjobs = [];
foreach( $argv as $argno => $argval ){
   if($argval === $argv[0]){ continue; } // Skip filename invocation
   $fetchjobs[] = $argval;
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
   if( !array_key_exists("db", $config) ){ echo 'No section "db" in config file.'; return false; }
   if( !array_key_exists("dsn", $config["db"]) ){ echo 'No DSN string given in database config.'; return false; }
   if( !array_key_exists("user", $config["db"]) ){ echo 'No user given in database config.'; return false; }
   if( !array_key_exists("password", $config["db"]) ){ echo 'No password given in database config.'; return false; }

   $odbcstring = $config["db"]["dsn"];

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

#echo yaml_emit( fetchUpdate($config) );
echo db_excecute("SELECT * FROM `servers`", $config);
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

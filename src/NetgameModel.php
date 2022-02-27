<?php
namespace LiquidMS;

require_once __DIR__.'/../vendor/autoload.php';
#require_once __DIR__.'/../fetch_common.php';

class NetgameModel{

		private static $dsn = null;
		private static $username = null;
		private static $password = null;

		public static function init(Array $settings){
				// The following YAML structure will be used from `config.yaml`.
				//
				// db: # liquidMS DB connection settings
				//    dsn: # ODBC data source name
				//    user: # database user
				//    password: # database password

				if( array_key_exists("dsn", $settings) &&
								gettype($settings["dsn"]) == "string"){
						self::$dsn = $settings["dsn"];
				}
				if( array_key_exists("user", $settings) &&
								gettype($settings["user"]) == "string"){
						self::$username = $settings["user"];
				}
				if( array_key_exists("password", $settings) &&
								gettype($settings["password"]) == "string"){
						self::$password = $settings["password"];
				}
		}

		public static function getVersions(int $id = null){

				// Filter server block into distinct value arrays (step 2)
				// - "[_id]"
				//   "[gameid]"
				//   "[version]"

				$rVal = [];
				$query = "SELECT * FROM versions";
				if($id != NULL){ $query .= " WHERE _id = {$id}"; }
				#echo "($id) $query\n";
				$serverdata = self::db_execute($query);

				return $serverdata;
		}

		public static function pushServers(Array $servers) {

			// Generate insert values
			$values = "";
			foreach( $servers as $netgameId => $netgame){
			   $values .= "(\"{$netgame["host"]}\", \"{$netgame["port"]}\", \"{$netgame["servername"]}\", \"{$netgame["version"]}\", \"{$netgame["roomname"]}\", \"{$netgame["origin"]}\"),";
			}
			$values = rtrim($values,", \n\r\t");
			$query = "REPLACE INTO `servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`) VALUES {$values}";

			$serverdata = self::db_execute($query);
			return $serverdata;
		}

		public static function changeServer($op = 1, $ip = null, $port = '5029', $title = 'SRB2 server', $version = '2.2.9', $roomname = null) { //Operation, Host, port, servername, version, roomname.
				//Creates an SQL query based of all the info we provided.
				//Really dirty, could possibly get cleaned.
				if($ip != NULL) {
						if($op = 1) { //Create
								$query = "REPLACE INTO `servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`)
								VALUES ('{$ip}', '{$port}', '{$title}', '{$version}', '{$roomname}', 'localhost')";
						}
						elseif($op = 2) { //Update
								$query = "UPDATE `servers` SET `servername` = '{$title}'
								WHERE `servers`.`host` = '{$ip}' AND `servers`.`port` = '{$port}'";
						}
						else { //Remove
								$query = "DELETE FROM `servers`
								WHERE `servers`.`host` = '{$ip}'
								AND `servers`.`port` = '{$port}'";
						}
				}
				$serverdata = self::db_execute($query);
				return $serverdata;
		}

		public static function getServers($room = null){

				// Filter server block into distinct value arrays (step 2)
				// - - "[server line]"
				//   - "[IP]"
				//   - "[port]"
				//   - "[name]"
				//   - "[version]
				$querycondition = "";
				if(intval($room) == 1){ 
					$querycondition = "WHERE servers.origin = 'localhost'";
				}else if($room != NULL){ 
					$querycondition = "WHERE rooms._id = {$room}";
				}
				$query = "SELECT host, port, servername, rooms._id AS roomid, rooms.roomname, version, servers.origin FROM servers INNER JOIN rooms ON servers.roomname = rooms.roomname AND rooms.origin = servers.origin {$querycondition}";
				#echo $query."\n";
				$serverdata = self::db_execute($query);

				return $serverdata;
		}
		public static function getRooms(int $room = null){

				// Filter server block into distinct value arrays (step 2)
				// - - "[server line]"
				//   - "[IP]"
				//   - "[port]"
				//   - "[name]"
				//   - "[version]"

				$rVal = [];
				$query = "SELECT _id AS roomid, roomname, origin, description FROM rooms ORDER BY _id";
				if($room != NULL){ $query .= " WHERE _id = {$room}"; }
				#echo $query."\n";
				$serverdata = self::db_execute($query);

				return $serverdata;
		}

		public static function getWorldRooms(){

				// Filter server block into distinct value arrays (step 2)
				// - - "[server line]"
				//   - "[IP]"
				//   - "[port]"
				//   - "[name]"
				//   - "[version]"

				$rVal = [];
				$query = "SELECT _id AS roomid, roomname, origin, description FROM rooms WHERE origin = 'localhost'";
				#echo $query."\n";
				$serverdata = self::db_execute($query);

				return $serverdata;
		}

		private static function db_execute(string $query){

				// Sanity check
				if( self::$dsn == null ){ echo "No DSN string given in config.\n"; return false; }
				if( self::$username == null ){ echo "No user name string given in config.\n"; return false; }
				if( self::$password == null ){ echo "No password string given in config.\n"; return false; }

				$connection = odbc_connect( self::$dsn, self::$username, self::$password );

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
										#$n_results = 0;
										#while(odbc_next_result($result)){ $n_results++; }

										#echo "RESULTS: ".$n_results."\n";

										#while( odbc_fetch_row($result) ){}
										#for( $i = 0; $i < $rTable["rows"]; $i++ ){
												while($row = @odbc_fetch_array($result)) { //The @ makes me very sad. Gotta fix this sometime.
														$rTable["data"][] = $row;
												}
										#}
										#var_dump($rTable);
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
}

?>

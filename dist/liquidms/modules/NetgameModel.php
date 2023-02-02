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

namespace LiquidMS;

require_once __DIR__.'/../../vendor/autoload.php';
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
			if( array_key_exists("db", $settings) &&
					gettype($settings["db"]) == "array"){

				if( array_key_exists("dsn", $settings["db"]) &&
						gettype($settings["db"]["dsn"]) == "string"){
					self::$dsn = $settings["db"]["dsn"];
				}
				if( array_key_exists("user", $settings["db"]) &&
						gettype($settings["db"]["user"]) == "string"){
					self::$username = $settings["db"]["user"];
				}
				if( array_key_exists("password", $settings["db"]) &&
						gettype($settings["db"]["password"]) == "string"){
					self::$password = $settings["db"]["password"];
				}
			}else{
				error_log("No DB structure string given in config.\n");
				return false;
			}
			return true;
		}

		private static function map4to6(string $address){
			if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false){
				// Address is IPV4
				return "::ffff:".$address;
			}else if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false){
				// Address is IPV6
				return $address;
			}else{
				return false;
			}
		}

		private static function map6to4(string $address){
			if(str_starts_with($address, "::ffff:")){
				// Address is IPV4-Mapped
				//echo substr($address, 7);
				return substr($address, 7);
			}else if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false){
				// Address is IPV6
				return $address;
			}else{
				return false;
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
			   $values .= "(\"".self::map4to6($netgame["host"])."\", \"{$netgame["port"]}\", \"{$netgame["servername"]}\", \"{$netgame["version"]}\", \"{$netgame["roomname"]}\", \"{$netgame["origin"]}\"),";
			}
			$values = rtrim($values,", \n\r\t");
			$query = "INSERT INTO `servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`)"
			."VALUES {$values}"
			."ON DUPLICATE KEY UPDATE `host`=VALUES(host), `port`=VALUES(port), `servername`=VALUES(servername), `version`=VALUES(version), `roomname`=VALUES(roomname), `origin`=VALUES(origin)";

			$serverdata = self::db_execute($query);
			return $serverdata;
		}

		public static function changeServer($op = 1, $ip = null, $port = '5029', $title = 'SRB2 server', $version = '2.2.10', $roomname = null) { //Operation, Host, port, servername, version, roomname.
				//Creates an SQL query based of all the info we provided.
				//Really dirty, could possibly get cleaned.
				if($ip != NULL) {
						switch($op){
						case 1:{ //Create
							$query = "REPLACE INTO `servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`) ".
							"VALUES ('".self::map4to6($ip)."', '{$port}', '".str_replace("'","\'", $title)."', '{$version}', '{$roomname}', 'localhost')";
							break;
						}
						case 2:{ //Update
							$query = "UPDATE `servers` SET `servername` = '".str_replace("'","\'", $title)."' WHERE `servers`.`host` = '"
										.self::map4to6($ip)."' AND `servers`.`port` = '{$port}'";
							 break;
						}
						default:{ //Remove
							$query = "DELETE FROM `servers` WHERE `servers`.`host` = '".self::map4to6($ip)."' AND `servers`.`port` = '{$port}'";
							break;
						}
						}
				}
				#error_log("OP: $op;\n$query");
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
				$query = "SELECT host, port, servername, rooms._id AS roomid, rooms.roomname, version, servers.origin FROM servers INNER JOIN rooms ON servers.roomname = rooms.roomname AND rooms.origin = servers.origin {$querycondition};";
				#echo $query."\n";
				$serverdata = self::db_execute($query);
				#var_dump($serverdata);

				foreach($serverdata["data"] as $netgameId => $netgame){
					$serverdata["data"][$netgameId]["host"] = self::map6to4($netgame["host"]);
				};

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
				$filter = "";
				if($room != NULL){ $filter = " WHERE _id = {$room}"; }
				$query = "SELECT _id AS roomid, roomname, origin, description FROM rooms {$filter} ORDER BY _id;";
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

				#error_log("Connecting to ODBC: ".self::$username.":".self::$password."@".self::$dsn);
				$connection = odbc_connect( self::$dsn, self::$username, self::$password );

				if($connection){
						file_put_contents('php://stderr', "$query");
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

<?php
# liquidMS - distributable SRB2 master server
# Copyright (C) 2021-2025 Zibon Badi et al.
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

namespace LiquidMS\SRB2HTTP;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../DBSingleton.php';

use LiquidMS\DBSingleton;

class NetgameModel{

		private static $instance = null;
		private static $db = null;

		public function __construct(){
			self::$db = \LiquidMS\DBSingleton::getInstance();
		}

		public static function getInstance(){
			if( !self::$instance ){
				self::$instance = new NetgameModel();
			}
			return self::$instance;
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
				$serverdata = self::$db->execute($query);

				return $serverdata;
		}

		public static function pushServers(Array $servers) {

			// Generate insert values
			$values = "";
			foreach( $servers as $netgameId => $netgame){
			   $values .= "(\"".self::map4to6($netgame["host"])."\", \"{$netgame["port"]}\", \"{$netgame["servername"]}\", \"{$netgame["version"]}\", \"{$netgame["roomname"]}\", \"{$netgame["origin"]}\"),";
			}
			$values = rtrim($values,", \n\r\t");
			$query = "INSERT INTO `v1_servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`)"
			."VALUES {$values}"
			."ON DUPLICATE KEY UPDATE `host`=VALUES(host), `port`=VALUES(port), `servername`=VALUES(servername), `version`=VALUES(version), `roomname`=VALUES(roomname), `origin`=VALUES(origin)";

			$serverdata = self::$db->execute($query);
			return $serverdata;
		}

		public static function changeServer($op = 1, $ip = null, $netgameid = '127.0.0.1:5029', $title = 'SRB2 server', $version = '2.2.10', $roomname = null) { //Operation, Host, netgameID, servername, version, roomname.
				//Creates an SQL query based of all the info we provided.
				//Really dirty, could possibly get cleaned.

				// Step 1:
				// Check if the ID belongs to the IP issuing the request
				// and map the port to $port
				$port = -1;
				$host_arr = explode(':', $netgameid);
				$ip_candidate = $host_arr[0];
				$port_candidate = $host_arr[1];
				if($ip_candidate == $ip){
					$port = $port_candidate;
				}else{ return [ "rows" => 0 ]; }


				// Step 2: Do the thing
				if($ip != NULL) {
						switch($op){
						case "create":{ //Create
							$query = "REPLACE INTO `v1_servers` (`host`, `port`, `servername`, `version`, `roomname`, `origin`) ".
							"VALUES ('".self::map4to6($ip)."', '{$port}', '".str_replace("'","\'", $title)."', '{$version}', '{$roomname}', 'localhost')";
							break;
						}
						case "update":{ //Update
							$query = "UPDATE `v1_servers` SET `servername` = '".str_replace("'","\'", $title)."' WHERE `v1_servers`.`host` = '"
										.self::map4to6($ip)."' AND `v1_servers`.`port` = '{$port}'";
							 break;
						}
						case "delete":
						default:{ //Remove
							$query = "DELETE FROM `v1_servers` WHERE `v1_servers`.`host` = '".self::map4to6($ip)."' AND `v1_servers`.`port` = '{$port}'";
							break;
						}
						}
				}
				#error_log("OP: $op;\n$query");
				$serverdata = self::$db->execute($query);
				return $serverdata;
		}

		public function getServers($room = null){

				// Filter server block into distinct value arrays (step 2)
				// - - "[server line]"
				//   - "[IP]"
				//   - "[port]"
				//   - "[name]"
				//   - "[version]
				$querycondition = "";
				if(intval($room) == 1){ 
					$querycondition = "WHERE v1_servers.origin = 'localhost'";
				}else if($room != NULL){ 
					$querycondition = "WHERE v1_rooms._id = {$room}";
				}
				$query = "SELECT host, port, servername, v1_rooms._id AS roomid, v1_rooms.roomname, version, v1_servers.origin FROM v1_servers INNER JOIN v1_rooms ON v1_servers.roomname = v1_rooms.roomname AND v1_rooms.origin = v1_servers.origin {$querycondition};";
				#echo $query."\n";
				$serverdata = self::$db->execute($query);
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
				$query = "SELECT _id AS roomid, roomname, origin, description FROM v1_rooms {$filter} ORDER BY _id;";
				#echo $query."\n";
				$serverdata = self::$db->execute($query);

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
				$query = "SELECT _id AS roomid, roomname, origin, description FROM v1_rooms WHERE origin = 'localhost'";
				#echo $query."\n";
				$serverdata = self::$db->execute($query);

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

<?php
# LiquidMS - federated master server
# Copyright (C) 2021-2026 Zibon Badi et al.
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
use LiquidMS\ConfigModel;


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

		public function getServers($service = null){
				$servtable = $service["srb2http_servers"];

				// Filter server block into distinct value arrays (step 2)
				// - - "[server line]"
				//   - "[IP]"
				//   - "[port]"
				//   - "[name]"
				//   - "[version]
				$query = "SELECT INET6_NTOA(host) AS host, port, servername, roomname, origin FROM {$servtable};";
				#echo $query."\n";
				$serverdata = self::$db->execute($query);
				#var_dump($serverdata);

				#foreach($serverdata["data"] as $netgameId => $netgame){
					#$serverdata["data"][$netgameId]["host"] = self::map6to4($netgame["host"]);
				#};

				return var_export($serverdata);
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

<?php
# LiquidMS - distributable SRB2 master server
# Copyright (C) 2021-2024 Zibon Badi et al.
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

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/ConfigModel.php';

class DBSingleton{

		private static $instance = null;
		private static $connection = null;
		private static $dsn = null;
		private static $username = null;
		private static $password = null;

		private function __construct(){
			// The following YAML structure will be used from `config.yaml`.
			//
			// db: # liquidMS DB connection settings
			//    dsn: # ODBC data source name
			//    user: # database user
			//    password: # database password
			$settings = \LiquidMS\ConfigModel::getConfig();

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
			}
			self::$connection = new \PDO( self::$dsn, self::$username, self::$password );

		}

		public function __destruct(){
			self::$connection = null;
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

		public static function getInstance(){
			if( !self::$instance ){
				self::$instance = new DBSingleton();
			}
			return self::$instance;
		}

		public static function getConnection(){
			return self::getInstance()::$connection;
		}

		public static function execute(string $query, array $params = []){

				// Sanity check
				if( self::getInstance()::$dsn == null ){ echo "No DSN string given in config.\n"; return false; }
				if( self::getInstance()::$username == null ){ echo "No user name string given in config.\n"; return false; }
				if( self::getInstance()::$password == null ){ echo "No password string given in config.\n"; return false; }

				$hdl = self::getInstance()::getConnection();
				$hdl->beginTransaction();

				try{
					$statement = $hdl->prepare($query);
					$result = $statement->execute($params);
					$hdl->commit();
					if($result == false){ 
							return [
								"error" => $hdl::getCode(), 
								"message" => $hdl::getMessage(),
								"query" => $query,
							];
					}else{
							return [
									"error" => 0,
									"message" => "Successfully executed.",
									"data" => $statement->fetchAll(),
									"rows" => $statement->rowCount(),
							];
					}
				}catch(PDOException $e){
					file_put_contents('php://stderr', "$e");
					$hdl->rollBack();
					return [
							"error" => $e::getCode(), 
							"message" => $e::getMessage(),
							"query" => $query,
					];
				}
		}


}

?>


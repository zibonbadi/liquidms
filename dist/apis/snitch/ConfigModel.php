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

namespace LiquidMS;

require_once __DIR__.'/vendor/autoload.php';

class ConfigModel{

	private static $instance = null;
	private static $config = [
		"basepath" => "",
		"db" => [
			"dsn" => NULL,
			"user" => NULL,
			"password" => NULL,
		],
		"apis" => [
		],
		"netgame_query_limit" => [
			"n" => 20,
			"seconds" => 1,
		],
	];

		private function __construct(){

			$import = yaml_parse_file(__DIR__."/config.yaml", -1);

			// Merge all yaml configs together
			$import_compose = [];
			foreach($import as $docname => $doc) {
				$import_compose = array_merge_recursive($import_compose, $doc);
			}

			self::setConfig($import_compose);
		}

		static function getConfig(){
			if(!self::$instance){
				self:: $instance = new ConfigModel();
			}
			return self::$config;
		}

		static function setConfig(Array $newconfig){
			// Cleanup config block
			if( self::child_assertType("basepath", $newconfig, "string") ){
				self::$config["basepath"] = $newconfig["basepath"];
			}

			if( self::child_assertType("db", $newconfig, "array") ){
				if( self::child_assertType("dsn", $newconfig["db"], "string") ){
					self::$config["db"]["dsn"] = $newconfig["db"]["dsn"];
				}
				if( self::child_assertType("user", $newconfig["db"], "string") ){
					self::$config["db"]["user"] = $newconfig["db"]["user"];
				}
				if( self::child_assertType("password", $newconfig["db"], "string") ){
					self::$config["db"]["password"] = $newconfig["db"]["password"];
				}
			}

			if( self::child_assertType("apis", $newconfig, "array") ){
				$newarray = [];
				foreach($newconfig["apis"] as $endpoint => $api) {
					switch($api["api"]){
						case "srb2legacy":
						case "srb2kart":
						case "srb2http":{
							$newarray[$endpoint] = $api;
							break;
						}
					}
				}
				self::$config["apis"] = $newarray;
			}

			if( self::child_assertType("netgame_query_limit", $newconfig, "array") ){
				if( self::child_assertType("n", $newconfig["netgame_query_limit"], "integer") ){
					self::$config["netgame_query_limit"]["n"] = $newconfig["netgame_query_limit"]["n"];
				}
				if( self::child_assertType("seconds", $newconfig["netgame_query_limit"], "integer") ){
					self::$config["netgame_query_limit"]["seconds"] = $newconfig["netgame_query_limit"]["seconds"];
				}
			}
			//error_log("Server config: ".yaml_emit(self::$config));
		}

		static function dumpConfig(){
			yaml_emit_file(__DIR__."/config.yaml", self::$config);
		}

		private static function child_assertType(string $field, Array $parent, string $type){
			return (    array_key_exists($field, $parent) &&
					gettype($parent[$field]) == $type
				   );
		}
}

?>

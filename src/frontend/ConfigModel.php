<?php
# liquidMS - distributable SRB2 master server
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

namespace LiquidMS;

require_once __DIR__.'/vendor/autoload.php';

class ConfigModel{

	private static $instance = null;
	private static $config = [
		"basepath" => "",
		"db" => [
		],
		"services" => [
		],
		"settings" => [
			"do_srb2query" => false,
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

			if( self::child_assertType("settings", $newconfig, "array") ){
				if( self::child_assertType("do_srb2query", $newconfig["settings"], "boolean") ){
					self::$config["settings"]["do_srb2query"] = $newconfig["settings"]["do_srb2query"];
				}
			}

			if( self::child_assertType("services", $newconfig, "array") ){
				foreach($newconfig["services"] as $field_index => $field_val){
					if( self::child_assertType($field_index, $newconfig["services"], "array") ){
						
						self::$config["services"][$field_index]["_api"] = $field_val["_api"];
						if( self::child_assertType("_motd", $newconfig["services"][$field_index], "string") ){
							self::$config["services"][$field_index]["_motd"] = $newconfig["services"][$field_index]["_motd"];
						}
							switch($field_val["_api"]){
							case "SRB2HTTP":{
								if( self::child_assertType("srb2http_servers", $field_val, "string") ){
									self::$config["services"][$field_index]["srb2http_servers"] = $field_val["srb2http_servers"];
								}
								break;
							}
							case "SRB2Kart":{
								if( self::child_assertType("srb2kart_servers", $field_val, "string") ){
									self::$config["services"][$field_index]["srb2kart_servers"] = $field_val["srb2kart_servers"];
								}
								break;
							}
							default:{
								break;
							}
						}
					}
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

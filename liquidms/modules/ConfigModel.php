<?php
namespace LiquidMS;

require_once __DIR__.'/../../vendor/autoload.php';

class ConfigModel{

	private static $config = [
		"db" => [
			"dsn" => "liquidms",
		"user" => "sonic",
		"password" => "gottagofast",
		],
		"motd" => "liquidMS is an AGPL-licensed, API-compatible reimplementation of the SRB2 master server. By fetching other master servers, it can be used as a decentralized node network.",
		"fetchmode" => "fetch", // Either "fetch" or "snitch"
		"fetch" => [],
		"snitch" => [],
	];

		public static function init(){

			if(!file_exists(__DIR__."/../../config.yaml")){
				copy(__DIR__."/../../config.yaml.example", __DIR__."/../../config.yaml");
			}

			$import = yaml_parse_file(__DIR__."/../../config.yaml", -1);

			// Merge all yaml configs together
			$import_compose = [];
			foreach($import as $docname => $doc) {
				$import_compose = array_merge_recursive($import_compose, $doc);
			}

			self::setConfig($import_compose);
		}

		static function getConfig(){return self::$config;}

		static function setConfig(Array $newconfig){
			// Cleanup config block
			if( self::child_assertType("motd", $newconfig, "string") ){
				self::$config["motd"] = $newconfig["motd"];
			}
			if( self::child_assertType("fetchmode", $newconfig, "string") &&
					(   $newconfig["fetchmode"] == "fetch" ||
						$newconfig["fetchmode"] == "snitch")
			  ){
				self::$config["fetchmode"] = $newconfig["fetchmode"];
			}

			if( self::child_assertType("fetch", $newconfig, "array") ){
				foreach($newconfig["fetch"] as $peer_name => $peer_data){
					if( self::child_assertType("host", $peer_data, "string") ){
						self::$config["fetch"][$peer_name]["host"] = $peer_data["host"];
					}
					if(
						self::child_assertType("minute", $peer_data, "integer") ){
						self::$config["fetch"][$peer_name]["minute"] = $peer_data["minute"];
					}
				}
			}

			if( self::child_assertType("snitch", $newconfig, "array") ){
				self::$config["snitch"] = [];
				foreach($newconfig["snitch"] as $peer_name => $peer_data){
					if( self::child_assertType($peer_name, $newconfig["snitch"], "string") ){
						self::$config["snitch"][] = $peer_data;
					}
				}
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
		}

		static function dumpConfig(){
			yaml_emit_file(__DIR__."/../../config.yaml", self::$config);
		}

		private static function child_assertType(string $field, Array $parent, string $type){
			return (    array_key_exists($field, $parent) &&
					gettype($parent[$field]) == $type
				   );
		}
}

?>

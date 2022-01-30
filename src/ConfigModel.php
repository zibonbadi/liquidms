<?php
namespace LiquidMS;

require_once __DIR__.'/../vendor/autoload.php';

class ConfigModel{

		private static $config = [
				"db" => [
						"dsn" => "liquidms",
				"user" => "sonic",
				"password" => "gottagofast",
				],
				"motd" => "liquidMS is an AGPL-licensed, API-compatible reimplementation of the SRB2 master server. By fetching other master servers, it can be used as a decentralized node network.",
				"fetch" => [],
		];

				public static function init(){
						$import = yaml_parse_file(__DIR__."/../config.yaml", -1);

						// Merge all yaml configs together
						$import_compose = [];
						foreach($import as $docname => $doc) {
								$import_compose = array_merge_recursive($import_compose, $doc);
						}

						// Cleanup config block
						if( self::child_assertType("motd", $import_compose, "string") ){
								self::$config["motd"] = $import_compose["motd"];
						}
						if( self::child_assertType("fetch", $import_compose, "array") ){
								foreach($import_compose["fetch"] as $peer_name => $peer_data)
										if( self::child_assertType("host", $peer_data, "string") ){
												self::$config["fetch"][$peer_name]["host"] = $peer_data["host"];
										}
						}
						if( self::child_assertType("db", $import_compose, "array") ){
								if( self::child_assertType("dsn", $import_compose["db"], "string") ){
										self::$config["db"]["dsn"] = $import_compose["db"]["dsn"];
								}
								if( self::child_assertType("dsn", $import_compose["db"], "string") ){
										self::$config["db"]["user"] = $import_compose["db"]["user"];
								}
								if( self::child_assertType("password", $import_compose["db"], "string") ){
										self::$config["db"]["password"] = $import_compose["db"]["password"];
								}
						}
				}
				static function getConfig(){return self::$config;}

				private static function child_assertType(string $field, Array $parent, string $type){
						return (    array_key_exists($field, $parent) &&
										gettype($parent[$field]) == $type
									 );
				}
}

?>

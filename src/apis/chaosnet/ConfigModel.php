<?php
# LiquidMS - distributable SRB2 master server
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
		"loglevel" => "quiet",
		"node_host" => "localhost",
		"node_actor_uri" => null,
		"apis" => [],
		"db" => [
			"dsn" => NULL,
			"user" => NULL,
			"password" => NULL,
		],
	];

	private function __construct(){
		$import = yaml_parse_file(__DIR__."/config.yaml", -1);
		$import_compose = [];
		foreach($import as $docname => $doc) {
			$import_compose = array_merge_recursive($import_compose, $doc);
		}
		self::setConfig($import_compose);
	}

	static function getConfig(){
		if(!self::$instance){
			self::$instance = new ConfigModel();
		}
		return self::$config;
	}

	static function setConfig(Array $newconfig){
		if(self::child_assertType("basepath", $newconfig, "string")){
			self::$config["basepath"] = $newconfig["basepath"];
		}
		if(self::child_assertType("loglevel", $newconfig, "string")){
			self::$config["loglevel"] = $newconfig["loglevel"];
		}
		if(self::child_assertType("node_host", $newconfig, "string")){
			self::$config["node_host"] = $newconfig["node_host"];
		}
		if(self::child_assertType("node_actor_uri", $newconfig, "string")){
			self::$config["node_actor_uri"] = $newconfig["node_actor_uri"];
		}
		if(array_key_exists("apis", $newconfig) && gettype($newconfig["apis"]) == "array"){
			self::$config["apis"] = $newconfig["apis"];
		}
		if(self::child_assertType("db", $newconfig, "array")){
			if(self::child_assertType("dsn", $newconfig["db"], "string")){
				self::$config["db"]["dsn"] = $newconfig["db"]["dsn"];
			}
			if(self::child_assertType("user", $newconfig["db"], "string")){
				self::$config["db"]["user"] = $newconfig["db"]["user"];
			}
			if(self::child_assertType("password", $newconfig["db"], "string")){
				self::$config["db"]["password"] = $newconfig["db"]["password"];
			}
		}
	}

	static function dumpConfig(){
		yaml_emit_file(__DIR__."/config.yaml", self::$config);
	}

	static function getApiActorUri(string $apiName): string{
		$config = self::$config;
		$base = $config["node_actor_uri"] ?? "https://{$config["node_host"]}{$config["basepath"]}";
		return "{$base}/services/{$apiName}";
	}

	private static function child_assertType(string $field, Array $parent, string $type){
		return (array_key_exists($field, $parent) &&
				gettype($parent[$field]) == $type);
	}
}

?>

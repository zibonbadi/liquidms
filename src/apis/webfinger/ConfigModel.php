<?php
# LiquidMS - distributable SRB2 master server
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

class ConfigModel{

	private static $instance = null;
	private static $config = [
		"loglevel" => "quiet",
		"debug_output" => false,
		"hosts" => [],
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
		if(self::child_assertType("loglevel", $newconfig, "string")){
			self::$config["loglevel"] = $newconfig["loglevel"];
		}
		if(self::child_assertType("debug_output", $newconfig, "boolean")){
			self::$config["debug_output"] = $newconfig["debug_output"];
		}
		if(array_key_exists("hosts", $newconfig) && gettype($newconfig["hosts"]) == "array"){
			$hosts = [];
			foreach($newconfig["hosts"] as $host => $entries){
				if(gettype($entries) != "array"){ continue; }
				$hosts[$host] = [];
				foreach($entries as $entry){
					if(gettype($entry) != "array"){ continue; }
					if(self::child_assertType("local", $entry, "string") &&
					   self::child_assertType("actor_uri", $entry, "string")){
						$hosts[$host][] = [
							"local" => $entry["local"],
							"actor_uri" => $entry["actor_uri"],
						];
					}
				}
			}
			self::$config["hosts"] = $hosts;
		}
	}

	private static function child_assertType(string $field, Array $parent, string $type){
		return (array_key_exists($field, $parent) &&
				gettype($parent[$field]) == $type);
	}
}

?>

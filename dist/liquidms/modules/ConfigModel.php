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

require_once __DIR__ . '/../../vendor/autoload.php';

class ConfigModel
{

    private static $config = [
        "modules" => [],
        "sbpath" => __DIR__ . "/../../browser",
        "db" => [
            "dsn" => "liquidms",
            "user" => "sonic",
            "password" => "gottagofast",
        ],
        "netgame_query_limit" => [
            "n" => 20,
            "seconds" => 1,
        ],
        "motd" => "liquidMS is an AGPL-licensed, API-compatible reimplementation of the SRB2 master server. By fetching other master servers, it can be used as a decentralized node network.",
        "fetchmode" => "fetch", // Either "fetch" or "snitch"
        "fetch" => [],
        "snitch" => [],
    ];

    public static function init()
    {

        if (!file_exists(__DIR__ . "/../../config.yaml")) {
            copy(__DIR__ . "/../../config.yaml.example", __DIR__ . "/../../config.yaml");
        }

        $import = yaml_parse_file(__DIR__ . "/../../config.yaml", -1);

        // Merge all yaml configs together
        $import_compose = [];
        foreach ($import as $docname => $doc) {
            $import_compose = array_merge_recursive($import_compose, $doc);
        }

        self::setConfig($import_compose);
    }

    static function getConfig()
    {
        return self::$config;
    }

    static function setConfig(array $newconfig)
    {
        // Cleanup config block
        if (self::child_assertType("modules", $newconfig, "array")) {
            foreach ($newconfig["modules"] as $field_index => $field_val) {
                if (self::child_assertType($field_index, $newconfig["modules"], "string")) {
                    self::$config["modules"][$field_index] = $field_val;
                }
            }
        }

        if (self::child_assertType("sbpath", $newconfig, "string")) {
            self::$config["sbpath"] = $newconfig["sbpath"];
        }
        if (self::child_assertType("motd", $newconfig, "string")) {
            self::$config["motd"] = $newconfig["motd"];
        }
        if (self::child_assertType("fetchmode", $newconfig, "string") &&
            ($newconfig["fetchmode"] == "fetch" ||
                $newconfig["fetchmode"] == "snitch")
        ) {
            self::$config["fetchmode"] = $newconfig["fetchmode"];
        }

        if (self::child_assertType("fetch", $newconfig, "array")) {
            foreach ($newconfig["fetch"] as $peer_name => $peer_data) {
                if (self::child_assertType("host", $peer_data, "string")) {
                    self::$config["fetch"][$peer_name]["host"] = $peer_data["host"];
                }
                if (self::child_assertType("api", $peer_data, "string")) {
                    self::$config["fetch"][$peer_name]["api"] = $peer_data["api"];
                }
                if (
                    self::child_assertType("minute", $peer_data, "integer")) {
                    self::$config["fetch"][$peer_name]["minute"] = $peer_data["minute"];
                }
                if (
                    self::child_assertType("http-header", $peer_data, "array")) {
                    self::$config["fetch"][$peer_name]["http-header"] = $peer_data["http-header"];
                }
            }
        }

        if (self::child_assertType("snitch", $newconfig, "array")) {
            self::$config["snitch"] = [];
            foreach ($newconfig["snitch"] as $peer_name => $peer_data) {
                if (self::child_assertType($peer_name, $newconfig["snitch"], "string")) {
                    self::$config["snitch"][] = $peer_data;
                }
            }
        }

        if (self::child_assertType("db", $newconfig, "array")) {
            if (self::child_assertType("dsn", $newconfig["db"], "string")) {
                self::$config["db"]["dsn"] = $newconfig["db"]["dsn"];
            }
            if (self::child_assertType("user", $newconfig["db"], "string")) {
                self::$config["db"]["user"] = $newconfig["db"]["user"];
            }
            if (self::child_assertType("password", $newconfig["db"], "string")) {
                self::$config["db"]["password"] = $newconfig["db"]["password"];
            }
        }

        if (self::child_assertType("netgame_query_limit", $newconfig, "array")) {
            if (self::child_assertType("n", $newconfig["netgame_query_limit"], "integer")) {
                self::$config["netgame_query_limit"]["n"] = $newconfig["netgame_query_limit"]["n"];
            }
            if (self::child_assertType("seconds", $newconfig["netgame_query_limit"], "integer")) {
                self::$config["netgame_query_limit"]["seconds"] = $newconfig["netgame_query_limit"]["seconds"];
            }
        }
        //error_log("Server config: ".yaml_emit(self::$config));
    }

    static function dumpConfig()
    {
        yaml_emit_file(__DIR__ . "/../../config.yaml", self::$config);
    }

    private static function child_assertType(string $field, array $parent, string $type)
    {
        return (array_key_exists($field, $parent) &&
            gettype($parent[$field]) == $type
        );
    }
}

?>

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

require_once __DIR__ . '/modules/ConfigModel.php';
require_once __DIR__ . "/modules/NetgameModel.php";
require_once __DIR__ . '/modules/TimestampModel.php';
include_once(__DIR__ . '/modules/fetch_common.php');

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;
use LiquidMS\TimestampModel;

// Get job list
ConfigModel::init();
TimestampModel::init();
$config = ConfigModel::getConfig(); // Local var kludge

// Start "daemon"
#var_dump($config);
echo "[" . date(DateTimeInterface::ATOM, time()) . "] liquidanacron UP\n";
while (true) {
    // Get new timestamps
    $timestamps = TimestampModel::getData(); // Local var kludge
#var_dump($timestamps);
    $todo = [];
    foreach ($config["fetch"] as $job_i => $job_v) {

        if (array_key_exists("minute", $job_v) &&
            (gettype($job_v["minute"]) == "integer") &&
            ($job_v["minute"] > 0)) {
#echo "[".date(DateTime::ISO8601, time())." {$job_i}] Job \"{$job_i}\" has a valid minute field of {$job_v["minute"]} minutes.\n";
#var_dump($timestamps);
            if (array_key_exists("fetch", $timestamps)) {
                if (array_key_exists($job_i, $timestamps["fetch"])) {
                    if (!array_key_exists("updated_at", $timestamps["fetch"][$job_i])) {
                        $timestamps["fetch"][$job_i]["updated_at"] = date(DateTimeInterface::ATOM, time());
                    }
                } else {
                    echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Job \"{$job_i}\" has been assigned a new timestamp (job field missing).\n";
                    $timestamps["fetch"][$job_i]["updated_at"] = date(DateTimeInterface::ATOM, time());
                }
            } else {
                echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Job \"{$job_i}\" has been assigned a new timestamp (fetch section missing).\n";
                $timestamps["fetch"][$job_i]["updated_at"] = date(DateTimeInterface::ATOM, time());
            }
            if (strtotime($timestamps["fetch"][$job_i]["updated_at"]) > (time() - ($job_v["minute"] * 60))) {
                // Too early, skip
                echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Job has been skipped because it's too recent. ({$timestamps["fetch"][$job_i]["updated_at"]})\n";
                continue;
            }
        } else if (array_key_exists("minute", $job_v)) {
            echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Job has been skipped. (minute field missing)\n";
            continue;
        }

        if (array_key_exists("fetch", $timestamps) &&
            array_key_exists($job_i, $timestamps["fetch"]) &&
            array_key_exists("updated_at", $timestamps["fetch"][$job_i])
        ) {
            if ((gettype($job_v["minute"]) != "integer") ||
                ($job_v["minute"] < 1)) {
                // Invalid config
                echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Job \"{$job_i}\" has a bad minute field and will be executed every minute.\n";
            } else if (strtotime($timestamps["fetch"][$job_i]["updated_at"]) > (time() - ($job_v["minute"] * 60))) {
                // Too early, skip
                continue;
            }
        } else {
            // Invalid config
            echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Couldn't find time stamp. Forcing job execution\n";
        }
        // Update timestamp
        $timestamps["fetch"][$job_i]["updated_at"] = date(DateTimeInterface::ATOM, time());
        $todo[$job_i] = $job_v;
    }

    // Fetch in summary, just in case
    $fetchdata = fetchUpdate($config, $todo);

    switch ($config["fetchmode"]) {
        case "fetch":
        {
            // Generate insert values
            // Reserved upsert for when MySQL actually supports MERGE from SQL:2003
            /*
            $fetchsql = "";
            foreach( $fetchdata as $serv_index => $serv_val){
                $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
            }
            $fetchsql = rtrim($fetchsql,", \n\r\t");
            */

            if (!empty($fetchdata)) {
                // Upsert database (yes that's a real word)
                if (NetgameModel::init($config)) {
                    $dbresponse = NetgameModel::pushServers($fetchdata);
                }
                /*
                $dbresponse = db_execute(
                        "INSERT INTO servers (host, port, servername, version, roomname, origin)
                        VALUES {$fetchsql}
                        ON DUPLICATE KEY UPDATE
                        host=VALUES(host), port=VALUES(port),
                        servername=VALUES(servername), version=VALUES(version),
                        roomname=VALUES(roomname), origin=VALUES(origin),
                        updated_at=CURRENT_TIMESTAMP",
                        $config);
                */

                // Only report failures
                if ($dbresponse["error"] != 0) {
                    echo yaml_emit($dbresponse);
                } else {
                    echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] {$dbresponse["rows"]} rows upserted.\n";
                }

                /*
                // Reserved upsert for when MySQL actually supports MERGE from SQL:2003
                echo yaml_emit( db_execute(
                "MERGE INTO servers AS lms
                USING VALUES {$fetchsql} AS new (host, port, servername, version, roomname, origin)
                ON lms.host = new.host AND lms.port = new.port
                WHEN MATCHED THEN
                UPDATE host=VALUES(host), port=VALUES(port), servername=VALUES(servername), version=VALUES(version), roomname=VALUES(roomname), origin=VALUES(origin)
                WHEN NOT MATCHED THEN
                INSERT INTO servers (host, port, servername, version, roomname, origin) VALUES (host, port, servername, version, roomname, origin)
                ",
                $config) );
                 */
            } else {
                echo "[" . date(DateTimeInterface::ATOM, time()) . " {$job_i}] Nothing to do here!.\n";
            }
            break;
        }
        case "snitch":
        {
            echo snitch($fetchdata, $config["snitch"]);
            break;
        }
    }
    TimestampModel::setData($timestamps);
    TimestampModel::dumpData();
    sleep(60);
}
?>

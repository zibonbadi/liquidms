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
require_once __DIR__ . '/modules/fetch_common.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;
use LiquidMS\TimestampModel;

ConfigModel::init();
TimestampModel::init();
$timestamps = TimestampModel::getData();
$config = ConfigModel::getConfig(); // Local var kludge

// Parse args into jobnames
$fetchjobs = [];
foreach ($argv as $argno => $argval) {
    if ($argval === $argv[0]) {
        continue;
    } // Skip filename invocation
    if (array_key_exists($argval, $config["fetch"])) {
        $fetchjobs[$argval] = $config["fetch"][$argval];
    }
}
if (empty($fetchjobs)) {
    $fetchjobs = $config["fetch"];
}

foreach ($fetchjobs as $jobId => $job) {
    $timestamps["fetch"][$jobId]["updated_at"] = date(DateTimeInterface::ATOM, time());
}
TimestampModel::setData($timestamps);
TimestampModel::dumpData();

$fetchdata = fetchUpdate($config, $fetchjobs);

// Generate insert values
// Reserved upsert for when MySQL actually supports MERGE from SQL:2003
/*
$fetchsql = "";
foreach( $fetchdata as $serv_index => $serv_val){
   $fetchsql .= "(\"{$serv_val["host"]}\", \"{$serv_val["port"]}\", \"{$serv_val["servername"]}\", \"{$serv_val["version"]}\", \"{$serv_val["roomname"]}\", \"{$serv_val["origin"]}\"),";
}
$fetchsql = rtrim($fetchsql,", \n\r\t");
*/

switch ($config["fetchmode"]) {
    case "fetch":
    {
        // Upsert database (yes that's a real word)
        if (NetgameModel::init($config)) {
            echo yaml_emit(NetgameModel::pushServers($fetchdata));
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
        break;
    }
    case "snitch":
    {
        if (NetgameModel::init($config)) {
            echo snitch($fetchdata, $config["snitch"]);
        }
        break;
    }
}

?>

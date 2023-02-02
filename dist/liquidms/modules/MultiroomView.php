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

$maincontent = "";
$import = $this->sharedData()->get('data');
$rooms = $this->sharedData()->get('rooms');
$servers_sorted = [];

#var_dump($rooms);
// Guarantee room slots
#foreach($rooms["data"] as $room_index => $room_value){ $servers_sorted[$room_value["roomid"]] = []; }

// Sort into room slots
foreach ($import["data"] as $import_index => $import_value) {
    $slot = [];
    $slot["host"] = $import_value["host"];
    $slot["port"] = $import_value["port"];
    $slot["servername"] = $import_value["servername"];
    $slot["version"] = $import_value["version"];

    $servers_sorted[$import_value["roomid"]][] = $slot;
}

foreach ($servers_sorted as $cat_index => $cat_data) {
    // Generate content string
    $maincontent .= $cat_index . "\n";
    foreach ($cat_data as $server_index => $server_value) {
        // Generate content string
        $maincontent .= $server_value["host"] . " " .
            $server_value["port"] . " " .
            $server_value["servername"] . " " .
            $server_value["version"] .
            "\n";
#$maincontent .= $server_value."\n";
    }
    $maincontent .= "\n";
}
// Cut off newline for V1 compliance
$maincontent = substr($maincontent, 0, -1);

echo $maincontent;
?>

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
$room = $this->sharedData()->get('room');

foreach ($import["data"] as $import_index => $import_value) {
#echo "Querying server '{$server_index}': {$server_value["host"]}\n";
    $maincontent .= $import_value["host"] . " " .
        $import_value["port"] . " " .
        $import_value["servername"] . " " .
        $import_value["version"] .
        "\n";
}
echo "{$room}\n{$maincontent}";

?>

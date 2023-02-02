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

$config = [];
if (PHP_SAPI == "cli") {
    $config = yaml_parse_file(__DIR__ . "/config.yaml", -1);

    // Merge all yaml configs together
    $tmp = [];
    foreach ($config as $docname => $doc) {
        $tmp = array_merge_recursive($tmp, $doc);
    }
    $config = $tmp;
    unset($tmp);
}
?>

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

class TimestampModel
{

    private static string $filename = __DIR__ . "/../../timestamps.yaml";
    private static array $data = [];

    public static function init(string $filename = __DIR__ . "/../../timestamps.yaml")
    {

        if (!file_exists($filename)) {
            yaml_emit_file($filename, []);
        }


        $import = yaml_parse_file($filename, -1);

        // Merge all yaml configs together
        $import_compose = [];
        foreach ($import as $docname => $doc) {
            if (gettype($import_compose) == "array") {
                $import_compose = array_merge_recursive($import_compose, $doc);
            }
        }

        self::setData($import_compose);
        self::$filename = $filename;
    }

    static function getData()
    {
        return self::$data;
    }

    static function setData($newdata)
    {
        self::$data = $newdata;
    }

    static function dumpData(string $filename = NULL)
    {
        $writeTo = self::$filename;
        if ($filename != NULL) {
            $writeTo = $filename;
        }
        yaml_emit_file($writeTo, self::$data);
    }
}



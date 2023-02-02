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

use LiquidMS\ConfigModel;

/* SRB2Query routes */
$router->with('/liquidms/SRB2Query', function () use ($router) {
    $router->respond('GET', '/?', function ($request, $response) {
        if ($request->hostname != NULL &&
            $request->port != NULL &&
            intval($request->port) > 1) {

            // Set up SRB2Query
            require_once __DIR__ . '/modules_vendor/srb2query.php';

            $config = ConfigModel::getConfig();

            $srb2conn = new SRB2Query;
            $ng_hdl = null;

            function utf8sanitize($input)
            {
                if (is_array($input)) {
                    foreach ($input as $i => $value) {
                        $input[$i] = utf8sanitize($value);
                    }
                } else if (is_string($input)) {
                    return utf8_encode($input);
                }
                return $input;
            }

            $srb2conn->Ask($request->hostname, intval($request->port));
            $netgame = $srb2conn->Info($ng_hdl);

            #error_log("SRB2QUERY: ".$request->hostname.' '.$request->port."\n".yaml_emit($netgame)."\n".yaml_emit($ng_hdl));

            // Add hostname to data, just in case
            #$netgame["hostname"] = $ng_hdl;

            // Guarantee form, fill with dummy data
            $out = [
                "hostname" => "127.0.0.1",
                "port" => "5029",
                "cheats" => false,
                "dedicated" => false,
                "gametype" => "Query Failure",
                "level" => [
                    "md5sum" => 00000,
                    "level" => "Query failure",
                ],
                "title" => "Query failure",
                "mods" => false,
                "players" => [
                    "max" => 0,
                    "list" => [],
                ],
                "version" => [
                    "major" => 0,
                    "minor" => 0,
                    "patch" => 0,
                    "name" => "No contest",
                ],
            ];
            if ($netgame) {
                $out = $netgame;
            } else {
                $response->code(404);
            }

            $response->json(utf8sanitize($out));
        } else {
            $response->code(400);
            $response->json([
                "?" => [
                    "hostname" => $request->hostname,
                    "port" => $request->port,
                ],
            ]);
        }
    });
});

?>

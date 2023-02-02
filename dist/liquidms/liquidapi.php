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
require_once __DIR__ . '/modules/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;

LiquidMS\NetgameModel::init(ConfigModel::getConfig());

// Namespace for extended 
$router->with('/liquidms', function () use ($router) {
    $router->respond('GET', '/?', function ($request, $response, $service) {
        $response->json([
            "routes" => [
                "/browse",
                "/license",
                "/snitch",
            ]
        ]);
    });

    $router->respond('PUT', '/?', function ($request, $response) {
        // Dumb JSON mirror for no reason
        // PUT is idempotent; Screw POST
        parse_str($request->body(), $reqdata);
        $response->json($reqdata);
    });

    $router->respond('GET', '/snitch/?', function ($request, $response) {
        // Get all known netgames as a CSV table (e.g. for snitching to other nodes)
        $response->header('Content-Type', 'text/csv;header=absent');
        $servers = NetgameModel::getServers();
        if ($servers["error"] == 0) {
            if ($servers["rows"] > 0) {
                $out = fopen('php://output', 'w');
                foreach ($servers["data"] as $server) {
                    if (($server["origin"] == "localhost") || ($server["origin"] == "127.0.0.1")) {
                        $server["origin"] = $_SERVER["SERVER_NAME"];
                    }
                    // Reordering to guarantee API-compliant output
                    fputcsv($out, [
                            "host" => $server["host"],
                            "port" => $server["port"],
                            "servername" => $server["servername"],
                            "version" => $server["version"],
                            "roomname" => $server["roomname"],
                            "origin" => $server["origin"],
                        ]
                    );
                }
                //$response->json($servers["data"]);
            } else {
                #$response->code(404);
                return "";
            }
        } else {
            $response->code(500);
            $response->json($servers);
            //return "\n";
        }
    });

    $router->respond('POST', '/snitch', function ($request, $response, $service) {
        // Provide some CSV text and it'll get parsed into tables
        //$csvdata[] = str_getcsv($request->body());
        $csvdata = [];
        $files = $request->files();

        error_log($request->ip() . " provided the following files:\n" . yaml_emit($files->all()));

        if (empty($files) ||
            $files == NULL) {
            $response->code(400);
            $response->json([
                "status" => $response->code(),
                "message" => "No valid data provided"
            ]);
            return;
        }

        foreach ($files as $fileId => $file) {
            //Formatting
            $csvlines = explode("\n", rtrim(file_get_contents($file['tmp_name']), "\n"));
            $csvdata_raw = array_map('str_getcsv', $csvlines);
            foreach ($csvdata_raw as $csvnetgameId => $csvnetgame) {
                $csvdata[] = [
                    "host" => $csvnetgame[0],
                    "port" => $csvnetgame[1],
                    "servername" => $csvnetgame[2],
                    "version" => $csvnetgame[3],
                    "roomname" => $csvnetgame[4],
                    "origin" => $csvnetgame[5],
                ];
            }
        }

        foreach ($csvdata as $netgameId => $netgame) {
            // Check entries. Keep halal ones, discard the rest
            if (
                ($netgame["host"] == "localhost") ||
                ($netgame["host"] == "127.0.0.1") ||
                ($netgame["host"] == $_SERVER["HTTP_HOST"]) ||
                ($netgame["origin"] == $_SERVER["HTTP_HOST"]) ||
                ($netgame["host"] == $_SERVER["SERVER_NAME"]) ||
                ($netgame["origin"] == $_SERVER["SERVER_NAME"]) ||
                ($netgame["host"] == "0.0.0.0") ||
                ($netgame["origin"] == "0.0.0.0") ||
                ($netgame["origin"] == "localhost") ||
                ($netgame["origin"] == "127.0.0.1")
            ) {
                error_log("Removing invalid netgame \"{$netgame["servername"]}\"");
                unset($csvdata[$netgameId]);
            }
        }

        if (empty($csvdata) || $csvdata == NULL) {
            $response->code(400);
            $response->json([
                "status" => $response->code(),
                "message" => "No valid data provided"
            ]);
            return;
        }

        error_log($request->ip() . " snitched the following netgames:\n" . yaml_emit($csvdata));

        // I'll think of something
        $dbresponse = NetgameModel::pushServers($csvdata);

        if ($dbresponse["error"] == 0) {
            if ($dbresponse["rows"] > 0) {
                // For now, just mirror what got parsed for testing
                $response->json([
                    "status" => $response->code(),
                    "message" => "Success",
                ]);
            } else {
                $response->code(403);
                $response->json([
                    "status" => $response->code(),
                    "message" => "Can't add those\n",
                ]);
            }
        } else {
            $response->code(500);
            $response->json([
                "status" => $response->code(),
                "message" => $service->render(__DIR__ . "/modules/ErrorView.php", ["response" => $dbresponse]),
            ]);
        }

    });
});

?>

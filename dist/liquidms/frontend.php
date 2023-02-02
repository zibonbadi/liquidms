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
use LiquidMS\NetgameModel;

$router->respond('GET', '/favicon.ico', function ($request, $response, $service) {
    $config = ConfigModel::getConfig();
    $response->file(rtrim($config["sbpath"], "/") . "/favicon.svg");
});

$router->with('/liquidms/browse', function () use ($router) {
    $router->respond('GET', '/?', function ($request, $response, $service) {
        $config = ConfigModel::getConfig();
        $netgames = NetgameModel::getServers();
        $service->render(rtrim($config["sbpath"], "/") . "/index.php", [
            "motd" => $config["motd"],
            "modules" => $config["modules"],
            "netgames" => $netgames
        ]);
    });
    // Three separate resource routes for capsuled security
    $router->respond('GET', '/img/[**:path]/?', function ($request, $response, $service) {
        $config = ConfigModel::getConfig();
        $response->file(rtrim($config["sbpath"], "/") . "/img/" . $request->path);
    });
    $router->respond('GET', '/static/[**:path]/?', function ($request, $response, $service) {
        $config = ConfigModel::getConfig();
        $response->header('Content-Type', 'text/html');
        $response->sendHeaders(true);
        #$response->file(rtrim($config["sbpath"], "/")."/static/".$request->path);
        $service->render(rtrim($config["sbpath"], "/") . "/static/" . $request->path);
    });
    $router->respond('GET', '/css/[**:path]/?', function ($request, $response, $service) {
        $config = ConfigModel::getConfig();
        $response->header('Content-Type', 'text/css');
        $response->sendHeaders(true);
        #$response->file(rtrim($config["sbpath"], "/")."/css/".$request->path);
        $service->render(rtrim($config["sbpath"], "/") . "/css/" . $request->path);
    });
    $router->respond('GET', '/js/[**:path]/?', function ($request, $response, $service) {
        $config = ConfigModel::getConfig();
        $response->header('Content-Type', 'application/javascript');
        $response->sendHeaders(true);
        #$response->file(rtrim($config["sbpath"], "/")."/js/".$request->path);
        $service->render(rtrim($config["sbpath"], "/") . "/js/" . $request->path);
    });
});

?>

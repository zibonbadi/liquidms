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

// Setup, configs etc.
require_once __DIR__ . '/../vendor/autoload.php';

// Local utilities
require_once __DIR__ . '/../liquidms/modules/ConfigModel.php';
require_once __DIR__ . '/../liquidms/modules/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;
use Klein\Klein;

// Main router object
$router = new Klein();
$configmodel = ConfigModel::init();
$config = ConfigModel::getConfig();

NetgameModel::init($config);

set_time_limit(5);

#error_log("Server settings: \n".yaml_emit($_SERVER));

// Set API routes
if (in_array('v1', $config["modules"])) {
    require_once(__DIR__ . '/../liquidms/v1.php');
}
if (in_array('snitch', $config["modules"])) {
    require_once(__DIR__ . '/../liquidms/liquidapi.php');
}
if (in_array('browser', $config["modules"])) {
    require_once(__DIR__ . '/../liquidms/frontend.php');
}
if (in_array('srb2query', $config["modules"])) {
    require_once(__DIR__ . '/../liquidms/srb2query.php');
}

# Always display LICENSE for AGPLv3 compliance
$router->with('/liquidms', function () use ($router) {
    $router->respond('GET', '/license/?', function ($request, $response, $service) {
        $response->header('Content-Type', 'text/plain;syntax=markdown');
        return file_get_contents(__DIR__ . "/../LICENSE.md");
    });
});


// Start accepting requests
$router->dispatch();
?>

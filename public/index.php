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
require_once __DIR__.'/../vendor/autoload.php';

// Local utilities
require_once __DIR__.'/../liquidms/modules/ConfigModel.php';
require_once __DIR__.'/../liquidms/modules/NetgameModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;
use Klein\Klein;

// Main router object
$router = new Klein();
$configmodel = ConfigModel::init();
$config = ConfigModel::getConfig();

#var_dump($config);
#var_dump($configmodel);

NetgameModel::init($config["db"]);

//var_dump($config);

var_dump($config["modules"]);
var_dump(in_array('v1', $config["modules"]));
var_dump(in_array('snitch', $config["modules"]));
var_dump(in_array('browser', $config["modules"]));

// Set API routes
include_once __DIR__.'/../liquidms/v1.php';
include_once __DIR__.'/../liquidms/liquidapi.php';
include_once __DIR__.'/../liquidms/frontend.php';
//if(in_array('v1', $config["modules"]){ include_once __DIR__.'/../liquidms/v1.php'; }
//if(in_array('snitch', $config["modules"]){ include_once __DIR__.'/../liquidms/liquidapi.php'; }
//if(in_array('browser', $config["modules"]){ include_once __DIR__.'/../liquidms/frontend.php'; }

// Start accepting requests
$router->dispatch();
?>

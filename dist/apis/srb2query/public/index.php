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

use LiquidMS\ConfigModel;
use Klein\Klein;

// Main router object
$router = new Klein();
$config = ConfigModel::getConfig();

set_time_limit(10);

require_once __DIR__.'/../liquidms/modules/V1/NetgameModel.php';
require_once(__DIR__.'/../api.php');

// Start accepting requests
$router->dispatch();
?>

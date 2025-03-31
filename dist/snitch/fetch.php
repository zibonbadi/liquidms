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

require_once __DIR__.'/modules/ConfigModel.php';
require_once __DIR__.'/modules/TimestampModel.php';
require_once __DIR__.'/modules/fetch_common.php';

use LiquidMS\ConfigModel;
use LiquidMS\TimestampModel;

TimestampModel::init();
$timestamps = TimestampModel::getData();
$config = ConfigModel::getConfig(); // Local var kludge

// Parse args into jobnames
$fetchjobs = [];
foreach( $argv as $argno => $argval ){
   if($argval === $argv[0]){ continue; } // Skip filename invocation
   if(array_key_exists($argval, $config["src"])){ $fetchjobs[$argval] = $config["fetch"][$argval]; }
}
if( empty($fetchjobs)){ $fetchjobs = $config["src"]; }




$fetchdata = fetchUpdate($config, $fetchjobs);

foreach( $fetchjobs as $jobId => $job ){
	$timestamps["fetch"][$jobId]["updated_at"] = date(DateTime::ISO8601, time());
}

TimestampModel::setData($timestamps);
TimestampModel::dumpData();

// Pass data to snitch server
echo snitch($fetchdata, $config["snitch"]);
#$fetchdata = snitchUpdate($config, $snitchjobs);

foreach( $fetchjobs as $jobId => $job ){
	$timestamps["snitch"][$jobId]["updated_at"] = date(DateTime::ISO8601, time());
}

?>

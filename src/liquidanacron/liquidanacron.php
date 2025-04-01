<?php
# liquidMS - distributable SRB2 master server
# Copyright (C) 2021-2024 Zibon Badi et al.
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
include_once(__DIR__.'/modules/fetch_common.php');

use LiquidMS\ConfigModel;
use LiquidMS\TimestampModel;

// Get job list
TimestampModel::init();
$config = ConfigModel::getConfig(); // Local var kludge

/*
* @params:
* -1 [JOB], --oneshot [JOB]: Run only once, w/o/ JOB, it will run all jobs.
*/
$posarg_idx = null;
$FLAGS = getopt( "1::", [], $posarg_idx);
$posargs = array_slice($argv, $posarg_idx);

// Start "daemon"
echo "[".date(DateTime::ISO8601, time())."] liquidanacron UP\n";
do{
	// Get new timestamps
	$timestamps = TimestampModel::getData(); // Local var kludge
	$todo_fetch = [];
	$todo_snitch = [];

	// Realtime Timestamps
	$time_before = time();
	$time_afterfetch = NULL;
	$time_aftersnitch = NULL;
	$time_afterall = NULL;

	/* Oneshot flag? -> Hijack the scheduler */ 
	if( $FLAGS !== false && array_key_exists("1", $FLAGS)){
		echo "[".date(DateTime::ISO8601, $time_before)."] ONESHOT mode. Only do one job.\n";
	}

	// Build FETCH list
	if($posargs != []){
		// Filter out nonexistent jobs
		foreach( $posargs as $jobname){
			if(array_key_exists($jobname, $config["src"])){
				$todo_fetch[$jobname] = $config["src"][$jobname];
			}
		}
	}else{
		// None defined - just use all of them
		$todo_fetch = $config["src"];
	}


	// Define FETCH schedule
	$todo_fetch = array_filter($todo_fetch, function($job_v, $job_i) use($timestamps,$time_before){
		// Job valid for anacron?
		if( array_key_exists("minute", $job_v) &&
				( gettype($job_v["minute"]) == "integer" ) &&
				($job_v["minute"] > 0) ){
			
			// === TIMESTAMP.YAML check ====
			// Does the job have a timestamp worth updating?
			if( array_key_exists("src", $timestamps) // Does timestamp YAML exists?
				&& array_key_exists($job_i, $timestamps["src"]) // Job is known
				&& array_key_exists("updated_at", $timestamps["src"][$job_i]) // Job has timestamp
				&& strtotime($timestamps["src"][$job_i]["updated_at"]) > (time() - ($job_v["minute"] * 60)) // Job is too recent
			){
				// Too early, skip
				echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] FETCH job skipped. (too recent: {$timestamps["src"][$job_i]["updated_at"]})\n";
				return false;
			}else{
				// No recent timestamp? WE'LL MAKE ONE!!
				// (after the HTTP requests are done)
				echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] FETCH job added to schedule:  \"{$job_i}\"\n";
				return true;
			}
			
		}else{
			echo "[".date(DateTime::ISO8601, time())." {$job_i}] FETCH job has been skipped. (minute int missing or invalid)\n";
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);

	// Define SNITCH schedule
	foreach( $config["dest"] as $job_i => $job_v){

		switch( $job_v["api"] ) {
			# Unsupported crap
			default: {
				echo "[".date(DateTime::ISO8601, time())." {$job_i}] SNITCH skipped. (invalid API \"{$job_v["api"]}\")\n";
				continue 2; # Skip this one
			}
			# Good APIs
			case "snitch":
			case "snitch_v2": {
				break;
			}
		}

		// Job valid for anacron?
		if( array_key_exists("minute", $job_v) &&
				( gettype($job_v["minute"]) == "integer" ) &&
				($job_v["minute"] > 0) ){
			
			// Does timestamp YAML exists?
			if( array_key_exists("dest", $timestamps) ){
				// === TIMESTAMP.YAML check ====
				// Does the job have a timestamp worth updating?
				if(    array_key_exists($job_i, $timestamps["dest"]) // Job is known
					&& array_key_exists("updated_at", $timestamps["dest"][$job_i]) // Job has timestamp
					&& strtotime($timestamps["dest"][$job_i]["updated_at"]) > (time() - ($job_v["minute"] * 60)) // Job is too recent
				){
					// Too early, skip
					echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job skipped because (too recent: {$timestamps["dest"][$job_i]["updated_at"]})\n";
					continue;
				}else{
					// No recent timestamp? WE'LL MAKE ONE!!
					// (after the HTTP requests are done)
					echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job received new timestamp: \"{$job_i}\"\n";
					$todo_snitch[$job_i] = $job_v;
				}
			}
		}else{
			echo "[".date(DateTime::ISO8601, time())." {$job_i}] SNITCH job has been skipped. (minute int missing or invalid)\n";
			continue;
		}
	}


	// ====== THE ACTUAL FETCH  ======
	// Fetch in summary, just in case
	$fetchdata = fetchUpdate($config, $todo_fetch);
	$time_afterfetch = time(); // The time is NOW!

	foreach( $todo_fetch as $job_i => $job_v){
		echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] FETCH job received new timestamp: \"{$job_i}\"\n";
		$timestamps["src"][$job_i]["updated_at"] = date(DateTime::ISO8601, $time_afterfetch);
	}

	// ====== THE SNITCH ======
	// Pass data to snitch server
	echo snitch($fetchdata, $config["dest"]);
	$time_aftersnitch = time(); // The time is NOW!

	foreach( $todo_snitch as $job_i => $job_v){
		echo "[".date(DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job \"{$job_i}\" has been assigned a new timestamp.\n";
		$timestamps["src"][$job_i]["updated_at"] = date(DateTime::ISO8601, $time_aftersnitch);
	}

	// Update to disk
	TimestampModel::setData($timestamps);
	TimestampModel::dumpData();
	
	// Oneshot exit
	if( $FLAGS !== false && array_key_exists("1", $FLAGS)){
		break;
	}

	// Realtime-aware "tickless" scheduling
	$time_afterall = time();
	if( $time_afterall < ($time_before + 60)){
		echo "[".date(DateTime::ISO8601, $time_afterall)."] Waiting for ".( 60-($time_afterall-$time_before) )." seconds...\n";
		sleep( 60-($time_afterall-$time_before) );
	}else{
		echo "[".date(DateTime::ISO8601, $time_afterall)."] RT deadline violated by ".($time_before+60-$time_afterall)." seconds. No wait needed.\n";
	}
}while(true);
?>

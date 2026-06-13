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

namespace LiquidAnacron;

require_once __DIR__.'/models/ConfigModel.php';
require_once __DIR__.'/models/TimestampModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\TimestampModel;

//===========================================================================//
// HELPER FUNCTIONS
//===========================================================================//

function normalizeName(string $name): string{
	$name = urldecode($name);
	$name = preg_replace('/[[:cntrl:]]/', '', $name);
	$name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
	$name = rawurlencode($name);
	return $name;
}

function fetch_mkContext(String $method, array|null $headers = []){
   // Emergency exit; return null in case the data is bad
   if(gettype($headers) == "NULL" || empty($headers)){
	   return null;
   }

   // Stream context for HTTP fetch
   $stream_context = null;
   $stream_context_opts = ["http" => [ "method" => $method] ];

   // sco = Stream Context Option
   $sco_header = "";
   foreach($headers as $sco_key => $sco_value ){
	   $sco_header .= "{$sco_key}: {$sco_value}\r\n";
   }

   // Construct context
   if($sco_header != ""){
	   $stream_context_opts["http"]["header"] = $sco_header;
   }

   $stream_context = stream_context_create($stream_context_opts);
   return $stream_context;
}

function fetch(array $config, array $jobs = []){
	// Internal data is gonna (somewhat) resemble the JSON data from ChaosNet's
	// Game objects, just without the ActivityPub stuff.
	//
	// ```JSON
	// [{
	// 	"name": "My Server" # non-printable chars stripped, URL-encoded
	// 	"game_host": "203.0.113.42" # non-printable chars stripped, URL-encoded
	// 	"game_port": 5029
	// 	"game_api_name": "whateverapi" # internal codename
	// 	"game_api_data": {} # API-dependent. Handled by adapter.
	// 	"external_origin": "hostname" | null # non-printable chars stripped, URL-encoded
	// 	"origin_node": "node URI" # non-printable chars stripped, URL-encoded
	//	"updated": "ISO 8601 timestamp" # When in doubt, just add time()
	// }, ...]
	// ```
	//
	// Reason for this is that it handles api-specific
	// data structures agnostically.
	//
	// Instead, we let each fetch/snitch adapter handle the data structure.
	// For more modularity, these will be loaded from "./api_adapters/<api>.php" as needed
	
	$rVal = []; // Return value

	foreach($jobs as $jobname => $jobval) {
		echo "[".date(\DateTime::ISO8601, time())." {$jobname}] Fetching \"{$jobval["host"]}\"...\n";
		$sv_new = [];
		$currentjob = $config["src"][$jobname];

		try{
			$api_file = "./api_adapters/{$jobval["api"]}.php";
			$api_func = "\\LiquidAnacron\\API\\{$jobval["api"]}\\fetch";
			require_once($api_file);
			$sv_new = $api_func($config, $currentjob);
		}catch (Exception $e){
			echo "[".date(\DateTime::ISO8601, time())."] {$e->getMessage()}\n";
			echo "[".date(\DateTime::ISO8601, time())."] Unable to fetch \"${jobname}\". Skipping...\n";
		}

		$rVal = array_merge($rVal, $sv_new);
	}

   return $rVal;
}

function snitch(Array $data, Array $dests){

	$rowCount = count($data);
	$srb2http_count= 0;
	$srb2legacy_count= 0;
	$srb2kart_count= 0;

	echo "[".date(\DateTime::ISO8601, time())."] Processing {$rowCount} rows of data...\n";

	if($rowCount < 1){
		echo "[".date(\DateTime::ISO8601, time())."] No data to propagate. Skipping...\n";
		return;
	}


	/**
	 * Count cached netgames by MS API
	 */
	$api_counter = [];
	foreach($data as $netgame_i => $netgame_v){
		if(!array_key_exists($netgame_v["game_api_name"], $api_counter)){
			$api_counter[$netgame_v["game_api_name"]] = 0;
		}
		$api_counter[$netgame_v["game_api_name"]]++;
	}

	// List API cache counters
	foreach($api_counter as $api => $count){
		echo "[".date(\DateTime::ISO8601, time())."] Cached ".$count." netgames from API \"".$api."\".\n";
	}

	foreach($dests as $dest_i => $dest_v){

		try{
			$api_file = "./api_adapters/{$dest_v["api"]}.php";
			$api_func = "\\LiquidAnacron\\API\\{$dest_v["api"]}\\snitch";
			require_once($api_file);
			echo $api_func($data, $dest_v["host"]);
		}catch (Exception $e){
			echo "[".date(\DateTime::ISO8601, time())."] {$e->getMessage()}\n";
			echo "[".date(\DateTime::ISO8601, time())."] Unable to snitch to \"${dest_i}\". Skipping...\n";
		}

	}

}

//===========================================================================//
// MAIN SCRIPT
//===========================================================================//

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
echo "[".date(\DateTime::ISO8601, time())."] liquidanacron UP\n";
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
		echo "[".date(\DateTime::ISO8601, $time_before)."] ONESHOT mode. Only do one job.\n";
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
				echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] FETCH job skipped. (too recent: {$timestamps["src"][$job_i]["updated_at"]})\n";
				return false;
			}else{
				// No recent timestamp? WE'LL MAKE ONE!!
				// (after the HTTP requests are done)
				echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] FETCH job added to schedule:  \"{$job_i}\"\n";
				return true;
			}
			
		}else{
			echo "[".date(\DateTime::ISO8601, time())." {$job_i}] FETCH job has been skipped. (minute int missing or invalid)\n";
			return false;
		}
	}, ARRAY_FILTER_USE_BOTH);

	// Define SNITCH schedule
	foreach( $config["dest"] as $job_i => $job_v){

		switch( $job_v["api"] ) {
			# Unsupported crap
			default: {
				echo "[".date(\DateTime::ISO8601, time())." {$job_i}] SNITCH skipped. (invalid API \"{$job_v["api"]}\")\n";
				continue 2; # Skip this one
			}
			# Good APIs
			case "chaosnet":
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
					echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job skipped because (too recent: {$timestamps["dest"][$job_i]["updated_at"]})\n";
					continue;
				}else{
					// No recent timestamp? WE'LL MAKE ONE!!
					// (after the HTTP requests are done)
					echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job received new timestamp: \"{$job_i}\"\n";
					$todo_snitch[$job_i] = $job_v;
				}
			}
		}else{
			echo "[".date(\DateTime::ISO8601, time())." {$job_i}] SNITCH job has been skipped. (minute int missing or invalid)\n";
			continue;
		}
	}


	// ====== THE ACTUAL FETCH  ======
	// Fetch in summary, just in case
	$fetchdata = \LiquidAnacron\fetch($config, $todo_fetch);
	$time_afterfetch = time(); // The time is NOW!

	foreach( $todo_fetch as $job_i => $job_v){
		echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] FETCH job received new timestamp: \"{$job_i}\"\n";
		$timestamps["src"][$job_i]["updated_at"] = date(\DateTime::ISO8601, $time_afterfetch);
	}

	// ====== THE SNITCH ======
	// Pass data to snitch server
	echo \LiquidAnacron\snitch($fetchdata, $config["dest"]);
	$time_aftersnitch = time(); // The time is NOW!

	foreach( $todo_snitch as $job_i => $job_v){
		echo "[".date(\DateTime::ISO8601, $time_before)." {$job_i}] SNITCH job \"{$job_i}\" has been assigned a new timestamp.\n";
		$timestamps["src"][$job_i]["updated_at"] = date(\DateTime::ISO8601, $time_aftersnitch);
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
		echo "[".date(\DateTime::ISO8601, $time_afterall)."] Waiting for ".( 60-($time_afterall-$time_before) )." seconds...\n";
		sleep( 60-($time_afterall-$time_before) );
	}else{
		echo "[".date(\DateTime::ISO8601, $time_afterall)."] RT deadline violated by ".($time_before+60-$time_afterall)." seconds. No wait needed.\n";
	}
}while(true);
?>

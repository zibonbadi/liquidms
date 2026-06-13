<?php
# LiquidMS - federated master server
# Copyright (C) 2021-2026 Zibon Badi et al.
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

namespace LiquidAnacron\API\srb2http;

function fetch(array $config, array $job = []){
   $rVal = []; // Return value

   // Get stream context for header configs
   $stream_context = null;
   if(array_key_exists("http-header",$job)){ 
	   $stream_context = \LiquidAnacron\fetch_mkContext("GET", $job["http-header"]);
   }else{
	   $stream_context = \LiquidAnacron\fetch_mkContext("GET");
   }
   #var_dump($stream_context);

   $res_rooms = file_get_contents(
		   rtrim($job["host"], "/")."/rooms",
		   false,
		   $stream_context
		   );
   $res_server = file_get_contents(
		   rtrim($job["host"], "/")."/servers",
		   false,
		   $stream_context
		   );

   // Regex match room list into a group array:
   // - - "[Entire match]"
   //   - "[Room # as string]"
   //   - "[Room name]"
   //
   // Regex explained:
   // "/regex/m": multiline
   // "([0-9]+)\n": Capture room number (line with sole number on it)
   // "((?:[^\n]+\n)": Capture Room name
   preg_match_all("/([0-9]+)\n([^\n]+)\n/m", $res_rooms, $rooms, PREG_SET_ORDER);

   // Regex match server list into a group array:
   // - - "[Entire match]"
   //   - "[Room # as string]"
   //   - "[Multiline server table]"
   //
   // Regex explained:
   // "/regex/m": multiline
   // "([0-9]+)\n": Capture room number (line with sole number on it)
   // "((?:.*\n)*?": Capture server lines as block; Lines require additional filtering
   // "(?:\n|\Z)": Don't capture; match end of block (either \n or EOT)
   preg_match_all('/^([0-9]+)\n((?:.*\n)*?(?:\n|\Z))/m', $res_server, $servers, PREG_SET_ORDER);

   foreach($servers as $roomid =>  $roomdata){
	   // Break Server lists into Lines and feed the array

	   // Filter server block into distinct value arrays (step 2)
	   $serversplit = explode("\n",rtrim($roomdata[2],"\n"));

	   foreach($serversplit as $rowid =>  $rowdata){
		   $newrow = [];
		   $rowfields = explode(" ",$rowdata);

		   // Figure out server name
		   $roomname = NULL;
		   foreach($rooms as $r_infoid =>  $r_infodata){
			   if($roomdata[1] == $r_infodata[1]){
				   $roomname = $r_infodata[2];
				   break;
			   }
		   }

		   // Build Game object
		   $game_obj = [
				"name" => \LiquidAnacron\normalizeName($rowfields[2]),
				"game_host"		 => $rowfields[0],
				"game_port"		 => $rowfields[1],
				"game_api_name"	=> "srb2http",
				"game_api_data" => [
					"host"		 => $rowfields[0],
					"port"		 => $rowfields[1],
					"servername" => $rowfields[2],
					"version"	 => $rowfields[3],
					"roomname"	 => $roomname,
				],
				# Make Snitch transparent -> treat Snitch as origin_node
				"external_origin" => parse_url($job["host"])["host"],
				"origin_node"	  => NULL,
			];

			// Insert Game into list
			$rVal[] = $game_obj;
		}
   }

   return $rVal;
}

?>

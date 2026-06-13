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

namespace LiquidAnacron\API\srb2kart;

function fetch(array $config, array $job = []){
   $rVal = []; // Return value

   // Get stream context for header configs
   $stream_context = null;
   if(array_key_exists("http-header",$job)){ 
	   $stream_context = \LiquidAnacron\fetch_mkContext("GET", $job["http-header"]);
   }else{
	   $stream_context = \LiquidAnacron\fetch_mkContext("GET");
   }

   $res_server = file_get_contents(
		   rtrim($job["host"], "/")."?v=".$job["api_version"],
		   false,
		   $stream_context
		   );

	$res_server = explode("\n", $res_server);
	

	foreach($res_server as $rowid =>  $rowdata){

		$newrow = [];
		#$rowfields = explode(" ",$rowdata);
		preg_match_all('/^([^\s]*)\s+([^\s]*)\s+(.*)/', $rowdata, $rowfields, PREG_SET_ORDER);

		// Build Game object for return value
		$game_obj = [
			"name" => \LiquidAnacron\normalizeName($rowfields[0][3]),
			"game_host"		 => $rowfields[0][1],
			"game_port"		 => $rowfields[0][2],
			"game_api_name"	=> "srb2kart",
			"game_api_data" => [
				"host"		 => $rowfields[0][1],
				"port"		 => $rowfields[0][2],
				"contact" => $rowfields[0][3],
				"game"	 => $job["srb2kart_game"],
			],
			# Make Snitch transparent -> treat Snitch as origin_node
			"external_origin" => parse_url($job["host"])["host"],
			"origin_node"	  => NULL,
		];
		$rVal[] = $game_obj;
	}

   return $rVal;
}

?>

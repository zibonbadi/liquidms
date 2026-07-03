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

namespace LiquidAnacron\API\darkplaces;

function fetch(array $config, array $job = []){
   $rVal = []; // Return value

   // Settings:
   //
   // $job["protocol"] => Game protocol (e.g. 71, Darkplaces-1, etc.)
   // $job["use_getserver_ext"] => Whether to issue `getserversExt` (default false)

   $conn = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
   
   echo "Connecting to {$job["host"]} on port {$job["port"]}...\n";
   
   if($conn == NULL){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }
   
   // Block for 10 seconds before timeout
   socket_set_option($conn, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>10, 'usec'=>0));
   
   // Construct query message
   $request_msg = "\xFF\xFF\xFF\xFFgetservers {$job["protocol"]}";
   if($job["use_getservers_ext"]){ $request_msg = "\xFF\xFF\xFF\xFFgetserversExt {$job["protocol"]}\n"; }
   
   // Let's pass this request over UDP-style!
   if(!socket_sendto($conn, $request_msg, strlen($request_msg), 0, $job["host"], $job["port"])){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }


   do{
		// Q3A/OpenArena/DarkPlaces chunk their responses by some maximum buffer size.
		// "\x04\x00\x00\x00" is a dedicated marker for the last chunk.
		// So unless we detect that marker (or we hit a timeout), we keep going.
		
		$res = NULL; // Response data

		// Can we get anything yet?
		$bytes = socket_recvfrom($conn, $res, 2048, 0, $job["host"], $job["port"]);
		if($bytes == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		

		// A byte-encoded IP/Port combo may contain
		// control charactes, but we know it's length.
		//
		// That's why we match by length

		$netgames_raw = [];
		$netgames = []; // Temporary store before we wrangle them into Activitypub/ChaosNet shape
		$ipv4_raw = [];
		$ipv6_raw = [];
		
		// Matching over hex string because PHP doesn't like binary strings
		// Combined regex to avoid weird detecting offset artifacts
		preg_match_all("/(?:5c(.{12}))|(?:2f(.{36}))/", bin2hex($res), $netgames_raw);
		
		// Sublimate by IP type for easier processing below
		$ipv4_raw = array_filter($netgames_raw[1], function($v, $k){
			return $v != "";
		}, ARRAY_FILTER_USE_BOTH);
		
		$ipv6_raw = array_filter($netgames_raw[2], function($v, $k){
			return $v != "";
		}, ARRAY_FILTER_USE_BOTH);
		
		// Processing IPv4 netgames
		foreach($ipv4_raw as $ipv4_id => $ipv4_val){
			$port = unpack("nport",hex2bin(substr($ipv4_val,8,4)))["port"];

			$netgames[] = [
				"host" => inet_ntop(hex2bin(substr($ipv4_val,0,8))),
				"port" => $port,
			];
		}
		
		// Processing IPv6 netgames
		foreach($ipv6_raw as $ipv6_id => $ipv6_val){
			$port = unpack("nport",hex2bin(substr($ipv6_val,32,4)))["port"];

			$netgames[] = [
				"host" => inet_ntop(hex2bin(substr($ipv6_val,0,32))),
				"port" => $port,
			];
		}

		// Wrangle netgames into ActivityPub/ChaosNet shape
		foreach($netgames as $ng){
			$game_obj = [
				#"name" => \LiquidAnacron\normalizeName(res["body"]["servername"]),
				"game_host"		 => $ng["host"],
				"game_port"		 => $ng["port"],
				"game_api_name"	=> "darkplaces",
				#"game_api_data" => []
				# Make Snitch transparent -> treat Snitch as origin_node
				"external_origin" => $job["host"],
				"origin_node"	  => NULL,
			];
			$rVal[] = $game_obj;
		}

   }while($bytes > 0);

   // Close connection
   socket_close($conn);
   return $rVal;
}

?>

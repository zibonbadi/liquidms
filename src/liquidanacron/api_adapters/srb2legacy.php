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

namespace LiquidAnacron\API\srb2legacy;

function fetch(array $config, array $job = []){
   $rooms = []; // Room list
   $rVal = []; // Return value

   // Settings:
   //
   // $job["protocol_version"] = 12 | 21 => 1.09/2.1 protocol
   // $job["server_msg"]
   //   = GET_SERVER_MSG | GET_SERVERv2_MSG | GET_SHORT_SERVER_MSG | GET_EXT_SERVER_MSG
   //   => Server message type. GET_EXT_SERVER_MSG changes response data structures to enable IPv6 support

   $get_server_msg = 0; // Invalid message
   $ip_length = 16; // IPv4 by default

   switch($job["protocol_version"]){
	case 12:{
		// 1.09 protocol
		break;
	}
	case 21:{
		// 2.1 protocol
		break;
	}
	default:{
		echo "[".date(\DateTime::ISO8601, time())."] Unsupported protocol version. Skipping...\n";
		return [];
	}
   }
   
   switch($job["server_msg"]){
	case "GET_SERVER_MSG":{			$get_server_msg = 200; $ip_length = 16; break;	}
	case "GET_SHORT_SERVER_MSG":{	$get_server_msg = 205; $ip_length = 16; break;	}
	case "GET_EXT_SERVER_MSG":{		$get_server_msg = 217; $ip_length = 40; break;	}	// Hacked elsewhere in for IPv6 support
	default:{
		echo "[".date(\DateTime::ISO8601, time())."] Unsupported server message type. Skipping...\n";
		return [];
	}
   }

   // Establish connection to Legacy server
   $conn = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
   if(!socket_connect($conn,$job["host"], $job["port"])){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }
   
   // Get room data
   $get_rooms_packet = pack("NNNNa*", 0,210,0,1, "\0" ); # ID=0 TYPE=GET_ROOMS_MSG ROOM=0 LENGTH=0
   socket_send($conn, $get_rooms_packet, strlen($get_rooms_packet), 0);

   do{
		$res = [];
		// Part 1: Receive head
		$err = socket_recv($conn, $res_head, 16, 0);
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		
		$res = unpack("Nid/Nmsgtype/Nroom/Nlength", $res_head);
		$bytes_recv = $res["length"]; // Loop breaker
		if( $res["length"] <= 0){break;} # Breakout on empty message

		// Part 2: Receive body
		$err = socket_recv($conn, $res_body, 16+4+32+255, 0);
		if($err == false){ 	error_log(socket_strerror(socket_last_error($conn))); break; }
		$res["body"] = unpack("A16header/Vroomid/A32roomname/A255roomdesc", $res_body);

		# Add Room to internal room list
		if($res["body"]["roomid"] != 0){
			// Filter out standard room
			$rooms[$res["body"]["roomid"]] = [
				"_id" => $res["body"]["roomid"],
				"name" => $res["body"]["roomname"],
				"description" => $res["body"]["roomdesc"],
			];
		}
		
   }while($bytes_recv > 0);
   
   // Reconnect socket for servers
   socket_shutdown($conn);
   socket_close($conn);
   unset($conn);
   $conn = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
   if(!socket_connect($conn,$job["host"], $job["port"])){
	error_log(socket_strerror(socket_last_error($conn)));
	return [];
   }
   

   print("=== SERVERS ===\n");
   // Get Server Data
   #$get_servers_packet = pack("VVVV", 0,200, $i,0 ); # ID=0 TYPE=GET_SERVER_MSG ROOM=i LENGTH=0
   $get_servers_packet = pack("NNNNa*", 0,$get_server_msg, 0,0, "\0" ); # ID=0 TYPE=<see above> ROOM=0 (All rooms) LENGTH=0

   socket_send($conn, $get_servers_packet, strlen($get_servers_packet), 0);
 
   do{
		// Part 1: Head
		$err = socket_recv($conn, $res_head, 16, 0);
		
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		#$res["body"] = unpack("Vid/Vmsgtype/Vroom/Vlength/A16msgheader/A16ip/A8port/A32servername/Vroom/A8version", $res_rooms);
		$res = unpack("Nid/Nmsgtype/Nroom/Nlength", $res_head);
		$bytes_recv = $res["length"]; // Loop breaker
		if($res["length"] <= 0){break;} # Breakout on empty message

		// Part 2: Body
		$err = socket_recv($conn, $res_body, 16+$ip_length+8+32+4+8, 0);
		if($err == false){ error_log(socket_strerror(socket_last_error($conn))); break; }
		$res["body"] = unpack("A16header/A{$ip_length}ip/A8port/A32servername/Vroom/A8version", $res_body);

		// Below: return value structure in YAML format (one server).
		// Defaults and examples are noted in paretheses:
		//
		// ---
		// - host: "[Server IP address (127.0.0.1)]"
		//   port: [Port (5029)]
		//   servername: "[Server name (SRB2%20server)]"
		//   version: "[Server version (2.2.9)]"
		//   roomname: "[Room name ("Casual", "World", etc.)]"
		//   origin: "[Room origin (mb.srb2.org)]"
		// ...
		//
		// The field "origin" is optional. If empty, it indicates a server
		// registered to the node's world.

		$rVal[] = [
		   "_api" => "srb2legacy",
		   "host" => $res["body"]["ip"],
		   "port" => $res["body"]["port"],
		   "servername" => urlencode(urldecode($res["body"]["servername"])),
		   "version" => $res["body"]["version"],
		   "roomname" => $rooms[$res["body"]["room"]]["name"],
		   "_origin" => $job["host"], // Extract hostname from URL
		];

		$game_obj = [
			"name" => \LiquidAnacron\normalizeName(res["body"]["servername"]),
			"game_host"		 => $res["body"]["ip"],
			"game_port"		 => $res["body"]["port"],
			# SRB2Legacy's data structure is identical to SRB2HTTP
			"game_api_name"	=> "srb2http",
			"game_api_data" => [
				"host"		 => $res["body"]["ip"],
				"port"		 => $res["body"]["port"],
				"servername" => $res["body"]["servername"],
				"version"	 => $res["body"]["version"],
				"roomname"	 => $rooms[$res["body"]["room"]]["name"],
			],
			# Make Snitch transparent -> treat Snitch as origin_node
			"external_origin" => $job["jost"],
			"origin_node"	  => NULL,
		];
		$rVal[] = $game_obj;

   }while($bytes_recv > 0);

   // Close connection
   socket_close($conn);
   return $rVal;
}

?>

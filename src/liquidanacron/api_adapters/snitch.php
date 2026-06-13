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

namespace LiquidAnacron\API\snitch;

function fetch(array $config, array $job = []){

	// === Snitch API source ===
	// "game_api_name" => "snitch" is not to be treated as a real API name.
	// Instead the adapters should try to resolve
	// the netgames' API to `srb2http` or `srb2kart`

	$rVal = [];
	if (($handle = fopen(rtrim($job["host"], "/"), "r")) !== FALSE) {
		while(($data = fgetcsv($handle, null, ",", '"', "\\")) !== FALSE) {
			if ($data != null and $data[0] != NULL) {
				$game_obj = [
					"name" => \LiquidAnacron\normalizeName($data[2]),
					"game_host"		 => $data[0],
					"game_port"		 => $data[1],
					# Making Snitch transparent to ChaosNet
					# Are we dealing with hax (version == "SRB2Kart") ?
					"game_api_name"	=> ($data[3] == "SRB2Kart") ? "srb2kart": "srb2http",
					# Make Snitch transparent -> treat Snitch as origin_node
					"external_origin" => $data[5] ?? NULL,
					"origin_node"	  => rtrim($job["host"], "/"),
				];

				if($data[3] == "SRB2Kart"){
					// SRB2Kart Game
					$game_obj["game_api_data"] = [
						"host"		 => $data[0],
						"port"		 => $data[1],
						"contact" => $data[2],
						"game"	 => $data[4],
					];
				}else{
					// SRB2HTTP game
					$game_obj["game_api_data"] = [
						"host"		 => $data[0],
						"port"		 => $data[1],
						"servername" => $data[2],
						"version"	 => $data[3],
						"roomname"	 => $data[4],
					];
				}

				$rVal[] = $game_obj;
			}
		}
		fclose($handle);
	}

	return $rVal;
}

function snitch(Array $data, String $url){
	$csvContent = "";
	$http_response = "";
	$multipart_boundary = '--------------------------'.microtime(true);
	$multipart_fieldname = 'data';
	$multipart_filename = 'snitch.csv';

	foreach($data as $dataIndex => $dataRow){
		// Create data
		#echo "[".date(\DateTime::ISO8601, time())."] Processing row {$dataIndex}...\n";

		$dRow_clean = NULL;
		switch($dataRow["game_api_name"]){
			case "srb2http":{
				// Clean, beautiful SRB2HTTP rows
				$dRow_clean = [
					"host" => $dataRow["game_api_data"]["host"] ?? $dataRow["game_host"],
					"port" => $dataRow["game_api_data"]["port"] ?? $dataRow["game_port"],
					"servername" => $dataRow["game_api_data"]["servername"] ?? ["name"],
					"version" => $dataRow["game_api_data"]["version"],
					"roomname" => $dataRow["game_api_data"]["roomname"],
					"origin" => $dataRow["external_origin"],
				];
				break;
			}
			case "srb2kart":{	
				// Hacky SRB2Kart rows	
				$dRow_clean = [
					"host" => $dataRow["game_api_data"]["host"] ?? $dataRow["game_host"],
					"port" => $dataRow["game_api_data"]["port"] ?? $dataRow["game_port"],
					"servername" => $dataRow["game_api_data"]["contact"] ?? ["name"],
					"version" => "SRB2Kart",
					"rommname" => $dataRow["game_api_data"]["game"],
					"origin" => $dataRow["external_origin"],
				];
				break;
			}
			default:{
				// Unsupported API -> Skip
				continue 2;
				break;
			}
		}


		// Write CSV to var. Iterative opening may
		// be slower, but guarantees clean output
		if($dRow_clean != NULL){
			$tmp = fopen('php://memory', 'r+');
			if( !fputcsv($tmp, $dRow_clean, ",", '"', "\\") ){ continue; }
			rewind($tmp);
			$csvContent .= stream_get_contents($tmp);
			fclose($tmp);
		}
	}
	$csvContent = rtrim($csvContent, "\n");

	/*/ NEW METHOD: cURL /*/

	$cStringFile = new \CurlStringFile($csvContent, $multipart_filename, "text/csv; header=absent");

	$creq = curl_init();

	curl_setopt_array($creq, [
		CURLOPT_URL =>  $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => [$cStringFile],
		CURLOPT_HTTPHEADER =>[ "Content-type: multipart/form-data"]
		]);

	$http_response = curl_exec($creq);
	curl_close($creq);

	return $http_response."\n";
}

?>

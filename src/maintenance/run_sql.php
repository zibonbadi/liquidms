<?php
# LiquidMS - distributable SRB2 master server
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

/*
* @params:
* --DSN=[DSNSTRING]: DSN connection string
* --user=[USER]: Database User
* --password=[PASS]: Database Password
*/
$posarg_idx = null;
$FLAGS = getopt("f:", [
"file:",
"user:",
"password:",
"dsn:",
], $posarg_idx);

$settings = [];
$posargs = array_slice($argv, $posarg_idx);
				
if( array_key_exists("f", $FLAGS) ){  # Prefer BSD flag
	$settings = yaml_parse_file($FLAGS["f"], -1); 
}else if( array_key_exists("file", $FLAGS) ){
	$settings = yaml_parse_file($FLAGS["file"], -1);
}else{
	// Get info from argv
	
	// Sanity check
	if( !array_key_exists("dsn", $FLAGS) ){ echo "No DSN string given.\n"; return false; }
	if( !array_key_exists("user", $FLAGS) ){ echo "No user name string given.\n"; return false; }
	if( !array_key_exists("password", $FLAGS) ){ echo "No password string given.\n"; return false; }

	$settings["db"] = [
		"dsn" => $FLAGS["dsn"],
		"user" => $FLAGS["user"],
		"password" => $FLAGS["password"],
	];

	$query_a = [];
	foreach($posargs as $pa_name => $pa_value){ $query_a[$pa_name] = file_get_contents($pa_value); }
	$settings["query"] = implode(";\n", $query_a);
}

$hdl = new \PDO( $settings["db"]["dsn"], $settings["db"]["user"], $settings["db"]["password"] );
$hdl->beginTransaction();

try{
	$statement = $hdl->prepare($settings["query"]);
	$result = $statement->execute();
	$hdl->commit();
	if($result == false){ 
			return [
				"error" => $hdl::getCode(), 
				"message" => $hdl::getMessage(),
				"query" => $settings["query"],
			];
	}else{
			return [
					"error" => 0,
					"message" => "Successfully executed.",
					"data" => $statement->fetchAll(),
					"rows" => $statement->rowCount(),
			];
	}
}catch(PDOException $e){
	file_put_contents('php://stderr', "$e");
	$hdl->rollBack();
	return [
			"error" => $e::getCode(), 
			"message" => $e::getMessage(),
			"query" => $settings["query"],
	];
}
?>

<?php
$maincontent = "";
$import = $this->sharedData()->get('data');
$room = $this->sharedData()->get('room');

foreach($import["data"] as $import_index => $import_value){
#echo "Querying server '{$server_index}': {$server_value["host"]}\n";
	$maincontent .= $import_value["host"]." ".
					$import_value["port"]." ".
					$import_value["servername"]." ".
					$import_value["version"].
					"\n";
}
echo "{$room}\n{$maincontent}";

?>

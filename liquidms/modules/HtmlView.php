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
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="browse/css/main.css">
<script src="browse/js/main.js" defer></script>
</head>
<body>
<h1>Sorry you gotta see us nude!</h1>
<p>We're working on a better browser right as we write! Until then, have this table:</p>
<?php
$maincontent = "<table><tr>"
				."<th>Host</th>"
				."<th>Port</th>"
				."<th>Title</th>"
				."<th>Version</th>"
				."<th>Room</th>"
				."<th>Room</th>"
				."<th>Last updated</th>";
$import = $this->sharedData()->get('data');

// Guarantee room slots
foreach($import["data"] as $import_index => $import_value){
	// Generate content string
	$maincontent .= "<tr><td>".$import_value["host"]."</td>"
		."<td>".$import_value["port"]."</td>"
		."<td>".urldecode($import_value["servername"])."</td>"
		."<td>".$import_value["version"]."</td>"
		."<td>".$import_value["roomname"]."</td>"
		."<td>".$import_value["origin"]."</td>"
		."<td>".$import_value["updated_at"]."</td></tr>";
}
$maincontent .= "</table>\n";
echo $maincontent;
#var_dump($import);
?>
</body>
</html>


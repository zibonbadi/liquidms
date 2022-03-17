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
<style>
@import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
:root{
	--color-bg: teal;
	--color-text: yellow;
	--color-table-bg: #003;
	--color-table-bg2: #005;
	--color-table-bg-head: #007;
	--color-table-bg-hover: #335;
	--color-table-border: #444;
}

@media screen and (prefers-color-scheme: dark){
:root{
	--color-bg: #000;
	--color-text: yellow;
	--color-table-bg: #003;
	--color-table-bg2: #005;
	--color-table-bg-head: #007;
	--color-table-bg-hover: #335;
	--color-table-border: #444;
}
}

body{
color: var(--color-text);
background: var(--color-bg);
font-family: 'Press Start 2P', sans-serif;
}

h1,p{
max-width: 60em;
}

table,tr,td{ border: none; }

table{
	background: var(--color-table-bg);
	border-color: var(--color-table-border);
	margin-left: auto;
	margin-right: auto;
}

td,th{ padding: 0.75em; }
tr:first-child{ background: var(--color-table-bg-head); }
tr:nth-child(2n){ background: var(--color-table-bg2); }
tr:hover{ background: var(--color-table-bg-hover); }

</style>
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
				."<th>Origin</th>";
$import = $this->sharedData()->get('data');

// Guarantee room slots
foreach($import["data"] as $import_index => $import_value){
	// Generate content string
	$maincontent .= "<tr><td>".$import_value["host"]."</td>"
		."<td>".$import_value["port"]."</td>"
		."<td>".urldecode($import_value["servername"])."</td>"
		."<td>".$import_value["version"]."</td>"
		."<td>".$import_value["roomname"]."</td>"
		."<td>".$import_value["origin"]."</td></tr>";
}
$maincontent .= "</table>\n";
echo $maincontent;
#var_dump($import);
?>
</body>
</html>


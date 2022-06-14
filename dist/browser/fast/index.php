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

<?php
$netgames = $this->sharedData()->get('netgames');
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="browse/css/main.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="browse/js/tablesort.js" async defer></script>
</head>
<body>
<img src="browse/img/logo.svg">

<h1>Integrated server browser</h1>
<pre><?php echo $this->sharedData()->get('motd'); ?></pre>
<table>
	<thead>
			<tr>
			<th data-category="servername">Title</th>
			<th data-category="host">Host</th>
			<th data-category="port">Port</th>
			<th data-category="version">version</th>
			<th data-category="roomname">Room name</th>
			<th data-category="origin">Origin</th>
			</tr>
	</thead>
<tbody>
<?php
foreach($netgames["data"] as $server){
	echo "<tr>
		<td slot=\"field\" data-category=\"servername\">".urldecode($server["servername"])."</td>
		<td slot=\"field\" data-category=\"host\">{$server["host"]}</td>
		<td slot=\"field\" data-category=\"port\">{$server["port"]}</td>
		<td slot=\"field\" data-category=\"version\">{$server["version"]}</td>
		<td slot=\"field\" data-category=\"roomname\">{$server["roomname"]}</td>
		<td slot=\"field\" data-category=\"origin\">{$server["origin"]}</td>
		</tr>\n";
}
?>
</tbody></table>
</body>
</html>


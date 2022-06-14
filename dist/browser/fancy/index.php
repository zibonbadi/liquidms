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
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="browse/js/main.js" async defer></script>
<!-- Custom element templates -->
<link rel="stylesheet" href="browse/css/NetgameComponent.css">
<template data-name="netgame">
	<link rel="stylesheet" href="browse/css/NetgameComponent-shadow.css">
	<div>
	<slot name="name">Dummy server</slot>
	<div class="flex">
	<ul>
	<li>
	<slot name="hostname">Dummy hostname</slot>
	<slot name="port">Dummy port</slot>
	</li>
	<li>Game type: <slot name="gametype">unknown</slot></li>
	</ul>
	<ul>
	<li><slot name="version">DummyBuild</slot> (<slot name="version_name">SRB2</slot> <slot name="version_major">X</slot> <slot name="version_minor">Y</slot> <slot name="version_patch">Z</slot>)</li>
	<li>Update: <slot name="updated_at">Never</slot></li>
	</ul>
	</div>
	<div class="flex">
	<div class="block">
	<slot name="players">x</slot>
	/
	<slot name="maxplayers">n</slot>
	</div>
	<div><slot name="ping">&infin;</slot> ms</div>
	</div>
	</div>
	<hr>
	<details>
	<summary>Netgame details</summary>
		<div class="flex">
		<ul class="flex-left">
		<li>Stage: <slot name="level_name">Earless Netless Zone</slot></li>
		<li>Stage hash: <slot name="level_md5">xxxxx</slot></li>
		</ul>
		<ul class="flex-right">
		<li>Dedicated server: <slot name="dedicated">unknown</slot></li>
		<li>Modified: <slot name="modified">unknown</slot></li>
		<li>Cheats: <slot name="cheats">unknown</slot></li>
		<li>Origin: <slot name="roomname">Dummy room</slot>@<slot name="origin">World</slot></li>
		</ul>
		</div>
	</details>
	<details>
	<summary>Players</summary>
	<div class="flex flex-left">
	<slot name="players_list">No players available.</slot>
	</div>
	</details>
	<hr>
	<div class="flex flex-center">
	<!-- <input type="button" value="Update" name="update"> -->
	<a href="#" class="button" name="update">Update</a>
	</div>
</template>
<link rel="stylesheet" href="browse/css/NetgameListComponent.css">
<template data-name="netgamelist">
	<link rel="stylesheet" href="browse/css/NetgameListComponent-shadow.css">
	<div class="buttonbox">
	<a href="#" class="button" name="update">Update all</a>
	<!--
	<input type="button" value="Update all" name="update">
	<a href="#" class="button" name="sortbutton">Sort list</a>
	-->
	<select value="Update all" name="sort">
		<option value="maxplayers">Max players</option>
		<option value="minplayers">Min players</option>
		<option value="name">Name A-Z</option>
		<option value="origin">Origin A-Z &rarr; Room A-Z</option>
		<option value="ping">Ping</option>
		<option value="roomname">Room A-Z &rarr; Origin A-Z</option>
		<option value="updated_at">Latest update</option>
		<option value="version">Latest version</option>
	</select>
	<select value="View" name="view">
		<option value="list">List</option>
		<option value="gallery">Gallery</option>
	</select>
	<a href="#" class="button checkbox">Reverse</a>
	</div>
	<slot name="netgames"><p>No servers available.</p></slot>
</template>
</head>
<body>
<img src="browse/img/logo.svg">
<h1>Integrated server browser</h1>
<pre><?php echo $this->sharedData()->get('motd'); ?></pre>
<sb-netgamelist view="list"></sb-netgamelist>
</body>
</html>


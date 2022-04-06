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

	<i class="fa-solid fa-alien-8bit"></i>
	<i class="fa-solid fa-cards"></i>
	<i class="fa-solid fa-signal"></i>
	<i class="fa-solid fa-user"></i>
	<i class="fa-solid fa-user-group"></i>
	<i class="fa-solid fa-wrench"></i>

	<slot name="name">Dummy server</slot>
	<ul>
	<li>
	<slot name="hostname">Dummy hostname</slot>
	<slot name="port">Dummy port</slot>
	</li>
	<li><slot name="roomname">Dummy room</slot>@<slot name="origin">World</slot></li>
	<li><slot name="version">DummyBuild</slot></li>
	<li>Last updated: <slot name="updated_at">Never</slot></li>
	</ul>
	<hr>
	<details>
	<summary>Netgame details</summary>
		<slot>Dummy netgame info (Map, mods, etc.)</slot>
	</details>
	<hr>
	<div class="flex">
	<div class="block">
	<slot name="players">x</slot>
	/
	<slot name="maxplayers">n</slot>
	</div>
	<div><slot name="ping">&infin;</slot> ms</div>
	</div>
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
	</div>
	<ul>
	<slot name="netgames"><p>No servers available.</p></slot>
	</ul>
</template>
</head>
<body>
<img src="browse/img/logo.svg">
<h1>Integrated server browser</h1>
<pre><?php echo $this->sharedData()->get('motd'); ?></pre>
<sb-netgamelist></sb-netgamelist>
</body>
</html>


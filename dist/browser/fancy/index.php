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
	<span name="name">Dummy server</span>
	<div name="netgameaddress"><span name="hostname">Dummy hostname</span>:<span name="port">Dummy port</span></div>
	<span name="gametype">unknown</span>
	<span name="version">DummyBuild</span>
	<span name="updated_at">Never</span>
	<div name="playercount">
		<span name="players">x</span>/<span name="maxplayers">n</span>
		<progress name="players_bar"></progress>
	</div>
	<div name="originaddress"><span name="roomname">Dummy room</span>@<span name="origin">World</span></div>
	<details id="meta" name="details">
	<summary>Netgame details</summary>
		<div class="flex">
		<ul class="flex-left">
		<li>Stage: <span name="level_name">Earless Netless Zone</span></li>
		<li>Stage hash: <span name="level_md5">xxxxx</span></li>
		</ul>
		<ul class="flex-right">
		<li>Dedicated server: <span name="dedicated">unknown</span></li>
		<li>Modified: <span name="modified">unknown</span></li>
		<li>Cheats: <span name="cheats">unknown</span></li>
		<li>Origin: <span name="roomname">Dummy room</span>@<span name="origin">World</span></li>
		</ul>
		</div>
	</details>
	<details id="playerlist" name="playerbox">
	<summary>Players</summary>
	<div class="flex flex-left">
	<slot name="players_list">No players available.</slot>
	</div>
	</details>
	<div name="buttons" class="flex flex-center">
	<!-- <input type="button" value="Update" name="update"> -->
	<a href="#" class="button" name="update">Update</a>
	<a href="#" class="checkbox" name="pin">Pin Netgame</a>
	</div>
</template>
<link rel="stylesheet" href="browse/css/NetgameListComponent.css">
<template data-name="netgamelist">
	<link rel="stylesheet" href="browse/css/NetgameListComponent-shadow.css">
	<p>
	<progress></progress>
	<span name="players_total">x</span>
	/
	<span name="maxplayers_total">x</span>
	Players online
	</p>
	<div class="buttonbox">
	<input type="text" placeholder="Search" name="search">
	<a href="#" class="button" name="update">Update all</a>
	<!--
	<input type="button" value="Update all" name="update">
	<a href="#" class="button" name="sortbutton">Sort list</a>
	-->
	<select value="Update all" name="sort">
		<option value="players">Players</option>
		<option value="name">Name</option>
		<option value="origin">Origin</option>
		<option value="roomname">Room</option>
		<option value="updated_at">Timestamp</option>
		<option value="version">Version</option>
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
<a target="_blank" href="https://github.com/zibonbadi/liquidms/">
<img src="browse/img/logo.svg">
</a>
<h1>Integrated server browser</h1>
<pre><?php echo $this->sharedData()->get('motd'); ?></pre>
<?php if(in_array('v1', $this->sharedData()->get('modules'))){ ?>
<p>
Use this server in-game:
<ol>
<li>Options</li>
<li>Server Options</li>
<li>Advanced</li>
<li>Server</li>
<li>Master Server: <a href="<?php
	$port_str = "";
	switch($_SERVER['SERVER_PORT']){
	case 80:
	case 443:{
		break;
	}
	default:{
		$port_str = ":{$_SERVER['SERVER_PORT']}";
		break;
	}
	}
	echo "http://{$_SERVER['SERVER_NAME']}{$port_str}/v1";
?>">
<?php
	$port_str = "";
	switch($_SERVER['SERVER_PORT']){
	case 80:
	case 443:{
		break;
	}
	default:{
		$port_str = ":{$_SERVER['SERVER_PORT']}";
		break;
	}
	}
	echo "http://{$_SERVER['SERVER_NAME']}{$port_str}/v1";
?></a></li>
</ol>
</p>
<?php } ?>
<sb-netgamelist view="list"></sb-netgamelist>
<p><a target="_blank"
href="https://github.com/zibonbadi/liquidms/">LiquidMS</a> version
1.1.1-dev. &copy; 2021-2022 Zibon Badi and others. LiquidMS is licensed under the <a target="_blank" href="/liquidms/license">GNU Affero General Public License version 3</a></p>
<p>LiquidMS is part of Liquid Underground. <a href="https://discord.gg/HVTzVfAWG6">Join our Discord!</a></p>
</body>
</html>


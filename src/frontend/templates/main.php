<?php
# LiquidMS - distributable SRB2 master server
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

$netgames = $this->sharedData()->get('netgames');
$config = $this->sharedData()->get('config');
?>

<!DOCTYPE html>
<html>
<head>
<!-- Search engine and preview metadata -->
<meta name="title" content="LiquidMS">
<meta name="generator" content="LiquidMS">
<meta name="creator" content="Zibon Badi">
<meta name="creator" content="Liquid Underground">
<meta name="description" content="Search netgames on LiquidMS - the federated master server.">
<meta name="keywords" content="masterserver, srb2, srb2ms">
<meta name="robots" content="index, follow">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="language" content="English">
<!-- Styles and favicon -->
<link rel="icon" type="image/svg" href="<?php echo "{$config["basepath"]}/favicon.svg";?>">
<link rel="stylesheet" href="<?php echo "{$config["basepath"]}/css/main.css";?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="<?php echo "{$config["basepath"]}/js/main.js";?>" async defer></script>
<!-- Custom element templates -->
</head>
<body>
<a target="_blank" href="https://github.com/zibonbadi/liquidms/">
<img src="<?php echo "{$config["basepath"]}/img/logo.svg";?>">
</a>
<h1>Master Server Browser</h1>

<input type="text" placeholder="Type to search netgames" name="search">

<p>Select an API to browse:</p>
<liquidms-tabwindow>
<?php
# include whatever templates we need
foreach($config["services"] as $service_name => $service ){
    echo "<details id=\"tab-$service_name\" open><summary>$service_name</summary><div id=\"content-$service_name\">";
    include __DIR__."/".strtolower($service["_api"]).".php";
    echo "</div></details>";
}
?>
</liquidms-tabwindow>
<sb-netgamelist view="list"></sb-netgamelist>
<p><a target="_blank"
href="https://github.com/zibonbadi/liquidms/">LiquidMS</a> version
2.0-dev. &copy; 2021-2026 Zibon Badi and others. LiquidMS is licensed under the <a target="_blank" href="<?php echo "{$config["basepath"]}/license";?>">GNU Affero General Public License version 3</a></p>
<p>LiquidMS is part of Liquid Underground. <a href="https://discord.gg/HVTzVfAWG6">Join our Discord!</a></p>

</body>
</html>


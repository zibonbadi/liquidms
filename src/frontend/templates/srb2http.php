<?php
require_once __DIR__.'/../modules_vendor/srb2query.php';
require_once __DIR__.'/../SRB2HTTP/NetgameModel.php';
$netgames = \LiquidMS\SRB2HTTP\NetgameModel::getInstance()->getServers($service);

if(array_key_exists("_motd", $service)){
	echo "<div class=\"info\">{$service["_motd"]}</div>";
}

if($config["settings"]["do_srb2query"]){ ?>
<p><strong>CAREFUL!</strong> Live data (such as ping) is shown <em>from the point-of-view of this web server!</em> While a good rule of thumb, it does NOT imply availability from wherever you're playing!</p>
<?php }

$universe_grouped = [];
$world = NULL;

foreach($netgames["data"] as $ng_i => $ng){

	$origin = $ng["origin"];
	if(!array_key_exists($ng["origin"], $universe_grouped)){
		$universe_grouped[$ng["origin"]] = [];
	}

	$universe_grouped[$ng["origin"]][] = $ng;
}

if(array_key_exists("localhost", $universe_grouped)){
	# Move world to the top of the page
	$world = $universe_grouped["localhost"];
	unset($universe_grouped["localhost"]);
	array_unshift($universe_grouped, $world);
}
?>
<liquidms-srb2httptable data-service="<?php echo $service_name?>" data-sort="players">

	<template shadowrootmode="open">
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
		:host{
			/* --color-bg: url('/browse/img/bg.svg'); */
			--main-bg: url('<?php echo $config["basepath"]; ?>/img/bg.svg') rgba(255, 220, 21, 1);
			/* --color-bg: teal; */
			--color-text: black;
			--color-link: yellow;
			--table-text: #ccc;
			--table-head-text: yellow;
			--table-bg: #003d;
			--table-bg2: #114d;
			--table-bg-head: #002f;
			--table-locked-bg: #447d;
			--table-bg-hover: #335;
			--netgame-bg: #003d;
			--netgame-locked-bg: #447d;
			--netgame-bg-error: #ff3f3f;
			--netgame-bg-error2: #ff5f5f;
			--netgame-bg-error-hover: #ff7f7f;
			--netgame-text-error: #ffbae4;
			--netgame-border-error: #fd9fa3;
			/* --netgame-bg: #35d1ff77; */
			--netgame-bg-hover: #335;
			--netgame-text: white;
			--netgame-title-text: yellow;
			--netgame-border: 3pt solid #444;
		}

		a{
			text-decoration: none;
			color: var(--color-link);
		}

		.hidden, .error,
		table:not(:has(tbody:not(.hidden, .error)))
		{
			display: none;
		}

		table,tbody,tr,td{ border: none; }

		table{
			border: var(--netgame-border);
			margin: 2em;
		}
		table,tbody{
			background: var(--table-bg);
			margin-left: auto;
			margin-right: auto;
			color: var(--table-text);
			max-width: fit-content;
		}

		th,thead{
			color: var(--table-head-text);
			background: var(--table-bg-head); 
			position: sticky;
			top: 0;
			border-bottom: 1pt solid var(--table-text);
		}
		td,th{ 
			padding: 0.75em;
			max-width: fit-content;
		}
		th{
			cursor: pointer;
		}
		
		tbody:nth-child(2n of :not(.hidden, .error)){ background: var(--table-bg2); }
		tbody:not(:focus-within) > tr + tr.details{ visibility: collapse;}
		@media(any-hover: hover) and (pointer: fine){
			tbody:hover,tbody:nth-child(2n):hover{ background: var(--table-bg-hover); }
			tbody.error:hover,tbody.error:nth-child(2n):hover{ background: var(--netgame-bg-error-hover); }
			th:hover{ background: var(--table-bg-hover); }
		}
		
		caption{
			font-family: 'Press Start 2P', sans-serif;
			font-size: larger;
			padding: 0.5em;
			text-shadow: 0.07em 0.1em 0 black;
			color: var(--netgame-title-text);
		}

		tbody.error{
			background:  var(--netgame-bg-error);
			border-color: var(--netgame-border-error);
			color: var(--netgame-border-error);
			opacity: 66%;
		}
		tbody.error:nth-child(2n){
			background:  var(--netgame-bg-error2);
		}
	</style>


<?php
foreach($universe_grouped as $origin_host => $origin_list){
?>
	<table>
	<?php if($origin_host != 0){ ?>
	<caption>Netgames @ <?php echo $origin_host; ?> 
		<a href="#" class="button" name="update">Update all</a>
	</caption>
	<?php }else{ ?>
	<caption>World Netgames</caption>
	<?php } ?>
	<thead>
			<tr>
			<th data-category="ip">IP address</th>
			<th data-category="port">Port</th>
			<th data-category="servername">Title</th>
			<?php if($config["settings"]["do_srb2query"]){ ?>
			<th data-category="players">Players</th>
			<?php } ?>
			<th data-category="version">Version</th>
			<th data-category="roomname">Room</th>
			<th data-category="origin">Origin</th>
			</tr>
	</thead>
<?php

$srb2q = new SRB2Query;

foreach($origin_list as $server){
	$html_row = "<tr>
		<td data-category=\"ip\">{$server["host"]}</td>
		<td data-category=\"port\">{$server["port"]}</td>
		<td data-category=\"servername\">".$srb2q->Colorize(urldecode($server["servername"]))."</td>";
		
	if($config["settings"]["do_srb2query"]){    
		$html_row .= "<td data-category=\"players\">???</td>";
	}

	$html_row .= "<td data-category=\"version\">{$server["version"]}</td>
		<td data-category=\"roomname\">{$server["roomname"]}</td>
		<td data-category=\"origin\">{$server["origin"]}</td>
		</tr>\n";
	
	if($config["settings"]["do_srb2query"]){ 
		# Add metadata row
		$html_row .= "<tr class=\"details\"><td colspan=\"7\"><em>Netgame data unavailable.</em></td></tr>";
	}

	echo "<tbody tabindex=\"0\" data-address=\"{$server["host"]}:{$server["port"]}\">{$html_row}</tbody>";
}

if(count($universe_grouped) < 1){
?>
<p>No servers available.</p>
<?php } ?>
</table>
<?php } # END Origin sorter ?>
</template>
</liquidms-srb2httptable>
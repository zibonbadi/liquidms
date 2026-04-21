<?php
require_once __DIR__.'/../SRB2Kart/NetgameModel.php';
$netgames = \LiquidMS\SRB2Kart\NetgameModel::getInstance()->getServers($service);

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

foreach($universe_grouped as $origin_host => $origin_list){
?>


<table is="liquidms-table" data-service="<?php echo $service_name?>" data-sort="players">
	<?php if($origin_host != 0){ ?>
	<caption>Netgames @ <?php echo $origin_host; ?></caption>
	<?php }else{ ?>
	<caption>World Netgames</caption>
	<?php } ?>
	<thead>
			<tr>
			<th data-category="address">Address</th>
			<th data-category="servername">Title</th>
			<?php if($config["settings"]["do_srb2query"]){ ?>
			<th data-category="players">Players</th>
			<?php } ?>
			<th data-category="contact">Contact</th>
			<th data-category="game">Game</th>
			<th data-category="origin">Origin</th>
			</tr>
	</thead>
	<tbody>
<?php
foreach($origin_list as $server){
	$html_row = "<tr>
		<td slot=\"field\" data-category=\"address\">{$server["host"]}:{$server["port"]}</td>
		<td slot=\"field\" data-category=\"servername\">".urldecode($server["contact"])."</td>";
    
	if($config["settings"]["do_srb2query"]){    
		$html_row .= "<td slot=\"field\" data-category=\"players\">???</td>";
	}

	$html_row .= "<td slot=\"field\" data-category=\"contact\">".urldecode($server["contact"])."</td>
        <td slot=\"field\" data-category=\"game\">".$server["game"]."</td>
		<td slot=\"field\" data-category=\"origin\">{$server["origin"]}</td>
		</tr>\n";
	echo $html_row;
}

if(count($universe_grouped) < 1){
?>
<p>No servers available.</p>
<?php } ?>
	</tbody>
</table>
<?php } # END Origin sorter ?>

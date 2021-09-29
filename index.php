<?php
if (!extension_loaded('yaml')) {
    echo "YAML not found";
    exit; //You need YAML. *Probably* another extension as well, but I'm not well aware.
}

$configpath = __DIR__ . "/config.yaml"; //the config file

$nodelist = yaml_parse_file($configpath, -1);

// Merge all yaml configs together
$tmp = [];
foreach($nodelist as $docname => $doc) {
$tmp = array_merge_recursive($tmp, $doc);
}
$nodelist = $tmp;

function liquidrequest($msservers, $apipage) {
    $getrooms = array('http' =>
        array(
            'method' => 'GET'
        )
    );

    $context = stream_context_create($getrooms); // creating the context for HTTP GET requests

    $servresults = file_get_contents($msservers . $apipage,
                      False,
                      $context
                   );

    echo $servresults; //Do whatever you want with the output from here (ex. giving the array another option like file location)
}

function updateliquid() {
    $apipages = array(
        "/rooms",
        "/versions/18",
        "/rooms/33/servers" //I'd much rather remove this, once there's a way of parsing room lists
    );
    global $nodelist;
    foreach($nodelist['fetch'] as $msservers) {
        foreach($apipages as $api2) {
            liquidrequest($msservers, "$api2"); //checking all the servers/pages
        }
    }
}
updateliquid()
?>

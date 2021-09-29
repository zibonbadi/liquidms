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

function listmsservers($msservers) {
    $getrooms = array('http' =>
        array(
            'method' => 'GET'
        )
    );

    $context = stream_context_create($getrooms); // creating the context for HTTP GET requests

    $servresults = file_get_contents($msservers . "/rooms", //in this example we're checking for rooms, issue #3
                      False,
                      $context
                   );

    echo $servresults;
}


foreach($nodelist['fetch'] as $msservers) {
    listmsservers($msservers); //checking all the servers -- I don't know why, but after the official
    //SRB2 MS, it attempts to run the function on nothing (file_get_contents())
    //Perhaps a configuration issue on my part?
}
?>

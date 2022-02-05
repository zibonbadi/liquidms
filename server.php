<?php
// Setup, configs etc.
require_once __DIR__.'/vendor/autoload.php';

// Local utilities
require_once __DIR__.'/src/ConfigModel.php';
require_once __DIR__.'/src/NetgameModel.php';


use LiquidMS\ConfigModel;
use LiquidMS\NetgameModel;
use Klein\Klein;

// Main router object
$router = new Klein();
$configmodel = ConfigModel::init();
$config = ConfigModel::getConfig();

#var_dump($config);
#var_dump($configmodel);

NetgameModel::init($config["db"]);

// Set API routes
include_once __DIR__.'/api.php';
include_once __DIR__.'/liquidendpoints.php';

// Start accepting requests
$router->dispatch();
?>

<?php
// Setup, configs etc.
require_once __DIR__.'/../vendor/autoload.php';

// Local utilities
require_once __DIR__.'/../liquidms/modules/ConfigModel.php';
require_once __DIR__.'/../liquidms/modules/NetgameModel.php';


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
include_once __DIR__.'/../liquidms/api.php';
include_once __DIR__.'/../liquidms/liquidendpoints.php';

// Start accepting requests
$router->dispatch();
?>

<?php
// Setup, configs etc.
require_once __DIR__.'/vendor/autoload.php';
use Klein\Klein;

// Main router object
$router = new Klein();

// Set API routes
include_once __DIR__.'/api.php';

// Start accepting requests
$router->dispatch();
?>

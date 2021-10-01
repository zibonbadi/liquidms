<?php
// Setup, configs etc.

// Set API routes
include_once __DIR__.'/api.php';
// Start accepting requests
LiquidMS\Router::run();
?>

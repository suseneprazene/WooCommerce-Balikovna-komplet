<?php

session_start();

require __DIR__ . "/../vendor/autoload.php";


$settings = require __DIR__ . "/../app/settings.php";


$app = new \Slim\App($settings);

// Set up Dependencies
require __DIR__ . "/../app/dependencies.php";

// Register Middleware
require __DIR__ . "/../app/middleware.php";

// Register routes
require __DIR__ . "/../app/routes.php";


$app->run();
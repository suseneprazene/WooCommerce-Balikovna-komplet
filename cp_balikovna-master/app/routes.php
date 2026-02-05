<?php

$app->get("/", "HomeController:home")->setName("home");

// import data
$app->get("/aktualizovat/", "ServiceController:syncData")->setName("syncData");

// API
$app->get("/api/hours/{branchId:[0-9]+}/", "HomeController:openingHours")->setName("openingHours");
$app->get("/api/search/", "HomeController:branches")->setName("branches");
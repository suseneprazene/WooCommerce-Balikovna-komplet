<?php

use Balikovna\Controllers\HomeController;
use Balikovna\Controllers\ServiceController;

$container = $app->getContainer();

// database
$setting = $container['settings'];
$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection($setting['database']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// views
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig(
        $container['settings']['view']['template_path'],
        $container['settings']['view']['twig']
    );

    $view->addExtension(new \Slim\Views\TwigExtension(
        $container->router,
        $container->request->getUri()
    ));

    return $view;
};

// CSRF Protection
$container['csrf'] = function () {
    return new \Slim\Csrf\Guard;
};

// controllers
$container['ServiceController'] = function ($container) {
    return new ServiceController($container);
};

$container['HomeController'] = function ($container) {
    return new HomeController($container);
};
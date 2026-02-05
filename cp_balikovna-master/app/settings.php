<?php

date_default_timezone_set('Europe/Prague');

return [
    "settings" => [
        "source_xml" => "http://napostu.ceskaposta.cz/vystupy/balikovny.xml",

        "determineRouteBeforeAppMiddleware" => true,
        "displayErrorDetails" => false,

        "database" => [
            "driver"    => "mysql",
            "host"      => "127.0.0.1",
            "port"      => "3306",
            "database"  => "cp_balikovna",
            "username"  => "root",
            "password"  => "",
            "charset"   => "utf8",
            "collation" => "utf8_unicode_ci",
            "prefix"    => "",
        ],

        "view" => [
            "template_path" => __DIR__ . "/../templates",
            "twig" => [
                "debug" => false,
            ],
        ],
    ],
];

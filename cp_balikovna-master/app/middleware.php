<?php

use Slim\Http\Request;
use Slim\Http\Response;

$app->add(new \Balikovna\Middleware\CsrfViewMiddleware($container));

$app->add(/**
 * @param Request $request
 * @param Response $response
 * @param callable $next
 * @return mixed
 */
    function (Request $request, Response $response, callable $next)
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        if ($path != "/" && substr($path, -1) != "/") {
            $uri = $uri->withPath($path . "/");
            if ($request->getMethod() == "GET") {
                return $response->withRedirect((string)$uri, 301);
            } else {
                return $next($request->withUri($uri), $response);
            }
        }
        return $next($request, $response);
    }
);

if (isset($container->csrf)) {
    $app->add($container->csrf);
}

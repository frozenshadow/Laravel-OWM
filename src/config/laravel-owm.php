<?php

return [
    'api_key' => '',                                                // visit: http://openweathermap.org/appid#get for more info.
    'routes_enabled' => true,                                       // If the routes have to be enabled.
    'httpClient' => 'Http\Adapter\Guzzle6\Client',                  // String classname of a PSR-17 compatible HTTP factory implementation
    'httpRequestFactory' => 'Http\Factory\Guzzle\RequestFactory',   // String classname of a PSR-18 compatible HTTP client implementation
    'cache' => app('cache.psr6'),                                   // PSR-6 compatible Cache implementation
];

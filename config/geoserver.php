<?php

return [
    'url' => env('GEOSERVER_URL', 'http://localhost:8080/geoserver/rest'),
    'username' => env('GEOSERVER_USER', 'admin'),
    'password' => env('GEOSERVER_PASS', 'geoserver'),
    'workspace' => env('GEOSERVER_WORKSPACE', 'myworkspace'),
    'default_srs' => env('GEOSERVER_DEFAULT_SRS', 'EPSG:4326'),
    'projection_policy' => env('GEOSERVER_PROJECTION_POLICY', 'REPROJECT_TO_DECLARED'),
    'recalculate_bounds' => filter_var(env('GEOSERVER_RECALCULATE_BOUNDS', true), FILTER_VALIDATE_BOOLEAN),
];

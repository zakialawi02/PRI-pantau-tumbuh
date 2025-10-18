<?php
return [
    'url' => env('GEOSERVER_URL', 'http://localhost:8080/geoserver/rest'),
    'username' => env('GEOSERVER_USER', 'admin'),
    'password' => env('GEOSERVER_PASS', 'geoserver'),
    'workspace' => env('GEOSERVER_WORKSPACE', 'myworkspace'),
    'wms_url' => env('GEOSERVER_WMS_URL', 'http://localhost:8080/geoserver/wms'),
    'default_srs' => env('GEOSERVER_DEFAULT_SRS', 'EPSG:4326'),
];

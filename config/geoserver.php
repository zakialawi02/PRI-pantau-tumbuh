<?php
return [
    'url' => env('GEOSERVER_URL', 'http://localhost:8080/geoserver/rest'),
    'username' => env('GEOSERVER_USER', 'admin'),
    'password' => env('GEOSERVER_PASS', 'geoserver'),
    'workspace' => env('GEOSERVER_WORKSPACE', 'myworkspace'),
    'wms_url' => env('GEOSERVER_WMS_URL', 'http://localhost:8080/geoserver/wms'),
    'default_srs' => env('GEOSERVER_DEFAULT_SRS', 'EPSG:4326'),
    'external_file_mode' => env('GEOSERVER_EXTERNAL_FILE_MODE', 'absolute'),
    'external_file_prefix' => env('GEOSERVER_EXTERNAL_FILE_PREFIX'),
    'external_file_base_path' => env('GEOSERVER_EXTERNAL_FILE_BASE_PATH'),
];

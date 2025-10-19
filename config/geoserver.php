<?php
return [
    'url' => env('GEOSERVER_URL', 'http://localhost:8080/geoserver/rest'),
    'username' => env('GEOSERVER_USER', 'admin'),
    'password' => env('GEOSERVER_PASS', 'geoserver'),
    'workspace' => env('GEOSERVER_WORKSPACE', 'myworkspace'),
    'wms_url' => env('GEOSERVER_WMS_URL', 'http://localhost:8080/geoserver/wms'),
    'default_srs' => env('GEOSERVER_DEFAULT_SRS', 'EPSG:4326'),

    /*
    |--------------------------------------------------------------------------
    | External file publication options
    |--------------------------------------------------------------------------
    |
    | When publishing GeoTIFF files via the GeoServer REST API the service
    | typically needs to know how to reference the file on disk. In some
    | environments (such as production) the absolute filesystem path used by
    | Laravel is not accessible from GeoServer and a different path reference
    | must be supplied (for example a path relative to the GeoServer data
    | directory).
    |
    | `file_path_mode` controls how the application rewrites the local path
    | before sending it to GeoServer. Supported values:
    |   - `absolute` (default): send the absolute filesystem path.
    |   - `relative_public_storage`: rewrite the path so that anything within
    |     Laravel's `storage/app/public` directory is mapped to the
    |     `file_relative_base` path (e.g. "../public/storage").
    |
    | `file_path_prefix` allows customising the prefix that is sent before the
    | computed path. By default GeoServer expects the `file:` prefix.
    |
    | `file_relative_base` is used with the `relative_public_storage` mode to
    | replace the public storage path segment.
    */
    'file_path_mode' => env('GEOSERVER_FILE_PATH_MODE', 'absolute'),
    'file_path_prefix' => env('GEOSERVER_FILE_PATH_PREFIX', 'file:'),
    'file_relative_base' => env('GEOSERVER_FILE_RELATIVE_BASE', '../public/storage'),
];

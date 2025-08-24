<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <!-- Judul -->
        <h1 class="mb-6 text-2xl font-bold">Checkout</h1>

        <!-- Container utama -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Form kiri -->
            <x-card class="md:order-0 order-2 space-y-4 md:col-span-2">
                <h2 class="border-b pb-2 text-lg font-semibold">Data Diri</h2>

                <form class="" id="form-ajuan" action="{{ route('checkout.payment') }}" method="post" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    @method('POST')
                    <!-- Nama -->
                    <div class="mb-3">
                        <x-input-label for="name">Nama</x-input-label>
                        <x-text-input id="name" name="name" value="{{ old('name') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <x-input-label for="email">Email</x-input-label>
                        <x-text-input id="email" name="email" value="{{ old('email') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <!-- Kontak -->
                    <div class="mb-3">
                        <x-input-label for="phone">Kontak Telepon</x-input-label>
                        <x-text-input id="phone" name="phone" value="{{ old('phone') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <!-- Total -->
                    <div class="mb-3">
                        <x-input-label for="amount">Total Harga</x-input-label>
                        <div id="amount">Rp. xxxx</div>

                    </div>

                    <!-- Informasi Bank -->
                    <h2 class="mb-3 border-b pb-2 text-lg font-semibold">Informasi Bank Pengirim</h2>

                    <!-- Nama Bank -->
                    <div class="mb-3">
                        <x-input-label for="sender_bank">Nama Bank Pengirim</x-input-label>
                        <select class="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring focus:ring-green-200">
                            <option>Pilih Bank</option>
                            <option>BCA</option>
                            <option>Mandiri</option>
                            <option>BNI</option>
                            <option>BRI</option>
                        </select>
                    </div>

                    <!-- Nama Pengirim -->
                    <div class="mb-3">
                        <x-input-label class="mb-1 block text-sm font-medium" for="sender_name_bank">Nama Pengirim</x-input-label>
                        <x-text-input id="sender_name_bank" name="sender_name_bank" value="{{ old('sender_name_bank') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('sender_name_bank')" />
                    </div>

                    <!-- No Rekening -->
                    <div class="mb-3">
                        <x-input-label class="mb-1 block text-sm font-medium">No. Rekening Pengirim</x-input-label>
                        <x-text-input id="sender_bank_number" name="sender_bank_number" value="{{ old('sender_bank_number') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('sender_bank_number')" />
                    </div>

                    <!-- Tombol -->
                    <div class="my-4 mb-3">
                        <x-button-primary class="w-full" type="submit">
                            Checkout
                        </x-button-primary>
                    </div>
                </form>
            </x-card>

            <!-- Map kanan -->
            <x-card class="order-1 space-y-4 self-start">
                <div>
                    <h2 class="border-b pb-2 text-lg font-semibold">Preview Map</h2>
                    <div class="mt-3 h-64 w-full overflow-hidden rounded-md border">
                        <div class="map" id="map"></div>
                        <div class="hidden md:block" id="mousePosition"></div>
                    </div>
                    <div class="mt-2 space-x-3 text-sm text-gray-600">
                        <span>Long: -</span>
                        <span>Lat: -</span>
                    </div>
                </div>
            </x-card>

        </div>
    </div>


    @push('css')
        <link href="https://cdn.jsdelivr.net/npm/ol@v10.6.0/ol.css" rel="stylesheet">

        <style>
            #map {
                width: 100%;
                height: 50vh;
            }
        </style>
    @endpush

    @push('javascript')
        <script src="https://cdn.jsdelivr.net/npm/ol@v10.6.0/dist/ol.js"></script>

        <script>
            let Map = ol.Map;
            let View = ol.View;
            let Source = ol.source.ImageTile;
            let OSM = ol.source.OSM;
            let TileLayer = ol.layer.Tile;
            let TileSource = ol.source.Tile;
            let XYZ = ol.source.XYZ;
            let Layer = ol.layer.WebGLTile;
            let {
                fromLonLat,
                toLonLat,
                Projection,
                useGeographic,
                getProjection,
                getTransform,
                addCoordinateTransforms,
                addProjection,
                transform,
            } = ol.proj;
            let LayerGroup = ol.layer.Group;
            let {
                Zoom,
                Attribution,
                OverviewMap,
                ScaleLine,
                MousePosition,
                FullScreen,
            } = ol.control;

            // Init View
            const view = new View({
                // projection: "EPSG:4326",
                center: ol.proj.fromLonLat([113.37706060700374, -1.1304744207092927]),
                zoom: 5.8,
                minZoom: 5,
                maxZoom: 19,
            });

            // BaseMap
            const World_Boundaries_and_Places = new TileLayer({
                source: new ol.source.XYZ({
                    url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}",
                    maxZoom: 18,
                    crossOrigin: "anonymous",
                }),
            });
            const World_Imagery = new TileLayer({
                source: new XYZ({
                    url: "https://services.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                    maxZoom: 18,
                    crossOrigin: "anonymous",
                }),
            });
            const World_Transportation = new TileLayer({
                source: new XYZ({
                    url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}",
                    maxZoom: 18,
                    crossOrigin: "anonymous",
                }),
            });

            const esriMapGroup = new LayerGroup({
                layers: [World_Imagery, World_Boundaries_and_Places, World_Transportation],
                visible: true,
            });

            // Mouse Position
            const mousePositionControl = new MousePosition({
                target: document.getElementById("mousePosition"),
                coordinateFormat: function(coordinate) {
                    const {
                        formattedLon,
                        formattedLat
                    } = coordinateFormatIndo(
                        coordinate,
                        "dd"
                    );

                    return (
                        "Long: " + formattedLon + " &nbsp&nbsp&nbsp  Lat: " + formattedLat
                    );
                },
                projection: "EPSG:4326",
                placeholder: "Long: - &nbsp&nbsp&nbsp  Lat: -",
                className: "ol-custom-mouse-position",
            });

            /**
             * Formats the given coordinate into a specific format for Indo coordinates.
             *
             * @param {Array<number>} coordinate - The coordinate to be formatted. It should be an array with two elements: [longitude, latitude].
             * @param {string} [format="dd"] - The format to use for the coordinate. It can be "dd" for decimal degrees, or "dms" for degrees, minutes, and seconds.
             * @return {Object} An object containing the formatted longitude and latitude.
             * @example
             * dd=> {"formattedLon": "112.74719° BT", "formattedLat": "7.26786° LS"}
             * or
             * dms=> {"formattedLon": "112° 47' 17.00\" BT", "formattedLat": "7° 24' 46.00\" LS"}
             */
            function coordinateFormatIndo(coordinate, format = "dd") {
                const lon = coordinate[0];
                const lat = coordinate[1];

                const lonDirection = lon < 0 ? "BB" : "BT";
                const latDirection = lat < 0 ? "LS" : "LU"; // LS: Lintang Selatan, LU: Lintang Utara

                if (format === "dms") {
                    const convertToDMS = (coord, direction) => {
                        const absoluteCoord = Math.abs(coord);
                        const degrees = Math.floor(absoluteCoord);
                        const minutes = Math.floor((absoluteCoord - degrees) * 60);
                        const seconds = (
                            (absoluteCoord - degrees - minutes / 60) *
                            3600
                        ).toFixed(2);
                        return `${degrees}° ${minutes}' ${seconds}" ${direction}`;
                    };
                    const formattedLon = convertToDMS(lon, lonDirection);
                    const formattedLat = convertToDMS(lat, latDirection);
                    return {
                        formattedLon,
                        formattedLat
                    };
                } else {
                    const formattedLon = `${Math.abs(lon).toFixed(5)}° ${lonDirection}`;
                    const formattedLat = `${Math.abs(lat).toFixed(5)}° ${latDirection}`;
                    return {
                        formattedLon,
                        formattedLat
                    };
                }
            }


            // Init To Canvas/View
            let map = new Map({
                target: "map",

                layers: [
                    new LayerGroup({
                        layers: [esriMapGroup],
                    }),
                ],

                view: view,


                controls: [
                    new Zoom(),
                    new FullScreen(),
                    mousePositionControl,
                    new ScaleLine(),
                    new Attribution(),
                ],
            });

            const geojsonData = JSON.parse(`{!! $data['geometry'] !!}`);

            const geojsonFormat = new ol.format.GeoJSON();
            const vectorSource = new ol.source.Vector({
                features: geojsonFormat.readFeatures(geojsonData, {
                    featureProjection: "EPSG:3857",
                }),
            });
            const vectorLayer = new ol.layer.Vector({
                source: vectorSource,
                style: new ol.style.Style({
                    stroke: new ol.style.Stroke({
                        color: "rgba(255, 0, 0, 1)",
                        width: 2,
                    }),
                    fill: new ol.style.Fill({
                        color: "rgba(255, 0, 0, 0.2)",
                    }),
                }),
            });
            map.addLayer(vectorLayer);
            const extent = vectorSource.getExtent();
            map.getView().fit(extent, {
                padding: [100, 100, 100, 100],
                duration: 1000,
            });
        </script>
    @endpush
</x-app-front-layout>

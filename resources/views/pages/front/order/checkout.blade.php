@section('title', $data['title'] ?? '')

<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <!-- Judul -->
        <h1 class="mb-6 text-2xl font-bold">Checkout</h1>

        <!-- Container utama -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Form kiri -->
            <x-card class="md:order-0 order-2 space-y-4 md:col-span-2">
                <h2 class="border-border border-b pb-2 text-lg font-semibold">Personal data</h2>

                <form class="" id="form-ajuan" action="{{ route('checkout.payment') }}" method="post" enctype="multipart/form-data" autocomplete="off">
                    @csrf
                    @method('POST')

                    <input id="order_id" name="order_id" type="hidden" value="{{ request('id') ?? '' }}" />

                    <!-- Nama -->
                    <div class="mb-3">
                        <x-input-label for="name">Name</x-input-label>
                        <x-text-input id="name" name="name" value="{{ Auth::user()->name }}" readonly size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <x-input-label for="email">Email</x-input-label>
                        <x-text-input id="email" name="email" value="{{ Auth::user()->email }}" readonly size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <!-- Kontak -->
                    <div class="mb-3">
                        <x-input-label for="phone">Phone</x-input-label>
                        <x-text-input id="phone" name="phone" value="{{ old('phone') }}" size="small" required />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <!-- Plans -->
                    <div class="mb-3">
                        <x-input-label for="plan">Plans</x-input-label>
                        <div class="border-primary/80 rounded-md border bg-gray-50 p-3">
                            <div class="font-medium">{{ $data['plan']['name'] ?? 'Plan Name' }}</div>
                            <div class="text-sm text-gray-600">{{ $data['price_currency'] ?? 'USD' }} {{ $data['price_per_hectare'] }}/ha</div>
                        </div>
                    </div>
                    <!-- Area -->
                    <div class="mb-3">
                        <x-input-label for="area_hectares">Area</x-input-label>
                        <div id="amount">{{ $data['area_hectares'] }} ha</div>
                    </div>
                    <!-- Total -->
                    <div class="mb-3">
                        <x-input-label for="amount">Total Price</x-input-label>
                        <div id="amount">{{ $data['total_price'] }} {{ $data['price_currency'] ?? 'USD' }}</div>
                    </div>

                    <!-- Informasi Bank -->
                    <h2 class="border-border mb-3 border-b pb-2 text-lg font-semibold">Payment</h2>

                    <div class="mb-3">
                        <x-input-label for="payment_method">Payment Method</x-input-label>
                        <select class="focus:ring-primary ring-primary/80 border-ring w-full rounded-md border px-3 py-2 focus:outline-none focus:ring" id="payment_method" name="payment_method">
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
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
                    <h2 class="border-border border-b pb-2 text-lg font-semibold">Preview Map</h2>
                    <div class="mt-3 h-64 w-full overflow-hidden rounded-md border">
                        <div class="map" id="map"></div>
                        <div class="hidden md:block" id="mousePosition"></div>
                    </div>
                    <div class="mt-2 space-x-3 text-sm text-gray-600" id="coordinateDisplay"> </div>
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
                    attributions: '© <a href="https://www.esri.com/">Esri</a>',
                }),
            });
            const World_Imagery = new TileLayer({
                source: new XYZ({
                    url: "https://services.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                    maxZoom: 18,
                    crossOrigin: "anonymous",
                    attributions: '© <a href="https://www.esri.com/">Esri</a>, © <a href="https://www.digitalglobe.com/">DigitalGlobe</a>, © <a href="http://www.geoeye.com/">GeoEye</a>, © <a href="https://www.i-cubed.com/">i-cubed</a>, © <a href="https://www.usda.gov/">USDA</a>, © <a href="https://www.usgs.gov/">USGS</a>, © <a href="https://www.aerogrid.com/">AeroGRID</a>, © <a href="https://www.igncorporation.com/">IGN</a>, and the GIS User Community',
                }),
            });
            const World_Transportation = new TileLayer({
                source: new XYZ({
                    url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}",
                    maxZoom: 18,
                    crossOrigin: "anonymous",
                    attributions: '© <a href="https://www.esri.com/">Esri</a>',
                }),
            });

            const esriMapGroup = new LayerGroup({
                layers: [World_Imagery, World_Boundaries_and_Places, World_Transportation],
                visible: true,
            });

            // Mouse Position Control
            const mousePositionControl = new MousePosition({
                target: document.getElementById("coordinateDisplay"),
                coordinateFormat: function(coordinate) {
                    const [lon, lat] = coordinate;
                    const formattedLon = lon.toFixed(6);
                    const formattedLat = lat.toFixed(6);
                    return (
                        "Long: " + formattedLon + " &nbsp&nbsp&nbsp  Lat: " + formattedLat
                    );
                },
                projection: "EPSG:4326",
                placeholder: "Long: - &nbsp&nbsp&nbsp  Lat: -",
                className: "ol-custom-mouse-position",
            });

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

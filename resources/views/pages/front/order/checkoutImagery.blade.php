@section('title', $data['title'] ?? '')

<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <!-- Judul -->
        <div class="mb-8 text-center">
            <h1 class="text-foreground text-3xl font-bold">Complete Your Imagery Order</h1>
            <p class="text-base-content-muted mt-2">
                Review your imagery order details and complete the payment using credit points
            </p>
        </div>

        <!-- Container utama -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Mobile: Field Preview (Above) | Desktop: Right Column -->
            <div class="order-1 lg:order-2 lg:col-span-1">
                <x-card class="sticky top-14 space-y-4">
                    <div>
                        <h2 class="border-border text-foreground border-b pb-3 text-xl font-semibold">Imagery Order Details</h2>

                        <!-- Field Preview for imagery orders -->
                        <div class="mt-4 h-80 w-full overflow-hidden rounded-lg border">
                            <div class="map" id="map"></div>
                            <div class="hidden md:block" id="mousePosition"></div>
                        </div>
                        <div class="text-base-content-muted mt-3 space-x-3 text-sm" id="coordinateDisplay"> </div>

                        <!-- Order Summary -->
                        <div class="mt-4 space-y-3">
                            <div class="border-border flex items-center justify-between border-b pb-2">
                                <div>
                                    <h3 class="text-foreground font-medium">{{ $data['name_feature'] ?? 'Custom Field' }}</h3>
                                    <p class="text-base-content-muted text-sm">{{ Number::format($data['area_hectares'], locale: app()->getLocale()) }} Hectares</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-foreground text-lg">{{ Number::format($data['credit_cost'], locale: app()->getLocale()) }} Credits</p>
                                </div>
                            </div>

                            <div class="flex justify-between pt-2">
                                <h3 class="text-foreground text-lg font-semibold">Total</h3>
                                <p class="text-primary text-xl font-bold">{{ Number::format($data['credit_cost'], locale: app()->getLocale()) }} Credit Points</p>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Form kiri -->
            <div class="order-2 lg:order-1 lg:col-span-2">
                <x-card class="space-y-6">
                    <div>
                        <h2 class="border-border text-foreground border-b pb-3 text-xl font-semibold">Personal Information</h2>

                        <form class="mt-4" id="form-ajuan" action="{{ route('processImageryCheckout') }}" method="post" enctype="multipart/form-data" autocomplete="off">
                            @csrf
                            @method('POST')

                            <input id="order_id" name="order_id" type="hidden" value="{{ request('id') ?? '' }}" />
                            <input name="name_feature" type="hidden" value="{{ $data['name_feature'] ?? '' }}" />
                            <input name="geometry" type="hidden" value="{{ $data['geometry'] ?? '' }}" />
                            <input name="area_hectares" type="hidden" value="{{ $data['area_hectares'] ?? '' }}" />

                            <div class="grid grid-cols-1 gap-4">
                                <!-- Nama -->
                                <div>
                                    <x-input-label for="name">Full Name</x-input-label>
                                    <x-text-input id="name" name="name" value="{{ old('name', Auth::user()->name) }}" size="normal" readonly required />
                                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                                </div>

                                <!-- Email -->
                                <div>
                                    <x-input-label for="email">Email Address</x-input-label>
                                    <x-text-input id="email" name="email" value="{{ old('email', Auth::user()->email) }}" size="normal" readonly required />
                                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                                </div>

                                <!-- Kontak -->
                                <div>
                                    <x-input-label for="phone">Phone Number</x-input-label>
                                    <x-text-input id="phone" name="phone" value="{{ old('phone', Auth::user()->phone ?? '') }}" size="normal" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                                </div>
                            </div>

                            <!-- Order Summary -->
                            <div class="mt-6">
                                <h2 class="border-border text-foreground border-b pb-1 text-xl font-semibold">Order Summary</h2>

                                <div class="mt-3 space-y-3">
                                    <!-- Imagery Order Summary -->
                                    <div class="border-border flex items-center justify-between border-b pb-1">
                                        <div>
                                            <h3 class="text-foreground font-medium">Field name: <span class="text-base-content-muted">{{ $data['name_feature'] ?? 'Custom Field' }}</span></h3>
                                            <p class="text-foreground text-sm">Area: <span class="text-base-content-muted">{{ Number::format($data['area_hectares'], locale: app()->getLocale()) }} Hectares</span></p>
                                            <p class="text-foreground text-sm">Rate: <span class="text-base-content-muted">{{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), 2, locale: app()->getLocale()) }} Credit Points per hectare</span></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-foreground text-lg">{{ Number::format($data['credit_cost'], locale: app()->getLocale()) }} Credits</p>
                                        </div>
                                    </div>

                                    <!-- Total -->
                                    <div class="flex justify-between pt-2">
                                        <h3 class="text-foreground text-lg font-semibold">Total</h3>
                                        <p class="text-primary text-xl font-bold">{{ Number::format($data['credit_cost'], locale: app()->getLocale()) }} Credit Points</p>
                                    </div>

                                    <!-- User Current Credits -->
                                    <div class="border-border flex justify-between border-b pb-1">
                                        <div>
                                            <h3 class="text-foreground font-medium">Your Credits</h3>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-foreground text-lg">{{ Number::format(Auth::user()->current_credits, locale: app()->getLocale()) }} Credits</p>
                                        </div>
                                    </div>

                                    <!-- Remaining Credits After Purchase -->
                                    @php
                                        $remainingCredits = Auth::user()->current_credits - $data['credit_cost'];
                                    @endphp
                                    <div class="flex justify-between pt-2">
                                        <h3 class="text-foreground text-lg font-semibold">Remaining Credits</h3>
                                        <p class="text-{{ $remainingCredits >= 0 ? 'primary' : 'error' }} text-xl font-bold">{{ Number::format($remainingCredits, locale: app()->getLocale()) }} Credits</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol -->
                            <div class="mt-8">
                                @if (Auth::user()->current_credits >= $data['credit_cost'])
                                    <x-button-primary class="w-full py-3 text-base" type="submit">
                                        Complete Imagery Order
                                    </x-button-primary>
                                @else
                                    <x-button-primary class="w-full py-3 text-base" type="button" disabled>
                                        Insufficient Credit Points
                                    </x-button-primary>
                                    <div class="mt-3 text-center">
                                        <a class="text-primary hover:underline" href="{{ route('admin.purchase-credits') }}">
                                            Purchase More Credits
                                        </a>
                                    </div>
                                @endif

                                <p class="text-base-content-muted mt-3 text-center text-sm">
                                    By completing your order, you agree to our <a class="text-primary hover:underline" href="{{ route('terms-of-service') }}">Terms of Service</a> and <a class="text-primary hover:underline" href="{{ route('privacy-policy') }}">Privacy Policy</a>.
                                </p>

                                <div class="mt-4 text-center">
                                    <a class="text-primary text-sm hover:underline" href="{{ route('appMap') }}">
                                        <i class="ri-arrow-left-line mr-1"></i> Back to Map
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    @push('css')
        <link href="https://cdn.jsdelivr.net/npm/ol@v10.6.0/ol.css" rel="stylesheet">

        <style>
            #map {
                width: 100%;
                height: 100%;
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
                    const formattedLon = formatNumber(lon, 6, document.documentElement.lang);
                    const formattedLat = formatNumber(lat, 6, document.documentElement.lang);
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
                padding: [50, 50, 50, 50],
                duration: 1000,
            });
        </script>
    @endpush
</x-app-front-layout>

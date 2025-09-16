@section('title', $data['title'] ?? '')

<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <!-- Judul -->
        <div class="mb-8 text-center">
            <h1 class="text-foreground text-3xl font-bold">Complete Your Order</h1>
            <p class="text-base-content-muted mt-2">Review your order details and complete the payment</p>
        </div>

        <!-- Container utama -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Mobile: Field Preview (Above) | Desktop: Right Column -->
            <div class="order-1 lg:order-2 lg:col-span-1">
                <x-card class="sticky top-14 space-y-4">
                    <div>
                        <h2 class="border-border text-foreground border-b pb-3 text-xl font-semibold">Field Preview</h2>
                        <div class="mt-4 h-80 w-full overflow-hidden rounded-lg border">
                            <div class="map" id="map"></div>
                            <div class="hidden md:block" id="mousePosition"></div>
                        </div>
                        <div class="text-base-content-muted mt-3 space-x-3 text-sm" id="coordinateDisplay"> </div>
                    </div>
                </x-card>
            </div>

            <!-- Form kiri -->
            <div class="order-2 lg:order-1 lg:col-span-2">
                <x-card class="space-y-6">
                    <div>
                        <h2 class="border-border text-foreground border-b pb-3 text-xl font-semibold">Personal Information</h2>

                        <form class="mt-4" id="form-ajuan" action="{{ route('checkout.payment') }}" method="post" enctype="multipart/form-data" autocomplete="off">
                            @csrf
                            @method('POST')

                            <input id="order_id" name="order_id" type="hidden" value="{{ request('id') ?? '' }}" />

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Nama -->
                                <div class="md:col-span-2">
                                    <x-input-label for="name">Full Name</x-input-label>
                                    <x-text-input id="name" name="name" value="{{ Auth::user()->name }}" readonly size="normal" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                                </div>

                                <!-- Email -->
                                <div class="md:col-span-2">
                                    <x-input-label for="email">Email Address</x-input-label>
                                    <x-text-input id="email" name="email" value="{{ Auth::user()->email }}" readonly size="normal" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                                </div>

                                <!-- Kontak -->
                                <div class="md:col-span-2">
                                    <x-input-label for="phone">Phone Number</x-input-label>
                                    <x-text-input id="phone" name="phone" value="{{ old('phone') }}" size="normal" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                                </div>
                            </div>

                            <!-- Order Summary -->
                            <div class="mt-6">
                                <h2 class="border-border text-foreground border-b pb-1 text-xl font-semibold">Order Summary</h2>

                                <div class="mt-3 space-y-3">
                                    <!-- Plans -->
                                    <div class="border-border flex justify-between border-b pb-1">
                                        <div>
                                            <h3 class="text-foreground font-medium">{{ $data['plan']['name'] ?? 'Plan Name' }}</h3>
                                            <p class="text-base-content-muted text-sm">{{ $data['price_currency'] ?? 'USD' }} {{ $data['price_per_hectare'] }}/ha</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-foreground font-medium">{{ $data['area_hectares'] }} ha</p>
                                            <p class="text-foreground text-lg">{{ $data['price_currency'] ?? 'USD' }} {{ $data['total_price'] }} </p>
                                        </div>
                                    </div>

                                    <!-- Tax -->
                                    <div class="border-border flex justify-between border-b pb-1">
                                        <div>
                                            <h3 class="text-foreground font-medium">Tax</h3>
                                            <p class="text-base-content-muted text-sm">0%</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-foreground text-lg">{{ $data['price_currency'] ?? 'USD' }} 0.00</p>
                                        </div>
                                    </div>

                                    <!-- Total -->
                                    <div class="flex justify-between pt-2">
                                        <h3 class="text-foreground text-lg font-semibold">Total</h3>
                                        <p class="text-primary text-xl font-bold">{{ $data['price_currency'] ?? 'USD' }} {{ $data['total_price'] }} </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="mt-6">
                                <h2 class="border-border text-foreground border-b pb-3 text-xl font-semibold">Payment Method</h2>

                                <div class="mt-4 space-y-3">
                                    <label class="border-border hover:bg-muted/50 flex items-center rounded-lg border p-4">
                                        <input class="text-primary focus:ring-primary h-5 w-5 rounded-full border-gray-300 focus:ring-2" name="payment_method" type="radio" value="bank_transfer" checked>
                                        <div class="ml-4">
                                            <span class="text-foreground block text-base font-medium">Bank Transfer (Indonesia Bank)</span>
                                            <span class="text-base-content-muted block text-sm">Pay directly from your bank account</span>
                                        </div>
                                    </label>

                                    <label class="border-border hover:bg-muted/50 flex items-center rounded-lg border p-4">
                                        <input class="text-primary focus:ring-primary h-5 w-5 rounded-full border-gray-300 focus:ring-2" name="payment_method" type="radio" value="paypal">
                                        <div class="ml-4">
                                            <span class="text-foreground block text-base font-medium">PayPal</span>
                                            <span class="text-base-content-muted block text-sm">Pay with your PayPal account</span>
                                        </div>
                                    </label>

                                    <label class="border-border flex items-center rounded-lg border p-4 opacity-50">
                                        <input class="text-primary focus:ring-primary h-5 w-5 rounded-full border-gray-300 focus:ring-2" name="payment_method" type="radio" value="stripe" disabled>
                                        <div class="ml-4">
                                            <span class="text-foreground block text-base font-medium">Credit Card (Stripe)</span>
                                            <span class="text-base-content-muted block text-sm">Pay with credit card</span>
                                            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">Disabled</span>
                                        </div>
                                    </label>

                                    <label class="border-border flex items-center rounded-lg border p-4 opacity-50">
                                        <input class="text-primary focus:ring-primary h-5 w-5 rounded-full border-gray-300 focus:ring-2" name="payment_method" type="radio" value="manual" disabled>
                                        <div class="ml-4">
                                            <span class="text-foreground block text-base font-medium">Manual Payment</span>
                                            <span class="text-base-content-muted block text-sm">Pay manually (for testing)</span>
                                            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">Disabled</span>
                                        </div>
                                    </label>
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                            </div>

                            <!-- Additional fields for bank transfer -->
                            <div class="mt-6 hidden" id="bankTransferFields">
                                <h3 class="border-border text-foreground border-b pb-3 text-lg font-semibold">Bank Transfer Details</h3>
                                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label for="bank_name">Bank Name</x-input-label>
                                        <x-text-input id="bank_name" name="bank_name" value="{{ old('bank_name') }}" size="normal" />
                                        <x-input-error class="mt-2" :messages="$errors->get('bank_name')" />
                                    </div>
                                    <div>
                                        <x-input-label for="account_name">Account Name</x-input-label>
                                        <x-text-input id="account_name" name="account_name" value="{{ old('account_name') }}" size="normal" />
                                        <x-input-error class="mt-2" :messages="$errors->get('account_name')" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-input-label for="account_number">Account Number</x-input-label>
                                        <x-text-input id="account_number" name="account_number" value="{{ old('account_number') }}" size="normal" />
                                        <x-input-error class="mt-2" :messages="$errors->get('account_number')" />
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol -->
                            <div class="mt-8">
                                <x-button-primary class="w-full py-3 text-base" type="submit">
                                    Complete Payment
                                </x-button-primary>

                                <p class="text-base-content-muted mt-3 text-center text-sm">
                                    By completing your payment, you agree to our <a class="text-primary hover:underline" href="#">Terms of Service</a> and <a class="text-primary hover:underline" href="#">Privacy Policy</a>.
                                </p>
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
            // Show/hide bank transfer fields based on payment method selection
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const bankTransferFields = document.getElementById('bankTransferFields');
                    if (this.value === 'bank_transfer') {
                        bankTransferFields.classList.remove('hidden');
                    } else {
                        bankTransferFields.classList.add('hidden');
                    }
                });
            });

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
                padding: [50, 50, 50, 50],
                duration: 1000,
            });
        </script>
    @endpush
</x-app-front-layout>

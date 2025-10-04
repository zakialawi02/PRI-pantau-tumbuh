@section('title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-map-layout class="flex h-screen w-screen flex-col overflow-hidden">
    <!-- HEADER -->
    <header class="bg-background border-foreground/5 flex h-12 items-center justify-between border-b-2 px-4">
        <h1 class="text-primary text-sm font-bold">🌱 PantauTumbuh Dashboard</h1>
        <div class="flex items-center space-x-3">
            <!-- Nav Menu -->
            <div class="text-foreground bg-background z-51 fixed inset-0 hidden w-full flex-col items-center justify-center space-y-6 whitespace-nowrap text-center uppercase opacity-0 transition-all duration-500 ease-in-out" id="navbar">

                <!-- Nav Menu -->
                <x-nav-menu :mobile="false" />
            </div>

            <button class="hover:text-accent z-51 text-3xl transition-transform duration-300 hover:scale-110" id="navbar-toggle">
                <i class="ri-menu-line transition-all duration-300" id="menu-icon"></i>
                <i class="ri-close-line hidden transition-all duration-300" id="close-icon"></i>
            </button>
        </div>
    </header>


    <!-- MAIN LAYOUT -->
    <div class="flex flex-1 overflow-hidden">
        <!-- DESKTOP SIDEBAR -->
        <aside class="bg-background hidden shadow-lg md:flex md:w-20 md:flex-col md:items-center md:py-4">
            <nav class="flex flex-1 flex-col items-center space-y-6">
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('data-panel', this)">
                    <span class="text-xl">📡</span>
                    <span>My Data</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('satellite-panel', this)">
                    <span class="text-xl">🛰️</span>
                    <span>Satellite</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('fields-panel', this)">
                    <span class="text-xl">🌾</span>
                    <span>Fields</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('seasons-panel', this)">
                    <span class="text-xl">📅</span>
                    <span>Seasons</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('settings-panel', this)">
                    <span class="text-xl">⚙️</span>
                    <span>Settings</span>
                </button>
            </nav>
            <div class="text-foreground/70 mt-auto text-xs">© 2025</div>
        </aside>

        <!-- MOBILE SIDEBAR HORIZONTAL -->
        <div class="fixed left-0 top-11 z-40 mx-auto w-full whitespace-nowrap bg-transparent px-4 py-2 md:hidden">
            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute left-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-left">
                <i class="ri-arrow-left-s-line text-lg"></i>
            </button>

            <div class="flex space-x-2 overflow-x-hidden scroll-smooth" id="scroll-container">
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('data-panel', this)">
                    <span class="text-xl">📡</span>
                    <span>My Data</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('satellite-panel', this)">
                    <span class="text-xl">🛰️</span>
                    <span>Satellite</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('fields-panel', this)">
                    <span class="text-xl">🌾</span>
                    <span>Fields</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('seasons-panel', this)">
                    <span class="text-xl">📅</span>
                    <span>Seasons</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('settings-panel', this)">
                    <span class="text-xl">⚙️</span>
                    <span>Settings</span>
                </button>
            </div>

            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute right-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-right">
                <i class="ri-arrow-right-s-line text-lg"></i>
            </button>
        </div>


        <!-- PANEL WRAPPER -->
        <div class="bg-background border-foreground/20 fixed bottom-0 left-0 z-50 max-h-[50%] w-full translate-y-full overflow-y-auto rounded-t-xl opacity-0 transition-all duration-500 ease-in-out md:relative md:max-h-full md:w-0 md:translate-y-0 md:overflow-hidden md:rounded-none md:border-l-2 md:opacity-100" id="panel-wrapper">

            <!-- ========== MY DATA PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="data-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">📡 My Data</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <!-- content -->
                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    @auth
                        @forelse ($activeFieldAreas as $fieldArea)
                            {{-- <div>
                                <label class="border-foreground/10 has-checked:border-muted/80 has-checked:bg-muted/60 has-checked:ring-1 has-checked:ring-muted/80 bg-neutral/80 shadow-xs {{ $fieldArea->subscriptions->status !== 'active' ? 'opacity-50 cursor-not-allowed' : 'hover:bg-muted/50 cursor-pointer' }} flex items-center gap-3 rounded-xl border p-3 text-base font-medium transition-colors" for="fieldArea-{{ $fieldArea->id }}">
                                    <input class="text-primary focus:ring-primary h-3 w-3 rounded border-gray-300" name="fieldArea" type="checkbox" {{ $fieldArea->subscriptions->status !== 'active' ? 'disabled' : "id=fieldArea-{$fieldArea->id} value=fieldArea-{$fieldArea->id}" }} />
                                    <div class="flex w-full items-start justify-between">
                                        <div>
                                            <h4 class="text-base font-semibold">{{ ucwords($fieldArea->name) ?? 'Unnamed Field' }}</h4>
                                            <p class="text-foreground/70 text-sm">
                                                {{ number_format($fieldArea->area_ha, 2) }} ha
                                            </p>
                                        </div>

                                        <div class="flex flex-col items-end">
                                            <span class="text-background @if ($fieldArea->subscriptions->status == 'active') bg-success/70
                                                        @elseif(in_array($fieldArea->subscriptions->status, ['suspended', 'cancelled', 'expired']))
                                                            bg-destructive/70
                                                        @else
                                                            bg-muted/70 @endif mb-1 inline-flex rounded-full px-2 py-1 text-xs font-medium">
                                                {{ str_replace('_', ' ', $fieldArea->subscriptions->status) }}
                                            </span>
                                            <button class="text-foreground/50 hover:text-foreground hover:bg-secondary bg-secondary/60 flex items-center gap-1 rounded-xl p-1 text-sm" title="Zoom to field">
                                                <i class="ri-zoom-in-line"></i>
                                                <span class="sr-only">Zoom</span>
                                            </button>
                                        </div>
                                    </div>
                                </label>
                            </div> --}}
                        @empty
                            <div class="flex flex-col items-center justify-center py-2 text-center">
                                <div class="mb-2">
                                    <div class="bg-muted mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                                        <i class="ri-database-2-line text-foreground/80 text-3xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-foreground/70 mb-2 text-lg font-semibold">No Data Available</h3>
                                <p class="text-foreground/50 mb-4 px-4 text-sm">
                                    You don't have any satellite imagery data yet. Purchase satellite imagery to start monitoring your crops and analyzing plant stress using PRI.
                                </p>
                                <x-button-primary id="buySatelliteBtn" type="button" size="small">
                                    <i class="ri-shopping-cart-line"></i>
                                    <span>Buy Satellite Imagery</span>
                                </x-button-primary>
                            </div>
                        @endforelse
                    @else
                        <div class="flex flex-col items-center justify-center py-3 text-center">
                            <div class="mb-3">
                                <div class="bg-muted mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                                    <i class="ri-database-2-line text-foreground/80 text-3xl"></i>
                                </div>
                            </div>
                            <h3 class="text-foreground/70 mb-2 text-lg font-semibold">No Data Available</h3>
                            <p class="text-foreground/50 mb-4 px-4 text-sm">
                                You don't have any satellite imagery data yet. Purchase satellite imagery to start monitoring your crops and analyzing plant stress using PRI.
                            </p>
                            <x-button-primary type="button" onclick="window.location.href='{{ route('login') }}'" size="small">
                                <i class="ri-login-box-line"></i>
                                <span>Login to View your or Buy Imagery</span>
                            </x-button-primary>
                        </div>
                    @endauth
                </div>

                <!-- sticky bottom panel -->
                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-foreground/70 text-sm">
                            @auth
                                {{ $activeFieldAreas->where('subscriptions.status', 'active')->count() ?? 0 }} field(s) active
                            @else
                                <span>Login to access your fields</span>
                            @endauth
                        </div>
                        <div class="text-foreground/50 text-xs">
                            Last updated: null
                        </div>
                    </div>
                    @auth
                        @if (!isset($activeFieldAreas) || $activeFieldAreas->count() > 0)
                            <x-button-primary id="buySatelliteBtn" type="button" size="small">
                                <i class="ri-shopping-cart-line"></i>
                                <span>Buy Satellite Imagery</span>
                            </x-button-primary>
                        @endif
                    @endauth
                </div>
            </section>

            <!-- ========== SATELLITE PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="satellite-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🛰️ Satellite Imagery</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <p>Isi panel Satellite Imagery.</p>
                    <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Quos, earum repellendus error saepe vitae deserunt doloribus officiis...</p>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <p class="text-foreground/70 text-sm">Footer Panel - Satellite</p>
                </div>
            </section>

            <!-- ========== FIELDS PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="fields-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🌾 Fields</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <p>Kelola data lahan dan batas poligon dummy.</p>
                    <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Atque aliquid doloremque, libero magni, voluptates soluta officiis tempore...</p>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <p class="text-foreground/70 text-sm">Footer Panel - Fields</p>
                </div>
            </section>

            <!-- ========== SEASONS PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="seasons-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">📅 Seasons</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <p>Timeline musim tanam dummy — scrollable content panjang.</p>
                    <p>Lorem ipsum dolor sit amet consectetur, adipisicing elit. Eum voluptates vel omnis nesciunt fuga! Ad sint dolor libero...</p>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <p class="text-foreground/70 text-sm">Footer Panel - Seasons</p>
                </div>
            </section>

            <!-- ========== SETTINGS PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="settings-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">⚙️ Settings</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <p>Konfigurasi user dan preferensi dummy.</p>
                    <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Hic itaque atque molestiae magnam iure facere voluptatum, recusandae...</p>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <p class="text-foreground/70 text-sm">Footer Panel - Settings</p>
                </div>
            </section>
        </div>

        <!-- MAP CONTAINER -->
        <div class="bg-background relative flex-1 transition-all duration-300 ease-in-out" id="map-container">
            <div class="h-full w-full" id="map"></div>

            <!-- Search Bar -->
            <div class="absolute left-2 right-2 top-12 flex w-1/2 items-center justify-between md:w-1/3">
                <x-text-input class="p-1" type="text" size="small" placeholder="Search..." />
            </div>

            <!-- Right Buttons -->
            <div class="absolute right-2 top-1/2 flex -translate-y-1/2 flex-col space-y-1 text-base md:text-lg">
                <button class="bg-neutral hover:bg-muted rounded px-2 py-1 text-xl font-bold transition-colors" title="Zoom In" onclick="zoomIn()">
                    +
                    <span class="sr-only">Zoom In</span>
                </button>
                <button class="bg-neutral hover:bg-muted rounded px-2 py-1 text-xl font-bold transition-colors" title="Zoom Out" onclick="zoomOut()">
                    –
                    <span class="sr-only">Zoom Out</span>
                </button>
                <button class="bg-neutral hover:bg-muted rotate-180 rounded px-2 py-1 text-xl font-bold transition-colors" id="minimapToggleBtn" title="Toggle Minimap" onclick="toggleMinimap(this)">
                    <i class="ri-arrow-left-double-line"></i>
                    <span class="sr-only">Toggle Minimap</span>
                </button>
            </div>

            <!-- * Bottom Buttons * -->
            <!-- Basemap Buttons -->
            <div class="absolute bottom-8 left-0 z-40 flex items-end space-x-2 text-xs md:left-2 md:text-base">
                <div class="basemap-switcher font-medium">
                    <div class="trigger-basemap font-bold" onclick="toggleOptions()">
                        <img id="active-basemap" src="{{ asset('assets/img/icon/here_satelliteday.png') }}" alt="Active Basemap" />
                        <span class="block">Basemap</span>
                    </div>
                    <div class="basemap-options">
                        <label class="basemap-option">
                            <input name="basemap" type="radio" value="bing" checked onclick="setBasemap('bing', this)" />
                            <img src="{{ asset('assets/img/icon/here_satelliteday.png') }}" alt="Satellite" />
                            <span>Satellite</span>
                        </label>
                        <label class="basemap-option">
                            <input name="basemap" type="radio" value="mapbox" onclick="setBasemap('mapbox', this)" />
                            <img src="{{ asset('assets/img/icon/here_normalday.png') }}" alt="Mapbox" />
                            <span>Street Mapbox</span>
                        </label>
                        <label class="basemap-option">
                            <input name="basemap" type="radio" value="osm" onclick="setBasemap('osm', this)" />
                            <img src="{{ asset('assets/img/icon/openstreetmap_mapnik.png') }}" alt="OpenStreet" />
                            <span>OpenStreet Map</span>
                        </label>
                        <label class="basemap-option">
                            <input name="basemap" type="radio" value="esriTerrain" onclick="setBasemap('esriTerrain', this)" />
                            <img src="{{ asset('assets/img/icon/esri_worldterrain.png') }}" alt="Esri Terrain" />
                            <span>Esri Terrain</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Info map controls -->
            <div class="absolute bottom-11 left-0 flex items-end space-x-2 text-xs md:left-2 md:text-base">
                <div class="relative hidden md:block" id="mousePosition"></div>
                <div class="relative -mb-2" id="scaleline"></div>
            </div>

            <!-- Bottom Date Selector -->
            <div class="absolute bottom-1 left-2 flex flex-wrap space-x-1 text-xs md:text-sm">
                <div class="bg-muted flex space-x-1 rounded-md p-1">
                    <button class="bg-neutral rounded px-1 py-0.5">1M</button>
                    <button class="bg-neutral rounded px-1 py-0.5">3M</button>
                    <button class="bg-neutral rounded px-1 py-0.5">6M</button>
                    <button class="bg-neutral rounded px-1 py-0.5">1Y</button>
                </div>

                <button class="bg-neutral rounded px-1 py-0.5">May 2025</button>
                <button class="bg-neutral rounded px-1 py-0.5">Jun 2025</button>
                <button class="bg-neutral rounded px-1 py-0.5">Jul 2025</button>
                <button class="bg-primary rounded px-1 py-0.5">Aug 2025</button>
            </div>
        </div>


        <!-- Right/Overlay Panel -->
        <div class="bg-background absolute bottom-0 left-0 z-50 hidden max-h-[60%] w-full max-w-full overflow-hidden rounded-t-xl shadow-xl transition-all duration-300 ease-in-out md:bottom-auto md:left-auto md:right-2 md:top-1/2 md:max-w-[30rem] md:-translate-y-1/2 md:transform md:rounded-xl" id="buyingPanel">
            <div class="flex h-full w-full min-w-full flex-col p-2" id="drawer-sidebar-left-panel1-label">
                <!-- Header drawer -->
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Purchase a field</h2>
                    <button class="hover:text-primary/80 close-panel-btn text-foreground/50" id="buyingPanelCloseBtn" data-drawer-hide="drawer-sidebar-left-panel1" type="button">✕</button>
                </div>
                <!-- Drawer content -->
                <div class="flex h-full max-h-96 flex-1 flex-col overflow-hidden">
                    <div class="mb-8 flex-1 space-y-3 overflow-y-auto px-1">
                        <!-- Draw Polygon Section -->
                        <div class="flex flex-col items-center justify-center py-2 text-center">
                            <div class="mb-2">
                                <h3 class="text-foreground/70 mb-3 text-lg font-semibold">Purchase Satellite Imagery</h3>
                                <p class="mb-3 text-xs text-gray-600">Draw a polygon on the map to define your area of interest for satellite imagery analysis.</p>
                                <x-button-primary id="drawPolygonBtn" type="button" size="small">
                                    <i class="ri-pencil-line"></i>
                                    <span>Draw Polygon</span>
                                </x-button-primary>
                            </div>

                            <!-- GeoJSON Output -->
                            <div class="w-full">
                                <div class="border-muted mt-3 max-h-11 w-full overflow-auto rounded border bg-gray-50 p-2 text-xs" id="drawerGeojson">
                                    <span class="text-foreground/50">Polygon coordinates will appear here...</span>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-300">

                        <!-- Feature Properties Form -->
                        <div class="hidden w-full" id="featureProperties">
                            <form class="space-y-4" id="featurePropertiesForm" action="{{ route('mapOrder') }}" method="POST">
                                @csrf
                                @method('POST')

                                <input id="geometryInput" name="geometry" type="hidden">
                                <input id="areaInput" name="area_hectares" type="hidden">

                                <!-- Feature Name -->
                                <div class="space-y-2">
                                    <x-input-label class="text-sm font-medium" for="name_feature">Field Name</x-input-label>
                                    <x-text-input class="w-full" id="name_feature" name="name_feature" size="small" placeholder="Enter field/region name" required />
                                </div>

                                <!-- Area Information -->
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <x-input-label class="text-sm font-medium" for="luas">Area</x-input-label>
                                        <div class="border-muted rounded border bg-gray-50 p-2 text-sm" id="measurementOutput">
                                            <div class="text-foreground/50 flex items-center">
                                                <i class="ri-crop-line mr-2"></i>
                                                <span>Calculate area...</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <x-input-label class="text-sm font-medium" for="harga_satuan">Price per Hectare</x-input-label>
                                        <div class="border-muted rounded border p-2 text-sm">
                                            <select class="border-border focus:border-primary focus:ring-primary w-full rounded border px-2 py-1 text-sm focus:outline-none focus:ring-1" id="plan_id" name="plan_id">
                                                @forelse($plans->where('isShow', true)->sortBy('price_per_hectare') as $plan)
                                                    <option data-price="{{ $plan->price_per_hectare }}" data-currency="{{ $plan->currency }}" value="{{ $plan->id }}" {{ $loop->first ? 'selected' : '' }}>
                                                        {{ $plan->name }} - {{ Number::currency($plan->price_per_hectare, $plan->currency, app()->getLocale()) }} / ha
                                                    </option>
                                                @empty
                                                    <option value="" selected disabled>No plans available</option>
                                                @endforelse
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Price -->
                                <div class="space-y-2">
                                    <x-input-label class="text-sm font-medium" for="harga">Total Price</x-input-label>
                                    <div class="border-muted bg-muted/60 rounded border p-2 text-sm" id="priceOutput">
                                        <span class="font-semibold text-blue-600" id="total_price">Total will be calculated...</span>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col gap-2 pt-2 sm:flex-row">
                                    <x-button-primary class="flex-1" id="saveFeatureProperties" type="submit" role="button" size="small">
                                        <i class="ri-arrow-right-line mr-1"></i>
                                        Continue to Checkout
                                    </x-button-primary>
                                    <x-button-secondary class="flex-1" id="cancelFeatureProperties" type="reset" role="button" size="small">
                                        <i class="ri-close-line mr-1"></i>
                                        Cancel
                                    </x-button-secondary>
                                </div>
                            </form>
                        </div>

                        <!-- Additional Information -->
                        <div class="bg-muted/60 rounded-lg p-3">
                            <h4 class="mb-2 text-sm font-medium text-blue-800">What you'll get:</h4>
                            <ul class="space-y-1 text-xs text-blue-700">
                                <li class="flex items-center">
                                    <i class="ri-check-line mr-1 text-green-500"></i>
                                    High-resolution satellite imagery
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line mr-1 text-green-500"></i>
                                    PRI stress analysis
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line mr-1 text-green-500"></i>
                                    Detailed crop health reports
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line mr-1 text-green-500"></i>
                                    Historical data comparison
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('javascript')
        <script>
            const panelWrapper = document.getElementById("panel-wrapper");
            const panels = document.querySelectorAll("#panel-wrapper section");
            const sidebarButtons = document.querySelectorAll(".sidebar-btn");
            const scrollContainer = document.getElementById('scroll-container');
            const scrollLeftBtn = document.getElementById('scroll-left');
            const scrollRightBtn = document.getElementById('scroll-right');

            const scrollAmount = 150; // pixels per click

            scrollLeftBtn.addEventListener('click', () => {
                scrollContainer.scrollBy({
                    left: -scrollAmount,
                    behavior: 'smooth'
                });
            });

            scrollRightBtn.addEventListener('click', () => {
                scrollContainer.scrollBy({
                    left: scrollAmount,
                    behavior: 'smooth'
                });
            });

            // Optional: drag/grab to scroll
            let isDown = false;
            let startX;
            let scrollLeft;

            scrollContainer.addEventListener('mousedown', (e) => {
                isDown = true;
                scrollContainer.classList.add('cursor-grabbing');
                startX = e.pageX - scrollContainer.offsetLeft;
                scrollLeft = scrollContainer.scrollLeft;
            });

            scrollContainer.addEventListener('mouseleave', () => {
                isDown = false;
                scrollContainer.classList.remove('cursor-grabbing');
            });

            scrollContainer.addEventListener('mouseup', () => {
                isDown = false;
                scrollContainer.classList.remove('cursor-grabbing');
            });

            scrollContainer.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - scrollContainer.offsetLeft;
                const walk = (x - startX) * 2; // scroll-fast
                scrollContainer.scrollLeft = scrollLeft - walk;
            });

            function showPanel(id, btn = null) {
                const isMobile = window.innerWidth < 768;

                panels.forEach(p => p.classList.add("hidden"));
                const targetPanel = document.getElementById(id);
                targetPanel.classList.remove("hidden");

                sidebarButtons.forEach(b => b.classList.remove("active"));
                if (btn) btn.classList.add("active");
                else {
                    const matchedBtn = Array.from(sidebarButtons).find(b => b.getAttribute("onclick")?.includes(id));
                    if (matchedBtn) matchedBtn.classList.add("active");
                }

                if (isMobile) {
                    // reset posisi
                    panelWrapper.classList.remove("translate-y-full");
                    panelWrapper.classList.add("translate-y-0");

                    // animasi slide-up
                    panelWrapper.classList.remove("slide-down");
                    panelWrapper.classList.add("slide-up");
                } else {
                    panelWrapper.classList.remove("w-0", "md:w-0");
                    panelWrapper.classList.add("w-80", "md:w-80");
                }

                panelWrapper.dataset.activePanel = id;
            }

            function closePanels() {
                const isMobile = window.innerWidth < 768;

                panels.forEach(p => p.classList.add("hidden"));
                sidebarButtons.forEach(b => b.classList.remove("active"));
                delete panelWrapper.dataset.activePanel;

                if (isMobile) {
                    // animasi slide-down
                    panelWrapper.classList.remove("slide-up");
                    panelWrapper.classList.add("slide-down");

                    // setelah animasi selesai (500ms), sembunyikan sepenuhnya
                    setTimeout(() => {
                        panelWrapper.classList.remove("translate-y-0");
                        panelWrapper.classList.add("translate-y-full");
                    }, 480);
                } else {
                    panelWrapper.classList.remove("w-80", "md:w-80");
                    panelWrapper.classList.add("w-0", "md:w-0");
                }
            }


            // === DEFAULT STATE saat halaman load ===
            window.addEventListener("DOMContentLoaded", () => {
                const defaultPanel = 'data-panel';
                const isMobile = window.innerWidth < 768;

                const defaultBtn = isMobile ?
                    document.querySelector(`#scroll-container .sidebar-btn[onclick*='${defaultPanel}']`) :
                    document.querySelector(`aside .sidebar-btn[onclick*='${defaultPanel}']`);

                showPanel(defaultPanel, defaultBtn);
            });

            // === RESPONSIVE HANDLER: SYNC STATE SAAT RESIZE ===
            window.addEventListener("resize", () => {
                const isMobile = window.innerWidth < 768;
                const activePanel = panelWrapper.dataset.activePanel;

                // Jika tidak ada panel aktif (semua ditutup), keluar saja
                if (!activePanel) {
                    // Pastikan panel wrapper tertutup di semua mode
                    panelWrapper.classList.add("translate-y-full");
                    panelWrapper.classList.remove("translate-y-0", "w-80", "md:w-80");
                    return;
                }

                // Hapus semua class transisi yang bisa bentrok
                panelWrapper.classList.remove("slide-up", "slide-down");

                if (isMobile) {
                    // mobile mode: gunakan slide-up style
                    panelWrapper.classList.remove("w-0", "md:w-0", "w-80", "md:w-80");
                    panelWrapper.classList.remove("translate-y-full");
                    panelWrapper.classList.add("translate-y-0", "opacity-100");
                } else {
                    // desktop mode: gunakan lebar tetap (sidebar style)
                    panelWrapper.classList.remove("translate-y-full", "translate-y-0");
                    panelWrapper.classList.add("w-80", "md:w-80", "opacity-100");
                }

                // perbarui tombol active sesuai mode baru
                const activeBtn = isMobile ?
                    document.querySelector(`#scroll-container .sidebar-btn[onclick*='${activePanel}']`) :
                    document.querySelector(`aside .sidebar-btn[onclick*='${activePanel}']`);

                sidebarButtons.forEach(b => b.classList.remove("active"));
                if (activeBtn) activeBtn.classList.add("active");
            });




            // Buy Satellite Button Event
            document.getElementById('buySatelliteBtn')?.addEventListener('click', function() {
                const buyingPanel = document.getElementById('buyingPanel');
                buyingPanel.classList.remove('hidden');
            });
            document.getElementById('buyingPanelCloseBtn')?.addEventListener('click', function() {
                const buyingPanel = document.getElementById('buyingPanel');
                buyingPanel.classList.add('hidden');
            });

            // Price calculation functions
            function calculateTotalPrice() {
                const planSelect = document.getElementById('plan_id');
                const selectedOption = planSelect.options[planSelect.selectedIndex];
                const pricePerHectare = parseFloat(selectedOption.dataset.price) || 0;
                const currencyCode = selectedOption.dataset.currency || 'USD'; // Get currency code from data attribute

                // Get area from global variable (set when polygon is drawn)
                const areaInSquareMeters = window.geojsonArea || 0;
                const areaInHectares = areaInSquareMeters / 10000; // Convert m² to hectares

                const totalPrice = areaInHectares * pricePerHectare;

                // Update the display
                const totalPriceElement = document.getElementById('total_price');
                const priceContainer = totalPriceElement.parentElement;

                if (areaInHectares > 0 && pricePerHectare > 0) {
                    totalPriceElement.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-green-700">${formatCurrency(totalPrice, currencyCode)}</span>
                            <i class="ri-money-dollar-circle-line text-green-600 text-xl"></i>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            ${formatNumber(areaInHectares)} hectares × ${formatCurrency(pricePerHectare, currencyCode)}/hectare
                        </div>
                    `;
                    priceContainer.classList.remove('bg-muted/60', 'border-muted');
                    priceContainer.classList.add('bg-green-50', 'border-green-300', 'shadow-sm');

                    // Add a subtle animation
                    priceContainer.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        priceContainer.style.transform = 'scale(1)';
                    }, 200);
                } else if (areaInHectares > 0 && pricePerHectare === 0) {
                    totalPriceElement.innerHTML = `
                        <div class="flex items-center text-amber-600">
                            <i class="ri-alert-line mr-2"></i>
                            Please select a plan to calculate price
                        </div>
                    `;
                    priceContainer.classList.remove('bg-green-50', 'border-green-300', 'shadow-sm');
                    priceContainer.classList.add('bg-amber-50', 'border-amber-300');
                } else {
                    totalPriceElement.innerHTML = `
                        <div class="flex items-center text-foreground/50">
                            <i class="ri-information-line mr-2"></i>
                            Draw an area to calculate price
                        </div>
                    `;
                    priceContainer.classList.remove('bg-green-50', 'border-green-300', 'shadow-sm', 'bg-amber-50', 'border-amber-300');
                    priceContainer.classList.add('bg-muted/60', 'border-muted');
                }

                // Add transition for smooth color changes
                priceContainer.style.transition = 'all 0.3s ease-in-out';
            }

            // Event listener for plan change
            document.getElementById('plan_id').addEventListener('change', function() {
                calculateTotalPrice();
            });

            // Make calculateTotalPrice available globally for map.js
            window.calculateTotalPrice = calculateTotalPrice;
        </script>

        @auth
            @if (isset($activeFieldAreas) && $activeFieldAreas->count() > 0)
                <script>
                    // Pass field areas data to JavaScript
                    window.activeFieldAreas = @json($activeFieldAreas);
                </script>
            @endif
        @endauth
    @endpush
</x-app-front-map-layout>

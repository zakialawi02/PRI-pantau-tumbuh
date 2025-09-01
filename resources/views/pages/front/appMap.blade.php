@section('title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-map-layout class="flex h-screen flex-col overflow-hidden md:flex-row">
    <!-- Sidebar -->
    <aside class="border-border bg-background group fixed inset-y-0 left-0 z-50 hidden h-screen w-64 -translate-x-full border-r px-1.5 py-3 transition-all duration-150 md:relative md:flex md:w-12 md:translate-x-0 md:flex-col md:justify-between hover:md:w-48" id="sidebar">
        <!-- Sidebar Nav Menu -->
        <div class="mt-10 flex w-full flex-col space-y-6">
            <button class="hover:text-accent nav-map-active flex items-center space-x-2 p-1 transition-colors" data-drawer-target="drawer-sidebar-left-panel1" data-drawer-show="drawer-sidebar-left-panel1" data-drawer-backdrop="false" type="button" aria-controls="drawer-sidebar-left-panel1">
                <span class="text-xl">📡</span>
                <span class="hidden whitespace-nowrap text-sm font-medium group-hover:inline-block">My Data</span>
            </button>
            <button class="hover:text-accent flex items-center space-x-2 p-1 transition-colors" data-drawer-target="drawer-sidebar-left-panel2" data-drawer-show="drawer-sidebar-left-panel2" data-drawer-backdrop="false" type="button" aria-controls="drawer-sidebar-left-panel2">
                <span class="text-xl">📊</span>
                <span class="hidden whitespace-nowrap text-sm font-medium group-hover:inline-block">Analytics</span>
            </button>
            <button class="hover:text-accent flex items-center space-x-2 p-1 transition-colors">
                <span class="text-xl">🌱</span>
                <span class="hidden whitespace-nowrap text-sm font-medium group-hover:inline-block">Growth</span>
            </button>
            <button class="hover:text-accent flex items-center space-x-2 p-1 transition-colors">
                <span class="text-xl">📜</span>
                <span class="hidden whitespace-nowrap text-sm font-medium group-hover:inline-block">Reports</span>
            </button>
        </div>

        <!-- Profile -->
        <div class="mb-0 mt-0 md:mb-4 md:mt-auto">
            @auth
                <div class="relative">
                    <button class="bg-foreground mx-3 flex rounded-full text-sm transition-all duration-200 hover:ring-2 focus:ring-4 focus:ring-gray-300 md:mr-0" id="user-menu-button" data-dropdown-toggle="user-dropdown" type="button" aria-expanded="false" aria-haspopup="true">
                        <span class="text-background hidden p-0.5 group-hover:inline-block">Profile</span>
                        <span class="sr-only">Open user menu</span>
                        <img class="h-6 w-6 rounded-full object-cover" src="{{ Auth::user()->profile_photo_path ?? asset('assets/img/image-placeholder.webp') }}" alt="{{ Auth::user()->name }}'s profile photo" loading="lazy">
                    </button>

                    <!-- Authenticated User Dropdown -->
                    <div class="bg-background text-foreground divide-foreground/50 border-border w-50 absolute right-0 z-50 mt-2 hidden list-none divide-y rounded-lg border shadow-lg" id="user-dropdown">
                        <div class="px-4 py-3">
                            <span class="block text-sm font-medium">{{ Str::limit(Auth::user()->name, 25) }}</span>
                            <span class="block truncate text-sm text-gray-500">{{ Str::limit(Auth::user()->email, 25) }}</span>
                        </div>

                        <ul class="py-2" role="menu" aria-labelledby="user-menu-button">
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="{{ route('admin.dashboard') }}" role="menuitem">
                                    <i class="ri-dashboard-line mr-2"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="{{ route('admin.profile.edit') }}" role="menuitem">
                                    <i class="ri-user-line mr-2"></i>
                                    Profile
                                </a>
                            </li>
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="#" role="menuitem">
                                    <i class="ri-settings-line mr-2"></i>
                                    Settings
                                </a>
                            </li>
                        </ul>

                        <div class="py-2">
                            <form role="none" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="hover:bg-muted flex w-full items-center px-4 py-2 text-left text-sm transition-colors" type="submit" role="menuitem">
                                    <i class="ri-logout-line mr-2"></i>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <div class="relative">
                    <button class="bg-foreground mx-3 flex rounded-full text-sm transition-all duration-200 hover:ring-2 focus:ring-4 focus:ring-gray-300 md:mr-0" id="guest-menu-button" data-dropdown-toggle="guest-dropdown" type="button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <img class="h-6 w-6 rounded-full object-cover" src="{{ asset('assets/img/image-placeholder.webp') }}" alt="Guest user photo" loading="lazy">
                    </button>

                    <!-- Guest User Dropdown -->
                    <div class="bg-background text-foreground divide-foreground/50 border-border w-50 absolute right-0 z-50 mt-2 hidden list-none divide-y rounded-lg border shadow-lg" id="guest-dropdown">
                        <ul class="py-2" role="menu" aria-labelledby="guest-menu-button">
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="{{ route('login') }}" role="menuitem">
                                    <i class="ri-login-box-line mr-2"></i>
                                    Login
                                </a>
                            </li>
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="{{ route('register') }}" role="menuitem">
                                    <i class="ri-user-add-line mr-2"></i>
                                    Register
                                </a>
                            </li>
                            <li role="none">
                                <a class="hover:bg-muted flex items-center px-4 py-2 text-sm transition-colors" href="#" role="menuitem">
                                    <i class="ri-settings-line mr-2"></i>
                                    Settings
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            @endauth
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex h-full flex-1 flex-col">
        <!-- Header -->
        <div class="bg-background flex items-center justify-between px-3 py-2 md:px-6">
            <h1 class="text-xl font-bold md:text-2xl">{{ config('app.name') }}</h1>
            <div class="flex items-center space-x-2 rounded-md">
                <x-text-input size="small" placeholder="Search..." />
            </div>

            <!-- Nav Menu -->
            <div class="text-foreground bg-background z-51 fixed inset-0 hidden flex-col items-center justify-center space-y-6 whitespace-nowrap text-center uppercase opacity-0 transition-all duration-500 ease-in-out" id="navbar">

                <!-- Nav Menu -->
                <x-nav-menu :mobile="false" />
            </div>

            <button class="hover:text-accent z-51 text-3xl transition-transform duration-300 hover:scale-110" id="navbar-toggle">
                <i class="ri-menu-line transition-all duration-300" id="menu-icon"></i>
                <i class="ri-close-line hidden transition-all duration-300" id="close-icon"></i>
            </button>
        </div>

        <!-- Horizontal Sidebar Nav -->
        <div class="top-13 fixed left-0 z-50 mx-auto w-full whitespace-nowrap px-4 py-2 md:hidden">
            <!-- Arrow Left -->
            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute left-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-left">
                <i class="ri-arrow-left-s-line text-lg"></i>
            </button>
            <!-- Scrollable Container -->
            <div class="flex space-x-2 overflow-x-hidden scroll-smooth" id="scroll-container">
                <button class="bg-neutral nav-map-active inline-flex items-center space-x-2 rounded-full border border-gray-300 px-4 py-1 text-sm font-medium" data-drawer-target="drawer-sidebar-left-panel1" data-drawer-show="drawer-sidebar-left-panel1" data-drawer-backdrop="false" type="button" aria-controls="drawer-sidebar-left-panel1">
                    <span class="text-xl">📡</span>
                    <span>My Data</span>
                </button>
                <button class="bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" data-drawer-target="drawer-sidebar-left-panel2" data-drawer-show="drawer-sidebar-left-panel2" data-drawer-backdrop="false" type="button" aria-controls="drawer-sidebar-left-panel2">
                    <span class="text-xl">📊</span>
                    <span>Analytics</span>
                </button>
                <button class="bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium">
                    <span class="text-xl">🌱</span>
                    <span>Growth</span>
                </button>
                <button class="bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium">
                    <span class="text-xl">📜</span>
                    <span>Reports</span>
                </button>
            </div>
            <!-- Arrow Right -->
            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute right-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-right">
                <i class="ri-arrow-right-s-line text-lg"></i>
            </button>
        </div>

        <div class="flex h-full flex-1 flex-row">
            <!-- * Left Panel (Push Layout)*  -->
            <!-- Left Panel1 - Responsive Flowbite Drawer -->
            <div class="bg-background shadow-r-lg responsive-drawer fixed bottom-0 left-0 right-0 z-50 h-[50vh] max-h-[50vh] w-full overflow-hidden transition-all duration-300 ease-in-out md:relative md:inset-auto md:z-auto md:block md:h-full md:max-h-full md:w-80" id="drawer-sidebar-left-panel1" data-drawer-state="open" data-drawer-placement="left" data-drawer-edge="true" aria-labelledby="drawer-sidebar-left-panel1-label" tabindex="-1">
                <div class="w-81 flex h-full min-w-full flex-col p-2 md:min-w-8" id="drawer-sidebar-left-panel1-label">
                    <!-- Header drawer -->
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">My Data</h2>
                        <button class="hover:text-primary/80 text-gray-500" data-drawer-hide="drawer-sidebar-left-panel1" type="button">✕</button>
                    </div>
                    <!-- Drawer content -->
                    <div class="flex-1 space-y-3 overflow-y-auto">
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="mb-6">
                                <div class="bg-muted mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                                    <i class="ri-database-2-line text-3xl text-gray-400"></i>
                                </div>
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-700">No Data Available</h3>
                            <p class="mb-6 px-4 text-sm text-gray-500">
                                You don't have any satellite imagery data yet. Purchase satellite imagery to start monitoring your crops and analyzing plant stress using PRI.
                            </p>
                            @auth
                                <x-button-primary id="buySatelliteBtn" type="button" size="small">
                                    <i class="ri-shopping-cart-line"></i>
                                    <span>Buy Satellite Imagery</span>
                                </x-button-primary>
                            @else
                                <x-button-primary type="button" onclick="window.location.href='{{ route('login') }}'" size="small">
                                    <i class="ri-login-box-line"></i>
                                    <span>Login to Buy Imagery</span>
                                </x-button-primary>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>

            <!-- Left Panel2 - Responsive Flowbite Drawer -->
            <div class="bg-background shadow-r-lg responsive-drawer fixed bottom-0 left-0 right-0 z-50 h-0 max-h-[50vh] w-full overflow-hidden transition-all duration-300 ease-in-out md:relative md:inset-auto md:z-auto md:block md:h-full md:max-h-full md:w-0" id="drawer-sidebar-left-panel2" data-drawer-state="closed" data-drawer-placement="left" data-drawer-edge="true" aria-labelledby="drawer-sidebar-left-panel2-label" tabindex="-1">
                <div class="flex h-full min-w-full flex-col p-2 md:min-w-80" id="drawer-sidebar-left-panel2-label">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Analytics</h2>
                        <button class="hover:text-primary/80 text-gray-500" data-drawer-hide="drawer-sidebar-left-panel2" type="button">
                            ✕
                        </button>
                    </div>
                    <!-- Drawer content -->
                    <div class="flex-1 space-y-3 overflow-y-auto">
                        <div class="bg-muted rounded-lg p-3">📈 Crop Yield Analysis</div>
                        <div class="bg-muted rounded-lg p-3">🌡️ Climate Trends</div>
                        <div class="bg-muted rounded-lg p-3">💧 Water Usage</div>
                        <div class="bg-muted rounded-lg p-3">🌍 Regional Comparison</div>
                    </div>
                </div>
            </div>


            <!-- Map / Content Area -->
            <div class="bg-background relative h-full min-h-0 flex-1 rounded-md rounded-t transition-all duration-300 ease-in-out">
                <div class="map" id="map"></div>

                <!-- Right Buttons -->
                <div class="absolute right-2 top-1/2 flex -translate-y-1/2 flex-col space-y-1 text-base md:text-lg">
                    <button class="bg-neutral hover:bg-muted rounded px-1.5 py-0.5 transition-colors" title="Zoom In" onclick="zoomIn()">
                        +
                        <span class="sr-only">Zoom In</span>
                    </button>
                    <button class="bg-neutral hover:bg-muted rounded px-1.5 py-0.5 transition-colors" title="Zoom Out" onclick="zoomOut()">
                        -
                        <span class="sr-only">Zoom Out</span>
                    </button>
                    <button class="bg-neutral hover:bg-muted rotate-180 rounded px-1.5 py-0.5 transition-colors" id="minimapToggleBtn" title="Toggle Minimap" onclick="toggleMinimap(this)">
                        <i class="ri-arrow-left-double-line"></i>
                        <span class="sr-only">Toggle Minimap</span>
                    </button>
                </div>

                <!-- * Bottom Buttons * -->
                <!-- Basemap Buttons -->
                <div class="fixed bottom-8 left-0 z-40 flex items-end space-x-2 text-xs md:absolute md:left-2 md:text-base">
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
                <div class="fixed bottom-11 left-0 flex items-end space-x-2 text-xs md:absolute md:left-2 md:text-base">
                    <div class="relative hidden md:block" id="mousePosition"></div>
                    <div class="relative -mb-2" id="scaleline"></div>
                </div>

                <!-- Bottom Date Selector -->
                <div class="fixed bottom-1 left-2 flex flex-wrap space-x-1 text-xs md:absolute md:text-sm">
                    <div class="bg-muted flex space-x-1 rounded-md p-1">
                        <button class="bg-neutral rounded px-1 py-0.5">1D</button>
                        <button class="bg-primary rounded px-1 py-0.5">1W</button>
                        <button class="bg-neutral rounded px-1 py-0.5">1M</button>
                        <button class="bg-neutral rounded px-1 py-0.5">1Y</button>
                    </div>

                    <button class="bg-neutral rounded px-1 py-0.5">20 Aug 2021</button>
                    <button class="bg-neutral rounded px-1 py-0.5">20 Aug 2021</button>
                    <button class="bg-neutral rounded px-1 py-0.5">20 Aug 2021</button>
                    <button class="bg-primary rounded px-1 py-0.5">20 Aug 2021</button>
                </div>
            </div>
        </div>

        <div class="bg-background absolute bottom-0 left-0 z-50 hidden max-h-[60%] w-full max-w-full overflow-hidden rounded-t-xl shadow-xl transition-all duration-300 ease-in-out md:bottom-auto md:left-auto md:right-2 md:top-1/2 md:max-w-[30rem] md:-translate-y-1/2 md:transform md:rounded-xl" id="buyingPanel">
            <div class="flex h-full w-full min-w-full flex-col p-2" id="drawer-sidebar-left-panel1-label">
                <!-- Header drawer -->
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Purchase a field</h2>
                    <button class="hover:text-primary/80 close-panel-btn text-gray-500" id="buyingPanelCloseBtn" data-drawer-hide="drawer-sidebar-left-panel1" type="button">✕</button>
                </div>
                <!-- Drawer content -->
                <div class="flex h-full max-h-96 flex-1 flex-col overflow-hidden">
                    <div class="mb-8 flex-1 space-y-3 overflow-y-auto px-1">
                        <!-- Draw Polygon Section -->
                        <div class="flex flex-col items-center justify-center py-4 text-center">
                            <div class="mb-2">
                                <h3 class="mb-3 text-lg font-semibold text-gray-700">Purchase Satellite Imagery</h3>
                                <p class="mb-3 text-xs text-gray-600">Draw a polygon on the map to define your area of interest for satellite imagery analysis.</p>
                                <x-button-primary id="drawPolygonBtn" type="button" size="small">
                                    <i class="ri-pencil-line"></i>
                                    <span>Draw Polygon</span>
                                </x-button-primary>
                            </div>

                            <!-- GeoJSON Output -->
                            <div class="w-full">
                                <div class="border-muted mt-3 max-h-11 w-full overflow-auto rounded border bg-gray-50 p-2 text-xs" id="drawerGeojson">
                                    <span class="text-gray-500">Polygon coordinates will appear here...</span>
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
                                            <div class="flex items-center text-gray-500">
                                                <i class="ri-crop-line mr-2"></i>
                                                <span>Calculate area...</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <x-input-label class="text-sm font-medium" for="harga_satuan">Price per Hectare</x-input-label>
                                        <div class="border-muted rounded border p-2 text-sm">
                                            <select class="border-border focus:border-primary focus:ring-primary w-full rounded border px-2 py-1 text-sm focus:outline-none focus:ring-1" id="plan_id" name="plan_id">
                                                @if (isset($plans) && count($plans) > 0)
                                                    @php
                                                        $hasVisiblePlans = false;
                                                        $sortedPlans = $plans->where('isShow', true)->sortBy('price_per_hectare');
                                                        $lowestPricePlan = $sortedPlans->first();
                                                    @endphp
                                                    @foreach ($sortedPlans as $index => $plan)
                                                        @php $hasVisiblePlans = true; @endphp
                                                        <option data-price="{{ $plan->price_per_hectare }}" value="{{ $plan->id }}" {{ $plan->id === $lowestPricePlan->id ? 'selected' : '' }}>
                                                            {{ $plan->name }} - {{ $plan->currency }} {{ number_format($plan->price_per_hectare, 2) }}/ha
                                                        </option>
                                                    @endforeach
                                                    @if (!$hasVisiblePlans)
                                                        <option value="" selected disabled>No plans available</option>
                                                    @endif
                                                @else
                                                    <option value="" selected disabled>No plans available</option>
                                                @endif
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

        <!-- Backdrop -->
        <div class="min-h-2/12 fixed inset-0 z-30 hidden bg-black/40" id="drawer-backdrop"></div>
    </div>

    @push('javascript')
        <script>
            // sidebar nav menu
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

            // drawer
            document.querySelectorAll("[data-drawer-target]").forEach(btn => {
                btn.addEventListener("click", function() {
                    const targetId = btn.getAttribute("data-drawer-target");
                    const drawer = document.getElementById(targetId);
                    const state = drawer.getAttribute("data-drawer-state");
                    const isMobile = window.innerWidth < 768;

                    // Reset semua tombol active terlebih dahulu
                    document.querySelectorAll("[data-drawer-target]").forEach(b => {
                        b.classList.remove("nav-map-active");
                    });

                    // Tutup semua drawer lain dulu
                    document.querySelectorAll("[id^='drawer-sidebar-']").forEach(d => {
                        if (isMobile) {
                            d.classList.remove("h-[50vh]");
                            d.classList.add("h-0");
                        } else {
                            d.classList.remove("w-80");
                            d.classList.add("w-0");
                        }
                        d.setAttribute("data-drawer-state", "closed");
                    });

                    // Toggle target drawer
                    if (state === "closed") {
                        if (isMobile) {
                            drawer.classList.remove("h-0");
                            drawer.classList.add("h-[50vh]");
                        } else {
                            drawer.classList.remove("w-0");
                            drawer.classList.add("w-80");
                        }
                        drawer.setAttribute("data-drawer-state", "open");

                        // Tambahkan active ke tombol yang sedang dipakai
                        btn.classList.add("nav-map-active");
                    }
                });
            });

            // tombol close di dalam drawer
            document.querySelectorAll("[data-drawer-hide]").forEach(btn => {
                btn.addEventListener("click", function() {
                    const targetId = btn.getAttribute("data-drawer-hide");
                    const drawer = document.getElementById(targetId);
                    const isMobile = window.innerWidth < 768;

                    // Close drawer
                    if (isMobile) {
                        drawer.classList.add("h-0");
                        drawer.classList.remove("h-[50vh]");
                    } else {
                        drawer.classList.remove("w-80");
                        drawer.classList.add("w-0");
                    }
                    drawer.setAttribute("data-drawer-state", "closed");

                    // Reset semua tombol nav active
                    document.querySelectorAll("[data-drawer-target]").forEach(b => {
                        if (b.getAttribute("data-drawer-target") === targetId) {
                            b.classList.remove("nav-map-active");
                        }
                    });
                });
            });

            // Handle window resize for responsive behavior
            window.addEventListener('resize', function() {
                const isMobile = window.innerWidth < 768;

                document.querySelectorAll("[id^='drawer-sidebar-']").forEach(drawer => {
                    const isOpen = drawer.getAttribute("data-drawer-state") === "open";

                    if (isMobile) {
                        // Switch to mobile mode
                        drawer.classList.remove("w-80", "w-0");
                        if (isOpen) {
                            drawer.classList.remove("h-0");
                            drawer.classList.add("h-[50vh]");
                        } else {
                            drawer.classList.add("h-0");
                            drawer.classList.remove("h-[50vh]");
                        }
                    } else {
                        // Switch to desktop mode
                        drawer.classList.remove("h-0", "h-[50vh]");
                        if (isOpen) {
                            drawer.classList.remove("w-0");
                            drawer.classList.add("w-80");
                        } else {
                            drawer.classList.remove("w-80");
                            drawer.classList.add("w-0");
                        }
                    }
                });

                // Maintain active state synchronization after resize
                const openDrawer = document.querySelector("[id^='drawer-sidebar-'][data-drawer-state='open']");
                if (openDrawer) {
                    const drawerId = openDrawer.id;
                    document.querySelectorAll("[data-drawer-target]").forEach(btn => {
                        if (btn.getAttribute("data-drawer-target") === drawerId) {
                            btn.classList.add("nav-map-active");
                        } else {
                            btn.classList.remove("nav-map-active");
                        }
                    });
                }
            });

            // Buy Satellite Button Event
            document.getElementById('buySatelliteBtn').addEventListener('click', function() {
                const buyingPanel = document.getElementById('buyingPanel');
                buyingPanel.classList.remove('hidden');
            });
            document.getElementById('buyingPanelCloseBtn').addEventListener('click', function() {
                const buyingPanel = document.getElementById('buyingPanel');
                buyingPanel.classList.add('hidden');
            });

            // Price calculation functions
            function calculateTotalPrice() {
                const planSelect = document.getElementById('plan_id');
                const selectedOption = planSelect.options[planSelect.selectedIndex];
                const pricePerHectare = parseFloat(selectedOption.dataset.price) || 0;

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
                            <span class="text-lg font-bold text-green-700">US$${totalPrice.toFixed(2)}</span>
                            <i class="ri-money-dollar-circle-line text-green-600 text-xl"></i>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            ${areaInHectares.toFixed(4)} hectares × US$${pricePerHectare.toFixed(2)}/hectare
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
                        <div class="flex items-center text-gray-500">
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
    @endpush
</x-app-front-map-layout>

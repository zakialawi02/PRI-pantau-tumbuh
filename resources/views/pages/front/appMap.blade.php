@section('title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')

<x-app-front-map-layout class="flex h-screen w-screen flex-col overflow-hidden">
    <!-- HEADER -->
    <header class="bg-background border-foreground/5 flex h-12 items-center justify-between border-b-2 px-4">
        <a href="/">
            <h1 class="text-primary max-h-10 w-auto text-sm font-bold"><x-application-logo class="h-8" /></h1>
        </a>
        <div class="flex items-center space-x-3">
            @auth
                <!-- Credit Display for Authenticated Users -->
                <div class="group relative">
                    <div class="bg-primary/10 text-primary flex cursor-pointer items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                        <i class="ri-coins-line mr-1"></i>
                        <p><span id="current-myCredits">{{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }}</span> <span>Credit Points</span></p>
                    </div>

                    <!-- Dropdown CTA for purchasing credit points -->
                    <div class="absolute right-0 z-50 hidden w-64 transition duration-200 ease-in-out group-hover:block">
                        <div class="bg-background rounded-md py-2 shadow-xl">
                            <div class="text-foreground/50 border-b px-4 py-2 text-xs">Your current balance</div>
                            <a class="text-foreground/70 hover:bg-foreground/10 block px-4 py-3 text-sm" href="{{ route('admin.purchase-credits') }}">
                                <div class="flex items-center">
                                    <i class="ri-coins-line mr-3 text-lg"></i>
                                    <div>
                                        <div class="font-medium">Purchase More Credits</div>
                                        <div class="text-foreground/50 text-xs">Get more credit points to access more premium features</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="group relative">
                    <div class="bg-primary/10 text-primary flex cursor-pointer items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                        <i class="ri-coins-line mr-1"></i>
                        <p><span id="current-myCredits">-</span> <span>Credit Points</span></p>
                    </div>

                    <!-- Dropdown CTA for purchasing credit points (for guests) -->
                    <div class="absolute right-0 z-50 hidden w-64 transition duration-200 ease-in-out group-hover:block">
                        <div class="bg-background rounded-md py-2 shadow-xl">
                            <a class="text-foreground/70 hover:bg-foreground/10 block px-4 py-3 text-sm" href="{{ route('purchase-credits') }}">
                                <div class="flex items-center">
                                    <i class="ri-coins-line mr-3 text-lg"></i>
                                    <div>
                                        <div class="font-medium">Purchase Credits</div>
                                        <div class="text-foreground/50 text-xs">Sign in to purchase credits</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            @endauth

            @guest
                <x-button-primary href="{{ route('login') }}" size="small" variant="outline">Login</x-button-primary>
            @endguest

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
                    <span class="text-xl">🗺️</span>
                    <span>My Data</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('uploads-panel', this)">
                    <span class="text-xl">⬆️</span>
                    <span>Uploads</span>
                </button>
                <button class="sidebar-btn hover:text-primary flex flex-col items-center text-xs" onclick="showPanel('sentinel-panel', this)">
                    <span class="text-xl">🛰️</span>
                    <span>Sentinel-2</span>
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
            <div class="text-foreground/70 mt-auto text-xs">v0.1.210</div>
        </aside>

        <!-- MOBILE SIDEBAR HORIZONTAL -->
        <div class="fixed left-0 top-11 z-40 mx-auto w-full whitespace-nowrap bg-transparent px-4 py-2 md:hidden">
            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute left-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-left">
                <i class="ri-arrow-left-s-line text-lg"></i>
            </button>

            <div class="flex space-x-2 overflow-x-hidden scroll-smooth" id="scroll-container">
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('data-panel', this)">
                    <span class="text-xl">🗺️</span>
                    <span>My Data</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('uploads-panel', this)">
                    <span class="text-xl">⬆️</span>
                    <span>Uploads</span>
                </button>
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('sentinel-panel', this)">
                    <span class="text-xl">🛰️</span>
                    <span>Sentinel-2</span>
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
        <div class="bg-background border-foreground/20 fixed bottom-0 left-0 z-50 max-h-[56%] w-full translate-y-full overflow-y-auto rounded-t-xl opacity-0 transition-all duration-500 ease-in-out md:relative md:max-h-full md:w-0 md:translate-y-0 md:overflow-hidden md:rounded-none md:border-l-2 md:opacity-100" id="panel-wrapper">

            <!-- ========== MY DATA PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="data-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🗺️ My Data Imagery</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <!-- content -->
                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <div class="space-y-2">
                        @auth
                            <div class="flex items-center justify-between space-x-1">
                                <x-button-primary class="px-2! py-1!" href="{{ route('admin.imagery.index') }}" size="small" variant="outline">
                                    <i class="ri-dashboard-line"></i>
                                    <span class="ml-1">Go to Dashboard</span>
                                </x-button-primary>
                                <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="window.AppMap.uploader.reload()">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        @endauth
                        <div class="space-y-2">
                            @auth
                                <div class="space-y-2" id="myDataContainer">
                                    <p class="text-foreground/60 text-sm">Loading your imagery list...</p>
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-3 text-center">
                                    <div class="mb-3">
                                        <div class="bg-muted mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                                            <i class="ri-database-2-line text-foreground/80 text-3xl"></i>
                                        </div>
                                    </div>
                                    <h3 class="text-foreground/80 mb-2 text-lg font-semibold">No Data Available</h3>
                                    <p class="text-foreground/60 mb-4 px-4 text-sm">
                                        You don't have any satellite imagery data yet. Upload or purchase satellite imagery to start monitoring your crops and analyzing plant stress using PRI.
                                    </p>
                                    <x-button-primary type="button" href="{{ route('login') }}" size="small">
                                        <i class="ri-login-box-line"></i>
                                        <span>Login to Access Your Data</span>
                                    </x-button-primary>
                                </div>
                            @endauth
                        </div>

                        <!-- Hidden template card -->
                        <template id="imageryCardTemplate">
                            <div class="imagery-card border-foreground/20 bg-background/40 flex items-center justify-between rounded-xl border p-3 shadow-sm transition-all duration-200 hover:shadow-md">
                                <div class="flex items-start space-x-3">
                                    <div class="bg-primary/10 text-primary imagery-format flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg font-semibold uppercase">
                                        JPG
                                    </div>
                                    <div>
                                        <p class="text-foreground imagery-name font-medium">Sample Imagery</p>
                                        <p class="imagery-meta text-foreground/60 text-sm">
                                            10 MB • 2025-10-05 • <span class="imagery-status text-success font-semibold">completed</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button class="view-btn view-source-btn hover:bg-foreground/10 rounded-lg p-2 transition"
                                        type="button" title="Preview source imagery" aria-label="Preview source imagery">
                                        <i class="ri-image-line"></i>
                                    </button>
                                    <button class="view-btn view-processed-btn hover:bg-foreground/10 rounded-lg p-2 transition"
                                        type="button" title="Preview processed imagery on the map"
                                        aria-label="Preview processed imagery">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>

                <!-- sticky bottom panel -->
                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-foreground/70 text-sm">
                            @auth
                                {{ $imagery->count() ?? 0 }} imagery(s) active
                            @else
                                <span>Login to access your imagery data</span>
                            @endauth
                        </div>
                        <div class="text-foreground/50 text-xs">
                            Last updated: null
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========== SENTINEL COLLECTION PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="sentinel-panel" data-sentinel-token="{{ $copernicusAccessToken ?? '' }}" data-sentinel-credentials="{{ $copernicusCredentialsConfigured ?? false ? 'true' : 'false' }}" data-sentinel-process-url="{{ auth()->check() ? route('admin.sentinel.process') : '' }}" data-sentinel-clip-process-url="{{ auth()->check() ? route('admin.sentinel.clip') : '' }}" data-sentinel-processing-cost="{{ config('app-constants.imagery_processing_cost', 10) }}">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🛰️ Sentinel-2 Collections</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-1 overflow-y-auto p-2">
                    <!-- Tab Navigation -->
                    <div class="flex">
                        <div class="bg-foreground/10 hover:bg-foreground/20 flex rounded-lg p-1 transition">
                            <nav class="flex gap-x-1" role="tablist" aria-label="Tabs" aria-orientation="horizontal">
                                <button class="hs-tab-active:bg-neutral hs-tab-active:text-foreground/80 text-foreground/50 hover:text-foreground/80 focus:outline-hidden focus:text-foreground/80 hover:hover:text-primary active inline-flex items-center gap-x-2 rounded-lg bg-transparent px-2 py-1 text-sm font-medium disabled:pointer-events-none disabled:opacity-50" id="sentinel-collection-tab" data-hs-tab="#sentinel-collection-panel" type="button" role="tab" aria-selected="true" aria-controls="sentinel-collection-panel">
                                    Data Collection (Scene)
                                </button>
                                <button class="hs-tab-active:bg-neutral hs-tab-active:text-foreground/80 text-foreground/50 hover:text-foreground/80 focus:outline-hidden focus:text-foreground/80 hover:hover:text-primary inline-flex items-center gap-x-2 rounded-lg bg-transparent px-2 py-1 text-sm font-medium disabled:pointer-events-none disabled:opacity-50" id="sentinel-clip-tab" data-hs-tab="#sentinel-clip-panel" type="button" role="tab" aria-selected="false" aria-controls="sentinel-clip-panel">
                                    Imagery by Clip
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Data Tab Content -->
                    <div class="tab-content" id="sentinel-collection-panel" role="tabpanel" aria-labelledby="sentinel-collection-tab">
                        <form class="bg-background/60 border-foreground/10 rounded-lg border p-2 shadow-sm" id="sentinelFilterForm">
                            <div class="mb-1.5 flex items-center justify-between">
                                <h3 class="text-foreground text-sm font-semibold">Filter Collections</h3>
                                <button class="text-foreground/60 hover:text-primary text-xs font-medium transition" id="sentinelFilterResetButton" type="button">
                                    Reset
                                </button>
                            </div>
                            <div class="flex flex-col space-y-1">
                                <div class="grid grid-cols-2 gap-1">
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelCloudFilter">
                                        <span>Max Cloud Cover (%)</span>
                                        <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelCloudFilter" name="cloud-cover" type="number" value="40" max="100" min="0" placeholder="e.g. 30" step="1" />
                                    </label>
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelProductLevel">
                                        <span>Product Level</span>
                                        <select class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelProductLevel" name="product-level">
                                            <option value="S2MSI2A" selected>Level-2A (Surface Reflectance)</option>
                                            <option value="S2MSI1C">Level-1C (Top-of-Atmosphere)</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelStartDate">
                                        <span>Start Date</span>
                                        <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelStartDate" name="start-date" type="date" autocomplete="off" />
                                    </label>
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelEndDate">
                                        <span>End Date</span>
                                        <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelEndDate" name="end-date" type="date" autocomplete="off" />
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 gap-1">
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelLatFilter">
                                        <span>Latitude</span>
                                        <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelLatFilter" name="latitude" type="number" value="-1.24536" max="90" min="-90" placeholder="e.g. -6.2" step="0.000001" />
                                    </label>
                                    <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelLonFilter">
                                        <span>Longitude</span>
                                        <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-1.5 py-0.5 text-sm focus:outline-none focus:ring" id="sentinelLonFilter" name="longitude" type="number" value="114.54535" max="180" min="-180" placeholder="e.g. 106.8" step="0.000001" />
                                    </label>
                                </div>
                            </div>
                            <p class="text-foreground/60 text-[11px]">Provide both latitude and longitude to focus on a specific location, or clear both fields to search globally.</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <button class="bg-primary hover:bg-primary/90 text-background inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold transition" type="submit">
                                    Apply Filters
                                </button>
                            </div>
                        </form>

                        <div class="text-foreground/70 mt-4 text-sm" id="sentinelCollectionStatus">
                            Loading latest Sentinel-2 acquisitions...
                        </div>
                        <div class="mt-2 space-y-2" id="sentinelCollectionList"></div>
                    </div>

                    <!-- Clip Tab Content -->
                    <div class="tab-content hidden" id="sentinel-clip-panel" role="tabpanel" aria-labelledby="sentinel-clip-tab">
                        <div class="space-y-3" id="sentinelClipModule" data-credit-rate="{{ config('app-constants.imagery_credit_cost_per_hectare') }}" data-process-url="{{ auth()->check() ? route('admin.sentinel.process') : '' }}" data-clip-process-url="{{ auth()->check() ? route('admin.sentinel.clip') : '' }}" data-processing-cost="{{ config('app-constants.imagery_processing_cost', 10) }}">
                            <div class="bg-background/60 border-foreground/10 space-y-3 rounded-lg border p-3 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h4 class="text-foreground font-semibold">Define Area of Interest</h4>
                                        <p class="text-foreground/70 text-sm">Draw a polygon on the map to clip Sentinel-2 imagery for your field.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-button-primary id="drawPolygonBtn" type="button" size="xsmall">
                                            <i class="ri-pencil-line"></i>
                                            <span>Draw Polygon</span>
                                        </x-button-primary>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipAreaOutput">Area (hectares)</x-input-label>
                                    <div class="border-foreground/20 rounded-lg border border-dashed px-3 py-2 text-sm" id="clipAreaOutput">
                                        Draw a polygon to calculate the area.
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipCreditOutput">Estimated Credit Cost</x-input-label>
                                    <div class="border-foreground/20 rounded-lg border px-3 py-2 text-sm" id="clipCreditOutput">
                                        –
                                    </div>
                                    <p class="text-foreground/60 text-xs">{{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), locale: app()->getLocale()) }} credit points per hectare.</p>
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipGeojsonOutput">AOI GeoJSON</x-input-label>
                                    <div class="border-foreground/10 bg-foreground/5 max-h-14 overflow-auto rounded-lg border p-2 font-mono text-xs" id="clipGeojsonOutput">
                                        <span class="text-foreground/60">Coordinates will appear here after drawing.</span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipFieldName">Field Name</x-input-label>
                                    <x-text-input class="w-full" id="clipFieldName" name="field_name" size="small" placeholder="e.g. North Farm Block" />
                                </div>
                            </div>

                            <div class="bg-background/60 border-foreground/10 flex gap-2 rounded-lg border p-3 shadow-sm">
                                <div class="flex flex-col gap-2 md:items-center">
                                    <div>
                                        <h5 class="text-foreground text-sm font-semibold uppercase tracking-wide">Process Clipped Imagery</h5>
                                        <p class="text-foreground/60 mt-1 text-xs" id="clipProcessStatus">Draw an area and provide a field name before processing.</p>
                                        <p class="text-foreground/60 mt-1 text-xs" id="clipProcessStatus">The system will analyse the date range and automatically choose the latest Sentinel-2 scene intersecting your polygon.</p>
                                    </div>
                                    @auth
                                        <x-button-primary id="clipProcessImageryBtn" type="button" size="small">
                                            <i class="ri-cpu-line"></i>
                                            <span>Process Imagery</span>
                                        </x-button-primary>
                                    @else
                                        <button class="bg-success/10 text-success inline-flex items-center space-x-1 rounded-lg px-3 py-2 text-sm font-semibold opacity-60" type="button" title="Log in to process imagery" disabled>
                                            <i class="ri-cpu-line"></i>
                                            <span>Process Imagery</span>
                                        </button>
                                    @endauth
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <div class="text-foreground/70 text-xs">
                        Data source: Copernicus Sentinel-2 (via public catalogue)
                    </div>
                </div>

                <template id="sentinelCollectionTemplate">
                    <div class="sentinel-card border-foreground/20 bg-background/60 flex flex-col rounded-xl border p-2 shadow-sm transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start space-x-2">
                            <div class="border-foreground/10 bg-muted text-foreground/50 h-15 w-15 flex flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border" data-sentinel-thumb>
                                <img class="hidden h-full w-full object-cover" data-sentinel-thumbnail alt="Sentinel-2 preview" />
                                <div class="flex flex-col items-center text-[10px] font-medium" data-sentinel-placeholder>
                                    <i class="ri-landscape-line text-lg"></i>
                                    <span>Preview</span>
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col space-y-1">
                                <p class="text-foreground break-all text-sm font-semibold" data-sentinel-title>Sentinel-2 Tile</p>
                                <p class="text-foreground/70 break-all text-xs" data-sentinel-product>Product ID</p>
                                <p class="text-foreground/80 truncate text-xs" data-sentinel-datetime>Acquired:</p>
                                <p class="text-foreground/60 break-words text-xs" data-sentinel-details>Tile • Cloud cover</p>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1" data-sentinel-actions>
                            <a class="bg-primary text-background enabled:hover:bg-primary/90 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" data-sentinel-download href="#" aria-disabled="true" target="_blank" rel="noopener noreferrer">
                                <i class="ri-download-cloud-2-line"></i>
                                <span>Download Scene</span>
                            </a>
                            @auth
                                <button class="bg-success/10 text-success enabled:hover:bg-success/20 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" data-sentinel-process type="button">
                                    <i class="ri-cpu-line"></i>
                                    <span>Process Imagery</span>
                                </button>
                            @else
                                <button class="bg-success/10 text-success enabled:hover:bg-success/20 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" type="button" title="Log in to process imagery" disabled>
                                    <i class="ri-cpu-line"></i>
                                    <span>Process This Imagery</span>
                                </button>
                            @endauth
                            <button class="enabled:hover:bg-primary/10 text-primary border-primary/40 inline-flex items-center space-x-1 rounded-lg border px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50" data-sentinel-preview type="button">
                                <i class="ri-image-line"></i>
                                <span>Preview on Map</span>
                            </button>
                        </div>
                    </div>
                </template>
            </section>

            <!-- ========== Upload PANEL ========== -->
            <section class="flex hidden h-full flex-col shadow-xl" id="uploads-panel">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">⬆️ Imagery Upload</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <!-- content -->
                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <div class="space-y-2">
                        @auth
                            <div class="bg-primary/10 space-y-2 rounded-lg p-2">
                                <h4 class="text-foreground flex items-center text-lg font-semibold">
                                    <i class="ri-upload-cloud-line text-primary mr-2"></i>
                                    Upload Your Own Imagery
                                </h4>
                                <p class="text-foreground/80 text-sm">
                                    Have your own satellite imagery? Upload it directly to our platform for advanced PRI analysis and crop health monitoring.
                                </p>
                                <p class="text-foreground/80 text-sm font-bold">Follow the instructions.</p>

                                <div class="bg-primary/20 space-y-2 rounded-lg p-2">
                                    <h5 class="text-foreground font-medium">Supported Formats</h5>
                                    <ul class="text-foreground/70 space-y-1 text-sm">
                                        <li class="flex items-center">
                                            <i class="ri-check-line text-success mr-2 text-xs"></i>
                                            <span>GeoTIFF (.tif, .tiff, .geotif)</span>
                                        </li>
                                        {{-- <li class="flex items-center">
                                            <i class="ri-check-line text-success mr-2 text-xs"></i>
                                            <span>Enhanced Compressed Wavelet (.ecw)</span>
                                        </li>
                                        <li class="flex items-center">
                                            <i class="ri-check-line text-success mr-2 text-xs"></i>
                                            <span>ZIP Archives (.zip)</span>
                                        </li> --}}
                                    </ul>
                                </div>

                                <div class="bg-primary/20 space-y-2 rounded-lg p-2">
                                    <h5 class="text-foreground font-medium">Compatible Sources</h5>
                                    <ul class="text-foreground/70 space-y-1 text-sm">
                                        <li class="flex items-start">
                                            <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                            <span>Sentinel-2 [band order: 1, 2, 3, 4, 5, 6, 7, 8, 8A, 9, 11, 12]</span>
                                        </li>
                                        {{-- <li class="flex items-start">
                                            <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                            <span>Landsat 8/7</span>
                                        </li> --}}
                                        <li class="flex items-start">
                                            <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                            <span>Get detailed plant stress analysis with our AI-powered engine</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="space-y-3">
                                    <h5 class="text-lg font-semibold">Upload Your File</h5>

                                    <!-- input form -->
                                    <form class="space-y-2">
                                        <x-input-label class="text-sm font-medium" for="source-type">Source Type</x-input-label>
                                        <x-select-input class="px-2! py-1!" id="sourceType" name="source-type" required>
                                            <option value="sentinel-2">Sentinel-2</option>
                                            <option value="landsat">Landsat</option>
                                            <option value="quicksat">Quicksat</option>
                                        </x-select-input>
                                        <x-input-error class="mt-2" :messages="$errors->get('source-type')" />

                                        <x-input-label class="text-sm font-medium" for="imagery-upload">Upload your imagery file</x-input-label>
                                        <input class="border-foreground/30 bg-neutral file:bg-foreground/10 focus:border-primary focus:ring-primary block w-full rounded-lg border text-sm shadow-sm file:me-4 file:border-0 file:px-4 file:py-2 focus:z-10 disabled:pointer-events-none disabled:opacity-50" id="fileInput" name="imagery-upload" type="file" accept=".tif,.tiff,.ecw,.zip">
                                        <x-input-error class="mt-2" :messages="$errors->get('imagery-upload')" />
                                    </form>

                                    <!-- info file -->
                                    <div class="text-foreground-70 mt-2 hidden text-sm" id="fileInfo"></div>

                                    <!-- progress bar -->
                                    <div class="bg-foreground/20 mt-2 h-4 w-full rounded">
                                        <div class="bg-primary h-4 rounded" id="progressBar" style="width: 0%;"></div>
                                    </div>
                                    <p class="text-foreground/100 mt-1 text-sm" id="progressText">Belum ada upload.</p>

                                    <!-- tombol kontrol -->
                                    <div class="flex flex-wrap gap-2">
                                        <x-button-primary id="startBtn" type="button" size="small">Start Upload</x-button-primary>
                                        <x-button-danger id="pauseBtn" type="button" size="small">⏸️ Pause</x-button-danger>
                                        <x-button-secondary id="resumeBtn" type="button" size="small">▶️ Resume</x-button-secondary>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-3 text-center">
                                <div class="mb-3">
                                    <div class="bg-muted mx-auto flex h-20 w-20 items-center justify-center rounded-full">
                                        <i class="ri-file-warning-line text-foreground/80 text-3xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-foreground/80 mb-2 text-lg font-semibold">No Access</h3>
                                <p class="text-foreground/60 mb-4 px-4 text-sm">
                                    You don't have access to satellite imagery data. Upload or purchase satellite imagery to start monitoring your crops and analyzing plant stress using PRI.
                                </p>
                                <x-button-primary type="button" href="{{ route('login') }}" size="small">
                                    <i class="ri-login-box-line"></i>
                                    <span>Login to Access Your Data</span>
                                </x-button-primary>
                            </div>
                        @endauth
                    </div>
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
            <div class="absolute left-2 right-2 top-12 flex w-1/2 items-center justify-between md:top-2 md:w-1/3">
                <div class="relative w-full">
                    <x-text-input class="w-full p-1 pr-8" id="search-location" type="text" size="small" placeholder="Search Location, Address, or Cordinate" />
                    <!-- Clear button -->
                    <button class="text-foreground/100 hover:text-foreground/80 absolute right-2 top-1/2 hidden -translate-y-1/2 transform" id="clear-search" type="button">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>
                <!-- Search Results Recommendation Container -->
                <div class="border-foreground/30 bg-background absolute left-0 top-full z-50 mt-1 hidden max-h-[300px] w-full overflow-y-auto rounded-lg border shadow-lg" id="search-results-recommendation">
                    <!-- Results will be dynamically inserted here -->
                </div>
            </div>

            <!-- Sentinel Preview Panel -->
            <div class="absolute bottom-0 right-0 top-auto z-50 flex w-full justify-end shadow-xl md:bottom-auto md:right-2 md:top-20 md:w-80">
                <div class="border-foreground/15 bg-background/95 supports-[backdrop-filter]:bg-background/70 pointer-events-auto hidden break-all rounded-xl border p-2 text-xs shadow-xl backdrop-blur" id="sentinelPreviewPanel">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <p class="text-primary text-[10px] font-semibold uppercase tracking-wide">Sentinel-2 Preview</p>
                            <p class="text-foreground text-sm font-semibold leading-tight" data-sentinel-preview-title>–</p>
                            <p class="text-foreground/70 text-xs leading-tight" data-sentinel-preview-acquired>Select a collection to preview on the map.</p>
                            <p class="text-foreground/60 hidden text-xs leading-tight" data-sentinel-preview-details></p>
                        </div>
                        <button class="text-foreground/60 hover:text-foreground focus-visible:ring-primary/50 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-transparent transition focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-40" id="sentinelPreviewClearBtn" type="button" title="Tutup panel preview">
                            <i class="ri-close-line text-sm"></i>
                            <span class="sr-only">Tutup preview Sentinel</span>
                        </button>
                    </div>
                    <p class="text-foreground/70 mt-2 text-xs" data-sentinel-preview-status>Awaiting preview selection.</p>
                    <div class="mt-2 flex flex-wrap gap-1.5" id="sentinelPreviewActions">
                        <button class="bg-foreground/10 hover:bg-foreground/20 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" id="sentinelPreviewImageryBtn" type="button" aria-pressed="false" aria-disabled="true">
                            <i class="ri-eye-line text-sm" data-sentinel-preview-imagery-icon></i>
                            <span data-sentinel-preview-imagery-label>Preview Imagery</span>
                        </button>
                        <a class="bg-primary text-background hover:bg-primary/90 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" id="sentinelPreviewDownloadBtn" href="#" aria-disabled="true" target="_blank" rel="noopener noreferrer">
                            <i class="ri-download-cloud-2-line text-sm"></i>
                            <span>Download Scene</span>
                        </a>
                    </div>
                </div>
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
            <div class="absolute bottom-8 left-0 z-40 flex items-end space-x-2 text-xs md:left-2 md:text-base">
                <!-- Basemap Buttons -->
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

            <!-- Info panel -->
            {{-- <div class="shadow-soft bottom-22 pointer-events-none absolute left-0 z-40 mx-auto hidden max-w-xl rounded-lg bg-white/90 p-2 ring-1 ring-black/5 backdrop-blur supports-[backdrop-filter]:bg-white/60 sm:right-auto sm:w-[380px] md:left-20" id="panel">
                <div class="pointer-events-auto">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-semibold" id="panelTitle">Info Panel Title</h2>
                            <p>Lorem ipsum dolor sit amet consectetur, adipisicing elit. Aspernatur, labore facilis! Fuga, ab molestiae?</p>
                        </div>
                        <button class="border-foreground/30 hover:bg-foreground/10 rounded-lg border bg-white px-2 py-1 text-xs" id="btnClear">Clear</button>
                    </div>
                    <div class="border-foreground/30 mt-3 hidden rounded-xl border bg-white p-3 text-sm" id="routeInfo">
                        <div class="font-medium">Rute</div>
                        <div class="text-foreground/80 mt-1 space-y-1">
                            <div id="routeSummary">Belum ada rute.</div>
                            <div class="nice-scrollbar max-h-40 overflow-auto" id="routeSteps"></div>
                        </div>
                    </div>
                </div>
            </div> --}}

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


    </div>
    @push('javascript')
        <script>
            (() => {
                if (typeof window === 'undefined') {
                    return;
                }

                const config = @json($geoserverConfig ?? []);
                window.AppMap = window.AppMap || {};
                window.AppMap.geoserver = {
                    workspace: config.workspace || '',
                    wmsUrl: config.wmsUrl || '',
                    wmtsUrl: config.wmtsUrl || '',
                };

                if (typeof window.ol === 'undefined') {
                    console.warn('OpenLayers is not available; GeoServer imagery layers are disabled.');
                    return;
                }

                const state = {
                    map: null,
                    layers: new Map(),
                    queue: [],
                };

                const normaliseVariant = (variant) => {
                    if (typeof variant !== 'string') {
                        return 'processed';
                    }

                    const value = variant.toLowerCase();
                    return value === 'source' ? 'source' : 'processed';
                };

                const buildLayerKey = (id, variant) => `${id}:${normaliseVariant(variant)}`;

                const hasAnyLayerForId = (id) => {
                    if (!id) {
                        return false;
                    }

                    const prefix = `${id}:`;
                    for (const key of state.layers.keys()) {
                        if (key.startsWith(prefix)) {
                            return true;
                        }
                    }

                    return false;
                };

                const dispatchVisibility = (id, variant, visible) => {
                    const normalisedVariant = normaliseVariant(variant);

                    document.dispatchEvent(
                        new CustomEvent('app:imagery:layer-visibility', {
                            detail: {
                                id,
                                variant: normalisedVariant,
                                visible: Boolean(visible),
                                anyVisible: hasAnyLayerForId(id),
                            },
                        })
                    );
                };

                const ensureMap = (callback) => {
                    if (state.map) {
                        callback(state.map);
                        return;
                    }

                    state.queue.push(callback);
                };

                const registerMap = (mapInstance) => {
                    state.map = mapInstance;
                    if (!state.queue.length) {
                        return;
                    }

                    const pending = state.queue.splice(0, state.queue.length);
                    pending.forEach((callback) => {
                        try {
                            callback(mapInstance);
                        } catch (error) {
                            console.error('Deferred map callback failed', error);
                        }
                    });
                };

                const resolveLayerName = (item, variant) => {
                    const normalisedVariant = normaliseVariant(variant);

                    if (!item) {
                        return null;
                    }

                    if (normalisedVariant === 'source') {
                        return item.geoserver_source_layer || null;
                    }

                    return item.geoserver_processed_layer || item.geoserver_layer || null;
                };

                const resolveBoundingBox = (item, variant) => {
                    if (!item) {
                        return null;
                    }

                    const normalisedVariant = normaliseVariant(variant);
                    const source = normalisedVariant === 'source'
                        ? item.geoserver_source_bbox ?? null
                        : item.geoserver_processed_bbox ?? item.geoserver_bbox ?? null;

                    if (!source) {
                        return null;
                    }

                    if (source.latLon) {
                        return source.latLon;
                    }

                    return source;
                };

                const createLayer = (item, variant) => {
                    const geoserver = window.AppMap.geoserver || {};
                    const normalisedVariant = normaliseVariant(variant);
                    const layerName = resolveLayerName(item, normalisedVariant);

                    if (!geoserver.wmsUrl || !layerName) {
                        return null;
                    }

                    const bbox = resolveBoundingBox(item, normalisedVariant);
                    const wmsParams = {
                        LAYERS: layerName,
                        TILED: true,
                        FORMAT: 'image/png',
                        TRANSPARENT: true,
                        STYLES: '',
                        VERSION: '1.1.1',
                    };

                    const declaredCrs = bbox?.crs || bbox?.crsCode || null;
                    if (declaredCrs) {
                        wmsParams.CRS = declaredCrs;
                        wmsParams.SRS = declaredCrs;
                    }

                    const source = new ol.source.TileWMS({
                        url: geoserver.wmsUrl,
                        params: wmsParams,
                        serverType: 'geoserver',
                        crossOrigin: 'anonymous',
                        transition: 0,
                    });

                    const layer = new ol.layer.Tile({
                        source,
                        opacity: 1,
                        visible: true,
                    });

                    layer.set('imageryId', item?.id || null);
                    layer.set('imageryVariant', normaliseVariant(variant));
                    return layer;
                };

                const fitToLayer = (mapInstance, item, variant) => {
                    const bbox = resolveBoundingBox(item, variant);
                    if (!bbox) {
                        return;
                    }

                    const extent = [bbox.minx, bbox.miny, bbox.maxx, bbox.maxy];
                    const view = mapInstance.getView?.();
                    if (!view) {
                        return;
                    }

                    let transformedExtent = extent;
                    const projection = view.getProjection?.();
                    if (
                        projection &&
                        projection.getCode &&
                        projection.getCode() !== 'EPSG:4326' &&
                        window.ol?.proj?.transformExtent
                    ) {
                        try {
                            transformedExtent = window.ol.proj.transformExtent(
                                extent,
                                'EPSG:4326',
                                projection
                            );
                        } catch (error) {
                            console.warn('Failed to transform extent for imagery layer', error);
                        }
                    }

                    view.fit(transformedExtent, {
                        duration: 700,
                        padding: [64, 64, 64, 64],
                        maxZoom: 16,
                    });
                };

                const toggleLayer = (item, variant = 'processed') => {
                    const id = item?.id;
                    const normalisedVariant = normaliseVariant(variant);

                    if (!id) {
                        return;
                    }

                    const layerName = resolveLayerName(item, normalisedVariant);
                    if (!layerName) {
                        window.MyZkToast?.warning?.('GeoServer layer is not ready yet.');
                        return;
                    }

                    ensureMap((mapInstance) => {
                        const key = buildLayerKey(id, normalisedVariant);
                        const existing = state.layers.get(key);
                        if (existing) {
                            mapInstance.removeLayer(existing);
                            state.layers.delete(key);
                            dispatchVisibility(id, normalisedVariant, false);
                            return;
                        }

                        const layer = createLayer(item, normalisedVariant);
                        if (!layer) {
                            dispatchVisibility(id, normalisedVariant, false);
                            return;
                        }

                        state.layers.set(key, layer);
                        mapInstance.addLayer(layer);
                        dispatchVisibility(id, normalisedVariant, true);
                        fitToLayer(mapInstance, item, normalisedVariant);
                    });
                };

                const removeLayer = (id, variant = 'processed') => {
                    if (!id) {
                        return;
                    }

                    const normalisedVariant = normaliseVariant(variant);
                    const key = buildLayerKey(id, normalisedVariant);
                    const layer = state.layers.get(key);
                    if (!layer || !state.map) {
                        return;
                    }

                    state.map.removeLayer(layer);
                    state.layers.delete(key);
                    dispatchVisibility(id, normalisedVariant, false);
                };

                const removeAllLayers = (id) => {
                    if (!id || !state.map) {
                        return;
                    }

                    const keysToRemove = [];
                    for (const key of state.layers.keys()) {
                        if (key.startsWith(`${id}:`)) {
                            keysToRemove.push(key);
                        }
                    }

                    keysToRemove.forEach((key) => {
                        const layer = state.layers.get(key);
                        if (layer) {
                            state.map.removeLayer(layer);
                        }
                        state.layers.delete(key);
                        const [, variant = 'processed'] = key.split(':');
                        dispatchVisibility(id, normaliseVariant(variant), false);
                    });
                };

                const isLayerVisible = (id, variant = 'processed') => {
                    if (!id) {
                        return false;
                    }

                    return state.layers.has(buildLayerKey(id, variant));
                };

                const isAnyLayerVisible = (id) => hasAnyLayerForId(id);

                window.AppMap.imagery = {
                    toggleLayer,
                    removeLayer,
                    removeAllLayers,
                    isLayerVisible,
                    isAnyLayerVisible,
                };

                window.addEventListener(
                    'map:ready',
                    (event) => {
                        const mapInstance = event.detail?.map;
                        if (mapInstance) {
                            registerMap(mapInstance);
                        }
                    },
                    { once: true }
                );
            })();
        </script>
    @endpush
    @push('javascript')
        <script>
            (() => {
                // Centralized DOM references used by the panel controller.
                const selectors = {
                    panelWrapper: document.getElementById('panel-wrapper'),
                    panels: Array.from(document.querySelectorAll('#panel-wrapper section')),
                    sidebarButtons: Array.from(document.querySelectorAll('.sidebar-btn')),
                    scroll: {
                        container: document.getElementById('scroll-container'),
                        left: document.getElementById('scroll-left'),
                        right: document.getElementById('scroll-right')
                    }
                };

                if (!selectors.panelWrapper) {
                    return;
                }

                window.AppMap = window.AppMap || {};

                // Track the currently visible panel via a getter/setter for clarity.
                const state = {
                    get activePanelId() {
                        return selectors.panelWrapper.dataset.activePanel || null;
                    },
                    set activePanelId(value) {
                        if (!value) {
                            delete selectors.panelWrapper.dataset.activePanel;
                            return;
                        }
                        selectors.panelWrapper.dataset.activePanel = value;
                    }
                };

                // Quick helper that checks the viewport width to decide layout mode.
                const isMobileView = () => window.innerWidth < 768;

                // Ensure a sidebar button exposes its target panel id for later lookups.
                const resolvePanelTarget = (button) => {
                    if (!button) return null;
                    if (!button.dataset.panelTarget) {
                        const inline = button.getAttribute('onclick') || '';
                        const match = inline.match(/showPanel\('([^']+)'/);
                        if (match) {
                            button.dataset.panelTarget = match[1];
                        }
                    }
                    return button.dataset.panelTarget || null;
                };

                // Resolve every sidebar button that points to the given panel id.
                const getButtonsByPanel = (panelId) => {
                    if (!panelId) return [];
                    return selectors.sidebarButtons.filter((button) => resolvePanelTarget(button) === panelId);
                };

                // Ask the Sentinel module to load its catalogue as soon as it is ready.
                const requestSentinelCatalogue = () => {
                    const sentinelModule = window.AppMap?.sentinel;
                    if (sentinelModule?.loadCollections && !sentinelModule.loadedOnce) {
                        sentinelModule.loadCollections();
                        return;
                    }

                    if (!sentinelModule) {
                        document.addEventListener('app:sentinel:ready', (event) => {
                            const module = event.detail;
                            if (module?.loadCollections && !module.loadedOnce) {
                                module.loadCollections();
                            }
                        }, {
                            once: true
                        });
                    }
                };

                // Hide every panel element before showing the newly selected one.
                const hideAllPanels = () => {
                    selectors.panels.forEach((panel) => panel.classList.add('hidden'));
                };

                // Apply the active state on the button that triggered the panel change.
                const setActiveButton = (panelId, triggerButton) => {
                    selectors.sidebarButtons.forEach((button) => {
                        button.classList.remove('active');
                        resolvePanelTarget(button);
                    });

                    if (triggerButton) {
                        triggerButton.classList.add('active');
                        triggerButton.dataset.panelTarget = panelId;
                    }

                    const relatedButtons = getButtonsByPanel(panelId);
                    relatedButtons.forEach((button) => button.classList.add('active'));
                };

                // Toggle classes that animate the panel opening for mobile and desktop.
                const animatePanelOpen = () => {
                    if (isMobileView()) {
                        selectors.panelWrapper.classList.remove('translate-y-full', 'slide-down');
                        selectors.panelWrapper.classList.add('translate-y-0', 'slide-up');
                        return;
                    }

                    selectors.panelWrapper.classList.remove('w-0', 'md:w-0');
                    selectors.panelWrapper.classList.add('w-80', 'md:w-80');
                };

                // Revert the open animation by hiding or collapsing the panel wrapper.
                const animatePanelClose = () => {
                    if (isMobileView()) {
                        selectors.panelWrapper.classList.remove('slide-up');
                        selectors.panelWrapper.classList.add('slide-down');
                        setTimeout(() => {
                            selectors.panelWrapper.classList.remove('translate-y-0');
                            selectors.panelWrapper.classList.add('translate-y-full');
                        }, 480);
                        return;
                    }

                    selectors.panelWrapper.classList.remove('w-80', 'md:w-80');
                    selectors.panelWrapper.classList.add('w-0', 'md:w-0');
                };

                // Display a specific panel while making sure supporting UI stays in sync.
                const showPanel = (panelId, triggerButton = null) => {
                    const targetPanel = document.getElementById(panelId);
                    if (!targetPanel) {
                        console.warn(`Panel with id "${panelId}" not found.`);
                        return;
                    }

                    hideAllPanels();
                    targetPanel.classList.remove('hidden');

                    setActiveButton(panelId, triggerButton);
                    animatePanelOpen();

                    state.activePanelId = panelId;

                    if (panelId === 'sentinel-panel') {
                        requestSentinelCatalogue();
                    }
                };

                // Close the active panel and reset the wrapper back to its hidden state.
                const closePanels = () => {
                    hideAllPanels();
                    selectors.sidebarButtons.forEach((button) => button.classList.remove('active'));
                    animatePanelClose();
                    state.activePanelId = null;
                };

                // Ensure the panel layout matches the current viewport (mobile vs. desktop).
                const syncPanelLayoutWithViewport = () => {
                    const activePanel = state.activePanelId;

                    selectors.panelWrapper.classList.remove('slide-up', 'slide-down');

                    if (!activePanel) {
                        selectors.panelWrapper.classList.add('translate-y-full');
                        selectors.panelWrapper.classList.remove('translate-y-0', 'w-80', 'md:w-80');
                        return;
                    }

                    if (isMobileView()) {
                        selectors.panelWrapper.classList.remove('w-0', 'md:w-0', 'w-80', 'md:w-80');
                        selectors.panelWrapper.classList.remove('translate-y-full');
                        selectors.panelWrapper.classList.add('translate-y-0', 'opacity-100');
                    } else {
                        selectors.panelWrapper.classList.remove('translate-y-full', 'translate-y-0');
                        selectors.panelWrapper.classList.add('w-80', 'md:w-80', 'opacity-100');
                    }

                    const activeButtons = getButtonsByPanel(activePanel);
                    selectors.sidebarButtons.forEach((button) => button.classList.remove('active'));
                    activeButtons.forEach((button) => button.classList.add('active'));
                };

                // Setup horizontal scrolling interaction for the mobile navigation pills.
                const initialiseHorizontalScroll = () => {
                    const {
                        container,
                        left,
                        right
                    } = selectors.scroll;
                    if (!container || !left || !right) {
                        return;
                    }

                    // Scroll the button container by the provided amount with smooth motion.
                    const scrollByAmount = (amount) => {
                        container.scrollBy({
                            left: amount,
                            behavior: 'smooth'
                        });
                    };

                    left.addEventListener('click', () => scrollByAmount(-150));
                    right.addEventListener('click', () => scrollByAmount(150));

                    let isDragging = false;
                    let dragStartX = 0;
                    let scrollStartLeft = 0;

                    // Allow mouse dragging to scroll the mobile navigation list.
                    container.addEventListener('mousedown', (event) => {
                        isDragging = true;
                        container.classList.add('cursor-grabbing');
                        dragStartX = event.pageX - container.offsetLeft;
                        scrollStartLeft = container.scrollLeft;
                    });

                    // Reset dragging flags when the pointer leaves or releases the container.
                    const stopDragging = () => {
                        isDragging = false;
                        container.classList.remove('cursor-grabbing');
                    };

                    container.addEventListener('mouseleave', stopDragging);
                    container.addEventListener('mouseup', stopDragging);

                    container.addEventListener('mousemove', (event) => {
                        if (!isDragging) return;
                        event.preventDefault();
                        const currentX = event.pageX - container.offsetLeft;
                        const delta = (currentX - dragStartX) * 2;
                        container.scrollLeft = scrollStartLeft - delta;
                    });
                };

                // Automatically open the data panel on initial load.
                const initialiseDefaultPanel = () => {
                    const defaultPanelId = 'data-panel';
                    const button = isMobileView() ?
                        document.querySelector(`#scroll-container .sidebar-btn[onclick*='${defaultPanelId}']`) :
                        document.querySelector(`aside .sidebar-btn[onclick*='${defaultPanelId}']`);

                    showPanel(defaultPanelId, button);
                };

                // Connect resize handlers relevant to the dashboard.
                const registerEventListeners = () => {
                    window.addEventListener('resize', syncPanelLayoutWithViewport);
                };

                const bootstrapPanels = () => {
                    window.showPanel = showPanel;
                    window.closePanels = closePanels;

                    initialiseHorizontalScroll();
                    initialiseDefaultPanel();
                    registerEventListeners();
                    syncPanelLayoutWithViewport();

                    const sentinelModule = window.AppMap?.sentinel;
                    if (sentinelModule?.loadCollections && !sentinelModule.loadedOnce) {
                        sentinelModule.loadCollections();
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootstrapPanels, {
                        once: true
                    });
                } else {
                    bootstrapPanels();
                }
            })();
        </script>
    @endpush

    @push('javascript')
        @auth
            <script>
                (() => {
                    // Cache frequently used DOM references for the uploader workflow.
                    const elements = {
                        sourceInput: document.getElementById('sourceType'),
                        fileInput: document.getElementById('fileInput'),
                        fileInfo: document.getElementById('fileInfo'),
                        progressBar: document.getElementById('progressBar'),
                        progressText: document.getElementById('progressText'),
                        startBtn: document.getElementById('startBtn'),
                        pauseBtn: document.getElementById('pauseBtn'),
                        resumeBtn: document.getElementById('resumeBtn'),
                        myDataContainer: document.getElementById('myDataContainer'),
                        cardTemplate: document.getElementById('imageryCardTemplate')
                    };

                    const allElementsReady = Object.values(elements).every(Boolean);

                    if (!allElementsReady) {
                        console.warn('Imagery uploader controls missing. Skipping uploader bootstrap.', {
                            hasSourceInput: Boolean(elements.sourceInput),
                            hasFileInput: Boolean(elements.fileInput),
                            hasFileInfo: Boolean(elements.fileInfo),
                            hasProgressBar: Boolean(elements.progressBar),
                            hasProgressText: Boolean(elements.progressText),
                            hasStartBtn: Boolean(elements.startBtn),
                            hasPauseBtn: Boolean(elements.pauseBtn),
                            hasResumeBtn: Boolean(elements.resumeBtn),
                            hasMyDataContainer: Boolean(elements.myDataContainer),
                            hasTemplate: Boolean(elements.cardTemplate)
                        });
                        return;
                    }

                    // Configuration values that control chunking and retry behaviour.
                    const config = {
                        chunkSize: 5 * 1024 * 1024,
                        maxRetries: 3,
                        autoResetDelay: 4000,
                        imageryProcessingCost: {{ config('app-constants.imagery_processing_cost', 10) }}
                    };

                    // Endpoints required throughout the upload process.
                    const endpoints = {
                        chunk: '{{ route('upload.chunk') }}',
                        merge: '{{ route('upload.merge') }}',
                        list: '{{ route('imagery.list') }}'
                    };

                    // Mutable state object tracking progress and timings.
                    const state = {
                        paused: false,
                        uploading: false,
                        file: null,
                        uploadId: null,
                        currentChunk: 0,
                        totalChunks: 0,
                        startTime: 0,
                        uploadedBytes: 0
                    };

                    // Ensure the uploader exposes hooks on the global AppMap namespace.
                    const ensureAppNamespace = () => {
                        window.AppMap = window.AppMap || {};
                        window.AppMap.uploader = window.AppMap.uploader || {};
                    };

                    // Toggle button states based on the current upload lifecycle stage.
                    const setButtonState = (mode) => {
                        const {
                            startBtn,
                            pauseBtn,
                            resumeBtn
                        } = elements;

                        const disableAll = () => {
                            startBtn.disabled = true;
                            pauseBtn.disabled = true;
                            resumeBtn.disabled = true;
                        };

                        switch (mode) {
                            case 'ready':
                                startBtn.disabled = false;
                                pauseBtn.disabled = true;
                                resumeBtn.disabled = true;
                                // Restore original button text if it was changed to loading state
                                if (startBtn.innerHTML.includes('Checking')) {
                                    startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                                }
                                break;
                            case 'uploading':
                                startBtn.disabled = true;
                                pauseBtn.disabled = false;
                                resumeBtn.disabled = true;
                                break;
                            case 'paused':
                                startBtn.disabled = true;
                                pauseBtn.disabled = true;
                                resumeBtn.disabled = false;
                                break;
                            case 'merging':
                            case 'completed':
                            case 'error':
                                disableAll();
                                if (mode === 'error') {
                                    startBtn.disabled = false;
                                }
                                // Restore original button text if it was changed to loading state
                                if (startBtn.innerHTML.includes('Checking')) {
                                    startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                                }
                                break;
                            case 'loading':
                                startBtn.disabled = true;
                                startBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Checking...';
                                pauseBtn.disabled = true;
                                resumeBtn.disabled = true;
                                break;
                            case 'idle':
                            default:
                                disableAll();
                                // Restore original button text if it was changed to loading state
                                if (startBtn.innerHTML.includes('Checking')) {
                                    startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                                }
                                break;
                        }
                    };

                    // Clear all runtime bookkeeping for a fresh upload session.
                    const resetState = () => {
                        state.paused = false;
                        state.uploading = false;
                        state.file = null;
                        state.uploadId = null;
                        state.currentChunk = 0;
                        state.totalChunks = 0;
                        state.startTime = 0;
                        state.uploadedBytes = 0;
                    };

                    // Restore the UI to an idle appearance without progress.
                    const resetUI = () => {
                        const {
                            fileInput,
                            fileInfo,
                            progressBar,
                            progressText
                        } = elements;
                        fileInput.value = '';
                        fileInfo.classList.add('hidden');
                        fileInfo.innerHTML = '';
                        progressBar.style.width = '0%';
                        progressText.textContent = 'Ready for next upload.';
                    };

                    // Present the selected file name and size before uploading.
                    const showFileSummary = (file) => {
                        const {
                            fileInfo,
                            progressBar,
                            progressText
                        } = elements;
                        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                        const shortName = shortenFilename(file.name, 40);

                        fileInfo.classList.remove('hidden');
                        fileInfo.innerHTML = `
                    <strong>Name:</strong> ${shortName}<br>
                    <strong>Size:</strong> ${sizeMB} MB
                    `;

                        progressText.textContent = "✅ File ready to upload. Click 'Start Upload' to begin.";
                        progressBar.style.width = '0%';
                    };

                    // Update progress bar width and accompanying status text.
                    const updateProgressDisplay = (percentage, speedMBps, etaText) => {
                        const {
                            progressBar,
                            progressText
                        } = elements;
                        progressBar.style.width = `${percentage}%`;
                        const speedText = Number.isFinite(speedMBps) ? speedMBps.toFixed(2) : '0.00';
                        progressText.textContent = `Uploading... ${percentage}% | 🚀 ${speedText} MB/s | ⏳ ETA: ${etaText}`;
                    };

                    // React to new file input selections and prime the uploader.
                    const handleFileChange = (event) => {
                        state.file = event.target.files?.[0] || null;

                        if (!state.file) {
                            resetState();
                            resetUI();
                            setButtonState('idle');
                            return;
                        }

                        showFileSummary(state.file);
                        MyZkToast.info('File ready to upload, click Start to begin.');
                        setButtonState('ready');
                    };

                    // Generate a lightweight identifier for coordinating chunk requests.
                    const generateUploadId = () => {
                        const timestamp = Date.now();
                        const random = Math.random().toString(36).substring(2, 10).toUpperCase();
                        return `${timestamp}_${random}`;
                    };

                    // Convert bytes remaining and elapsed seconds into a readable ETA.
                    const formatEta = (remainingBytes, elapsedSeconds) => {
                        if (!Number.isFinite(elapsedSeconds) || elapsedSeconds <= 0) {
                            return '-';
                        }
                        const speedBytesPerSecond = state.uploadedBytes / elapsedSeconds;
                        if (speedBytesPerSecond <= 0) {
                            return '-';
                        }
                        const remainingSeconds = remainingBytes / speedBytesPerSecond;
                        return remainingSeconds > 0 ? formatTimeETA(remainingSeconds) : '-';
                    };

                    // Upload the next chunk and retry if transient errors occur.
                    const uploadNextChunk = async (retryCount = 0) => {
                        if (state.paused || !state.file) return;

                        if (state.currentChunk >= state.totalChunks) {
                            elements.progressText.textContent = '🧩 Preparing file for background merge...';
                            await mergeChunks();
                            return;
                        }

                        const start = state.currentChunk * config.chunkSize;
                        const end = Math.min(state.file.size, start + config.chunkSize);
                        const chunk = state.file.slice(start, end);

                        const formData = new FormData();
                        formData.append('upload_id', state.uploadId);
                        formData.append('chunk_index', state.currentChunk);
                        formData.append('chunk', chunk);

                        try {
                            const response = await fetch(endpoints.chunk, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: formData
                            });

                            const payload = await response.json();
                            if (!response.ok || !payload.success) {
                                throw new Error(payload.message || `Chunk ${state.currentChunk} failed.`);
                            }

                            state.currentChunk += 1;
                            state.uploadedBytes += chunk.size;

                            const elapsedSeconds = Math.max((performance.now() - state.startTime) / 1000, 0.001);
                            const speedMBps = state.uploadedBytes / 1024 / 1024 / elapsedSeconds;
                            const remainingBytes = state.file.size - state.uploadedBytes;
                            const etaText = formatEta(remainingBytes, elapsedSeconds);
                            const progress = Math.round((state.currentChunk / state.totalChunks) * 100);

                            updateProgressDisplay(progress, speedMBps, etaText);

                            if (progress === 100) {
                                MyZkToast.info('Finalising upload on server...');
                            }

                            if (!state.paused) {
                                await uploadNextChunk();
                            }
                        } catch (error) {
                            if (retryCount < config.maxRetries) {
                                const delay = 2000 * (retryCount + 1);
                                setTimeout(() => uploadNextChunk(retryCount + 1), delay);
                                return;
                            }

                            elements.progressText.textContent = `❌ Chunk ${state.currentChunk} failed after ${config.maxRetries} retries. Upload paused.`;
                            MyZkToast.error(`Chunk ${state.currentChunk} failed after ${config.maxRetries} retries.`);
                            state.paused = true;
                            state.uploading = false;
                            setButtonState('paused');
                        }
                    };

                    // Ask the backend to merge all uploaded chunks into a single file.
                    const mergeChunks = async () => {
                        setButtonState('merging');

                        const formData = new FormData();
                        formData.append('upload_id', state.uploadId);
                        formData.append('filename', state.file.name);
                        formData.append('total_chunks', state.totalChunks);
                        formData.append('source_type', elements.sourceInput.value);

                        // Check if user has enough credits to determine processing status
                        const creditCheck = await checkUserCredits();
                        if (!creditCheck.hasCredits) {
                            formData.append('skip_processing', 'true');
                        }

                        try {
                            const response = await fetch(endpoints.merge, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: formData
                            });

                            const result = await response.json();
                            if (!response.ok || !result.success) {
                                throw new Error(result.message || 'Failed to queue merge on server.');
                            }

                            elements.progressBar.style.width = '100%';
                            elements.progressText.textContent = `✅ ${result.message || 'Upload received. Finalising in background.'}`;
                            MyZkToast.success('File received! Finalising in the background.');
                            setButtonState('completed');
                            if (result.data?.currentCredits !== undefined) {
                                $('#current-myCredits').text(formatNumber(result.data.currentCredits, 2));
                            }
                            await loadMyData();
                            scheduleAutoReset();
                        } catch (error) {
                            elements.progressText.textContent = `❌ Error: ${error.message}`;
                            MyZkToast.error(error.message || 'Server error while scheduling merge.');
                            setButtonState('error');
                            scheduleAutoReset();
                        }
                    };

                    // After finishing, reset the UI back to idle after a short delay.
                    const scheduleAutoReset = () => {
                        setTimeout(() => {
                            resetState();
                            resetUI();
                            setButtonState('idle');
                        }, config.autoResetDelay);
                    };

                    // Validate prerequisites and kick off the chunk upload loop.
                    const startUpload = () => {
                        if (!state.file) {
                            MyZkToast.warning('Please select a file first!');
                            return;
                        }

                        // Show loading state on start button while checking credits
                        setButtonState('loading');

                        // Check user credits before proceeding
                        checkUserCredits().then(res => {
                            // Restore ready state if user has credits
                            setButtonState('ready');
                            $('#current-myCredits').text(formatNumber(res.currentCredits, 2));

                            // Show confirmation modal before starting upload
                            ZkPopAlert.show({
                                message: `${res.hasCredits ? `This upload will cost ${res.requiredCredits} credit points for processing imagery. Do you want to proceed?` : `Insufficient credit points for processing. You need ${res.requiredCredits} credits. You can still upload the file, but processing will be skipped. Please purchase more credits to continue processing.`}`,
                                icon: '<i class="ri-upload-cloud-2-line text-2xl text-primary"></i>',
                                confirmClass: "focus:ring-primary/80 rounded-md text-sm px-2.5 py-1.5 bg-primary text-primary-foreground border border-primary hover:bg-primary/80 focus:outline-none focus:ring-primary",
                                confirmText: "Yes, Upload",
                                cancelText: "Cancel",
                                onConfirm: () => {
                                    state.uploadId = generateUploadId();
                                    state.totalChunks = Math.ceil(state.file.size / config.chunkSize);
                                    state.currentChunk = 0;
                                    state.uploadedBytes = 0;
                                    state.paused = false;
                                    state.uploading = true;
                                    state.startTime = performance.now();

                                    MyZkToast.info('🚀 Upload started...');
                                    elements.progressText.textContent = `🚀 Uploading ${state.file.name}...`;
                                    setButtonState('uploading');
                                    uploadNextChunk();
                                }
                            });
                        }).catch(error => {
                            // Restore ready state on error
                            setButtonState('ready');
                            MyZkToast.error('Failed to check credit balance: ' + error.message);
                        });
                    };

                    // Suspend ongoing uploads without losing progress state.
                    const pauseUpload = () => {
                        if (!state.uploading) {
                            return;
                        }
                        state.paused = true;
                        state.uploading = false;
                        elements.progressText.textContent = '⏸️ Upload paused.';
                        MyZkToast.warning('Upload paused.');
                        setButtonState('paused');
                    };

                    // Resume uploads after a pause by continuing from the current chunk.
                    const resumeUpload = () => {
                        if (!state.file) {
                            return;
                        }
                        state.paused = false;
                        state.uploading = true;
                        elements.progressText.textContent = '▶️ Upload resumed...';
                        MyZkToast.info('Upload resumed...');
                        setButtonState('uploading');
                        uploadNextChunk();
                    };

                    // Create a DOM fragment describing a single imagery record.
                    const resolveAssetUrl = (path) => {
                        if (!path || typeof path !== 'string') {
                            return null;
                        }

                        if (/^https?:\/\//i.test(path)) {
                            return path;
                        }

                        if (path.startsWith('/')) {
                            return path;
                        }

                        return `${window.location.origin}/${path.replace(/^\/+/, '')}`;
                    };

                    const disablePreviewButton = (button, tooltip) => {
                        if (!button) {
                            return;
                        }

                        button.setAttribute('disabled', 'true');
                        button.classList.add('opacity-40', 'cursor-not-allowed');
                        button.setAttribute('aria-pressed', 'false');
                        if (tooltip) {
                            button.setAttribute('title', tooltip);
                        }
                    };

                    const setPreviewButtonState = (button, isVisible) => {
                        if (!button) {
                            return;
                        }

                        button.innerHTML = `<i class="ri-${isVisible ? 'eye-off-line' : 'eye-line'}"></i>`;
                        button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
                    };

                    const renderImageryCard = (item) => {
                        const template = elements.cardTemplate;
                        if (!template?.content) {
                            return null;
                        }

                        const fragment = template.content.cloneNode(true);
                        const card = fragment.querySelector('.imagery-card');
                        if (!card) {
                            return null;
                        }

                        card.dataset.imageryId = item.id || '';

                        const formatLabel = (item.format || '').slice(0, 3).toUpperCase() || 'N/A';
                        card.querySelector('.imagery-format').textContent = formatLabel;
                        const displayName = item.stored_name || item.original_name || 'Imagery File';
                        card.querySelector('.imagery-name').textContent = shortenFilename(displayName, 26);

                        const sizeValue = Number(item.size) || 0;
                        const sizeMb = (sizeValue / 1024 / 1024).toFixed(2);
                        const uploadDate = item.uploaded_at ? new Date(item.uploaded_at).toLocaleString() : '—';

                        const uploadStatusKey = (item.upload_status || 'unknown').toLowerCase();
                        const processingStatusKey = (item.processing_status || 'unknown').toLowerCase();

                        const uploadStatusMap = {
                            done: {
                                label: 'Uploaded',
                                className: 'text-success'
                            },
                            merging: {
                                label: 'Finalising Upload',
                                className: 'text-warning'
                            },
                            pending: {
                                label: 'Awaiting Merge',
                                className: 'text-warning'
                            },
                            failed: {
                                label: 'Upload Failed',
                                className: 'text-red-500'
                            },
                        };

                        const processingStatusMap = {
                            completed: {
                                label: 'Processing Complete',
                                className: 'text-success'
                            },
                            processing: {
                                label: 'Processing',
                                className: 'text-warning'
                            },
                            waiting: {
                                label: 'Queued for Processing',
                                className: 'text-warning'
                            },
                            queued: {
                                label: 'Queued',
                                className: 'text-warning'
                            },
                            skip: {
                                label: 'Processing Skipped',
                                className: 'text-foreground/60'
                            },
                            error: {
                                label: 'Processing Failed',
                                className: 'text-red-500'
                            },
                        };

                        const uploadStatusInfo = uploadStatusMap[uploadStatusKey] || {
                            label: uploadStatusKey.charAt(0).toUpperCase() + uploadStatusKey.slice(1),
                            className: 'text-foreground/60',
                        };

                        const processingStatusInfo = processingStatusMap[processingStatusKey] || {
                            label: processingStatusKey.charAt(0).toUpperCase() + processingStatusKey.slice(1),
                            className: 'text-foreground/60',
                        };

                        const hasSourceLayer = Boolean(item.geoserver_source_layer);
                        const hasProcessedLayer = Boolean(item.geoserver_processed_layer || item.geoserver_layer);

                        let geoserverStatusInfo;
                        if (hasSourceLayer && hasProcessedLayer) {
                            geoserverStatusInfo = { label: 'GeoServer Ready', className: 'text-primary' };
                        } else if (hasSourceLayer || hasProcessedLayer) {
                            geoserverStatusInfo = { label: 'GeoServer Partial', className: 'text-warning' };
                        } else {
                            geoserverStatusInfo = { label: 'GeoServer Pending', className: 'text-foreground/60' };
                        }

                        const meta = `${sizeMb} MB • ${uploadDate} • <span class="imagery-status font-semibold ${uploadStatusInfo.className}">${uploadStatusInfo.label}</span> • <span class="imagery-status font-semibold ${processingStatusInfo.className}">${processingStatusInfo.label}</span> • <span class="imagery-status font-semibold ${geoserverStatusInfo.className}">${geoserverStatusInfo.label}</span>`;
                        card.querySelector('.imagery-meta').innerHTML = meta;

                        const imageryModule = window.AppMap?.imagery;
                        const sourceButton = card.querySelector('.view-source-btn');
                        if (sourceButton) {
                            if (!imageryModule?.toggleLayer) {
                                disablePreviewButton(sourceButton, 'Map preview is not available in this session.');
                            } else if (hasSourceLayer) {
                                sourceButton.addEventListener('click', (event) => {
                                    event.preventDefault();
                                    imageryModule.toggleLayer(item, 'source');
                                });

                                const isSourceVisible = imageryModule.isLayerVisible?.(item.id, 'source') ?? false;
                                setPreviewButtonState(sourceButton, isSourceVisible);
                            } else {
                                const sourceUrl = resolveAssetUrl(item.path);
                                if (!sourceUrl) {
                                    disablePreviewButton(sourceButton, 'Source imagery is not available yet.');
                                } else {
                                    sourceButton.addEventListener('click', (event) => {
                                        event.preventDefault();
                                        window.open(sourceUrl, '_blank', 'noopener');
                                    });
                                    sourceButton.setAttribute('title', 'Open source file in a new tab');
                                    setPreviewButtonState(sourceButton, false);
                                }
                            }
                        }

                        const processedButton = card.querySelector('.view-processed-btn');
                        if (processedButton) {
                            if (!imageryModule?.toggleLayer) {
                                disablePreviewButton(processedButton, 'Map preview is not available in this session.');
                            } else if (!hasProcessedLayer) {
                                disablePreviewButton(processedButton, 'Processed GeoServer layer not published yet.');
                            } else {
                                processedButton.addEventListener('click', (event) => {
                                    event.preventDefault();
                                    imageryModule.toggleLayer(item, 'processed');
                                });

                                const isProcessedVisible = imageryModule.isLayerVisible?.(item.id, 'processed') ?? false;
                                setPreviewButtonState(processedButton, isProcessedVisible);
                            }
                        }

                        if (imageryModule?.isAnyLayerVisible?.(item.id)) {
                            card.classList.add('ring-2', 'ring-primary/60');
                        }

                        return fragment;
                    };

                    document.addEventListener('app:imagery:layer-visibility', (event) => {
                        const detail = event.detail || {};
                        const id = detail.id;
                        if (!id) {
                            return;
                        }

                        const container = elements.myDataContainer;
                        if (!container) {
                            return;
                        }

                        const card = container.querySelector(`[data-imagery-id="${id}"]`);
                        if (!card) {
                            return;
                        }

                        const variant = detail.variant === 'source' ? 'source' : 'processed';
                        const selector = variant === 'source' ? '.view-source-btn' : '.view-processed-btn';
                        const button = card.querySelector(selector);
                        setPreviewButtonState(button, Boolean(detail.visible));

                        const imageryModule = window.AppMap?.imagery;
                        const anyVisible = typeof detail.anyVisible === 'boolean'
                            ? detail.anyVisible
                            : imageryModule?.isAnyLayerVisible?.(id) ?? false;

                        if (anyVisible) {
                            card.classList.add('ring-2', 'ring-primary/60');
                        } else {
                            card.classList.remove('ring-2', 'ring-primary/60');
                        }
                    });

                    // Retrieve the user's imagery list and render it into the panel.
                    const loadMyData = async () => {
                        const container = elements.myDataContainer;
                        container.innerHTML = `
                        <div class="flex justify-center py-4">
                            <p class="text-sm text-foreground/60 animate-pulse">Loading your imagery list...</p>
                        </div>
                        `;

                        try {
                            const response = await fetch(endpoints.list);
                            const payload = await response.json();
                            if (!response.ok || !payload.success) {
                                throw new Error(payload.message || 'Failed to fetch imagery data.');
                            }

                            const data = payload.data || [];
                            container.innerHTML = '';

                            if (data.length === 0) {
                                container.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No imagery uploaded yet.</p>';
                                return;
                            }

                            data.forEach((item) => {
                                const fragment = renderImageryCard(item);
                                if (fragment) {
                                    container.appendChild(fragment);
                                }
                            });
                        } catch (error) {
                            container.innerHTML = `
                            <div class="text-sm text-red-500 bg-red-50 border border-red-200 rounded p-3">
                                ❌ ${error.message}
                            </div>
                            `;
                        }
                    };

                    // Attach DOM event listeners for file and control buttons.
                    const bindEventListeners = () => {
                        elements.fileInput.addEventListener('change', handleFileChange);
                        elements.startBtn.addEventListener('click', startUpload);
                        elements.pauseBtn.addEventListener('click', pauseUpload);
                        elements.resumeBtn.addEventListener('click', resumeUpload);
                    };

                    // Bootstrap the uploader module and fetch initial server data.
                    const initialise = () => {
                        ensureAppNamespace();
                        window.setButtonState = setButtonState;
                        window.AppMap.uploader.reload = loadMyData;

                        resetState();
                        resetUI();
                        setButtonState('idle');
                        bindEventListeners();
                        loadMyData();
                    };

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initialise, {
                            once: true
                        });
                    } else {
                        initialise();
                    }
                })
                ();
            </script>
        @endauth
    @endpush
    @push('javascript')
        <script>
            (() => {
                // Abort if the Sentinel panel is not present in the DOM.
                const panel = document.getElementById('sentinel-panel');
                if (!panel) {
                    return;
                }

                // Cache frequently accessed DOM nodes for the Sentinel module.
                const previewPanelEl = document.getElementById('sentinelPreviewPanel');
                const previewDownloadBtnEl = document.getElementById('sentinelPreviewDownloadBtn');
                const previewImageryBtnEl = document.getElementById('sentinelPreviewImageryBtn');
                const previewClearBtnEl = document.getElementById('sentinelPreviewClearBtn');

                const elements = {
                    filterForm: document.getElementById('sentinelFilterForm'),
                    resetButton: document.getElementById('sentinelFilterResetButton'),
                    cloudInput: document.getElementById('sentinelCloudFilter'),
                    productInput: document.getElementById('sentinelProductLevel'),
                    startInput: document.getElementById('sentinelStartDate'),
                    endInput: document.getElementById('sentinelEndDate'),
                    latInput: document.getElementById('sentinelLatFilter'),
                    lonInput: document.getElementById('sentinelLonFilter'),
                    status: document.getElementById('sentinelCollectionStatus'),
                    list: document.getElementById('sentinelCollectionList'),
                    template: document.getElementById('sentinelCollectionTemplate'),
                    previewPanel: previewPanelEl,
                    previewTitle: previewPanelEl?.querySelector('[data-sentinel-preview-title]'),
                    previewAcquired: previewPanelEl?.querySelector('[data-sentinel-preview-acquired]'),
                    previewDetails: previewPanelEl?.querySelector('[data-sentinel-preview-details]'),
                    previewStatus: previewPanelEl?.querySelector('[data-sentinel-preview-status]'),
                    previewDownloadBtn: previewDownloadBtnEl,
                    previewImageryBtn: previewImageryBtnEl,
                    previewImageryIcon: previewPanelEl?.querySelector('[data-sentinel-preview-imagery-icon]'),
                    previewImageryLabel: previewPanelEl?.querySelector('[data-sentinel-preview-imagery-label]'),
                    previewClearBtn: previewClearBtnEl,
                };

                if (!elements.template || !elements.list || !elements.status) {
                    console.warn('Sentinel collection template or container missing. Skipping Sentinel module bootstrap.');
                    return;
                }

                // Normalise access to the global namespace so other modules can reuse this logic.
                window.AppMap = window.AppMap || {};

                const config = {
                    endpoint: 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json',
                    defaultMaxRecords: 10,
                    filteredMaxRecords: 20,
                    defaultDateRangeDays: 30,
                    token: (panel.dataset.sentinelToken || '').trim(),
                    processUrl: (panel.dataset.sentinelProcessUrl || '').trim(),
                    clipProcessUrl: (panel.dataset.sentinelClipProcessUrl || '').trim(),
                    processingCost: Number.parseFloat(panel.dataset.sentinelProcessingCost ?? '') || 0,
                    // Fallback Copernicus WMS endpoint & layer when the catalogue response omits one.
                    defaultWmsEndpoint: 'https://sh.dataspace.copernicus.eu/ogc/wms/1bd0fec1-0e52-427a-8e83-6e0dcd29a03a',
                    defaultWmsLayer: 'NATURAL-COLOR',
                    // Layer stacking order: keep WMS below the highlighted footprint polygons.
                    previewWmsZIndex: 10,
                    previewFootprintZIndex: 50,
                    // Copernicus WMS enforces a ~200 m/px ceiling; clamp tiles below this threshold.
                    previewMaxMetersPerPixel: 190,
                    // Ensure enough zoom levels for over-zooming while keeping tile math predictable.
                    previewTileZoomLevels: 22,
                    previewTileSize: 256,
                };

                window.AppMap.constants = window.AppMap.constants || {};
                if (!Number.isFinite(window.AppMap.constants.imageryProcessingCost) || window.AppMap.constants.imageryProcessingCost <= 0) {
                    window.AppMap.constants.imageryProcessingCost = config.processingCost;
                }

                const clipModule = document.getElementById('sentinelClipModule');
                const clipElements = {
                    container: clipModule,
                    processBtn: document.getElementById('clipProcessImageryBtn'),
                    status: document.getElementById('clipProcessStatus'),
                    fieldName: document.getElementById('clipFieldName'),
                };
                const clipConfig = {
                    processUrl: (clipModule?.dataset.clipProcessUrl || config.clipProcessUrl || '').trim(),
                    creditRate: Number.parseFloat(clipModule?.dataset.creditRate ?? '') || 0,
                    processingCost: Number.parseFloat(clipModule?.dataset.processingCost ?? '') || 0,
                };
                const clipStatusToneClasses = ['text-success', 'text-warning', 'text-error', 'text-foreground/60'];

                const updateClipStatus = (message, tone = 'muted') => {
                    if (!clipElements.status) {
                        return;
                    }
                    clipElements.status.textContent = message;
                    clipStatusToneClasses.forEach((className) => {
                        clipElements.status.classList.remove(className);
                    });
                    const toneClass = tone === 'success' ?
                        'text-success' :
                        tone === 'error' ?
                        'text-error' :
                        tone === 'warning' ?
                        'text-warning' :
                        'text-foreground/60';
                    clipElements.status.classList.add(toneClass);
                };

                const computeClipCredits = (areaHa) => {
                    if (!Number.isFinite(areaHa) || areaHa <= 0 || !Number.isFinite(clipConfig.creditRate) || clipConfig.creditRate <= 0) {
                        return 0;
                    }
                    return Number((areaHa * clipConfig.creditRate).toFixed(2));
                };

                const buildFeatureCollection = (feature) => {
                    if (!feature) {
                        return null;
                    }
                    const properties = typeof feature.properties === 'object' && feature.properties !== null ?
                        feature.properties : {};
                    return {
                        type: 'FeatureCollection',
                        features: [{
                            type: 'Feature',
                            properties,
                            geometry: feature.geometry,
                        }],
                    };
                };

                const enqueueClipProcessing = async (payload) => {
                    if (!clipElements.processBtn || !clipConfig.processUrl) {
                        return;
                    }

                    const originalHtml = clipElements.processBtn.innerHTML;
                    const setButtonState = (html, disabled) => {
                        if (typeof html === 'string') {
                            clipElements.processBtn.innerHTML = html;
                        }
                        if (typeof disabled === 'boolean') {
                            clipElements.processBtn.disabled = disabled;
                        }
                    };

                    setButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Submitting...</span>', true);
                    updateClipStatus('Submitting clipped imagery request...', 'warning');

                    try {
                        const response = await fetch(clipConfig.processUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(payload),
                        });

                        const result = await response.json().catch(() => ({}));

                        if (response.ok && result?.success) {
                            const message = result?.message || 'Sentinel clip queued for processing.';
                            updateClipStatus(message, 'success');
                            if (result?.data?.current_credits !== undefined) {
                                $('#current-myCredits')?.text(formatNumber(result.data.current_credits, 2));
                            }
                            MyZkToast?.success?.(message);
                        } else {
                            const errorMessage = result?.message || 'Failed to queue clipped imagery processing. Please try again later.';
                            updateClipStatus(errorMessage, 'error');
                            MyZkToast?.error?.(errorMessage);
                        }
                    } catch (error) {
                        console.error('Failed to queue Sentinel clip processing', error);
                        updateClipStatus(error?.message || 'Unable to queue clipped imagery at this time.', 'error');
                        MyZkToast?.error?.('Unable to queue clipped imagery at this time.');
                    } finally {
                        setButtonState(originalHtml, false);
                        if (typeof window.AppMap?.uploader?.reload === 'function') {
                            window.AppMap.uploader.reload();
                        }
                    }
                };

                const handleClipProcessingClick = async () => {
                    if (!clipElements.processBtn) {
                        return;
                    }
                    if (!clipConfig.processUrl) {
                        MyZkToast?.warning?.('Please sign in to process Sentinel-2 clips.');
                        return;
                    }

                    const feature = window.geojsonFeature;
                    if (!feature || !feature.geometry) {
                        updateClipStatus('Please draw an area of interest on the map before processing.', 'warning');
                        MyZkToast?.warning?.('Draw a polygon on the map to define your area of interest.');
                        return;
                    }

                    const areaSquareMeters = Number(window.geojsonArea);
                    const areaHa = Number.isFinite(areaSquareMeters) ? areaSquareMeters / 10000 : NaN;
                    if (!Number.isFinite(areaHa) || areaHa <= 0) {
                        updateClipStatus('Unable to determine the area of interest. Draw the polygon again.', 'error');
                        MyZkToast?.error?.('Unable to determine the selected area. Please redraw the polygon.');
                        return;
                    }

                    const fieldName = (clipElements.fieldName?.value || '').trim();
                    if (fieldName === '') {
                        updateClipStatus('Enter a field name before processing the clipped imagery.', 'warning');
                        MyZkToast?.warning?.('Please provide a field name for this area.');
                        clipElements.fieldName?.focus();
                        return;
                    }

                    const featureCollection = buildFeatureCollection(feature);
                    if (!featureCollection?.features?.length) {
                        updateClipStatus('Invalid geometry detected. Please redraw your area of interest.', 'error');
                        MyZkToast?.error?.('Invalid geometry detected. Please redraw the area.');
                        return;
                    }

                    const estimatedCredits = computeClipCredits(areaHa);
                    const payload = {
                        field_name: fieldName,
                        area_hectares: areaHa,
                        geojson: featureCollection,
                        geometry: feature.geometry,
                        estimated_credits: estimatedCredits,
                    };

                    try {
                        const creditsInfo = await checkUserCredits(estimatedCredits);
                        const currentCredits = Number.parseFloat(creditsInfo?.currentCredits ?? creditsInfo?.credits ?? 0) || 0;
                        const requiredCredits = Number.isFinite(estimatedCredits) ? estimatedCredits : creditsInfo?.requiredCredits;
                        const hasCredits = Number.isFinite(requiredCredits) ? currentCredits >= requiredCredits : creditsInfo?.hasCredits;
                        const formattedRequired = Number.isFinite(requiredCredits) ? formatNumber(requiredCredits, 2) : formatNumber(creditsInfo?.requiredCredits ?? 0, 2);
                        const formattedCurrent = formatNumber(currentCredits, 2);

                        const proceed = () => {
                            if (!hasCredits && Number.isFinite(requiredCredits) && requiredCredits > 0) {
                                MyZkToast?.warning?.(`Insufficient credit points. You need ${formattedRequired} credits but only have ${formattedCurrent}.`);
                                updateClipStatus('Insufficient credit points to process this imagery.', 'warning');
                                return;
                            }
                            enqueueClipProcessing(payload);
                        };

                        const confirmationMessage = hasCredits || !Number.isFinite(requiredCredits) || requiredCredits <= 0 ?
                            `This action will cost approximately ${formattedRequired} credit points. Do you want to continue?` :
                            `You need ${formattedRequired} credit points but currently have ${formattedCurrent}. Please purchase more credits.`;

                        if (typeof ZkPopAlert?.show === 'function') {
                            ZkPopAlert.show({
                                message: confirmationMessage,
                                icon: '<i class="ri-cpu-line text-2xl text-primary"></i>',
                                confirmClass: "focus:ring-primary/80 rounded-md text-sm px-2.5 py-1.5 bg-primary text-primary-foreground border border-primary hover:bg-primary/80 focus:outline-none focus:ring-primary",
                                confirmText: hasCredits ? 'Yes, Process' : 'Close',
                                cancelText: hasCredits ? 'Cancel' : null,
                                onConfirm: () => {
                                    if (hasCredits) {
                                        proceed();
                                    }
                                },
                            });
                        } else if (hasCredits) {
                            const confirmed = window.confirm(confirmationMessage);
                            if (confirmed) {
                                proceed();
                            }
                        } else {
                            MyZkToast?.warning?.(`Insufficient credit points. You need ${formattedRequired} credits but only have ${formattedCurrent}.`);
                            updateClipStatus('Insufficient credit points to process this imagery.', 'warning');
                        }
                    } catch (error) {
                        console.error('Failed to verify credit balance', error);
                        updateClipStatus('Unable to verify your credit balance. Please try again.', 'error');
                        MyZkToast?.error?.('Unable to verify your credit balance.');
                    }
                };

                if (clipElements.processBtn) {
                    clipElements.processBtn.addEventListener('click', handleClipProcessingClick);
                }

                // Track loaded scenes, pending requests, and map preview state.
                const state = {
                    scenes: [],
                    loadedOnce: false,
                    requestController: null,
                    lastQueryString: null,
                    preview: {
                        map: window.map || null,
                        footprintLayer: null,
                        wmsLayer: null,
                        activeSceneIndex: null,
                        wmsActive: false,
                    },
                };

                /**
                 * Resolve the underlying OpenLayers map instance from various potential wrappers.
                 */
                const resolveMapInstance = (candidate) => {
                    if (candidate && typeof candidate.addLayer === 'function' && typeof candidate.getView === 'function') {
                        return candidate;
                    }
                    if (candidate?.map && typeof candidate.map.addLayer === 'function') {
                        return candidate.map;
                    }
                    if (candidate?.detail) {
                        return resolveMapInstance(candidate.detail);
                    }
                    return null;
                };

                /**
                 * Persist and return the active OpenLayers map instance.
                 */
                const assignMapInstance = (candidate) => {
                    const resolved = resolveMapInstance(candidate);
                    if (resolved) {
                        state.preview.map = resolved;
                    }
                    return resolved;
                };

                /**
                 * Retrieve the OpenLayers map, refreshing the cached instance when necessary.
                 */
                const getMapInstance = () => {
                    const resolved =
                        resolveMapInstance(state.preview.map) ||
                        assignMapInstance(window.map);
                    if (resolved && state.preview.map !== resolved) {
                        state.preview.map = resolved;
                    }
                    return resolved;
                };

                // Cache any immediately available map reference.
                assignMapInstance(window.map);

                // Lazily capture the OpenLayers map instance when it becomes available.
                if (!state.preview.map) {
                    window.addEventListener('map:ready', (event) => {
                        assignMapInstance(event.detail);
                    }, {
                        once: true
                    });
                }

                /**
                 * Format a Date object into the YYYY-MM-DD pattern expected by date inputs.
                 */
                const formatDateForInput = (date) => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };

                /**
                 * Provide a default 30-day date range ending today so the initial query stays manageable.
                 */
                const ensureDefaultDates = () => {
                    if (!elements.startInput || !elements.endInput) {
                        return;
                    }

                    const end = new Date();
                    const start = new Date();
                    start.setDate(end.getDate() - config.defaultDateRangeDays);

                    if (!elements.startInput.value) {
                        elements.startInput.value = formatDateForInput(start);
                    }
                    if (!elements.endInput.value) {
                        elements.endInput.value = formatDateForInput(end);
                    }
                };

                /**
                 * Replace characters that are invalid for filenames so download names stay portable.
                 */
                const sanitizeFileName = (value, fallback = 'sentinel-scene') => {
                    const base = (value || '').toString().trim();
                    const normalized = base
                        .normalize('NFKD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-zA-Z0-9-_.]+/g, '-')
                        .replace(/-{2,}/g, '-')
                        .replace(/^-+|-+$/g, '');
                    return (normalized || fallback).substring(0, 120);
                };

                /**
                 * Append the Copernicus access token to download URLs so the ZIP service authorises the request.
                 */
                const withAccessToken = (url) => {
                    if (!url || !config.token) {
                        return url;
                    }

                    try {
                        const parsed = new URL(url, window.location.href);
                        const params = parsed.searchParams;

                        if (!params.has('token') && !params.has('access_token')) {
                            params.set('token', config.token);
                        }

                        return parsed.toString();
                    } catch (error) {
                        // Fallback for environments that cannot parse the URL instance (e.g. malformed URLs).
                        const hasQuery = url.includes('?');
                        const hasTokenParam = /(?:\?|&)(token|access_token)=/i.test(url);
                        if (hasTokenParam) {
                            return url;
                        }
                        const separator = hasQuery ? '&' : '?';
                        return `${url}${separator}token=${encodeURIComponent(config.token)}`;
                    }
                };

                /**
                 * Mutate the status label shown above the collection list.
                 */
                const setStatus = (message, isLoading = false) => {
                    if (elements.status) {
                        if (isLoading) {
                            elements.status.innerHTML = `
                                <div class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>${message}</span>
                                </div>
                            `;
                        } else {
                            elements.status.innerHTML = message;
                        }
                    }
                };

                /**
                 * Update the preview status text safely.
                 */
                const setPreviewStatus = (message) => {
                    if (elements.previewStatus) {
                        elements.previewStatus.textContent = message;
                    }
                };

                /**
                 * Toggle a loading appearance while data is being fetched.
                 */
                const setLoadingState = (isLoading) => {
                    if (isLoading) {
                        // Clear the container content and show only status with spinner
                        if (elements.list) {
                            elements.list.innerHTML = '';
                        }
                    }
                };

                /**
                 * Convert a bounding box array into a simple polygon GeoJSON object.
                 */
                const geometryFromBbox = (bbox) => {
                    if (!Array.isArray(bbox) || bbox.length < 4) {
                        return null;
                    }
                    const [minLon, minLat, maxLon, maxLat] = bbox;
                    return {
                        type: 'Polygon',
                        coordinates: [
                            [
                                [minLon, minLat],
                                [maxLon, minLat],
                                [maxLon, maxLat],
                                [minLon, maxLat],
                                [minLon, minLat],
                            ]
                        ],
                    };
                };

                /**
                 * Recursively flatten coordinate arrays so we can derive bounds from arbitrary geometries.
                 */
                const collectCoordinatePairs = (input, acc = []) => {
                    if (!Array.isArray(input)) {
                        return acc;
                    }
                    if (
                        input.length >= 2 &&
                        typeof input[0] === 'number' &&
                        typeof input[1] === 'number'
                    ) {
                        acc.push([input[0], input[1]]);
                        return acc;
                    }
                    for (const value of input) {
                        collectCoordinatePairs(value, acc);
                    }
                    return acc;
                };

                /**
                 * Derive a geographic bounding box from any supported GeoJSON geometry.
                 */
                const bboxFromGeometry = (geometry) => {
                    if (!geometry) {
                        return null;
                    }

                    const geometries =
                        geometry.type === 'GeometryCollection' ?
                        geometry.geometries || [] : [geometry];

                    let minLon = Infinity;
                    let minLat = Infinity;
                    let maxLon = -Infinity;
                    let maxLat = -Infinity;

                    for (const geom of geometries) {
                        const coords = collectCoordinatePairs(geom?.coordinates);
                        for (const pair of coords) {
                            const [lon, lat] = pair;
                            if (!Number.isFinite(lon) || !Number.isFinite(lat)) {
                                continue;
                            }
                            if (lon < minLon) minLon = lon;
                            if (lat < minLat) minLat = lat;
                            if (lon > maxLon) maxLon = lon;
                            if (lat > maxLat) maxLat = lat;
                        }
                    }

                    if (
                        !Number.isFinite(minLon) ||
                        !Number.isFinite(minLat) ||
                        !Number.isFinite(maxLon) ||
                        !Number.isFinite(maxLat)
                    ) {
                        return null;
                    }

                    return [minLon, minLat, maxLon, maxLat];
                };

                /**
                 * Transform a geographic bounding box into the map's projection for clipping the WMS layer.
                 */
                const projectBboxToMapExtent = (bbox, mapInstance) => {
                    if (!Array.isArray(bbox) || bbox.length < 4 || !mapInstance) {
                        return null;
                    }

                    const view = typeof mapInstance.getView === 'function' ? mapInstance.getView() : null;
                    const projection = view?.getProjection?.();
                    if (!projection) {
                        return null;
                    }

                    const code = typeof projection.getCode === 'function' ? projection.getCode() : null;
                    if (code === 'EPSG:4326') {
                        return bbox;
                    }

                    if (!window.ol?.proj?.transformExtent) {
                        return null;
                    }

                    try {
                        return window.ol.proj.transformExtent(bbox, 'EPSG:4326', projection);
                    } catch (error) {
                        console.warn('Failed to transform Sentinel extent:', error);
                        return null;
                    }
                };

                /**
                 * Build a tile grid tailored to the scene extent so WMS requests stay within Copernicus limits.
                 */
                const createTileGridForExtent = (extent, projection) => {
                    if (
                        !extent ||
                        extent.length < 4 ||
                        !window.ol?.tilegrid ||
                        (!window.ol.tilegrid.createXYZ && !window.ol.tilegrid.TileGrid)
                    ) {
                        return null;
                    }

                    const [minX, minY, maxX, maxY] = extent;
                    if (
                        !Number.isFinite(minX) ||
                        !Number.isFinite(minY) ||
                        !Number.isFinite(maxX) ||
                        !Number.isFinite(maxY) ||
                        maxX <= minX ||
                        maxY <= minY
                    ) {
                        return null;
                    }

                    const units = projection?.getUnits?.();
                    if (units && units !== 'm') {
                        // Only clamp resolutions for metre-based projections; degrees don't trigger the Copernicus cap.
                        return null;
                    }

                    const tileSize = Number.isFinite(config.previewTileSize) ?
                        config.previewTileSize :
                        256;
                    const spanX = maxX - minX;
                    const spanY = maxY - minY;
                    const dominantSpan = Math.max(spanX, spanY);
                    if (!Number.isFinite(dominantSpan) || dominantSpan <= 0) {
                        return null;
                    }

                    const baseResolution = dominantSpan / tileSize;
                    if (!Number.isFinite(baseResolution) || baseResolution <= 0) {
                        return null;
                    }

                    const cap = Number.isFinite(config.previewMaxMetersPerPixel) ?
                        Math.max(1, config.previewMaxMetersPerPixel) :
                        190;
                    const startingResolution = Math.min(baseResolution, cap);

                    const zoomLevels = Number.isFinite(config.previewTileZoomLevels) ?
                        Math.max(1, Math.floor(config.previewTileZoomLevels)) :
                        22;

                    // Precompute the resolution pyramid so we can fall back if createXYZ is unavailable.
                    const resolutions = [];
                    for (let i = 0; i < zoomLevels; i += 1) {
                        resolutions.push(startingResolution / Math.pow(2, i));
                    }

                    try {
                        if (window.ol.tilegrid.createXYZ) {
                            return window.ol.tilegrid.createXYZ({
                                extent,
                                tileSize,
                                maxResolution: startingResolution,
                                maxZoom: zoomLevels - 1,
                            });
                        }

                        if (window.ol.tilegrid.TileGrid) {
                            return new window.ol.tilegrid.TileGrid({
                                extent,
                                tileSize,
                                resolutions,
                            });
                        }
                    } catch (error) {
                        console.warn('Failed to construct Sentinel tile grid:', error);
                    }

                    return null;
                };

                /**
                 * Extract a numeric cloud cover percentage from a feature if available.
                 */
                const resolveCloudCover = (feature) => {
                    const props = feature?.properties ?? {};
                    const candidates = [
                        props['eo:cloudCover'],
                        props.cloudCover,
                        props.cloudcoverpercentage,
                        props.cloudCoverageAssessment,
                    ];
                    for (const candidate of candidates) {
                        const value = Number(candidate);
                        if (Number.isFinite(value)) {
                            return value;
                        }
                    }
                    return null;
                };

                /**
                 * Locate the best available download link for a feature.
                 */
                const resolveDownloadUrl = (feature) => {
                    const props = feature?.properties ?? {};
                    const linkSources = [];

                    if (Array.isArray(props.links)) {
                        linkSources.push(...props.links);
                    } else if (props.links && typeof props.links === 'object') {
                        linkSources.push(...Object.values(props.links));
                    }

                    const services = props.services || {};
                    if (services.download) {
                        linkSources.push(services.download);
                    }
                    if (services.s3) {
                        linkSources.push(services.s3);
                    }

                    for (const entry of linkSources) {
                        const href = entry?.href || entry?.url || entry?.URI;
                        const relation = (entry?.rel || entry?.role || '').toLowerCase();
                        if (typeof href === 'string' && href.startsWith('http')) {
                            if (!relation || /enclosure|download|data/.test(relation)) {
                                return href;
                            }
                        }
                    }

                    if (typeof props.downloadLink === 'string' && props.downloadLink.startsWith('http')) {
                        return props.downloadLink;
                    }

                    return null;
                };

                /**
                 * Discover a thumbnail or quicklook URL so each card can show a preview image.
                 */
                const resolveThumbnailUrl = (feature) => {
                    const props = feature?.properties ?? {};
                    const assets = feature?.assets ?? {};
                    const candidates = [
                        props.thumbnail,
                        props.quicklook,
                        props.quicklookUrl,
                        props.thumbnailUrl,
                        assets.thumbnail?.href,
                        assets.quicklook?.href,
                    ];
                    return candidates.find((value) => typeof value === 'string' && value.startsWith('http')) || null;
                };

                /**
                 * Extract WMS configuration (URL & layers) if the API exposes it.
                 */
                const resolveWmsConfig = (feature, acquisitionDate) => {
                    const services = feature?.properties?.services ?? {};
                    const ogc = services.ogc || services.wms || {};
                    const wmsCandidates = Array.isArray(ogc?.wms) ? ogc.wms : [ogc.wms || ogc];

                    for (const candidate of wmsCandidates) {
                        const url = candidate?.url || candidate?.href;
                        const layers = candidate?.layers || candidate?.layer || candidate?.name;
                        if (typeof url === 'string' && url.startsWith('http') && layers) {
                            return {
                                url,
                                layers,
                                time: candidate?.time || candidate?.TIME || formatReadableDate(acquisitionDate),
                            };
                        }
                    }

                    if (config.defaultWmsEndpoint && config.defaultWmsLayer) {
                        // Use the shared Copernicus service when the catalogue does not expose a per-scene WMS link.
                        return {
                            url: config.defaultWmsEndpoint,
                            layers: config.defaultWmsLayer,
                            time: formatReadableDate(acquisitionDate),
                        };
                    }

                    return null;
                };

                /**
                 * Prepare URL parameters used for the Sentinel search endpoint.
                 */
                const buildQueryParams = (maxRecords) => {
                    const params = new URLSearchParams();
                    params.set('maxRecords', String(maxRecords));
                    params.set('productType', (elements.productInput?.value || '').trim() || 'S2MSI2A');

                    const cloudValue = Number(elements.cloudInput?.value ?? '');
                    if (Number.isFinite(cloudValue)) {
                        const bounded = Math.min(Math.max(cloudValue, 0), 100);
                        const rounded = Math.round(bounded);
                        // Dataspace expects range syntax with inclusive brackets, e.g. "[0,30]".
                        // Supplying legacy dash-separated ranges triggers a 400 validation error.
                        const rangeValue = `[0,${rounded}]`;
                        // Only set the documented "cloudCover" parameter; the API rejects other
                        // aliases (cloudCoverPercentage/cloudcoverpercentage) with a 400 error.
                        params.set('cloudCover', rangeValue);
                    }

                    const startValue = (elements.startInput?.value || '').trim();
                    const endValue = (elements.endInput?.value || '').trim();
                    if (startValue) {
                        params.set('startDate', `${startValue}T00:00:00Z`);
                    }
                    if (endValue) {
                        params.set('completionDate', `${endValue}T23:59:59Z`);
                    }

                    const latValue = Number(elements.latInput?.value ?? '');
                    const lonValue = Number(elements.lonInput?.value ?? '');
                    if (Number.isFinite(latValue) && Number.isFinite(lonValue)) {
                        params.set('lat', latValue.toFixed(6));
                        params.set('lon', lonValue.toFixed(6));
                    }

                    return params;
                };

                /**
                 * Convert the raw API feature into a simplified scene object for rendering.
                 */
                const transformFeature = (feature) => {
                    if (!feature) return null;

                    const props = feature.properties || {};
                    const title = shortenFilename(props?.title, 30) || props?.productIdentifier || feature?.id || 'Sentinel-2 Scene';
                    const acquisitionDate = props.completionDate || props.startDate || props.endPosition || props.beginPosition || props.startTimeFromAscendingNode;
                    const cloudCover = resolveCloudCover(feature);
                    const mgrs = props.mgrsId || props.tileId || props.MGRS || props.utmc || null;
                    const downloadUrl = withAccessToken(resolveDownloadUrl(feature));
                    const thumbnailUrl = resolveThumbnailUrl(feature);
                    const wms = resolveWmsConfig(feature, acquisitionDate);
                    const geometry = feature.geometry || geometryFromBbox(feature.bbox || []);
                    const bbox = Array.isArray(feature.bbox) && feature.bbox.length >= 4 ?
                        feature.bbox.slice(0, 4) :
                        bboxFromGeometry(geometry);

                    const details = [];
                    if (mgrs) {
                        details.push(`Tile ${mgrs}`);
                    }
                    if (Number.isFinite(cloudCover)) {
                        details.push(`Cloud ${cloudCover.toFixed(1)}%`);
                    }

                    return {
                        id: feature.id || props.id || null,
                        title,
                        productId: props.productIdentifier || null,
                        collection: props.collection || props.collectionName || null,
                        datetime: formatReadableDate(acquisitionDate) ?? null,
                        details: details.join(' • '),
                        cloudCover,
                        mgrs,
                        downloadUrl,
                        downloadName: sanitizeFileName(`${title}.zip`),
                        thumbnailUrl,
                        wms,
                        geometry,
                        bbox,
                        raw: feature,
                    };
                };

                const buildProcessingPayload = (scene) => ({
                    title: scene.title || 'Sentinel-2 Scene',
                    download_url: scene.downloadUrl,
                    product_id: scene.productId || scene.id || null,
                    collection: scene.collection || null,
                    acquisition_date: scene.datetime || null,
                    download_filename: scene.downloadName || sanitizeFileName(`${scene.title || 'Sentinel-2 Scene'}.zip`),
                });

                const handleProcessScene = (index, buttonEl) => {
                    const scene = state.scenes[index];
                    if (!scene) {
                        return;
                    }

                    if (!config.processUrl) {
                        MyZkToast?.warning?.('Please sign in to process Sentinel-2 imagery.');
                        return;
                    }

                    if (!scene.downloadUrl) {
                        MyZkToast?.warning?.('Selected scene is missing a Copernicus download link.');
                        return;
                    }

                    const originalHtml = buttonEl?.innerHTML || '<i class="ri-cpu-line"></i><span>Process Imagery</span>';
                    const setButtonState = (html, disabled) => {
                        if (!buttonEl) {
                            return;
                        }
                        if (typeof html === 'string') {
                            buttonEl.innerHTML = html;
                        }
                        if (typeof disabled === 'boolean') {
                            buttonEl.disabled = disabled;
                        }
                    };

                    const payload = buildProcessingPayload(scene);

                    const enqueueProcessing = async () => {
                        setButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Queuing...</span>', true);
                        try {
                            const response = await fetch(config.processUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                                },
                                body: JSON.stringify(payload)
                            });

                            const result = await response.json();

                            if (response.ok) {
                                $('#current-myCredits')?.text(formatNumber(result?.data?.current_credits, 2));
                                const message = result?.message || 'Sentinel scene queued for processing.';
                                MyZkToast?.success?.(message);
                            } else {
                                const errorMessage = result?.message || 'Failed to queue Sentinel processing. Please try again later.';
                                MyZkToast?.error?.(errorMessage);
                            }
                        } catch (error) {
                            console.error('Failed to queue Sentinel processing', error);
                            MyZkToast?.error?.(error?.message || 'Unable to queue Sentinel imagery.');
                        } finally {
                            setButtonState(originalHtml, false);
                            if (typeof window.AppMap?.uploader?.reload === 'function') {
                                window.AppMap.uploader.reload();
                            }
                        }
                    };

                    setButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Checking credits...</span>', true);

                    // Check user credits before proceeding
                    checkUserCredits().then(res => {
                        $('#current-myCredits').text(formatNumber(res.currentCredits, 2));
                        // Show confirmation modal before starting upload
                        ZkPopAlert.show({
                            message: `${res.hasCredits ? `This action will cost ${res.requiredCredits} credit points for processing imagery. Do you want to proceed?` : `Insufficient credit points for processing. You need ${res.requiredCredits} credits. Please purchase more credits to continue processing.`}`,
                            icon: '<i class="ri-cpu-line text-2xl text-primary"></i>',
                            confirmClass: "focus:ring-primary/80 rounded-md text-sm px-2.5 py-1.5 bg-primary text-primary-foreground border border-primary hover:bg-primary/80 focus:outline-none focus:ring-primary",
                            confirmText: "Yes, Continue",
                            cancelText: "Cancel",
                            onConfirm: () => {
                                if (!res.hasCredits) {
                                    MyZkToast.warning('Insufficient credit points for processing. Please purchase more credits to continue processing.');
                                } else {
                                    enqueueProcessing()
                                }
                            }
                        });
                    }).catch(error => {
                        // Restore ready state on error
                        MyZkToast.error('Failed to check credit balance: ' + error.message);
                    }).finally(() => {
                        setButtonState(originalHtml, false);
                    });
                };

                /**
                 * Render a single Sentinel scene card using the HTML template fragment.
                 */
                const renderSceneCard = (scene, index) => {
                    const fragment = elements.template.content.cloneNode(true);
                    const card = fragment.querySelector('.sentinel-card');
                    if (!card) {
                        return null;
                    }

                    const titleEl = fragment.querySelector('[data-sentinel-title]');
                    const productEl = fragment.querySelector('[data-sentinel-product]');
                    const datetimeEl = fragment.querySelector('[data-sentinel-datetime]');
                    const detailsEl = fragment.querySelector('[data-sentinel-details]');
                    const thumbWrapper = fragment.querySelector('[data-sentinel-thumb]');
                    const thumbnailEl = fragment.querySelector('[data-sentinel-thumbnail]');
                    const placeholderEl = fragment.querySelector('[data-sentinel-placeholder]');
                    const downloadBtn = fragment.querySelector('[data-sentinel-download]');
                    const processBtn = fragment.querySelector('[data-sentinel-process]');
                    const previewBtn = fragment.querySelector('[data-sentinel-preview]');

                    if (titleEl) {
                        titleEl.textContent = scene.title;
                    }
                    if (productEl) {
                        productEl.textContent = scene.productId || scene.id || 'Sentinel-2 Product';
                    }
                    if (datetimeEl) {
                        datetimeEl.textContent = scene.datetime ?
                            `Acquired: ${formatReadableDate(scene.datetime)}` :
                            'Acquired: –';
                    }
                    if (detailsEl) {
                        detailsEl.textContent = scene.details || 'Details unavailable';
                    }

                    if (scene.thumbnailUrl && thumbnailEl) {
                        thumbnailEl.src = scene.thumbnailUrl;
                        thumbnailEl.classList.remove('hidden');
                        placeholderEl?.classList.add('hidden');
                    } else {
                        thumbnailEl?.classList.add('hidden');
                        placeholderEl?.classList.remove('hidden');
                    }

                    if (downloadBtn) {
                        if (scene.downloadUrl) {
                            downloadBtn.href = scene.downloadUrl;
                            downloadBtn.download = scene.downloadName;
                            downloadBtn.title = `Download ${scene.title}`;
                            downloadBtn.setAttribute('aria-disabled', 'false');
                            downloadBtn.classList.remove('hidden');
                        } else {
                            downloadBtn.classList.add('hidden');
                            downloadBtn.setAttribute('aria-disabled', 'true');
                        }
                    }

                    if (processBtn) {
                        if (config.processUrl && scene.downloadUrl) {
                            processBtn.classList.remove('hidden');
                            processBtn.disabled = false;
                            processBtn.dataset.sceneIndex = String(index);
                            processBtn.addEventListener('click', () => handleProcessScene(index, processBtn));
                        } else {
                            processBtn.classList.add('hidden');
                            processBtn.disabled = true;
                        }
                    }

                    if (previewBtn) {
                        previewBtn.dataset.sceneIndex = String(index);
                        previewBtn.addEventListener('click', () => handlePreview(index));
                    }

                    // Expose the raw scene payload so other actions (process imagery) can reuse it later.
                    card.dataset.sceneIndex = String(index);

                    return fragment;
                };

                /**
                 * Refresh the list container with the latest set of scenes.
                 */
                const renderSceneList = () => {
                    if (!elements.list) return;
                    elements.list.innerHTML = '';
                    state.scenes.forEach((scene, index) => {
                        const cardFragment = renderSceneCard(scene, index);
                        if (cardFragment) {
                            elements.list.appendChild(cardFragment);
                        }
                    });
                };

                /**
                 * Ensure a vector layer exists for drawing Sentinel footprints.
                 */
                const ensureFootprintLayer = () => {
                    if (state.preview.footprintLayer) {
                        return state.preview.footprintLayer;
                    }
                    const mapInstance = getMapInstance();
                    if (
                        !mapInstance ||
                        !window.ol?.layer?.Vector ||
                        !window.ol?.source?.Vector ||
                        !window.ol?.style?.Style ||
                        !window.ol?.style?.Stroke ||
                        !window.ol?.style?.Fill
                    ) {
                        return null;
                    }

                    if (typeof mapInstance.addLayer !== 'function') {
                        return null;
                    }

                    const layer = new ol.layer.Vector({
                        source: new ol.source.Vector(),

                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: 'rgba(37, 99, 235, 0.9)',
                                width: 2,
                                lineDash: [4, 4]
                            }),
                            fill: new ol.style.Fill({
                                color: 'rgba(37, 99, 235, 0.06)'
                            }),
                        }),
                        properties: {
                            name: 'sentinel-footprint'
                        },
                        visible: false,
                    });

                    if (typeof layer.setZIndex === 'function') {
                        layer.setZIndex(config.previewFootprintZIndex);
                    }

                    mapInstance.addLayer(layer);
                    state.preview.footprintLayer = layer;
                    return layer;
                };

                /**
                 * Show the Sentinel footprint geometry on the OpenLayers map.
                 */
                const showFootprint = (geometry) => {
                    const mapInstance = getMapInstance();
                    if (
                        !mapInstance ||
                        typeof mapInstance.getView !== 'function' ||
                        !geometry ||
                        !window.ol?.format?.GeoJSON
                    ) {
                        return false;
                    }

                    const layer = ensureFootprintLayer();
                    if (!layer) {
                        return false;
                    }

                    const source = layer.getSource();
                    source.clear();

                    const reader = new ol.format.GeoJSON();
                    const feature = reader.readFeature({
                        type: 'Feature',
                        geometry
                    }, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: mapInstance.getView().getProjection(),
                    });

                    source.addFeature(feature);
                    layer.setVisible(true);

                    const extent = feature.getGeometry().getExtent();
                    mapInstance.getView().fit(extent, {
                        duration: 400,
                        padding: [40, 40, 40, 40],
                        maxZoom: 13
                    });
                    return true;
                };

                /**
                 * Tear down the footprint overlay and optionally hide the layer.
                 */
                const clearFootprint = () => {
                    if (!state.preview.footprintLayer) {
                        return;
                    }
                    state.preview.footprintLayer.getSource().clear();
                    state.preview.footprintLayer.setVisible(false);
                };

                /**
                 * Create or update the WMS layer used for Sentinel preview imagery.
                 */
                const toggleWmsLayer = (enable) => {
                    const mapInstance = getMapInstance();
                    const hasMapSupport =
                        mapInstance &&
                        typeof mapInstance.addLayer === 'function' &&
                        typeof mapInstance.getView === 'function';

                    if (!enable) {
                        if (state.preview.wmsLayer) {
                            const currentSource = typeof state.preview.wmsLayer.getSource === 'function' ?
                                state.preview.wmsLayer.getSource() :
                                null;
                            if (currentSource?.updateParams) {
                                currentSource.updateParams({
                                    TIME: undefined
                                });
                            }
                            state.preview.wmsLayer.setVisible(false);
                            state.preview.wmsLayer.setExtent(undefined);
                        }
                        state.preview.wmsActive = false;
                        return true;
                    }

                    const scene = state.scenes[state.preview.activeSceneIndex ?? -1];
                    if (!scene?.wms || !hasMapSupport || !window.ol?.layer?.Tile || !window.ol?.source?.TileWMS) {
                        return false;
                    }

                    const bboxSource = Array.isArray(scene.bbox) && scene.bbox.length >= 4 ? scene.bbox : null;
                    const bbox = bboxSource || bboxFromGeometry(scene.geometry || geometryFromBbox(scene.bbox || []));
                    if (!bbox) {
                        return false;
                    }

                    let extent = projectBboxToMapExtent(bbox, mapInstance);
                    const view = mapInstance.getView();
                    const projection = typeof view?.getProjection === 'function' ? view.getProjection() : null;
                    const projectionCode = typeof projection?.getCode === 'function' ? projection.getCode() : 'EPSG:3857';

                    if (!extent) {
                        try {
                            if (projection && window.ol?.proj?.transformExtent) {
                                extent = window.ol.proj.transformExtent(bbox, 'EPSG:4326', projection);
                            } else {
                                extent = bbox;
                            }
                        } catch (error) {
                            console.warn('Failed to transform Sentinel preview extent:', error);
                            extent = bbox;
                        }
                    }

                    const params = {
                        LAYERS: scene.wms.layers,
                        FORMAT: 'image/png',
                        TRANSPARENT: 'true',
                        TILED: 'true',
                        SHOWLOGO: 'false',
                        VERSION: '1.3.0',
                    };

                    if (projectionCode) {
                        params.CRS = projectionCode;
                        params.SRS = projectionCode;
                    }

                    if (scene.wms.time) {
                        const candidate = new Date(scene.wms.time);
                        if (!Number.isNaN(candidate.getTime())) {
                            params.TIME = candidate.toISOString();
                        } else if (typeof scene.wms.time === 'string') {
                            params.TIME = scene.wms.time;
                        }
                    }

                    const wmsUrl = withAccessToken(scene.wms.url);
                    const tileGrid = extent ? createTileGridForExtent(extent, projection) : null;
                    const source = new ol.source.TileWMS({
                        url: wmsUrl,
                        params,
                        crossOrigin: 'anonymous',
                        wrapX: false,
                        tileGrid: tileGrid || undefined,
                    });

                    if (!state.preview.wmsLayer) {
                        state.preview.wmsLayer = new ol.layer.Tile({
                            opacity: 1,
                            visible: false,
                        });
                        if (typeof state.preview.wmsLayer.setZIndex === 'function') {
                            state.preview.wmsLayer.setZIndex(config.previewWmsZIndex);
                        }
                        mapInstance.addLayer(state.preview.wmsLayer);
                    }

                    state.preview.wmsLayer.setSource(source);
                    if (extent) {
                        state.preview.wmsLayer.setExtent(extent);
                    } else {
                        state.preview.wmsLayer.setExtent(undefined);
                    }
                    state.preview.wmsLayer.setVisible(true);
                    state.preview.wmsActive = true;
                    return true;
                };

                /**
                 * Update the preview panel UI to reflect the selected scene.
                 */
                const updatePreviewPanel = (scene) => {
                    if (!elements.previewPanel) {
                        return;
                    }

                    elements.previewPanel.classList.remove('hidden');
                    if (elements.previewTitle) {
                        elements.previewTitle.textContent = scene.title;
                    }
                    if (elements.previewAcquired) {
                        elements.previewAcquired.textContent = scene.datetime ?
                            new Date(scene.datetime).toUTCString() :
                            'Acquisition time unavailable';
                    }

                    if (scene.details) {
                        if (elements.previewDetails) {
                            elements.previewDetails.textContent = scene.details;
                            elements.previewDetails.classList.remove('hidden');
                        }
                    } else {
                        if (elements.previewDetails) {
                            elements.previewDetails.textContent = '';
                            elements.previewDetails.classList.add('hidden');
                        }
                    }

                    if (scene.downloadUrl) {
                        if (elements.previewDownloadBtn) {
                            elements.previewDownloadBtn.href = scene.downloadUrl;
                            elements.previewDownloadBtn.download = scene.downloadName;
                            elements.previewDownloadBtn.setAttribute('aria-disabled', 'false');
                            elements.previewDownloadBtn.classList.remove('hidden');
                        }
                    } else {
                        if (elements.previewDownloadBtn) {
                            elements.previewDownloadBtn.href = '#';
                            elements.previewDownloadBtn.setAttribute('aria-disabled', 'true');
                            elements.previewDownloadBtn.classList.add('hidden');
                        }
                    }

                    if (elements.previewImageryBtn) {
                        if (scene.wms) {
                            elements.previewImageryBtn.classList.remove('hidden');
                            elements.previewImageryBtn.removeAttribute('disabled');
                            elements.previewImageryBtn.setAttribute('aria-disabled', 'false');
                        } else {
                            elements.previewImageryBtn.classList.add('hidden');
                            elements.previewImageryBtn.setAttribute('aria-disabled', 'true');
                        }
                    }

                    setPreviewStatus('Footprint highlighted on the map.');
                    if (elements.previewImageryBtn) {
                        elements.previewImageryBtn.dataset.sceneIndex = String(state.preview.activeSceneIndex ?? -1);
                        elements.previewImageryBtn.setAttribute('aria-pressed', 'false');
                    }
                    if (elements.previewImageryLabel) {
                        elements.previewImageryLabel.textContent = 'Preview Imagery';
                    }
                    if (elements.previewImageryIcon) {
                        elements.previewImageryIcon.classList.remove('ri-eye-off-line');
                        elements.previewImageryIcon.classList.add('ri-eye-line');
                    }
                };

                /**
                 * Handle user clicks on "Preview on Map" buttons within the card list.
                 */
                const handlePreview = (index) => {
                    const scene = state.scenes[index];
                    if (!scene) {
                        return;
                    }

                    state.preview.activeSceneIndex = index;
                    state.preview.wmsActive = false;

                    const footprintDisplayed = showFootprint(scene.geometry);
                    if (!footprintDisplayed) {
                        setPreviewStatus('Unable to display footprint on the map.');
                    }

                    toggleWmsLayer(false);
                    updatePreviewPanel(scene);
                };

                /**
                 * Hide the preview panel and clean up any active map overlays.
                 */
                const clearPreviewPanel = () => {
                    clearFootprint();
                    toggleWmsLayer(false);
                    state.preview.activeSceneIndex = null;
                    state.preview.wmsActive = false;
                    if (elements.previewPanel) {
                        elements.previewPanel.classList.add('hidden');
                    }
                    setPreviewStatus('Awaiting preview selection.');
                };

                /**
                 * Fetch Sentinel scenes using the current filter values.
                 */
                const loadCollections = async (options = {}) => {
                    const triggeredByFilter = options.triggeredByFilter === true;
                    const maxRecords = options.maxRecords ?
                        Number(options.maxRecords) :
                        triggeredByFilter ?
                        config.filteredMaxRecords :
                        config.defaultMaxRecords;

                    if (state.requestController) {
                        state.requestController.abort();
                    }

                    const controller = new AbortController();
                    state.requestController = controller;

                    setLoadingState(true);
                    setStatus('Fetching Sentinel-2 scenes...', true);

                    const params = buildQueryParams(maxRecords);
                    state.lastQueryString = params.toString();
                    const requestUrl = `${config.endpoint}?${state.lastQueryString}`;

                    const headers = {
                        Accept: 'application/json'
                    };
                    if (config.token) {
                        headers.Authorization = `Bearer ${config.token}`;
                    }

                    try {
                        const response = await fetch(requestUrl, {
                            headers,
                            signal: controller.signal
                        });
                        if (!response.ok) {
                            throw new Error(`Unable to fetch Sentinel-2 scenes (HTTP ${response.status}).`);
                        }

                        const payload = await response.json();
                        const features = Array.isArray(payload?.features) ? payload.features : [];
                        const scenes = features
                            .map(transformFeature)
                            .filter(Boolean)
                            .sort((a, b) => {
                                const aTime = a.acquisitionDate?.getTime() || 0;
                                const bTime = b.acquisitionDate?.getTime() || 0;
                                return bTime - aTime;
                            });

                        state.scenes = scenes;
                        state.loadedOnce = true;

                        renderSceneList();

                        if (scenes.length === 0) {
                            setStatus('No Sentinel-2 scenes found for the selected filters.');
                        } else {
                            const suffix = triggeredByFilter ? 'filters' : 'default filters';
                            setStatus(`Showing ${scenes.length} scene(s) based on ${suffix}.`);
                        }
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }
                        console.error('Sentinel catalogue fetch failed:', error);
                        state.scenes = [];
                        renderSceneList();
                        const fallbackMessage = error?.message || 'Failed to load Sentinel-2 scenes.';
                        setStatus(fallbackMessage);
                    } finally {
                        if (state.requestController === controller) {
                            state.requestController = null;
                        }
                        setLoadingState(false);
                    }
                };

                /**
                 * Reset filter inputs to their default values before refreshing the catalogue.
                 */
                const resetFilters = () => {
                    if (elements.cloudInput) {
                        elements.cloudInput.value = '40';
                    }
                    if (elements.productInput) {
                        elements.productInput.value = 'S2MSI2A';
                    }
                    if (elements.latInput) {
                        elements.latInput.value = '-1.24536';
                    }
                    if (elements.lonInput) {
                        elements.lonInput.value = '114.54535';
                    }
                    if (elements.startInput) {
                        elements.startInput.value = '';
                    }
                    if (elements.endInput) {
                        elements.endInput.value = '';
                    }
                    ensureDefaultDates();
                };

                // Wire up event listeners for filter submission, reset, and preview actions.
                elements.filterForm?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    loadCollections({
                        triggeredByFilter: true
                    }).catch(() => {
                        /* handled in loadCollections */
                    });
                });

                elements.resetButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    resetFilters();
                    loadCollections({
                        triggeredByFilter: false
                    }).catch(() => {
                        /* handled in loadCollections */
                    });
                });

                elements.previewClearBtn?.addEventListener('click', () => {
                    clearPreviewPanel();
                });

                elements.previewImageryBtn?.addEventListener('click', () => {
                    if (state.preview.activeSceneIndex == null) {
                        return;
                    }
                    const nextState = !state.preview.wmsActive;
                    const toggled = toggleWmsLayer(nextState);
                    if (!toggled) {
                        setPreviewStatus('Imagery preview is unavailable for this scene.');
                        return;
                    }
                    state.preview.wmsActive = nextState;
                    elements.previewImageryBtn.setAttribute('aria-pressed', String(nextState));
                    if (nextState) {
                        setPreviewStatus('Sentinel WMS preview enabled on the map.');
                        if (elements.previewImageryLabel) {
                            elements.previewImageryLabel.textContent = 'Hide Imagery';
                        }
                        elements.previewImageryIcon?.classList.remove('ri-eye-line');
                        elements.previewImageryIcon?.classList.add('ri-eye-off-line');
                    } else {
                        setPreviewStatus('Imagery preview hidden.');
                        if (elements.previewImageryLabel) {
                            elements.previewImageryLabel.textContent = 'Preview Imagery';
                        }
                        elements.previewImageryIcon?.classList.remove('ri-eye-off-line');
                        elements.previewImageryIcon?.classList.add('ri-eye-line');
                    }
                });

                const helpers = {
                    withAccessToken,
                    sanitizeFileName,
                    resolveDownloadUrl,
                };

                // Expose the Sentinel module to other parts of the app while keeping internals encapsulated.
                const sentinelModule = {
                    get loadedOnce() {
                        return state.loadedOnce;
                    },
                    loadCollections,
                    clearPreview: clearPreviewPanel,
                    resetFilters,
                    helpers,
                };

                window.AppMap.sentinel = sentinelModule;
                window.AppMap.sentinelHelpers = helpers;
                document.dispatchEvent(new CustomEvent('app:sentinel:ready', {
                    detail: sentinelModule
                }));

                // Bootstrap defaults and kick off the initial fetch.
                ensureDefaultDates();
                loadCollections().catch(() => {
                    /* handled inside loadCollections */
                });
            })();
        </script>
    @endpush

</x-app-front-map-layout>

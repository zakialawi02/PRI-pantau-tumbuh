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
            <div class="text-foreground/70 mt-auto text-xs">v0.1.156</div>
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

                                <button class="view-btn hover:bg-foreground/10 rounded-lg p-2 transition">
                                    <i class="ri-eye-line"></i>
                                </button>
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
            <section class="flex hidden h-full flex-col shadow-xl" id="sentinel-panel" data-sentinel-token="{{ $copernicusAccessToken ?? '' }}" data-sentinel-credentials="{{ $copernicusCredentialsConfigured ?? false ? 'true' : 'false' }}" data-sentinel-process-url="{{ auth()->check() ? route('admin.sentinel.process') : '' }}" data-sentinel-processing-cost="{{ config('app-constants.imagery_processing_cost', 10) }}">
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
                        <div class="space-y-3" id="sentinelClipModule" data-credit-rate="{{ config('app-constants.imagery_credit_cost_per_hectare') }}" data-process-url="{{ auth()->check() ? route('admin.sentinel.process') : '' }}" data-processing-cost="{{ config('app-constants.imagery_processing_cost', 10) }}">
                            <div class="bg-background/60 border-foreground/10 space-y-3 rounded-lg border p-3 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h4 class="text-foreground text-lg font-semibold">Define Area of Interest</h4>
                                        <p class="text-foreground/70 text-sm">Draw a polygon on the map to clip Sentinel-2 imagery for your field.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-button-primary id="drawPolygonBtn" type="button" size="small">
                                            <i class="ri-pencil-line"></i>
                                            <span>Draw Polygon</span>
                                        </x-button-primary>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipGeojsonOutput">AOI GeoJSON</x-input-label>
                                    <div class="border-foreground/10 bg-foreground/5 max-h-36 overflow-auto rounded-lg border p-2 font-mono text-xs" id="clipGeojsonOutput">
                                        <span class="text-foreground/60">Coordinates will appear here after drawing.</span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <x-input-label class="text-sm font-medium" for="clipFieldName">Field Name</x-input-label>
                                    <x-text-input class="w-full" id="clipFieldName" name="field_name" size="small" placeholder="e.g. North Farm Block" />
                                </div>
                            </div>

                            <div class="bg-background/60 border-foreground/10 space-y-3 rounded-lg border p-3 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-foreground text-lg font-semibold">Scene Selection</h4>
                                    <div class="border-foreground/20 inline-flex overflow-hidden rounded-full border" role="tablist">
                                        <button class="clip-mode-btn data-[active=true]:bg-primary data-[active=true]:text-primary-foreground px-3 py-1 text-xs font-semibold transition" id="clipModeAutoBtn" data-mode="auto" type="button" aria-pressed="true">Auto Mode</button>
                                        <button class="clip-mode-btn data-[active=true]:bg-primary data-[active=true]:text-primary-foreground px-3 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" id="clipModeManualBtn" data-mode="manual" type="button" aria-pressed="false" aria-disabled="true" disabled>Manual Mode</button>
                                    </div>
                                </div>

                                <div class="space-y-2" id="clipAutoPanel">
                                    <p class="text-foreground/70 text-sm">The system will analyse the date range and automatically choose the clearest Sentinel-2 scene intersecting your polygon.</p>
                                    <x-button-primary id="clipAutoSearchBtn" type="button" size="small" disabled>
                                        <i class="ri-magic-line"></i>
                                        <span>Find Best Scene</span>
                                    </x-button-primary>
                                    <div class="border-foreground/15 rounded-lg border border-dashed p-3 text-sm" id="clipAutoResult">
                                        Draw an area and search to preview the recommended scene.
                                    </div>
                                </div>

                            </div>

                            <div class="bg-background/60 border-foreground/10 space-y-2 rounded-lg border p-3 shadow-sm">
                                <h4 class="text-foreground text-lg font-semibold">Selected Scene</h4>
                                <div class="border-foreground/15 rounded-lg border p-3 text-sm" id="clipSelectionSummary">
                                    No scene selected yet. Use auto mode to pick one.
                                </div>
                                <x-button-primary class="w-full md:w-auto" id="clipProcessBtn" type="button" size="small" disabled>
                                    <i class="ri-cpu-line"></i>
                                    <span>Process &amp; Download</span>
                                </x-button-primary>
                                <p class="text-foreground/60 text-xs" id="clipProcessNotice">Processing will run in the background via job queue. We will notify you when the imagery is ready.</p>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="bg-background border-foreground/10 sticky bottom-0 border-t p-3">
                    <div class="text-foreground/70 text-xs">
                        Data source: Copernicus Sentinel-2 (via public catalogue)
                    </div>
                    <div class="text-foreground/50 text-xs">
                        Last updated: <span id="sentinelLastUpdated">–</span>
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

                const formatCredits = (value, decimals = 2) => {
                    const numeric = Number(value);
                    if (!Number.isFinite(numeric)) {
                        return Number(0).toFixed(decimals);
                    }
                    return numeric.toLocaleString(undefined, {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    });
                };

                const updateDisplayedCredits = (value) => {
                    if (!Number.isFinite(value)) {
                        return;
                    }

                    const formatted = formatCredits(value, 2);
                    if (typeof window.$ === 'function') {
                        window.$('#current-myCredits')?.text(formatted);
                    } else {
                        const target = document.getElementById('current-myCredits');
                        if (target) {
                            target.textContent = formatted;
                        }
                    }
                };

                const computeRequiredCreditEstimate = () => {
                    const candidates = [
                        Number(config.processingCost),
                        Number(window.AppMap?.constants?.imageryProcessingCost),
                    ].filter((value) => Number.isFinite(value) && value > 0);

                    return candidates.length ? Math.max(...candidates) : null;
                };

                window.AppMap.constants = window.AppMap.constants || {};
                if (!Number.isFinite(window.AppMap.constants.imageryProcessingCost) || window.AppMap.constants.imageryProcessingCost <= 0) {
                    window.AppMap.constants.imageryProcessingCost = config.processingCost;
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
                    }, { once: true });
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
                const setStatus = (message) => {
                    if (elements.status) {
                        elements.status.textContent = message;
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
                        elements.list?.classList.add('opacity-60');
                    } else {
                        elements.list?.classList.remove('opacity-60');
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
                        coordinates: [[
                            [minLon, minLat],
                            [maxLon, minLat],
                            [maxLon, maxLat],
                            [minLon, maxLat],
                            [minLon, minLat],
                        ]],
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
                        geometry.type === 'GeometryCollection'
                            ? geometry.geometries || []
                            : [geometry];

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

                    const tileSize = Number.isFinite(config.previewTileSize)
                        ? config.previewTileSize
                        : 256;
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

                    const cap = Number.isFinite(config.previewMaxMetersPerPixel)
                        ? Math.max(1, config.previewMaxMetersPerPixel)
                        : 190;
                    const startingResolution = Math.min(baseResolution, cap);

                    const zoomLevels = Number.isFinite(config.previewTileZoomLevels)
                        ? Math.max(1, Math.floor(config.previewTileZoomLevels))
                        : 22;

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
                 * Determine the acquisition timestamp of a scene so we can sort and format it.
                 */
                const resolveAcquisitionDate = (feature) => {
                    const props = feature?.properties ?? {};
                    const candidates = [
                        props.completionDate,
                        props.endPosition,
                        props.startDate,
                        props.beginPosition,
                        props.contentDate?.end,
                        props.contentDate?.start,
                        props.datetime,
                    ];
                    for (const candidate of candidates) {
                        if (!candidate) continue;
                        const date = new Date(candidate);
                        if (!Number.isNaN(date.getTime())) {
                            return date;
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
                                time: candidate?.time || candidate?.TIME || (acquisitionDate ? acquisitionDate.toISOString() : null),
                            };
                        }
                    }

                    if (config.defaultWmsEndpoint && config.defaultWmsLayer) {
                        // Use the shared Copernicus service when the catalogue does not expose a per-scene WMS link.
                        return {
                            url: config.defaultWmsEndpoint,
                            layers: config.defaultWmsLayer,
                            time: acquisitionDate ? acquisitionDate.toISOString() : null,
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
                    const title = props.title || props.productIdentifier || feature.id || 'Sentinel-2 Scene';
                    const acquisitionDate = resolveAcquisitionDate(feature);
                    const cloudCover = resolveCloudCover(feature);
                    const mgrs = props.mgrsId || props.tileId || props.MGRS || props.utmc || null;
                    const downloadUrl = withAccessToken(resolveDownloadUrl(feature));
                    const thumbnailUrl = resolveThumbnailUrl(feature);
                    const wms = resolveWmsConfig(feature, acquisitionDate);
                    const geometry = feature.geometry || geometryFromBbox(feature.bbox || []);
                    const bbox = Array.isArray(feature.bbox) && feature.bbox.length >= 4
                        ? feature.bbox.slice(0, 4)
                        : bboxFromGeometry(geometry);

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
                        datetime: acquisitionDate ? acquisitionDate.toISOString() : null,
                        acquisitionDate,
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
                        window.MyZkToast?.warning?.('Please sign in to process Sentinel-2 imagery.');
                        return;
                    }

                    if (!scene.downloadUrl) {
                        window.MyZkToast?.warning?.('Selected scene is missing a Copernicus download link.');
                        return;
                    }

                    if (typeof window.checkUserCredits !== 'function') {
                        window.MyZkToast?.error?.('Credit checker is unavailable.');
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

                    const enqueueProcessing = () => {
                        setButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Queuing...</span>', true);

                        fetch(config.processUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(payload),
                        })
                            .then(async (response) => {
                                const data = await response.json().catch(() => ({}));
                                if (Number.isFinite(data?.data?.current_credits)) {
                                    updateDisplayedCredits(Number(data.data.current_credits));
                                }
                                if (!response.ok || data?.success === false) {
                                    throw new Error(data?.message || 'Unable to queue Sentinel imagery.');
                                }
                                window.MyZkToast?.success?.(data?.message || 'Sentinel scene queued for processing.');
                            })
                            .catch((error) => {
                                window.MyZkToast?.error?.(error?.message || 'Unable to queue Sentinel imagery.');
                            })
                            .finally(() => {
                                setButtonState(originalHtml, false);
                            });
                    };

                    const requiredEstimate = computeRequiredCreditEstimate();
                    setButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Checking credits...</span>', true);

                    window.checkUserCredits(requiredEstimate ?? undefined)
                        .then((creditInfo) => {
                            setButtonState(originalHtml, false);

                            if (Number.isFinite(creditInfo?.currentCredits)) {
                                updateDisplayedCredits(Number(creditInfo.currentCredits));
                            }

                            const fallbackRequired = requiredEstimate ?? config.processingCost ?? 0;
                            const resolvedRequired = Number.isFinite(creditInfo?.requiredCredits) && creditInfo.requiredCredits > 0
                                ? creditInfo.requiredCredits
                                : fallbackRequired;

                            const message = creditInfo?.hasCredits
                                ? `Processing this scene will deduct ${formatCredits(resolvedRequired, 2)} credit points. You currently have ${formatCredits(creditInfo?.currentCredits ?? 0, 2)} credit points. Continue?`
                                : `Insufficient credits to process this scene. You need ${formatCredits(resolvedRequired, 2)} credit points but only have ${formatCredits(creditInfo?.currentCredits ?? 0, 2)} credit points.`;

                            const confirmAction = () => {
                                if (!creditInfo?.hasCredits) {
                                    window.MyZkToast?.warning?.('Insufficient credits to process this scene.');
                                    return;
                                }
                                enqueueProcessing();
                            };

                            if (window.ZkPopAlert?.show) {
                                const dialogConfig = {
                                    message,
                                    icon: '<i class="ri-cpu-line text-2xl text-primary"></i>',
                                    onConfirm: confirmAction,
                                };

                                if (creditInfo?.hasCredits) {
                                    dialogConfig.confirmText = 'Yes, Process';
                                    dialogConfig.cancelText = 'Cancel';
                                } else {
                                    dialogConfig.confirmText = 'Close';
                                    dialogConfig.hideCancel = true;
                                }

                                ZkPopAlert.show(dialogConfig);
                            } else {
                                if (!creditInfo?.hasCredits) {
                                    window.alert(message);
                                    return;
                                }
                                if (window.confirm(message)) {
                                    enqueueProcessing();
                                }
                            }
                        })
                        .catch((error) => {
                            setButtonState(originalHtml, false);
                            window.MyZkToast?.error?.(error?.message || 'Failed to verify credit balance.');
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
                        datetimeEl.textContent = scene.datetime
                            ? `Acquired: ${new Date(scene.datetime).toUTCString()}`
                            : 'Acquired: –';
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
                            stroke: new ol.style.Stroke({ color: 'rgba(37, 99, 235, 0.9)', width: 2 }),
                            fill: new ol.style.Fill({ color: 'rgba(37, 99, 235, 0.15)' }),
                        }),
                        properties: { name: 'sentinel-footprint' },
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
                    const feature = reader.readFeature({ type: 'Feature', geometry }, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: mapInstance.getView().getProjection(),
                    });

                    source.addFeature(feature);
                    layer.setVisible(true);

                    const extent = feature.getGeometry().getExtent();
                    mapInstance.getView().fit(extent, { duration: 400, padding: [40, 40, 40, 40], maxZoom: 13 });
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
                            const currentSource = typeof state.preview.wmsLayer.getSource === 'function'
                                ? state.preview.wmsLayer.getSource()
                                : null;
                            if (currentSource?.updateParams) {
                                currentSource.updateParams({ TIME: undefined });
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
                            opacity: 0.7,
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
                        elements.previewAcquired.textContent = scene.datetime
                            ? new Date(scene.datetime).toUTCString()
                            : 'Acquisition time unavailable';
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
                    const maxRecords = options.maxRecords
                        ? Number(options.maxRecords)
                        : triggeredByFilter
                            ? config.filteredMaxRecords
                            : config.defaultMaxRecords;

                    if (state.requestController) {
                        state.requestController.abort();
                    }

                    const controller = new AbortController();
                    state.requestController = controller;

                    setLoadingState(true);
                    setStatus('Fetching Sentinel-2 scenes...');

                    const params = buildQueryParams(maxRecords);
                    state.lastQueryString = params.toString();
                    const requestUrl = `${config.endpoint}?${state.lastQueryString}`;

                    const headers = { Accept: 'application/json' };
                    if (config.token) {
                        headers.Authorization = `Bearer ${config.token}`;
                    }

                    try {
                        const response = await fetch(requestUrl, { headers, signal: controller.signal });
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
                    loadCollections({ triggeredByFilter: true }).catch(() => {
                        /* handled in loadCollections */
                    });
                });

                elements.resetButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    resetFilters();
                    loadCollections({ triggeredByFilter: false }).catch(() => {
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
                document.dispatchEvent(new CustomEvent('app:sentinel:ready', { detail: sentinelModule }));

                // Bootstrap defaults and kick off the initial fetch.
                ensureDefaultDates();
                loadCollections().catch(() => {
                    /* handled inside loadCollections */
                });
            })();
        </script>
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

                const initialiseClipModule = () => {
                    const moduleEl = document.getElementById('sentinelClipModule');
                    if (!moduleEl) {
                        return;
                    }

                    const clipConfig = {
                        endpoint: 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json',
                        maxRecords: 50,
                        defaultDateWindowDays: 30,
                        defaultMaxCloud: 60,
                        processingCost: Number.parseFloat(moduleEl.dataset.processingCost ?? '') || 0,
                    };

                    const elements = {
                        areaOutput: document.getElementById('clipAreaOutput'),
                        creditOutput: document.getElementById('clipCreditOutput'),
                        geojsonOutput: document.getElementById('clipGeojsonOutput'),
                        fieldInput: document.getElementById('clipFieldName'),
                        autoBtn: document.getElementById('clipAutoSearchBtn'),
                        modeButtons: Array.from(document.querySelectorAll('.clip-mode-btn')),
                        autoPanel: document.getElementById('clipAutoPanel'),
                        autoResult: document.getElementById('clipAutoResult'),
                        selectionSummary: document.getElementById('clipSelectionSummary'),
                        processBtn: document.getElementById('clipProcessBtn'),
                        processNotice: document.getElementById('clipProcessNotice'),
                        processUrl: moduleEl.dataset.processUrl || '',
                    };

                    const defaultProcessButtonHtml = elements.processBtn?.innerHTML || '<i class="ri-cpu-line"></i><span>Process Imagery</span>';

                    const sentinelHelpers = window.AppMap?.sentinel?.helpers || window.AppMap?.sentinelHelpers || {};
                    const applyAccessToken = typeof sentinelHelpers.withAccessToken === 'function'
                        ? sentinelHelpers.withAccessToken
                        : (url) => url;
                    const sanitizeFileName = typeof sentinelHelpers.sanitizeFileName === 'function'
                        ? sentinelHelpers.sanitizeFileName
                        : (value, fallback = 'sentinel-scene') => {
                            const base = (value || '').toString().trim();
                            const normalized = base
                                .normalize('NFKD')
                                .replace(/[\u0300-\u036f]/g, '')
                                .replace(/[^a-zA-Z0-9-_.]+/g, '-')
                                .replace(/-{2,}/g, '-')
                                .replace(/^-+|-+$/g, '');
                            return (normalized || fallback).substring(0, 120);
                        };

                    const fallbackResolveDownloadUrl = (feature) => {
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

                    const resolveDownloadLink = typeof sentinelHelpers.resolveDownloadUrl === 'function'
                        ? sentinelHelpers.resolveDownloadUrl
                        : fallbackResolveDownloadUrl;

                    const state = {
                        geometry: null,
                        feature: null,
                        areaSqMeters: 0,
                        areaHectares: 0,
                        creditRate: Number(moduleEl.dataset.creditRate ?? '0') || 0,
                        mode: 'auto',
                        autoScene: null,
                        selectedScene: null,
                    };

                    const safeFormatNumber = (value, decimals = 2) => {
                        const numeric = Number(value ?? 0);
                        if (!Number.isFinite(numeric)) {
                            return '0';
                        }
                        if (typeof window.formatNumber === 'function') {
                            return window.formatNumber(numeric, decimals);
                        }
                        return numeric.toFixed(decimals);
                    };

                    const formatIsoDate = (date) => {
                        const copy = new Date(date.getTime());
                        copy.setHours(0, 0, 0, 0);
                        return copy.toISOString().split('T')[0];
                    };

                    const formatHumanDate = (value) => {
                        if (typeof window.formatReadableDate === 'function') {
                            return window.formatReadableDate(value);
                        }
                        if (!value) {
                            return '–';
                        }
                        try {
                            const parsed = new Date(value);
                            return parsed.toISOString().replace('T', ' ').replace('Z', ' UTC');
                        } catch (_) {
                            return value;
                        }
                    };

                    const getDefaultDateRange = () => {
                        const end = new Date();
                        const start = new Date();
                        start.setDate(start.getDate() - clipConfig.defaultDateWindowDays);
                        return {
                            start: formatIsoDate(start),
                            end: formatIsoDate(end),
                        };
                    };

                    const computeCreditCost = () => {
                        const cost = state.areaHectares * state.creditRate;
                        return Number.isFinite(cost) ? cost : 0;
                    };

                    const updateAreaDisplay = () => {
                        if (!elements.areaOutput) return;
                        if (state.areaHectares > 0) {
                            elements.areaOutput.innerHTML = `<strong>${safeFormatNumber(state.areaHectares, 2)} ha</strong>`;
                        } else {
                            elements.areaOutput.textContent = 'Draw a polygon to calculate the area.';
                        }
                    };

                    const updateCreditDisplay = () => {
                        if (!elements.creditOutput) return;
                        if (state.areaHectares > 0 && state.creditRate > 0) {
                            const cost = computeCreditCost();
                            elements.creditOutput.innerHTML = `<span class="font-semibold text-primary">${safeFormatNumber(cost, 2)} Credit Points</span>`;
                        } else {
                            elements.creditOutput.textContent = '–';
                        }
                    };

                    const updateGeojsonDisplay = () => {
                        if (!elements.geojsonOutput) return;
                        if (state.feature) {
                            elements.geojsonOutput.innerHTML = `<pre>${JSON.stringify(state.feature, null, 2)}</pre>`;
                        } else {
                            elements.geojsonOutput.innerHTML = '<span class="text-foreground/60">Coordinates will appear here after drawing.</span>';
                        }
                    };

                    const resetAutoResult = () => {
                        if (!elements.autoResult) return;
                        elements.autoResult.textContent = state.geometry ?
                            'Click “Find Best Scene” to analyse the area.' :
                            'Draw an area and search to preview the recommended scene.';
                    };

                    const updateProcessAvailability = () => {
                        if (!elements.processBtn) return;
                        const fieldNameFilled = (elements.fieldInput?.value ?? '').trim().length > 0;
                        const hasScene = !!(state.selectedScene && state.selectedScene.download_url);
                        const hasGeometry = !!state.geometry;
                        const urlAvailable = elements.processUrl !== '';

                        elements.processBtn.disabled = !(fieldNameFilled && hasScene && hasGeometry && urlAvailable);

                        if (!urlAvailable && elements.processNotice) {
                            elements.processNotice.textContent = 'Log in to clip and download imagery.';
                        } else if (state.selectedScene && !state.selectedScene.download_url && elements.processNotice) {
                            elements.processNotice.textContent = 'Selected scene is missing a Copernicus download link.';
                        } else if (elements.processNotice) {
                            elements.processNotice.textContent = 'Processing will run in the background via job queue. We will notify you when the imagery is ready.';
                        }
                    };

                    const updateSelectionSummary = () => {
                        if (!elements.selectionSummary) return;
                        if (!state.selectedScene) {
                            elements.selectionSummary.textContent = 'No scene selected yet. Use auto mode to pick one.';
                            updateProcessAvailability();
                            return;
                        }

                        const parts = [];
                        parts.push(`<div class="font-semibold">${state.selectedScene.title ?? 'Sentinel-2 Scene'}</div>`);
                        if (state.selectedScene.datetime) {
                            parts.push(`<div class="text-xs text-foreground/70">Acquired: ${formatHumanDate(state.selectedScene.datetime)}</div>`);
                        }
                        if (state.selectedScene.cloud_cover !== undefined && state.selectedScene.cloud_cover !== null) {
                            parts.push(`<div class="text-xs text-foreground/70">Cloud cover: ${safeFormatNumber(state.selectedScene.cloud_cover, 2)}%</div>`);
                        }
                        parts.push('<div class="text-xs text-foreground/60">Mode: Auto selection</div>');

                        elements.selectionSummary.innerHTML = parts.join('');
                        updateProcessAvailability();
                    };

                    const selectScene = (scene) => {
                        state.autoScene = scene || null;
                        state.selectedScene = scene || null;
                        if (scene && elements.autoResult) {
                            elements.autoResult.innerHTML = `
                                <div class="font-semibold">${scene.title ?? 'Latest Scene'}</div>
                                <div class="text-xs text-foreground/70">Acquired: ${formatHumanDate(scene.datetime)}</div>
                                <div class="text-xs text-foreground/70">Cloud cover: ${safeFormatNumber(scene.cloud_cover ?? 0, 2)}%</div>
                            `;
                        } else if (elements.autoResult) {
                            resetAutoResult();
                        }

                        updateSelectionSummary();
                    };

                    const setMode = () => {
                        state.mode = 'auto';
                        elements.modeButtons.forEach((button) => {
                            const isAuto = button.dataset.mode === 'auto';
                            if (!isAuto) {
                                button.disabled = true;
                                button.setAttribute('aria-disabled', 'true');
                            }
                            button.dataset.active = isAuto ? 'true' : 'false';
                            button.setAttribute('aria-pressed', isAuto ? 'true' : 'false');
                        });

                        if (elements.autoPanel) {
                            elements.autoPanel.classList.remove('hidden');
                        }
                    };

                    const computeCentroid = (geometry) => {
                        if (!geometry) return null;
                        const coords = Array.isArray(geometry.coordinates) ? geometry.coordinates : [];
                        const ring = coords[0] ?? [];
                        if (!ring.length) return null;
                        let sumX = 0;
                        let sumY = 0;
                        let count = 0;
                        ring.forEach((point) => {
                            if (Array.isArray(point) && point.length >= 2) {
                                sumX += Number(point[0]);
                                sumY += Number(point[1]);
                                count++;
                            }
                        });
                        if (!count) return null;
                        return [sumX / count, sumY / count];
                    };

                    const toWkt = (geometry) => {
                        if (!geometry || typeof geometry !== 'object') return null;
                        const type = geometry.type;
                        const coords = geometry.coordinates;
                        if (!type || !coords) return null;

                        const normalizeRing = (ring) => {
                            if (!Array.isArray(ring) || !ring.length) return null;
                            const points = ring
                                .map((coord) =>
                                    Array.isArray(coord) && coord.length >= 2 ? [Number(coord[0]), Number(coord[1])] :
                                    null
                                )
                                .filter((coord) => Array.isArray(coord));
                            if (!points.length) return null;
                            const [firstLon, firstLat] = points[0];
                            const [lastLon, lastLat] = points[points.length - 1];
                            if (firstLon !== lastLon || firstLat !== lastLat) {
                                points.push([firstLon, firstLat]);
                            }
                            return points;
                        };

                        const formatRing = (ring) => {
                            const normalized = normalizeRing(ring);
                            if (!normalized) return null;
                            return normalized
                                .map((coord) => `${coord[0]} ${coord[1]}`)
                                .join(', ');
                        };

                        switch (type) {
                            case 'Polygon': {
                                if (!Array.isArray(coords) || !coords.length) return null;
                                const rings = coords
                                    .map((ring) => {
                                        if (!Array.isArray(ring) || !ring.length) return null;
                                        const wktRing = formatRing(ring);
                                        return wktRing ? `(${wktRing})` : null;
                                    })
                                    .filter(Boolean);
                                return rings.length ? `POLYGON (${rings.join(', ')})` : null;
                            }
                            case 'MultiPolygon': {
                                if (!Array.isArray(coords) || !coords.length) return null;
                                const polygons = coords
                                    .map((polygon) => {
                                        if (!Array.isArray(polygon) || !polygon.length) return null;
                                        const rings = polygon
                                            .map((ring) => {
                                                if (!Array.isArray(ring) || !ring.length) return null;
                                                const wktRing = formatRing(ring);
                                                return wktRing ? `(${wktRing})` : null;
                                            })
                                            .filter(Boolean);
                                        return rings.length ? `(${rings.join(', ')})` : null;
                                    })
                                    .filter(Boolean);
                                return polygons.length ? `MULTIPOLYGON (${polygons.join(', ')})` : null;
                            }
                            default:
                                return null;
                        }
                    };

                    const buildQueryParams = () => {
                        const params = new URLSearchParams();
                        const {
                            start,
                            end
                        } = getDefaultDateRange();
                        params.set('startDate', `${start}T00:00:00Z`);
                        params.set('completionDate', `${end}T23:59:59Z`);
                        params.set('maxRecords', String(clipConfig.maxRecords));

                        if (state.geometry) {
                            const wkt = toWkt(state.geometry);
                            if (wkt) {
                                params.set('geometry', wkt);
                            }
                            const centroid = computeCentroid(state.geometry);
                            if (centroid) {
                                params.set('lat', centroid[1].toFixed(6));
                                params.set('lon', centroid[0].toFixed(6));
                            }
                        }

                        return params;
                    };

                    const getCloudCover = (feature) => {
                        const props = feature?.properties ?? {};
                        const keys = ['eo:cloudCover', 'cloudCover', 'cloudcoverpercentage', 'cloudCoverageAssessment'];
                        for (const key of keys) {
                            if (props[key] !== undefined && props[key] !== null) {
                                const value = Number(props[key]);
                                if (Number.isFinite(value)) {
                                    return value;
                                }
                            }
                        }
                        return null;
                    };

                    const getAcquisitionTimestamp = (feature) => {
                        const props = feature?.properties ?? {};
                        const candidates = [
                            props.completionDate,
                            props.endPosition,
                            props.startDate,
                            props.contentDate?.end,
                            props.contentDate?.start,
                            props.datetime,
                            feature?.id && typeof feature.id === 'string' && feature.id.includes('T') ? feature.id : null,
                        ];

                        for (const candidate of candidates) {
                            if (!candidate) continue;
                            const date = new Date(candidate);
                            if (!Number.isNaN(date.getTime())) {
                                return date.getTime();
                            }
                        }

                        return 0;
                    };

                    const buildScenePayload = (feature) => {
                        const props = feature?.properties ?? {};
                        const sceneId = feature?.id || props.productIdentifier || props.title || props.id || null;
                        const acquisition = props.completionDate || props.startDate || props.datetime || props.endPosition || props.beginPosition || null;
                        const rawTitle = props.title || props.productIdentifier || (sceneId ? String(sceneId) : 'Sentinel-2 Scene');
                        const downloadSource = resolveDownloadLink(feature);
                        const downloadUrl = downloadSource ? applyAccessToken(downloadSource) : null;
                        const downloadFilename = sanitizeFileName(`${rawTitle}.zip`);
                        return {
                            id: sceneId ? String(sceneId) : null,
                            product_id: props.productIdentifier || null,
                            title: rawTitle,
                            datetime: acquisition || null,
                            cloud_cover: getCloudCover(feature),
                            collection: props.collection || props.collectionName || null,
                            mgrs: props.mgrsId || props.tileId || props.MGRS || null,
                            download_url: downloadUrl,
                            download_filename: downloadFilename,
                        };
                    };

                    const fetchScenes = async () => {
                        const params = buildQueryParams();
                        const requestUrl = `${clipConfig.endpoint}?${params.toString()}`;
                        const response = await fetch(requestUrl);
                        if (!response.ok) {
                            throw new Error('Failed to fetch Sentinel-2 scenes.');
                        }
                        const data = await response.json();
                        const features = Array.isArray(data?.features) ? data.features : [];
                        return features.sort((a, b) => getAcquisitionTimestamp(b) - getAcquisitionTimestamp(a));
                    };

                    const handleAutoSearch = async () => {
                        if (!state.geometry) {
                            window.MyZkToast?.warning?.('Draw a polygon first to enable auto selection.');
                            return;
                        }
                        if (!elements.autoBtn) return;
                        const originalHtml = elements.autoBtn.innerHTML;
                        elements.autoBtn.disabled = true;
                        elements.autoBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i><span>Searching...</span>';

                        try {
                            const features = await fetchScenes();
                            if (!features.length) {
                                if (elements.autoResult) {
                                    elements.autoResult.textContent = 'No recent scenes found for this area.';
                                }
                                state.autoScene = null;
                                selectScene(null);
                                return;
                            }

                            const latestFeature = features[0];
                            const sceneData = buildScenePayload(latestFeature);
                            state.autoScene = sceneData;
                            selectScene(sceneData);
                        } catch (error) {
                            if (elements.autoResult) {
                                elements.autoResult.textContent = error?.message || 'Unable to fetch scenes. Please try again later.';
                            }
                        } finally {
                            elements.autoBtn.disabled = false;
                            elements.autoBtn.innerHTML = originalHtml;
                            updateProcessAvailability();
                        }
                    };

                    const updateGeometryState = (detail) => {
                        state.geometry = detail?.geometry || null;
                        state.feature = detail?.feature || (state.geometry ? {
                            type: 'Feature',
                            properties: {},
                            geometry: state.geometry
                        } : null);
                        state.areaSqMeters = detail?.areaSqMeters || 0;
                        state.areaHectares = detail?.areaHectares || 0;

                        if (!state.geometry) {
                            state.autoScene = null;
                            state.selectedScene = null;
                            resetAutoResult();
                        }

                        updateAreaDisplay();
                        updateCreditDisplay();
                        updateGeojsonDisplay();
                        if (elements.autoBtn) {
                            elements.autoBtn.disabled = !state.geometry;
                        }
                        updateSelectionSummary();
                        updateProcessAvailability();
                    };

                    const handleProcess = () => {
                        if (!elements.processBtn) return;
                        if (!elements.processUrl) {
                            window.MyZkToast?.warning?.('Please sign in to process Sentinel-2 imagery.');
                            return;
                        }
                        if (!state.geometry) {
                            window.MyZkToast?.warning?.('Draw a polygon before processing.');
                            return;
                        }
                        if (!state.selectedScene) {
                            window.MyZkToast?.warning?.('Select a Sentinel-2 scene first.');
                            return;
                        }

                        const scene = state.selectedScene;
                        if (!scene.download_url) {
                            window.MyZkToast?.warning?.('Selected scene does not expose a downloadable link.');
                            return;
                        }

                        const fieldName = (elements.fieldInput?.value || '').trim();
                        if (!fieldName) {
                            window.MyZkToast?.warning?.('Please enter a field name.');
                            elements.fieldInput?.focus();
                            return;
                        }

                        const {
                            start: defaultDateFrom,
                            end: defaultDateTo
                        } = getDefaultDateRange();

                        const basePayload = {
                            field_name: fieldName,
                            geometry: state.feature?.geometry ?? state.geometry,
                            area_hectares: Number(state.areaHectares.toFixed(4)),
                            mode: state.mode,
                            date_from: defaultDateFrom,
                            date_to: defaultDateTo,
                            max_cloud: clipConfig.defaultMaxCloud,
                            limit: clipConfig.maxRecords,
                            resolution: 10,
                            nodata: 0,
                            selected_scene: state.selectedScene,
                        };

                        const processPayload = {
                            ...basePayload,
                            title: scene.title || 'Sentinel-2 Scene',
                            download_url: scene.download_url,
                            product_id: scene.product_id || scene.id || null,
                            collection: scene.collection || null,
                            acquisition_date: scene.datetime || null,
                            download_filename: scene.download_filename || sanitizeFileName(`${scene.title || 'Sentinel-2 Scene'}.zip`),
                        };

                        const creditCandidates = [
                            computeCreditCost(),
                            clipConfig.processingCost,
                            window.AppMap?.constants?.imageryProcessingCost,
                        ]
                            .map((value) => Number(value))
                            .filter((value) => Number.isFinite(value) && value > 0);

                        const requiredCreditEstimate = creditCandidates.length ? Math.max(...creditCandidates) : null;

                        const setProcessButtonState = (html, disabled) => {
                            if (!elements.processBtn) return;
                            if (typeof disabled === 'boolean') {
                                elements.processBtn.disabled = disabled;
                            }
                            if (typeof html === 'string') {
                                elements.processBtn.innerHTML = html;
                            }
                        };

                        const restoreProcessButton = () => {
                            setProcessButtonState(defaultProcessButtonHtml, false);
                            updateProcessAvailability();
                        };

                        const proceed = () => {
                            setProcessButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Processing...</span>', true);

                            fetch(elements.processUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                                },
                                body: JSON.stringify(processPayload),
                            })
                                .then(async (response) => {
                                    const data = await response.json().catch(() => ({}));
                                    if (data?.data?.current_credits !== undefined) {
                                        $('#current-myCredits')?.text(safeFormatNumber(data.data.current_credits, 2));
                                    }
                                    if (!response.ok) {
                                        const message = data?.message || 'Unable to queue Sentinel-2 clipping request.';
                                        throw new Error(message);
                                    }
                                    if (window.MyZkToast?.success) {
                                        window.MyZkToast.success(data?.message || 'Sentinel-2 clipping queued successfully.');
                                    }
                                })
                                .catch((error) => {
                                    if (window.MyZkToast?.error) {
                                        window.MyZkToast.error(error?.message || 'Failed to queue clipping request.');
                                    }
                                })
                                .finally(() => {
                                    restoreProcessButton();
                                });
                        };

                        setProcessButtonState('<i class="ri-loader-4-line animate-spin"></i><span>Checking credits...</span>', true);

                        checkUserCredits(requiredCreditEstimate ?? undefined)
                            .then((creditInfo) => {
                                restoreProcessButton();

                                if (creditInfo && typeof creditInfo.currentCredits === 'number') {
                                    $('#current-myCredits')?.text(safeFormatNumber(creditInfo.currentCredits, 2));
                                }

                                const requiredCredits = Number.isFinite(creditInfo?.requiredCredits) && creditInfo.requiredCredits > 0
                                    ? creditInfo.requiredCredits
                                    : (requiredCreditEstimate ?? 0);

                                const message = creditInfo?.hasCredits
                                    ? `Processing this scene will deduct ${safeFormatNumber(requiredCredits, 2)} credit points. You currently have ${safeFormatNumber(creditInfo.currentCredits ?? 0, 2)} credit points. Continue?`
                                    : `Insufficient credits to process this scene. You need ${safeFormatNumber(requiredCredits, 2)} credit points but only have ${safeFormatNumber(creditInfo?.currentCredits ?? 0, 2)} credit points.`;

                                if (window.ZkPopAlert?.show) {
                                    const dialogConfig = {
                                        message,
                                        icon: '<i class="ri-cpu-line text-2xl text-primary"></i>',
                                        confirmText: creditInfo?.hasCredits ? 'Yes, Process' : 'Close',
                                        onConfirm: () => {
                                            if (!creditInfo?.hasCredits) {
                                                if (window.MyZkToast?.warning) {
                                                    window.MyZkToast.warning('Insufficient credits to process this scene.');
                                                }
                                                return;
                                            }
                                            proceed();
                                        },
                                    };

                                    if (creditInfo?.hasCredits) {
                                        dialogConfig.cancelText = 'Cancel';
                                    } else {
                                        dialogConfig.hideCancel = true;
                                    }

                                    ZkPopAlert.show(dialogConfig);
                                } else {
                                    if (!creditInfo?.hasCredits) {
                                        window.alert(message);
                                        return;
                                    }
                                    if (window.confirm(message)) {
                                        proceed();
                                    }
                                }
                            })
                            .catch((error) => {
                                restoreProcessButton();
                                if (window.MyZkToast?.error) {
                                    window.MyZkToast.error(error?.message || 'Failed to verify credit balance.');
                                }
                            });
                    };

                    updateAreaDisplay();
                    updateCreditDisplay();
                    updateGeojsonDisplay();
                    resetAutoResult();
                    setMode();
                    updateSelectionSummary();
                    updateProcessAvailability();
                    if (elements.autoBtn) {
                        elements.autoBtn.disabled = true;
                    }

                    elements.modeButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            setMode();
                        });
                    });

                    elements.autoBtn?.addEventListener('click', handleAutoSearch);
                    elements.fieldInput?.addEventListener('input', updateProcessAvailability);
                    elements.processBtn?.addEventListener('click', handleProcess);

                    document.addEventListener('app:clip:geometry', (event) => {
                        updateGeometryState(event.detail || {});
                    });

                    if (window.AppMap?.clip?.latest) {
                        updateGeometryState(window.AppMap.clip.latest);
                    }
                };

                const bootstrapPanels = () => {
                    window.showPanel = showPanel;
                    window.closePanels = closePanels;

                    initialiseHorizontalScroll();
                    initialiseDefaultPanel();
                    registerEventListeners();
                    initialiseClipModule();
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

                        const meta = `${sizeMb} MB • ${uploadDate} • <span class="imagery-status font-semibold ${uploadStatusInfo.className}">${uploadStatusInfo.label}</span> • <span class="imagery-status font-semibold ${processingStatusInfo.className}">${processingStatusInfo.label}</span>`;
                        card.querySelector('.imagery-meta').innerHTML = meta;

                        const viewBtn = card.querySelector('.view-btn');
                        viewBtn?.addEventListener('click', () => viewImagery(item));

                        return fragment;
                    };

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
    @endpush

</x-app-front-map-layout>

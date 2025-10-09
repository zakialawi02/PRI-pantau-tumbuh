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

            <x-button-primary href="{{ route('login') }}" size="small" variant="outline">Login</x-button-primary>

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
                    <span class="text-xl">🛰️</span>
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
        </aside>

        <!-- MOBILE SIDEBAR HORIZONTAL -->
        <div class="fixed left-0 top-11 z-40 mx-auto w-full whitespace-nowrap bg-transparent px-4 py-2 md:hidden">
            <button class="bg-neutral border-foreground/70 hover:bg-muted absolute left-0 top-1/2 z-10 mx-0.5 -translate-y-1/2 rounded-full border px-1 py-0.5" id="scroll-left">
                <i class="ri-arrow-left-s-line text-lg"></i>
            </button>

            <div class="flex space-x-2 overflow-x-hidden scroll-smooth" id="scroll-container">
                <button class="sidebar-btn bg-neutral inline-flex items-center space-x-2 rounded-full border border-gray-300 px-3 py-1 text-sm font-medium" onclick="showPanel('data-panel', this)">
                    <span class="text-xl">🛰️</span>
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
                    <h2 class="text-lg font-bold">📡 My Data Imagery</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <!-- content -->
                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between space-x-1">
                            <x-button-primary class="px-2! py-1!" href="{{ route('admin.imagery.index') }}" size="small" variant="outline">
                                <i class="ri-dashboard-line"></i>
                                <span class="ml-1">Go to Dashboard</span>
                            </x-button-primary>
                            <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="window.AppMap.uploader.reload()">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
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
                                            10 MB • 2025-10-05 • <span class="imagery-status text-success font-semibold">done</span>
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
            <section class="flex hidden h-full flex-col shadow-xl" id="sentinel-panel" data-sentinel-token="{{ $copernicusAccessToken ?? '' }}" data-sentinel-credentials="{{ $copernicusCredentialsConfigured ?? false ? 'true' : 'false' }}">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🛰️ Sentinel-2 Collections</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-1 overflow-y-auto p-2">
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
                            <a class="bg-primary text-background hover:bg-primary/90 inline-flex hidden items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" data-sentinel-download href="#" aria-disabled="true" target="_blank" rel="noopener noreferrer">
                                <i class="ri-download-cloud-2-line"></i>
                                <span>Download Scene</span>
                            </a>
                            <button class="hover:bg-primary/10 text-primary border-primary/40 inline-flex items-center space-x-1 rounded-lg border px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50" data-sentinel-preview type="button">
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
                    <h2 class="text-lg font-bold">⬆️ Imagery Collection</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <!-- content -->
                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-3">
                    <div class="space-y-2">
                        @auth
                            <!-- Tab Navigation -->
                            <div class="flex">
                                <div class="bg-foreground/10 hover:bg-foreground/20 flex rounded-lg p-1 transition">
                                    <nav class="flex gap-x-1" role="tablist" aria-label="Tabs" aria-orientation="horizontal">
                                        <button class="hs-tab-active:bg-neutral hs-tab-active:text-foreground/80 text-foreground/50 hover:text-foreground/80 focus:outline-hidden focus:text-foreground/80 hover:hover:text-primary inline-flex items-center gap-x-2 rounded-lg bg-transparent px-2 py-1 text-sm font-medium disabled:pointer-events-none disabled:opacity-50" id="buy-tab" data-hs-tab="#buy-panel" type="button" role="tab" aria-selected="false" aria-controls="buy-panel">
                                            Buy Imagery
                                        </button>
                                        <button class="hs-tab-active:bg-neutral hs-tab-active:text-foreground/80 text-foreground/50 hover:text-foreground/80 focus:outline-hidden focus:text-foreground/80 hover:hover:text-primary active inline-flex items-center gap-x-2 rounded-lg bg-transparent px-2 py-1 text-sm font-medium disabled:pointer-events-none disabled:opacity-50" id="upload-tab" data-hs-tab="#upload-panel" type="button" role="tab" aria-selected="true" aria-controls="upload-panel">
                                            Upload Imagery
                                        </button>
                                    </nav>
                                </div>
                            </div>

                            <!-- Upload Tab Content -->
                            <div class="tab-content" id="upload-panel" role="tabpanel" aria-labelledby="upload-tab">
                                <div class="bg-primary/10 space-y-2 rounded-lg p-2">
                                    <h4 class="text-foreground flex items-center text-lg font-semibold">
                                        <i class="ri-upload-cloud-line text-primary mr-2"></i>
                                        Upload Your Own Imagery
                                    </h4>
                                    <p class="text-foreground/80 text-sm">
                                        Have your own satellite imagery? Upload it directly to our platform for advanced PRI analysis and crop health monitoring.
                                    </p>

                                    <div class="bg-primary/20 space-y-2 rounded-lg p-2">
                                        <h5 class="text-foreground font-medium">Supported Formats</h5>
                                        <ul class="text-foreground/70 grid grid-cols-2 gap-2 text-sm">
                                            <li class="flex items-center">
                                                <i class="ri-check-line text-success mr-2 text-xs"></i>
                                                <span>GeoTIFF (.tif, .tiff)</span>
                                            </li>
                                            <li class="flex items-center">
                                                <i class="ri-check-line text-success mr-2 text-xs"></i>
                                                <span>Enhanced Compressed Wavelet (.ecw)</span>
                                            </li>
                                            <li class="flex items-center">
                                                <i class="ri-check-line text-success mr-2 text-xs"></i>
                                                <span>ZIP Archives (.zip)</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="bg-primary/20 space-y-2 rounded-lg p-2">
                                        <h5 class="text-foreground font-medium">Compatible Sources</h5>
                                        <ul class="text-foreground/70 space-y-1 text-sm">
                                            <li class="flex items-start">
                                                <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                                <span>Sentinel-2, Landsat, and QuickSat</span>
                                            </li>
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
                            </div>

                            <!-- Buy Tab Content -->
                            <div class="tab-content hidden" class="hidden" id="buy-panel" role="tabpanel" aria-labelledby="buy-tab">
                                <div class="bg-primary/10 space-y-2 rounded-lg p-2">
                                    <h4 class="text-foreground flex items-center text-lg font-semibold">
                                        <i class="ri-shopping-bag-line text-primary mr-2"></i>
                                        Buy from Our Collection
                                    </h4>
                                    <p class="text-foreground/80 text-sm">
                                        Don't have satellite data? Purchase high-resolution imagery directly from our platform, captured by leading satellite constellations.
                                    </p>

                                    <div class="bg-primary/20 rounded-lg p-2">
                                        <h5 class="text-foreground mb-2 font-medium">What You Get</h5>
                                        <ul class="text-foreground/70 space-y-1 text-sm">
                                            <li class="flex items-start">
                                                <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                                <span>Access to daily updated satellite imagery</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                                <span>Global coverage</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="ri-check-line text-success mr-2 mt-0.5 text-xs"></i>
                                                <span>Automatic PRI analysis and health reports included</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="bg-primary/20 rounded-lg p-2">
                                        <h5 class="text-foreground mb-2 font-medium">Satellite Sources</h5>
                                        <div class="grid grid-cols-2 gap-2 text-sm">
                                            <div class="flex items-center">
                                                <i class="ri-satellite-line text-success mr-2"></i>
                                                <span>Sentinel-2</span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="ri-satellite-line text-success mr-2"></i>
                                                <span>Landsat 8/9</span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="ri-satellite-line text-success mr-2"></i>
                                                <span>Quicksat</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="">
                                        <x-button-primary id="buySatelliteBtn" type="button" size="small">
                                            <i class="ri-shopping-cart-line"></i>
                                            <span>Buy Satellite Imagery</span>
                                        </x-button-primary>
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
            <div class="shadow-soft bottom-22 pointer-events-none absolute left-0 z-40 mx-auto hidden max-w-xl rounded-lg bg-white/90 p-2 ring-1 ring-black/5 backdrop-blur supports-[backdrop-filter]:bg-white/60 sm:right-auto sm:w-[380px] md:left-20" id="panel">
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
                                <p class="text-foreground-70 mb-3 text-xs">Draw a polygon on the map to define your area of interest for satellite imagery analysis.</p>
                                <x-button-primary id="drawPolygonBtn" type="button" size="small">
                                    <i class="ri-pencil-line"></i>
                                    <span>Draw Polygon</span>
                                </x-button-primary>
                            </div>

                            <!-- GeoJSON Output -->
                            <div class="w-full">
                                <div class="border-muted bg-foreground/10 mt-3 max-h-11 w-full overflow-auto rounded border p-2 text-xs" id="drawerGeojson">
                                    <span class="text-foreground/50">Polygon coordinates will appear here...</span>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-300">

                        <!-- Feature Properties Form -->
                        <form class="space-y-4" id="featurePropertiesForm" action="{{ route('imageryOrder') }}" method="POST">
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
                                    <div class="border-muted bg-foreground/10 rounded border p-2 text-sm" id="measurementOutput">
                                        <div class="text-foreground/50 flex items-center">
                                            <i class="ri-crop-line mr-2"></i>
                                            <span>Calculate area...</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credit Points Information -->
                                <div class="space-y-2">
                                    <x-input-label class="text-sm font-medium" for="credit_info">Credit Points</x-input-label>
                                    <div class="border-muted rounded border p-2 text-sm">
                                        <div class="text-foreground/50 flex items-center">
                                            <i class="ri-coins-line mr-2"></i>
                                            <span>{{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), locale: app()->getLocale()) }} Credit Points per hectare</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Price -->
                            <div class="space-y-2">
                                <x-input-label class="text-sm font-medium" for="harga">Total Credit Points Needed</x-input-label>
                                <div class="border-muted bg-muted/60 rounded border p-2 text-sm" id="priceOutput">
                                    <span class="text-primary font-semibold" id="total_price">Total will be calculated...</span>
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

                        <!-- Additional Information -->
                        <div class="bg-primary/60 rounded-lg p-3">
                            <h4 class="text-primary-foreground mb-2 text-sm font-medium">What you'll get:</h4>
                            <ul class="text-primary-foreground/80 space-y-1 text-xs">
                                <li class="flex items-center">
                                    <i class="ri-check-line text-success mr-1"></i>
                                    High-resolution satellite imagery
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line text-success mr-1"></i>
                                    PRI stress analysis
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line text-success mr-1"></i>
                                    Detailed crop health reports
                                </li>
                                <li class="flex items-center">
                                    <i class="ri-check-line text-success mr-1"></i>
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

                // Connect resize handlers and modal toggles relevant to the dashboard.
                const registerEventListeners = () => {
                    window.addEventListener('resize', syncPanelLayoutWithViewport);

                    document.getElementById('buySatelliteBtn')?.addEventListener('click', () => {
                        document.getElementById('buyingPanel')?.classList.remove('hidden');
                    });

                    document.getElementById('buyingPanelCloseBtn')?.addEventListener('click', () => {
                        document.getElementById('buyingPanel')?.classList.add('hidden');
                    });
                };

                const initialisePriceCalculator = () => {
                    // Convert area from square meters to hectares.
                    const toHectares = (squareMeters) => squareMeters / 10000;
                    // Provide a subtle animation whenever the price updates.
                    const highlightContainer = (container) => {
                        container.style.transform = 'scale(1.02)';
                        setTimeout(() => {
                            container.style.transform = 'scale(1)';
                        }, 200);
                    };

                    // Compute total credit points needed and update the display widget.
                    const calculateTotalPrice = () => {
                        const areaInSquareMeters = window.geojsonArea || 0;
                        const areaInHectares = toHectares(areaInSquareMeters);
                        const totalPriceElement = document.getElementById('total_price');

                        if (!totalPriceElement) {
                            return;
                        }

                        const priceContainer = totalPriceElement.parentElement;
                        const creditPointsNeeded = areaInHectares * {{ config('app-constants.imagery_credit_cost_per_hectare') }};

                        if (areaInHectares > 0) {
                            totalPriceElement.innerHTML = `
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-green-700">${formatNumber(creditPointsNeeded.toFixed(2), 2)} Credit Points</span>
                                <i class="ri-coins-line font-base text-success text-xl"></i>
                            </div>
                            <div class="text-xs text-foreground-70 mt-1">
                                ${formatNumber(areaInHectares)} hectares × {{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), locale: app()->getLocale()) }} credit points/hectare
                            </div>
                        `;
                            priceContainer.classList.remove('bg-muted/60', 'border-muted', 'bg-amber-50', 'border-amber-300');
                            priceContainer.classList.add('bg-green-50', 'border-green-300', 'shadow-sm');
                            highlightContainer(priceContainer);
                        } else {
                            totalPriceElement.innerHTML = `
                            <div class="flex items-center text-foreground/50">
                                <i class="ri-information-line mr-2"></i>
                                Draw an area to calculate credit points
                            </div>
                        `;
                            priceContainer.classList.remove('bg-green-50', 'border-green-300', 'shadow-sm');
                            priceContainer.classList.add('bg-muted/60', 'border-muted');
                        }

                        priceContainer.style.transition = 'all 0.3s ease-in-out';
                    };

                    window.calculateTotalPrice = calculateTotalPrice;
                };

                // Bootstrap the panel experience once the DOM has finished loading.
                document.addEventListener('DOMContentLoaded', () => {
                    window.showPanel = showPanel;
                    window.closePanels = closePanels;

                    initialiseHorizontalScroll();
                    initialiseDefaultPanel();
                    registerEventListeners();
                    initialisePriceCalculator();
                    syncPanelLayoutWithViewport();

                    const sentinelModule = window.AppMap?.sentinel;
                    if (sentinelModule?.loadCollections && !sentinelModule.loadedOnce) {
                        sentinelModule.loadCollections();
                    }
                });
            })();
        </script>
    @endpush
    @push('javascript')
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
                        case 'done':
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
                const generateUploadId = () => Math.random().toString(36).substring(2, 12);

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
                        elements.progressText.textContent = '🧩 Merging file on server...';
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
                            MyZkToast.info('Merging file on server...');
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
                            throw new Error(result.message || 'Failed to merge file on server.');
                        }

                        elements.progressBar.style.width = '100%';
                        elements.progressText.textContent = `✅ Upload complete! ${result.message || 'Upload completed. Processing started in background.'}`;
                        MyZkToast.success(result.message || 'Upload completed successfully!');
                        setButtonState('done');
                        $('#current-myCredits').text(formatNumber(result.data.currentCredits, 2));
                        await loadMyData();
                        scheduleAutoReset();
                    } catch (error) {
                        elements.progressText.textContent = `❌ Error: ${error.message}`;
                        MyZkToast.error(error.message || 'Server error during merge.');
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

                // Function to check user credits
                const checkUserCredits = async () => {
                    const response = await fetch('{{ route('user.credits.check') }}');
                    const result = await response.json();

                    if (!result.success) {
                        MyZkToast.error(result.message || 'Failed to check credit balance.');
                        return false;
                    }

                    const currentCredits = parseFloat(formatNumber(result.credits, 2));
                    const requiredCredits = config.imageryProcessingCost || 10; // Default to 10 if not set

                    return new Promise((resolve) => {
                        resolve(data = {
                            hasCredits: currentCredits >= requiredCredits,
                            currentCredits: currentCredits,
                            requiredCredits: requiredCredits
                        });
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

                    const formatLabel = item.format.slice(0, 3).toUpperCase();
                    card.querySelector('.imagery-format').textContent = formatLabel;
                    card.querySelector('.imagery-name').textContent = shortenFilename(item.original_name, 25);

                    const sizeMb = (item.size / 1024 / 1024).toFixed(2);
                    const uploadDate = new Date(item.uploaded_at).toLocaleDateString();
                    const statusEl = card.querySelector('.imagery-status');
                    statusEl.textContent = item.processing_status;
                    statusEl.classList.toggle('text-success', item.processing_status === 'done');
                    statusEl.classList.toggle('text-warning', item.processing_status !== 'done');

                    const meta = `${sizeMb} MB • ${uploadDate} • <span class="${statusEl.className}">${statusEl.textContent}</span>`;
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
            })();
        </script>
    @endpush
    @push('javascript')
        <script>
            (() => {
                const statusEl = document.getElementById('sentinelCollectionStatus');
                const listEl = document.getElementById('sentinelCollectionList');
                const templateEl = document.getElementById('sentinelCollectionTemplate');
                const lastUpdatedEl = document.getElementById('sentinelLastUpdated');
                const formEl = document.getElementById('sentinelFilterForm');
                const resetButtonEl = document.getElementById('sentinelFilterResetButton');
                const cloudInputEl = document.getElementById('sentinelCloudFilter');
                const startDateInputEl = document.getElementById('sentinelStartDate');
                const endDateInputEl = document.getElementById('sentinelEndDate');
                const latInputEl = document.getElementById('sentinelLatFilter');
                const lonInputEl = document.getElementById('sentinelLonFilter');
                const levelInputEl = document.getElementById('sentinelProductLevel');
                const panelEl = document.getElementById('sentinel-panel');

                const previewPanelEl = document.getElementById('sentinelPreviewPanel');
                const previewElements = {
                    panel: previewPanelEl,
                    title: previewPanelEl?.querySelector('[data-sentinel-preview-title]') ?? null,
                    acquired: previewPanelEl?.querySelector('[data-sentinel-preview-acquired]') ?? null,
                    details: previewPanelEl?.querySelector('[data-sentinel-preview-details]') ?? null,
                    status: previewPanelEl?.querySelector('[data-sentinel-preview-status]') ?? null,
                    clearButton: document.getElementById('sentinelPreviewClearBtn'),
                    downloadButton: document.getElementById('sentinelPreviewDownloadBtn')
                };

                if (!statusEl || !listEl || !templateEl) {
                    return;
                }

                const config = {
                    endpoint: 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json',
                    defaultMonthsBack: 1,
                    defaultCloudCover: 40,
                    defaultLatitude: -1.24536,
                    defaultLongitude: 114.54535,
                    defaultProductType: 'S2MSI2A'
                };

                const state = {
                    loadedOnce: false,
                    defaultStartIso: '',
                    defaultEndIso: '',
                    token: sanitizeToken(panelEl?.dataset?.sentinelToken ?? ''),
                    tokenConfigured: (panelEl?.dataset?.sentinelCredentials ?? '').toLowerCase() === 'true'
                };

                const ignoredDownloadKeywords = ['quicklook', 'thumbnail', 'thumb', 'overview', 'browse', 'preview', 'allorigins'];

                // Ensure sentinel helpers are available under the shared AppMap namespace.
                const ensureAppNamespace = () => {
                    window.AppMap = window.AppMap || {};
                    window.AppMap.sentinel = window.AppMap.sentinel || {};
                };

                // Safely convert incoming values into Date objects when possible.
                const parseDate = (value) => {
                    if (typeof window.parseDateInput === 'function') {
                        return window.parseDateInput(value);
                    }
                    if (value instanceof Date) {
                        return new Date(value);
                    }
                    if (typeof value === 'string' && value.trim()) {
                        const parsed = new Date(value);
                        return Number.isNaN(parsed.getTime()) ? null : parsed;
                    }
                    return null;
                };

                // Determine the default date window used for catalogue searches.
                const getDefaultDateRange = (monthsBack = config.defaultMonthsBack) => {
                    const months = Number.isFinite(monthsBack) && monthsBack > 0 ? monthsBack : config.defaultMonthsBack;
                    const end = new Date();
                    end.setHours(23, 59, 59, 999);
                    const start = new Date(end.getTime());
                    start.setMonth(start.getMonth() - months);
                    start.setHours(0, 0, 0, 0);
                    return {
                        start,
                        end
                    };
                };

                // Normalize a Date into a YYYY-MM-DD formatted string.
                const ensureIsoDateString = (date) => {
                    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                        return '';
                    }
                    if (typeof window.formatISODate === 'function') {
                        return window.formatISODate(date);
                    }
                    return date.toISOString().split('T')[0];
                };

                // Keep start and end date fields within mutually valid ranges.
                const syncDateConstraints = () => {
                    if (!startDateInputEl || !endDateInputEl) return;
                    endDateInputEl.min = startDateInputEl.value || '';
                    startDateInputEl.max = endDateInputEl.value || '';
                };

                // Populate filters with default dates when empty or forced.
                const applyDefaultDates = (force = false) => {
                    const range = getDefaultDateRange(config.defaultMonthsBack);
                    const start = parseDate(range.start) ?? new Date();
                    start.setHours(0, 0, 0, 0);
                    const end = parseDate(range.end) ?? new Date();
                    end.setHours(23, 59, 59, 999);

                    state.defaultStartIso = ensureIsoDateString(start);
                    state.defaultEndIso = ensureIsoDateString(end);

                    if (startDateInputEl && (force || !startDateInputEl.value)) {
                        startDateInputEl.value = state.defaultStartIso;
                    }
                    if (endDateInputEl && (force || !endDateInputEl.value)) {
                        endDateInputEl.value = state.defaultEndIso;
                    }

                    syncDateConstraints();
                };

                // Retry until the global formatter exists before running the callback.
                const waitForFormatIso = (callback, attempts = 10, delayMs = 50) => {
                    if (typeof window.formatISODate === 'function' || attempts <= 0) {
                        callback();
                        return;
                    }
                    setTimeout(() => waitForFormatIso(callback, attempts - 1, delayMs), delayMs);
                };

                // Present cloud cover percentages with graceful fallbacks.
                const formatCloudCover = (value) => {
                    if (typeof value === 'number' && !Number.isNaN(value)) {
                        return `${value.toFixed(1)}%`;
                    }
                    return 'N/A';
                };

                // Clamp numeric inputs and return null when value is invalid.
                const clampNumber = (value, min, max) => {
                    if (typeof value !== 'number' || Number.isNaN(value)) return null;
                    return Math.min(Math.max(value, min), max);
                };

                function sanitizeToken(value) {
                    return typeof value === 'string' ? value.trim() : '';
                }

                const hasToken = () => sanitizeToken(state.token).length > 0;

                // Craft a status message that explains token requirements when missing.
                const buildStatusMessage = (message) => {
                    const parts = [];
                    if (message) parts.push(message);
                    if (!hasToken()) {
                        parts.push(state.tokenConfigured ?
                            'Download unavailable: Copernicus access token could not be issued.' :
                            'Configure Copernicus credentials on the server to enable downloads.');
                    }
                    return parts.join(' ');
                };

                // Add the Copernicus token query parameter to download URLs when possible.
                const applyTokenToUrl = (url) => {
                    if (!url) return null;
                    const token = sanitizeToken(state.token);
                    if (!token) return null;
                    const queryPattern = /([?&])token=[^&#]*/i;
                    if (queryPattern.test(url)) {
                        return url.replace(queryPattern, `$1token=${encodeURIComponent(token)}`);
                    }
                    const separator = url.includes('?') ? '&' : '?';
                    return `${url}${separator}token=${encodeURIComponent(token)}`;
                };

                // Validate that a URL appears to be a downloadable resource link.
                const isValidDownloadUrl = (url) => {
                    if (typeof url !== 'string') return false;
                    const trimmed = url.trim();
                    if (!trimmed) return false;
                    if (!/^https?:\/\//i.test(trimmed)) return false;
                    const lowered = trimmed.toLowerCase();
                    return !ignoredDownloadKeywords.some((keyword) => lowered.includes(keyword));
                };

                // Recursively gather URLs from the various service response formats.
                const extractServiceUrls = (service) => {
                    const results = [];
                    if (!service) return results;
                    if (typeof service === 'string') {
                        results.push(service);
                        return results;
                    }
                    if (Array.isArray(service)) {
                        service.forEach((item) => {
                            results.push(...extractServiceUrls(item));
                        });
                        return results;
                    }
                    if (typeof service === 'object') {
                        ['url', 'href', 'https', 'http'].forEach((key) => {
                            const value = service[key];
                            if (typeof value === 'string') {
                                results.push(value);
                            }
                        });
                    }
                    return results;
                };

                // Determine the best download URL candidate from a feature payload.
                const resolveDownloadUrl = (feature) => {
                    if (!feature) return null;

                    const props = feature.properties ?? {};
                    const links = Array.isArray(feature.links) ? feature.links : [];
                    const assets = feature.assets ?? {};

                    const candidateScores = new Map();
                    const registerCandidate = (url, score = 0) => {
                        if (!isValidDownloadUrl(url)) return;
                        const existing = candidateScores.get(url);
                        if (existing === undefined || score > existing) {
                            candidateScores.set(url, score);
                        }
                    };

                    const registerFromService = (service, score = 0) => {
                        extractServiceUrls(service).forEach((url) => registerCandidate(url, score));
                    };

                    const linkScoreByRel = {
                        enclosure: 100,
                        download: 95,
                        data: 90,
                        alternate: 75,
                        source: 70,
                        self: 65
                    };

                    links.forEach((link) => {
                        const href = typeof link?.href === 'string' ? link.href : null;
                        if (!href) return;
                        const rel = typeof link?.rel === 'string' ? link.rel.toLowerCase() : '';
                        const type = typeof link?.type === 'string' ? link.type.toLowerCase() : '';
                        let score = linkScoreByRel[rel] ?? 60;
                        if (type.includes('zip') || type.includes('safe') || type.includes('application/octet-stream')) {
                            score += 15;
                        }
                        registerCandidate(href, score);
                    });

                    ['downloadUrl', 'productDownloadUrl', 'productUrl', 'dataUrl', 'url', 'servicesDownloadUrl', 'resourceUrl'].forEach((key) => {
                        const value = props[key];
                        if (typeof value === 'string') {
                            registerCandidate(value, 92);
                        }
                    });

                    const services = props.services;
                    if (services && typeof services === 'object' && !Array.isArray(services)) {
                        Object.entries(services).forEach(([key, serviceValue]) => {
                            const lowerKey = String(key).toLowerCase();
                            let score = 70;
                            if (lowerKey.includes('download')) score = 95;
                            else if (lowerKey.includes('data')) score = 88;
                            else if (lowerKey.includes('s3') || lowerKey.includes('aws')) score = 82;
                            registerFromService(serviceValue, score);
                        });
                    } else {
                        registerFromService(services, 80);
                    }

                    Object.entries(assets).forEach(([key, assetValue]) => {
                        const lowerKey = String(key).toLowerCase();
                        if (ignoredDownloadKeywords.some((keyword) => lowerKey.includes(keyword))) {
                            return;
                        }
                        const href = typeof assetValue === 'string' ?
                            assetValue :
                            (typeof assetValue?.href === 'string' ?
                                assetValue.href :
                                (typeof assetValue?.url === 'string' ? assetValue.url : null));
                        if (!href) return;
                        let score = 68;
                        if (Array.isArray(assetValue?.roles)) {
                            const roleScore = assetValue.roles.some((role) => ['data', 'download', 'product', 'analytic'].includes(String(role).toLowerCase()));
                            if (roleScore) score += 20;
                        }
                        const type = typeof assetValue?.type === 'string' ? assetValue.type.toLowerCase() : '';
                        if (type.includes('zip') || type.includes('safe') || type.includes('geotiff') || type.includes('jp2')) {
                            score += 12;
                        }
                        if (/(data|product|tile|granule|image|scene)/.test(lowerKey)) {
                            score += 6;
                        }
                        registerCandidate(href, score);
                    });

                    const propsAssets = props.assets;
                    if (propsAssets && typeof propsAssets === 'object' && !Array.isArray(propsAssets)) {
                        Object.entries(propsAssets).forEach(([key, assetValue]) => {
                            const lowerKey = String(key).toLowerCase();
                            if (ignoredDownloadKeywords.some((keyword) => lowerKey.includes(keyword))) {
                                return;
                            }
                            if (typeof assetValue === 'string') {
                                registerCandidate(assetValue, 66);
                            } else if (assetValue && typeof assetValue === 'object') {
                                if (typeof assetValue.href === 'string') {
                                    registerCandidate(assetValue.href, 66);
                                }
                                if (typeof assetValue.url === 'string') {
                                    registerCandidate(assetValue.url, 66);
                                }
                            }
                        });
                    }

                    let bestUrl = null;
                    let bestScore = -Infinity;
                    candidateScores.forEach((score, url) => {
                        if (score > bestScore) {
                            bestScore = score;
                            bestUrl = url;
                        }
                    });

                    return bestUrl ?? null;
                };

                // Generate a filesystem-friendly filename for Sentinel downloads.
                const buildDownloadName = (primary, fallback) => {
                    const base = String(primary ?? fallback ?? 'sentinel-2-scene').trim();
                    const normalized = base.replace(/[\s]+/g, '_').replace(/[^A-Za-z0-9._-]+/g, '_');
                    return normalized || 'sentinel-2-scene';
                };

                // Encapsulate preview map rendering, metadata display, and downloads.
                const createPreviewModule = () => {
                    const defaultContent = {
                        title: previewElements.title?.textContent ?? '–',
                        acquired: previewElements.acquired?.textContent ?? '',
                        details: previewElements.details?.textContent ?? '',
                        status: previewElements.status?.textContent ?? ''
                    };

                    const dataProjection = 'EPSG:4326';
                    const wmsDefaults = {
                        baseUrl: 'https://sh.dataspace.copernicus.eu/ogc/wms/1bd0fec1-0e52-427a-8e83-6e0dcd29a03a',
                        layerName: 'NATURAL-COLOR',
                        baseParams: {
                            FORMAT: 'image/png',
                            TRANSPARENT: true,
                            SHOWLOGO: false,
                            VERSION: '1.3.0'
                        },
                        attribution: 'Sentinel Hub / Copernicus Data Space Ecosystem'
                    };

                    const MAX_METERS_PER_PIXEL = 200;
                    const MAX_WMS_DIMENSION = 4096;
                    const MAX_WMS_PIXELS = MAX_WMS_DIMENSION * MAX_WMS_DIMENSION;
                    const IMAGE_LOAD_DEBOUNCE_MS = 300;

                    const buildWmsParams = (overrides = {}) => ({
                        ...wmsDefaults.baseParams,
                        ...overrides
                    });

                    const getDataProjectionOrientation = () => {
                        if (typeof ol?.proj?.get !== 'function') return 'ne';
                        try {
                            return ol.proj.get(dataProjection)?.getAxisOrientation?.() ?? 'ne';
                        } catch (error) {
                            console.warn('Failed to read projection axis orientation', error);
                            return 'ne';
                        }
                    };

                    const reorderExtentForAxisOrientation = (extent, orientation) => {
                        if (!Array.isArray(extent) || extent.length !== 4) return extent;
                        if (typeof orientation !== 'string' || orientation.length < 2) return extent;
                        const firstAxis = orientation[0]?.toLowerCase?.();
                        const secondAxis = orientation[1]?.toLowerCase?.();
                        if (firstAxis === 'n' && secondAxis === 'e') {
                            return [extent[1], extent[0], extent[3], extent[2]];
                        }
                        return extent;
                    };

                    const restoreExtentFromAxisOrientation = (extent, orientation) => {
                        if (!Array.isArray(extent) || extent.length !== 4) return extent;
                        if (typeof orientation !== 'string' || orientation.length < 2) return extent;
                        const firstAxis = orientation[0]?.toLowerCase?.();
                        const secondAxis = orientation[1]?.toLowerCase?.();
                        if (firstAxis === 'n' && secondAxis === 'e') {
                            return [extent[1], extent[0], extent[3], extent[2]];
                        }
                        return extent;
                    };

                    const enforceMetersPerPixelLimit = (width, height, url, orientation) => {
                        if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
                            return [width, height];
                        }
                        if (!Number.isFinite(MAX_METERS_PER_PIXEL) || MAX_METERS_PER_PIXEL <= 0) {
                            return [width, height];
                        }
                        if (typeof ol?.proj?.transformExtent !== 'function') {
                            return [width, height];
                        }
                        const bboxParam = url.searchParams.get('BBOX');
                        if (typeof bboxParam !== 'string' || bboxParam.trim().length === 0) {
                            return [width, height];
                        }
                        const values = bboxParam.split(',').map((value) => Number(value));
                        if (values.length !== 4 || values.some((value) => !Number.isFinite(value))) {
                            return [width, height];
                        }
                        try {
                            const lonLatExtent = restoreExtentFromAxisOrientation(values, orientation);
                            const metricExtent = ol.proj.transformExtent(lonLatExtent, dataProjection, 'EPSG:3857');
                            if (!Array.isArray(metricExtent) || metricExtent.length !== 4) {
                                return [width, height];
                            }
                            const widthMeters = Math.abs(metricExtent[2] - metricExtent[0]);
                            const heightMeters = Math.abs(metricExtent[3] - metricExtent[1]);
                            if (widthMeters <= 0 && heightMeters <= 0) {
                                return [width, height];
                            }
                            const widthFactor = widthMeters > 0
                                ? widthMeters / (MAX_METERS_PER_PIXEL * width)
                                : 0;
                            const heightFactor = heightMeters > 0
                                ? heightMeters / (MAX_METERS_PER_PIXEL * height)
                                : 0;
                            const scale = Math.max(1, widthFactor, heightFactor);
                            if (scale <= 1) {
                                return [width, height];
                            }
                            const nextWidth = Math.max(width, Math.ceil(width * scale));
                            const nextHeight = Math.max(height, Math.ceil(height * scale));
                            return [nextWidth, nextHeight];
                        } catch (error) {
                            console.warn('Failed to enforce WMS meters-per-pixel limit', error);
                            return [width, height];
                        }
                    };

                    const normalizeWmsUrl = (src, context) => {
                        if (typeof src !== 'string' || !src) return src;
                        try {
                            const url = new URL(src, window.location.href);
                            const projection = context?.projection;
                            const orientation = getDataProjectionOrientation();
                            const projectionCode = typeof projection?.getCode === 'function'
                                ? projection.getCode()
                                : (typeof projection === 'string' ? projection : null);
                            const bboxParam = url.searchParams.get('BBOX');

                            if (bboxParam && projectionCode && projectionCode !== dataProjection && typeof ol?.proj?.transformExtent === 'function') {
                                const values = bboxParam.split(',').map((value) => Number(value));
                                if (values.length === 4 && values.every((value) => Number.isFinite(value))) {
                                    try {
                                        const transformed = ol.proj.transformExtent(values, projectionCode, dataProjection);
                                        if (Array.isArray(transformed)) {
                                            const oriented = reorderExtentForAxisOrientation(transformed, orientation);
                                            const formatted = oriented.map((value) => value.toFixed(8));
                                            url.searchParams.set('BBOX', formatted.join(','));
                                        }
                                    } catch (error) {
                                        console.warn('Failed to transform WMS BBOX to EPSG:4326', error);
                                    }
                                }
                            }

                            url.searchParams.set('CRS', dataProjection);
                            url.searchParams.set('SRS', dataProjection);
                            if (!url.searchParams.has('VERSION') && wmsDefaults.baseParams?.VERSION) {
                                url.searchParams.set('VERSION', wmsDefaults.baseParams.VERSION);
                            }

                            const width = Number(url.searchParams.get('WIDTH'));
                            const height = Number(url.searchParams.get('HEIGHT'));
                            if (Number.isFinite(width) && Number.isFinite(height) && width > 0 && height > 0) {
                                let adjustedWidth = width;
                                let adjustedHeight = height;

                                [adjustedWidth, adjustedHeight] = enforceMetersPerPixelLimit(
                                    adjustedWidth,
                                    adjustedHeight,
                                    url,
                                    orientation
                                );

                                if (Number.isFinite(MAX_WMS_DIMENSION) && MAX_WMS_DIMENSION > 0) {
                                    const largestDimension = Math.max(adjustedWidth, adjustedHeight);
                                    if (largestDimension > MAX_WMS_DIMENSION) {
                                        const scale = MAX_WMS_DIMENSION / largestDimension;
                                        adjustedWidth = Math.max(1, Math.floor(adjustedWidth * scale));
                                        adjustedHeight = Math.max(1, Math.floor(adjustedHeight * scale));
                                    }
                                }

                                const limitPixels = (w, h) => {
                                    if (!Number.isFinite(MAX_WMS_PIXELS) || MAX_WMS_PIXELS <= 0) {
                                        return [w, h];
                                    }
                                    const currentPixels = w * h;
                                    if (currentPixels <= MAX_WMS_PIXELS) {
                                        return [w, h];
                                    }
                                    const scale = Math.sqrt(MAX_WMS_PIXELS / currentPixels);
                                    let nextWidth = Math.max(1, Math.floor(w * scale));
                                    let nextHeight = Math.max(1, Math.floor(h * scale));
                                    while (nextWidth * nextHeight > MAX_WMS_PIXELS && (nextWidth > 1 || nextHeight > 1)) {
                                        if (nextWidth >= nextHeight && nextWidth > 1) {
                                            nextWidth -= 1;
                                        } else if (nextHeight > 1) {
                                            nextHeight -= 1;
                                        } else {
                                            break;
                                        }
                                    }
                                    return [nextWidth, nextHeight];
                                };

                                [adjustedWidth, adjustedHeight] = limitPixels(adjustedWidth, adjustedHeight);

                                if (adjustedWidth !== width) {
                                    url.searchParams.set('WIDTH', String(adjustedWidth));
                                }
                                if (adjustedHeight !== height) {
                                    url.searchParams.set('HEIGHT', String(adjustedHeight));
                                }
                            }

                            return url.toString();
                        } catch (error) {
                            console.warn('Unable to normalize WMS request URL', error);
                            return src;
                        }
                    };

                    const createImageLoadFunction = (context) => {
                        const fallbackLoader = typeof ol?.source?.Image?.defaultImageLoadFunction === 'function'
                            ? ol.source.Image.defaultImageLoadFunction
                            : ((image, source) => {
                                if (image?.getImage) {
                                    image.getImage().src = source;
                                }
                            });

                        const scheduleLoad = (imageInstance, source) => {
                            const normalizedSrc = normalizeWmsUrl(source, context);
                            fallbackLoader(imageInstance, normalizedSrc);
                        };

                        return (image, src) => {
                            const delay = Number.isFinite(context?.imageLoadDelay)
                                ? context.imageLoadDelay
                                : IMAGE_LOAD_DEBOUNCE_MS;

                            if (!delay || delay <= 0) {
                                scheduleLoad(image, src);
                                return;
                            }

                            if (context.imageLoadTimer) {
                                clearTimeout(context.imageLoadTimer);
                                context.imageLoadTimer = null;
                            }

                            context.imageLoadPending = { image, src };
                            context.imageLoadTimer = setTimeout(() => {
                                const pending = context.imageLoadPending;
                                context.imageLoadTimer = null;
                                context.imageLoadPending = null;
                                if (!pending) return;
                                scheduleLoad(pending.image, pending.src);
                            }, delay);
                        };
                    };

                    const localState = {
                        map: null,
                        projection: null,
                        layer: null,
                        source: null,
                        geoJson: null,
                        selection: null,
                        imageryLayer: null,
                        imagerySource: null,
                        imageLoadTimer: null,
                        imageLoadPending: null,
                        imageLoadDelay: IMAGE_LOAD_DEBOUNCE_MS
                    };

                    const cancelPendingImageryLoad = () => {
                        if (localState.imageLoadTimer) {
                            clearTimeout(localState.imageLoadTimer);
                            localState.imageLoadTimer = null;
                        }
                        localState.imageLoadPending = null;
                    };

                    const imageLoadFunction = createImageLoadFunction(localState);

                    const createImageSource = (url, params) => new ol.source.ImageWMS({
                        url,
                        params,
                        ratio: 1,
                        crossOrigin: 'anonymous',
                        attributions: wmsDefaults.attribution,
                        imageLoadFunction
                    });

                    // Lazily initialize OpenLayers resources used to draw footprints.
                    const ensureContext = () => {
                        if (typeof window === 'undefined' || typeof ol === 'undefined') return null;
                        const mapInstance = window.map;
                        const projection = mapInstance?.getView?.()?.getProjection?.();
                        if (!mapInstance || !projection) return null;

                        localState.map = mapInstance;
                        localState.projection = projection;

                        if (!localState.imageryLayer) {
                            const imageLayerSupported = Boolean(ol?.source?.ImageWMS) && typeof ol?.layer?.Image === 'function';
                            if (imageLayerSupported) {
                                const params = buildWmsParams({
                                    LAYERS: wmsDefaults.layerName
                                });
                                localState.imagerySource = createImageSource(wmsDefaults.baseUrl, params);
                                localState.imageryLayer = new ol.layer.Image({
                                    source: localState.imagerySource,
                                    visible: false,
                                    opacity: 0.95,
                                    zIndex: 100
                                });
                                mapInstance.addLayer(localState.imageryLayer);
                            }
                        }

                        if (!localState.layer) {
                            localState.source = new ol.source.Vector();
                            localState.layer = new ol.layer.Vector({
                                source: localState.source,
                                style: new ol.style.Style({
                                    stroke: new ol.style.Stroke({
                                        color: 'rgba(59,130,246,0.9)',
                                        width: 2,
                                        lineDash: [6, 6]
                                    }),
                                    fill: new ol.style.Fill({
                                        color: 'rgba(59,130,246,0.05)'
                                    })
                                }),
                                visible: false,
                                zIndex: 100
                            });
                            mapInstance.addLayer(localState.layer);
                        }

                        localState.geoJson = localState.geoJson ?? new ol.format.GeoJSON();
                        return localState;
                    };

                    const ensureAbsoluteUrl = (value) => {
                        if (typeof value !== 'string') return '';
                        const trimmed = value.trim();
                        if (!trimmed || !/^https?:\/\//i.test(trimmed)) return '';
                        return trimmed;
                    };

                    const toLayerArray = (value) => {
                        if (Array.isArray(value)) {
                            return value
                                .map((item) => (typeof item === 'string' ? item.trim() : ''))
                                .filter((item) => item.length > 0);
                        }
                        if (typeof value === 'string') {
                            const trimmed = value.trim();
                            return trimmed ? [trimmed] : [];
                        }
                        return [];
                    };

                    const resolveWmsTimeParam = (payload) => {
                        const candidates = [
                            payload?.acquisitionDate,
                            payload?.featureProperties?.acquisitionDate,
                            payload?.featureProperties?.completionDate,
                            payload?.featureProperties?.beginPosition,
                            payload?.featureProperties?.endPosition,
                            payload?.featureProperties?.startDate,
                            payload?.featureProperties?.endDate,
                            payload?.featureProperties?.startTimeFromAscendingNode,
                            payload?.featureProperties?.datatakeTime
                        ];
                        for (const candidate of candidates) {
                            const parsed = parseDate(candidate);
                            if (parsed instanceof Date && !Number.isNaN(parsed.getTime())) {
                                const iso = parsed.toISOString();
                                return iso.includes('.') ? `${iso.split('.')[0]}Z` : iso;
                            }
                        }
                        return null;
                    };

                    const inspectServiceEntry = (entry, accumulator) => {
                        if (!entry) return;
                        if (Array.isArray(entry)) {
                            entry.forEach((value) => inspectServiceEntry(value, accumulator));
                            return;
                        }
                        if (typeof entry === 'string') {
                            const url = ensureAbsoluteUrl(entry);
                            if (url && /wms|ogc/i.test(entry)) {
                                accumulator.urls.push(url);
                            }
                            return;
                        }
                        if (typeof entry !== 'object') return;

                        const urlKeys = ['wms', 'ogc', 'ogcWms', 'ogcUrl', 'wmsUrl', 'url', 'href', 'endpoint'];
                        urlKeys.forEach((key) => {
                            const value = entry[key];
                            if (typeof value === 'string') {
                                const url = ensureAbsoluteUrl(value);
                                if (url && (/wms/i.test(key) || /ogc/i.test(key) || /wms|ogc/i.test(url))) {
                                    accumulator.urls.push(url);
                                }
                            } else if (value && typeof value === 'object') {
                                inspectServiceEntry(value, accumulator);
                            }
                        });

                        const layerKeys = ['layer', 'layers', 'layerId', 'layerIds', 'layerName', 'defaultLayer'];
                        layerKeys.forEach((key) => {
                            const value = entry[key];
                            toLayerArray(value).forEach((layer) => accumulator.layers.push(layer));
                        });

                        Object.entries(entry).forEach(([, value]) => {
                            if (value && typeof value === 'object' && !Array.isArray(value)) {
                                inspectServiceEntry(value, accumulator);
                            }
                        });
                    };

                    const resolveImageryOptions = (payload) => {
                        const accumulator = {
                            urls: [],
                            layers: []
                        };
                        if (payload?.services && typeof payload.services === 'object') {
                            Object.values(payload.services).forEach((service) => {
                                inspectServiceEntry(service, accumulator);
                            });
                        }
                        if (Array.isArray(payload?.links)) {
                            payload.links.forEach((link) => {
                                const rel = String(link?.rel ?? '');
                                const type = String(link?.type ?? '');
                                const title = String(link?.title ?? '');
                                if (/wms|ogc/i.test(rel) || /wms|ogc/i.test(type) || /wms|ogc/i.test(title)) {
                                    const url = ensureAbsoluteUrl(link?.href);
                                    if (url) {
                                        accumulator.urls.push(url);
                                    }
                                }
                            });
                        }
                        if (payload?.assets && typeof payload.assets === 'object') {
                            Object.values(payload.assets).forEach((asset) => {
                                if (typeof asset === 'string') {
                                    const url = ensureAbsoluteUrl(asset);
                                    if (url && /wms|ogc/i.test(url)) {
                                        accumulator.urls.push(url);
                                    }
                                    return;
                                }
                                if (asset && typeof asset === 'object') {
                                    ['href', 'url', 'wms', 'ogc'].forEach((key) => {
                                        const value = asset[key];
                                        if (typeof value === 'string') {
                                            const url = ensureAbsoluteUrl(value);
                                            if (url && (/wms/i.test(key) || /ogc/i.test(key) || /wms|ogc/i.test(url))) {
                                                accumulator.urls.push(url);
                                            }
                                        }
                                    });
                                    ['layer', 'layers', 'layerId', 'layerIds'].forEach((key) => {
                                        const value = asset[key];
                                        toLayerArray(value).forEach((layer) => accumulator.layers.push(layer));
                                    });
                                }
                            });
                        }

                        const uniqueUrls = Array.from(new Set(accumulator.urls));
                        const fallbackUrl = wmsDefaults.baseUrl;
                        const resolvedUrl = uniqueUrls.find((url) => /\/wms\//i.test(url)) || uniqueUrls[0] || fallbackUrl;
                        const resolvedLayers = [wmsDefaults.layerName];
                        const time = resolveWmsTimeParam(payload);
                        return {
                            url: resolvedUrl,
                            layers: resolvedLayers,
                            time
                        };
                    };

                    const buildLayerExtent = (payload, geometryExtent) => {
                        if (geometryExtent && Array.isArray(geometryExtent) && geometryExtent.length === 4) {
                            return geometryExtent;
                        }
                        if (!Array.isArray(payload?.bbox) || payload.bbox.length !== 4) {
                            return null;
                        }
                        if (!localState.projection || !ol?.proj?.transformExtent) {
                            return null;
                        }
                        try {
                            return ol.proj.transformExtent(payload.bbox, dataProjection, localState.projection);
                        } catch (error) {
                            console.warn('Unable to transform bbox extent for imagery preview.', error);
                            return null;
                        }
                    };

                    const applyImageryPreview = (payload, geometryExtent) => {
                        if (!localState.imageryLayer || !ol?.source?.ImageWMS) {
                            return false;
                        }
                        const options = resolveImageryOptions(payload);
                        const url = ensureAbsoluteUrl(options?.url) || wmsDefaults.baseUrl;
                        const layerName = wmsDefaults.layerName;

                        if (!url || !layerName) {
                            localState.imageryLayer.setVisible(false);
                            return false;
                        }

                        const params = buildWmsParams({
                            LAYERS: layerName
                        });

                        if (options?.time) {
                            params.TIME = options.time;
                        }

                        const token = sanitizeToken(state.token);
                        if (token) {
                            params.token = token;
                        }

                        const source = createImageSource(url, params);
                        localState.imagerySource = source;
                        localState.imageryLayer.setSource(source);

                        const extent = buildLayerExtent(payload, geometryExtent);
                        if (extent) {
                            localState.imageryLayer.setExtent(extent);
                        } else {
                            localState.imageryLayer.setExtent(undefined);
                        }

                        localState.imageryLayer.setVisible(true);
                        return true;
                    };

                    // Show or hide the preview panel container.
                    const setPanelVisible = (visible) => {
                        if (!previewElements.panel) return;
                        previewElements.panel.classList.toggle('hidden', !visible);
                        previewElements.panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
                    };

                    // Replace preview title and metadata labels with provided values.
                    const setPanelContent = (content = {}) => {
                        const next = {
                            ...defaultContent,
                            ...content
                        };
                        if (previewElements.title) previewElements.title.textContent = next.title;
                        if (previewElements.acquired) previewElements.acquired.textContent = next.acquired;
                        if (previewElements.details) {
                            previewElements.details.textContent = next.details;
                            previewElements.details.classList.toggle('hidden', !next.details);
                        }
                        if (previewElements.status) previewElements.status.textContent = next.status;
                    };

                    // Animate the main map to zoom onto the preview geometry.
                    const focusExtent = (extent) => {
                        if (!extent || !localState.map) return;
                        const view = localState.map.getView?.();
                        if (!view?.fit) return;
                        view.fit(extent, {
                            padding: [32, 32, 32, 32],
                            duration: 400,
                            maxZoom: 14
                        });
                    };

                    // Convert footprint/geometry payloads into OpenLayers features.
                    const buildFeature = (payload) => {
                        if (!localState.geoJson || !localState.projection || !payload) return null;
                        const {
                            footprint,
                            geometry,
                            bbox
                        } = payload;
                        try {
                            if (footprint) {
                                return localState.geoJson.readFeature(footprint, {
                                    featureProjection: localState.projection,
                                    dataProjection
                                });
                            }
                            if (geometry) {
                                return localState.geoJson.readFeature({
                                    type: 'Feature',
                                    geometry
                                }, {
                                    featureProjection: localState.projection,
                                    dataProjection
                                });
                            }
                            if (Array.isArray(bbox) && bbox.length === 4) {
                                const transformed = ol.proj.transformExtent(bbox, dataProjection, localState.projection);
                                return new ol.Feature({
                                    geometry: ol.geom.Polygon.fromExtent(transformed)
                                });
                            }
                        } catch (error) {
                            console.error('Unable to construct Sentinel footprint', error);
                        }
                        return null;
                    };

                    // Sync button states (clear/download) with the current selection.
                    const updateActions = () => {
                        const hasSelection = Boolean(localState.selection);
                        if (previewElements.clearButton) {
                            previewElements.clearButton.disabled = !hasSelection;
                            previewElements.clearButton.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
                        }
                        if (!previewElements.downloadButton) return;
                        if (hasSelection && localState.selection.downloadUrl && hasToken()) {
                            previewElements.downloadButton.classList.remove('hidden');
                            previewElements.downloadButton.setAttribute('href', localState.selection.downloadUrl);
                            previewElements.downloadButton.setAttribute('aria-disabled', 'false');
                            previewElements.downloadButton.setAttribute('title', `Download full scene for ${localState.selection.label}`);
                            previewElements.downloadButton.setAttribute('download', localState.selection.downloadFilename);
                            previewElements.downloadButton.dataset.downloadBase = localState.selection.downloadBase ?? '';
                            previewElements.downloadButton.tabIndex = 0;
                        } else {
                            previewElements.downloadButton.classList.add('hidden');
                            previewElements.downloadButton.removeAttribute('href');
                            previewElements.downloadButton.removeAttribute('download');
                            previewElements.downloadButton.removeAttribute('title');
                            previewElements.downloadButton.setAttribute('aria-disabled', 'true');
                            delete previewElements.downloadButton.dataset.downloadBase;
                            previewElements.downloadButton.tabIndex = -1;
                        }
                    };

                    // Render preview metadata and geometry for the chosen catalogue entry.
                    const show = (payload) => {
                        const context = ensureContext();
                        if (!context) return;
                        cancelPendingImageryLoad();
                        context.source?.clear();
                        context.layer?.setVisible(false);
                        if (localState.imageryLayer) {
                            localState.imageryLayer.setVisible(false);
                            localState.imageryLayer.setExtent(undefined);
                        }
                        localState.imagerySource = null;
                        localState.selection = null;
                        updateActions();
                        if (!payload) {
                            setPanelContent();
                            setPanelVisible(false);
                            if (localState.imageryLayer) {
                                localState.imageryLayer.setVisible(false);
                                localState.imageryLayer.setExtent(undefined);
                            }
                            localState.imagerySource = null;
                            return;
                        }
                        const title = payload.title || payload.productId || 'Sentinel-2 Preview';
                        const acquiredText = payload.acquiredText ?? (payload.acquisitionDate ? `Acquired: ${formatReadableDate(payload.acquisitionDate)}` : 'Acquisition date unavailable.');
                        const detailParts = [];
                        if (payload.detailText) detailParts.push(payload.detailText);
                        if (payload.tileText || payload.tileId) detailParts.push(payload.tileText || payload.tileId);
                        if (typeof payload.cloudCover === 'number' && !Number.isNaN(payload.cloudCover)) {
                            detailParts.push(`Cloud cover: ${formatCloudCover(Number(payload.cloudCover))}`);
                        }
                        if (payload.collection) detailParts.push(`Collection: ${payload.collection}`);
                        const details = detailParts.join(' • ');
                        const feature = buildFeature(payload);
                        let featureExtent = null;
                        if (feature) {
                            context.source.addFeature(feature);
                            context.layer.setVisible(true);
                            featureExtent = feature.getGeometry()?.getExtent?.() ?? null;
                            if (featureExtent) {
                                focusExtent(featureExtent);
                            }
                        }
                        const imageryShown = applyImageryPreview(payload, featureExtent);
                        const downloadBase = payload.downloadUrlBase || payload.downloadUrl || null;
                        const downloadUrl = applyTokenToUrl(downloadBase);
                        const downloadFilename = payload.downloadFilename ?? buildDownloadName(payload.productId, title);
                        const statusMessage = feature ?
                            (imageryShown ?
                                'Coverage footprint and true color scene displayed on the map.' :
                                'Coverage footprint displayed on the map. Imagery preview unavailable.') :
                            'Coverage area unavailable for this product.';
                        localState.selection = {
                            downloadUrl,
                            downloadBase,
                            downloadFilename,
                            label: title
                        };
                        setPanelContent({
                            title,
                            acquired: acquiredText,
                            details,
                            status: buildStatusMessage(statusMessage)
                        });
                        setPanelVisible(true);
                        updateActions();
                    };

                    // Remove any preview selection and restore default messaging.
                    const clear = () => {
                        localState.selection = null;
                        cancelPendingImageryLoad();
                        localState.source?.clear();
                        localState.layer?.setVisible(false);
                        if (localState.imageryLayer) {
                            localState.imageryLayer.setVisible(false);
                            localState.imageryLayer.setExtent(undefined);
                        }
                        localState.imagerySource = null;
                        setPanelContent(defaultContent);
                        setPanelVisible(false);
                        updateActions();
                    };

                    setPanelContent(defaultContent);
                    setPanelVisible(false);
                    updateActions();

                    return {
                        ensure: ensureContext,
                        show,
                        clear
                    };
                };

                const previewModule = createPreviewModule();

                if (typeof window !== 'undefined') {
                    if (window.map) {
                        previewModule.ensure();
                    } else {
                        window.addEventListener('map:ready', () => {
                            previewModule.ensure();
                        }, {
                            once: true
                        });
                    }
                }

                if (previewElements.clearButton) {
                    previewElements.clearButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        previewModule.clear();
                    });
                }

                window.showSentinelPreviewOnMap = (payload) => {
                    previewModule.show(payload);
                };

                // Fetch catalogue data, retrying through AllOrigins if needed.
                const fetchCatalog = async (url) => {
                    const attempt = async (targetUrl) => {
                        const response = await fetch(targetUrl);
                        if (!response.ok) {
                            throw new Error(`Status ${response.status}`);
                        }
                        return response.json();
                    };
                    try {
                        return await attempt(url);
                    } catch (error) {
                        const proxyUrl = `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`;
                        return await attempt(proxyUrl);
                    }
                };

                // Create a UI card summarizing a single Sentinel catalogue feature.
                const renderCard = (feature) => {
                    if (!templateEl?.content) return null;
                    const clone = templateEl.content.cloneNode(true);
                    const props = feature?.properties ?? {};
                    const links = feature?.links ?? [];
                    const assets = feature?.assets ?? {};

                    const titleEl = clone.querySelector('[data-sentinel-title]');
                    const productEl = clone.querySelector('[data-sentinel-product]');
                    const datetimeEl = clone.querySelector('[data-sentinel-datetime]');
                    const detailEl = clone.querySelector('[data-sentinel-details]');
                    const previewButton = clone.querySelector('[data-sentinel-preview]');
                    const downloadButton = clone.querySelector('[data-sentinel-download]');
                    const thumbnailImg = clone.querySelector('[data-sentinel-thumbnail]');
                    const thumbnailPlaceholder = clone.querySelector('[data-sentinel-placeholder]');

                    const shortenText = typeof window?.shortenFilename === 'function' ?
                        (value, max = 40) => window.shortenFilename(String(value), max) :
                        (value) => String(value ?? '');

                    const productId = props.productIdentifier || props.title || feature?.id || 'Sentinel-2 Product';
                    const acquisitionDate = props.completionDate || props.startDate || props.endPosition || props.beginPosition || props.startTimeFromAscendingNode;
                    const mgrsIdentifier = props.mgrsId || props.tileId || props.MGRS;
                    const tileText = mgrsIdentifier ? `Tile ${mgrsIdentifier}` : null;
                    const cloudCover = props.cloudCover ?? props['cloudcoverpercentage'] ?? props['cloudCoverageAssessment'];

                    const titleText = props.title || productId;
                    const productText = productId;

                    if (titleEl) {
                        titleEl.textContent = shortenText(titleText, 48);
                        titleEl.setAttribute('title', titleText);
                    }
                    if (productEl) {
                        productEl.textContent = shortenText(productText, 44);
                        productEl.setAttribute('title', productText);
                    }
                    if (datetimeEl) {
                        datetimeEl.textContent = `Acquired: ${formatReadableDate(acquisitionDate)}`;
                    }

                    const detailParts = [];
                    if (tileText) detailParts.push(tileText);
                    if (cloudCover !== undefined) detailParts.push(`Cloud cover: ${formatCloudCover(Number(cloudCover))}`);
                    if (props.collection) detailParts.push(`Collection: ${props.collection}`);
                    if (detailEl) {
                        detailEl.textContent = detailParts.length ? detailParts.join(' • ') : 'No additional metadata available';
                    }

                    const quicklookUrl = props.thumbnail ||
                        props.quicklook ||
                        assets?.thumbnail?.href ||
                        assets?.overview?.href ||
                        links.find((link) => link.rel === 'preview')?.href;

                    const downloadUrl = resolveDownloadUrl(feature);
                    const downloadUrlWithToken = applyTokenToUrl(downloadUrl);
                    const downloadFilename = buildDownloadName(productId, titleText);

                    if (downloadButton) {
                        if (downloadUrl && downloadUrlWithToken && hasToken()) {
                            downloadButton.classList.remove('hidden');
                            downloadButton.setAttribute('href', downloadUrlWithToken);
                            downloadButton.setAttribute('aria-disabled', 'false');
                            downloadButton.setAttribute('title', `Download full scene for ${productText}`);
                            downloadButton.setAttribute('download', downloadFilename);
                            downloadButton.dataset.downloadBase = downloadUrl;
                            downloadButton.tabIndex = 0;
                        } else {
                            downloadButton.classList.add('hidden');
                            downloadButton.setAttribute('href', '#');
                            downloadButton.setAttribute('aria-disabled', 'true');
                            downloadButton.removeAttribute('download');
                            delete downloadButton.dataset.downloadBase;
                            downloadButton.tabIndex = -1;
                        }
                    }

                    if (thumbnailImg) {
                        if (quicklookUrl) {
                            const handleError = () => {
                                thumbnailImg.classList.add('hidden');
                                thumbnailImg.removeAttribute('src');
                                thumbnailPlaceholder?.classList.remove('hidden');
                            };
                            const handleLoad = () => {
                                thumbnailImg.classList.remove('hidden');
                                thumbnailPlaceholder?.classList.add('hidden');
                            };
                            thumbnailImg.classList.add('hidden');
                            thumbnailPlaceholder?.classList.remove('hidden');
                            thumbnailImg.addEventListener('error', handleError, {
                                once: true
                            });
                            thumbnailImg.addEventListener('load', handleLoad, {
                                once: true
                            });
                            thumbnailImg.src = quicklookUrl;
                            thumbnailImg.alt = `Quicklook preview for ${productText}`;
                        } else {
                            thumbnailImg.classList.add('hidden');
                            thumbnailImg.removeAttribute('src');
                            thumbnailPlaceholder?.classList.remove('hidden');
                        }
                    }

                    const bboxArray = Array.isArray(feature?.bbox) ? feature.bbox : (Array.isArray(props?.bbox) ? props.bbox : null);
                    const hasCoverage = Boolean(feature?.geometry) || (Array.isArray(bboxArray) && bboxArray.length === 4);

                    if (previewButton) {
                        if (hasCoverage || quicklookUrl) {
                            previewButton.disabled = false;
                            previewButton.title = 'Display preview on the map';
                            previewButton.addEventListener('click', () => {
                                window.showSentinelPreviewOnMap?.({
                                    title: titleText,
                                    productId,
                                    quicklookUrl,
                                    downloadUrl: downloadUrlWithToken,
                                    downloadUrlBase: downloadUrl,
                                    downloadFilename,
                                    geometry: feature?.geometry,
                                    bbox: bboxArray,
                                    acquisitionDate,
                                    tileText,
                                    cloudCover,
                                    collection: props.collection || feature?.collection || null,
                                    services: props?.services ?? null,
                                    links,
                                    assets,
                                    featureProperties: props,
                                    featureId: feature?.id ?? null
                                });
                            });
                        } else {
                            previewButton.disabled = true;
                            previewButton.title = 'Preview not available for this product';
                        }
                    }

                    return clone;
                };

                // Display a loading message while clearing previous catalogue entries.
                const setLoadingState = () => {
                    statusEl.classList.remove('hidden');
                    statusEl.textContent = 'Fetching latest Sentinel-2 collections...';
                    listEl.innerHTML = '';
                };

                // Ensure numeric filter inputs stay within sensible limits.
                const normalizeInputValue = (input, min, max) => {
                    if (!input) return;
                    if (input.value === '') {
                        input.value = '';
                        return;
                    }
                    const parsed = Number(input.value);
                    if (Number.isNaN(parsed)) {
                        input.value = '';
                        return;
                    }
                    const normalized = clampNumber(parsed, min, max);
                    if (normalized !== null) {
                        input.value = normalized.toString();
                    }
                };

                // Stamp the timestamp of the most recent successful fetch.
                const updateLastUpdated = () => {
                    if (lastUpdatedEl) {
                        lastUpdatedEl.textContent = new Date().toLocaleString('id-ID');
                    }
                };

                // Reset all filter fields back to their default configuration.
                const resetFilters = () => {
                    if (cloudInputEl) cloudInputEl.value = config.defaultCloudCover;
                    applyDefaultDates(true);
                    if (latInputEl) latInputEl.value = config.defaultLatitude;
                    if (lonInputEl) lonInputEl.value = config.defaultLongitude;
                    if (levelInputEl) levelInputEl.value = config.defaultProductType;
                };

                // Construct the query string parameters for the Sentinel catalogue API.
                const buildQueryParams = () => {
                    const params = new URLSearchParams({
                        maxRecords: '20',
                        sortParam: 'startDate',
                        sortOrder: 'descending'
                    });

                    const startValue = startDateInputEl?.value?.trim();
                    const endValue = endDateInputEl?.value?.trim();
                    const fallbackRange = getDefaultDateRange(config.defaultMonthsBack);

                    let startDate = parseDate(startValue) ?? parseDate(state.defaultStartIso) ?? parseDate(fallbackRange.start) ?? parseDate(new Date());
                    let endDate = parseDate(endValue) ?? parseDate(state.defaultEndIso) ?? parseDate(fallbackRange.end) ?? parseDate(new Date());

                    if (!startDate || !endDate) {
                        throw new Error('Please provide a valid start and end date to filter collections.');
                    }

                    startDate.setHours(0, 0, 0, 0);
                    endDate.setHours(23, 59, 59, 999);

                    if (startDate > endDate) {
                        throw new Error('Start date must be earlier than or equal to end date.');
                    }

                    const startIso = ensureIsoDateString(startDate);
                    const endIso = ensureIsoDateString(endDate);
                    if (!startIso || !endIso) {
                        throw new Error('Unable to format the selected date range. Please adjust the dates and try again.');
                    }

                    params.set('startDate', startIso);
                    params.set('completionDate', endIso);

                    const productTypeRaw = levelInputEl?.value?.trim();
                    const productType = ['S2MSI2A', 'S2MSI1C'].includes(productTypeRaw) ? productTypeRaw : config.defaultProductType;
                    params.set('productType', productType);

                    const cloudRaw = cloudInputEl?.value?.trim();
                    const cloudNumber = cloudRaw === '' || cloudRaw === undefined ? null : Number(cloudRaw);
                    if (cloudNumber !== null && !Number.isNaN(cloudNumber)) {
                        const normalized = clampNumber(cloudNumber, 0, 100);
                        if (normalized !== null) {
                            params.set('cloudCover', `[0,${Math.round(normalized)}]`);
                        }
                    }

                    const latRaw = latInputEl?.value?.trim();
                    const lonRaw = lonInputEl?.value?.trim();
                    const hasLat = latRaw !== undefined && latRaw !== '';
                    const hasLon = lonRaw !== undefined && lonRaw !== '';

                    if ((hasLat && !hasLon) || (!hasLat && hasLon)) {
                        throw new Error('Please provide both latitude and longitude to filter by location.');
                    }

                    if (hasLat && hasLon) {
                        const latNumber = Number(latRaw);
                        const lonNumber = Number(lonRaw);
                        const normalizedLat = clampNumber(latNumber, -90, 90);
                        const normalizedLon = clampNumber(lonNumber, -180, 180);
                        if (normalizedLat === null || normalizedLon === null) {
                            throw new Error('Latitude must be between -90 and 90 and longitude between -180 and 180.');
                        }
                        params.set('lat', normalizedLat.toFixed(6));
                        params.set('lon', normalizedLon.toFixed(6));
                    }

                    return params;
                };

                // Fetch Sentinel collections based on current filters and render them.
                const loadCollections = async (forceRefresh = false) => {
                    applyDefaultDates(false);
                    syncDateConstraints();

                    if (forceRefresh && window.MyZkToast?.info) {
                        window.MyZkToast.info('Refreshing Sentinel-2 catalogue...');
                    }

                    setLoadingState();

                    try {
                        const params = buildQueryParams();
                        const requestUrl = `${config.endpoint}?${params.toString()}`;
                        const response = await fetchCatalog(requestUrl);
                        const features = Array.isArray(response?.features) ? response.features : [];

                        if (!features.length) {
                            statusEl.textContent = 'No Sentinel-2 collections found for the selected date range.';
                        } else {
                            statusEl.classList.add('hidden');
                            features.forEach((feature) => {
                                const card = renderCard(feature);
                                if (card) {
                                    listEl.appendChild(card);
                                }
                            });
                        }

                        state.loadedOnce = true;
                        window.AppMap.sentinel.loadedOnce = true;
                        updateLastUpdated();

                        if (features.length && forceRefresh && window.MyZkToast?.success) {
                            window.MyZkToast.success('Sentinel-2 collections updated.');
                        }
                    } catch (error) {
                        statusEl.classList.remove('hidden');
                        statusEl.textContent = error?.message || 'Unable to fetch Sentinel-2 collections. Please try again later.';
                        updateLastUpdated();
                        if (window.MyZkToast?.error) {
                            window.MyZkToast.error('Failed to update Sentinel-2 collections.');
                        }
                    }
                };

                // Attach listeners to filter inputs and actions to keep data fresh.
                const bindEvents = () => {
                    startDateInputEl?.addEventListener('change', () => {
                        if (endDateInputEl && startDateInputEl.value && endDateInputEl.value && endDateInputEl.value < startDateInputEl.value) {
                            endDateInputEl.value = startDateInputEl.value;
                        }
                        syncDateConstraints();
                    });

                    endDateInputEl?.addEventListener('change', () => {
                        if (startDateInputEl && endDateInputEl.value && startDateInputEl.value && startDateInputEl.value > endDateInputEl.value) {
                            startDateInputEl.value = endDateInputEl.value;
                        }
                        syncDateConstraints();
                    });

                    cloudInputEl?.addEventListener('blur', () => normalizeInputValue(cloudInputEl, 0, 100));
                    latInputEl?.addEventListener('blur', () => normalizeInputValue(latInputEl, -90, 90));
                    lonInputEl?.addEventListener('blur', () => normalizeInputValue(lonInputEl, -180, 180));

                    formEl?.addEventListener('submit', (event) => {
                        event.preventDefault();
                        loadCollections(true);
                    });

                    resetButtonEl?.addEventListener('click', () => {
                        resetFilters();
                        loadCollections(true);
                    });
                };

                // Bootstrap sentinel catalogue helpers and trigger the initial load.
                const initialise = () => {
                    ensureAppNamespace();
                    window.AppMap.sentinel.loadedOnce = state.loadedOnce;
                    window.AppMap.sentinel.loadCollections = loadCollections;

                    if (cloudInputEl && cloudInputEl.value === '') {
                        cloudInputEl.value = config.defaultCloudCover;
                    }
                    if (latInputEl && latInputEl.value === '') {
                        latInputEl.value = config.defaultLatitude;
                    }
                    if (lonInputEl && lonInputEl.value === '') {
                        lonInputEl.value = config.defaultLongitude;
                    }
                    if (levelInputEl && levelInputEl.value === '') {
                        levelInputEl.value = config.defaultProductType;
                    }

                    waitForFormatIso(() => applyDefaultDates(false));
                    bindEvents();
                    loadCollections();

                    document.dispatchEvent(new CustomEvent('app:sentinel:ready', {
                        detail: window.AppMap.sentinel
                    }));
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initialise, {
                        once: true
                    });
                } else {
                    initialise();
                }
            })();
        </script>
    @endpush

</x-app-front-map-layout>

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
            <!-- Credit Display for Authenticated Users -->
            @auth
                <div class="bg-primary/10 text-primary flex items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                    <i class="ri-coins-line mr-2"></i>
                    <span>{{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }} Credit Points</span>
                </div>
            @endauth

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
                                                <span>Sentinel-2, Landsat, and commercial satellites</span>
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
                                            <x-button-primary id="startBtn" type="button" size="small">🚀 Start Upload</x-button-primary>
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
                <div class="border-foreground/30 absolute left-0 top-full z-50 mt-1 hidden max-h-[300px] w-full overflow-y-auto rounded-lg border bg-white shadow-lg" id="search-results-recommendation">
                    <!-- Results will be dynamically inserted here -->
                </div>
            </div>

            <!-- Sentinel Preview Panel -->
            <div class="absolute left-2 right-2 top-[6.5rem] z-40 flex justify-end sm:left-auto sm:right-2 sm:w-80">
                <div class="border-foreground/15 bg-background/95 supports-[backdrop-filter]:bg-background/70 pointer-events-auto hidden w-full max-w-md break-all rounded-xl border p-2 text-xs shadow-lg backdrop-blur" id="sentinelPreviewPanel">
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
            const panelWrapper = document.getElementById("panel-wrapper");
            const panels = document.querySelectorAll("#panel-wrapper section");
            const sidebarButtons = document.querySelectorAll(".sidebar-btn");
            const scrollContainer = document.getElementById('scroll-container');
            const scrollLeftBtn = document.getElementById('scroll-left');
            const scrollRightBtn = document.getElementById('scroll-right');


            const scrollAmount = 150; // pixels per click

            // Mobile nav arrows scroll the horizontal chip list into view.
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

            // Enable click-and-drag scrolling for a smoother touchpad-like experience on desktop.
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

            // Toggle the requested panel and ensure the wrapper animates correctly for the viewport size.
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

                if (id === 'sentinel-panel' && !sentinelLoadedOnce) {
                    loadSentinelCollections();
                }
            }

            // Collapse the panel wrapper and clear active states.
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
            // Open the default panel on load and fetch the initial Sentinel catalogue.
            window.addEventListener("DOMContentLoaded", () => {
                const defaultPanel = 'data-panel';
                const isMobile = window.innerWidth < 768;

                const defaultBtn = isMobile ?
                    document.querySelector(`#scroll-container .sidebar-btn[onclick*='${defaultPanel}']`) :
                    document.querySelector(`aside .sidebar-btn[onclick*='${defaultPanel}']`);

                showPanel(defaultPanel, defaultBtn);

                if (!sentinelLoadedOnce) {
                    loadSentinelCollections();
                }
            });

            // === RESPONSIVE HANDLER: SYNC STATE SAAT RESIZE ===
            // Reconcile mobile/desktop panel classes whenever the viewport size changes.
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


            // Price calculation functions, estimates for the purchase panel.
            function calculateTotalPrice() {
                // Get area from global variable (set when polygon is drawn)
                const areaInSquareMeters = window.geojsonArea || 0;
                const areaInHectares = areaInSquareMeters / 10000; // Convert m² to hectares

                // Calculate credit points needed (using global constant rate)
                const creditPointsNeeded = areaInHectares * {{ config('app-constants.imagery_credit_cost_per_hectare') }};

                // Update the display
                const totalPriceElement = document.getElementById('total_price');
                const priceContainer = totalPriceElement.parentElement;

                if (areaInHectares > 0) {
                    totalPriceElement.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-green-700">${formatNumber(creditPointsNeeded.toFixed(2),2)} Credit Points</span>
                            <i class="ri-coins-line font-base text-success text-xl"></i>
                        </div>
                        <div class="text-xs text-foreground-70 mt-1">
                            ${formatNumber(areaInHectares)} hectares × {{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), locale: app()->getLocale()) }} credit points/hectare
                        </div>
                    `;
                    priceContainer.classList.remove('bg-muted/60', 'border-muted');
                    priceContainer.classList.add('bg-green-50', 'border-green-300', 'shadow-sm');

                    // Add a subtle animation
                    priceContainer.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        priceContainer.style.transform = 'scale(1)';
                    }, 200);
                } else {
                    totalPriceElement.innerHTML = `
                        <div class="flex items-center text-foreground/50">
                            <i class="ri-information-line mr-2"></i>
                            Draw an area to calculate credit points
                        </div>
                    `;
                    priceContainer.classList.remove('bg-green-50', 'border-green-300', 'shadow-sm', 'bg-amber-50', 'border-amber-300');
                    priceContainer.classList.add('bg-muted/60', 'border-muted');
                }

                // Add transition for smooth color changes
                priceContainer.style.transition = 'all 0.3s ease-in-out';
            }

            // Make calculateTotalPrice available globally for map.js
            window.calculateTotalPrice = calculateTotalPrice;
        </script>
    @endpush

    @push('javascript')
        <script>
            // All uploader related DOM elements
            const sourceInput = document.getElementById('sourceType');
            const fileInput = document.getElementById('fileInput');
            const fileInfo = document.getElementById('fileInfo');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const startBtn = document.getElementById('startBtn');
            const pauseBtn = document.getElementById('pauseBtn');
            const resumeBtn = document.getElementById('resumeBtn');
            const myDataContainer = document.getElementById('myDataContainer');

            const uploaderElements = {
                sourceInput,
                fileInput,
                fileInfo,
                progressBar,
                progressText,
                startBtn,
                pauseBtn,
                resumeBtn,
                myDataContainer
            };

            // Quickly verify whether every required element exists before wiring up listeners.
            const uploaderReady = Object.values(uploaderElements).every((element) => Boolean(element));
            if (!uploaderReady) {
                console.warn('Imagery uploader controls missing. Skipping uploader bootstrap.', {
                    hasSourceInput: Boolean(sourceInput),
                    hasFileInput: Boolean(fileInput),
                    hasFileInfo: Boolean(fileInfo),
                    hasProgressBar: Boolean(progressBar),
                    hasProgressText: Boolean(progressText),
                    hasStartBtn: Boolean(startBtn),
                    hasPauseBtn: Boolean(pauseBtn),
                    hasResumeBtn: Boolean(resumeBtn),
                    hasMyDataContainer: Boolean(myDataContainer)
                });
            } else {
                // Track upload state locally so we can update the UI without querying the server.
                let paused = false;
                let uploading = false;
                let file = null;
                let uploadId = null;
                let currentChunk = 0;
                let totalChunks = 0;
                const chunkSize = 5 * 1024 * 1024; // 5 MB per chunk
                let startTime = null;
                let uploadedBytes = 0;

                // === INIT ===
                setButtonState("idle");
                loadMyData();

                // On input imagery file, updating the summary row and element.
                fileInput.addEventListener("change", (e) => {
                    file = e.target.files[0];
                    if (!file) return;

                    const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                    const shortName = shortenFilename(file.name, 40);

                    fileInfo.classList.remove("hidden");
                    fileInfo.innerHTML = `
                        <strong>Name:</strong> ${shortName}<br>
                        <strong>Size:</strong> ${sizeMB} MB
                    `;

                    progressText.textContent = "✅ File ready to upload. Click 'Start Upload' to begin.";
                    progressBar.style.width = "0%";
                    MyZkToast.info("File ready to upload, click Start to begin.");
                    setButtonState("ready");
                });

                //  Start upload session with a pseudo-random upload id.
                startBtn.addEventListener("click", () => {
                    if (!file) {
                        MyZkToast.warning("Please select a file first!");
                        return;
                    }

                    uploadId = Math.random().toString(36).substring(2, 12);
                    totalChunks = Math.ceil(file.size / chunkSize);
                    currentChunk = 0;
                    uploadedBytes = 0;
                    paused = false;
                    uploading = true;
                    startTime = performance.now();

                    MyZkToast.info("🚀 Upload started...");
                    progressText.textContent = `🚀 Uploading ${file.name}...`;
                    setButtonState("uploading");
                    uploadNextChunk();
                });

                // Pause upload, flips the flags so the recursive chunk uploader stops itself.
                pauseBtn.addEventListener("click", () => {
                    if (!uploading) return;
                    paused = true;
                    uploading = false;
                    progressText.textContent = "⏸️ Upload paused.";
                    MyZkToast.warning("Upload paused.");
                    setButtonState("paused");
                });

                // Resume upload without reinitialising counters or the upload id.
                resumeBtn.addEventListener("click", () => {
                    if (!file) return;
                    paused = false;
                    uploading = true;
                    progressText.textContent = "▶️ Upload resumed...";
                    MyZkToast.info("Upload resumed...");
                    setButtonState("uploading");
                    uploadNextChunk();
                });

                // Upload the next chunk of the file, retrying transient failures with exponential back-off.
                async function uploadNextChunk(retryCount = 0) {
                    if (paused || !file) return;

                    if (currentChunk >= totalChunks) {
                        progressText.textContent = "🧩 Merging file on server...";
                        return mergeChunks();
                    }

                    const start = currentChunk * chunkSize;
                    const end = Math.min(file.size, start + chunkSize);
                    const chunk = file.slice(start, end);
                    const chunkSizeBytes = end - start;

                    const formData = new FormData();
                    formData.append("upload_id", uploadId);
                    formData.append("chunk_index", currentChunk);
                    formData.append("chunk", chunk);

                    try {
                        const res = await fetch('{{ route('upload.chunk') }}', {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: formData,
                        });

                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || `Chunk ${currentChunk} failed.`);
                        }

                        currentChunk++;
                        uploadedBytes += chunkSizeBytes;

                        const now = performance.now();
                        const elapsedSec = (now - startTime) / 1000;
                        const speedMBps = (uploadedBytes / 1024 / 1024 / elapsedSec).toFixed(2);
                        const remainingBytes = file.size - uploadedBytes;
                        const estRemainingSec = remainingBytes / (speedMBps * 1024 * 1024);
                        const etaText = estRemainingSec > 0 ? formatTimeETA(estRemainingSec) : "-";

                        const progress = Math.round((currentChunk / totalChunks) * 100);
                        progressBar.style.width = `${progress}%`;
                        progressText.textContent = `Uploading... ${progress}% | 🚀 ${speedMBps} MB/s | ⏳ ETA: ${etaText}`;

                        if (progress === 100) {
                            MyZkToast.info("Merging file on server...");
                        }

                        if (!paused) uploadNextChunk();

                    } catch (err) {
                        if (retryCount < 3) {
                            setTimeout(() => uploadNextChunk(retryCount + 1), 2000 * (retryCount + 1));
                        } else {
                            progressText.textContent = `❌ Chunk ${currentChunk} failed after 3 retries. Upload paused.`;
                            MyZkToast.error(`Chunk ${currentChunk} failed after 3 retries.`);
                            paused = true;
                            uploading = false;
                            setButtonState("paused");
                        }
                    }
                }

                // Ask the backend to merge all uploaded chunks and register the final file metadata.
                async function mergeChunks() {
                    setButtonState("merging");

                    const sourceType = sourceInput.value;
                    const formData = new FormData();
                    formData.append("upload_id", uploadId);
                    formData.append("filename", file.name);
                    formData.append("total_chunks", totalChunks);
                    formData.append("source_type", sourceType);

                    try {
                        const res = await fetch('{{ route('upload.merge') }}', {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: formData,
                        });

                        const result = await res.json();

                        if (res.ok && result.success) {
                            progressBar.style.width = "100%";
                            progressText.textContent = `✅ Upload complete! ${result.message || "Upload completed. Processing started in background."}`;
                            MyZkToast.success(result.message || "Upload completed successfully!");
                            setButtonState("done");
                            await loadMyData();
                            autoReset();
                        } else {
                            throw new Error(result.message || "Failed to merge file on server.");
                        }

                    } catch (err) {
                        progressText.textContent = `❌ Error: ${err.message}`;
                        MyZkToast.error(err.message || "Server error during merge.");
                        setButtonState("error");
                        autoReset();
                    }
                }

                // Reset the form state a few seconds after a successful or failed upload.
                function autoReset() {
                    setTimeout(() => {
                        file = null;
                        fileInput.value = "";
                        fileInfo.classList.add("hidden");
                        progressBar.style.width = "0%";
                        progressText.textContent = "Ready for next upload.";
                        setButtonState("idle");
                    }, 4000);
                }

                // LOAD MY DATA, Fetch the authenticated user imagery list and render each entry with the template card.
                async function loadMyData() {
                    myDataContainer.innerHTML = `
                    <div class="flex justify-center py-4">
                        <p class="text-sm text-foreground/60 animate-pulse">Loading your imagery list...</p>
                    </div>
                `;

                    try {
                        const res = await fetch('{{ route('imagery.list') }}');
                        const result = await res.json();

                        if (!res.ok || !result.success) throw new Error(result.message || "Failed to fetch imagery data.");
                        const data = result.data;

                        myDataContainer.innerHTML = ''; // clear existing

                        if (data.length === 0) {
                            myDataContainer.innerHTML = `<p class="text-sm text-gray-400 text-center py-4">No imagery uploaded yet.</p>`;
                            return;
                        }

                        const cardListDataImagery = document.getElementById('imageryCardTemplate');

                        data.forEach(item => {
                            const clone = cardListDataImagery.content.cloneNode(true);
                            const card = clone.querySelector('.imagery-card');

                            // populate data
                            card.querySelector('.imagery-format').textContent = item.format.slice(0, 3).toUpperCase();
                            card.querySelector('.imagery-name').textContent = shortenFilename(item.original_name, 25);
                            const meta = `${(item.size / 1024 / 1024).toFixed(2)} MB • ${new Date(item.uploaded_at).toLocaleDateString()} • `;
                            const statusEl = card.querySelector('.imagery-status');
                            statusEl.textContent = item.processing_status;
                            statusEl.classList.toggle('text-success', item.processing_status === 'done');
                            statusEl.classList.toggle('text-warning', item.processing_status !== 'done');
                            card.querySelector('.imagery-meta').innerHTML = `${meta}<span class="${statusEl.className}">${statusEl.textContent}</span>`;

                            // handle view button
                            const viewBtn = card.querySelector('.view-btn');
                            viewBtn.addEventListener('click', () => viewImagery(item));

                            // append to container
                            myDataContainer.appendChild(clone);
                        });

                    } catch (err) {
                        myDataContainer.innerHTML = `
                        <div class="text-sm text-red-500 bg-red-50 border border-red-200 rounded p-3">
                            ❌ ${err.message}
                        </div>
                        `;
                    }
                }

            }

            // === HELPER FUNCTIONS ===
            // Centralised place to toggle the uploader button states for each lifecycle stage.
            function setButtonState(state) {
                if (!startBtn || !pauseBtn || !resumeBtn) {
                    return;
                }
                switch (state) {
                    case "idle":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "ready": // file sudah dipilih
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "uploading":
                        startBtn.disabled = true;
                        pauseBtn.disabled = false;
                        resumeBtn.disabled = true;
                        break;
                    case "paused":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = false;
                        break;
                    case "merging":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "done":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "error":
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                }
            }
        </script>
    @endpush

    @push('javascript')
        <script>
            // All sentinel related DOM elements and default variables
            const sentinelStatus = document.getElementById('sentinelCollectionStatus');
            const sentinelList = document.getElementById('sentinelCollectionList');
            const sentinelTemplate = document.getElementById('sentinelCollectionTemplate');
            const sentinelLastUpdated = document.getElementById('sentinelLastUpdated');
            const sentinelFilterForm = document.getElementById('sentinelFilterForm');
            const sentinelFilterResetButton = document.getElementById('sentinelFilterResetButton');
            const sentinelCloudInput = document.getElementById('sentinelCloudFilter');
            const sentinelStartDateInput = document.getElementById('sentinelStartDate');
            const sentinelEndDateInput = document.getElementById('sentinelEndDate');
            const sentinelLatInput = document.getElementById('sentinelLatFilter');
            const sentinelLonInput = document.getElementById('sentinelLonFilter');
            const sentinelLevelInput = document.getElementById('sentinelProductLevel');
            const sentinelPanelEl = document.getElementById('sentinel-panel');
            const sentinelPreviewPanel = document.getElementById('sentinelPreviewPanel');
            const sentinelPreviewTitle = sentinelPreviewPanel?.querySelector('[data-sentinel-preview-title]');
            const sentinelPreviewAcquired = sentinelPreviewPanel?.querySelector('[data-sentinel-preview-acquired]');
            const sentinelPreviewDetails = sentinelPreviewPanel?.querySelector('[data-sentinel-preview-details]');
            const sentinelPreviewStatus = sentinelPreviewPanel?.querySelector('[data-sentinel-preview-status]');
            const sentinelPreviewClearBtn = document.getElementById('sentinelPreviewClearBtn');
            const sentinelPreviewDownloadBtn = document.getElementById('sentinelPreviewDownloadBtn');

            const sentinelPreviewDefaultTitle = sentinelPreviewTitle?.textContent ?? '–';
            const sentinelPreviewDefaultAcquired = sentinelPreviewAcquired?.textContent ?? '';
            const sentinelPreviewDefaultDetails = sentinelPreviewDetails?.textContent ?? '';
            const sentinelPreviewDefaultStatus = sentinelPreviewStatus?.textContent ?? '';

            const sentinelCatalogEndpoint = 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json';
            let sentinelLoadedOnce = false;
            const defaultCloudCoverMax = 40;
            const defaultLatitude = -1.24536;
            const defaultLongitude = 114.54535;
            const defaultProductType = 'S2MSI2A';
            const sentinelDefaultMonthsBack = 1;

            const getDateRangeHelper = typeof window.getDefaultDateRange === 'function' ?
                window.getDefaultDateRange :
                (monthsBack = 1) => {
                    const months = Number.isFinite(monthsBack) && monthsBack > 0 ? monthsBack : 1;
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

            const ensureIsoDateString = (date) => {
                if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                    return '';
                }
                return typeof window.formatISODate === 'function' ?
                    window.formatISODate(date) :
                    date.toISOString().split('T')[0];
            };

            const syncSentinelDateConstraints = () => {
                if (!sentinelStartDateInput || !sentinelEndDateInput) return;
                sentinelEndDateInput.min = sentinelStartDateInput.value || '';
                sentinelStartDateInput.max = sentinelEndDateInput.value || '';
            };

            let sentinelDefaultStartValue = '';
            let sentinelDefaultEndValue = '';

            const applySentinelDefaultDates = (force = false) => {
                const sentinelDefaultRange = getDateRangeHelper(sentinelDefaultMonthsBack) || {};
                const sentinelDefaultStartDate = parseDateInput(sentinelDefaultRange.start) ?? new Date();
                sentinelDefaultStartDate.setHours(0, 0, 0, 0);
                const sentinelDefaultEndDate = parseDateInput(sentinelDefaultRange.end) ?? new Date();
                sentinelDefaultEndDate.setHours(23, 59, 59, 999);

                sentinelDefaultStartValue = ensureIsoDateString(sentinelDefaultStartDate);
                sentinelDefaultEndValue = ensureIsoDateString(sentinelDefaultEndDate);

                if (sentinelStartDateInput && (force || !sentinelStartDateInput.value)) {
                    sentinelStartDateInput.value = sentinelDefaultStartValue;
                }
                if (sentinelEndDateInput && (force || !sentinelEndDateInput.value)) {
                    sentinelEndDateInput.value = sentinelDefaultEndValue;
                }

                syncSentinelDateConstraints();
            };

            const waitForFormatISODate = (callback, attempts = 10, delayMs = 50) => {
                if (typeof window.formatISODate === 'function' || attempts <= 0) {
                    callback();
                    return;
                }
                setTimeout(() => {
                    waitForFormatISODate(callback, attempts - 1, delayMs);
                }, delayMs);
            };

            waitForFormatISODate(() => applySentinelDefaultDates(false));

            sentinelStartDateInput?.addEventListener('change', () => {
                if (sentinelEndDateInput && sentinelStartDateInput.value && sentinelEndDateInput.value && sentinelEndDateInput.value < sentinelStartDateInput.value) {
                    sentinelEndDateInput.value = sentinelStartDateInput.value;
                }
                syncSentinelDateConstraints();
            });

            sentinelEndDateInput?.addEventListener('change', () => {
                if (sentinelStartDateInput && sentinelEndDateInput.value && sentinelStartDateInput.value && sentinelStartDateInput.value > sentinelEndDateInput.value) {
                    sentinelStartDateInput.value = sentinelEndDateInput.value;
                }
                syncSentinelDateConstraints();
            });

            // Sentinel responses store cloud cover as a float, ensure consistent text output.
            const formatCloudCover = (value) => {
                if (typeof value === 'number' && !Number.isNaN(value)) {
                    return `${value.toFixed(1)}%`;
                }
                return 'N/A';
            };

            // Prevent filter inputs from sending invalid latitude/longitude or percentage values.
            const clampNumber = (value, min, max) => {
                if (typeof value !== 'number' || Number.isNaN(value)) return null;
                return Math.min(Math.max(value, min), max);
            };

            // Generate a filesystem safe filename for download actions.
            const buildSentinelDownloadName = (primary, fallback) => {
                const base = String(primary ?? fallback ?? 'sentinel-2-scene').trim();
                const normalized = base.replace(/[\s]+/g, '_').replace(/[^A-Za-z0-9._-]+/g, '_');
                return normalized || 'sentinel-2-scene';
            };

            const sentinelDownloadIgnoredKeywords = ['quicklook', 'thumbnail', 'thumb', 'overview', 'browse', 'preview', 'allorigins'];
            const sentinelPreviewIgnoredKeywords = ['thumbnail', 'thumb', 'legend', 'logo', 'browse', 'allorigins'];

            // Trim any whitespace from the access token resolved from the backend configuration.
            const sanitizeSentinelToken = (value) => {
                if (typeof value !== 'string') return '';
                return value.trim();
            };

            let sentinelDownloadToken = sanitizeSentinelToken(sentinelPanelEl?.dataset?.sentinelToken ?? '');
            const sentinelTokenConfigured = (sentinelPanelEl?.dataset?.sentinelCredentials ?? '').toLowerCase() === 'true';

            const hasSentinelToken = () => sanitizeSentinelToken(sentinelDownloadToken).length > 0;

            const buildSentinelStatusMessage = (message) => {
                const parts = [];
                if (message) parts.push(message);
                if (!hasSentinelToken()) {
                    parts.push(
                        sentinelTokenConfigured ?
                        'Download unavailable: Copernicus access token could not be issued.' :
                        'Configure Copernicus credentials on the server to enable downloads.'
                    );
                }
                return parts.join(' ');
            };

            // Append the active access token to Copernicus download when required.
            const applySentinelTokenToUrl = (url) => {
                if (!url) return null;
                const token = sanitizeSentinelToken(sentinelDownloadToken);
                if (!token) return null;
                const queryPattern = /([?&])token=[^&#]*/i;
                if (queryPattern.test(url)) {
                    return url.replace(queryPattern, `$1token=${encodeURIComponent(token)}`);
                }
                const separator = url.includes('?') ? '&' : '?';
                return `${url}${separator}token=${encodeURIComponent(token)}`;
            };

            const isValidSentinelDownloadUrl = (url) => {
                if (typeof url !== 'string') return false;
                const trimmed = url.trim();
                if (!trimmed) return false;
                if (!/^https?:\/\//i.test(trimmed)) return false;
                const lowered = trimmed.toLowerCase();
                return !sentinelDownloadIgnoredKeywords.some((keyword) => lowered.includes(keyword));
            };

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

            // Evaluate every link/asset reference and pick the most appropriate full scene download URL.
            const resolveSentinelDownloadUrl = (feature) => {
                if (!feature) return null;

                const props = feature.properties ?? {};
                const links = Array.isArray(feature.links) ? feature.links : [];
                const assets = feature.assets ?? {};

                const candidateScores = new Map();
                const registerCandidate = (url, score = 0) => {
                    if (!isValidSentinelDownloadUrl(url)) return;
                    const existing = candidateScores.get(url);
                    if (existing === undefined || score > existing) {
                        candidateScores.set(url, score);
                    }
                };

                const registerFromService = (service, score = 0) => {
                    extractServiceUrls(service).forEach((url) => {
                        registerCandidate(url, score);
                    });
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
                    if (sentinelDownloadIgnoredKeywords.some((keyword) => lowerKey.includes(keyword))) {
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
                        if (sentinelDownloadIgnoredKeywords.some((keyword) => lowerKey.includes(keyword))) {
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

            const sentinelPreviewModule = (() => {
                const moduleState = {
                    map: null,
                    projection: null,
                    source: null,
                    layer: null,
                    current: null
                };

                let geoJsonParser = null;
                const dataProjection = 'EPSG:4326';

                const ensureContext = () => {
                    if (typeof window === 'undefined' || typeof ol === 'undefined') {
                        return null;
                    }

                    const mapInstance = window.map;
                    const mapProjection = mapInstance?.getView?.()?.getProjection?.();
                    if (!mapInstance || !mapProjection) {
                        return null;
                    }

                    if (!moduleState.layer) {
                        moduleState.source = new ol.source.Vector();
                        moduleState.layer = new ol.layer.Vector({
                            source: moduleState.source,
                            style: new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: 'rgba(59,130,246,0.9)',
                                    width: 2,
                                    lineDash: [6, 6]
                                }),
                                fill: new ol.style.Fill({
                                    color: 'rgba(59,130,246,0.15)'
                                })
                            }),
                            visible: false,
                            zIndex: 1200
                        });
                        mapInstance.addLayer(moduleState.layer);
                    }

                    geoJsonParser = geoJsonParser ?? new ol.format.GeoJSON();
                    moduleState.map = mapInstance;
                    moduleState.projection = mapProjection;
                    return moduleState;
                };

                const togglePanel = (visible) => {
                    if (!sentinelPreviewPanel) return;
                    sentinelPreviewPanel.classList.toggle('hidden', !visible);
                    sentinelPreviewPanel.setAttribute('aria-hidden', visible ? 'false' : 'true');
                };

                const setPanelContent = ({
                    title,
                    acquired,
                    details,
                    status
                } = {}) => {
                    if (title !== undefined && sentinelPreviewTitle) {
                        sentinelPreviewTitle.textContent = title;
                    }
                    if (acquired !== undefined && sentinelPreviewAcquired) {
                        sentinelPreviewAcquired.textContent = acquired;
                    }
                    if (details !== undefined && sentinelPreviewDetails) {
                        sentinelPreviewDetails.textContent = details;
                        sentinelPreviewDetails.classList.toggle('hidden', !details);
                    }
                    if (status !== undefined && sentinelPreviewStatus) {
                        sentinelPreviewStatus.textContent = status;
                    }
                };

                const focusExtent = (extent) => {
                    if (!extent) return;
                    const mapInstance = moduleState.map;
                    try {
                        const view = mapInstance?.getView?.();
                        if (view && typeof view.fit === 'function') {
                            view.fit(extent, {
                                padding: [32, 32, 32, 32],
                                duration: 500,
                                maxZoom: 14
                            });
                        }
                    } catch (error) {
                        console.error('Failed to fit map to extent', error);
                    }
                };

                const updateButtons = () => {
                    const hasSelection = Boolean(moduleState.current);

                    if (sentinelPreviewClearBtn) {
                        sentinelPreviewClearBtn.disabled = !hasSelection;
                        sentinelPreviewClearBtn.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
                    }

                    if (!sentinelPreviewDownloadBtn) return;

                    const downloadUrl = moduleState.current?.downloadUrl ?? null;
                    const downloadName = moduleState.current?.downloadFilename ?? 'sentinel-2-product.zip';
                    const downloadBase = moduleState.current?.downloadUrlBase ?? '';
                    const label = moduleState.current?.baseTitle ?? 'Sentinel-2 product';

                    if (downloadUrl && hasSentinelToken()) {
                        sentinelPreviewDownloadBtn.classList.remove('hidden');
                        sentinelPreviewDownloadBtn.setAttribute('href', downloadUrl);
                        sentinelPreviewDownloadBtn.setAttribute('aria-disabled', 'false');
                        sentinelPreviewDownloadBtn.setAttribute('title', `Download full scene for ${label}`);
                        sentinelPreviewDownloadBtn.setAttribute('download', downloadName);
                        sentinelPreviewDownloadBtn.dataset.downloadBase = downloadBase;
                        sentinelPreviewDownloadBtn.tabIndex = 0;
                    } else {
                        sentinelPreviewDownloadBtn.classList.add('hidden');
                        sentinelPreviewDownloadBtn.removeAttribute('href');
                        sentinelPreviewDownloadBtn.removeAttribute('download');
                        sentinelPreviewDownloadBtn.removeAttribute('title');
                        sentinelPreviewDownloadBtn.setAttribute('aria-disabled', 'true');
                        delete sentinelPreviewDownloadBtn.dataset.downloadBase;
                        sentinelPreviewDownloadBtn.tabIndex = -1;
                    }
                };

                const buildFootprintFeature = (data) => {
                    if (!data || !geoJsonParser || !moduleState.projection) {
                        return null;
                    }

                    const {
                        footprint,
                        geometry,
                        bbox
                    } = data;

                    try {
                        if (footprint) {
                            return geoJsonParser.readFeature(footprint, {
                                featureProjection: moduleState.projection,
                                dataProjection
                            });
                        }

                        if (geometry) {
                            return geoJsonParser.readFeature({
                                type: 'Feature',
                                geometry
                            }, {
                                featureProjection: moduleState.projection,
                                dataProjection
                            });
                        }

                        if (Array.isArray(bbox) && bbox.length === 4) {
                            const transformed = ol.proj.transformExtent(bbox, dataProjection, moduleState.projection);
                            return new ol.Feature({
                                geometry: ol.geom.Polygon.fromExtent(transformed)
                            });
                        }
                    } catch (error) {
                        console.error('Unable to construct Sentinel footprint', error);
                    }

                    return null;
                };

                const show = (payload) => {
                    if (!payload) return;
                    const context = ensureContext();
                    if (!context) {
                        console.warn('Sentinel preview controller is unavailable. Map is not ready yet.');
                        return;
                    }

                    context.source?.clear();
                    context.layer?.setVisible(false);
                    moduleState.current = null;
                    updateButtons();

                    const title = payload.title || payload.productId || 'Sentinel-2 Preview';
                    const acquiredText = payload.acquiredText ??
                        (payload.acquisitionDate ?
                            `Acquired: ${formatReadableDate(payload.acquisitionDate)}` :
                            'Acquisition date unavailable.');

                    const detailParts = [];
                    if (payload.detailText) detailParts.push(payload.detailText);
                    const tileText = payload.tileText || payload.tileId;
                    if (tileText) detailParts.push(tileText);
                    if (typeof payload.cloudCover === 'number' && !Number.isNaN(payload.cloudCover)) {
                        detailParts.push(`Cloud cover: ${formatCloudCover(Number(payload.cloudCover))}`);
                    }
                    if (payload.collection) detailParts.push(`Collection: ${payload.collection}`);
                    const detailText = detailParts.join(' • ');

                    const feature = buildFootprintFeature(payload);
                    let extent = null;
                    if (feature) {
                        context.source.addFeature(feature);
                        context.layer.setVisible(true);
                        extent = feature.getGeometry()?.getExtent?.() ?? null;
                        focusExtent(extent);
                    }

                    const baseDownloadUrl = payload.downloadUrlBase || payload.downloadUrl || null;
                    const resolvedDownloadUrl = applySentinelTokenToUrl(baseDownloadUrl);
                    const downloadFilename = payload.downloadFilename ??
                        buildSentinelDownloadName(payload.productId, title);

                    const statusMessage = feature ?
                        'Coverage footprint displayed on the map. Imagery preview is disabled.' :
                        'Coverage area unavailable for this product.';

                    moduleState.current = {
                        ...payload,
                        extent,
                        baseTitle: title,
                        baseAcquired: acquiredText,
                        baseDetails: detailText,
                        downloadUrlBase: baseDownloadUrl,
                        downloadUrl: resolvedDownloadUrl,
                        downloadFilename
                    };

                    setPanelContent({
                        title,
                        acquired: acquiredText,
                        details: detailText,
                        status: buildSentinelStatusMessage(statusMessage)
                    });

                    togglePanel(true);
                    updateButtons();
                };

                const clear = () => {
                    moduleState.current = null;
                    moduleState.source?.clear();
                    moduleState.layer?.setVisible(false);
                    setPanelContent({
                        title: sentinelPreviewDefaultTitle,
                        acquired: sentinelPreviewDefaultAcquired,
                        details: sentinelPreviewDefaultDetails,
                        status: sentinelPreviewDefaultStatus
                    });
                    togglePanel(false);
                    updateButtons();
                };

                togglePanel(false);
                updateButtons();

                return {
                    ensure: ensureContext,
                    show,
                    clear
                };
            })();

            if (typeof window !== 'undefined') {
                if (window.map) {
                    sentinelPreviewModule.ensure();
                } else {
                    window.addEventListener('map:ready', () => {
                        sentinelPreviewModule.ensure();
                    }, {
                        once: true
                    });
                }
            }

            if (sentinelPreviewClearBtn) {
                sentinelPreviewClearBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    sentinelPreviewModule.clear();
                });
            }

            window.showSentinelPreviewOnMap = (payload) => {
                sentinelPreviewModule.show(payload);
            };

            // Wrapper around fetch that retries via AllOrigins when CORS rejects the primary request.
            async function fetchSentinelCatalog(url) {
                const attemptFetch = async (targetUrl) => {
                    const response = await fetch(targetUrl);
                    if (!response.ok) {
                        throw new Error(`Status ${response.status}`);
                    }
                    return response.json();
                };

                try {
                    return await attemptFetch(url);
                } catch (err) {
                    const proxyUrl = `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`;
                    return await attemptFetch(proxyUrl);
                }
            }

            // Render a Sentinel catalogue card using the HTML template and bind preview/download handlers.
            function createSentinelCard(feature) {
                if (!sentinelTemplate) return null;

                const clone = sentinelTemplate.content.cloneNode(true);
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
                if (datetimeEl) datetimeEl.textContent = `Acquired: ${formatReadableDate(acquisitionDate)}`;

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
                    links.find(link => link.rel === 'preview')?.href;

                const downloadUrl = resolveSentinelDownloadUrl(feature);
                const downloadUrlWithToken = applySentinelTokenToUrl(downloadUrl);
                const downloadFilename = buildSentinelDownloadName(productId, titleText);
                const tokenAvailable = hasSentinelToken();

                if (downloadButton) {
                    if (downloadUrl && downloadUrlWithToken && tokenAvailable) {
                        const finalDownloadUrl = downloadUrlWithToken;
                        downloadButton.classList.remove('hidden');
                        downloadButton.setAttribute('href', finalDownloadUrl);
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
                        const handleThumbnailError = () => {
                            thumbnailImg.classList.add('hidden');
                            thumbnailImg.removeAttribute('src');
                            if (thumbnailPlaceholder) {
                                thumbnailPlaceholder.classList.remove('hidden');
                            }
                        };

                        const handleThumbnailLoad = () => {
                            thumbnailImg.classList.remove('hidden');
                            if (thumbnailPlaceholder) {
                                thumbnailPlaceholder.classList.add('hidden');
                            }
                        };

                        thumbnailImg.classList.add('hidden');
                        if (thumbnailPlaceholder) {
                            thumbnailPlaceholder.classList.remove('hidden');
                        }

                        thumbnailImg.addEventListener('error', handleThumbnailError, {
                            once: true
                        });
                        thumbnailImg.addEventListener('load', handleThumbnailLoad, {
                            once: true
                        });
                        thumbnailImg.src = quicklookUrl;
                        thumbnailImg.alt = `Quicklook preview for ${productText}`;
                    } else {
                        thumbnailImg.classList.add('hidden');
                        thumbnailImg.removeAttribute('src');
                        if (thumbnailPlaceholder) {
                            thumbnailPlaceholder.classList.remove('hidden');
                        }
                    }
                }

                const bboxArray = Array.isArray(feature?.bbox) ? feature.bbox :
                    (Array.isArray(props?.bbox) ? props.bbox : null);
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
            }

            // Pull the latest Sentinel results with optional filters and populate the panel list.
            async function loadSentinelCollections(forceRefresh = false) {
                if (!sentinelStatus || !sentinelList) return;
                // Ensure the default date cache is refreshed before we inspect any inputs.
                // This prevents the initial load from falling back to "today" for both start
                // and end values when the inputs are still empty and the defaults have not
                // been materialised yet.
                applySentinelDefaultDates(false);
                syncSentinelDateConstraints();

                if (forceRefresh && window.MyZkToast?.info) {
                    window.MyZkToast.info('Refreshing Sentinel-2 catalogue...');
                }

                sentinelStatus.classList.remove('hidden');
                sentinelStatus.textContent = 'Fetching latest Sentinel-2 collections...';
                sentinelList.innerHTML = '';

                const params = new URLSearchParams({
                    maxRecords: '20',
                    sortParam: 'startDate',
                    sortOrder: 'descending'
                });

                const startDateRaw = sentinelStartDateInput?.value?.trim();
                const endDateRaw = sentinelEndDateInput?.value?.trim();

                const sentinelRangeFallback = getDateRangeHelper(sentinelDefaultMonthsBack) || {};

                let startDate = parseDateInput(startDateRaw) ??
                    parseDateInput(sentinelDefaultStartValue) ??
                    parseDateInput(sentinelRangeFallback.start) ??
                    parseDateInput(new Date());
                let endDate = parseDateInput(endDateRaw) ??
                    parseDateInput(sentinelDefaultEndValue) ??
                    parseDateInput(sentinelRangeFallback.end) ??
                    parseDateInput(new Date());

                if (!startDate || !endDate) {
                    sentinelStatus.textContent = 'Please provide a valid start and end date to filter collections.';
                    sentinelList.innerHTML = '';
                    return;
                }

                startDate.setHours(0, 0, 0, 0);
                endDate.setHours(23, 59, 59, 999);

                if (startDate > endDate) {
                    sentinelStatus.textContent = 'Start date must be earlier than or equal to end date.';
                    sentinelList.innerHTML = '';
                    return;
                }

                const startDateIso = ensureIsoDateString(startDate);
                const endDateIso = ensureIsoDateString(endDate);

                if (!startDateIso || !endDateIso) {
                    sentinelStatus.textContent = 'Unable to format the selected date range. Please adjust the dates and try again.';
                    sentinelList.innerHTML = '';
                    return;
                }

                params.set('startDate', startDateIso);
                params.set('completionDate', endDateIso);

                const productTypeRaw = sentinelLevelInput?.value?.trim();
                const productType = ['S2MSI2A', 'S2MSI1C'].includes(productTypeRaw) ? productTypeRaw : defaultProductType;
                params.set('productType', productType);

                const cloudRaw = sentinelCloudInput?.value?.trim();
                const cloudNumber = cloudRaw === '' || cloudRaw === undefined ? null : Number(cloudRaw);
                if (cloudNumber !== null && !Number.isNaN(cloudNumber)) {
                    const normalized = clampNumber(cloudNumber, 0, 100);
                    if (normalized !== null) {
                        params.set('cloudCover', `[0,${Math.round(normalized)}]`);
                    }
                }

                const latRaw = sentinelLatInput?.value?.trim();
                const lonRaw = sentinelLonInput?.value?.trim();
                const hasLat = latRaw !== undefined && latRaw !== '';
                const hasLon = lonRaw !== undefined && lonRaw !== '';

                if ((hasLat && !hasLon) || (!hasLat && hasLon)) {
                    sentinelStatus.classList.remove('hidden');
                    sentinelStatus.textContent = 'Please provide both latitude and longitude to filter by location.';
                    sentinelList.innerHTML = '';
                    return;
                }

                if (hasLat && hasLon) {
                    const latNumber = Number(latRaw);
                    const lonNumber = Number(lonRaw);
                    const normalizedLat = clampNumber(latNumber, -90, 90);
                    const normalizedLon = clampNumber(lonNumber, -180, 180);

                    if (normalizedLat === null || normalizedLon === null) {
                        sentinelStatus.classList.remove('hidden');
                        sentinelStatus.textContent = 'Latitude must be between -90 and 90 and longitude between -180 and 180.';
                        sentinelList.innerHTML = '';
                        return;
                    }

                    params.set('lat', normalizedLat.toFixed(6));
                    params.set('lon', normalizedLon.toFixed(6));
                }

                const requestUrl = `${sentinelCatalogEndpoint}?${params.toString()}`;

                try {
                    const response = await fetchSentinelCatalog(requestUrl);
                    const features = Array.isArray(response?.features) ? response.features : [];

                    if (!features.length) {
                        sentinelStatus.textContent = 'No Sentinel-2 collections found for the selected date range.';
                    } else {
                        sentinelStatus.classList.add('hidden');
                        features.forEach(feature => {
                            const card = createSentinelCard(feature);
                            if (card) {
                                sentinelList.appendChild(card);
                            }
                        });
                    }

                    sentinelLoadedOnce = true;

                    if (sentinelLastUpdated) {
                        sentinelLastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }

                    if (features.length && forceRefresh && window.MyZkToast?.success) {
                        window.MyZkToast.success('Sentinel-2 collections updated.');
                    }
                } catch (error) {
                    const message = error?.message ? ` (${error.message})` : '';
                    sentinelStatus.classList.remove('hidden');
                    sentinelStatus.textContent = `Unable to fetch Sentinel-2 collections${message}. Please try again later.`;

                    if (sentinelLastUpdated) {
                        sentinelLastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }

                    if (window.MyZkToast?.error) {
                        window.MyZkToast.error('Failed to update Sentinel-2 collections.');
                    }
                }
            }

            if (sentinelCloudInput && sentinelCloudInput.value === '') {
                sentinelCloudInput.value = defaultCloudCoverMax;
            }

            if (sentinelLatInput && sentinelLatInput.value === '') {
                sentinelLatInput.value = defaultLatitude;
            }

            if (sentinelLonInput && sentinelLonInput.value === '') {
                sentinelLonInput.value = defaultLongitude;
            }

            if (sentinelLevelInput && sentinelLevelInput.value === '') {
                sentinelLevelInput.value = defaultProductType;
            }

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

            sentinelCloudInput?.addEventListener('blur', () => normalizeInputValue(sentinelCloudInput, 0, 100));
            sentinelLatInput?.addEventListener('blur', () => normalizeInputValue(sentinelLatInput, -90, 90));
            sentinelLonInput?.addEventListener('blur', () => normalizeInputValue(sentinelLonInput, -180, 180));

            if (sentinelFilterForm) {
                sentinelFilterForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    loadSentinelCollections(true);
                });
            }

            if (sentinelFilterResetButton) {
                sentinelFilterResetButton.addEventListener('click', () => {
                    if (sentinelCloudInput) sentinelCloudInput.value = defaultCloudCoverMax;
                    applySentinelDefaultDates(true);
                    if (sentinelLatInput) sentinelLatInput.value = defaultLatitude;
                    if (sentinelLonInput) sentinelLonInput.value = defaultLongitude;
                    if (sentinelLevelInput) sentinelLevelInput.value = defaultProductType;
                    loadSentinelCollections(true);
                });
            }
        </script>
    @endpush
</x-app-front-map-layout>

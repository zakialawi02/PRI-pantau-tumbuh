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
            <section class="flex hidden h-full flex-col shadow-xl" id="sentinel-panel" data-sentinel-token="{{ $copernicusAccessToken ?? '' }}" data-sentinel-credentials="{{ ($copernicusCredentialsConfigured ?? false) ? 'true' : 'false' }}">
                <div class="bg-background border-foreground/10 sticky top-0 z-20 flex items-center justify-between border-b p-2">
                    <h2 class="text-lg font-bold">🛰️ Sentinel-2 Collections</h2>
                    <button class="hover:bg-foreground/20 bg-foreground/10 rounded px-2 py-1 text-sm" onclick="closePanels()">✖</button>
                </div>

                <div class="panel-content flex-1 space-y-3 overflow-y-auto p-2">
                    <form class="bg-background/60 border-foreground/10 rounded-lg border p-2.5 shadow-sm" id="sentinelFilterForm">
                        <div class="mb-1.5 flex items-center justify-between">
                            <h3 class="text-foreground text-sm font-semibold">Filter Collections</h3>
                            <button class="text-foreground/60 hover:text-primary text-xs font-medium transition" id="sentinelFilterResetButton" type="button">
                                Reset
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelCloudFilter">
                                <span>Max Cloud Cover (%)</span>
                                <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring" id="sentinelCloudFilter" name="cloud-cover" type="number" value="40" max="100" min="0" placeholder="e.g. 30" step="1" />
                            </label>
                            <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelLatFilter">
                                <span>Latitude</span>
                                <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring" id="sentinelLatFilter" name="latitude" type="number" value="-1.24536" max="90" min="-90" placeholder="e.g. -6.2" step="0.000001" />
                            </label>
                            <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelLonFilter">
                                <span>Longitude</span>
                                <input class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring" id="sentinelLonFilter" name="longitude" type="number" value="114.54535" max="180" min="-180" placeholder="e.g. 106.8" step="0.000001" />
                            </label>
                            <label class="text-foreground/80 flex flex-col space-y-0.5 text-xs font-medium" for="sentinelProductLevel">
                                <span>Product Level</span>
                                <select class="border-foreground/20 bg-background focus:border-primary focus:ring-primary/30 w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring" id="sentinelProductLevel" name="product-level">
                                    <option value="S2MSI2A" selected>Level-2A (Surface Reflectance)</option>
                                    <option value="S2MSI1C">Level-1C (Top-of-Atmosphere)</option>
                                </select>
                            </label>
                        </div>
                        <p class="text-foreground/60 mt-1.5 text-[11px]">Provide both latitude and longitude to focus on a specific location, or clear both fields to search globally.</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button class="bg-primary hover:bg-primary/90 text-background inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold transition" type="submit">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                    <div class="mt-4 space-y-1.5 rounded-xl border border-foreground/10 bg-background/60 p-3 text-xs">
                        @if ($copernicusAccessToken)
                            <p class="text-foreground/70 text-[11px] leading-snug">
                                Full-scene downloads use a Copernicus access token requested automatically from the configured client credentials. Tokens rotate roughly every hour; ensure the credentials remain active to keep downloads available.
                            </p>
                        @elseif (!empty($copernicusCredentialsConfigured))
                            <p class="text-foreground text-xs font-semibold uppercase tracking-wide">Copernicus token unavailable</p>
                            <p class="text-foreground/70 mt-0.5 text-[11px] leading-snug">
                                The application could not exchange the configured client credentials for an access token. Verify the client ID and secret on the server and check the logs for additional details.
                            </p>
                        @else
                            <p class="text-foreground text-xs font-semibold uppercase tracking-wide">Copernicus access token missing</p>
                            <p class="text-foreground/70 mt-0.5 text-[11px] leading-snug">
                                Set the <code>COPERNICUS_CLIENT_ID</code> and <code>COPERNICUS_CLIENT_SECRET</code> environment variables to enable Sentinel-2 scene downloads.
                            </p>
                        @endif
                    </div>

                    <div class="text-foreground/70 mt-4 text-sm" id="sentinelCollectionStatus">
                        Loading latest Sentinel-2 acquisitions...
                    </div>
                    <div class="space-y-2.5" id="sentinelCollectionList"></div>
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
                    <div class="sentinel-card border-foreground/20 bg-background/60 flex flex-col rounded-xl border p-3 shadow-sm transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start space-x-3">
                            <div class="border-foreground/10 bg-muted text-foreground/50 flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border" data-sentinel-thumb>
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
                        <div class="mt-3 flex flex-wrap gap-2" data-sentinel-actions>
                            <a
                                class="bg-primary text-background hover:bg-primary/90 hidden inline-flex items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition"
                                data-sentinel-download
                                href="#"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-disabled="true"
                            >
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
                <div class="pointer-events-auto hidden w-full max-w-md rounded-xl border border-foreground/15 bg-background/95 p-3 text-xs shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/70" id="sentinelPreviewPanel">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-primary">Sentinel-2 Preview</p>
                            <p class="text-foreground text-sm font-semibold leading-tight" data-sentinel-preview-title>–</p>
                            <p class="text-foreground/70 text-xs leading-tight" data-sentinel-preview-acquired>Select a collection to preview on the map.</p>
                            <p class="text-foreground/60 text-xs leading-tight hidden" data-sentinel-preview-details></p>
                        </div>
                        <button class="text-foreground/50 transition hover:text-foreground" id="sentinelPreviewClearBtn" type="button">
                            <i class="ri-close-line text-base"></i>
                            <span class="sr-only">Clear Sentinel preview</span>
                        </button>
                    </div>
                    <p class="text-foreground/70 mt-2 text-xs" data-sentinel-preview-status>Awaiting preview selection.</p>
                    <div class="mt-2 flex flex-wrap gap-1.5" id="sentinelPreviewActions">
                        <a
                            class="bg-primary text-background hover:bg-primary/90 hidden inline-flex items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition"
                            id="sentinelPreviewDownloadBtn"
                            href="#"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-disabled="true"
                        >
                            <i class="ri-download-cloud-2-line text-sm"></i>
                            <span>Download Scene</span>
                        </a>
                        <button class="bg-primary text-background hover:bg-primary/90 inline-flex items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" id="sentinelPreviewHideBtn" type="button">
                            <i class="ri-eye-off-line text-sm"></i>
                            <span>Hide Preview</span>
                        </button>
                        <button class="bg-primary text-background hover:bg-primary/90 hidden inline-flex items-center space-x-1 rounded-lg px-2 py-1 text-xs font-semibold transition" id="sentinelPreviewShowBtn" type="button">
                            <i class="ri-eye-line text-sm"></i>
                            <span>Unhide Preview</span>
                        </button>
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
        document.addEventListener('DOMContentLoaded', () => {
            const uploader = createImageryUploader();
            if (uploader) {
                uploader.init();
            }
            initTabFunctionality();
        });

        function createImageryUploader() {
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
            };

            const ready = Object.values(elements).every(Boolean);

            if (!ready) {
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
                });
                return null;
            }

            const toast = window.MyZkToast ?? {};
            const state = {
                paused: false,
                uploading: false,
                file: null,
                uploadId: null,
                currentChunk: 0,
                totalChunks: 0,
                uploadedBytes: 0,
                startTime: null,
            };
            const chunkSize = 5 * 1024 * 1024;
            const defaultProgressMessage = elements.progressText?.textContent?.trim() || 'Ready for next upload.';
            let resetTimer = null;

            const notify = (type, message) => {
                if (typeof toast[type] === 'function') {
                    toast[type](message);
                }
            };

            const setButtonState = (status) => {
                if (!elements.startBtn || !elements.pauseBtn || !elements.resumeBtn) {
                    return;
                }

                const config = {
                    idle: [true, true, true],
                    ready: [false, true, true],
                    uploading: [true, false, true],
                    paused: [true, true, false],
                    merging: [true, true, true],
                    done: [true, true, true],
                    error: [false, true, true],
                };

                const [startDisabled, pauseDisabled, resumeDisabled] = config[status] ?? config.idle;
                elements.startBtn.disabled = startDisabled;
                elements.pauseBtn.disabled = pauseDisabled;
                elements.resumeBtn.disabled = resumeDisabled;
            };

            const updateProgressBar = (progress) => {
                if (!elements.progressBar) return;
                const safeProgress = Math.min(Math.max(progress, 0), 100);
                elements.progressBar.style.width = `${safeProgress}%`;
            };

            const updateProgressText = (text) => {
                if (elements.progressText) {
                    elements.progressText.textContent = text;
                }
            };

            const resetFileInfo = () => {
                elements.fileInput.value = '';
                elements.fileInfo.classList.add('hidden');
                elements.fileInfo.innerHTML = '';
            };

            const resetUploader = (message = defaultProgressMessage) => {
                state.paused = false;
                state.uploading = false;
                state.file = null;
                state.uploadId = null;
                state.currentChunk = 0;
                state.totalChunks = 0;
                state.uploadedBytes = 0;
                state.startTime = null;
                resetFileInfo();
                updateProgressBar(0);
                updateProgressText(message);
                setButtonState('idle');
            };

            const scheduleReset = () => {
                window.clearTimeout(resetTimer);
                resetTimer = window.setTimeout(() => resetUploader(), 4000);
            };

            const renderSelectedFile = (selectedFile) => {
                if (!selectedFile) {
                    resetUploader();
                    return;
                }

                const sizeMB = (selectedFile.size / 1024 / 1024).toFixed(2);
                const shortName = shortenFilename(selectedFile.name, 40);

                elements.fileInfo.classList.remove('hidden');
                elements.fileInfo.innerHTML = `
            <strong>Name:</strong> ${shortName}<br>
            <strong>Size:</strong> ${sizeMB} MB
        `;

                updateProgressText("✅ File ready to upload. Click 'Start Upload' to begin.");
                updateProgressBar(0);
                notify('info', 'File ready to upload, click Start to begin.');
                setButtonState('ready');
            };

            const beginUploadSession = () => {
                state.uploadId = Math.random().toString(36).substring(2, 12);
                state.totalChunks = Math.ceil(state.file.size / chunkSize);
                state.currentChunk = 0;
                state.uploadedBytes = 0;
                state.paused = false;
                state.uploading = true;
                state.startTime = performance.now();

                notify('info', '🚀 Upload started...');
                updateProgressText(`🚀 Uploading ${state.file.name}...`);
                setButtonState('uploading');
                uploadNextChunk();
            };

            const handleFileSelection = (event) => {
                const selectedFile = event.target.files?.[0] ?? null;
                state.file = selectedFile;

                if (!selectedFile) {
                    resetUploader();
                    return;
                }

                renderSelectedFile(selectedFile);
            };

            const handleStartUpload = () => {
                if (!state.file) {
                    notify('warning', 'Please select a file first!');
                    return;
                }

                beginUploadSession();
            };

            const handlePauseUpload = () => {
                if (!state.uploading) return;
                state.paused = true;
                state.uploading = false;
                updateProgressText('⏸️ Upload paused.');
                notify('warning', 'Upload paused.');
                setButtonState('paused');
            };

            const handleResumeUpload = () => {
                if (!state.file) return;
                state.paused = false;
                state.uploading = true;
                updateProgressText('▶️ Upload resumed...');
                notify('info', 'Upload resumed...');
                setButtonState('uploading');
                uploadNextChunk();
            };

            const uploadNextChunk = async (retryCount = 0) => {
                if (state.paused || !state.file) return;

                if (state.currentChunk >= state.totalChunks) {
                    updateProgressText('🧩 Merging file on server...');
                    return mergeChunks();
                }

                const start = state.currentChunk * chunkSize;
                const end = Math.min(state.file.size, start + chunkSize);
                const chunk = state.file.slice(start, end);
                const chunkSizeBytes = end - start;

                const formData = new FormData();
                formData.append('upload_id', state.uploadId);
                formData.append('chunk_index', state.currentChunk);
                formData.append('chunk', chunk);

                try {
                    const res = await fetch('{{ route('upload.chunk') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: formData,
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || `Chunk ${state.currentChunk} failed.`);
                    }

                    state.currentChunk += 1;
                    state.uploadedBytes += chunkSizeBytes;

                    const elapsedSec = Math.max((performance.now() - state.startTime) / 1000, 0.001);
                    const speedMBps = state.uploadedBytes / 1024 / 1024 / elapsedSec;
                    const remainingBytes = state.file.size - state.uploadedBytes;
                    const etaSeconds = speedMBps > 0 ? remainingBytes / (speedMBps * 1024 * 1024) : 0;
                    const etaText = etaSeconds > 0 ? formatTimeETA(etaSeconds) : '-';

                    const progress = Math.round((state.currentChunk / state.totalChunks) * 100);
                    updateProgressBar(progress);
                    updateProgressText(`Uploading... ${progress}% | 🚀 ${speedMBps.toFixed(2)} MB/s | ⏳ ETA: ${etaText}`);

                    if (progress === 100) {
                        notify('info', 'Merging file on server...');
                    }

                    if (!state.paused) {
                        uploadNextChunk();
                    }
                } catch (err) {
                    if (retryCount < 3) {
                        const delay = 2000 * (retryCount + 1);
                        window.setTimeout(() => uploadNextChunk(retryCount + 1), delay);
                    } else {
                        updateProgressText(`❌ Chunk ${state.currentChunk} failed after 3 retries. Upload paused.`);
                        notify('error', `Chunk ${state.currentChunk} failed after 3 retries.`);
                        state.paused = true;
                        state.uploading = false;
                        setButtonState('paused');
                    }
                }
            };

            const mergeChunks = async () => {
                setButtonState('merging');

                const formData = new FormData();
                formData.append('upload_id', state.uploadId);
                formData.append('filename', state.file?.name ?? '');
                formData.append('total_chunks', state.totalChunks);
                formData.append('source_type', elements.sourceInput?.value ?? '');

                try {
                    const res = await fetch('{{ route('upload.merge') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: formData,
                    });

                    const result = await res.json();

                    if (res.ok && result.success) {
                        updateProgressBar(100);
                        updateProgressText(`✅ Upload complete! ${result.message || 'Upload completed. Processing started in background.'}`);
                        notify('success', result.message || 'Upload completed successfully!');
                        state.uploading = false;
                        setButtonState('done');
                        await loadMyData();
                        scheduleReset();
                    } else {
                        throw new Error(result.message || 'Failed to merge file on server.');
                    }
                } catch (err) {
                    updateProgressText(`❌ Error: ${err.message}`);
                    notify('error', err.message || 'Server error during merge.');
                    state.uploading = false;
                    setButtonState('error');
                    scheduleReset();
                }
            };

            const bindEvents = () => {
                elements.fileInput.addEventListener('change', handleFileSelection);
                elements.startBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleStartUpload();
                });
                elements.pauseBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    handlePauseUpload();
                });
                elements.resumeBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleResumeUpload();
                });
            };

            function renderImageryCard(item, template) {
                if (!template) return null;
                const clone = template.content.cloneNode(true);
                const card = clone.querySelector('.imagery-card');
                if (!card) return null;

                const formatEl = card.querySelector('.imagery-format');
                if (formatEl && item.format) {
                    formatEl.textContent = item.format.slice(0, 3).toUpperCase();
                }

                const nameEl = card.querySelector('.imagery-name');
                if (nameEl) {
                    nameEl.textContent = shortenFilename(item.original_name, 25);
                }

                const metaEl = card.querySelector('.imagery-meta');
                if (metaEl) {
                    const sizeText = (item.size / 1024 / 1024).toFixed(2);
                    const dateText = new Date(item.uploaded_at).toLocaleDateString();
                    const status = item.processing_status ?? 'unknown';
                    const statusClass = status === 'done' ? 'text-success' : 'text-warning';
                    metaEl.innerHTML = `${sizeText} MB • ${dateText} • <span class="imagery-status ${statusClass}">${status}</span>`;
                }

                const viewBtn = card.querySelector('.view-btn');
                if (viewBtn) {
                    viewBtn.addEventListener('click', () => viewImagery(item));
                }

                return clone;
            }

            async function loadMyData() {
                if (!elements.myDataContainer) return;
                elements.myDataContainer.innerHTML = `
            <div class="flex justify-center py-4">
                <p class="text-sm text-foreground/60 animate-pulse">Loading your imagery list...</p>
            </div>
        `;

                try {
                    const res = await fetch('{{ route('imagery.list') }}');
                    const result = await res.json();

                    if (!res.ok || !result.success) {
                        throw new Error(result.message || 'Failed to fetch imagery data.');
                    }

                    const data = Array.isArray(result.data) ? result.data : [];
                    elements.myDataContainer.innerHTML = '';

                    if (!data.length) {
                        elements.myDataContainer.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No imagery uploaded yet.</p>';
                        return;
                    }

                    const template = document.getElementById('imageryCardTemplate');
                    data.forEach((item) => {
                        const fragment = renderImageryCard(item, template);
                        if (fragment) {
                            elements.myDataContainer.appendChild(fragment);
                        }
                    });
                } catch (err) {
                    elements.myDataContainer.innerHTML = `
                    <div class="text-sm text-red-500 bg-red-50 border border-red-200 rounded p-3">
                        ❌ ${err.message}
                    </div>
                `;
                }
            }

            return {
                init() {
                    resetUploader();
                    loadMyData();
                    bindEvents();
                },
            };
        }

        function initTabFunctionality() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    tabButtons.forEach((btn) => btn.classList.remove('active'));
                    tabContents.forEach((content) => content.classList.remove('active'));

                    button.classList.add('active');

                    const tabId = button.getAttribute('data-tab');
                    const content = document.getElementById(tabId);
                    if (content) {
                        content.classList.add('active');
                    }
                });
            });
        }
    </script>
@endpush


    
@push('javascript')
    <script>
        (function () {
            const defaults = {
                cloudCover: 40,
                latitude: -1.24536,
                longitude: 114.54535,
                productType: 'S2MSI2A',
                scrollAmount: 150,
            };

            const sentinelCatalogEndpoint = 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json';
            const sentinelDownloadIgnoredKeywords = ['quicklook', 'thumbnail', 'thumb', 'overview', 'browse', 'allorigins'];
            const sentinelPreviewIgnoredKeywords = ['thumbnail', 'thumb', 'legend', 'logo', 'browse', 'allorigins'];

            const toast = window.MyZkToast ?? {};
            const state = {
                sentinelLoadedOnce: false,
                sentinelDownloadToken: '',
                sentinelTokenConfigured: false,
                sentinelPreviewController: null,
            };

            const elements = {};
            let isDragging = false;
            let dragStartX = 0;
            let dragScrollLeft = 0;

            document.addEventListener('DOMContentLoaded', () => {
                cacheDomReferences();
                initializeSentinelToken();
                bindEventListeners();
                preparePreviewController();
                openDefaultPanel();
                if (!state.sentinelLoadedOnce) {
                    loadSentinelCollections();
                }
            });

            function cacheDomReferences() {
                elements.panelWrapper = document.getElementById('panel-wrapper');
                elements.panels = Array.from(document.querySelectorAll('#panel-wrapper section'));
                elements.sidebarButtons = Array.from(document.querySelectorAll('.sidebar-btn'));
                elements.scrollContainer = document.getElementById('scroll-container');
                elements.scrollLeftBtn = document.getElementById('scroll-left');
                elements.scrollRightBtn = document.getElementById('scroll-right');
                elements.sentinelStatus = document.getElementById('sentinelCollectionStatus');
                elements.sentinelList = document.getElementById('sentinelCollectionList');
                elements.sentinelTemplate = document.getElementById('sentinelCollectionTemplate');
                elements.sentinelLastUpdated = document.getElementById('sentinelLastUpdated');
                elements.sentinelFilterForm = document.getElementById('sentinelFilterForm');
                elements.sentinelFilterResetButton = document.getElementById('sentinelFilterResetButton');
                elements.sentinelCloudInput = document.getElementById('sentinelCloudFilter');
                elements.sentinelLatInput = document.getElementById('sentinelLatFilter');
                elements.sentinelLonInput = document.getElementById('sentinelLonFilter');
                elements.sentinelLevelInput = document.getElementById('sentinelProductLevel');
                elements.sentinelPanel = document.getElementById('sentinel-panel');
                elements.sentinelPreviewPanel = document.getElementById('sentinelPreviewPanel');
                elements.sentinelPreviewTitle = elements.sentinelPreviewPanel?.querySelector('[data-sentinel-preview-title]');
                elements.sentinelPreviewAcquired = elements.sentinelPreviewPanel?.querySelector('[data-sentinel-preview-acquired]');
                elements.sentinelPreviewDetails = elements.sentinelPreviewPanel?.querySelector('[data-sentinel-preview-details]');
                elements.sentinelPreviewStatus = elements.sentinelPreviewPanel?.querySelector('[data-sentinel-preview-status]');
                elements.sentinelPreviewHideBtn = document.getElementById('sentinelPreviewHideBtn');
                elements.sentinelPreviewShowBtn = document.getElementById('sentinelPreviewShowBtn');
                elements.sentinelPreviewClearBtn = document.getElementById('sentinelPreviewClearBtn');
                elements.sentinelPreviewDownloadBtn = document.getElementById('sentinelPreviewDownloadBtn');
                elements.buySatelliteBtn = document.getElementById('buySatelliteBtn');
                elements.buyingPanel = document.getElementById('buyingPanel');
                elements.buyingPanelCloseBtn = document.getElementById('buyingPanelCloseBtn');
            }

            function initializeSentinelToken() {
                const panel = elements.sentinelPanel;
                state.sentinelDownloadToken = sanitizeSentinelToken(panel?.dataset?.sentinelToken ?? '');
                state.sentinelTokenConfigured = (panel?.dataset?.sentinelCredentials ?? '').toLowerCase() === 'true';
            }

            function bindEventListeners() {
                setDefaultFilterValues();
                bindFilterForm();
                bindFilterReset();
                bindScrollControls();
                bindScrollDragging();
                bindPanelResizeSync();
                bindBuyingPanelControls();
                bindPreviewButtons();
            }

            function setDefaultFilterValues() {
                if (elements.sentinelCloudInput && elements.sentinelCloudInput.value === '') {
                    elements.sentinelCloudInput.value = defaults.cloudCover;
                }
                if (elements.sentinelLatInput && elements.sentinelLatInput.value === '') {
                    elements.sentinelLatInput.value = defaults.latitude;
                }
                if (elements.sentinelLonInput && elements.sentinelLonInput.value === '') {
                    elements.sentinelLonInput.value = defaults.longitude;
                }
                if (elements.sentinelLevelInput && elements.sentinelLevelInput.value === '') {
                    elements.sentinelLevelInput.value = defaults.productType;
                }
            }

            function bindFilterForm() {
                if (!elements.sentinelFilterForm) return;
                elements.sentinelFilterForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    loadSentinelCollections(true);
                });
            }

            function bindFilterReset() {
                if (!elements.sentinelFilterResetButton) return;
                elements.sentinelFilterResetButton.addEventListener('click', () => {
                    if (elements.sentinelCloudInput) elements.sentinelCloudInput.value = defaults.cloudCover;
                    if (elements.sentinelLatInput) elements.sentinelLatInput.value = defaults.latitude;
                    if (elements.sentinelLonInput) elements.sentinelLonInput.value = defaults.longitude;
                    if (elements.sentinelLevelInput) elements.sentinelLevelInput.value = defaults.productType;
                    loadSentinelCollections(true);
                });

                elements.sentinelCloudInput?.addEventListener('blur', () => normalizeInputValue(elements.sentinelCloudInput, 0, 100));
                elements.sentinelLatInput?.addEventListener('blur', () => normalizeInputValue(elements.sentinelLatInput, -90, 90));
                elements.sentinelLonInput?.addEventListener('blur', () => normalizeInputValue(elements.sentinelLonInput, -180, 180));
            }

            function bindScrollControls() {
                if (elements.scrollLeftBtn && elements.scrollContainer) {
                    elements.scrollLeftBtn.addEventListener('click', () => {
                        elements.scrollContainer.scrollBy({
                            left: -defaults.scrollAmount,
                            behavior: 'smooth',
                        });
                    });
                }

                if (elements.scrollRightBtn && elements.scrollContainer) {
                    elements.scrollRightBtn.addEventListener('click', () => {
                        elements.scrollContainer.scrollBy({
                            left: defaults.scrollAmount,
                            behavior: 'smooth',
                        });
                    });
                }
            }

            function bindScrollDragging() {
                if (!elements.scrollContainer) return;

                elements.scrollContainer.addEventListener('mousedown', (event) => {
                    isDragging = true;
                    elements.scrollContainer.classList.add('cursor-grabbing');
                    dragStartX = event.pageX - elements.scrollContainer.offsetLeft;
                    dragScrollLeft = elements.scrollContainer.scrollLeft;
                });

                elements.scrollContainer.addEventListener('mouseleave', stopDragging);
                elements.scrollContainer.addEventListener('mouseup', stopDragging);
                elements.scrollContainer.addEventListener('mousemove', (event) => {
                    if (!isDragging) return;
                    event.preventDefault();
                    const x = event.pageX - elements.scrollContainer.offsetLeft;
                    const walk = (x - dragStartX) * 2;
                    elements.scrollContainer.scrollLeft = dragScrollLeft - walk;
                });
            }

            function stopDragging() {
                if (!elements.scrollContainer) return;
                isDragging = false;
                elements.scrollContainer.classList.remove('cursor-grabbing');
            }

            function bindPanelResizeSync() {
                window.addEventListener('resize', handleResize);
            }

            function bindBuyingPanelControls() {
                elements.buySatelliteBtn?.addEventListener('click', () => {
                    elements.buyingPanel?.classList.remove('hidden');
                });
                elements.buyingPanelCloseBtn?.addEventListener('click', () => {
                    elements.buyingPanel?.classList.add('hidden');
                });
            }

            function bindPreviewButtons() {
                elements.sentinelPreviewHideBtn?.addEventListener('click', (event) => {
                    event.preventDefault();
                    createSentinelPreviewController()?.hideImage();
                });
                elements.sentinelPreviewShowBtn?.addEventListener('click', (event) => {
                    event.preventDefault();
                    createSentinelPreviewController()?.showImage();
                });
                elements.sentinelPreviewClearBtn?.addEventListener('click', (event) => {
                    event.preventDefault();
                    createSentinelPreviewController()?.clear();
                });
            }

            function preparePreviewController() {
                if (typeof window === 'undefined') {
                    return;
                }

                if (window.map) {
                    createSentinelPreviewController();
                } else {
                    window.addEventListener('map:ready', () => {
                        createSentinelPreviewController();
                    }, { once: true });
                }

                if (!createSentinelPreviewController()) {
                    elements.sentinelPreviewHideBtn?.classList.add('hidden');
                    elements.sentinelPreviewShowBtn?.classList.add('hidden');
                }

                window.showSentinelPreviewOnMap = triggerSentinelPreview;
            }

            function openDefaultPanel() {
                const defaultPanel = 'data-panel';
                const isMobile = window.innerWidth < 768;
                const selector = isMobile
                    ? `#scroll-container .sidebar-btn[onclick*='${defaultPanel}']`
                    : `aside .sidebar-btn[onclick*='${defaultPanel}']`;
                const defaultBtn = document.querySelector(selector);
                showPanel(defaultPanel, defaultBtn);
            }

            async function loadSentinelCollections(forceRefresh = false) {
                if (!elements.sentinelStatus || !elements.sentinelList) {
                    return;
                }

                if (forceRefresh && toast.info) {
                    toast.info('Refreshing Sentinel-2 catalogue...');
                }

                elements.sentinelStatus.classList.remove('hidden');
                elements.sentinelStatus.textContent = 'Fetching latest Sentinel-2 collections...';
                elements.sentinelList.innerHTML = '';

                const endDate = new Date();
                const startDate = new Date(endDate);
                startDate.setMonth(startDate.getMonth() - 1);
                startDate.setHours(0, 0, 0, 0);
                const endDateAdjusted = new Date(endDate);
                endDateAdjusted.setHours(23, 59, 59, 999);

                const params = new URLSearchParams({
                    startDate: formatISODate(startDate),
                    completionDate: formatISODate(endDateAdjusted),
                    maxRecords: '20',
                    sortParam: 'startDate',
                    sortOrder: 'descending',
                });

                const productTypeRaw = elements.sentinelLevelInput?.value?.trim();
                const productType = ['S2MSI2A', 'S2MSI1C'].includes(productTypeRaw) ? productTypeRaw : defaults.productType;
                params.set('productType', productType);

                const cloudRaw = elements.sentinelCloudInput?.value?.trim();
                const cloudNumber = cloudRaw === '' || cloudRaw === undefined ? null : Number(cloudRaw);
                if (cloudNumber !== null && !Number.isNaN(cloudNumber)) {
                    const normalized = clampNumber(cloudNumber, 0, 100);
                    if (normalized !== null) {
                        params.set('cloudCover', `[0,${Math.round(normalized)}]`);
                    }
                }

                const latRaw = elements.sentinelLatInput?.value?.trim();
                const lonRaw = elements.sentinelLonInput?.value?.trim();
                const hasLat = latRaw !== undefined && latRaw !== '';
                const hasLon = lonRaw !== undefined && lonRaw !== '';

                if ((hasLat && !hasLon) || (!hasLat && hasLon)) {
                    elements.sentinelStatus.classList.remove('hidden');
                    elements.sentinelStatus.textContent = 'Please provide both latitude and longitude to filter by location.';
                    elements.sentinelList.innerHTML = '';
                    return;
                }

                if (hasLat && hasLon) {
                    const latNumber = Number(latRaw);
                    const lonNumber = Number(lonRaw);
                    const normalizedLat = clampNumber(latNumber, -90, 90);
                    const normalizedLon = clampNumber(lonNumber, -180, 180);

                    if (normalizedLat === null || normalizedLon === null) {
                        elements.sentinelStatus.classList.remove('hidden');
                        elements.sentinelStatus.textContent = 'Latitude must be between -90 and 90 and longitude between -180 and 180.';
                        elements.sentinelList.innerHTML = '';
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
                        elements.sentinelStatus.textContent = 'No Sentinel-2 collections found in the last 30 days.';
                    } else {
                        elements.sentinelStatus.classList.add('hidden');
                        features.forEach((feature) => {
                            const card = createSentinelCard(feature);
                            if (card) {
                                elements.sentinelList.appendChild(card);
                            }
                        });
                    }

                    state.sentinelLoadedOnce = true;

                    if (elements.sentinelLastUpdated) {
                        elements.sentinelLastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }

                    if (features.length && forceRefresh && toast.success) {
                        toast.success('Sentinel-2 collections updated.');
                    }
                } catch (error) {
                    const message = error?.message ? ` (${error.message})` : '';
                    elements.sentinelStatus.classList.remove('hidden');
                    elements.sentinelStatus.textContent = `Unable to fetch Sentinel-2 collections${message}. Please try again later.`;

                    if (elements.sentinelLastUpdated) {
                        elements.sentinelLastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }

                    if (toast.error) {
                        toast.error('Failed to update Sentinel-2 collections.');
                    }
                }
            }

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
                    return attemptFetch(proxyUrl);
                }
            }

            function createSentinelCard(feature) {
                if (!elements.sentinelTemplate) return null;

                const clone = elements.sentinelTemplate.content.cloneNode(true);
                const card = clone.querySelector('[data-sentinel-card]');
                if (!card) return null;

                const props = feature?.properties ?? {};
                const links = feature?.links ?? [];
                const assets = feature?.assets ?? {};

                const title = props.title || props.productIdentifier || props.productTitle || 'Sentinel-2 Scene';
                const acquisitionDate = formatReadableDate(props.startDate || props.acquisitionDate || props.datetime);
                const cloudCover = formatCloudCover(props.cloudCoverPercentage ?? props.cloudCover);

                const badgeEl = card.querySelector('[data-sentinel-cloud]');
                if (badgeEl) {
                    badgeEl.textContent = cloudCover;
                }

                const titleEl = card.querySelector('[data-sentinel-title]');
                if (titleEl) {
                    titleEl.textContent = title;
                }

                const dateEl = card.querySelector('[data-sentinel-date]');
                if (dateEl) {
                    dateEl.textContent = acquisitionDate;
                }

                const locationEl = card.querySelector('[data-sentinel-location]');
                if (locationEl) {
                    locationEl.textContent = props.location || props.productRegion || props.origin || 'Location unavailable';
                }

                const previewBtn = card.querySelector('[data-sentinel-preview]');
                const downloadBtn = card.querySelector('[data-sentinel-download]');

                const downloadUrl = resolveSentinelDownloadUrl(feature);
                const previewPayload = {
                    productId: props.id || props.productIdentifier || props.productId || props.title,
                    title: title,
                    acquisitionDate: acquisitionDate,
                    details: buildSentinelStatusMessage(`Cloud cover: ${cloudCover}`),
                    feature,
                    downloadUrl: applySentinelTokenToUrl(downloadUrl) || downloadUrl,
                    downloadUrlBase: downloadUrl,
                    downloadFilename: buildSentinelDownloadName(props.productIdentifier || props.id || title, title),
                    quicklookUrl: Array.isArray(props.quicklook) ? props.quicklook.find((item) => typeof item === 'string') : props.quicklook,
                };

                if (previewBtn) {
                    previewBtn.addEventListener('click', (event) => {
                        event.preventDefault();
                        triggerSentinelPreview(previewPayload);
                    });
                }

                if (downloadBtn) {
                    if (previewPayload.downloadUrl) {
                        downloadBtn.href = previewPayload.downloadUrl;
                        downloadBtn.download = previewPayload.downloadFilename;
                        downloadBtn.textContent = 'Download';
                        downloadBtn.classList.remove('hidden');
                    } else {
                        downloadBtn.classList.add('hidden');
                    }
                }

                const quicklookEl = card.querySelector('[data-sentinel-quicklook]');
                const quicklookUrl = previewPayload.quicklookUrl;
                if (quicklookEl) {
                    if (quicklookUrl) {
                        quicklookEl.src = quicklookUrl;
                        quicklookEl.alt = `${title} quicklook`;
                    } else {
                        quicklookEl.classList.add('hidden');
                    }
                }

                return clone;
            }

            function triggerSentinelPreview(payload) {
                if (!payload) return;
                const controller = createSentinelPreviewController();
                if (!controller) {
                    console.warn('Sentinel preview controller is unavailable. Map is not ready yet.');
                    return;
                }
                controller.showPreview(payload);
            }

            function showPanel(id, btn = null) {
                const isMobile = window.innerWidth < 768;

                elements.panels.forEach((panel) => panel.classList.add('hidden'));
                const targetPanel = document.getElementById(id);
                targetPanel?.classList.remove('hidden');

                elements.sidebarButtons.forEach((button) => button.classList.remove('active'));
                if (btn) {
                    btn.classList.add('active');
                } else {
                    const matchedBtn = elements.sidebarButtons.find((button) => button.getAttribute('onclick')?.includes(id));
                    matchedBtn?.classList.add('active');
                }

                if (!elements.panelWrapper) {
                    return;
                }

                if (isMobile) {
                    elements.panelWrapper.classList.remove('translate-y-full');
                    elements.panelWrapper.classList.add('translate-y-0');
                    elements.panelWrapper.classList.remove('slide-down');
                    elements.panelWrapper.classList.add('slide-up');
                } else {
                    elements.panelWrapper.classList.remove('w-0', 'md:w-0');
                    elements.panelWrapper.classList.add('w-80', 'md:w-80');
                }

                elements.panelWrapper.dataset.activePanel = id;

                if (id === 'sentinel-panel' && !state.sentinelLoadedOnce) {
                    loadSentinelCollections();
                }
            }

            function closePanels() {
                const isMobile = window.innerWidth < 768;

                elements.panels.forEach((panel) => panel.classList.add('hidden'));
                elements.sidebarButtons.forEach((button) => button.classList.remove('active'));

                if (elements.panelWrapper) {
                    delete elements.panelWrapper.dataset.activePanel;

                    if (isMobile) {
                        elements.panelWrapper.classList.add('translate-y-full');
                        elements.panelWrapper.classList.remove('translate-y-0');
                    } else {
                        elements.panelWrapper.classList.remove('w-80', 'md:w-80');
                        elements.panelWrapper.classList.add('w-0', 'md:w-0');
                    }
                }
            }

            function handleResize() {
                if (!elements.panelWrapper) return;
                const isMobile = window.innerWidth < 768;
                const activePanel = elements.panelWrapper.dataset.activePanel;

                if (!activePanel) {
                    elements.panelWrapper.classList.add('translate-y-full');
                    elements.panelWrapper.classList.remove('translate-y-0', 'w-80', 'md:w-80');
                    return;
                }

                elements.panelWrapper.classList.remove('slide-up', 'slide-down');

                if (isMobile) {
                    elements.panelWrapper.classList.remove('w-0', 'md:w-0', 'w-80', 'md:w-80');
                    elements.panelWrapper.classList.remove('translate-y-full');
                    elements.panelWrapper.classList.add('translate-y-0', 'opacity-100');
                } else {
                    elements.panelWrapper.classList.remove('translate-y-full', 'translate-y-0');
                    elements.panelWrapper.classList.add('w-80', 'md:w-80', 'opacity-100');
                }

                const selector = isMobile
                    ? `#scroll-container .sidebar-btn[onclick*='${activePanel}']`
                    : `aside .sidebar-btn[onclick*='${activePanel}']`;
                const activeBtn = document.querySelector(selector);

                elements.sidebarButtons.forEach((button) => button.classList.remove('active'));
                activeBtn?.classList.add('active');
            }

            function normalizeInputValue(input, min, max) {
                if (!input) return;
                const parsed = Number(input.value);
                if (Number.isNaN(parsed)) {
                    input.value = '';
                    return;
                }
                const normalized = clampNumber(parsed, min, max);
                if (normalized !== null) {
                    input.value = normalized.toString();
                }
            }

            function formatISODate(date) {
                if (!(date instanceof Date)) return '';
                const copy = new Date(date.getTime());
                copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());
                return copy.toISOString().split('T')[0];
            }

            function formatReadableDate(value) {
                if (!value) return 'Unknown date';
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) return value;
                return `${parsed.toLocaleString('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                    timeZone: 'UTC',
                })} UTC`;
            }

            function formatCloudCover(value) {
                if (typeof value === 'number' && !Number.isNaN(value)) {
                    return `${value.toFixed(1)}%`;
                }
                return 'N/A';
            }

            function clampNumber(value, min, max) {
                if (typeof value !== 'number' || Number.isNaN(value)) return null;
                return Math.min(Math.max(value, min), max);
            }

            function buildSentinelDownloadName(primary, fallback) {
                const base = String(primary ?? fallback ?? 'sentinel-2-scene').trim();
                const normalized = base.replace(/[\s]+/g, '_').replace(/[^A-Za-z0-9._-]+/g, '_');
                return normalized || 'sentinel-2-scene';
            }

            function sanitizeSentinelToken(value) {
                if (typeof value !== 'string') return '';
                return value.trim();
            }

            function hasSentinelToken() {
                return sanitizeSentinelToken(state.sentinelDownloadToken).length > 0;
            }

            function buildSentinelStatusMessage(message) {
                const parts = [];
                if (message) parts.push(message);
                if (!hasSentinelToken()) {
                    parts.push(
                        state.sentinelTokenConfigured
                            ? 'Download unavailable: Copernicus access token could not be issued.'
                            : 'Configure Copernicus credentials on the server to enable downloads.',
                    );
                }
                return parts.join(' ');
            }

            function applySentinelTokenToUrl(url) {
                if (!url) return null;
                const token = sanitizeSentinelToken(state.sentinelDownloadToken);
                if (!token) return null;
                try {
                    const baseHref = typeof window !== 'undefined' && window.location ? window.location.href : 'https://example.com/';
                    const parsed = new URL(url, baseHref);
                    parsed.searchParams.set('token', token);
                    return parsed.toString();
                } catch (error) {
                    const queryPattern = /([?&])token=[^&#]*/i;
                    if (queryPattern.test(url)) {
                        return url.replace(queryPattern, `$1token=${encodeURIComponent(token)}`);
                    }
                    const separator = url.includes('?') ? '&' : '?';
                    return `${url}${separator}token=${encodeURIComponent(token)}`;
                }
            }

            function isLikelyWmsUrl(url) {
                if (typeof url !== 'string') return false;
                const trimmed = url.trim();
                if (!trimmed) return false;
                const lowered = trimmed.toLowerCase();
                if (sentinelPreviewIgnoredKeywords.some((keyword) => lowered.includes(keyword))) {
                    return false;
                }
                const hasProtocol = /^https?:\/\//i.test(trimmed);
                const looksRelative = trimmed.startsWith('/') || trimmed.startsWith('./') || trimmed.startsWith('../');
                const mentionsWms = lowered.includes('service=wms')
                    || lowered.includes('request=getmap')
                    || lowered.includes('/wms')
                    || lowered.includes('ogc/wms')
                    || lowered.includes('/ows');
                if (!hasProtocol && !looksRelative && !mentionsWms) {
                    return false;
                }
                return mentionsWms;
            }

            function normalizeWmsCandidate(url) {
                if (!isLikelyWmsUrl(url)) return null;
                try {
                    const baseHref = typeof window !== 'undefined' && window.location
                        ? window.location.href
                        : 'https://example.com/';
                    const parsed = new URL(url, baseHref);
                    const baseUrl = `${parsed.origin}${parsed.pathname}`;
                    const params = {};
                    parsed.searchParams.forEach((value, key) => {
                        if (value === undefined || value === null || value === '') return;
                        const lower = key.toLowerCase();
                        if (lower === 'token') return;
                        if (['bbox', 'width', 'height', 'x', 'y', 'lat', 'lon'].includes(lower)) return;
                        if (lower === 'request' || lower === 'service') return;
                        if (lower === 'layers' || lower === 'layer') {
                            params.LAYERS = value;
                            return;
                        }
                        if (lower === 'styles' || lower === 'style') {
                            params.STYLES = value;
                            return;
                        }
                        params[key.toUpperCase()] = value;
                    });

                    if (!params.LAYERS) {
                        const fallbackLayer = parsed.searchParams.get('LAYERS') || parsed.searchParams.get('layers');
                        if (fallbackLayer) {
                            params.LAYERS = fallbackLayer;
                        }
                    }

                    const version = parsed.searchParams.get('VERSION') || parsed.searchParams.get('version') || null;

                    return {
                        url: baseUrl,
                        params,
                        version,
                        originalUrl: url,
                    };
                } catch (error) {
                    console.warn('Unable to normalise WMS candidate', url, error);
                    return null;
                }
            }

            function collectWmsServiceCandidates(input, context = {}) {
                const results = [];
                const seen = new Set();

                const visit = (value, ctx) => {
                    if (!value) return;

                    if (typeof value === 'string') {
                        const candidate = value.trim();
                        if (!candidate || !isLikelyWmsUrl(candidate)) return;
                        const normalizedContext = {
                            layers: ctx.layers ?? null,
                            styles: ctx.styles ?? null,
                            version: ctx.version ?? null,
                        };

                        const key = `${candidate}|${normalizedContext.layers ?? ''}|${normalizedContext.styles ?? ''}|${normalizedContext.version ?? ''}`;
                        if (seen.has(key)) return;
                        results.push({ url: candidate, ...normalizedContext });
                        seen.add(key);
                        return;
                    }

                    if (Array.isArray(value)) {
                        value.forEach((item) => visit(item, ctx));
                        return;
                    }

                    if (typeof value === 'object') {
                        let layers = ctx.layers ?? null;
                        let styles = ctx.styles ?? null;
                        let version = ctx.version ?? null;

                        Object.entries(value).forEach(([rawKey, rawValue]) => {
                            const key = String(rawKey).toLowerCase();
                            if (key === 'layers' || key === 'layer' || key.includes('layer')) {
                                if (typeof rawValue === 'string' && rawValue.trim()) {
                                    layers = rawValue.trim();
                                } else if (Array.isArray(rawValue)) {
                                    const joined = rawValue
                                        .map((item) => (typeof item === 'string' ? item.trim() : ''))
                                        .filter(Boolean)
                                        .join(',');
                                    if (joined) {
                                        layers = joined;
                                    }
                                }
                            } else if (key === 'styles' || key === 'style' || key.includes('style')) {
                                if (typeof rawValue === 'string' && rawValue.trim()) {
                                    styles = rawValue.trim();
                                } else if (Array.isArray(rawValue)) {
                                    const joined = rawValue
                                        .map((item) => (typeof item === 'string' ? item.trim() : ''))
                                        .filter(Boolean)
                                        .join(',');
                                    if (joined) {
                                        styles = joined;
                                    }
                                }
                            } else if (key === 'version') {
                                if (typeof rawValue === 'string' && rawValue.trim()) {
                                    version = rawValue.trim();
                                }
                            }
                        });

                        Object.entries(value).forEach(([rawKey, rawValue]) => {
                            if (typeof rawValue === 'string') {
                                visit(rawValue, { layers, styles, version });
                            } else {
                                visit(rawValue, { layers, styles, version });
                            }
                        });
                    }
                };

                visit(input, { ...context });

                return results;
            }

            function resolveSentinelWmsConfig(data) {
                if (!data) return null;

                const candidates = new Map();
                const registerCandidate = (candidate, score = 0) => {
                    if (!candidate || !candidate.url) return;
                    const key = `${candidate.url}|${candidate.params?.LAYERS ?? ''}|${candidate.params?.STYLES ?? ''}`;
                    const existing = candidates.get(key);
                    if (!existing || score > existing.score) {
                        candidates.set(key, { ...candidate, score });
                    }
                };

                const registerFromValue = (value, score = 0) => {
                    if (!value) return;
                    collectWmsServiceCandidates(value).forEach((entry) => {
                        const candidate = normalizeWmsCandidate(entry.url);
                        if (!candidate) return;
                        const params = { ...(candidate.params ?? {}) };
                        if (!params.LAYERS && entry.layers) {
                            params.LAYERS = entry.layers;
                        }
                        if (!params.STYLES && entry.styles) {
                            params.STYLES = entry.styles;
                        }
                        if (Object.keys(params).length) {
                            candidate.params = params;
                        }
                        if (!candidate.version && entry.version) {
                            candidate.version = entry.version;
                        }
                        registerCandidate(candidate, score);
                    });
                };

                registerFromValue(data.wms, 96);
                registerFromValue(data.previewWms, 94);
                registerFromValue(data.wmts, 80);

                const props = data.featureProperties ?? data.properties ?? {};
                registerFromValue(props?.services, 92);

                if (props && typeof props === 'object') {
                    Object.entries(props).forEach(([key, value]) => {
                        const lowered = String(key).toLowerCase();
                        if (lowered.includes('wms') || lowered.includes('ogc') || lowered.includes('ows')) {
                            registerFromValue(value, lowered.includes('wms') ? 95 : 85);
                        }
                    });
                }

                const services = data.services ?? props?.services;
                if (services && typeof services === 'object') {
                    Object.entries(services).forEach(([key, value]) => {
                        const lowered = String(key).toLowerCase();
                        let score = 84;
                        if (lowered.includes('wms')) score = 98;
                        else if (lowered.includes('ogc') || lowered.includes('ows')) score = 90;
                        registerFromValue(value, score);
                    });
                } else {
                    registerFromValue(services, 82);
                }

                const links = Array.isArray(data.links) ? data.links : props?.links;
                if (Array.isArray(links)) {
                    links.forEach((link) => {
                        const href = typeof link?.href === 'string' ? link.href : null;
                        if (!href) return;
                        const rel = typeof link?.rel === 'string' ? link.rel.toLowerCase() : '';
                        const type = typeof link?.type === 'string' ? link.type.toLowerCase() : '';
                        let score = 70;
                        if (rel.includes('wms') || rel.includes('ogc')) score += 20;
                        if (type.includes('wms')) score += 18;
                        registerFromValue(href, score);
                    });
                }

                const assets = data.assets ?? props?.assets;
                if (assets && typeof assets === 'object') {
                    Object.entries(assets).forEach(([key, value]) => {
                        const lowered = String(key).toLowerCase();
                        if (sentinelPreviewIgnoredKeywords.some((keyword) => lowered.includes(keyword))) {
                            return;
                        }
                        if (lowered.includes('wms') || lowered.includes('ogc') || lowered.includes('ows')) {
                            registerFromValue(value, lowered.includes('wms') ? 90 : 78);
                        }
                    });
                }

                if (!candidates.size) {
                    return null;
                }

                const sorted = Array.from(candidates.values()).sort((a, b) => b.score - a.score);
                const best = sorted[0];
                if (!best || !best.url) return null;

                if (!best.params?.LAYERS) {
                    const fallbackLayer = data.productId || data.tileId || props?.productIdentifier || props?.title || null;
                    if (fallbackLayer) {
                        best.params = { ...(best.params ?? {}), LAYERS: fallbackLayer };
                    }
                }

                if (!best.params?.LAYERS) {
                    return null;
                }

                return best;
            }

            function isValidSentinelDownloadUrl(url) {
                if (typeof url !== 'string') return false;
                const trimmed = url.trim();
                if (!trimmed) return false;
                if (!/^https?:\/\//i.test(trimmed)) return false;
                const lowered = trimmed.toLowerCase();
                return !sentinelDownloadIgnoredKeywords.some((keyword) => lowered.includes(keyword));
            }

            function extractServiceUrls(service) {
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
            }

            function resolveSentinelDownloadUrl(feature) {
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
                    self: 65,
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
                    const href = typeof assetValue === 'string'
                        ? assetValue
                        : (typeof assetValue?.href === 'string'
                            ? assetValue.href
                            : (typeof assetValue?.url === 'string' ? assetValue.url : null));
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
            }

            function createSentinelPreviewController() {
                if (state.sentinelPreviewController) {
                    return state.sentinelPreviewController;
                }

                if (typeof window === 'undefined' || typeof ol === 'undefined') {
                    return null;
                }

                const mapInstance = window.map;
                const mapProjection = mapInstance?.getView?.()?.getProjection?.();
                if (!mapInstance || !mapProjection) {
                    return null;
                }

                const dataProjection = 'EPSG:4326';
                const geoJsonParser = new ol.format.GeoJSON();

                const bboxSource = new ol.source.Vector();
                const bboxLayer = new ol.layer.Vector({
                    source: bboxSource,
                    style: new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: 'rgba(59,130,246,0.9)',
                            width: 2,
                            lineDash: [6, 6],
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(59,130,246,0.15)',
                        }),
                    }),
                    visible: false,
                    zIndex: 1200,
                });

                const previewLayer = new ol.layer.Image({
                    visible: false,
                    opacity: 0.8,
                    zIndex: 1150,
                });

                mapInstance.addLayer(previewLayer);
                mapInstance.addLayer(bboxLayer);

                const controllerState = {
                    current: null,
                    hasImage: false,
                    imageHidden: false,
                    previewType: null,
                };

                const setPanelContent = ({ title, acquired, details, status }) => {
                    if (title !== undefined && elements.sentinelPreviewTitle) {
                        elements.sentinelPreviewTitle.textContent = title;
                    }
                    if (acquired !== undefined && elements.sentinelPreviewAcquired) {
                        elements.sentinelPreviewAcquired.textContent = acquired;
                    }
                    if (details !== undefined && elements.sentinelPreviewDetails) {
                        elements.sentinelPreviewDetails.textContent = details;
                        elements.sentinelPreviewDetails.classList.toggle('hidden', !details);
                    }
                    if (status !== undefined && elements.sentinelPreviewStatus) {
                        elements.sentinelPreviewStatus.textContent = status;
                    }
                };

                const setPanelVisible = (visible) => {
                    if (elements.sentinelPreviewPanel) {
                        elements.sentinelPreviewPanel.classList.toggle('hidden', !visible);
                    }
                };

                const updateButtons = () => {
                    const hasSelection = Boolean(controllerState.current);
                    const canToggleImage = hasSelection && controllerState.hasImage;

                    if (elements.sentinelPreviewHideBtn) {
                        elements.sentinelPreviewHideBtn.disabled = !canToggleImage || controllerState.imageHidden;
                        elements.sentinelPreviewHideBtn.textContent = controllerState.imageHidden ? 'Preview hidden' : 'Hide preview';
                    }

                    if (elements.sentinelPreviewShowBtn) {
                        elements.sentinelPreviewShowBtn.disabled = !canToggleImage || !controllerState.imageHidden;
                        elements.sentinelPreviewShowBtn.textContent = controllerState.imageHidden ? 'Unhide Preview' : 'Preview Visible';
                    }

                    if (elements.sentinelPreviewClearBtn) {
                        elements.sentinelPreviewClearBtn.disabled = !hasSelection;
                    }

                    if (elements.sentinelPreviewDownloadBtn) {
                        const downloadUrl = hasSelection ? controllerState.current?.downloadUrl : null;
                        if (downloadUrl) {
                            const label = controllerState.current?.productId || controllerState.current?.baseTitle || 'Sentinel-2 scene';
                            const downloadName = controllerState.current?.downloadFilename
                                || buildSentinelDownloadName(controllerState.current?.productId, controllerState.current?.baseTitle);

                            elements.sentinelPreviewDownloadBtn.classList.remove('hidden');
                            elements.sentinelPreviewDownloadBtn.setAttribute('href', downloadUrl);
                            elements.sentinelPreviewDownloadBtn.setAttribute('aria-disabled', 'false');
                            elements.sentinelPreviewDownloadBtn.setAttribute('title', `Download full scene for ${label}`);
                            elements.sentinelPreviewDownloadBtn.setAttribute('download', downloadName);
                            elements.sentinelPreviewDownloadBtn.dataset.downloadBase = controllerState.current?.downloadUrlBase || '';
                            elements.sentinelPreviewDownloadBtn.tabIndex = 0;
                        } else {
                            elements.sentinelPreviewDownloadBtn.classList.add('hidden');
                            elements.sentinelPreviewDownloadBtn.removeAttribute('href');
                            elements.sentinelPreviewDownloadBtn.removeAttribute('download');
                            elements.sentinelPreviewDownloadBtn.setAttribute('aria-disabled', 'true');
                            delete elements.sentinelPreviewDownloadBtn.dataset.downloadBase;
                            elements.sentinelPreviewDownloadBtn.tabIndex = -1;
                        }
                    }
                };

                const clearPreviewLayer = () => {
                    previewLayer.setSource(null);
                    previewLayer.setVisible(false);
                    controllerState.previewType = null;
                };

                const focusExtent = (extent) => {
                    if (!extent) return;
                    try {
                        const view = mapInstance.getView();
                        if (view && typeof view.fit === 'function') {
                            view.fit(extent, { padding: [50, 50, 50, 50], duration: 500, maxZoom: 14 });
                        }
                    } catch (error) {
                        console.warn('Unable to focus extent', error);
                    }
                };

                const applyStaticPreview = (url, extent) => {
                    if (!url || !extent) return false;
                    try {
                        const imageSource = new ol.source.ImageStatic({
                            url,
                            imageExtent: extent,
                            projection: mapProjection,
                        });
                        previewLayer.setSource(imageSource);
                        previewLayer.setVisible(true);
                        controllerState.previewType = 'static';
                        return true;
                    } catch (error) {
                        console.warn('Failed to apply static preview', error);
                        return false;
                    }
                };

                const applyWmsPreview = (config, { onLoad, onError } = {}) => {
                    if (!config || !config.url || !config.params?.LAYERS) return false;
                    try {
                        const wmsSource = new ol.source.ImageWMS({
                            url: config.url,
                            params: config.params,
                            ratio: 1,
                            serverType: 'geoserver',
                            crossOrigin: 'anonymous',
                        });

                        wmsSource.on('imageloadend', () => {
                            if (typeof onLoad === 'function') onLoad();
                        });
                        wmsSource.on('imageloaderror', () => {
                            if (typeof onError === 'function') onError();
                        });

                        previewLayer.setSource(wmsSource);
                        previewLayer.setVisible(true);
                        controllerState.previewType = 'wms';
                        return true;
                    } catch (error) {
                        console.warn('Failed to apply WMS preview', error);
                        if (typeof onError === 'function') onError();
                        return false;
                    }
                };

                const controller = {
                    showPreview(data) {
                        if (!data) return;

                        let featureGeoJson = null;
                        let extent = null;
                        try {
                            featureGeoJson = data.feature?.geometry ?? data.geometry ?? null;
                            if (featureGeoJson) {
                                const feature = geoJsonParser.readFeature(featureGeoJson, {
                                    dataProjection,
                                    featureProjection: mapProjection,
                                });
                                bboxSource.clear();
                                bboxSource.addFeature(feature);
                                bboxLayer.setVisible(true);
                                extent = feature.getGeometry()?.getExtent() ?? null;
                                focusExtent(extent);
                            } else {
                                bboxLayer.setVisible(false);
                            }
                        } catch (error) {
                            console.warn('Failed to parse Sentinel geometry', error);
                            bboxLayer.setVisible(false);
                        }

                        const title = data.title || data.productId || 'Sentinel-2 Scene';
                        const acquiredText = data.acquisitionDate || 'Unknown date';
                        const detailText = data.details || '';

                        const baseDownloadUrl = data.downloadUrlBase || data.downloadUrl || null;
                        const resolvedDownloadUrl = applySentinelTokenToUrl(baseDownloadUrl);

                        controllerState.current = {
                            ...data,
                            extent,
                            baseTitle: title,
                            baseAcquired: acquiredText,
                            baseDetails: detailText,
                            downloadUrlBase: baseDownloadUrl,
                            downloadUrl: resolvedDownloadUrl,
                            downloadFilename: data.downloadFilename,
                        };

                        const wmsConfig = resolveSentinelWmsConfig(controllerState.current);
                        controllerState.current.wmsConfig = wmsConfig;

                        const hasWmsCandidate = Boolean(wmsConfig);
                        const hasQuicklook = Boolean(data.quicklookUrl && extent);

                        const initialStatus = hasWmsCandidate
                            ? 'Loading Sentinel-2 scene via WMS...'
                            : (hasQuicklook
                                ? 'Loading preview image...'
                                : 'Preview image not available. Showing coverage.');

                        setPanelContent({
                            title,
                            acquired: acquiredText,
                            details: detailText,
                            status: buildSentinelStatusMessage(initialStatus),
                        });

                        const renderQuicklookPreview = () => {
                            if (!hasQuicklook) return false;
                            setPanelContent({
                                status: buildSentinelStatusMessage('Loading preview image...'),
                            });
                            const loader = new Image();
                            loader.crossOrigin = 'anonymous';
                            loader.onload = () => {
                                if (!controllerState.current) return;
                                const applied = applyStaticPreview(data.quicklookUrl, extent);
                                controllerState.hasImage = applied;
                                controllerState.imageHidden = false;
                                updateButtons();
                                setPanelContent({
                                    status: buildSentinelStatusMessage(
                                        applied
                                            ? (controllerState.imageHidden
                                                ? 'Preview image loaded. Use "Unhide Preview" to display it.'
                                                : 'Preview image displayed on the map.')
                                            : 'Unable to load preview image. Showing coverage only.',
                                    ),
                                });
                            };
                            loader.onerror = () => {
                                clearPreviewLayer();
                                controllerState.hasImage = false;
                                controllerState.imageHidden = false;
                                controllerState.previewType = null;
                                updateButtons();
                                setPanelContent({
                                    status: buildSentinelStatusMessage('Unable to load preview image. Showing coverage only.'),
                                });
                            };
                            loader.src = data.quicklookUrl;
                            return true;
                        };

                        if (!extent) {
                            setPanelContent({
                                status: buildSentinelStatusMessage('Coverage area unavailable for this product.'),
                            });
                        }

                        if (hasWmsCandidate) {
                            const applied = applyWmsPreview(wmsConfig, {
                                onLoad: () => {
                                    if (!controllerState.current) return;
                                    controllerState.hasImage = true;
                                    updateButtons();
                                    setPanelContent({
                                        status: buildSentinelStatusMessage(
                                            controllerState.imageHidden
                                                ? 'Preview imagery loaded. Use "Unhide Preview" to display it.'
                                                : 'Preview imagery displayed on the map.',
                                        ),
                                    });
                                },
                                onError: () => {
                                    clearPreviewLayer();
                                    controllerState.hasImage = false;
                                    controllerState.previewType = null;
                                    updateButtons();
                                    if (!renderQuicklookPreview()) {
                                        setPanelContent({
                                            status: buildSentinelStatusMessage('Unable to load WMS preview imagery. Showing coverage only.'),
                                        });
                                    }
                                },
                            });

                            if (applied) {
                                controllerState.hasImage = true;
                                controllerState.imageHidden = false;
                                updateButtons();
                                setPanelVisible(true);
                                return;
                            }
                        }

                        if (renderQuicklookPreview()) {
                            setPanelVisible(true);
                            return;
                        }

                        clearPreviewLayer();
                        controllerState.hasImage = false;
                        controllerState.imageHidden = false;
                        controllerState.previewType = null;
                        updateButtons();
                        setPanelContent({
                            status: buildSentinelStatusMessage('Preview image not available. Showing coverage.'),
                        });
                        setPanelVisible(true);
                    },
                    hideImage() {
                        if (!controllerState.current || !controllerState.hasImage) return;
                        controllerState.imageHidden = true;
                        previewLayer.setVisible(false);
                        updateButtons();
                        setPanelContent({
                            status: buildSentinelStatusMessage('Preview hidden. Bounding box remains visible.'),
                        });
                    },
                    showImage() {
                        if (!controllerState.current || !controllerState.hasImage) return;
                        controllerState.imageHidden = false;
                        previewLayer.setVisible(true);
                        updateButtons();
                        setPanelContent({
                            status: buildSentinelStatusMessage('Preview image visible.'),
                        });
                    },
                    clear() {
                        controllerState.current = null;
                        controllerState.hasImage = false;
                        controllerState.imageHidden = false;
                        bboxSource.clear();
                        bboxLayer.setVisible(false);
                        clearPreviewLayer();
                        updateButtons();
                        setPanelVisible(false);
                    },
                };

                elements.sentinelPreviewHideBtn?.classList.remove('hidden');
                elements.sentinelPreviewShowBtn?.classList.remove('hidden');
                updateButtons();

                state.sentinelPreviewController = controller;
                return controller;
            }

            window.showPanel = showPanel;
            window.closePanels = closePanels;
            window.calculateTotalPrice = calculateTotalPrice;

            function calculateTotalPrice() {
                const areaInSquareMeters = window.geojsonArea || 0;
                const areaInHectares = areaInSquareMeters / 10000;
                const creditPointsNeeded = areaInHectares * {{ config('app-constants.imagery_credit_cost_per_hectare') }};

                const totalPriceElement = document.getElementById('total_price');
                const priceContainer = totalPriceElement?.parentElement;

                if (!totalPriceElement || !priceContainer) {
                    return;
                }

                if (areaInHectares > 0) {
                    totalPriceElement.innerHTML = `
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-green-700">${formatNumber(creditPointsNeeded.toFixed(2))} Credit Points</span>
                        <i class="ri-coins-line font-base text-success text-xl"></i>
                    </div>
                    <div class="text-xs text-foreground-70 mt-1">
                        ${formatNumber(areaInHectares)} hectares × {{ Number::format(config('app-constants.imagery_credit_cost_per_hectare'), locale: app()->getLocale()) }} credit points/hectare
                    </div>
                `;
                    priceContainer.classList.remove('bg-muted/60', 'border-muted');
                    priceContainer.classList.add('bg-green-50', 'border-green-300', 'shadow-sm');
                    priceContainer.style.transform = 'scale(1.02)';
                    window.setTimeout(() => {
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

                priceContainer.style.transition = 'all 0.3s ease-in-out';
            }
        })();
    </script>
@endpush

</x-app-front-map-layout>

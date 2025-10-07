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
                            ImageryUploader.init();
                            Tabs.init();
                        });

                        const ImageryUploader = (() => {
                            const elements = {
                                source: null,
                                file: null,
                                info: null,
                                progressBar: null,
                                progressText: null,
                                start: null,
                                pause: null,
                                resume: null,
                                list: null
                            };

                            const state = {
                                file: null,
                                uploadId: null,
                                currentChunk: 0,
                                totalChunks: 0,
                                paused: false,
                                uploading: false,
                                startTime: 0,
                                uploadedBytes: 0
                            };

                            const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

                            const toast = {
                                info: (message) => window.MyZkToast?.info?.(message),
                                success: (message) => window.MyZkToast?.success?.(message),
                                warning: (message) => window.MyZkToast?.warning?.(message),
                                error: (message) => window.MyZkToast?.error?.(message)
                            };

                            const selectors = {
                                source: '#sourceType',
                                file: '#fileInput',
                                info: '#fileInfo',
                                progressBar: '#progressBar',
                                progressText: '#progressText',
                                start: '#startBtn',
                                pause: '#pauseBtn',
                                resume: '#resumeBtn',
                                list: '#myDataContainer'
                            };

                            function init() {
                                cacheElements();
                                if (!isReady()) {
                                    logMissingElements();
                                    return;
                                }

                                resetUi();
                                bindEvents();
                                loadImageryList();
                            }

                            function cacheElements() {
                                Object.entries(selectors).forEach(([key, selector]) => {
                                    elements[key] = document.querySelector(selector);
                                });
                            }

                            function isReady() {
                                return Object.values(elements).every(Boolean);
                            }

                            function logMissingElements() {
                                const missing = Object.entries(elements)
                                    .filter(([, value]) => !value)
                                    .map(([key]) => key);

                                console.warn('Imagery uploader controls missing. Skipping uploader bootstrap.', { missing });
                            }

                            function bindEvents() {
                                elements.file.addEventListener('change', handleFileSelection);
                                elements.start.addEventListener('click', startUpload);
                                elements.pause.addEventListener('click', pauseUpload);
                                elements.resume.addEventListener('click', resumeUpload);
                            }

                            function handleFileSelection(event) {
                                state.file = event.target.files?.[0] ?? null;
                                if (!state.file) {
                                    resetUi();
                                    return;
                                }

                                const sizeMB = (state.file.size / 1024 / 1024).toFixed(2);
                                const displayName = shortenFilename(state.file.name, 40);

                                elements.info.classList.remove('hidden');
                                elements.info.innerHTML = `
                                    <strong>Name:</strong> ${displayName}<br>
                                    <strong>Size:</strong> ${sizeMB} MB
                                `;

                                setProgressMessage("✅ File ready to upload. Click 'Start Upload' to begin.");
                                updateProgressBar(0);
                                toast.info?.('File ready to upload, click Start to begin.');
                                setButtonState('ready');
                            }

                            function startUpload() {
                                if (!state.file) {
                                    toast.warning?.('Please select a file first!');
                                    return;
                                }

                                state.uploadId = Math.random().toString(36).slice(2, 12);
                                state.totalChunks = Math.ceil(state.file.size / CHUNK_SIZE);
                                state.currentChunk = 0;
                                state.uploadedBytes = 0;
                                state.paused = false;
                                state.uploading = true;
                                state.startTime = performance.now();

                                toast.info?.('🚀 Upload started...');
                                setProgressMessage(`🚀 Uploading ${state.file.name}...`);
                                setButtonState('uploading');
                                uploadNextChunk();
                            }

                            function pauseUpload() {
                                if (!state.uploading) return;
                                state.paused = true;
                                state.uploading = false;
                                setProgressMessage('⏸️ Upload paused.');
                                toast.warning?.('Upload paused.');
                                setButtonState('paused');
                            }

                            function resumeUpload() {
                                if (!state.file) return;
                                state.paused = false;
                                state.uploading = true;
                                setProgressMessage('▶️ Upload resumed...');
                                toast.info?.('Upload resumed...');
                                setButtonState('uploading');
                                uploadNextChunk();
                            }

                            async function uploadNextChunk(retryCount = 0) {
                                if (state.paused || !state.file) return;

                                if (state.currentChunk >= state.totalChunks) {
                                    setProgressMessage('🧩 Merging file on server...');
                                    await mergeChunks();
                                    return;
                                }

                                const start = state.currentChunk * CHUNK_SIZE;
                                const end = Math.min(state.file.size, start + CHUNK_SIZE);
                                const chunk = state.file.slice(start, end);
                                const chunkSizeBytes = end - start;

                                const payload = new FormData();
                                payload.append('upload_id', state.uploadId);
                                payload.append('chunk_index', state.currentChunk);
                                payload.append('chunk', chunk);

                                try {
                                    const response = await fetch('{{ route('upload.chunk') }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: payload
                                    });

                                    const result = await response.json();
                                    if (!response.ok || !result.success) {
                                        throw new Error(result.message || `Chunk ${state.currentChunk} failed.`);
                                    }

                                    state.currentChunk += 1;
                                    state.uploadedBytes += chunkSizeBytes;

                                    updateProgress();

                                    if (!state.paused) {
                                        uploadNextChunk();
                                    }
                                } catch (error) {
                                    if (retryCount < 3) {
                                        const retryDelay = 2000 * (retryCount + 1);
                                        setTimeout(() => uploadNextChunk(retryCount + 1), retryDelay);
                                        return;
                                    }

                                    setProgressMessage(`❌ Chunk ${state.currentChunk} failed after 3 retries. Upload paused.`);
                                    toast.error?.(`Chunk ${state.currentChunk} failed after 3 retries.`);
                                    state.paused = true;
                                    state.uploading = false;
                                    setButtonState('paused');
                                }
                            }

                            function updateProgress() {
                                const now = performance.now();
                                const elapsedSeconds = (now - state.startTime) / 1000;
                                const speedMBps = elapsedSeconds > 0
                                    ? (state.uploadedBytes / 1024 / 1024 / elapsedSeconds).toFixed(2)
                                    : '0.00';

                                const remainingBytes = state.file.size - state.uploadedBytes;
                                const etaSeconds = Number(speedMBps) > 0
                                    ? remainingBytes / (Number(speedMBps) * 1024 * 1024)
                                    : 0;
                                const etaText = etaSeconds > 0 ? formatTimeETA(etaSeconds) : '-';

                                const progress = Math.round((state.currentChunk / state.totalChunks) * 100);
                                updateProgressBar(progress);
                                setProgressMessage(`Uploading... ${progress}% | 🚀 ${speedMBps} MB/s | ⏳ ETA: ${etaText}`);

                                if (progress === 100) {
                                    toast.info?.('Merging file on server...');
                                }
                            }

                            async function mergeChunks() {
                                setButtonState('merging');

                                const payload = new FormData();
                                payload.append('upload_id', state.uploadId);
                                payload.append('filename', state.file.name);
                                payload.append('total_chunks', state.totalChunks);
                                payload.append('source_type', elements.source.value);

                                try {
                                    const response = await fetch('{{ route('upload.merge') }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: payload
                                    });

                                    const result = await response.json();
                                    if (!response.ok || !result.success) {
                                        throw new Error(result.message || 'Failed to merge file on server.');
                                    }

                                    updateProgressBar(100);
                                    const message = result.message || 'Upload completed. Processing started in background.';
                                    setProgressMessage(`✅ Upload complete! ${message}`);
                                    toast.success?.(result.message || 'Upload completed successfully!');
                                    setButtonState('done');

                                    await loadImageryList();
                                    scheduleReset();
                                } catch (error) {
                                    setProgressMessage(`❌ Error: ${error.message}`);
                                    toast.error?.(error.message || 'Server error during merge.');
                                    setButtonState('error');
                                    scheduleReset();
                                }
                            }

                            function scheduleReset() {
                                setTimeout(resetUi, 4000);
                            }

                            function resetUi() {
                                state.file = null;
                                elements.file.value = '';
                                elements.info?.classList.add('hidden');
                                updateProgressBar(0);
                                setProgressMessage('Ready for next upload.');
                                setButtonState('idle');
                            }

                            async function loadImageryList() {
                                if (!elements.list) return;

                                elements.list.innerHTML = `
                                    <div class="flex justify-center py-4">
                                        <p class="text-sm text-foreground/60 animate-pulse">Loading your imagery list...</p>
                                    </div>
                                `;

                                try {
                                    const response = await fetch('{{ route('imagery.list') }}');
                                    const result = await response.json();

                                    if (!response.ok || !result.success) {
                                        throw new Error(result.message || 'Failed to fetch imagery data.');
                                    }

                                    renderImageryCards(result.data ?? []);
                                } catch (error) {
                                    elements.list.innerHTML = `
                                        <div class="text-sm text-red-500 bg-red-50 border border-red-200 rounded p-3">
                                            ❌ ${error.message}
                                        </div>
                                    `;
                                }
                            }

                            function renderImageryCards(items) {
                                elements.list.innerHTML = '';

                                if (!items.length) {
                                    elements.list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No imagery uploaded yet.</p>';
                                    return;
                                }

                                const template = document.getElementById('imageryCardTemplate');
                                if (!template) return;

                                items.forEach((item) => {
                                    const clone = template.content.cloneNode(true);
                                    const card = clone.querySelector('.imagery-card');
                                    if (!card) return;

                                    const format = card.querySelector('.imagery-format');
                                    const name = card.querySelector('.imagery-name');
                                    const meta = card.querySelector('.imagery-meta');
                                    const status = card.querySelector('.imagery-status');
                                    const viewButton = card.querySelector('.view-btn');

                                    if (format) format.textContent = (item.format || '').slice(0, 3).toUpperCase();
                                    if (name) name.textContent = shortenFilename(item.original_name, 25);

                                    const sizeText = (item.size / 1024 / 1024).toFixed(2);
                                    const dateText = new Date(item.uploaded_at).toLocaleDateString();
                                    const statusText = item.processing_status;

                                    if (status) {
                                        status.textContent = statusText;
                                        status.classList.toggle('text-success', statusText === 'done');
                                        status.classList.toggle('text-warning', statusText !== 'done');
                                    }

                                    if (meta && status) {
                                        meta.innerHTML = `${sizeText} MB • ${dateText} • <span class="${status.className}">${status.textContent}</span>`;
                                    }

                                    viewButton?.addEventListener('click', () => viewImagery(item));

                                    elements.list.appendChild(clone);
                                });
                            }

                            function updateProgressBar(percent) {
                                if (elements.progressBar) {
                                    elements.progressBar.style.width = `${percent}%`;
                                }
                            }

                            function setProgressMessage(message) {
                                if (elements.progressText) {
                                    elements.progressText.textContent = message;
                                }
                            }

                            function setButtonState(stateName) {
                                const buttonStates = {
                                    idle: { start: true, pause: true, resume: true },
                                    ready: { start: false, pause: true, resume: true },
                                    uploading: { start: true, pause: false, resume: true },
                                    paused: { start: true, pause: true, resume: false },
                                    merging: { start: true, pause: true, resume: true },
                                    done: { start: true, pause: true, resume: true },
                                    error: { start: false, pause: true, resume: true }
                                };

                                const config = buttonStates[stateName] || buttonStates.idle;
                                elements.start.disabled = config.start;
                                elements.pause.disabled = config.pause;
                                elements.resume.disabled = config.resume;
                            }

                            return { init };
                        })();

                        const Tabs = (() => {
                            function init() {
                                const buttons = document.querySelectorAll('.tab-btn');
                                const contents = document.querySelectorAll('.tab-content');
                                if (!buttons.length || !contents.length) return;

                                buttons.forEach((button) => {
                                    button.addEventListener('click', () => {
                                        buttons.forEach((item) => item.classList.remove('active'));
                                        contents.forEach((item) => item.classList.remove('active'));

                                        button.classList.add('active');

                                        const targetId = button.getAttribute('data-tab');
                                        const target = document.getElementById(targetId);
                                        target?.classList.add('active');
                                    });
                                });
                            }

                            return { init };
                        })();
        </script>
    @endpush


    @push('javascript')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
            MapInterface.init();
        });

        const MapInterface = (() => {
            const dom = {
                panelWrapper: null,
                panels: [],
                sidebarButtons: [],
                scrollContainer: null,
                scrollLeft: null,
                scrollRight: null,
                sentinel: {
                    status: null,
                    list: null,
                    template: null,
                    lastUpdated: null,
                    filterForm: null,
                    resetButton: null,
                    cloudInput: null,
                    latInput: null,
                    lonInput: null,
                    levelInput: null,
                    panel: null,
                    previewPanel: null,
                    previewTitle: null,
                    previewAcquired: null,
                    previewDetails: null,
                    previewStatus: null,
                    hideBtn: null,
                    showBtn: null,
                    clearBtn: null,
                    downloadBtn: null
                }
            };

            const defaults = {
                scrollAmount: 150,
                cloudCover: 40,
                latitude: -1.24536,
                longitude: 114.54535,
                productType: 'S2MSI2A'
            };

            function init() {
                cacheDom();
                if (!dom.panelWrapper) return;

                Scrolling.init();
                SentinelCatalog.init();
                attachBuyPanelHandlers();
                openDefaultPanel();
                window.addEventListener('resize', handleResize);
            }

            function cacheDom() {
                dom.panelWrapper = document.getElementById('panel-wrapper');
                dom.panels = Array.from(document.querySelectorAll('#panel-wrapper section'));
                dom.sidebarButtons = Array.from(document.querySelectorAll('.sidebar-btn'));
                dom.scrollContainer = document.getElementById('scroll-container');
                dom.scrollLeft = document.getElementById('scroll-left');
                dom.scrollRight = document.getElementById('scroll-right');

                const sentinelPanel = document.getElementById('sentinel-panel');
                dom.sentinel.panel = sentinelPanel;
                dom.sentinel.status = document.getElementById('sentinelCollectionStatus');
                dom.sentinel.list = document.getElementById('sentinelCollectionList');
                dom.sentinel.template = document.getElementById('sentinelCollectionTemplate');
                dom.sentinel.lastUpdated = document.getElementById('sentinelLastUpdated');
                dom.sentinel.filterForm = document.getElementById('sentinelFilterForm');
                dom.sentinel.resetButton = document.getElementById('sentinelFilterResetButton');
                dom.sentinel.cloudInput = document.getElementById('sentinelCloudFilter');
                dom.sentinel.latInput = document.getElementById('sentinelLatFilter');
                dom.sentinel.lonInput = document.getElementById('sentinelLonFilter');
                dom.sentinel.levelInput = document.getElementById('sentinelProductLevel');

                const previewPanel = document.getElementById('sentinelPreviewPanel');
                dom.sentinel.previewPanel = previewPanel;
                dom.sentinel.previewTitle = previewPanel?.querySelector('[data-sentinel-preview-title]') ?? null;
                dom.sentinel.previewAcquired = previewPanel?.querySelector('[data-sentinel-preview-acquired]') ?? null;
                dom.sentinel.previewDetails = previewPanel?.querySelector('[data-sentinel-preview-details]') ?? null;
                dom.sentinel.previewStatus = previewPanel?.querySelector('[data-sentinel-preview-status]') ?? null;
                dom.sentinel.hideBtn = document.getElementById('sentinelPreviewHideBtn');
                dom.sentinel.showBtn = document.getElementById('sentinelPreviewShowBtn');
                dom.sentinel.clearBtn = document.getElementById('sentinelPreviewClearBtn');
                dom.sentinel.downloadBtn = document.getElementById('sentinelPreviewDownloadBtn');
            }

            function handleResize() {
                const { panelWrapper, sidebarButtons } = dom;
                if (!panelWrapper) return;

                const isMobile = window.innerWidth < 768;
                const activePanel = panelWrapper.dataset.activePanel;

                if (!activePanel) {
                    panelWrapper.classList.add('translate-y-full');
                    panelWrapper.classList.remove('translate-y-0', 'w-80', 'md:w-80');
                    return;
                }

                panelWrapper.classList.remove('slide-up', 'slide-down');

                if (isMobile) {
                    panelWrapper.classList.remove('w-0', 'md:w-0', 'w-80', 'md:w-80');
                    panelWrapper.classList.remove('translate-y-full');
                    panelWrapper.classList.add('translate-y-0', 'opacity-100');
                } else {
                    panelWrapper.classList.remove('translate-y-full', 'translate-y-0');
                    panelWrapper.classList.add('w-80', 'md:w-80', 'opacity-100');
                }

                const activeBtn = isMobile
                    ? document.querySelector(`#scroll-container .sidebar-btn[onclick*='${activePanel}']`)
                    : document.querySelector(`aside .sidebar-btn[onclick*='${activePanel}']`);

                sidebarButtons.forEach((button) => button.classList.remove('active'));
                activeBtn?.classList.add('active');
            }

            function openDefaultPanel() {
                const defaultPanelId = 'data-panel';
                const isMobile = window.innerWidth < 768;
                const defaultButton = isMobile
                    ? document.querySelector(`#scroll-container .sidebar-btn[onclick*='${defaultPanelId}']`)
                    : document.querySelector(`aside .sidebar-btn[onclick*='${defaultPanelId}']`);

                showPanel(defaultPanelId, defaultButton);
                SentinelCatalog.loadCollections();
            }

            function showPanel(id, button = null) {
                const { panelWrapper, panels, sidebarButtons } = dom;
                if (!panelWrapper) return;

                const isMobile = window.innerWidth < 768;

                panels.forEach((panel) => panel.classList.add('hidden'));
                const targetPanel = document.getElementById(id);
                targetPanel?.classList.remove('hidden');

                sidebarButtons.forEach((btn) => btn.classList.remove('active'));
                if (button) {
                    button.classList.add('active');
                } else {
                    const matchedBtn = sidebarButtons.find((btn) => btn.getAttribute('onclick')?.includes(id));
                    matchedBtn?.classList.add('active');
                }

                if (isMobile) {
                    panelWrapper.classList.remove('translate-y-full', 'slide-down');
                    panelWrapper.classList.add('translate-y-0', 'slide-up');
                } else {
                    panelWrapper.classList.remove('w-0', 'md:w-0');
                    panelWrapper.classList.add('w-80', 'md:w-80');
                }

                panelWrapper.dataset.activePanel = id;

                if (id === 'sentinel-panel') {
                    SentinelCatalog.loadCollections();
                }
            }

            function closePanels() {
                const { panelWrapper, panels, sidebarButtons } = dom;
                if (!panelWrapper) return;

                const isMobile = window.innerWidth < 768;

                panels.forEach((panel) => panel.classList.add('hidden'));
                sidebarButtons.forEach((btn) => btn.classList.remove('active'));
                delete panelWrapper.dataset.activePanel;

                if (isMobile) {
                    panelWrapper.classList.remove('slide-up');
                    panelWrapper.classList.add('slide-down');
                    setTimeout(() => {
                        panelWrapper.classList.remove('translate-y-0');
                        panelWrapper.classList.add('translate-y-full');
                    }, 480);
                } else {
                    panelWrapper.classList.remove('w-80', 'md:w-80');
                    panelWrapper.classList.add('w-0', 'md:w-0');
                }
            }

            function attachBuyPanelHandlers() {
                const buyButton = document.getElementById('buySatelliteBtn');
                const closeButton = document.getElementById('buyingPanelCloseBtn');
                const panel = document.getElementById('buyingPanel');

                buyButton?.addEventListener('click', () => panel?.classList.remove('hidden'));
                closeButton?.addEventListener('click', () => panel?.classList.add('hidden'));
            }

            function calculateTotalPrice() {
                const areaInSquareMeters = window.geojsonArea || 0;
                const areaInHectares = areaInSquareMeters / 10000;
                const creditPointsNeeded = areaInHectares * {{ config('app-constants.imagery_credit_cost_per_hectare') }};

                const totalPriceElement = document.getElementById('total_price');
                if (!totalPriceElement) return;
                const priceContainer = totalPriceElement.parentElement;

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
                    priceContainer?.classList.remove('bg-muted/60', 'border-muted');
                    priceContainer?.classList.add('bg-green-50', 'border-green-300', 'shadow-sm');
                    if (priceContainer) {
                        priceContainer.style.transform = 'scale(1.02)';
                        setTimeout(() => {
                            priceContainer.style.transform = 'scale(1)';
                        }, 200);
                    }
                } else {
                    totalPriceElement.innerHTML = `
                    <div class="flex items-center text-foreground/50">
                        <i class="ri-information-line mr-2"></i>
                        Draw an area to calculate credit points
                    </div>
                `;
                    priceContainer?.classList.remove('bg-green-50', 'border-green-300', 'shadow-sm', 'bg-amber-50', 'border-amber-300');
                    priceContainer?.classList.add('bg-muted/60', 'border-muted');
                }

                if (priceContainer) {
                    priceContainer.style.transition = 'all 0.3s ease-in-out';
                }
            }

            const Scrolling = (() => {
                function init() {
                    const { scrollContainer, scrollLeft, scrollRight } = dom;
                    if (!scrollContainer) return;

                    scrollLeft?.addEventListener('click', () => {
                        scrollContainer.scrollBy({ left: -defaults.scrollAmount, behavior: 'smooth' });
                    });

                    scrollRight?.addEventListener('click', () => {
                        scrollContainer.scrollBy({ left: defaults.scrollAmount, behavior: 'smooth' });
                    });

                    enableDragScrolling(scrollContainer);
                }

                function enableDragScrolling(container) {
                    let isMouseDown = false;
                    let startX = 0;
                    let scrollLeftStart = 0;

                    container.addEventListener('mousedown', (event) => {
                        isMouseDown = true;
                        container.classList.add('cursor-grabbing');
                        startX = event.pageX - container.offsetLeft;
                        scrollLeftStart = container.scrollLeft;
                    });

                    container.addEventListener('mouseleave', () => {
                        isMouseDown = false;
                        container.classList.remove('cursor-grabbing');
                    });

                    container.addEventListener('mouseup', () => {
                        isMouseDown = false;
                        container.classList.remove('cursor-grabbing');
                    });

                    container.addEventListener('mousemove', (event) => {
                        if (!isMouseDown) return;
                        event.preventDefault();
                        const x = event.pageX - container.offsetLeft;
                        const walk = (x - startX) * 2;
                        container.scrollLeft = scrollLeftStart - walk;
                    });
                }

                return { init };
            })();

            const SentinelCatalog = (() => {
                const config = {
                    endpoint: 'https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json'
                };

                const state = {
                    loadedOnce: false,
                    downloadToken: '',
                    tokenConfigured: false
                };

                function init() {
                    const sentinel = dom.sentinel;
                    if (!sentinel.panel) return;

                    state.downloadToken = sanitizeToken(sentinel.panel.dataset.sentinelToken ?? '');
                    state.tokenConfigured = (sentinel.panel.dataset.sentinelCredentials ?? '').toLowerCase() === 'true';

                    setDefaultFilterValues();
                    bindFilterEvents();
                    setupPreviewControls();
                    loadCollections();
                }

                function setDefaultFilterValues() {
                    const { cloudInput, latInput, lonInput, levelInput } = dom.sentinel;
                    if (cloudInput && cloudInput.value === '') cloudInput.value = defaults.cloudCover;
                    if (latInput && latInput.value === '') latInput.value = defaults.latitude;
                    if (lonInput && lonInput.value === '') lonInput.value = defaults.longitude;
                    if (levelInput && levelInput.value === '') levelInput.value = defaults.productType;
                }

                function bindFilterEvents() {
                    const sentinel = dom.sentinel;

                    const normalizeInput = (input, min, max) => {
                        if (!input) return;
                        if (input.value === '') return;
                        const parsed = Number(input.value);
                        if (Number.isNaN(parsed)) {
                            input.value = '';
                            return;
                        }
                        const clamped = clampNumber(parsed, min, max);
                        if (clamped !== null) {
                            input.value = clamped.toString();
                        }
                    };

                    sentinel.cloudInput?.addEventListener('blur', () => normalizeInput(sentinel.cloudInput, 0, 100));
                    sentinel.latInput?.addEventListener('blur', () => normalizeInput(sentinel.latInput, -90, 90));
                    sentinel.lonInput?.addEventListener('blur', () => normalizeInput(sentinel.lonInput, -180, 180));

                    sentinel.filterForm?.addEventListener('submit', (event) => {
                        event.preventDefault();
                        loadCollections(true);
                    });

                    sentinel.resetButton?.addEventListener('click', () => {
                        if (sentinel.cloudInput) sentinel.cloudInput.value = defaults.cloudCover;
                        if (sentinel.latInput) sentinel.latInput.value = defaults.latitude;
                        if (sentinel.lonInput) sentinel.lonInput.value = defaults.longitude;
                        if (sentinel.levelInput) sentinel.levelInput.value = defaults.productType;
                        loadCollections(true);
                    });
                }

                function setupPreviewControls() {
                    const sentinel = dom.sentinel;

                    sentinel.hideBtn?.addEventListener('click', (event) => {
                        event.preventDefault();
                        getPreviewController()?.setImageVisibility(true);
                    });

                    sentinel.showBtn?.addEventListener('click', (event) => {
                        event.preventDefault();
                        getPreviewController()?.setImageVisibility(false);
                    });

                    sentinel.clearBtn?.addEventListener('click', (event) => {
                        event.preventDefault();
                        getPreviewController()?.clear();
                    });

                    if (!getPreviewController()) {
                        sentinel.hideBtn?.classList.add('hidden');
                        sentinel.showBtn?.classList.add('hidden');
                    }

                    window.showSentinelPreviewOnMap = (feature) => {
                        if (feature) {
                            getPreviewController(feature)?.selectFeature(feature);
                        } else {
                            getPreviewController()?.clear();
                        }
                    };
                }

                function loadCollections(forceRefresh = false) {
                    const sentinel = dom.sentinel;
                    if (!sentinel.list || !sentinel.template) return;

                    sentinel.status?.classList.remove('hidden');
                    if (sentinel.status) sentinel.status.textContent = 'Fetching Sentinel-2 collections...';
                    sentinel.list.innerHTML = '';

                    fetch(buildQueryUrl())
                        .then((response) => response.ok ? response.json() : Promise.reject(new Error(`Status ${response.status}`)))
                        .catch(() => fetchViaProxy(buildQueryUrl()))
                        .then((data) => {
                            const features = data?.features ?? [];
                            renderCollections(features, forceRefresh);
                        })
                        .catch((error) => {
                            showErrorState(error);
                        });
                }

                function loadCollectionsIfNeeded() {
                    if (!state.loadedOnce) {
                        loadCollections();
                    }
                }

                function fetchViaProxy(url) {
                    const proxied = `https://api.allorigins.win/raw?url=${encodeURIComponent(url)}`;
                    return fetch(proxied).then((response) => {
                        if (!response.ok) throw new Error(`Status ${response.status}`);
                        return response.json();
                    });
                }

                function renderCollections(features, forceRefresh) {
                    const sentinel = dom.sentinel;
                    sentinel.list.innerHTML = '';

                    if (!features.length) {
                        sentinel.status?.classList.remove('hidden');
                        if (sentinel.status) {
                            sentinel.status.textContent = buildStatusMessage('No Sentinel-2 collections found for the selected filters.');
                        }
                    } else {
                        sentinel.status?.classList.add('hidden');
                        features.forEach((feature) => {
                            const card = buildCard(feature);
                            if (card) sentinel.list.appendChild(card);
                        });
                    }

                    if (sentinel.lastUpdated) {
                        sentinel.lastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }

                    if (features.length && forceRefresh) {
                        window.MyZkToast?.success?.('Sentinel-2 collections updated.');
                    }

                    state.loadedOnce = true;
                }

                function showErrorState(error) {
                    const sentinel = dom.sentinel;
                    const message = error?.message ? ` (${error.message})` : '';
                    sentinel.status?.classList.remove('hidden');
                    if (sentinel.status) {
                        sentinel.status.textContent = `Unable to fetch Sentinel-2 collections${message}. Please try again later.`;
                    }
                    if (sentinel.lastUpdated) {
                        sentinel.lastUpdated.textContent = new Date().toLocaleString('id-ID');
                    }
                    window.MyZkToast?.error?.('Failed to update Sentinel-2 collections.');
                }

                function buildQueryUrl() {
                    const sentinel = dom.sentinel;
                    const params = new URLSearchParams();

                    const cloudMax = Number(sentinel.cloudInput?.value ?? defaults.cloudCover);
                    const latitude = Number(sentinel.latInput?.value ?? defaults.latitude);
                    const longitude = Number(sentinel.lonInput?.value ?? defaults.longitude);
                    const productType = sentinel.levelInput?.value || defaults.productType;

                    if (!Number.isNaN(cloudMax)) params.set('maxCloudCover', cloudMax.toString());
                    if (!Number.isNaN(latitude) && !Number.isNaN(longitude)) {
                        params.set('lat', latitude.toString());
                        params.set('lon', longitude.toString());
                    }
                    if (productType) params.set('productType', productType);

                    const today = new Date();
                    const startDate = new Date();
                    startDate.setMonth(today.getMonth() - 3);

                    params.set('startDate', formatISODate(startDate));
                    params.set('completionDate', formatISODate(today));
                    params.set('dataset', 'S2');
                    params.set('processingLevel', productType);
                    params.set('maxRecords', '50');

                    return `${config.endpoint}?${params.toString()}`;
                }

                function buildCard(feature) {
                    const sentinel = dom.sentinel;
                    if (!sentinel.template) return null;

                    const clone = sentinel.template.content.cloneNode(true);
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

                    const shortenText = typeof window?.shortenFilename === 'function'
                        ? (value, max = 40) => window.shortenFilename(String(value), max)
                        : (value) => String(value ?? '');

                    const productId = props.productIdentifier || props.title || feature?.id || 'Sentinel-2 Product';
                    const acquisitionDate = props.completionDate || props.startDate || props.endPosition || props.beginPosition || props.startTimeFromAscendingNode;
                    const mgrsIdentifier = props.mgrsId || props.tileId || props.MGRS;
                    const tileText = mgrsIdentifier ? `Tile ${mgrsIdentifier}` : null;
                    const cloudCover = props.cloudCover ?? props['cloudcoverpercentage'] ?? props['cloudCoverageAssessment'];

                    const titleText = props.title || productId;
                    const productText = productId;

                    titleEl?.setAttribute('title', titleText);
                    productEl?.setAttribute('title', productText);
                    if (titleEl) titleEl.textContent = shortenText(titleText, 48);
                    if (productEl) productEl.textContent = shortenText(productText, 44);
                    if (datetimeEl) datetimeEl.textContent = `Acquired: ${formatReadableDate(acquisitionDate)}`;

                    const detailParts = [];
                    if (tileText) detailParts.push(tileText);
                    if (cloudCover !== undefined) detailParts.push(`Cloud cover: ${formatCloudCover(Number(cloudCover))}`);
                    if (props.collection) detailParts.push(`Collection: ${props.collection}`);
                    if (detailEl) {
                        detailEl.textContent = detailParts.length ? detailParts.join(' • ') : 'No additional metadata available';
                    }

                    const quicklookUrl = props.thumbnail
                        || props.quicklook
                        || assets?.thumbnail?.href
                        || assets?.overview?.href
                        || links.find((link) => link.rel === 'preview')?.href;

                    const downloadUrl = resolveDownloadUrl(feature);
                    const downloadUrlWithToken = applyToken(downloadUrl);
                    const downloadFilename = buildDownloadName(productId, titleText);
                    const tokenAvailable = hasToken();

                    if (downloadButton) {
                        if (downloadUrl && downloadUrlWithToken && tokenAvailable) {
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

                        downloadButton.addEventListener('click', (event) => {
                            if (!hasToken()) {
                                event.preventDefault();
                                event.stopPropagation();
                                window.MyZkToast?.warning?.('Copernicus access token is required before downloading.');
                            }
                        });
                    }

                    if (thumbnailImg) {
                        if (quicklookUrl) {
                            thumbnailImg.addEventListener('error', () => {
                                thumbnailImg.classList.add('hidden');
                                thumbnailImg.removeAttribute('src');
                                thumbnailPlaceholder?.classList.remove('hidden');
                            });
                            thumbnailImg.src = quicklookUrl;
                            thumbnailImg.classList.remove('hidden');
                            thumbnailPlaceholder?.classList.add('hidden');
                        } else {
                            thumbnailImg.classList.add('hidden');
                            thumbnailPlaceholder?.classList.remove('hidden');
                        }
                    }

                    previewButton?.addEventListener('click', (event) => {
                        event.preventDefault();
                        getPreviewController(feature)?.selectFeature(feature);
                    });

                    return clone;
                }

                function getPreviewController(key = 'default') {
                    if (!window.createSentinelPreviewController) {
                        window.createSentinelPreviewController = createPreviewController;
                    }
                    return createPreviewController(key);
                }

                const previewControllers = new Map();

                function createPreviewController(key = 'default') {
                    if (previewControllers.has(key)) {
                        return previewControllers.get(key);
                    }

                    if (!window.mapInstance || !window.mapProjection || !window.previewLayer) {
                        return null;
                    }

                    const controller = buildPreviewController(window.mapInstance, window.mapProjection, window.previewLayer);
                    previewControllers.set(key, controller);
                    return controller;
                }

                function buildPreviewController(mapInstance, mapProjection, previewLayer) {
                    const sentinel = dom.sentinel;
                    const state = {
                        current: null,
                        hasImage: false,
                        imageHidden: false,
                        previewType: null
                    };

                    const api = {
                        selectFeature(feature) {
                            if (!feature) {
                                api.clear();
                                return;
                            }

                            const props = feature.properties ?? {};
                            const title = props.title || props.productIdentifier || feature.id || 'Sentinel-2 Product';
                            const acquired = formatReadableDate(
                                props.completionDate || props.startDate || props.endPosition || props.beginPosition || props.startTimeFromAscendingNode
                            );

                            const mgrsIdentifier = props.mgrsId || props.tileId || props.MGRS;
                            const cloudCover = props.cloudCover ?? props['cloudcoverpercentage'] ?? props['cloudCoverageAssessment'];
                            const detailParts = [];
                            if (mgrsIdentifier) detailParts.push(`Tile ${mgrsIdentifier}`);
                            if (cloudCover !== undefined) detailParts.push(`Cloud cover: ${formatCloudCover(Number(cloudCover))}`);
                            if (props.collection) detailParts.push(`Collection: ${props.collection}`);

                        state.current = {
                            feature,
                            previewUrl: resolvePreviewUrl(feature),
                            downloadUrl: resolveDownloadUrl(feature),
                                wmsConfig: resolveWmsConfig({
                                    wms: props.wms,
                                    previewWms: props.preview,
                                    wmts: props.wmts,
                                    featureProperties: props,
                                    services: props.services,
                                    links: feature.links,
                                    assets: feature.assets,
                                    productId: props.productIdentifier,
                                    tileId: props.tileId,
                                    properties: props
                                }),
                                extent: resolveExtent(feature),
                                productId: props.productIdentifier,
                                baseTitle: title,
                                downloadFilename: buildDownloadName(props.productIdentifier, title)
                            };

                        setPreviewPanel({
                            title,
                            acquired,
                            details: detailParts.join(' • ') || null,
                            status: buildStatusMessage('')
                        });

                        setPreviewVisible(true);
                        updateButtons();

                        refreshPreview();
                    },
                        clear() {
                            state.current = null;
                            state.hasImage = false;
                            state.imageHidden = false;
                            previewLayer.setSource(null);
                            previewLayer.setVisible(false);
                            setPreviewPanel({
                                title: 'Sentinel-2 Preview',
                                acquired: 'Select a product to preview',
                                details: null,
                                status: buildStatusMessage('No product selected.')
                            });
                            setPreviewVisible(false);
                            updateButtons();
                        },
                        setImageVisibility(hidden) {
                            state.imageHidden = hidden;
                            previewLayer.setVisible(!hidden);
                            updateButtons();
                        },
                        refreshPreview: refreshPreview
                    };

                    function refreshPreview() {
                        const current = state.current;
                        if (!current) {
                            api.clear();
                            return;
                        }

                        const extent = current.extent;
                        const previewUrl = current.previewUrl;
                        const wmsConfig = current.wmsConfig;
                        const downloadUrl = current.downloadUrl;
                        const tokenisedDownload = applyToken(downloadUrl);

                        if (downloadUrl && tokenisedDownload) {
                            current.downloadUrl = tokenisedDownload;
                            current.downloadUrlBase = downloadUrl;
                        }

                        state.hasImage = false;

                        const onSuccess = () => {
                            state.hasImage = true;
                            api.setImageVisibility(false);
                        };

                        const onError = () => {
                            state.hasImage = false;
                            previewLayer.setSource(null);
                            previewLayer.setVisible(false);
                            updateButtons();
                        };

                        if (wmsConfig && applyWmsPreview(wmsConfig, { onLoad: onSuccess, onError })) {
                            focusExtent(extent);
                            updateButtons();
                            return;
                        }

                        if (previewUrl && applyStaticPreview(previewUrl, extent)) {
                            state.hasImage = true;
                            api.setImageVisibility(false);
                            focusExtent(extent);
                            updateButtons();
                            return;
                        }

                        onError();
                    }

                    function applyStaticPreview(url, extent) {
                        if (!url || !extent) return false;
                        try {
                            const imageExtent = ol.proj.transformExtent(extent, mapProjection, mapProjection);
                            previewLayer.setSource(new ol.source.ImageStatic({
                                url,
                                imageExtent,
                                projection: mapProjection,
                                crossOrigin: 'anonymous'
                            }));
                            previewLayer.setVisible(!state.imageHidden);
                            return true;
                        } catch (error) {
                            console.error('Unable to create Sentinel preview source', error);
                            previewLayer.setSource(null);
                            previewLayer.setVisible(false);
                            return false;
                        }
                    }

                    function applyWmsPreview(config, callbacks = {}) {
                        if (!config || !config.url) return false;
                        try {
                            let wmsUrl = config.url;
                            const tokenised = applyToken(wmsUrl);
                            if (tokenised) wmsUrl = tokenised;

                            const params = { ...(config.params ?? {}) };
                            if (!params.LAYERS) params.LAYERS = config.layer ?? config.layers ?? '';
                            if (!params.LAYERS) return false;

                            previewLayer.setSource(new ol.source.ImageWMS({
                                url: wmsUrl,
                                params,
                                ratio: 1,
                                serverType: 'geoserver',
                                crossOrigin: 'anonymous'
                            }));

                            const source = previewLayer.getSource();
                            if (callbacks.onLoad) source.once('imageloadend', callbacks.onLoad);
                            if (callbacks.onError) source.once('imageloaderror', callbacks.onError);

                            previewLayer.setVisible(!state.imageHidden);
                            return true;
                        } catch (error) {
                            console.error('Unable to configure Sentinel WMS preview', error);
                            previewLayer.setSource(null);
                            previewLayer.setVisible(false);
                            return false;
                        }
                    }

                    function focusExtent(extent) {
                        if (!extent) return;
                        try {
                            const view = mapInstance.getView();
                            view?.fit(extent, { padding: [50, 50, 50, 50], duration: 500, maxZoom: 14 });
                        } catch (error) {
                            console.error('Failed to fit map to extent', error);
                        }
                    }

                    function setPreviewPanel({ title, acquired, details, status }) {
                        const sentinel = dom.sentinel;
                        if (title !== undefined && sentinel.previewTitle) sentinel.previewTitle.textContent = title;
                        if (acquired !== undefined && sentinel.previewAcquired) sentinel.previewAcquired.textContent = acquired;
                        if (details !== undefined && sentinel.previewDetails) {
                            sentinel.previewDetails.textContent = details;
                            sentinel.previewDetails.classList.toggle('hidden', !details);
                        }
                        if (status !== undefined && sentinel.previewStatus) sentinel.previewStatus.textContent = status;
                    }

                    function setPreviewVisible(visible) {
                        dom.sentinel.previewPanel?.classList.toggle('hidden', !visible);
                    }

                    function updateButtons() {
                        const sentinel = dom.sentinel;
                        const hasSelection = Boolean(state.current);
                        const canToggleImage = hasSelection && state.hasImage;

                        if (sentinel.hideBtn) {
                            sentinel.hideBtn.disabled = !canToggleImage || state.imageHidden;
                            sentinel.hideBtn.textContent = state.imageHidden ? 'Preview hidden' : 'Hide preview';
                        }

                        if (sentinel.showBtn) {
                            sentinel.showBtn.disabled = !canToggleImage || !state.imageHidden;
                            sentinel.showBtn.textContent = state.imageHidden ? 'Unhide Preview' : 'Preview Visible';
                        }

                        if (sentinel.clearBtn) {
                            sentinel.clearBtn.disabled = !hasSelection;
                        }

                        if (sentinel.downloadBtn) {
                            const downloadUrl = hasSelection ? state.current?.downloadUrl : null;
                            if (downloadUrl) {
                                const label = state.current?.productId || state.current?.baseTitle || 'Sentinel-2 scene';
                                const downloadName = state.current?.downloadFilename
                                    || buildDownloadName(state.current?.productId, state.current?.baseTitle);

                                sentinel.downloadBtn.classList.remove('hidden');
                                sentinel.downloadBtn.setAttribute('href', downloadUrl);
                                sentinel.downloadBtn.setAttribute('aria-disabled', 'false');
                                sentinel.downloadBtn.setAttribute('title', `Download full scene for ${label}`);
                                sentinel.downloadBtn.setAttribute('download', downloadName);
                                sentinel.downloadBtn.dataset.downloadBase = state.current?.downloadUrlBase || '';
                                sentinel.downloadBtn.tabIndex = 0;
                            } else {
                                sentinel.downloadBtn.classList.add('hidden');
                                sentinel.downloadBtn.removeAttribute('href');
                                sentinel.downloadBtn.removeAttribute('download');
                                sentinel.downloadBtn.setAttribute('aria-disabled', 'true');
                                delete sentinel.downloadBtn.dataset.downloadBase;
                                sentinel.downloadBtn.tabIndex = -1;
                            }
                        }
                    }

                    return api;
                }

                function sanitizeToken(value) {
                    return typeof value === 'string' ? value.trim() : '';
                }

                function hasToken() {
                    return sanitizeToken(state.downloadToken).length > 0;
                }

                function applyToken(url) {
                    if (!url) return null;
                    const token = sanitizeToken(state.downloadToken);
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
                        return url.includes('?')
                            ? `${url}&token=${encodeURIComponent(token)}`
                            : `${url}?token=${encodeURIComponent(token)}`;
                    }
                }

                function buildStatusMessage(message) {
                    const parts = [];
                    if (message) parts.push(message);
                    if (!hasToken()) {
                        parts.push(
                            state.tokenConfigured
                                ? 'Download unavailable: Copernicus access token could not be issued.'
                                : 'Configure Copernicus credentials on the server to enable downloads.'
                        );
                    }
                    return parts.join(' ');
                }

                function buildDownloadName(primary, fallback) {
                    const base = String(primary ?? fallback ?? 'sentinel-2-scene').trim();
                    const normalized = base.replace(/[\s]+/g, '_').replace(/[^A-Za-z0-9._-]+/g, '_');
                    return normalized || 'sentinel-2-scene';
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
                    return `${parsed.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' })} UTC`;
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

                const downloadIgnoredKeywords = ['quicklook', 'thumbnail', 'thumb', 'overview', 'browse', 'preview', 'allorigins'];
                const previewIgnoredKeywords = ['thumbnail', 'thumb', 'legend', 'logo', 'browse', 'allorigins'];

                function resolveDownloadUrl(feature) {
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
                            score += 25;
                        } else if (type.includes('xml')) {
                            score -= 10;
                        }
                        registerCandidate(href, score);
                    });

                    if (assets && typeof assets === 'object') {
                        Object.values(assets).forEach((asset) => {
                            const href = typeof asset?.href === 'string' ? asset.href : null;
                            if (href) registerCandidate(href, 70);
                            if (asset?.service) registerFromService(asset.service, 80);
                        });
                    }

                    registerFromService(props.services, 85);
                    registerFromService(props.download, 88);
                    registerFromService(props.data, 86);
                    registerFromService(props.links, 70);

                    const best = Array.from(candidateScores.entries())
                        .sort((a, b) => b[1] - a[1])
                        .map(([url]) => url)[0];

                    return best || null;
                }

                function isValidDownloadUrl(url) {
                    if (typeof url !== 'string') return false;
                    const trimmed = url.trim();
                    if (!trimmed) return false;
                    if (!/^https?:\/\//i.test(trimmed)) return false;
                    const lowered = trimmed.toLowerCase();
                    return !downloadIgnoredKeywords.some((keyword) => lowered.includes(keyword));
                }

                function extractServiceUrls(service) {
                    const results = [];
                    if (!service) return results;
                    if (typeof service === 'string') {
                        results.push(service);
                    } else if (Array.isArray(service)) {
                        service.forEach((item) => results.push(...extractServiceUrls(item)));
                    } else if (typeof service === 'object') {
                        ['url', 'href', 'https', 'http'].forEach((key) => {
                            const value = service[key];
                            if (typeof value === 'string') {
                                results.push(value);
                            }
                        });
                    }
                    return results;
                }

                function resolvePreviewUrl(feature) {
                    if (!feature) return null;

                    const props = feature.properties ?? {};
                    const links = Array.isArray(feature.links) ? feature.links : [];
                    const assets = feature.assets ?? {};

                    const prioritizedKeys = ['preview', 'quicklook', 'thumbnail', 'overview', 'image', 'browse'];

                    const candidateUrls = new Map();
                    const register = (url, score) => {
                        if (!url || typeof url !== 'string') return;
                        const trimmed = url.trim();
                        if (!trimmed) return;
                        const lowered = trimmed.toLowerCase();
                        if (previewIgnoredKeywords.some((keyword) => lowered.includes(keyword))) return;
                        const existing = candidateUrls.get(trimmed);
                        if (existing === undefined || score > existing) {
                            candidateUrls.set(trimmed, score);
                        }
                    };

                    prioritizedKeys.forEach((key, index) => {
                        const value = props[key];
                        if (typeof value === 'string') {
                            register(value, 100 - index * 5);
                        }
                    });

                    if (assets && typeof assets === 'object') {
                        Object.entries(assets).forEach(([key, asset]) => {
                            const href = typeof asset?.href === 'string' ? asset.href : null;
                            if (!href) return;
                            const lowered = key.toLowerCase();
                            let score = 70;
                            if (lowered.includes('preview')) score = 98;
                            else if (lowered.includes('quicklook')) score = 96;
                            else if (lowered.includes('overview')) score = 90;
                            else if (lowered.includes('thumbnail')) score = 85;
                            register(href, score);
                        });
                    }

                    links.forEach((link) => {
                        const href = typeof link?.href === 'string' ? link.href : null;
                        if (!href) return;
                        const rel = typeof link?.rel === 'string' ? link.rel.toLowerCase() : '';
                        const type = typeof link?.type === 'string' ? link.type.toLowerCase() : '';
                        let score = 60;
                        if (rel.includes('preview')) score += 30;
                        if (type.includes('jpeg') || type.includes('png')) score += 10;
                        register(href, score);
                    });

                    const best = Array.from(candidateUrls.entries())
                        .sort((a, b) => b[1] - a[1])
                        .map(([url]) => url)[0];

                    return best || null;
                }

                function resolveExtent(feature) {
                    if (!feature) return null;
                    const geometry = feature.geometry || feature.features?.[0]?.geometry;
                    if (!geometry) return null;
                    try {
                        const format = new ol.format.GeoJSON();
                        const geoJson = format.readGeometry(geometry);
                        return geoJson?.getExtent() ?? null;
                    } catch (error) {
                        console.error('Failed to parse Sentinel geometry', error);
                        return null;
                    }
                }

                function resolveWmsConfig(data) {
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
                            if (!params.LAYERS && entry.layers) params.LAYERS = entry.layers;
                            if (!params.STYLES && entry.styles) params.STYLES = entry.styles;
                            if (Object.keys(params).length) candidate.params = params;
                            if (!candidate.version && entry.version) candidate.version = entry.version;
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
                            if (previewIgnoredKeywords.some((keyword) => lowered.includes(keyword))) return;
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

                    return best.params?.LAYERS ? best : null;
                }

                function normalizeWmsCandidate(value) {
                    if (!value) return null;
                    if (typeof value === 'string') {
                        return { url: value };
                    }
                    if (typeof value === 'object') {
                        const url = value.url || value.href || value.link || value.URI || value.uri;
                        if (!url) return null;
                        const params = value.params || value.Parameters || value.PARAMS;
                        const layers = value.layers || value.LAYERS || value.layer || value.Layer;
                        const styles = value.styles || value.STYLES || value.style || value.Style;
                        const version = value.version || value.VERSION;
                        const result = { url };
                        if (params && typeof params === 'object') {
                            result.params = params;
                        } else {
                            result.params = {};
                        }
                        if (layers && typeof layers === 'string') {
                            result.params.LAYERS = layers;
                        }
                        if (styles && typeof styles === 'string') {
                            result.params.STYLES = styles;
                        }
                        if (version && typeof version === 'string') {
                            result.version = version;
                        }
                        return result;
                    }
                    return null;
                }

                function collectWmsServiceCandidates(input, context = {}) {
                    const results = [];
                    if (!input) return results;

                    const visit = (value, ctx = {}) => {
                        if (!value) return;
                        if (typeof value === 'string') {
                            const trimmed = value.trim();
                            if (trimmed) {
                                results.push({ url: trimmed, ...ctx });
                            }
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
                                        if (joined) layers = joined;
                                    }
                                } else if (key === 'styles' || key === 'style' || key.includes('style')) {
                                    if (typeof rawValue === 'string' && rawValue.trim()) {
                                        styles = rawValue.trim();
                                    } else if (Array.isArray(rawValue)) {
                                        const joined = rawValue
                                            .map((item) => (typeof item === 'string' ? item.trim() : ''))
                                            .filter(Boolean)
                                            .join(',');
                                        if (joined) styles = joined;
                                    }
                                } else if (key === 'version') {
                                    if (typeof rawValue === 'string' && rawValue.trim()) {
                                        version = rawValue.trim();
                                    }
                                }
                            });

                            Object.entries(value).forEach(([rawKey, rawValue]) => {
                                visit(rawValue, { layers, styles, version });
                            });
                        }
                    };

                    visit(input, { ...context });

                    return results;
                }

                return {
                    init,
                    loadCollections: loadCollectionsIfNeeded
                };
            })();

            return {
                init,
                showPanel,
                closePanels,
                calculateTotalPrice,
                loadSentinelCollections: () => SentinelCatalog.loadCollections()
            };
        })();

            window.showPanel = (id, btn) => MapInterface.showPanel(id, btn);
            window.closePanels = () => MapInterface.closePanels();
            window.calculateTotalPrice = () => MapInterface.calculateTotalPrice();
        </script>
    @endpush
</x-app-front-map-layout>

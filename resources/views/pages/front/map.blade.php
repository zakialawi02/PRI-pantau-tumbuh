@section('title', 'Peta Estimasi PRI | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-map-layout class="flex h-screen flex-col overflow-hidden md:flex-row">
    <!-- Sidebar -->
    <div class="bg-background flex w-full flex-row items-center justify-between px-3 py-1 md:w-16 md:flex-col md:space-y-6 md:py-3">
        <button class="hover:text-accent text-xl font-bold md:block" id="panel-toggle">
            <i class="ri-sidebar-fold-line"></i>
        </button>

        <!-- Menu Icons -->
        <div class="hidden text-gray-300 md:flex md:flex-col md:space-y-6">
            <div class="bg-muted rounded-md p-1">📡</div>
            <div>📊</div>
            <div>🌱</div>
            <div>📜</div>
            <div>⚙️</div>
        </div>

        <!-- Profile -->
        <div class="mb-0 mt-0 md:mb-4 md:mt-auto">
            @auth
                <div class="relative">
                    <button class="bg-foreground mx-3 flex rounded-full text-sm transition-all duration-200 hover:ring-2 focus:ring-4 focus:ring-gray-300 md:mr-0" id="user-menu-button" data-dropdown-toggle="user-dropdown" type="button" aria-expanded="false" aria-haspopup="true">
                        <span class="sr-only">Open user menu</span>
                        <img class="h-6 w-6 rounded-full object-cover" src="{{ Auth::user()->profile_photo_path ?? asset('assets/img/image-placeholder.webp') }}" alt="{{ Auth::user()->name }}'s profile photo" loading="lazy">
                    </button>

                    <!-- Authenticated User Dropdown -->
                    <div class="bg-background text-foreground divide-foreground/50 border-border z-60 w-50 absolute right-0 mt-2 hidden list-none divide-y rounded-lg border shadow-lg" id="user-dropdown">
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
                    <div class="bg-background text-foreground divide-foreground/50 border-border z-60 w-50 absolute right-0 mt-2 hidden list-none divide-y rounded-lg border shadow-lg" id="guest-dropdown">
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
    </div>

    <!-- Main Content -->
    <div class="flex h-full flex-1 flex-col">

        <!-- Header -->
        <div class="bg-background flex items-center justify-between px-6 py-1.5">
            <h1 class="text-xl font-bold md:text-3xl">Title</h1>
            <div class="flex items-center space-x-2 rounded-md">
                <x-text-input size="small" placeholder="Search..." />
            </div>

            <!-- Nav Menu -->
            <div class="text-foreground bg-background z-51 fixed inset-0 hidden flex-col items-center justify-center space-y-6 whitespace-nowrap text-center uppercase opacity-0 transition-all duration-500 ease-in-out" id="navbar">

                <!-- Nav Menu -->
                <x-nav-menu :mobile="false" />
            </div>

            <button class="hover:text-accent z-51 text-3xl transition-transform duration-300 hover:scale-110" id="navbar-toggle">
                <i class="ri-menu-fold-line transition-all duration-300" id="menu-icon"></i>
                <i class="ri-close-line hidden transition-all duration-300" id="close-icon"></i>
            </button>
        </div>

        <div class="flex h-full flex-1 flex-col md:flex-row">
            <!-- Left Panel -->
            <div class="bg-background fixed inset-y-0 left-0 z-50 hidden w-64 max-w-full p-4 shadow-lg transition-transform duration-300 md:relative md:block md:w-72 md:translate-x-0" id="side-panel">
                <div>
                    <x-text-input placeholder="Search Field" />
                </div>
                <div class="bg-neutral cursor-pointer rounded p-3">
                    <span class="text-primary">●</span> NDVI2 - Normalized Difference Vegetation Index
                </div>
                <div class="bg-neutral cursor-pointer rounded p-3">
                    <span class="text-primary">●</span> NDVI - Normalized Difference Vegetation Index
                </div>
            </div>


            <!-- Map / Content Area -->
            <div class="relative h-full min-h-0 flex-1 bg-orange-700">
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

                <!-- Info map controls -->
                <div class="absolute bottom-12 left-2 flex items-end space-x-2 text-xs md:text-base">
                    <div class="relative hidden md:block" id="mousePosition"></div>
                    <div class="relative -mb-2" id="scaleline"></div>
                </div>

                <!-- Bottom Date Selector -->
                <div class="absolute bottom-1 left-2 flex flex-wrap space-x-1 text-xs md:text-sm">
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

        <!-- Backdrop -->
        <div class="fixed inset-0 z-40 hidden bg-black/40 md:hidden" id="backdrop"></div>
    </div>

    @push('javascript')
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const panel = document.getElementById("side-panel");
                const backdrop = document.getElementById("backdrop");
                const toggleBtn = document.getElementById("panel-toggle");

                function openPanel() {
                    panel.classList.remove("hidden", "-translate-x-full");
                    backdrop.classList.remove("hidden");
                }

                function closePanel() {
                    panel.classList.add("hidden");
                    backdrop.classList.add("hidden");
                }

                toggleBtn.addEventListener("click", () => {
                    if (panel.classList.contains("hidden")) {
                        openPanel();
                    } else {
                        closePanel();
                    }
                });

                backdrop.addEventListener("click", closePanel);
            });
        </script>
    @endpush
</x-app-front-map-layout>

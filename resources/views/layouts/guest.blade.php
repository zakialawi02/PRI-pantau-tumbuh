<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        <!-- SEO Meta Tags -->
        <meta name="description" content="@yield('meta_description', 'PantauTumbuh.id is an advanced satellite-based plant health monitoring system that utilizes the Photochemical Reflectance Index (PRI) to detect crop stress early. Our platform empowers farmers, agricultural researchers, and stakeholders with precise, real-time insights for efficient crop management and enhanced yield productivity.')">
        <meta name="author" content="@yield('meta_author', 'Ahmad Zaki Alawi')">
        <meta name="keywords" content="@yield('meta_keywords', 'PRI, photochemical reflectance index, plant stress detection, satellite imagery, pantautumbuh, pantautumbuh.id, crop health monitoring, agricultural webgis, remote sensing technology, sentinel-2 satellite, deep learning agriculture, precision farming, vegetasi stress analysis, ndvi monitoring, crop monitoring system, stres tanaman, kesehatan tanaman, pertanian, citra satelit')">

        <!-- Open Graph Meta Tags -->
        <meta property="og:title" content="@yield('og_title', config('app.name'))" />
        <meta property="og:type" content="@yield('og_type', 'website')" />
        <meta property="og:url" content="@yield('og_url', url()->current())" />
        <meta property="og:description" content="@yield('og_description', config('app.name'))" />
        <meta property="og:image" content="@yield('og_image', asset('assets/img/favicon.png'))" />

        <!-- Other Meta Tags -->
        <meta name="robots" content="@yield('meta_robots', 'index,follow')">
        <link href="{{ url()->current() }}" rel="canonical">

        <link type="image/png" href="{{ asset('/assets/img/favicon.png') }}" rel="icon">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet" integrity="sha512-XcIsjKMcuVe0Ucj/xgIXQnytNwBttJbNjltBV18IOnru2lDPe9KRRyvCXw6Y5H415vbBLRm8+q6fmLUU7DfO6Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <style>
            /* Edge (lama) & IE: hilangkan tombol eye & clear */
            input::-ms-reveal,
            input::-ms-clear {
                display: none !important;
            }

            /* WebKit “auto-fill/contact” button (Safari/Chrome varian) */
            ::-webkit-contacts-auto-fill-button {
                visibility: hidden !important;
                display: none !important;
                pointer-events: none !important;
            }

            /* Opsional: netralisir background kuning autofill (kalau terlanjur terisi) */
            input:-webkit-autofill {
                -webkit-box-shadow: 0 0 0 1000px transparent inset !important;
                -webkit-text-fill-color: inherit !important;
                caret-color: inherit !important;
            }
        </style>
        @stack('css')
        {{ $css ?? '' }}

        <!-- Scripts -->
        <script>
            (function() {
                if (localStorage.getItem("hs_theme") === "dark") {
                    document.documentElement.classList.add("dark");
                }
            })();
        </script>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        {{ $slot }}

        <script>
            function togglePassword(id) {
                const input = document.getElementById(id);
                const icon = document.getElementById('icon-' + id);

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("ri-eye-off-line");
                    icon.classList.add("ri-eye-line");
                } else {
                    input.type = "password";
                    icon.classList.remove("ri-eye-line");
                    icon.classList.add("ri-eye-off-line");
                }
            }
        </script>

        <!-- Supporting Components -->
        <x-toast />
        <x-alert-modal />
        <x-dependencies._messageAlert />


        @stack('javascript')
        {{ $javascript ?? '' }}
    </body>

</html>

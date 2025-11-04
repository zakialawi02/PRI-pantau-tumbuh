@props(['header' => 'solid'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">

        <meta name="description" content="@yield('meta_description', 'PantauTumbuh.id is an advanced satellite-based plant health monitoring system that utilizes the Photochemical Reflectance Index (PRI) to detect crop stress early. Our platform empowers farmers, agricultural researchers, and stakeholders with precise, real-time insights for efficient crop management and enhanced yield productivity.')">
        <meta name="author" content="@yield('meta_author', 'Ahmad Zaki Alawi')">
        <meta name="keywords" content="@yield('meta_keywords', 'PRI, photochemical reflectance index, plant stress detection, satellite imagery, pantautumbuh, pantautumbuh.id, crop health monitoring, agricultural webgis, remote sensing technology, sentinel-2 satellite, deep learning agriculture, precision farming, vegetasi stress analysis, ndvi monitoring, crop monitoring system, stres tanaman, kesehatan tanaman, pertanian, citra satelit')">

        <meta property="og:title" content="@yield('og_title', config('app.name'))" />
        <meta property="og:type" content="@yield('og_type', 'website')" />
        <meta property="og:url" content="@yield('og_url', url()->current())" />
        <meta property="og:description" content="@yield('og_description', 'Monitor crop health and detect plant stress early with PantauTumbuh.id. Our platform leverages satellite imagery and deep learning to calculate Photochemical Reflectance Index (PRI) values, delivering accurate spatial information about vegetation conditions for farmers, researchers, and agricultural decision-makers.')" />
        <meta property="og:image" content="@yield('og_image', asset('assets/img/favicon.png'))" />

        <meta name="robots" content="@yield('meta_robots', 'index,follow')">
        <link href="{{ url()->current() }}" rel="canonical">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link type="image/png" href="{{ asset('assets/img/favicon.png') }}" rel="shortcut icon">

        <title>@yield('title', config('app.name'))</title>

        <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet" integrity="sha512-XcIsjKMcuVe0Ucj/xgIXQnytNwBttJbNjltBV18IOnru2lDPe9KRRyvCXw6Y5H415vbBLRm8+q6fmLUU7DfO6Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="bg-background text-foreground min-h-screen font-sans antialiased">
        <!-- HEADER -->
        <x-fragment.header :variant="$header" />

        <main>
            {{ $slot }}
        </main>

        <!-- Footer -->
        <x-fragment.footer />

        <!-- Supporting Components -->
        <x-toast />
        <x-alert-modal />
        <x-dependencies._messageAlert />

        @stack('javascript')
        {{ $javascript ?? '' }}
    </body>

</html>

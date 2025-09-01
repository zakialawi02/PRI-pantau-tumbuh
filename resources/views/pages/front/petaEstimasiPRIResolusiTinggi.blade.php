@section('title', 'Peta Estimasi PRI | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-layout>
    <!-- Map Intro Start -->
    <!-- Bagaimana Data PRI Didapatkan -->
    <section class="mx-auto mb-8 max-w-7xl px-6 py-10">
        <div class="mb-8 text-center">
            <h1 class="mb-6 text-3xl font-bold md:text-4xl">AI & Google Earth Engine-Based PRI Estimation Map</h1>
            <p class="text-foreground/80 mb-6 text-lg">
                Below is an interactive visualization of the <strong>Photochemical Reflectance Index (PRI)</strong> values processed using the <strong>Deep Neural Network (DNN)</strong> method based on Google Earth Engine (GEE).
            </p>
        </div>

        <h2 class="mb-4 flex items-center text-2xl font-semibold">
            🔍 How is PRI Data Obtained?
        </h2>
        <p class="mb-4">
            PRI values are calculated from <strong>Sentinel-2</strong> satellite imagery by utilizing two main spectral bands, namely:
        </p>
        <ul class="mb-4 list-disc space-y-2 pl-6" style="list-style-type: disc; list-style-position: outside;">
            <li><strong>Band 11 (1610 nm)</strong> — Near Infrared Shortwave</li>
            <li><strong>Band 4 (665 nm)</strong> — Red</li>
        </ul>
        <p>
            The basic formula of PRI is: <span class="bg-muted rounded px-2 py-1 font-mono">(R<sub>λ531</sub> - R<sub>λ570</sub>) / (R<sub>λ531</sub> + R<sub>λ570</sub>)</span>. Since Sentinel-2 does not have a channel at 531 nm, this value is extracted using a <strong>Deep Learning-based spectral modeling</strong> technique to estimate reflectance at that wavelength from a combination of available bands.
        </p>
    </section>

    <!-- About Deep Neural Network Method -->
    <section class="mx-auto mb-8 max-w-7xl px-6 py-6">
        <h2 class="mb-4 flex items-center text-2xl font-semibold">
            🤖 About the Deep Neural Network (DNN) Method
        </h2>
        <p class="mb-4">
            This AI model was built using a <strong>Deep Neural Network</strong> approach, trained on multispectral reflectance datasets and actual PRI values from survey sites. This process enables the model to:
        </p>
        <ul class="mb-4 list-disc space-y-2 pl-6" style="list-style-type: disc; list-style-position: outside;">
            <li>Extract spectral features from Sentinel-2 bands</li>
            <li>Predict accurate PRI values even when some wavelengths are unavailable</li>
            <li>Map healthy and stressed crops based on model predictions</li>
            <li>Reduce noise from thin clouds & vegetation variation</li>
            <li>Detect non-linear spectral patterns & plant stress</li>
            <li>Produce more consistent estimates under various conditions</li>
        </ul>
        <p>
            These estimation results are then processed and displayed interactively.
        </p>

        <!-- Call to Action Button -->
        <div class="mt-8 flex justify-center space-x-3">
            <x-button-primary href="{{ route('appMap') }}">
                Go to Dashboard Apps
            </x-button-primary>
        </div>
    </section>

</x-app-front-layout>

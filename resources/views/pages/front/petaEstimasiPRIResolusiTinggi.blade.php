@section('title', 'Peta Estimasi PRI | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-layout>
    <!-- Map Intro Start -->
    <!-- Bagaimana Data PRI Didapatkan -->
    <section class="mx-auto mb-8 max-w-7xl py-10">
        <div class="mb-8 text-center">
            <h1 class="mb-6 text-3xl font-bold md:text-4xl">Peta Estimasi PRI Berbasis AI & Google Earth Engine</h1>
            <p class="text-foreground/80 mb-6 text-lg">
                Di bawah ini adalah visualisasi interaktif hasil pengolahan nilai <strong>Photochemical Reflectance Index (PRI)</strong> menggunakan metode <strong>Deep Neural Network (DNN)</strong> berbasis Google Earth Engine (GEE).
            </p>
        </div>

        <h2 class="mb-4 flex items-center text-2xl font-semibold">
            🔍 Bagaimana Data PRI Didapatkan?
        </h2>
        <p class="mb-4">
            Nilai PRI dihitung dari citra satelit <strong>Sentinel-2</strong> dengan memanfaatkan dua kanal spektral utama, yaitu:
        </p>
        <ul class="mb-4 list-disc space-y-2 pl-6" style="list-style-type: disc; list-style-position: outside;">
            <li><strong>Band 11 (1610 nm)</strong> — Near Infrared Shortwave</li>
            <li><strong>Band 4 (665 nm)</strong> — Red</li>
        </ul>
        <p>
            Rumus dasar PRI adalah: <span class="bg-muted rounded px-2 py-1 font-mono">(R<sub>λ531</sub> - R<sub>λ570</sub>) / (R<sub>λ531</sub> + R<sub>λ570</sub>)</span>. Karena Sentinel-2 tidak memiliki kanal di 531 nm, nilai tersebut diekstrak menggunakan teknik <strong>modeling spektral berbasis Deep Learning</strong> untuk melakukan estimasi reflektansi di panjang gelombang tersebut dari kombinasi band yang tersedia.
        </p>
    </section>

    <!-- Tentang Metode Deep Neural Network -->
    <section class="mx-auto mb-8 max-w-7xl py-6">
        <h2 class="mb-4 flex items-center text-2xl font-semibold">
            🤖 Tentang Metode Deep Neural Network (DNN)
        </h2>
        <p class="mb-4">
            Model AI ini dibangun menggunakan pendekatan <strong>Deep Neural Network</strong>, yang dilatih pada dataset reflektansi multispektral dan nilai PRI aktual dari lokasi survei. Proses ini memungkinkan model untuk:
        </p>
        <ul class="mb-4 list-disc space-y-2 pl-6" style="list-style-type: disc; list-style-position: outside;">
            <li>Mengekstrak fitur spektral dari band Sentinel-2</li>
            <li>Memprediksi nilai PRI akurat meskipun tidak semua panjang gelombang tersedia</li>
            <li>Memetakan area tanaman sehat dan stres berdasarkan prediksi model</li>
            <li>Mengurangi noise dari awan tipis & variasi vegetasi</li>
            <li>Mendeteksi pola non-linear spektral & stres tanaman</li>
            <li>Hasil estimasi lebih konsisten di berbagai kondisi</li>
        </ul>
        <p>
            Hasil estimasi ini kemudian diproses dan ditampilkan secara interaktif.
        </p>

        <!-- Call to Action Button -->
        <div class="mt-8 flex justify-center space-x-3">
            <x-button-primary href="{{ route('appMap') }}">
                Go to Dashboard Apps
            </x-button-primary>
        </div>
    </section>

</x-app-front-layout>

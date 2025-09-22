@section('title', 'Tentang PRI | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')

<x-app-front-layout>

    <!-- Main Content -->
    <section class="from-background to-muted min-h-screen bg-gradient-to-br py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Title -->
            <div class="mb-12 text-center">
                <h1 class="text-foreground mb-4 text-4xl font-bold md:text-5xl">
                    About <span class="text-primary">Photochemical Reflectance Index</span>
                </h1>
                <p class="text-base-content-muted mx-auto max-w-3xl text-xl">
                    Understanding plant stress detection technology through photochemical reflectance index
                </p>
            </div>

            <!-- What is PRI Section -->
            <div class="bg-neutral mb-8 rounded-xl p-8 shadow-lg">
                <div class="mb-6 flex items-center">
                    <div class="bg-muted mr-4 flex h-12 w-12 items-center justify-center rounded-lg">
                        <svg class="text-primary h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-foreground text-3xl font-bold">What is PRI?</h2>
                </div>
                <div class="grid items-center gap-8 md:grid-cols-2">
                    <div>
                        <p class="text-base-content mb-4 text-lg leading-relaxed">
                            <strong>Photochemical Reflectance Index (PRI)</strong> is a spectral vegetation index developed to detect changes in photosynthetic efficiency in plants. PRI measures changes in xanthophyll cycle pigments that occur when plants experience stress.
                        </p>
                        <p class="text-base-content text-lg leading-relaxed">
                            This index is highly sensitive to plant stress and can detect stress conditions before visual symptoms appear on leaves.
                        </p>

                        <img class="mx-auto h-80 w-auto" src="{{ asset('assets/img/pri-formula.png') }}" alt="PRI Formula">
                    </div>
                    <div class="from-muted to-secondary/20 rounded-lg bg-gradient-to-r p-6">
                        <h3 class="text-foreground mb-4 text-xl font-semibold">PRI Formula</h3>
                        <div class="border-primary/50 bg-neutral rounded-lg border-2 border-dashed p-4">
                            <p class="text-center font-mono text-lg">
                                PRI = (R<sub>531</sub> - R<sub>570</sub>) / (R<sub>531</sub> + R<sub>570</sub>)
                            </p>
                            <p class="text-base-content-muted mt-2 text-center text-sm">
                                R<sub>531</sub> = Reflectance at 531 nm<br>
                                R<sub>570</sub> = Reflectance at 570 nm
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How PRI Works -->
            <div class="bg-neutral mb-8 rounded-xl p-8 shadow-lg">
                <div class="mb-6 flex items-center">
                    <div class="bg-secondary/20 mr-4 flex h-12 w-12 items-center justify-center rounded-lg">
                        <svg class="text-secondary h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <h2 class="text-foreground text-3xl font-bold">How Does PRI Work?</h2>
                </div>
                <div class="grid gap-6 md:grid-cols-3">
                    <div class="from-primary/10 to-primary/20 rounded-lg bg-gradient-to-b p-6 text-center">
                        <div class="bg-primary/30 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <span class="text-primary text-2xl font-bold">1</span>
                        </div>
                        <h3 class="text-foreground mb-3 text-xl font-semibold">Normal Condition</h3>
                        <p class="text-base-content">
                            Tanaman sehat memiliki efisiensi fotosintesis optimal dengan nilai PRI yang stabil
                        </p>
                    </div>
                    <div class="from-warning/10 to-warning/20 rounded-lg bg-gradient-to-b p-6 text-center">
                        <div class="bg-warning/30 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <span class="text-warning text-2xl font-bold">2</span>
                        </div>
                        <h3 class="text-foreground mb-3 text-xl font-semibold">Stress Detection</h3>
                        <p class="text-base-content">
                            When plants are stressed, the xanthophyll cycle changes and affects light reflectance.
                        </p>
                    </div>
                    <div class="from-error/10 to-error/20 rounded-lg bg-gradient-to-b p-6 text-center">
                        <div class="bg-error/30 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <span class="text-error text-2xl font-bold">3</span>
                        </div>
                        <h3 class="text-foreground mb-3 text-xl font-semibold">PRI Value Changes</h3>
                        <p class="text-base-content">
                            Changes in PRI values ​​indicate the level of stress experienced by plants.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Applications -->
            <div class="bg-neutral mb-8 rounded-xl p-8 shadow-lg">
                <div class="mb-6 flex items-center">
                    <div class="bg-accent/20 mr-4 flex h-12 w-12 items-center justify-center rounded-lg">
                        <svg class="text-accent h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <h2 class="text-foreground text-3xl font-bold">PRI Application</h2>
                </div>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <div class="from-primary/20 to-primary/30 rounded-lg bg-gradient-to-br p-6">
                        <h3 class="text-foreground mb-3 text-lg font-semibold">Precision Farming</h3>
                        <p class="text-base-content text-sm">
                            Plant health monitoring to optimize harvest yields
                        </p>
                    </div>
                    <div class="from-secondary/20 to-secondary/30 rounded-lg bg-gradient-to-br p-6">
                        <h3 class="text-foreground mb-3 text-lg font-semibold">Water Management</h3>
                        <p class="text-base-content text-sm">
                            Early detection of drought stress for irrigation scheduling
                        </p>
                    </div>
                    <div class="from-warning/20 to-warning/30 rounded-lg bg-gradient-to-br p-6">
                        <h3 class="text-foreground mb-3 text-lg font-semibold">Ecological Research</h3>
                        <p class="text-base-content text-sm">
                            Study of the impact of climate change on vegetation
                        </p>
                    </div>
                    <div class="from-accent/20 to-accent/30 rounded-lg bg-gradient-to-br p-6">
                        <h3 class="text-foreground mb-3 text-lg font-semibold">Forestry</h3>
                        <p class="text-base-content text-sm">
                            Forest health monitoring and tree disease detection
                        </p>
                    </div>
                </div>
            </div>

            <!-- Advantages -->
            <div class="bg-neutral mb-8 rounded-xl p-8 shadow-lg">
                <div class="mb-6 flex items-center">
                    <div class="bg-success/20 mr-4 flex h-12 w-12 items-center justify-center rounded-lg">
                        <svg class="text-success h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <h2 class="text-foreground text-3xl font-bold">PRI Advantages</h2>
                </div>
                <div class="grid gap-8 md:grid-cols-2">
                    <div>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Early Detection:</strong> Can detect stress before visual symptoms appear</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Non-destructive:</strong> Monitoring without damaging or disturbing the plants</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Wide Coverage:</strong> Can be applied to large areas using satellites</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Real-time:</strong> Continuous monitoring of plant conditions</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Sensitive:</strong> Highly responsive to plant physiological changes</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div>
                                <span class="text-base-content"><strong>Accurate:</strong> Provides precise information about plant conditions</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Implementation in PantauTumbuh -->
            <div class="from-primary to-secondary text-primary-foreground rounded-xl bg-gradient-to-r p-8 shadow-lg">
                <div class="text-center">
                    <h2 class="mb-4 text-3xl font-bold">PRI in PantauTumbuh.id</h2>
                    <p class="mb-6 text-lg opacity-90">
                        Our platform integrates PRI technology with Sentinel-2 satellite imagery and deep learning models to provide comprehensive analysis of crop stress conditions.
                    </p>
                    <div class="flex flex-wrap justify-center gap-4">
                        <a class="bg-neutral text-primary rounded-lg px-4 py-2 font-semibold transition duration-300 hover:opacity-80" href="{{ route('peta-estimasi-pri') }}">
                            View Demo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-app-front-layout>

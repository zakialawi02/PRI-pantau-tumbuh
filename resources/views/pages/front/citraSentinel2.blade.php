@section('title', 'Citra Sentinel 2 | ' . config('app.name'))

@section('meta_description', 'Sentinel-2 is an advanced Earth observation satellite by ESA providing high-resolution multispectral imagery for agricultural monitoring, environmental analysis, and land use mapping with 10-meter spatial resolution.')
@section('meta_keywords', 'Sentinel-2, ESA satellite, multispectral imagery, Earth observation, agricultural monitoring, environmental analysis, land use mapping, precision farming, remote sensing, vegetation index, crop health, satellite data')

@section('og_title', 'Sentinel-2 - Advanced Earth Observation Satellite')
@section('og_description', 'Discover how Sentinel-2 satellite technology delivers high-resolution multispectral data for precision agriculture, environmental monitoring, and land use analysis. Monitor crop health and vegetation changes with 10-meter accuracy.')

<x-app-front-layout header="transparent">
    <!-- Hero Section -->
    <section class="from-accent to-secondary relative flex min-h-[60vh] items-center justify-center overflow-hidden bg-gradient-to-br">
        <!-- Background Image -->
        <div class="absolute inset-0">
            <img class="h-full w-full object-cover opacity-80" src="{{ asset('assets/img/sat3.webp') }}" alt="Earth from Space">
        </div>

        <div class="text-background relative z-10 mx-auto max-w-6xl px-4 py-12 text-center">
            <div class="mb-6">
                <h1 class="from-primary via-primary to-accent mb-4 bg-gradient-to-r bg-clip-text text-4xl font-bold text-transparent md:text-6xl">
                    Sentinel-2
                </h1>
                <h2 class="text-background mb-3 text-xl font-semibold md:text-2xl">
                    Eyes in the Sky for Our Earth
                </h2>
                <p class="text-background mx-auto max-w-3xl text-base leading-relaxed md:text-lg">
                    An in-depth exploration of the revolutionary satellite that is changing the way we understand and monitor planet Earth
                </p>
            </div>
        </div>
    </section>

    <!-- Satellite Overview Section -->
    <section class="bg-background py-18 relative">
        <!-- Decorative Elements -->
        <div class="from-primary via-primary to-accent absolute left-0 top-0 h-1 w-full bg-gradient-to-r"></div>

        <div class="mx-auto max-w-7xl px-4">
            <div class="mb-16 text-center">
                <h2 class="text-foreground mb-6 text-4xl font-bold md:text-5xl">
                    Introducing <span class="from-primary via-primary to-accent bg-gradient-to-r bg-clip-text text-transparent">Sentinel-2</span>
                </h2>
                <div class="from-primary via-primary to-accent mx-auto mb-8 h-1 w-24 bg-gradient-to-r"></div>
                <p class="text-foreground mx-auto max-w-4xl text-lg leading-relaxed">
                    Sentinel-2 is an Earth observation satellite constellation developed by the European Space Agency (ESA)
                    as part of the Copernicus program, providing high-quality multispectral data for environmental monitoring.
                </p>
            </div>

            <div class="grid items-center gap-6 lg:grid-cols-2">
                <div class="space-y-5">
                    <div class="border-primary/40 from-primary/20 to-accent/20 rounded-2xl border bg-gradient-to-br p-8 shadow-lg">
                        <div class="mb-4 flex items-center">
                            <div class="bg-primary flex h-12 w-12 items-center justify-center rounded-full">
                                <svg class="text-background h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                </svg>
                            </div>
                            <h3 class="text-base-content ml-4 text-2xl font-bold">Main Mission</h3>
                        </div>
                        <p class="text-foreground leading-relaxed">
                            Provide high-quality satellite imagery data for land, agriculture,
                            forestry, and environmental change monitoring with spatial resolution up to 10 meters.
                        </p>
                    </div>

                    <div class="border-accent/40 from-accent/20 to-secondary/20 rounded-2xl border bg-gradient-to-br p-8 shadow-lg">
                        <div class="mb-4 flex items-center">
                            <div class="bg-primary flex h-12 w-12 items-center justify-center rounded-full">
                                <svg class="text-background h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            </div>
                            <h3 class="text-base-content ml-4 text-2xl font-bold">Twin Constellation</h3>
                        </div>
                        <p class="text-foreground leading-relaxed">
                            Consists of two twin satellites (Sentinel-2A and Sentinel-2B) operating together
                            to provide global coverage with optimal revisit intervals.
                        </p>
                    </div>
                </div>

                <div class="relative">
                    <div class="rounded-3xl bg-gradient-to-br from-gray-700 to-blue-700 p-8 shadow-2xl">
                        <div class="text-center">
                            <div class="relative mb-6 inline-block">
                                <div class="mx-auto flex h-48 w-48 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 via-purple-500 to-cyan-400">
                                    <img class="h-32 w-32" src="{{ asset('assets/img/sentinel-2.png') }}" alt="Sentinel-2">
                                </div>
                                <div class="absolute -inset-4 animate-pulse rounded-full bg-gradient-to-r from-blue-400 to-purple-400 opacity-20"></div>
                            </div>
                            <h3 class="text-background mb-4 text-2xl font-bold">Satellite Specifications</h3>
                            <div class="space-y-3 text-left">
                                <div class="text-background flex justify-between">
                                    <span>Mass:</span>
                                    <span class="text-background font-semibold">{{ Number::format(1.2, locale: app()->getLocale()) }} tons</span>
                                </div>
                                <div class="text-background flex justify-between">
                                    <span>Orbit:</span>
                                    <span class="text-background font-semibold">786 km (Sun-synchronous)</span>
                                </div>
                                <div class="text-background flex justify-between">
                                    <span>Sensor:</span>
                                    <span class="text-background font-semibold">MSI (MultiSpectral Instrument)</span>
                                </div>
                                <div class="text-background flex justify-between">
                                    <span>Swath width:</span>
                                    <span class="text-background font-semibold">290 km</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Specifications Section -->
    <section class="bg-background relative overflow-hidden py-10">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="bg-secondary absolute left-0 top-0 h-64 w-64 -translate-x-32 -translate-y-32 transform rounded-full"></div>
            <div class="bg-primary absolute bottom-0 right-0 h-96 w-96 translate-x-48 translate-y-48 transform rounded-full"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4">
            <div class="mb-16 text-center">
                <h2 class="text-base-content mb-6 text-4xl font-bold md:text-5xl">
                    <span class="from-primary via-primary to-accent bg-gradient-to-r bg-clip-text text-transparent">Technical</span> Specifications
                </h2>
                <div class="from-primary via-primary to-accent mx-auto mb-8 h-1 w-24 bg-gradient-to-r"></div>
            </div>

            <!-- MSI Sensor Details -->
            <div class="bg-background border-background/80 mb-12 rounded-3xl border p-8 shadow-2xl">
                <div class="mb-8 text-center">
                    <h3 class="text-base-content mb-4 text-3xl font-bold">MultiSpectral Instrument (MSI)</h3>
                    <p class="text-foreground text-lg">Advanced sensor with 13 spectral bands for in-depth analysis</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Band 10m Resolution -->
                    <div class="border-primary/20 from-primary/10 to-primary/20 rounded-2xl border bg-gradient-to-br p-6">
                        <div class="mb-4 text-center">
                            <div class="from-primary/20 to-primary mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                                <span class="text-background text-xl font-bold">10m</span>
                            </div>
                            <h4 class="text-base-content font-bold">High Resolution</h4>
                        </div>
                        <ul class="text-foreground space-y-2 text-sm">
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-blue-500"></div>
                                Band 2 (Blue): 490 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-green-500"></div>
                                Band 3 (Green): 560 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-red-500"></div>
                                Band 4 (Red): 665 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-purple-500"></div>
                                Band 8 (NIR): 842 nm
                            </li>
                        </ul>
                    </div>

                    <!-- Band 20m Resolution -->
                    <div class="border-secondary/20 from-secondary/10 to-secondary/20 rounded-2xl border bg-gradient-to-br p-6">
                        <div class="mb-4 text-center">
                            <div class="from-secondary/20 to-secondary mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                                <span class="text-background text-xl font-bold">20m</span>
                            </div>
                            <h4 class="text-base-content font-bold">Medium Resolution</h4>
                        </div>
                        <ul class="text-foreground space-y-2 text-sm">
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-indigo-500"></div>
                                Band 5 (Red Edge): 705 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-pink-500"></div>
                                Band 6 (Red Edge): 740 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-yellow-500"></div>
                                Band 7 (Red Edge): 783 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-orange-500"></div>
                                Band 8A (NIR): 865 nm
                            </li>
                        </ul>
                    </div>

                    <!-- Band 60m Resolution -->
                    <div class="border-accent/20 from-accent/10 to-accent/20 rounded-2xl border bg-gradient-to-br p-6">
                        <div class="mb-4 text-center">
                            <div class="from-accent/20 to-accent/10 mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                                <span class="text-background text-xl font-bold">60m</span>
                            </div>
                            <h4 class="text-base-content font-bold">Atmospheric</h4>
                        </div>
                        <ul class="text-foreground space-y-2 text-sm">
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-violet-500"></div>
                                Band 1 (Aerosol): 443 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-cyan-500"></div>
                                Band 9 (Water vapor): 945 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-teal-500"></div>
                                Band 10 (Cirrus): 1375 nm
                            </li>
                            <li class="flex items-center">
                                <div class="mr-2 h-3 w-3 rounded-full bg-amber-500"></div>
                                Band 11-12 (SWIR): 1610-2190 nm
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Key Features -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="bg-background rounded-2xl p-6 text-center shadow-lg transition-shadow duration-300 hover:shadow-xl">
                    <div class="from-info/30 to-info/80 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                        <svg class="text-background h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                        </svg>
                    </div>
                    <h4 class="text-base-content mb-2 text-xl font-bold">Global Coverage</h4>
                    <p class="text-foreground text-sm">5-day revisit time for the entire Earth's surface</p>
                </div>

                <div class="bg-background rounded-2xl p-6 text-center shadow-lg transition-shadow duration-300 hover:shadow-xl">
                    <div class="from-success/30 to-success/80 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                        <svg class="text-background h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z" />
                        </svg>
                    </div>
                    <h4 class="text-base-content mb-2 text-xl font-bold">Free Data</h4>
                    <p class="text-foreground text-sm">Open access for all users worldwide</p>
                </div>

                <div class="bg-background rounded-2xl p-6 text-center shadow-lg transition-shadow duration-300 hover:shadow-xl">
                    <div class="from-error/20 to-error/80 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                        <svg class="text-background h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <h4 class="text-base-content mb-2 text-xl font-bold">High Quality</h4>
                    <p class="text-foreground text-sm">Spatial resolution up to 10 meters with 13 spectral bands</p>
                </div>

                <div class="bg-background rounded-2xl p-6 text-center shadow-lg transition-shadow duration-300 hover:shadow-xl">
                    <div class="from-warning/50 to-waring mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br">
                        <svg class="text-background h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9s.67 1.5 1.5 1.5zm-7 0c.83 01.5-.67 1.5-1.5S7.33 8 6.5 8 5 8.67 5 9s.67 1.5 1.5 1.5zm3.5 6.5c2.33 0 4.31-1.46
                                5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
                        </svg>
                    </div>
                    <h4 class="text-base-content mb-2 text-xl font-bold">Multi-Use</h4>
                    <p class="text-foreground text-sm">Applications across agriculture, forestry, urban planning, and disaster management</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Applications Section -->
    <section class="bg-background py-18 relative overflow-hidden overflow-x-hidden">
        <div class="absolute inset-0">
            <div class="bg-primary/5 absolute right-0 top-0 h-96 w-96 -translate-y-48 translate-x-48 transform rounded-full"></div>
            <div class="bg-primary/5 absolute bottom-0 left-0 h-80 w-80 -translate-x-40 translate-y-40 transform rounded-full"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4">
            <div class="mb-16 text-center">
                <h2 class="text-base-content mb-6 text-4xl font-bold md:text-5xl">
                    <span class="from-primary via-primary to-accent bg-gradient-to-r bg-clip-text text-transparent">Sentinel-2</span> Applications
                </h2>
                <div class="from-primary via-primary to-accent mx-auto mb-8 h-1 w-24 bg-gradient-to-r"></div>
                <p class="text-foreground mx-auto max-w-4xl text-xl leading-relaxed">
                    Sentinel-2 data has a wide range of applications across various fields to support environmental monitoring and analysis
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Agriculture -->
                <div class="border-primary/20 from-primary/10 to-primary/20 hover:border-primary/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-primary to-primary/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Agriculture</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Crop health monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Yield estimation</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Crop stress detection</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-primary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Irrigation management</span>
                        </li>
                    </ul>
                </div>

                <!-- Forestry -->
                <div class="border-secondary/20 from-secondary/10 to-secondary/20 hover:border-secondary/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-secondary to-secondary/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.34c.48-.3 1.34-.81 2.34-1.81C9.46 17.38 10 16.2 10 15c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1c.28 0 .5-.11.71-.29.32-.32.69-.71.29-1.11C9.46 14.46 9 14.73 9 15c0 .83.67 1.5 1.5 1.5S12 15.83 12 15c0-2.12-1.19-3.84-2.87-4.5C12.22 9.5 15.31 8.5 17 8z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Forestry</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-secondary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Deforestation monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-secondary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Forest fire detection</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-secondary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Biomass estimation</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-secondary mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Biodiversity conservation</span>
                        </li>
                    </ul>
                </div>

                <!-- Urban Planning -->
                <div class="border-accent/20 from-accent/10 to-accent/20 hover:border-accent/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-accent to-accent/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Urban Planning</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-accent mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Land use change monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-accent mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Infrastructure mapping</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-accent mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Urban expansion analysis</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-accent mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Green space planning</span>
                        </li>
                    </ul>
                </div>

                <!-- Water Resources -->
                <div class="border-info/20 from-info/10 to-info/20 hover:border-info/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-info to-info/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l-2 9h4l-2-9zM9 13c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-7 7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm14 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Water Resources</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-info mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Water quality monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-info mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Water pollution detection</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-info mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Water body mapping</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-info mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Hydrological change analysis</span>
                        </li>
                    </ul>
                </div>

                <!-- Climate Monitoring -->
                <div class="border-warning/20 from-warning/10 to-warning/20 hover:border-warning/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-warning to-warning/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Climate Monitoring</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-warning mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Climate change monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-warning mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Plant phenology analysis</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-warning mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Weather anomaly detection</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-warning mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Seasonal pattern prediction</span>
                        </li>
                    </ul>
                </div>

                <!-- Disaster Management -->
                <div class="border-error/20 from-error/10 to-error/20 hover:border-error/40 group rounded-3xl border bg-gradient-to-br p-8 shadow-lg transition-all duration-300 hover:shadow-2xl">
                    <div class="mb-6 text-center">
                        <div class="from-error to-error/80 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br transition-transform duration-300 group-hover:scale-110">
                            <svg class="text-background h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1.91l-.01-.01L23 10z" />
                            </svg>
                        </div>
                        <h3 class="text-base-content mb-3 text-2xl font-bold">Disaster Management</h3>
                    </div>
                    <ul class="text-foreground space-y-3">
                        <li class="flex items-start">
                            <div class="bg-error mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Early disaster detection</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-error mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Flood risk mapping</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-error mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Fire monitoring</span>
                        </li>
                        <li class="flex items-start">
                            <div class="bg-error mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full"></div><span>Rapid emergency response</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>


</x-app-front-layout>

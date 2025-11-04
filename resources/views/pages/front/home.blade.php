@section('title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')

@section('meta_description', 'PantauTumbuh.id is an advanced satellite-based plant health monitoring system that utilizes the Photochemical Reflectance Index (PRI) to detect crop stress early. Our platform empowers farmers, agricultural researchers, and stakeholders with precise, real-time insights for efficient crop management and enhanced yield productivity.')
@section('meta_keywords', 'PRI, photochemical reflectance index, plant stress detection, satellite imagery, pantautumbuh, pantautumbuh.id, crop health monitoring, agricultural webgis, remote sensing technology, sentinel-2 satellite, deep learning agriculture, precision farming, vegetasi stress analysis, ndvi monitoring, crop monitoring system, stres tanaman, kesehatan tanaman, pertanian, citra satelit')

@section('og_title', 'PantauTumbuh.id - Advanced Plant Stress Detection Using Satellite Technology')
@section('og_description', 'Monitor crop health and detect plant stress early with PantauTumbuh.id. Our platform leverages satellite imagery and deep learning to calculate Photochemical Reflectance Index (PRI) values, delivering accurate spatial information about vegetation conditions for farmers, researchers, and agricultural decision-makers.')

<x-app-front-layout header="transparent">
    <!-- Hero Section -->
    <section class="relative flex min-h-screen items-center justify-center bg-cover bg-center" style="background-image: url('/assets/img/sat.webp');">

        <!-- Content -->
        <div class="relative z-10 mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <h1 class="text-background mb-6 text-3xl font-bold leading-tight md:text-5xl">
                A smart satellite-based monitoring system<br>
                <span class="text-primary">Early Detection of Plant Stress, Directly from Satellites</span>
            </h1>
            <p class="text-foreground/90 mx-auto mb-10 max-w-3xl text-lg md:text-xl">
                Growing the Future, Monitoring from Above 🌍
            </p>

            <a class="border-primary bg-primary text-primary-foreground hover:text-primary hover:bg-background mx-auto inline-block rounded-xl border px-6 py-2 text-lg font-medium shadow-md transition-colors duration-300" href="{{ route('pri-estimation-map-ai') }}">
                Get Started
            </a>
            <a class="border-primary bg-background text-primary hover:text-foreground hover:border-foreground mx-auto inline-block rounded-xl border px-6 py-2 text-lg font-medium shadow-md transition-colors duration-300 hover:bg-transparent" href="{{ route('about-pri') }}">
                Learn More
            </a>
        </div>

        <!-- Gradient Overlay -->
        <div class="to-background/80 from-base-content-muted/50 absolute inset-0 bg-gradient-to-b opacity-80"></div>
    </section>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 transform animate-bounce">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </div>

    <!-- About Section -->
    <section class="from-background to-background/95 relative overflow-hidden bg-gradient-to-br">
        <!-- Decorative Pattern -->
        <div class="absolute inset-0 opacity-[0.02]">
            <svg class="h-full w-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#grid)" />
            </svg>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="mx-auto mb-16 max-w-4xl text-center">
                <!-- Badge -->
                <div class="bg-primary/10 border-primary/20 mb-6 inline-flex items-center rounded-full border px-4 py-2">
                    <i class="ri-dashboard-2-fill text-primary mr-1"></i>
                    <span class="text-primary text-sm font-medium">About Our Platform</span>
                </div>

                <!-- Main Title -->
                <h2 class="text-foreground mb-6 text-4xl font-bold leading-tight md:text-5xl lg:text-5xl">
                    About
                    <span class="text-primary relative">
                        PantauTumbuh.id
                    </span>
                </h2>

                <!-- Subtitle -->
                <div class="relative">
                    <p class="text-foreground/80 mx-auto max-w-3xl text-lg font-light leading-relaxed md:text-xl">
                        A smart satellite-based monitoring system for accurately detecting plant health and stress.
                    </p>
                </div>
            </div>

            <!-- Content Cards -->
            <div class="mx-auto grid max-w-6xl gap-8 md:grid-cols-2">
                <!-- Mission Card -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-8 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-6 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <svg class="text-primary h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="text-foreground mb-4 text-xl font-semibold">Our Mission</h3>
                        <p class="text-foreground/70 leading-relaxed">
                            From palm oil plantations in Kalimantan to other agricultural lands, we're here to help farmers, researchers, and decision makers protect and improve crop productivity.
                        </p>
                    </div>
                </div>

                <!-- Technology Card -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-8 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-6 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <svg class="text-primary h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <h3 class="text-foreground mb-4 text-xl font-semibold">Advanced Technology</h3>
                        <p class="text-foreground/70 leading-relaxed">
                            Leveraging cutting-edge satellite technology and AI-powered analytics to provide real-time insights into plant health and early stress detection.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="mt-16 text-center">
                <div class="inline-flex items-center space-x-4">
                    <a class="border-primary/30 text-primary hover:bg-primary/5 rounded-xl border px-8 py-3 font-medium transition-colors duration-300" href="#">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- About PRI -->
    <section class="bg-primary">
        <div class="mx-auto">
            <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                <!-- Left Column - Image -->
                <div class="order-2 lg:order-1">
                    <div class="relative overflow-hidden">
                        <img class="h-full w-full object-cover" src="{{ asset('assets/img/pri.png') }}" alt="PRI Illustration">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                    </div>
                </div>

                <!-- Right Column - Content -->
                <div class="order-1 space-y-5 p-5 lg:order-2">
                    <div>
                        <!-- Badge -->
                        <div class="bg-primary/10 border-primary-foreground/20 mb-6 inline-flex items-center rounded-full border px-4 py-2">
                            <i class="ri-plant-line text-primary-foreground mr-1"></i>
                            <span class="text-primary-foreground text-sm font-medium">Plant Reflectance Index</span>
                        </div>

                        <!-- Title -->
                        <h2 class="text-primary-foreground mb-6 text-3xl font-bold leading-tight md:text-4xl lg:text-5xl">
                            What is PRI and Why is it
                            <span class="text-accent">Important?</span>
                        </h2>

                        <!-- Description -->
                        <p class="text-primary-foreground/80 mb-8 text-lg leading-relaxed">
                            The Photochemical Reflectance Index (PRI) is a crucial vegetation index that measures plant photosynthetic efficiency and stress levels. By analyzing light reflectance patterns, PRI helps detect early signs of plant stress, enabling proactive agricultural management and improved crop yields.
                        </p>

                        <!-- Button -->
                        <a class="bg-accent text-accent-foreground hover:bg-accent/90 inline-flex items-center rounded-md px-8 py-3 font-medium shadow-md transition-colors duration-300" href="{{ route('about-pri') }}">
                            Learn More About PRI
                            <i class="ri-arrow-right-line ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="from-background via-background/95 to-primary/10 relative overflow-hidden bg-gradient-to-br py-20">
        <!-- Background dekoratif -->
        <div class="absolute inset-0 opacity-[0.03]">
            <svg class="h-full w-full" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="faq-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <circle cx="20" cy="20" r="1.5" fill="currentColor" />
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" stroke-width="0.5" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#faq-grid)" />
            </svg>
        </div>

        <!-- Floating dekorasi -->
        <div class="bg-accent/15 absolute -bottom-32 -left-32 h-80 w-80 rounded-full blur-3xl"></div>
        <div class="bg-primary/15 absolute -right-24 -top-24 h-96 w-96 rounded-full blur-3xl"></div>


        <!-- Partikel -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="bg-primary/30 absolute left-1/4 top-1/4 h-2 w-2 animate-pulse rounded-full"></div>
            <div class="bg-accent/40 absolute right-1/3 top-3/4 h-1.5 w-1.5 animate-pulse rounded-full delay-1000"></div>
            <div class="bg-primary/20 delay-2000 absolute bottom-1/4 left-1/3 h-1 w-1 animate-pulse rounded-full"></div>
        </div>

        <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="mb-20 text-center">
                <div class="border-primary/20 from-primary/10 via-primary/5 to-accent/10 group mb-8 inline-flex items-center rounded-full border bg-gradient-to-r px-5 py-2.5 backdrop-blur-sm transition-all duration-300">
                    <div class="bg-primary/20 group-hover:bg-primary/30 mr-3 flex h-6 w-6 items-center justify-center rounded-full transition-all duration-300">
                        <i class="ri-question-line text-primary text-sm"></i>
                    </div>
                    <span class="text-primary text-sm font-semibold tracking-wide">Frequently Asked Questions</span>
                </div>

                <h2 class="text-foreground mb-8 text-4xl font-bold leading-tight tracking-tight md:text-5xl lg:text-6xl">
                    <span class="relative">
                        Got
                        <svg class="text-primary/20 absolute bottom-0 left-0 h-4 w-full" viewBox="0 0 100 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 6C20 2, 40 0, 60 2C80 4, 100 6, 100 4" stroke="currentColor" stroke-width="6" fill="none" />
                        </svg>
                    </span>
                    <br class="hidden sm:block">
                    <span class="from-primary via-primary to-accent bg-gradient-to-r bg-clip-text text-transparent">Questions?</span>
                </h2>

                <div class="relative mx-auto max-w-3xl">
                    <p class="text-foreground/80 text-lg font-light leading-relaxed md:text-xl">
                        Find comprehensive answers to common questions about our
                        <span class="text-primary font-medium">satellite-based plant monitoring system</span>
                    </p>
                    <div class="mx-auto mt-8 flex w-24 items-center justify-center">
                        <div class="via-primary/50 h-[2px] flex-1 bg-gradient-to-r from-transparent to-transparent"></div>
                        <div class="bg-primary/60 mx-3 h-2 w-2 rounded-full"></div>
                        <div class="via-primary/50 h-[2px] flex-1 bg-gradient-to-r from-transparent to-transparent"></div>
                    </div>
                </div>
            </div>

            <!-- Accordion FAQ  -->
            <div class="hs-accordion-group space-y-3">
                <!-- FAQ 1 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-1">
                    <h2 class="hs-accordion-heading" id="faq-heading-1">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-1">
                            <span class="text-lg md:text-xl">What is the Photochemical Reflectance Index (PRI)?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-1" aria-labelledby="faq-heading-1">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-plant-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    PRI is a spectral-based vegetation index used to detect physiological stress in plants, particularly related to photosynthetic efficiency and light-use efficiency variations.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-2">
                    <h2 class="hs-accordion-heading" id="faq-heading-2">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-2">
                            <span class="text-lg md:text-xl">Why use Sentinel-2 satellite imagery?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-2" aria-labelledby="faq-heading-2">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-satellite-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    Sentinel-2 imagery offers high spatial resolution, free access, and regular coverage every 5-10 days, making it ideal for routine agricultural land monitoring and analysis.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-3">
                    <h2 class="hs-accordion-heading" id="faq-heading-3">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-3">
                            <span class="text-lg md:text-xl">What types of crops can be monitored with PRI?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-3" aria-labelledby="faq-heading-3">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-seedling-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    PRI is effective for monitoring plantation and food crops such as rice, corn, sugarcane, palm oil, and plants with dense canopy coverage.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-4">
                    <h2 class="hs-accordion-heading" id="faq-heading-4">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-4">
                            <span class="text-lg md:text-xl">Do I need special equipment to use this service?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-4" aria-labelledby="faq-heading-4">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-computer-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    No special equipment required. You only need internet access to view interactive maps and analysis reports through our web platform.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-5">
                    <h2 class="hs-accordion-heading" id="faq-heading-5">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-5">
                            <span class="text-lg md:text-xl">How frequently is the data updated?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-5" aria-labelledby="faq-heading-5">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-refresh-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    Sentinel-2 imagery data is available every 5-10 days, depending on weather conditions and satellite scheduling for your specific area of interest.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="hs-accordion from-background/80 via-background/50 to-background/80 border-foreground/10 hover:border-primary/30 group relative overflow-hidden rounded-xl border bg-gradient-to-r backdrop-blur-sm transition-all duration-300 hover:shadow-xl" id="faq-6">
                    <h2 class="hs-accordion-heading" id="faq-heading-6">
                        <button class="hs-accordion-toggle text-foreground hover:text-primary group-hover:from-primary/5 group-hover:to-accent/5 flex w-full items-center justify-between p-3 text-left font-semibold transition-all duration-300 group-hover:bg-gradient-to-r group-hover:via-transparent" type="button" aria-expanded="false" aria-controls="faq-body-6">
                            <span class="text-lg md:text-xl">Is this service free or paid?</span>
                            <div class="bg-primary/10 group-hover:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-full transition-all duration-300">
                                <i class="ri-arrow-down-s-line hs-accordion-active:rotate-180 text-xl transition-transform duration-300"></i>
                            </div>
                        </button>
                    </h2>
                    <div class="hs-accordion-content hidden" id="faq-body-6" aria-labelledby="faq-heading-6">
                        <div class="border-foreground/10 border-t px-3 pb-3">
                            <div class="flex items-start space-x-4 pt-4">
                                <div class="bg-primary/20 mt-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                                    <i class="ri-money-dollar-circle-line text-primary text-sm"></i>
                                </div>
                                <p class="text-foreground/70 text-lg leading-relaxed">
                                    PantauTumbuh.id offers a free demo for 1 location. For routine monitoring and large area coverage, flexible subscription packages are available to meet your specific needs.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div class="mt-16 text-center">
                <div class="from-primary/5 via-primary/10 to-accent/5 border-primary/20 rounded-2xl border bg-gradient-to-r p-8 backdrop-blur-sm">
                    <h3 class="text-foreground mb-4 text-xl font-semibold">Still have questions?</h3>
                    <p class="text-foreground/70 mb-6">Our team is here to help you get started with satellite-based plant monitoring.</p>
                    <a class="border-primary/30 text-primary hover:bg-primary/10 hover:border-primary inline-flex items-center rounded-xl border px-8 py-3 font-semibold transition-all duration-300" href="{{ route('contact') }}">
                        Contact Support
                        <i class="ri-arrow-right-line ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section class="from-secondary/20 via-background/95 to-primary/10 py-15 relative overflow-hidden bg-gradient-to-tr">
        <!-- Decorative Background -->
        <div class="absolute inset-0 opacity-[0.02]">
            <svg class="h-full w-full" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="benefits-grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#benefits-grid)" />
            </svg>
        </div>

        <!-- Floating dekorasi -->
        <div class="bg-accent/15 absolute -left-32 -top-32 h-80 w-80 rounded-full blur-3xl"></div>
        <div class="bg-primary/15 absolute -bottom-32 -right-24 h-96 w-80 rounded-full blur-3xl"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="mx-auto mb-16 max-w-4xl text-center">
                <!-- Badge -->
                <div class="bg-primary/10 border-primary/20 mb-6 inline-flex items-center rounded-full border px-4 py-2">
                    <i class="ri-bar-chart-2-fill text-primary mr-1"></i>
                    <span class="text-primary text-sm font-medium">Benefits</span>
                </div>

                <!-- Main Title -->
                <h2 class="text-foreground mb-6 text-4xl font-bold leading-tight md:text-5xl lg:text-5xl">
                    Benefits of
                    <span class="text-primary relative">
                        PRI Monitoring
                    </span>
                </h2>

                <!-- Subtitle -->
                <p class="text-foreground/80 mx-auto max-w-3xl text-lg font-light leading-relaxed md:text-xl">
                    Discover how satellite-based plant monitoring can transform your agricultural management
                </p>
            </div>

            <!-- Benefits Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Benefit 1 -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-6 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-4 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <span class="text-xl">📊</span>
                        </div>
                        <h5 class="text-foreground mb-3 text-lg font-bold">Early Plant Stress Detection</h5>
                        <p class="text-foreground/70 text-sm leading-relaxed">
                            Identify plant problems before visual symptoms appear, enabling faster intervention and treatment.
                        </p>
                    </div>
                </div>

                <!-- Benefit 2 -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-6 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-4 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <span class="text-xl">💧</span>
                        </div>
                        <h5 class="text-foreground mb-3 text-lg font-bold">Water & Fertilizer Efficiency</h5>
                        <p class="text-foreground/70 text-sm leading-relaxed">
                            Optimize water and fertilizer usage only in areas that truly need it, reducing waste and costs.
                        </p>
                    </div>
                </div>

                <!-- Benefit 3 -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-6 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-4 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <span class="text-xl">📈</span>
                        </div>
                        <h5 class="text-foreground mb-3 text-lg font-bold">Increase Productivity</h5>
                        <p class="text-foreground/70 text-sm leading-relaxed">
                            With routine monitoring, plant productivity can increase through timely and appropriate treatment.
                        </p>
                    </div>
                </div>

                <!-- Benefit 4 -->
                <div class="bg-background/50 border-foreground/10 hover:border-primary/30 hover:shadow-primary/5 group relative rounded-2xl border p-6 backdrop-blur-sm transition-all duration-300 hover:shadow-lg">
                    <div class="from-primary/5 absolute inset-0 rounded-2xl bg-gradient-to-br to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    <div class="relative">
                        <div class="bg-primary/10 group-hover:bg-primary/20 mb-4 flex h-12 w-12 items-center justify-center rounded-xl transition-colors duration-300">
                            <span class="text-xl">📍</span>
                        </div>
                        <h5 class="text-foreground mb-3 text-lg font-bold">Remote Land Monitoring</h5>
                        <p class="text-foreground/70 text-sm leading-relaxed">
                            Monitor land conditions from anywhere without the need to physically visit the field site.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="mt-16 text-center">
                <div class="inline-flex items-center space-x-4">
                    <a class="border-primary/30 text-primary hover:bg-primary/5 rounded-xl border px-8 py-3 font-medium transition-colors duration-300" href="{{ route('peta-estimasi-pri') }}">
                        Start Monitoring
                    </a>
                    <a class="text-foreground/70 hover:text-primary font-medium transition-colors duration-300" href="{{ route('about-pri') }}">
                        Learn More →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-primary py-15 text-center">
        <div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-primary-foreground mb-6 text-4xl font-bold leading-tight md:text-5xl">
                Want to Monitor Plant Health from Above?
            </h2>
            <p class="text-primary-foreground/90 mb-8 text-lg leading-relaxed md:text-xl">
                Use <strong>free Sentinel-2 based PRI visualization</strong>, or enhance your analysis accuracy with <strong>paid high-resolution PRI</strong> using advanced processing.
            </p>
            <div class="mb-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a class="bg-background text-primary hover:bg-background/90 w-full rounded-xl px-6 py-2.5 font-semibold shadow-md transition-colors duration-300 sm:w-auto" href="{{ route('peta-estimasi-pri') }}">
                    Try Free Visualization
                </a>
                <a class="border-primary-foreground text-primary-foreground hover:bg-primary-foreground hover:text-primary w-full rounded-xl border bg-transparent px-6 py-2.5 font-semibold shadow-md transition-colors duration-300 sm:w-auto" href="{{ route('pri-estimation-map-ai') }}">
                    Premium Visualization
                </a>
            </div>
            <p class="text-primary-foreground/80 text-sm">
                Not sure yet? Consult your needs with us!
            </p>
        </div>
    </section>

    <!-- Contact Us -->
    <x-fragment.get-in-touch />

</x-app-front-layout>

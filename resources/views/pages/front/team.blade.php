@section('title', 'Team us | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - Satellite-Based Plant Health Monitoring')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-layout>
    <section class="bg-gradient-to-br from-green-50 via-blue-50 to-emerald-50 py-12">
        <div class="mx-auto mb-16 max-w-7xl px-4 py-6 text-center sm:px-6 lg:px-8">
            <h1 class="mb-4 text-4xl font-bold text-gray-900">Our Team</h1>
            <p class="mx-auto max-w-3xl text-lg text-gray-600">
                Meet the dedicated professionals behind PantauTumbuh.id who are committed to revolutionizing agricultural monitoring through satellite imagery and advanced technology.
            </p>
            <p class="mx-auto max-w-3xl text-gray-600">
                PantauTumbuh.id was developed by a multidisciplinary team consisting of geospatial experts, AI engineers, agronomists, and agricultural practitioners.
            </p>

            <img class="mx-auto mb-3 mt-8 h-auto w-full max-w-2xl rounded-md shadow-lg" src="{{ asset('assets/img/dokumentasi/dokum1.jpeg') }}" alt="PantauTumbuh.id Team">
        </div>
    </section>

    <section class="mx-auto">
        <div class="grid grid-cols-1 items-center lg:grid-cols-2">
            <!-- Left Column - Image -->
            <div class="order-2 hidden lg:order-1 lg:block">
                <div class="relative min-h-screen overflow-hidden">
                    <img class="h-screen w-full bg-cover bg-center bg-no-repeat object-cover" src="{{ asset('assets/img/sawit.png') }}" alt="Palm Tree">
                    <div class="absolute inset-0 bg-black/50"></div>
                </div>
            </div>

            <!-- Right Column - Content -->
            <div class="order-1 space-y-3 lg:order-2">
                <div class="swiper mySwiper">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum1.jpeg') }} alt="" /></div>
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum2.jpeg') }} alt="" /></div>
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum3.jpeg') }} alt="" /></div>
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum4.jpeg') }} alt="" /></div>
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum5.jpeg') }} alt="" /></div>
                        <div class="swiper-slide"><img src={{ asset('assets/img/dokumentasi/dokum6.jpeg') }} alt="" /></div>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>
        </div>
    </section>


    <!-- Contact Us -->
    <x-fragment.get-in-touch />


    @push('css')
        <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet" />
    @endpush

    @push('javascript')
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

        <script>
            const swiper = new Swiper(".mySwiper", {
                direction: "vertical",
                slidesPerView: 1,
                spaceBetween: 0,
                loop: true,
                mousewheel: false,
                grabCursor: false,
                allowTouchMove: false,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                breakpoints: {
                    1023: {
                        grabCursor: true,
                        allowTouchMove: true,
                    },
                },
            });
        </script>
    @endpush

</x-app-front-layout>

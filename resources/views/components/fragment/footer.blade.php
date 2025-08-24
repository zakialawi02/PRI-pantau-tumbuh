<footer class="bg-foreground text-background py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 md:grid-cols-3">
            <!-- Company Info -->
            <div>
                <div class="mb-8 flex items-center">
                    <x-application-logo class="mr-3 h-8 w-auto" />
                    <span class="text-background text-lg font-semibold">PantauTumbuh.id</span>
                </div>
                <p class="text-background/80 mb-4 text-sm leading-relaxed">
                    Smart satellite-based monitoring system for early detection of plant stress, directly from satellites.
                </p>
                <div class="flex space-x-4">
                    <a class="text-background/60 hover:text-primary transition-colors duration-300" href="https://x.com/geomatika_its">
                        <i class="ri-twitter-line text-lg"></i>
                    </a>
                    <a class="text-background/60 hover:text-primary transition-colors duration-300" href="https://www.instagram.com/its_geomatics/">
                        <i class="ri-instagram-line text-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-background mb-4 font-semibold">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a class="text-background/80 hover:text-primary transition-colors duration-300" href="{{ route('about-pri') }}">About</a></li>
                    <li><a class="text-background/80 hover:text-primary transition-colors duration-300" href="{{ route('what-is-sentinel-2') }}">What is Sentinel-2</a></li>
                    <li><a class="text-background/80 hover:text-primary transition-colors duration-300" href="#">Services</a></li>
                    <li><a class="text-background/80 hover:text-primary transition-colors duration-300" href="#">Team</a></li>
                    <li><a class="text-background/80 hover:text-primary transition-colors duration-300" href="#">Blog</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h4 class="text-background mb-4 font-semibold">Contact</h4>
                <div class="space-y-2">
                    <p class="text-background/80 text-sm">
                        <i class="ri-map-pin-line mr-2"></i>
                        Departemen Teknik Geomatika, ITS Surabaya
                    </p>
                    <p class="text-background/80 text-sm">
                        <i class="ri-mail-line mr-2"></i>
                        info@pantautumbuh.id
                    </p>
                    <p class="text-background/80 text-sm">
                        <i class="ri-phone-line mr-2"></i>
                        +62 31 594 730
                    </p>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-background/20 mt-8 border-t pt-8 text-center">
            <p class="text-background/60 text-sm">
                © {{ date('Y') }} PantauTumbuh.id. All rights reserved.
            </p>
        </div>
    </div>
</footer>

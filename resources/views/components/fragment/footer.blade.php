<footer class="bg-background text-foreground py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 md:grid-cols-3">
            <!-- Company Info -->
            <div>
                <div class="mb-8 flex items-center">
                    <x-application-logo class="mr-3 h-8 w-auto dark:invert" />
                    <span class="text-lg font-semibold">PantauTumbuh.id</span>
                </div>
                <p class="mb-4 text-sm leading-relaxed opacity-80">
                    Smart satellite-based monitoring system for early detection of plant stress, directly from satellites.
                </p>
                <div class="flex space-x-4">
                    <a class="hover:text-primary opacity/70 transition-colors duration-300" href="https://x.com/geomatika_its">
                        <i class="ri-twitter-line text-lg"></i>
                    </a>
                    <a class="hover:text-primary opacity/70 transition-colors duration-300" href="https://www.instagram.com/its_geomatics/">
                        <i class="ri-instagram-line text-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-background mb-4 font-semibold">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a class="hover:text-primary opacity-80 transition-colors duration-300" href="{{ route('about-pri') }}">About</a></li>
                    <li><a class="hover:text-primary opacity-80 transition-colors duration-300" href="{{ route('what-is-sentinel-2') }}">What is Sentinel-2</a></li>
                    <li><a class="hover:text-primary opacity-80 transition-colors duration-300" href="#">Services</a></li>
                    <li><a class="hover:text-primary opacity-80 transition-colors duration-300" href="#">Team</a></li>
                    <li><a class="hover:text-primary opacity-80 transition-colors duration-300" href="#">Blog</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h4 class="text-background mb-4 font-semibold">Contact</h4>
                <div class="space-y-2">
                    <p class="text-sm opacity-80">
                        <i class="ri-map-pin-line mr-2"></i>
                        Departemen Teknik Geomatika, ITS Surabaya
                    </p>
                    <p class="text-sm opacity-80">
                        <i class="ri-mail-line mr-2"></i>
                        info@pantautumbuh.id
                    </p>
                    <p class="text-sm opacity-80">
                        <i class="ri-phone-line mr-2"></i>
                        +62 897 4884 990
                    </p>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-background/20 mt-8 border-t pt-8 text-center">
            <p class="text-sm">
                © {{ date('Y') }} PantauTumbuh.id. All rights reserved. |
                <a class="hover:text-primary opacity-80 transition-colors duration-300" href="{{ route('privacy-policy') }}">Privacy Policy</a> |
                <a class="hover:text-primary opacity-80 transition-colors duration-300" href="{{ route('terms-of-service') }}">Terms of Service</a>
            </p>
        </div>
    </div>
</footer>

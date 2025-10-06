@props(['mobile' => true])

<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="/" style="animation-delay: 0.1s;">
    Home</a>
<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="{{ route('about-pri') }}" style="animation-delay: 0.2s;">About PRI</a>
<!-- Services Mega Menu -->
<div class="group relative" id="services-mega-menu">
    <button class="nav-link hover:text-accent! peer flex translate-y-4 transform items-center text-sm font-bold uppercase transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" type="button" style="animation-delay: 0.3s;">
        Services
        <svg class="ms-2.5 h-2.5 w-2.5 transition-transform duration-200 group-hover:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
        </svg>
    </button>

    <!-- Mega Menu Dropdown -->
    <div class="divide-neutral bg-background invisible absolute left-1/2 top-full z-50 mt-1 w-max max-w-4xl -translate-x-1/2 transform divide-y rounded-lg opacity-0 shadow-lg transition-all duration-300 ease-in-out group-hover:visible group-hover:opacity-100">
        <div class="p-4">
            <div class="grid gap-5 md:grid-cols-2">
                <!-- Dashboard App Services -->
                <div>
                    <h3 class="text-foreground mb-4 flex items-center text-lg font-semibold">
                        <i class="ri-side-bar-line text-primary"></i>
                        Dashboard App
                    </h3>
                    <ul class="space-y-2 text-left">
                        <li>
                            <a class="hover:bg-primary/50 text-foreground block rounded-md p-1 text-left text-sm font-semibold transition-colors duration-200" href="{{ route('appMap') }}">
                                <div class="font-semibold">Dashboard App Imagery</div>
                                <span class="text-foreground/60 text-xs capitalize">Interactive imagery dashboard and analysis tools.</span>
                            </a>
                        </li>
                        <li>
                            <a class="hover:bg-primary/50 text-foreground block rounded-md p-1 text-left text-sm font-semibold transition-colors duration-200" href="{{ route('purchase-credits.public') }}">
                                <div class="font-semibold">Buy Credits</div>
                                <span class="text-foreground/60 text-xs capitalize">Purchase credits for premium features.</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- Remote Sensing Services -->
                <div>
                    <h3 class="text-foreground mb-4 flex items-center text-lg font-semibold">
                        <svg class="text-accent mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" />
                        </svg>
                        Remote Sensing
                    </h3>
                    <ul class="space-y-2 text-left">
                        <li>
                            <a class="hover:bg-primary/50 text-foreground block rounded-md p-1 text-left text-sm font-semibold transition-colors duration-200" href="{{ route('peta-estimasi-pri') }}">
                                <div class="font-semibold">PRI Estimation Map</div>
                                <span class="text-foreground/60 text-xs capitalize">Free access preview.</span>
                            </a>
                        </li>
                        <li>
                            <a class="hover:bg-primary/50 text-foreground block rounded-md p-1 text-left text-sm font-semibold transition-colors duration-200" href="{{ route('pri-estimation-map-ai') }}">
                                <div class="font-semibold">PRI Estimation Map with AI</div>
                                <span class="text-foreground/60 text-xs capitalize">Premium access with subscription/credit.</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Call to Action Section -->
            <div class="border-muted mt-2 border-t pt-5">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h4 class="text-foreground font-semibold">Need a Custom Solution?</h4>
                        <p class="text-foreground/80 text-xs">Contact us for tailored geospatial services</p>
                    </div>
                    <div class="flex space-x-3">
                        <x-button-primary href="{{ route('contact') }}" size="small">
                            Get Quote
                        </x-button-primary>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="{{ route('what-is-sentinel-2') }}" style="animation-delay: 0.4s;">What is Sentinel-2</a>
<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="{{ route('team') }}" style="animation-delay: 0.5s;">Team</a>
<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="#" style="animation-delay: 0.6s;">Blog</a>
<a class="nav-link hover:text-accent! translate-y-4 transform text-sm font-bold transition-all duration-300 hover:scale-105 md:translate-y-0 md:opacity-100" href="{{ route('contact') }}" style="animation-delay: 0.7s;">Contact</a>

<!-- Auth  -->
<div class="{{ $mobile ? 'md:hidden' : '' }} flex translate-y-4 transform flex-col space-y-2 transition-all duration-300" style="animation-delay: 0.7s;">
    @auth
        <x-button-primary href="{{ route('admin.dashboard') }}" size="small" variant="outline">Dashboard</x-button-primary>
        <form class="inline" method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button-danger type="submit" size="small">Logout</x-button-danger>
        </form>
    @else
        <x-button-primary href="{{ route('login') }}" size="small" variant="outline">Login</x-button-primary>
        <x-button-primary href="{{ route('register') }}" size="small">Register</x-button-primary>
    @endauth
</div>

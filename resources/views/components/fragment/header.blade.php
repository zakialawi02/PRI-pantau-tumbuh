@props([
    'variant' => 'solid', // default solid
])

<nav class="{{ $variant === 'transparent' ? 'bg-transparent fixed' : 'bg-background sticky' }} left-0 right-0 top-0 z-50 transition-all duration-300" id="main-nav" data-variant="{{ $variant }}">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="{{ $variant === 'transparent' ? 'py-4' : 'py-2' }} flex items-center justify-between transition-all duration-300" id="nav-inner">

            <!-- Logo kiri -->
            <div class="flex flex-1 justify-start">
                <x-application-logo class="h-10 w-auto" />
            </div>

            <!-- Menu Tengah -->
            <div class="text-foreground {{ $variant === 'transparent' ? 'md:text-background' : '' }} bg-background fixed inset-0 z-40 hidden flex-col items-center justify-center space-y-6 whitespace-nowrap text-center uppercase transition-all duration-500 ease-in-out md:static md:z-auto md:flex md:flex-1 md:flex-row md:justify-center md:space-x-8 md:space-y-0 md:bg-transparent" id="navbar">

                <!-- Nav Menu -->
                <x-nav-menu />
            </div>

            <!-- Auth kanan (desktop) -->
            <div class="hidden flex-1 justify-end space-x-2 md:flex">
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

            <!-- Mobile Toggle -->
            <div class="md:hidden">
                <button class="text-primary relative z-50 text-3xl" id="navbar-toggle">
                    <i class="ri-menu-line" id="menu-icon"></i>
                    <i class="ri-close-line hidden" id="close-icon"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

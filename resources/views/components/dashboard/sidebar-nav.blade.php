<nav class="hs-accordion-group flex w-full flex-col flex-wrap p-3" data-hs-accordion-always-open>
    <ul class="flex flex-col space-y-1">
        @php
            $userRole = Auth::user()->role;
            $isAdmin = in_array($userRole, ['admin', 'superadmin']);
            $isSuperAdmin = $userRole === 'superadmin';
            $isUser = $userRole === 'user';
            $isSuperadmin = $userRole === 'superadmin';
        @endphp

        <!-- Main Navigation -->
        <x-dashboard.nav-item href="/" icon="ri-home-4-line" text="{{ __('Home') }}" />
        <x-dashboard.nav-item href="admin.dashboard" icon="ri-dashboard-line" text="{{ __('Dashboard') }}" />
        <x-dashboard.nav-item href="appMap" icon="ri-side-bar-line" text="{{ __('Apps Map') }}" />

        @if ($isAdmin)
            <x-dashboard.nav-accordion id="plans-accordion" icon="ri-currency-line" text="{{ __('Plans') }}">
                <x-dashboard.nav-item href="admin.plans.index" text="{{ __('Plans List') }}" />
                <x-dashboard.nav-item href="admin.voucher.create" text="{{ __('Voucher') }}" />
            </x-dashboard.nav-accordion>
        @endif

        <div class="text-base-content-muted border-foreground/20 border-b px-1 pt-3 text-sm font-bold">
            <p>{{ __('Manage') }}</p>
        </div>

        <x-dashboard.nav-item href="admin.field-area.index" icon="ri-map-pin-line" text="{{ $isAdmin ? __('Field Area') : __('My Field Area') }}" />


        <x-dashboard.nav-item href="admin.payment.index" icon="ri-currency-line" text="{{ __('Payment') }}" />
        <x-dashboard.nav-item href="#" icon="ri-notification-3-line" text="{{ __('Notification') }}" />

        <!-- Superadmin Only Section -->
        @if ($isSuperadmin)
            <x-dashboard.nav-item href="admin.users.index" icon="ri-user-line" text="{{ __('User') }}" />
            <x-dashboard.nav-item href="docs" icon="ri-file-list-3-line" text="{{ __('Route Docs') }}" target="_blank" />

            <div class="text-base-content-muted border-foreground/30 border-b px-1 pt-3 text-sm font-bold">
                <p>{{ __('Settings') }}</p>
            </div>
        @endif
    </ul>
</nav>

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
        <x-dashboard.nav-item href="/" icon="ri-home-4-line" text="Home" />
        <x-dashboard.nav-item href="admin.dashboard" icon="ri-dashboard-line" text="Dashboard" />
        <x-dashboard.nav-item href="appMap" icon="ri-side-bar-line" text="Apps Map" />

        @if ($isAdmin)
            <x-dashboard.nav-accordion id="plans-accordion" icon="ri-currency-line" text="Plans">
                <x-dashboard.nav-item href="admin.plans.index" text="Plans List" />
                <x-dashboard.nav-item href="admin.voucher.create" text="Voucher" />
            </x-dashboard.nav-accordion>
        @endif

        <div class="text-base-content-muted border-foreground/20 border-b px-1 pt-3 text-sm font-bold">
            <p>Manage</p>
        </div>
        <!-- Subscription Management -->
        @if ($isAdmin)
            <x-dashboard.nav-item href="admin.subscription.index" icon="ri-vip-crown-line" text="Subscription" />
        @elseif ($isUser)
            <x-dashboard.nav-item href="admin.subscription.index" icon="ri-vip-crown-line" text="My Subscription" />
        @endif

        <x-dashboard.nav-item href="admin.payment.index" icon="ri-currency-line" text="Payment" />
        <x-dashboard.nav-item href="#" icon="ri-notification-3-line" text="Notification" />

        <!-- Superadmin Only Section -->
        @if ($isSuperadmin)
            <x-dashboard.nav-item href="admin.users.index" icon="ri-user-line" text="User" />
            <x-dashboard.nav-item href="docs" icon="ri-file-list-3-line" text="Route Docs" target="_blank" />

            <div class="text-base-content-muted border-foreground/30 border-b px-1 pt-3 text-sm font-bold">
                <p>Settings</p>
            </div>
        @endif
    </ul>
</nav>

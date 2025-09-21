@section('title', $data['title'] ?? 'Dashboard' . ' | ' . config('app.name'))
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">Dashboard</h1>
        </div>

        <div class="grid grid-cols-1 gap-2 lg:grid-cols-3 lg:gap-4">
            <div class="lg:col-span-2">
                <x-card>
                    <div class="mb-3">
                        <h4 class="mb-0 text-xl">Welcome to Your Dashboard</h4>
                    </div>
                    <p>Select an option from the sidebar to get started.</p>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-3 transition-colors" href="{{ route('admin.profile.edit') }}">
                            <i class="ri-user-line mr-2 text-lg"></i>
                            <span>Profile Settings</span>
                        </a>
                        <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-3 transition-colors" href="{{ route('admin.subscription.index') }}">
                            <i class="ri-file-list-line mr-2 text-lg"></i>
                            <span>My Subscriptions</span>
                        </a>
                        <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-3 transition-colors" href="{{ route('admin.payment.index') }}">
                            <i class="ri-bank-line mr-2 text-lg"></i>
                            <span>My Payments</span>
                        </a>
                        @if (in_array(Auth::user()->role, ['admin', 'superadmin']))
                            <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-3 transition-colors" href="{{ route('admin.users.index') }}">
                                <i class="ri-group-line mr-2 text-lg"></i>
                                <span>User Management</span>
                            </a>
                        @endif
                    </div>
                </x-card>
            </div>
            <div class="">
                <x-card>
                    <div class="mb-3">
                        <h4 class="mb-0 text-xl">Quick Stats</h4>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-foreground/70">Subscriptions:</span>
                            <span class="font-medium">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-foreground/70">Payments:</span>
                            <span class="font-medium">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-foreground/70">Field Areas:</span>
                            <span class="font-medium">-</span>
                        </div>
                    </div>
                </x-card>

                <x-card class="mt-4">
                    <div class="mb-3">
                        <h4 class="mb-0 text-xl">Help & Support</h4>
                    </div>
                    <p class="text-foreground/70 text-sm">
                        Need help with your account or subscriptions?
                        Contact our support team for assistance.
                    </p>
                    <a class="text-primary mt-3 inline-flex items-center hover:underline" href="#">
                        <i class="ri-customer-service-line mr-1"></i>
                        Contact Support
                    </a>
                </x-card>
            </div>
        </div>
    </section>
</x-app-layout>

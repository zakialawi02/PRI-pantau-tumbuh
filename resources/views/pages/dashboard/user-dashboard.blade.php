@section('title', $data['title'] ?? 'User Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'User dashboard with your subscriptions, payments, and field areas.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">User Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

        <!-- Credit Balance Card -->
        <div class="mt-4">
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Your Credit Balance</h3>
                        <p class="mt-1 text-3xl font-bold text-blue-600">
                            {{ Auth::user()->current_credits }} Credit Points
                        </p>
                    </div>
                    <div>
                        <x-button-primary href="{{ route('admin.purchase-credits') }}">
                            <i class="ri-add-line mr-1"></i> Buy More Credits
                        </x-button-primary>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Credit points can be used to access premium features like satellite imagery processing.
                    </p>
                </div>
            </x-card>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6">
            <h2 class="mb-4 text-xl font-semibold">Quick Actions</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <x-card>
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-full bg-blue-100 p-3">
                                <i class="ri-map-2-line text-xl text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">Dashboard App</h3>
                            <p class="text-sm text-gray-600">Access interactive imagery dashboard</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800" href="{{ route('appMap') }}">
                                Go to App
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-full bg-green-100 p-3">
                                <i class="ri-image-line text-xl text-green-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">My Imagery</h3>
                            <p class="text-sm text-gray-600">View your processed imagery</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-green-600 hover:text-green-800" href="{{ route('admin.imagery.index') }}">
                                View Imagery
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-full bg-purple-100 p-3">
                                <i class="ri-file-list-2-line text-xl text-purple-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">My Payments</h3>
                            <p class="text-sm text-gray-600">View your payment history</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-800" href="{{ route('admin.payment.index') }}">
                                View Payments
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </section>
</x-app-layout>

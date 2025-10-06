@section('title', $title ?? 'Admin Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'Admin dashboard with system statistics and management overview.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

        <!-- Stats Section -->
        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-card>
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3">
                        <i class="ri-group-line text-xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-600">Total Users</h3>
                        <p class="text-2xl font-bold">{{ $data['totalUsers'] ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3">
                        <i class="ri-map-pin-line text-xl text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-600">Field Areas</h3>
                        <p class="text-2xl font-bold">{{ $data['totalFieldAreas'] ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-3">
                        <i class="ri-money-dollar-circle-line text-xl text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-600">Payments</h3>
                        <p class="text-2xl font-bold">{{ $data['totalPayments'] ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="rounded-full bg-yellow-100 p-3">
                        <i class="ri-shield-user-line text-xl text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-600">Your Role</h3>
                        <p class="text-2xl font-bold">{{ ucfirst(Auth::user()->role) }}</p>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Recent Activity Section -->
        <div class="mt-6">
            <x-card>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Recent Payments</h3>
                    <x-button-secondary href="{{ route('admin.payment.index') }}" size="small">
                        <i class="ri-history-line mr-1"></i> View All
                    </x-button-secondary>
                </div>

                @if (isset($data['recentPayments']) && $data['recentPayments']->count() > 0)
                    <div class="mt-4 space-y-3">
                        @foreach ($data['recentPayments'] as $payment)
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 hover:bg-gray-50">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-full bg-green-100 p-2">
                                            <i class="ri-money-dollar-circle-line text-green-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="font-medium">#{{ $payment->invoice_number }}</h4>
                                        <p class="text-sm text-gray-600">
                                            By {{ $payment->user->name }} • {{ $payment->paid_at ? $payment->paid_at->isoFormat('MMM D, YYYY') : 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ Number::format($payment->amount, 2, locale: app()->getLocale()) }} {{ $payment->currency }}</p>
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                        Paid
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-8 text-center">
                        <i class="ri-money-dollar-circle-line mx-auto text-3xl text-gray-400"></i>
                        <h4 class="mt-2 text-sm font-medium text-gray-900">No payments yet</h4>
                        <p class="mt-1 text-sm text-gray-500">Payments will appear here once received.</p>
                    </div>
                @endif
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
                                <i class="ri-group-line text-xl text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">Manage Users</h3>
                            <p class="text-sm text-gray-600">View and manage user accounts</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800" href="{{ route('admin.users.index') }}">
                                Manage Users
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-full bg-green-100 p-3">
                                <i class="ri-money-dollar-circle-line text-xl text-green-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">Manage Payments</h3>
                            <p class="text-sm text-gray-600">View and verify payments</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-green-600 hover:text-green-800" href="{{ route('admin.payment.index') }}">
                                View Payments
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-full bg-purple-100 p-3">
                                <i class="ri-price-tag-3-line text-xl text-purple-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">Manage Plans</h3>
                            <p class="text-sm text-gray-600">View and manage subscription plans</p>
                            <a class="mt-2 inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-800" href="{{ route('admin.plans.index') }}">
                                Manage Plans
                                <i class="ri-arrow-right-line ml-1"></i>
                            </a>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </section>
</x-app-layout>

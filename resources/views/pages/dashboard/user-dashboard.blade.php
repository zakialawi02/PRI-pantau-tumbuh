@section('title', $data['title'] ?? 'User Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'User dashboard with your subscriptions, payments, and field areas.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">User Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-blue-100 p-3">
                        <i class="ri-map-pin-line text-xl text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Field Areas</p>
                        <p class="text-2xl font-bold">{{ $userFieldAreas ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-green-100 p-3">
                        <i class="ri-file-list-line text-xl text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Subscriptions</p>
                        <p class="text-2xl font-bold">{{ $userSubscriptions ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-yellow-100 p-3">
                        <i class="ri-bank-line text-xl text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Payments</p>
                        <p class="text-2xl font-bold">{{ $userPayments ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-purple-100 p-3">
                        <i class="ri-calendar-line text-xl text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Active Subs</p>
                        <p class="text-2xl font-bold">
                            {{ $subscriptions->where('status', 'active')->count() }}
                        </p>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Subscriptions Section -->
            <div class="lg:col-span-2">
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Recent Subscriptions</h2>
                        <a class="text-primary text-sm hover:underline" href="{{ route('admin.subscription.index') }}">
                            View All
                        </a>
                    </div>

                    @if ($subscriptions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="divide-foreground/20 min-w-full divide-y">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Plan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Field Area</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Price</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-foreground/10 divide-y">
                                    @foreach ($subscriptions as $subscription)
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <div class="text-sm font-medium">{{ $subscription->plan->name ?? 'N/A' }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <div class="text-sm">{{ $subscription->fieldArea->name ?? 'N/A' }}</div>
                                                <div class="text-foreground/70 text-xs">{{ Number::format($subscription->fieldArea->area_ha ?? 0, locale: app()->getLocale()) }} ha</div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                {{ Number::currency($subscription->payments[0]->amount, $subscription->payments[0]->currency, app()->getLocale()) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <span class="@if ($subscription->status == 'active') bg-green-100 text-green-800
                                                    @elseif($subscription->status == 'expired') bg-red-100 text-red-800
                                                    @else bg-yellow-100 text-yellow-800 @endif inline-flex rounded-full px-2 text-xs font-semibold leading-5">
                                                    {{ ucfirst($subscription->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <i class="ri-file-list-line text-foreground/30 mb-3 text-4xl"></i>
                            <p class="text-foreground/70">No subscriptions found</p>
                        </div>
                    @endif
                </x-card>
            </div>

            <!-- Payments Section -->
            <div class="">
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Recent Payments</h2>
                        <a class="text-primary text-sm hover:underline" href="{{ route('admin.payment.index') }}">
                            View All
                        </a>
                    </div>

                    @if ($payments->count() > 0)
                        <div class="space-y-4">
                            @foreach ($payments as $payment)
                                <div class="border-foreground/10 rounded-lg border p-3">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm font-medium">{{ $payment->name }}</h3>
                                            <p class="text-foreground/70 text-xs">
                                                {{ $payment->subscription->plan->name ?? 'Subscription' }}
                                            </p>
                                        </div>
                                        <span class="@if ($payment->status == 'paid') bg-green-100 text-green-800
                                            @elseif($payment->status == 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($payment->status == 'expired') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800 @endif rounded-full px-2 py-1 text-xs">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <div>
                                            <div class="text-foreground/70 text-xs">
                                                {{ Number::format($payment->subscription->fieldArea->area_ha ?? 0, locale: app()->getLocale()) }} ha
                                            </div>
                                            <div class="text-sm font-medium">
                                                {{ Number::currency($payment->amount, $payment->currency, app()->getLocale()) }}
                                            </div>
                                        </div>
                                        <span class="text-foreground/70 text-xs">
                                            {{ $payment->created_at->isoFormat('D MMM YYYY') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <i class="ri-bank-line text-foreground/30 mb-3 text-4xl"></i>
                            <p class="text-foreground/70">No payments found</p>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6">
            <x-card>
                <h2 class="mb-4 text-xl font-semibold">Quick Actions</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                    <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-4 text-center transition-colors" href="{{ route('admin.subscription.index') }}">
                        <i class="ri-file-list-line mb-2 block text-2xl"></i>
                        <span class="text-sm">My Subscriptions</span>
                    </a>
                    <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-4 text-center transition-colors" href="{{ route('admin.payment.index') }}">
                        <i class="ri-bank-line mb-2 block text-2xl"></i>
                        <span class="text-sm">My Payments</span>
                    </a>
                    <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-4 text-center transition-colors" href="{{ route('admin.profile.edit') }}">
                        <i class="ri-user-line mb-2 block text-2xl"></i>
                        <span class="text-sm">Profile Settings</span>
                    </a>
                    <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-4 text-center transition-colors" href="{{ route('appMap') }}">
                        <i class="ri-map-line mb-2 block text-2xl"></i>
                        <span class="text-sm">Access Map</span>
                    </a>
                </div>
            </x-card>
        </div>
    </section>
</x-app-layout>

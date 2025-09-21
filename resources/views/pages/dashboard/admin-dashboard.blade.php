@section('title', $data['title'] ?? 'Admin Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'Admin dashboard with system statistics and management overview.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-blue-100 p-3">
                        <i class="ri-group-line text-xl text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Total Users</p>
                        <p class="text-2xl font-bold">{{ $totalUsers ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-green-100 p-3">
                        <i class="ri-file-list-line text-xl text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Active Subscriptions</p>
                        <p class="text-2xl font-bold">{{ $recentSubscriptions->where('status', 'active')->count() ?? 0 }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="mr-4 rounded-full bg-yellow-100 p-3">
                        <i class="ri-bank-line text-xl text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-foreground/70 text-sm">Payments Paid</p>
                        <p class="text-2xl font-bold">{{ $recentPayments->where('status', 'paid')->count() ?? 0 }}</p>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Recent Users -->
            <div class="lg:col-span-2">
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Recent Users</h2>
                        <a class="text-primary text-sm hover:underline" href="{{ route('admin.users.index') }}">
                            View All
                        </a>
                    </div>

                    @if (isset($recentUsers) && $recentUsers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="divide-foreground/20 min-w-full divide-y">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">User</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Role</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Registered</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-foreground/10 divide-y">
                                    @foreach ($recentUsers as $user)
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <div class="flex items-center">
                                                    <div class="h-10 w-10 flex-shrink-0">
                                                        <img class="h-10 w-10 rounded-full" src="{{ asset($user->profile_photo_path) }}" alt="{{ $user->name }}">
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium">{{ $user->name }}</div>
                                                        <div class="text-foreground/70 text-sm">{{ $user->username }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                {{ $user->email }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <span class="@if ($user->role == 'superadmin') bg-red-100 text-red-800
                                                    @elseif($user->role == 'admin') bg-blue-100 text-blue-800
                                                    @else bg-gray-100 text-gray-800 @endif inline-flex rounded-full px-2 text-xs font-semibold leading-5">
                                                    {{ ucfirst($user->role) }}
                                                </span>
                                            </td>
                                            <td class="text-foreground/70 whitespace-nowrap px-4 py-3 text-sm">
                                                {{ $user->created_at->isoFormat('D MMM YYYY') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <i class="ri-group-line text-foreground/30 mb-3 text-4xl"></i>
                            <p class="text-foreground/70">No users found</p>
                        </div>
                    @endif
                </x-card>
            </div>

            <!-- Recent Subscriptions -->
            <div class="">
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Recent Active Subscriptions</h2>
                        <a class="text-primary text-sm hover:underline" href="{{ route('admin.subscription.index') }}">
                            View All
                        </a>
                    </div>

                    @if (isset($recentSubscriptions) && $recentSubscriptions->where('status', 'active')->count() > 0)
                        <div class="space-y-4">
                            @foreach ($recentSubscriptions->where('status', 'active') as $subscription)
                                <div class="border-foreground/10 rounded-lg border p-3">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm font-medium">{{ $subscription->user->name ?? 'Unknown User' }}</h3>
                                            <p class="text-foreground/70 text-xs">
                                                {{ $subscription->plan->name ?? 'N/A' }}
                                            </p>
                                        </div>
                                        <span class="@if ($subscription->status == 'active') bg-green-100 text-green-800
                                            @elseif($subscription->status == 'expired') bg-red-100 text-red-800
                                            @else bg-yellow-100 text-yellow-800 @endif rounded-full px-2 py-1 text-xs">
                                            {{ ucfirst($subscription->status) }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <div>
                                            <div class="text-foreground/70 text-xs">
                                                {{ Number::format($subscription->fieldArea->area_ha ?? 0, locale: app()->getLocale()) }} ha
                                            </div>
                                            <div class="text-sm font-medium">
                                                {{ Number::currency($subscription->total_price, $subscription->plan->currency, app()->getLocale()) }}
                                            </div>
                                        </div>
                                        <span class="text-foreground/70 text-xs">
                                            {{ $subscription->created_at->isoFormat('D MMM YYYY') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <i class="ri-file-list-line text-foreground/30 mb-3 text-4xl"></i>
                            <p class="text-foreground/70">No active subscriptions found</p>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

        <!-- Second Row -->
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Recent Payments -->
            <div class="lg:col-span-2">
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Recent Paid Payments</h2>
                        <a class="text-primary text-sm hover:underline" href="{{ route('admin.payment.index') }}">
                            View All
                        </a>
                    </div>

                    @if (isset($recentPayments) && $recentPayments->where('status', 'paid')->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="divide-foreground/20 min-w-full divide-y">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Method</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-foreground/10 divide-y">
                                    @foreach ($recentPayments->where('status', 'paid') as $payment)
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                <div class="text-sm font-medium">{{ $payment->subscription->user->email ?? 'N/A' }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                {{ Number::currency($payment->amount, $payment->currency, app()->getLocale()) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                {{ ucfirst($payment->payment_method ?? 'N/A') }}
                                            </td>
                                            <td class="text-foreground/70 whitespace-nowrap px-4 py-3 text-sm">
                                                {{ $payment->created_at->isoFormat('D MMM YYYY') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <i class="ri-bank-line text-foreground/30 mb-3 text-4xl"></i>
                            <p class="text-foreground/70">No paid payments found</p>
                        </div>
                    @endif
                </x-card>
            </div>

            <!-- System Stats -->
            <div class="">
                <x-card>
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold">System Overview</h2>
                    </div>

                    <div class="space-y-4">
                        <div class="border-foreground/10 flex items-center justify-between border-b pb-2">
                            <span class="text-sm">Total Field Areas</span>
                            <span class="font-medium">{{ $totalFieldAreas ?? 0 }}</span>
                        </div>

                        <div class="border-foreground/10 flex items-center justify-between border-b pb-2">
                            <span class="text-sm">Total Payments</span>
                            <span class="font-medium">
                                {{ $totalPayments ?? 0 }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm">Waiting Verification Payment</span>
                            <span class="font-medium">
                                {{ $recentPayments->where('status', 'waiting_verification')->count() ?? 0 }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="mb-2 font-medium">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-2 text-center text-sm transition-colors" href="{{ route('admin.users.index') }}">
                                <i class="ri-group-line mb-1 block"></i>
                                Manage Users
                            </a>
                            <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-2 text-center text-sm transition-colors" href="{{ route('admin.plans.index') }}">
                                <i class="ri-price-tag-line mb-1 block"></i>
                                Manage Plans
                            </a>
                            <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-2 text-center text-sm transition-colors" href="{{ route('admin.payment.index') }}">
                                <i class="ri-bank-line mb-1 block"></i>
                                View Payments
                            </a>
                            <a class="border-foreground/20 hover:bg-foreground/5 rounded-lg border p-2 text-center text-sm transition-colors" href="{{ route('admin.subscription.index') }}">
                                <i class="ri-file-list-line mb-1 block"></i>
                                View Subscriptions
                            </a>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </section>
</x-app-layout>

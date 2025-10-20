@section('title', $data['title'] ?? 'User Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'User dashboard with your subscriptions, payments, and field areas.')

<x-app-layout>
    <section class="space-y-4 p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">User Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

        <!-- Credit Points Card -->
        <x-card>
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h3 class="text-foreground text-lg font-medium">Your Credit Points</h3>
                    <p class="text-primary mt-1 text-2xl font-bold">
                        {{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }} Credit Points
                    </p>
                </div>
                <div>
                    <x-button-primary href="{{ route('admin.purchase-credits') }}" size="xsmall">
                        <i class="ri-add-line mr-1"></i> Buy More Credits
                    </x-button-primary>
                </div>
            </div>
            <div>
                <p class="text-foreground/60 text-sm">
                    Credit points can be used to access premium features like satellite imagery processing.
                </p>
            </div>
        </x-card>

        <!-- Recent Activity Section -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Recent Imagery -->
            <x-card class="space-y-4">
                <h3 class="text-lg font-semibold">Recent Imagery</h3>
                @if (isset($data['recentImagery']) && $data['recentImagery']->count() > 0)
                    <div class="space-y-3">
                        @foreach ($data['recentImagery'] as $imagery)
                            <div class="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0">
                                <div>
                                    <h4 class="font-medium">{{ $imagery->stored_name ?? $imagery->original_name }}</h4>
                                    <p class="text-foreground/60 text-sm">
                                        {{ $imagery->format }} •
                                        {{ $imagery->uploaded_at ? $imagery->uploaded_at->isoFormat('MMM D, YYYY') : 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="@if ($imagery->processing_status === 'completed') bg-success/10 text-green-800
                                        @elseif($imagery->processing_status === 'failed') bg-danger/10 text-red-800
                                        @else bg-warning/10 text-yellow-800 @endif inline-flex rounded-full px-2 py-1 text-xs font-semibold">
                                        {{ ucfirst($imagery->processing_status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-foreground/50">No imagery uploaded yet.</p>
                @endif
                <div>
                    <x-button-secondary href="{{ route('admin.imagery.index') }}" size="xsmall">
                        <i class="ri-image-line mr-1"></i> View All Imagery
                    </x-button-secondary>
                </div>
            </x-card>

            <!-- Recent Payments -->
            <x-card class="space-y-4">
                <h3 class="text-lg font-semibold">Recent Payments</h3>
                @if (isset($data['recentPayments']) && $data['recentPayments']->count() > 0)
                    <div class="space-y-3">
                        @foreach ($data['recentPayments'] as $payment)
                            <div class="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0">
                                <div>
                                    <h4 class="font-medium">#{{ $payment->invoice_number }}</h4>
                                    <p class="text-foreground/60 text-sm">
                                        {{ $payment->paid_at ? $payment->paid_at->isoFormat('MMM D, YYYY') : 'N/A' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ Number::format($payment->amount, 2, locale: app()->getLocale()) }} {{ $payment->currency }}</p>
                                    <span class="bg-success/10 inline-flex rounded-full px-2 py-1 text-xs font-semibold text-green-800">
                                        Paid
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-foreground/50">No payments made yet.</p>
                @endif
                <div>
                    <x-button-secondary href="{{ route('admin.payment.index') }}" size="xsmall">
                        <i class="ri-history-line mr-1"></i> View All Payments
                    </x-button-secondary>
                </div>
            </x-card>
        </div>

        <!-- Quick Actions -->
        <h2 class="text-xl font-semibold">Quick Actions</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <x-card>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary/10 rounded-full p-3">
                            <i class="ri-map-2-line text-primary text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-foreground text-lg font-medium">Dashboard App</h3>
                        <p class="text-foreground/60 text-sm">Access interactive imagery dashboard</p>
                        <a class="text-primary hover:text-primary/80 mt-2 inline-flex items-center text-sm font-medium" href="{{ route('appMap') }}">
                            Go to App
                            <i class="ri-arrow-right-line ml-1"></i>
                        </a>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success/10 rounded-full p-3">
                            <i class="ri-image-line text-success text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-foreground text-lg font-medium">My Imagery</h3>
                        <p class="text-foreground/60 text-sm">View your processed imagery</p>
                        <a class="text-success hover:text-success/80 mt-2 inline-flex items-center text-sm font-medium" href="{{ route('admin.imagery.index') }}">
                            View Imagery
                            <i class="ri-arrow-right-line ml-1"></i>
                        </a>
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-accent/10 rounded-full p-3">
                            <i class="ri-file-list-2-line text-accent text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-foreground text-lg font-medium">My Payments</h3>
                        <p class="text-foreground/60 text-sm">View your payment history</p>
                        <a class="text-accent hover:text-accent/80 mt-2 inline-flex items-center text-sm font-medium" href="{{ route('admin.payment.index') }}">
                            View Payments
                            <i class="ri-arrow-right-line ml-1"></i>
                        </a>
                    </div>
                </div>
            </x-card>
        </div>
    </section>
</x-app-layout>

@section('title', $data['title'] ?? 'Purchase Credits')
@section('meta_description', 'Buy credit points to access premium features and services')

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="border-foreground/20 bg-neutral p-2">
                <div class="mb-6">
                    <h2 class="text-foreground text-2xl font-bold">Purchase Credit Points</h2>
                    <p class="text-foreground/70 mt-2">
                        Buy credit points to access premium features and services. Credits can be used for satellite imagery processing and other advanced features.
                    </p>
                    @if (isset($displayCurrency))
                        <p class="text-foreground/60 mt-1 text-sm">
                            Prices shown in {{ $displayCurrency === 'IDR' ? 'Indonesian Rupiah (IDR)' : 'United States Dollar (USD)' }}.
                        </p>
                    @endif
                </div>

                @if ($plans->isEmpty())
                    <div class="bg-warning/10 text-warning mb-4 rounded-lg p-4">
                        <p>No credit plans are currently available. Please check back later.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($plans as $plan)
                            <div class="border-foreground/20 bg-neutral overflow-hidden rounded-lg border shadow-sm transition-shadow hover:shadow-md">
                                <div class="p-6">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-foreground text-xl font-semibold">{{ $plan->name }}</h3>
                                        @if ($plan->isFeatured)
                                            <span class="bg-primary/10 text-primary rounded-full px-2 py-1 text-xs font-semibold">Popular</span>
                                        @endif
                                    </div>

                                    @php
                                        $displayCurrency = $plan->display_currency ?? $plan->currency;
                                        $displayPrice = $plan->display_price ?? $plan->price;
                                        $alternateCurrency = $plan->alternate_currency ?? ($displayCurrency === 'IDR' ? 'USD' : 'IDR');
                                        $alternatePrice = $plan->alternate_price ?? null;
                                    @endphp
                                    <div class="mt-4">
                                        <p class="text-foreground text-3xl font-bold">
                                            {{ Number::currency($displayPrice, $displayCurrency, app()->getLocale()) }}
                                        </p>
                                        @if ($alternatePrice)
                                            <p class="text-foreground/60 text-sm">
                                                ≈ {{ Number::currency($alternatePrice, $alternateCurrency, app()->getLocale()) }}
                                            </p>
                                        @endif
                                        <p class="text-foreground/70 mt-1">
                                            {{ $plan->credit_points }} Credit Points
                                        </p>
                                    </div>

                                    <div class="mt-6">
                                        <!-- We need to create a form to initiate the purchase -->
                                        <form method="POST" action="{{ route('orderCredit') }}">
                                            @csrf
                                            @method('POST')

                                            <input name="plan_id" type="hidden" value="{{ $plan->id }}">

                                            <button class="bg-primary/80 hover:bg-primary/90 focus:ring-primary text-neutral w-full rounded-md px-4 py-2 text-center text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2" type="submit">
                                                Purchase Now
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8">
                    <h3 class="text-foreground text-lg font-medium">How Credit Points Work</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-primary/60 text-neutral flex h-6 w-6 items-center justify-center rounded-full">
                                    1
                                </div>
                            </div>
                            <p class="text-foreground/70 ml-3">
                                Purchase credit points using one of our plans above.
                            </p>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-primary/60 text-neutral flex h-6 w-6 items-center justify-center rounded-full">
                                    2
                                </div>
                            </div>
                            <p class="text-foreground/70 ml-3">
                                Credits will be added to your account immediately after payment confirmation.
                            </p>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="bg-primary/60 text-neutral flex h-6 w-6 items-center justify-center rounded-full">
                                    3
                                </div>
                            </div>
                            <p class="text-foreground/70 ml-3">
                                Use your credits to access premium features like satellite imagery processing.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </section>
</x-app-layout>

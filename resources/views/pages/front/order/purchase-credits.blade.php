@section('title', $data['title'] ?? 'Purchase Credits Points')
@section('meta_description', 'Buy credit points to access premium features and services')

@section('title', 'PayPal Sandbox Payment Guide')

<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">

        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold md:text-4xl">Purchase Credit Points</h1>
            <p class="text-foreground/70 mt-4">
                Buy credit points to access premium features and services. Credits can be used for satellite imagery processing and other advanced features.
            </p>
            @isset($displayCurrency)
                <p class="text-foreground/60 mt-2 text-sm">
                    {{ __('Prices are shown in :currency.', ['currency' => strtoupper($displayCurrency)]) }}
                </p>
            @endisset
        </div>

        @if ($plans->isEmpty())
            <div class="bg-warning/10 text-warning mb-8 rounded-lg p-6 text-center">
                <p class="text-lg">No credit plans are currently available. Please check back later.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="border-foreground/20 bg-neutral overflow-hidden rounded-lg border shadow-sm transition-all hover:shadow-md">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-foreground text-xl font-semibold">{{ $plan->name }}</h3>
                                @if ($plan->isFeatured)
                                    <span class="bg-primary/10 text-primary rounded-full px-2 py-1 text-xs font-semibold">Popular</span>
                                @endif
                            </div>

                            <div class="mt-4">
                                <p class="text-foreground text-3xl font-bold">
                                    {{ Number::currency($plan->display_price ?? $plan->price, $plan->display_currency ?? $plan->currency, app()->getLocale()) }}
                                </p>
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

        <div class="bg-neutral mt-12 rounded-lg p-6">
            <h3 class="text-foreground text-xl font-medium">How Credit Points Work</h3>
            <div class="mt-6 space-y-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="bg-primary/60 text-neutral flex h-8 w-8 items-center justify-center rounded-full">
                            1
                        </div>
                    </div>
                    <p class="text-foreground/70 ml-4 mt-1">
                        Purchase credit points using one of our plans above.
                    </p>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="bg-primary/60 text-neutral flex h-8 w-8 items-center justify-center rounded-full">
                            2
                        </div>
                    </div>
                    <p class="text-foreground/70 ml-4 mt-1">
                        Credits will be added to your account immediately after payment confirmation.
                    </p>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="bg-primary/60 text-neutral flex h-8 w-8 items-center justify-center rounded-full">
                            3
                        </div>
                    </div>
                    <p class="text-foreground/70 ml-4 mt-1">
                        Use your credits to access premium features like satellite imagery processing.
                    </p>
                </div>
            </div>
        </div>

        @guest
            <div class="mt-8 text-center">
                <p class="text-foreground/70">
                    Already have an account? <a class="text-primary hover:underline" href="{{ route('login') }}">Sign in</a> to manage your credits.
                </p>
            </div>
        @endauth
    </div>
    </div>

</x-app-front-layout>

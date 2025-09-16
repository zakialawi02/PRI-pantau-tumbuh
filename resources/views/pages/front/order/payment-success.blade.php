@section('title', 'Payment Success')

<x-app-front-layout>
    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <div class="flex flex-col items-center justify-center py-12">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-8 text-center shadow-xl md:p-12 dark:bg-gray-800">
                <!-- Success Icon -->
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <svg class="h-12 w-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <!-- Title -->
                <h1 class="mb-4 text-3xl font-bold text-gray-900 dark:text-white">Payment Successful!</h1>

                <!-- Description -->
                <p class="mb-8 text-lg text-gray-600 dark:text-gray-300">
                    Thank you for your payment. Your transaction has been completed successfully.
                </p>

                <!-- Order Details -->
                <div class="mb-8 rounded-xl bg-gray-50 p-6 text-left dark:bg-gray-700/50">
                    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Order Summary</h2>

                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Order Number</span>
                            <span class="font-medium text-gray-900 dark:text-white">#ORD-{{ date('Ymd') }}-{{ rand(1000, 9999) }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Date</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ date('F j, Y') }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Amount</span>
                            <span class="font-medium text-gray-900 dark:text-white">IDR {{ number_format(500000, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Payment Method</span>
                            <span class="font-medium text-gray-900 dark:text-white">Bank Transfer</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col justify-center gap-4 sm:flex-row">
                    <a class="bg-primary hover:bg-primary/90 focus:ring-primary rounded-lg px-6 py-3 font-medium text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2" href="{{ route('admin.dashboard') }}">
                        Go to Dashboard
                    </a>

                    <a class="rounded-lg bg-gray-100 px-6 py-3 font-medium text-gray-800 transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600" href="{{ route('home') }}">
                        Back to Home
                    </a>
                </div>

                <!-- Additional Info -->
                <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">
                    Need help? <a class="text-primary hover:underline" href="{{ route('contact') }}">Contact our support team</a>
                </p>
            </div>
        </div>
    </div>
</x-app-front-layout>

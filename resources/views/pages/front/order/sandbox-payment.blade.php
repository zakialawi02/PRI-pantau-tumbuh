@section('title', 'PayPal Sandbox Payment Guide')

<x-app-front-layout>
    <div class="mx-auto max-w-4xl p-4 md:p-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                    <a class="text-base-content-muted hover:text-primary inline-flex items-center text-sm font-medium" href="{{ route('home') }}">
                        <i class="ri-home-4-line mr-2"></i>
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="ri-arrow-right-s-line text-base-content-muted mx-2 text-sm"></i>
                        <a class="text-base-content-muted hover:text-primary ms-1 text-sm font-medium md:ms-2" href="{{ route('checkoutOrder') }}">Checkout</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="ri-arrow-right-s-line text-base-content-muted mx-2 text-sm"></i>
                        <span class="text-primary ms-1 text-sm font-medium md:ms-2">Sandbox Payment</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Judul -->
        <div class="mb-8 text-center">
            <h1 class="text-foreground text-3xl font-bold">PayPal Sandbox Payment Guide</h1>
            <p class="text-base-content-muted mt-2">Learn how to complete payments in our demo environment</p>
        </div>

        <!-- Konten Utama -->
        <div class="space-y-8">
            <!-- Intro -->
            <div class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-start">
                    <div class="mr-4 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                        <i class="ri-information-line text-xl text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="text-foreground text-xl font-semibold">Demo Environment Notice</h2>
                        <p class="text-base-content-muted mt-2">
                            This application is currently in demo stage. All PayPal payments are processed through PayPal's sandbox environment,
                            which means no real money is transferred. This guide will help you complete the payment simulation.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-foreground mb-6 text-2xl font-semibold">How to Complete PayPal Sandbox Payment</h2>

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white">
                                1
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Select PayPal as Payment Method</h3>
                            <p class="text-base-content-muted mt-2">
                                On the checkout page, select "PayPal" as your payment method and click "Complete Payment".
                            </p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white">
                                2
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Redirect to PayPal Sandbox</h3>
                            <p class="text-base-content-muted mt-2">
                                You will be redirected to PayPal's sandbox environment. This looks like the real PayPal website
                                but is specifically for testing purposes.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white">
                                3
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Login to PayPal Sandbox</h3>
                            <p class="text-base-content-muted mt-2">
                                Use the following sandbox account credentials to log in:
                            </p>
                            <div class="mt-3 rounded-lg bg-gray-50 p-4">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Email</p>
                                        <p class="font-mono">sb-{{ substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8) }}@personal.example.com</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Password</p>
                                        <p class="font-mono">sandbox_password</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-base-content-muted mt-3 text-sm">
                                Note: These are sample credentials. In a real implementation, you would receive actual sandbox credentials from your system administrator.
                            </p>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white">
                                4
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Review and Confirm Payment</h3>
                            <p class="text-base-content-muted mt-2">
                                Review the payment details on PayPal. The amount should match your order total.
                                Click "Pay Now" to confirm the payment.
                            </p>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-white">
                                5
                            </div>
                        </div>
                        <div>
                            <h3 class="text-foreground text-lg font-medium">Return to Our Application</h3>
                            <p class="text-base-content-muted mt-2">
                                After confirming the payment, you will be automatically redirected back to our application.
                                Your payment status will be updated to "Paid" and your subscription will be activated.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Transfer Section -->
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-foreground mb-6 text-2xl font-semibold">Bank Transfer Payment Method</h2>

                <div class="flex items-start">
                    <div class="mr-4 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                        <i class="ri-bank-line text-xl text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-foreground text-xl font-semibold">Demo Bank Transfer Process</h3>
                        <p class="text-base-content-muted mt-2">
                            For bank transfer payments in our demo environment, no actual money transfer is required.
                            You can simulate the payment process by uploading any image as your payment proof.
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <!-- Bank Transfer Step 1 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">
                                1
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Select Bank Transfer</h3>
                            <p class="text-base-content-muted mt-2">
                                On the checkout page, select "Bank Transfer" as your payment method.
                            </p>
                        </div>
                    </div>

                    <!-- Bank Transfer Step 2 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">
                                2
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">View Bank Account Details</h3>
                            <p class="text-base-content-muted mt-2">
                                You will see our demo bank account information. In a real environment, you would transfer the exact amount to this account.
                            </p>
                        </div>
                    </div>

                    <!-- Bank Transfer Step 3 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">
                                3
                            </div>
                            <div class="h-full w-0.5 bg-gray-200"></div>
                        </div>
                        <div class="pb-6">
                            <h3 class="text-foreground text-lg font-medium">Upload Payment Proof</h3>
                            <p class="text-base-content-muted mt-2">
                                In the demo environment, you don't need to transfer real money. Simply upload any image file as your payment proof.
                                This simulates the process of uploading your bank transfer receipt.
                            </p>
                        </div>
                    </div>

                    <!-- Bank Transfer Step 4 -->
                    <div class="flex">
                        <div class="mr-4 flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">
                                4
                            </div>
                        </div>
                        <div>
                            <h3 class="text-foreground text-lg font-medium">Complete Payment</h3>
                            <p class="text-base-content-muted mt-2">
                                After uploading your "payment proof", click "Complete Payment". Your payment status will be updated to "Paid"
                                and your subscription will be activated immediately in the demo environment.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-foreground mb-4 text-xl font-semibold">Important Notes</h2>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="ri-checkbox-circle-line mr-2 mt-0.5 text-green-500"></i>
                        <span>No real money is transferred in this demo environment.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-checkbox-circle-line mr-2 mt-0.5 text-green-500"></i>
                        <span>All transactions are processed through PayPal's secure sandbox environment.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-checkbox-circle-line mr-2 mt-0.5 text-green-500"></i>
                        <span>Your account will be activated immediately after successful payment.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-alert-line mr-2 mt-0.5 text-yellow-500"></i>
                        <span>In production, real PayPal accounts and actual money transfers will be used.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    @push('css')
        <style>
            .demo-preview-banner {
                display: none;
            }
        </style>
    @endpush
</x-app-front-layout>

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card class="p-0!">
            <!-- Invoice Header -->
            <div class="from-primary to-secondary text-background inset-0 rounded-t-md bg-gradient-to-r p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="mb-2 text-3xl font-bold">INVOICE</h1>
                        <p class="text-background">Invoice #{{ $payment->id }}</p>
                        <p class="text-background">Date: {{ $payment->created_at->format('F d, Y H:i:s') }}</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-xl font-semibold">PantauTumbuh.id</h2>
                        <p class="text-background">Pantau Tumbuh</p>
                        <p class="text-background">+1 (555) 123-4567</p>
                        <p class="text-background">support@pantautumbuh.id</p>
                    </div>
                </div>
            </div>

            <!-- Invoice Content -->
            <div class="p-6">
                <!-- Bill To Section -->
                <div class="mb-8">
                    <h3 class="text-base-content mb-3 text-lg font-semibold">Bill To:</h3>
                    <div class="bg-base-content-muted/20 rounded-lg p-4">
                        <p class="text-base-content font-medium">Name: {{ $payment->name ?? '-' }}</p>
                        <p class="text-base-content">Email: {{ $payment->email ?? '-' }}</p>
                        <p class="text-base-content">Phone: {{ $payment->phone ?? '-' }}</p>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="mb-8">
                    <h3 class="text-base-content mb-4 text-lg font-semibold">Order Information</h3>
                    <div class="overflow-x-auto">
                        <table class="border-border w-full border-collapse border">
                            <thead class="bg-background">
                                <tr>
                                    <th class="border-border text-foreground border px-4 py-3 text-left font-semibold">Description</th>
                                    <th class="border-border text-foreground border px-4 py-3 text-center font-semibold">Area</th>
                                    <th class="border-border text-foreground border px-4 py-3 text-center font-semibold">Rate per Hectare</th>
                                    <th class="border-border text-foreground border px-4 py-3 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hover:bg-base-content-muted/20">
                                    <td class="border-border border px-4 py-3">
                                        <div class="font-medium">{{ $payment->subscription->plan->name }}</div>
                                        <div class="text-base-content-muted text-sm">Field: {{ $payment->subscription->fieldArea->name }}</div>
                                    </td>
                                    <td class="border-border border px-4 py-3 text-center">{{ $payment->subscription->fieldArea->area_ha }} ha</td>
                                    <td class="border-border border px-4 py-3 text-center">{{ number_format($payment->subscription->price_per_hectare, 2) }} {{ $payment->currency }}</td>
                                    <td class="border-border border px-4 py-3 text-right font-medium">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Total Section -->
                <div class="mb-8 flex justify-end">
                    <div class="w-64">
                        <div class="bg-base-content-muted/20 rounded-lg p-4">
                            <div class="border-border flex items-center justify-between border-b py-2">
                                <span class="text-base-content-muted">Subtotal:</span>
                                <span class="font-medium">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                            </div>
                            <div class="border-border flex items-center justify-between border-b py-2">
                                <span class="text-base-content-muted">Tax (0%):</span>
                                <span class="font-medium">0.00 {{ $payment->currency }}</span>
                            </div>
                            <div class="border-border flex items-center justify-between border-t-2 py-3">
                                <span class="text-base-content text-lg font-semibold">Total:</span>
                                <span class="text-primary text-lg font-bold">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Instructions -->
                <div class="mb-8">
                    <h3 class="text-base-content mb-4 text-lg font-semibold">Payment Instructions</h3>
                    <div class="border-info bg-info/20 rounded-r-lg border-l-4 p-4">
                        @if ($payment->payment_method === 'manual' || $payment->payment_method === 'bank_transfer')
                            <h4 class="text-info mb-2 font-semibold">Bank Transfer Details:</h4>
                            <div class="grid gap-4 text-sm md:grid-cols-3">
                                <div>
                                    <span class="text-foreground font-medium">Bank:</span>
                                    <p class="text-base-content-muted">BCA</p>
                                </div>
                                <div>
                                    <span class="text-foreground font-medium">Account Number:</span>
                                    <p class="text-base-content-muted">1234567890</p>
                                </div>
                                <div>
                                    <span class="text-foreground font-medium">Account Name:</span>
                                    <p class="text-base-content-muted">PT ABCDEFG</p>
                                </div>
                            </div>
                            <p class="mt-3 text-sm text-blue-700">
                                <strong>Note:</strong> After transfer, please upload your payment proof below for admin verification.
                            </p>
                        @else
                            <p class="text-blue-800">Payment Method: <span class="font-semibold">{{ ucfirst($payment->payment_method) }}</span></p>
                            <p class="mt-2 text-sm text-blue-700">Please proceed with payment through the related gateway.</p>
                        @endif
                    </div>
                </div>

                <!-- Upload Payment Proof (for manual payments) -->
                @if (($payment->status === 'pending' && $payment->payment_method === 'manual') || $payment->payment_method === 'bank_transfer')
                    <div class="mb-8">
                        <h3 class="text-base-content mb-4 text-lg font-semibold">Upload Payment Proof</h3>
                        <div class="border-border bg-base-content-muted/20 rounded-lg border-2 border-dashed p-6">
                            <form class="space-y-4" action="{{ url('/checkout/' . $payment->id . '/upload-proof') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label for="bank_name">Sender Bank</x-input-label>
                                        <x-text-input name="bank_name" size="small" placeholder="e.g., BCA, Mandiri" required />
                                    </div>
                                    <div>
                                        <x-input-label for="account_number">Sender Account Number</x-input-label>
                                        <x-text-input name="account_number" size="small" placeholder="e.g., 1234567890" required />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="account_name">Account Holder Name</x-input-label>
                                    <x-text-input name="account_name" size="small" placeholder="Enter account holder name" required />
                                </div>
                                <div>
                                    <label class="text-foreground mb-2 block text-sm font-medium">Upload Transfer Receipt</label>
                                    <input class="border-border file:bg-info/20 hover:file:bg-info/50 focus:ring-info w-full rounded-md border px-3 py-2 file:mr-4 file:rounded-md file:border-0 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 focus:border-transparent focus:outline-none focus:ring-2" name="proof_image" type="file" accept="image/*" required>
                                    <p class="text-base-content-muted/200 mt-1 text-xs">Accepted formats: JPG, PNG, PDF (Max: 1MB)</p>
                                </div>
                                <x-button-primary class="w-full">
                                    Upload Payment Proof
                                </x-button-primary>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Payment Status -->
                <div class="mb-6">
                    <div class="@if ($payment->status === 'paid') bg-green-100 border border-green-300
                        @elseif($payment->status === 'waiting_verification') bg-yellow-100 border border-yellow-300
                        @elseif($payment->status === 'failed') bg-red-100 border border-red-300
                        @elseif($payment->status === 'refunded') bg-blue-100 border border-blue-300
                        @elseif($payment->status === 'chargeback') bg-purple-100 border border-purple-300
                        @else bg-background border border-border @endif flex items-center justify-between rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="mr-3">
                                @if ($payment->status === 'paid')
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @elseif($payment->status === 'waiting_verification')
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @elseif($payment->status === 'failed')
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @elseif($payment->status === 'refunded')
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                    </svg>
                                @elseif($payment->status === 'chargeback')
                                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                @else
                                    <svg class="text-base-content-muted h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <h4 class="@if ($payment->status === 'paid') text-green-800
                                    @elseif($payment->status === 'waiting_verification') text-yellow-800
                                    @elseif($payment->status === 'failed') text-red-800
                                    @elseif($payment->status === 'refunded') text-blue-800
                                    @elseif($payment->status === 'chargeback') text-purple-800
                                    @else text-base-content @endif font-semibold">
                                    Payment Status: {{ ucwords(str_replace('_', ' ', $payment->status)) }}
                                </h4>
                                @if ($payment->status === 'paid')
                                    <p class="text-sm text-green-600">Payment completed successfully</p>
                                @elseif($payment->status === 'waiting_verification')
                                    <p class="text-sm text-yellow-600">Payment proof received, waiting for verification</p>
                                @elseif($payment->status === 'failed')
                                    <p class="text-sm text-red-600">Payment failed, please try again</p>
                                @elseif($payment->status === 'refunded')
                                    <p class="text-sm text-blue-600">Payment has been refunded</p>
                                @elseif($payment->status === 'chargeback')
                                    <p class="text-sm text-purple-600">Payment disputed by customer</p>
                                @else
                                    <p class="text-base-content-muted text-sm">Payment is pending</p>
                                @endif
                            </div>
                        </div>
                        <span class="@if ($payment->status === 'paid') bg-green-200 text-green-800
                            @elseif($payment->status === 'waiting_verification') bg-yellow-200 text-yellow-800
                            @elseif($payment->status === 'failed') bg-red-200 text-red-800
                            @elseif($payment->status === 'refunded') bg-blue-200 text-blue-800
                            @elseif($payment->status === 'chargeback') bg-purple-200 text-purple-800
                            @else bg-border text-base-content @endif rounded-full px-3 py-1 text-sm font-semibold">
                            {{ ucwords(str_replace('_', ' ', $payment->status)) }}
                        </span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-base-content-muted/200 border-border border-t pt-6 text-center text-sm">
                    <p>Thank you for choosing PantauTumbuh.id!</p>
                    <p>For questions about this invoice, please contact our support team at support@pantautumbuh.id</p>
                </div>
            </div>
        </x-card>
    </section>
</x-app-layout>

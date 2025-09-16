@section('title', $data['title'] ?? '')
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ $data['title'] }}</h1>
            <p class="mt-1 text-gray-600">
                @if (auth()->user()->role === 'user')
                    View and track your payment transactions
                @else
                    Manage all payment transactions in the system
                @endif
            </p>
        </div>

        <x-card>
            <div class="table-container">
                <table class="display table" id="myTable">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                #
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Invoice Number
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Customer Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Payment Method
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Due Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Order Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-y divide-gray-200">
                        <!-- DataTables will populate this -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    <!-- Payment Details Modal (Flowbite) -->
    <div class="fixed left-0 right-0 top-0 z-50 hidden h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden md:inset-0" id="payment-modal" data-modal-backdrop="static" data-modal-keyboard="false" aria-hidden="true" tabindex="-1">
        <div class="relative max-h-full w-full max-w-2xl p-4">
            <!-- Modal content -->
            <div class="bg-background relative rounded-lg shadow">
                <!-- Modal header -->
                <div class="flex items-center justify-between rounded-t border-b p-4 md:p-5">
                    <h3 class="text-base-content text-xl font-semibold">
                        Payment Details
                    </h3>
                    <button class="hover:text-base-content text-base-content-muted hover:bg-base-content/50 ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm" data-modal-hide="payment-modal" type="button">
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <!-- Modal body -->
                <div class="space-y-4 p-4 md:p-5">
                    <div class="flex items-center justify-center py-8" id="payment-loading">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]" role="status">
                            <span class="!absolute !-m-px !h-px !w-px !overflow-hidden !whitespace-nowrap !border-0 !p-0 ![clip:rect(0,0,0,0)]">Loading...</span>
                        </div>
                        <span class="text-base-content-muted ml-2">Loading payment details...</span>
                    </div>

                    <div class="hidden" id="payment-content">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Invoice Number</label>
                                    <p class="text-base-content text-sm" id="modal-invoice-number">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Customer Name</label>
                                    <p class="text-base-content text-sm" id="modal-customer-name">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Email</label>
                                    <p class="text-base-content text-sm" id="modal-email">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Phone</label>
                                    <p class="text-base-content text-sm" id="modal-phone">-</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Amount</label>
                                    <p class="text-base-content text-sm font-semibold" id="modal-amount">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Status</label>
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" id="modal-status">-</span>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Payment Method</label>
                                    <p class="text-base-content text-sm" id="modal-payment-method">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Order Date</label>
                                    <p class="text-base-content text-sm" id="modal-order-date">-</p>
                                </div>
                            </div>
                        </div>

                        @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                            <div class="border-border mt-6 border-t pt-4">
                                <h4 class="text-base-content mb-3 text-lg font-medium">Update Payment Status</h4>
                                <form class="space-y-4" id="status-update-form" action="">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="text-foreground block text-sm font-medium" for="payment-status-select">Payment Status</label>
                                        <select class="border-border focus:border-primary focus:ring-primary mt-1 block w-full rounded-md border px-3 py-2 shadow-sm focus:outline-none" id="payment-status-select" name="status">
                                            <option value="pending">Pending</option>
                                            <option value="waiting_verification">Waiting Verification</option>
                                            <option value="paid">Paid</option>
                                            <option value="failed">Failed</option>
                                            <option value="refunded">Refunded</option>
                                            <option value="chargeback">Chargeback</option>
                                        </select>
                                    </div>
                                    <div class="flex justify-end space-x-3">
                                        <x-button-primary id="update-status-btn" type="submit" size="small">
                                            Update Status
                                        </x-button-primary>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
                <!-- Modal footer -->
                <div class="border-border flex items-center rounded-b border-t p-4 md:p-5">
                    <x-button-light data-modal-hide="payment-modal" type="button" size="small">
                        Close
                    </x-button-light>
                </div>
            </div>
        </div>
    </div>

    @include('components.dependencies._datatables')

    @push('javascript')
        <script>
            $(document).ready(function() {
                let urlParams = new URLSearchParams(window.location.search);
                let pageParam = parseInt(urlParams.get('page')) || 1;
                let limitParam = parseInt(urlParams.get('limit')) || 10;
                let currentPaymentId = null;

                let table = new DataTable('#myTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    displayStart: (pageParam - 1) * limitParam,
                    pageLength: limitParam,
                    ajax: {
                        url: "{{ url()->full() }}",
                        beforeSend: function() {
                            dt_showLoader("#myTable");
                        },
                        complete: function() {
                            dt_hideLoader();
                        }
                    },
                    lengthMenu: [
                        [10, 15, 25, 50, -1],
                        [10, 15, 25, 50, "All"]
                    ],
                    language: {
                        paginate: {
                            previous: '<i class="ri-arrow-left-s-line"></i>',
                            next: '<i class="ri-arrow-right-s-line"></i>'
                        },
                    },
                    order: [
                        [7, 'desc'] // Order by created date descending
                    ],
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false,
                            className: 'px-3 py-2 whitespace-nowrap text-sm'
                        },
                        {
                            data: 'invoice_number',
                            name: 'invoice_number',
                            className: 'px-3 py-2 whitespace-nowrap text-sm font-medium'
                        },
                        {
                            data: 'customer_name',
                            name: 'customer_name',
                            className: 'px-3 py-2 whitespace-nowrap text-sm'
                        },
                        {
                            data: 'amount',
                            name: 'amount',
                            className: 'px-3 py-2 whitespace-nowrap text-sm'
                        },
                        {
                            data: 'status',
                            name: 'status',
                            className: 'px-3 py-2 whitespace-nowrap text-sm',
                            render: function(data, type, row) {
                                const statusConfig = {
                                    'paid': {
                                        class: 'bg-green-100 text-green-800',
                                        text: 'Paid'
                                    },
                                    'pending': {
                                        class: 'bg-yellow-100 text-yellow-800',
                                        text: 'Pending'
                                    },
                                    'waiting_verification': {
                                        class: 'bg-blue-100 text-blue-800',
                                        text: 'Waiting Verification'
                                    },
                                    'failed': {
                                        class: 'bg-red-100 text-red-800',
                                        text: 'Failed'
                                    },
                                    'refunded': {
                                        class: 'bg-gray-100 text-gray-800',
                                        text: 'Refunded'
                                    },
                                    'chargeback': {
                                        class: 'bg-red-100 text-red-800',
                                        text: 'Chargeback'
                                    },
                                    default: {
                                        class: 'bg-gray-100 text-gray-800',
                                        text: 'Unknown'
                                    }
                                };

                                const config = statusConfig[data] || statusConfig.default;
                                return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.class}">
                                    ${config.text}
                                </span>`;
                            }
                        },
                        {
                            data: 'payment_method',
                            name: 'payment_method',
                            className: 'px-3 py-2 whitespace-nowrap text-sm'
                        },
                        {
                            data: 'due_date',
                            name: 'due_date',
                            className: 'px-3 py-2 whitespace-nowrap text-sm',
                            render: function(data, type, row) {
                                if (!data) return '-';

                                const dueDate = new Date(data);
                                const now = new Date();

                                let dateString = formatCustomDate(data);

                                // If payment is paid, show paid_at date
                                if (row.status === 'paid' && row.paid_at) {
                                    dateString += '<br><span class="text-xs text-gray-500">Paid: ' + formatCustomDate(row.paid_at) + '</span>';
                                }

                                // Check if overdue (only if not paid)
                                if (row.status !== 'paid' && dueDate < now) {
                                    dateString += ' <span class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Overdue</span>';
                                } else if (row.status !== 'paid') {
                                    // Check if due within 2 hours (only if not paid)
                                    const timeDifference = dueDate.getTime() - now.getTime();
                                    const hoursUntilDue = timeDifference / (1000 * 60 * 60);

                                    if (hoursUntilDue <= 2 && hoursUntilDue > 0) {
                                        dateString += ' <span class="ml-2 inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-800">Due Soon</span>';
                                    }
                                }

                                // If payment is paid, show paid status instead of overdue
                                if (row.status === 'paid' && row.paid_at) {
                                    dateString += ' <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Paid</span>';
                                }

                                return dateString;
                            }
                        },
                        {
                            data: 'created_at',
                            name: 'created_at',
                            className: 'px-3 py-2 whitespace-nowrap text-sm',
                            render: function(data) {
                                return formatCustomDate(data);
                            }
                        },
                        {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false,
                            className: 'px-3 py-2 whitespace-nowrap text-sm font-medium'
                        }
                    ]
                });

                // Handle pagination URL updates
                table.on('draw', function() {
                    var info = table.page.info();
                    var currentPage = info.page + 1;
                    var pageLength = info.length;

                    // Update URL parameters
                    var newUrl = new URL(window.location);
                    newUrl.searchParams.set('page', currentPage);
                    newUrl.searchParams.set('limit', pageLength);
                    window.history.replaceState({}, '', newUrl);
                });

                const paymentModal = new Modal(document.getElementById('payment-modal'), {
                    backdrop: 'static',
                    backdropClasses: 'bg-foreground/60 fixed inset-0 z-40',
                });
                $(document).on('click', '[data-modal-hide="payment-modal"]', function() {
                    paymentModal.hide();
                });

                // Open payment modal when .payment-status button is clicked
                $('body').on('click', '.payment-status', function(e) {
                    e.preventDefault();
                    currentPaymentId = $(this).data('id');

                    paymentModal.show();

                    // Show loading state
                    $('#payment-loading').removeClass('hidden');
                    $('#payment-content').addClass('hidden');

                    // Fetch payment data
                    $.ajax({
                        type: 'GET',
                        url: `/dashboard/payments/${currentPaymentId}/data`,
                        success: function(response) {
                            if (response.success) {
                                const payment = response.payment;

                                // Populate modal with payment data
                                $('#modal-invoice-number').text('#' + payment.id.substr(0, 8));
                                $('#modal-customer-name').text(payment.name || '-');
                                $('#modal-email').text(payment.email || '-');
                                $('#modal-phone').text(payment.phone || '-');
                                $('#modal-amount').text(parseFloat(payment.amount).toLocaleString('en-US', {
                                    minimumFractionDigits: 2
                                }) + ' ' + payment.currency.toUpperCase());
                                $('#modal-payment-method').text(payment.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                                $('#modal-order-date').text(formatCustomDate(payment.created_at));

                                // Set status badge with proper styling
                                const statusConfig = {
                                    'paid': {
                                        class: 'bg-green-100 text-green-800',
                                        text: 'Paid'
                                    },
                                    'pending': {
                                        class: 'bg-yellow-100 text-yellow-800',
                                        text: 'Pending'
                                    },
                                    'waiting_verification': {
                                        class: 'bg-blue-100 text-blue-800',
                                        text: 'Waiting Verification'
                                    },
                                    'failed': {
                                        class: 'bg-red-100 text-red-800',
                                        text: 'Failed'
                                    },
                                    'refunded': {
                                        class: 'bg-gray-100 text-gray-800',
                                        text: 'Refunded'
                                    },
                                    'chargeback': {
                                        class: 'bg-red-100 text-red-800',
                                        text: 'Chargeback'
                                    }
                                };

                                const status = statusConfig[payment.status] || {
                                    class: 'bg-gray-100 text-gray-800',
                                    text: 'Unknown'
                                };

                                let statusText = status.text;
                                if (payment.status === 'paid' && payment.paid_at) {
                                    statusText += ' (' + formatCustomDate(payment.paid_at) + ')';
                                }

                                $('#modal-status').attr('class', 'inline-flex px-2 py-1 text-xs font-semibold rounded-full ' + status.class).html(statusText);

                                @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                    // Set current status in select dropdown
                                    $('#payment-status-select').val(payment.status);
                                @endif

                                // Hide loading and show content
                                $('#payment-loading').addClass('hidden');
                                $('#payment-content').removeClass('hidden');
                            } else {
                                if (MyZkToast) {
                                    MyZkToast.error('Failed to load payment data');
                                }
                                paymentModal.hide();
                            }
                        },
                        error: function(xhr) {
                            console.error('Error fetching payment data:', xhr);
                            if (MyZkToast) {
                                MyZkToast.error('Failed to load payment data');
                            }
                            paymentModal.hide();
                        }
                    });
                });


                @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                    // Update payment status using form submission
                    $('#status-update-form').on('submit', function(e) {
                        e.preventDefault();

                        if (!currentPaymentId) {
                            MyZkToast.error('No payment selected');
                            return;
                        }

                        const status = $('#payment-status-select').val();
                        const $form = $(this);
                        const $btn = $('#update-status-btn');
                        const originalText = $btn.text();

                        $.ajax({
                            type: 'PUT',
                            url: `/dashboard/payments/${currentPaymentId}/status`,
                            data: {
                                status: status,
                                _token: '{{ csrf_token() }}'
                            },
                            beforeSend: function() {
                                $btn.prop('disabled', true).text('Updating...');
                                $form.find('select').prop('disabled', true);
                            },
                            success: function(response) {
                                if (response.success) {
                                    paymentModal.hide();
                                    table.ajax.reload();

                                    MyZkToast.success(response.message || 'Payment status updated successfully');
                                } else {
                                    MyZkToast.error(response.message || 'Failed to update payment status');
                                }
                            },
                            error: function(xhr) {
                                console.error('Error updating status:', xhr);
                                let errorMessage = 'Failed to update payment status';

                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessage = xhr.responseJSON.message;
                                } else if (xhr.status === 403) {
                                    errorMessage = 'Unauthorized access';
                                } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                    const errors = Object.values(xhr.responseJSON.errors).flat();
                                    errorMessage = errors.join(', ');
                                }

                                MyZkToast.error(errorMessage);
                            },
                            complete: function() {
                                $btn.prop('disabled', false).text(originalText);
                                $form.find('select').prop('disabled', false);
                            }
                        });
                    });
                @endif
            });
        </script>
    @endpush
</x-app-layout>

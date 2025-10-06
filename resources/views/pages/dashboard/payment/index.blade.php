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

    <!-- Payment Details Modal  -->
    <div class="hs-overlay z-80 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="payment-modal" role="dialog" aria-labelledby="payment-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-lg">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Payment Details
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#payment-modal" type="button" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body overflow-y-auto p-4">
                    <div class="hidden animate-pulse" id="modal-loader-data" role="status">
                        <div class="bg-base-content-muted mx-auto mb-4 h-2.5 w-60 rounded-full"></div>
                        <div class="w-50 bg-base-content-muted mx-auto mb-4 h-2.5 rounded-full"></div>
                        <span class="sr-only">Loading...</span>
                    </div>

                    <div class="" id="payment-content">
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
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Due Date</label>
                                    <p class="text-base-content text-sm" id="modal-due-date">-</p>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Payment Status Modal -->
    <div class="hs-overlay z-90 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="update-status-modal" role="dialog" aria-labelledby="update-status-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-md">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Update Payment Status
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#update-status-modal" type="button" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body overflow-y-auto p-4">
                    <form id="update-status-form">
                        @csrf
                        @method('PUT')
                        <input id="payment-id" name="payment_id" type="hidden">
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Current Status</label>
                            <p class="inline-block rounded-full px-2 py-1 text-sm font-medium" id="current-status-display"></p>
                        </div>

                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="status">New Status</label>
                            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="status" name="status">
                                <option value="pending">Pending</option>
                                <option value="waiting_verification">Waiting Verification</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                                <option value="chargeback">Chargeback</option>
                            </select>
                        </div>

                        <!-- Payment Proof Section -->
                        <div class="mb-4" id="payment-proof-section" style="display: none;">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Payment Proof</label>
                            <div class="mt-1">
                                <img class="h-auto max-w-full cursor-pointer rounded-lg border" id="payment-proof-image" src="" alt="Payment Proof" style="max-height: 200px;" onclick="window.openProofModal(this.src)">
                                <p class="mt-1 text-xs text-gray-500">Click image to view larger version</p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" data-hs-overlay="#update-status-modal" type="button">
                                Cancel
                            </button>
                            <button class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" type="submit">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Proof Enlarged Modal -->
    <div class="hs-overlay pointer-events-none fixed start-0 top-0 z-[100] hidden size-full overflow-y-auto overflow-x-hidden" id="proof-modal" role="dialog" aria-labelledby="proof-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-4xl">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Payment Proof
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#proof-modal" type="button" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body overflow-y-auto p-4">
                    <div class="flex justify-center">
                        <img class="max-h-[80vh] max-w-full rounded-lg" id="proof-modal-image" src="" alt="Payment Proof Enlarged">
                    </div>
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
                            className: 'px-3 py-2 whitespace-nowrap text-sm',
                            render: function(data, type, full, meta) {
                                return formatCurrency(data, full.currency);
                            }
                        },
                        {
                            data: 'status',
                            name: 'status',
                            className: 'px-3 py-2 whitespace-nowrap text-sm',
                            render: function(data, type, row) {
                                const config = STATUS_CONFIG_BADGE_COLOR[data] || STATUS_CONFIG_BADGE_COLOR.default;
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

                const modalInstance = HSOverlay.getInstance('#payment-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove payment_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('payment_id')) {
                            url.searchParams.delete('payment_id');
                        }
                        // Also close update status modal if open
                        if (url.searchParams.has('update_status')) {
                            url.searchParams.delete('update_status');
                        }
                        window.history.replaceState({}, '', url);
                    });
                }

                // Open payment modal when .btn-payment-status button is clicked
                $('body').on('click', '.btn-payment-status', function(e) {
                    e.preventDefault();
                    currentPaymentId = $(this).data('id');

                    // Show loading state
                    $('#modal-loader-data').show();
                    $('#payment-content').hide();

                    // Tampilkan ID Payment di URL tanpa reload halaman
                    let newUrl = new URL(window.location);
                    newUrl.searchParams.set('payment_id', currentPaymentId);
                    window.history.pushState({}, '', newUrl);
                    openModal('#payment-modal')
                    getPaymentStatus(currentPaymentId);
                });

                // Handle direct Update Status button click (from table row)
                $('body').on('click', '.btn-update-status', function(e) {
                    e.preventDefault();
                    currentPaymentId = $(this).data('id');

                    // Open update status modal directly
                    openUpdateStatusModal(currentPaymentId);
                });

                // Handle form submission for updating status
                $('#update-status-form').on('submit', function(e) {
                    e.preventDefault();
                    updatePaymentStatus();
                });

                function getPaymentStatus(paymentId) {
                    $.ajax({
                        type: 'GET',
                        url: `{{ route('admin.payment.getData', ':payment_id') }}`.replace(':payment_id', paymentId),

                        success: function(response) {
                            if (response.success) {
                                const payment = response.payment;

                                parsingPaymentData(payment);
                            } else {
                                if (MyZkToast) {
                                    MyZkToast.error('Failed to load payment data');
                                }
                                closeModal('#payment-modal');
                            }
                        },
                        error: function(xhr) {
                            console.error('Error fetching payment data:', xhr);
                            if (MyZkToast) {
                                MyZkToast.error(xhr?.responseJSON?.message || 'Failed to load payment data');
                            }
                            closeModal('#payment-modal');
                        },
                        complete: function() {
                            // Hide loading state
                            $('#modal-loader-data').hide();
                            $('#payment-content').show();
                        }
                    });
                }

                function parsingPaymentData(paymentData) {
                    const invoiceNumber = paymentData?.invoice_number || (paymentData?.id ? paymentData.id.toString().substr(0, 16) : '');
                    $('#modal-invoice-number').text('#' + invoiceNumber || '-');
                    $('#modal-customer-name').text(paymentData?.name || '-');
                    $('#modal-email').text(paymentData?.email || '-');
                    $('#modal-phone').text(paymentData?.phone || '-');
                    $('#modal-amount').text(formatCurrency(paymentData?.amount, paymentData?.currency));
                    $('#modal-payment-method').text(paymentData?.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                    $('#modal-order-date').text(formatCustomDate(paymentData?.created_at));
                    $('#modal-due-date').text(paymentData?.due_date ? formatCustomDate(paymentData?.due_date) : '-');

                    let statusText = '';
                    const config = STATUS_CONFIG_BADGE_COLOR[paymentData?.status] || STATUS_CONFIG_BADGE_COLOR.default;

                    // If status is paid and paid_at exists, show paid_at time
                    if (paymentData?.status === 'paid' && paymentData?.paid_at) {
                        statusText = `${config.text} at ${formatCustomDate(paymentData?.paid_at)}`;
                    } else {
                        statusText = config.text;
                    }

                    $('#modal-status').removeClass().addClass('inline-flex rounded-full px-2 py-1 text-xs font-semibold ' + config.class).text(statusText);
                }

                function openUpdateStatusModal(paymentId) {
                    // Get current payment data to show current status
                    $.ajax({
                        type: 'GET',
                        url: `{{ route('admin.payment.getData', ':payment_id') }}`.replace(':payment_id', paymentId),
                        success: function(response) {
                            if (response.success) {
                                const payment = response.payment;
                                const currentStatus = payment.status;

                                // Set form values
                                $('#payment-id').val(paymentId);
                                $('#current-status-display').removeClass().addClass('text-sm font-medium px-2 py-1 rounded-full inline-block');

                                // Apply status styling
                                const config = STATUS_CONFIG_BADGE_COLOR[currentStatus] || STATUS_CONFIG_BADGE_COLOR.default;
                                $('#current-status-display').addClass(config.class).text(config.text);

                                // Set selected status in dropdown
                                $('#status').val(currentStatus);

                                // Handle payment proof display
                                if (payment.payment_proof) {
                                    $('#payment-proof-image').attr('src', '/' + payment.payment_proof);
                                    $('#payment-proof-section').show();
                                } else {
                                    $('#payment-proof-section').hide();
                                }

                                // Open modal
                                openModal('#update-status-modal');
                            } else {
                                if (MyZkToast) {
                                    MyZkToast.error('Failed to load payment data');
                                }
                            }
                        },
                        error: function(xhr) {
                            console.error('Error fetching payment data:', xhr);
                            if (MyZkToast) {
                                MyZkToast.error(xhr?.responseJSON?.message || 'Failed to load payment data');
                            }
                        }
                    });
                }

                // Assign openProofModal to window object to make it globally accessible
                window.openProofModal = function(src) {
                    $('#proof-modal-image').attr('src', src);
                    openModal('#proof-modal');
                };

                function updatePaymentStatus() {
                    const paymentId = $('#payment-id').val();
                    const status = $('#status').val();
                    const formData = {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        _method: 'PUT',
                        status: status
                    };

                    $.ajax({
                        type: 'POST', // Using POST because we're sending _method=PUT
                        url: `{{ route('admin.payment.updateStatus', ':payment_id') }}`.replace(':payment_id', paymentId),
                        data: formData,
                        beforeSend: function() {
                            // Disable submit button and show loading
                            $('#update-status-form button[type="submit"]').prop('disabled', true).text('Updating...');
                        },
                        success: function(response) {
                            if (response.success) {
                                if (MyZkToast) {
                                    MyZkToast.success(response.message || 'Payment status updated successfully');
                                }

                                // Close modal
                                closeModal('#update-status-modal');

                                // Refresh payment details
                                getPaymentStatus(paymentId);

                                // Refresh DataTable
                                $('#myTable').DataTable().ajax.reload();
                            } else {
                                if (MyZkToast) {
                                    MyZkToast.error(response.message || 'Failed to update payment status');
                                }
                            }
                        },
                        error: function(xhr) {
                            console.error('Error updating payment status:', xhr);
                            if (MyZkToast) {
                                MyZkToast.error(xhr?.responseJSON?.message || 'Failed to update payment status');
                            }
                        },
                        complete: function() {
                            // Re-enable submit button
                            $('#update-status-form button[type="submit"]').prop('disabled', false).text('Update Status');
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>

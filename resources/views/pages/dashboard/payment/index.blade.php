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
                                    <label class="text-foreground block text-sm font-medium">Name of Field</label>
                                    <p class="text-base-content text-sm" id="modal-field-name">-</p>
                                </div>
                                <div>
                                    <label class="text-foreground block text-sm font-medium">Field Area</label>
                                    <p class="text-base-content text-sm" id="modal-field-area">-</p>
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
                            <div class="border-border mt-6 border-t pt-4" id="payment-status-update">
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
                });

                const modalInstance = HSOverlay.getInstance('#payment-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove payment_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('payment_id')) {
                            url.searchParams.delete('payment_id');
                            window.history.replaceState({}, '', url);
                        }
                    });
                }

                // Open payment modal when .payment-status button is clicked
                $('body').on('click', '.payment-status', function(e) {
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
                    // Populate modal with payment data
                    $('#modal-invoice-number').text('#' + paymentData?.id.substr(0, 8));
                    $('#modal-customer-name').text(paymentData?.name || '-');
                    $('#modal-email').text(paymentData?.email || '-');
                    $('#modal-phone').text(paymentData?.phone || '-');
                    $('#modal-amount').text(formatCurrency(paymentData?.amount, paymentData?.currency));
                    $('#modal-payment-method').text(paymentData?.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                    $('#modal-order-date').text(formatCustomDate(paymentData?.created_at));

                    $('#modal-field-area').text((formatNumber(paymentData?.field_area?.area_ha) || '-') + ' ha');
                    $('#modal-field-name').text(paymentData?.field_area?.name || '-');

                    let statusText = '';
                    const config = STATUS_CONFIG_BADGE_COLOR[paymentData?.status] || STATUS_CONFIG_BADGE_COLOR.default;

                    // If status is paid and paid_at exists, show paid_at time
                    if (paymentData?.status === 'paid' && paymentData?.paid_at) {
                        statusText = `${config.text} at ${formatCustomDate(paymentData?.paid_at)}`;
                    } else {
                        statusText = config.text;
                    }

                    $('#modal-status').removeClass().addClass('inline-flex rounded-full px-2 py-1 text-xs font-semibold ' + config.class).text(statusText);

                    @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                        $('#payment-status-select').val(paymentData?.status);


                        if (paymentData?.status === 'expired' || paymentData?.status === 'paid') {
                            $('#payment-status-update').hide();
                        } else {
                            $('#payment-status-update').show();
                        }
                    @endif
                }


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
                                    closeModal('#payment-modal');
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

@section('title', $data['title'] ?? '')
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ $data['title'] }}</h1>
            <p class="mt-1 text-gray-600">
                @if (auth()->user()->role === 'user')
                    View and manage your active subscriptions
                @else
                    Manage all user subscriptions in the system
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
                            @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                    Customer Name
                                </th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Plan Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Field Area
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Total Price
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Start Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                End Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-foreground/20 divide-y">
                        <!-- DataTables will populate this -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    <!-- Subs Details Modal -->
    <div class="hs-overlay z-80 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="subs-modal" role="dialog" aria-labelledby="subs-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-lg">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Subscription Details
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#subs-modal" type="button" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body overflow-y-auto p-4">
                    <div id="error-messages"></div>

                    <div class="hidden animate-pulse" id="modal-loader-data" role="status">
                        <div class="bg-base-content-muted mx-auto mb-4 h-2.5 w-60 rounded-full"></div>
                        <div class="w-50 bg-base-content-muted mx-auto mb-4 h-2.5 rounded-full"></div>
                        <span class="sr-only">Loading...</span>
                    </div>

                    <div class="" id="subscription-details">
                        <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <h4 class="text-foreground text-lg font-medium">Subscription Information</h4>
                                <dl class="mt-2 space-y-2">
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Plan Name</dt>
                                        <dd class="text-foreground text-sm" id="modal-plan-name"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Field Area</dt>
                                        <dd class="text-foreground text-sm" id="modal-field-area"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Total Price</dt>
                                        <dd class="text-foreground text-sm" id="modal-total-price"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Status</dt>
                                        <dd class="text-foreground text-sm" id="modal-status"></dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h4 class="text-foreground text-lg font-medium">Date Information</h4>
                                <dl class="mt-2 space-y-2">
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Start Date</dt>
                                        <dd class="text-foreground text-sm" id="modal-start-date"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">End Date</dt>
                                        <dd class="text-foreground text-sm" id="modal-end-date"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Created At</dt>
                                        <dd class="text-foreground text-sm" id="modal-created-at"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Updated At</dt>
                                        <dd class="text-foreground text-sm" id="modal-updated-at"></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                            <div class="mb-3">
                                <h4 class="text-foreground text-lg font-medium">Customer Information</h4>
                                <dl class="mt-2 space-y-2">
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Customer Name</dt>
                                        <dd class="text-foreground text-sm" id="modal-customer-name"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Customer Email</dt>
                                        <dd class="text-foreground text-sm" id="modal-customer-email"></dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        <div>
                            <h4 class="text-foreground text-lg font-medium">Payment Information</h4>
                            <dl class="mt-2 space-y-2">
                                <div>
                                    <dt class="text-foreground/60 text-sm font-medium">Payment Status</dt>
                                    <dd class="text-foreground text-sm" id="modal-payment-status"></dd>
                                </div>
                                <div>
                                    <dt class="text-foreground/60 text-sm font-medium">Transaction ID</dt>
                                    <dd class="text-foreground text-sm" id="modal-transaction-id"></dd>
                                </div>
                            </dl>

                            <!-- Payments Table -->
                            <div class="mt-4" id="payments-table-container">
                                <h5 class="text-md text-foreground mb-2 font-medium">Payment History</h5>
                                <div class="overflow-x-auto">
                                    <table class="divide-foreground/20 min-w-full divide-y">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="text-foreground/60 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" scope="col">Invoice</th>
                                                <th class="text-foreground/60 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" scope="col">Amount</th>
                                                <th class="text-foreground/60 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" scope="col">Status</th>
                                                <th class="text-foreground/60 px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" scope="col">Payment Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-foreground/20 bg-neutral divide-y" id="payments-table-body">
                                            <!-- Payments will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-foreground/60 mt-2 text-sm" id="payments-limit-note" style="display: none;">
                                    Showing only the 2 most recent payments. For complete payment history, please check the Payments section.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error message container -->
                    <div class="text-error hidden py-4 text-center" id="subscription-details-error">
                        Error fetching subscription details data
                    </div>
                </div>
                <div class="border-foreground/20 flex items-center justify-end gap-x-2 border-t px-4 py-3">
                    <x-button-light class="border-border bg-background text-foreground hover:bg-muted focus:bg-muted inline-flex items-center gap-x-2 rounded-lg border px-3 py-2 text-sm font-medium focus:outline-none disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#subs-modal" type="button">
                        Close
                    </x-button-light>
                </div>
            </div>
        </div>
    </div>

    @include('components.dependencies._datatables')

    @push('css')
        <link href="https://cdn.jsdelivr.net/npm/ol@v10.6.0/ol.css" rel="stylesheet">
    @endpush

    @push('javascript')
        <script src="https://cdn.jsdelivr.net/npm/ol@v10.6.0/dist/ol.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.11.0/proj4.min.js" integrity="sha512-JfEOeAU2TD7AtE3xJPSBwBFCxURVqQCysNBwOnNhEJS9LgTHTWGSyYd11JUBOaJ+xVHPaA0ZhLin365CapD8EQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <script>
            $(document).ready(function() {
                let urlParams = new URLSearchParams(window.location.search);
                let pageParam = parseInt(urlParams.get('page')) || 1;
                let limitParam = parseInt(urlParams.get('limit')) || 10;
                let subscriptionIdParam = urlParams.get('subscription_id');
                let currentSubscriptionId = null;

                // Initialize DataTable
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
                        [{{ in_array(auth()->user()->role, ['superadmin', 'admin']) ? '7' : '6' }}, 'desc'] // Order by start date descending
                    ],
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                            {
                                data: 'user.name',
                                name: 'user.name'
                            },
                        @endif {
                            data: 'plan.name',
                            name: 'plan.name'
                        },
                        {
                            data: 'area_ha',
                            name: 'area_ha',
                            orderable: true,
                            render: function(data, type, full, meta) {
                                return formatNumber(data) + ' ha';
                            }
                        },
                        {
                            data: 'total_price',
                            name: 'total_price',
                            render: function(data, type, full, meta) {
                                return formatCurrency(data, full?.payments?.[0]?.currency);
                            }
                        },
                        {
                            data: 'status',
                            name: 'status',
                            render: function(data) {
                                let config = STATUS_CONFIG_BADGE_COLOR[data] || {
                                    class: "bg-gray-100 text-gray-800",
                                    text: data
                                };

                                return `<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${config.class}">${config.text}</span>`;
                            }
                        },
                        {
                            data: 'start_date',
                            name: 'start_date',
                            render: function(data) {
                                return formatCustomDate(data, false);
                            }
                        },
                        {
                            data: 'end_date',
                            name: 'end_date',
                            render: function(data) {
                                return formatCustomDate(data, false);
                            }
                        },
                        {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
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

                const modalInstance = HSOverlay.getInstance('#subs-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove subs_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('subscription_id')) {
                            url.searchParams.delete('subscription_id');
                            window.history.replaceState({}, '', url);
                        }
                    });
                }

                // Clone the subscription details element
                const subscriptionDetail = document.getElementById('subscription-details').cloneNode(true);

                // Function to fetch and display subscription details
                function getSubscriptionDetails(subscriptionId) {
                    // Show loader and hide details
                    $('#modal-loader-data').removeClass('hidden');
                    $('#subscription-details').addClass('hidden');
                    $('#subscription-details-error').addClass('hidden');

                    // Make AJAX request to fetch subscription data
                    $.ajax({
                        url: `{{ route('admin.subscription.show', ':subscription_id') }}`.replace(':subscription_id', subscriptionId),
                        method: 'GET',
                        success: function(response) {
                            const subscription = response.data;

                            // Hide loader and show details
                            $('#modal-loader-data').addClass('hidden');
                            $('#subscription-details').removeClass('hidden');

                            // Populate modal with subscription data
                            $('#modal-plan-name').text(subscription.plan?.name || '-');
                            $('#modal-field-area').text(subscription.field_area ? formatNumber(subscription.field_area.area_ha) + ' ha' : '-');

                            $('#modal-total-price').text(subscription.total_price ? formatCurrency(subscription.total_price, response?.payments?.[0]?.currency) : '-');

                            // Status with badge color
                            const status = subscription.status || '-';
                            const statusConfig = window.STATUS_CONFIG_BADGE_COLOR?.[status] || window.STATUS_CONFIG_BADGE_COLOR?.default || {
                                class: "bg-gray-100 text-gray-800",
                                text: status
                            };
                            $('#modal-status').html(`<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${statusConfig.class}">${statusConfig.text}</span>`);

                            // Format dates
                            $('#modal-start-date').text(subscription.start_date ? formatCustomDate(subscription.start_date, false) : '-');
                            $('#modal-end-date').text(subscription.end_date ? formatCustomDate(subscription.end_date, false) : '-');
                            $('#modal-created-at').text(subscription.created_at ? formatCustomDate(subscription.created_at, true) : '-');
                            $('#modal-updated-at').text(subscription.updated_at ? formatCustomDate(subscription.updated_at, true) : '-');

                            // Customer information (for admin roles)
                            @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                $('#modal-customer-name').text(subscription.user?.name || '-');
                                $('#modal-customer-email').text(subscription.user?.email || '-');
                            @endif

                            // Payment information
                            const payment = subscription.payments?.[0] || null;
                            if (payment) {
                                const paymentStatusConfig = window.STATUS_CONFIG_BADGE_COLOR?.[payment.status] || window.STATUS_CONFIG_BADGE_COLOR?.default || {
                                    class: "bg-gray-100 text-gray-800",
                                    text: payment.status
                                };
                                $('#modal-payment-status').html(`<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${paymentStatusConfig.class}">${paymentStatusConfig.text}</span>`);
                                $('#modal-transaction-id').text(payment.id || '-');
                            } else {
                                $('#modal-payment-status').text('-');
                                $('#modal-transaction-id').text('-');
                            }

                            // Display all payments in a table
                            const payments = subscription.payments || [];
                            if (payments.length > 0) {
                                $('#payments-table-container').show();
                                let paymentsTableBody = '';

                                payments.forEach(function(payment) {
                                    const paymentStatusConfig = window.STATUS_CONFIG_BADGE_COLOR?.[payment.status] || window.STATUS_CONFIG_BADGE_COLOR?.default || {
                                        class: "bg-gray-100 text-gray-800",
                                        text: payment.status
                                    };

                                    paymentsTableBody += `
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 text-sm text-foreground ">${payment.invoice_number || '-'}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-sm text-foreground ">${formatCurrency(payment.amount, payment.currency)}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-sm">
                                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${paymentStatusConfig.class}">${paymentStatusConfig.text}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-sm text-foreground ">${payment.paid_at ? formatCustomDate(payment.paid_at, false) : '-'}</td>
                                        </tr>
                                    `;
                                });

                                $('#payments-table-body').html(paymentsTableBody);
                            } else {
                                $('#payments-table-container').hide();
                            }
                        },
                        error: function(xhr, status, error) {
                            // Hide loader and show error
                            $('#modal-loader-data').addClass('hidden');
                            $('#subscription-details').addClass('hidden');
                            $('#subscription-details-error').removeClass('hidden');

                            // Show specific error message if available
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                $('#subscription-details-error').text(`Error: ${xhr.responseJSON.message}`);
                            } else {
                                $('#subscription-details-error').text('Error fetching subscription details. Please try again.');
                            }

                            console.error('Error fetching subscription details:', error);
                        }
                    });
                }

                $('body').on('click', '.view-subscription', function(e) {
                    e.preventDefault();
                    let subscriptionId = $(this).data('id');
                    currentSubscriptionId = subscriptionId;

                    // Update URL with subscription ID
                    let newUrl = new URL(window.location);
                    newUrl.searchParams.set('subscription_id', currentSubscriptionId);
                    window.history.pushState({}, '', newUrl);

                    // Fetch and display subscription details
                    getSubscriptionDetails(subscriptionId);

                    // Open modal using Preline UI
                    openModal('#subs-modal');
                });

                // Check if subscription_id parameter exists in URL on page load
                if (subscriptionIdParam) {
                    getSubscriptionDetails(subscriptionIdParam);
                    openModal('#subs-modal');
                }
            });
        </script>
    @endpush
</x-app-layout>

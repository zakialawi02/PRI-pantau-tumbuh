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
                    <tbody class="bg-background divide-y divide-gray-200">
                        <!-- DataTables will populate this -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    @include('components.dependencies._datatables')

    @push('javascript')
        <script>
            $(document).ready(function() {
                let urlParams = new URLSearchParams(window.location.search);
                let pageParam = parseInt(urlParams.get('page')) || 1;
                let limitParam = parseInt(urlParams.get('limit')) || 10;
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
                        },
                        {
                            data: 'total_price',
                            name: 'total_price',
                            render: function(data, type, full, meta) {
                                return full?.payments?.[0]?.currency + ' ' + data?.toLocaleString();
                            }
                        },
                        {
                            data: 'status',
                            name: 'status',
                            render: function(data) {
                                var statusColors = {
                                    'active': 'bg-green-100 text-green-800',
                                    'expired': 'bg-red-100 text-red-800',
                                    'cancelled': 'bg-yellow-100 text-yellow-800',
                                    'trial': 'bg-blue-100 text-blue-800',
                                    'awaiting_payment': 'bg-orange-100 text-orange-800',
                                    'suspended': 'bg-gray-100 text-gray-800'
                                };

                                var statusText = {
                                    'active': 'Active',
                                    'expired': 'Expired',
                                    'cancelled': 'Cancelled',
                                    'trial': 'Trial',
                                    'awaiting_payment': 'Awaiting Payment',
                                    'suspended': 'Suspended'
                                };

                                var colorClass = statusColors[data] || 'bg-gray-100 text-gray-800';
                                var displayText = statusText[data] || data;

                                return `<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${colorClass}">${displayText}</span>`;
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
            });
        </script>
    @endpush
</x-app-layout>

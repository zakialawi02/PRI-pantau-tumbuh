@section('title', $data['title'] ?? 'Field Areas')
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ $data['title'] }}</h1>
            <p class="text-foreground/60 mt-1">
                @if (auth()->user()->role === 'user')
                    View your imagery uploads
                @else
                    Manage all imagery uploads in the system
                @endif
            </p>
        </div>

        <x-card>
            <div class="mb-4 flex justify-end gap-1">
                <x-button-primary href="{{ route('admin.imagery.upload') }}" size="small">
                    <i class="ri-upload-2-line mr-2"></i> Upload / Add Imagery
                </x-button-primary>
                <x-button-secondary id="btn-refresh" type="button" size="small">
                    <i class="ri-refresh-line mr-2"></i> Refresh
                </x-button-secondary>
            </div>

            <div class="table-container">
                <table class="display table" id="myTable">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                #
                            </th>
                            @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                    User Name
                                </th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Source
                            </th>
                            <th class="w-32! px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Stored Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Size
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Format
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Upload Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Processing
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Uploaded At
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Last Updated
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-foreground/20 divide-y">
                        <!-- Ajax datatable -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>


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
                let pageParam = parseInt(urlParams.get('page')) || 1; // Ambil halaman dari URL
                let limitParam = parseInt(urlParams.get('limit')) || 10;

                let table = new DataTable('#myTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    displayStart: (pageParam - 1) * limitParam, // Atur posisi awal paging
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
                        }
                    },
                    order: [
                        [0, 'asc']
                    ],
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                            {
                                data: 'user_name',
                                name: 'user.name'
                            },
                        @endif {
                            data: 'source_type',
                            name: 'source_type'
                        }, {
                            data: 'stored_name',
                            name: 'stored_name',
                            width: "120px",
                            className: "text-wrap"
                        }, {
                            data: 'size',
                            name: 'size',
                            width: "100px",
                            className: "text-nowrap"
                        }, {
                            data: 'format',
                            name: 'format',
                            className: "text-nowrap"
                        }, {
                            data: 'upload_status',
                            name: 'upload_status',
                            render: function(data, type, row) {
                                if (!data) {
                                    return 'N/A';
                                }
                                const status = data;
                                const label = status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                let badgeClasses = 'bg-foreground/10 text-foreground';
                                if (status === 'pending') {
                                    badgeClasses = 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300';
                                } else if (status === 'merging') {
                                    badgeClasses = 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-300';
                                } else if (status === 'done') {
                                    badgeClasses = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300';
                                } else if (status === 'failed' || status === 'error') {
                                    badgeClasses = 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-300';
                                }
                                return `<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${badgeClasses}">${label}</span>`;
                            }
                        }, {
                            data: 'processing_status',
                            name: 'processing_status',
                        }, {
                            data: 'created_at',
                            name: 'created_at',
                        }, {
                            data: 'updated_at',
                            name: 'updated_at',
                        }, {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        }
                    ],
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

                // Function to check user credits
                const checkUserCredits = async () => {
                    const response = await fetch('{{ route('user.credits.check') }}');
                    const result = await response.json();

                    if (!result.success) {
                        MyZkToast.error(result.message || 'Failed to check credit balance.');
                        return false;
                    }

                    const currentCredits = parseFloat(formatNumber(result.credits, 2));
                    const requiredCredits = {{ config('app-constants.imagery_processing_cost', 10) }};

                    return new Promise((resolve) => {
                        resolve(data = {
                            hasCredits: currentCredits >= requiredCredits,
                            currentCredits: currentCredits,
                            requiredCredits: requiredCredits
                        });
                    });
                };

                // Retry imagery processing
                $('body').on('click', '.btn-retry-imagery', function() {
                    $(this).attr('disabled', true);
                    const imageryId = $(this).data('id');

                    checkUserCredits().then(res => {
                        if (!res.hasCredits) {
                            MyZkToast.error(`You do not have enough credits to retry this imagery processing. ${res.requiredCredits} credits are required.`);
                            $('#myTable').DataTable().ajax.reload(null, false);
                            return;
                        }

                        $.ajax({
                            url: "{{ route('admin.imagery.retry', ':id') }}".replace(':id', imageryId),
                            method: "POST",
                            success: function(response) {
                                MyZkToast.success(response.message);
                            },
                            error: function(error) {
                                console.log(error);
                                MyZkToast.error(error.responseJSON.message)
                            },
                            complete: function() {
                                $('#myTable').DataTable().ajax.reload(null, false);
                                checkUserCredits().then(res => {
                                    $('#current-myCredits').text(formatNumber(res.currentCredits, 2));
                                }).catch(error => {
                                    console.log(error);
                                    MyZkToast.error(error.responseJSON.message || 'Failed to update credits view')
                                });
                            }
                        })
                    }).catch(error => {
                        MyZkToast.error('Failed to check credit balance: ' + error.message);
                    });

                });

                // Retry merging uploaded chunks
                $('body').on('click', '.btn-retry-merge', function() {
                    const $button = $(this);
                    $button.attr('disabled', true);
                    const imageryId = $button.data('id');

                    $.ajax({
                        url: "{{ route('admin.imagery.retry-merge', ':id') }}".replace(':id', imageryId),
                        method: "POST",
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            MyZkToast.success(response.message || 'Merge restarted.');
                        },
                        error: function(error) {
                            console.log(error);
                            const message = error.responseJSON?.message || 'Failed to restart merge.';
                            MyZkToast.error(message);
                        },
                        complete: function() {
                            $('#myTable').DataTable().ajax.reload(null, false);
                            $button.attr('disabled', false);
                        }
                    });
                });

                // Download imagery source
                $('body').on('click', '.btn-download-source', function() {
                    const imageryId = $(this).data('id');
                    // Create a temporary link and trigger download
                    const downloadUrl = "{{ route('admin.imagery.download.source', ':id') }}".replace(':id', imageryId);
                    window.location.href = downloadUrl;
                });

                // Download imagery result
                $('body').on('click', '.btn-download-result', function() {
                    const imageryId = $(this).data('id');
                    // Create a temporary link and trigger download
                    const downloadUrl = "{{ route('admin.imagery.download.result', ':id') }}".replace(':id', imageryId);
                    window.location.href = downloadUrl;
                });

                // Delete imagery
                $('body').on('click', '.btn-delete-imagery', function(e) {
                    e.preventDefault();
                    const imageryId = $(this).data('id');

                    ZkPopAlert.show({
                        message: "Are you sure you want to delete this data?",
                        confirmText: "Yes, delete it",
                        cancelText: "No, cancel",
                        onConfirm: () => {
                            deleteImagery(imageryId);
                        }
                    });
                });

                $('body').on('click', '#btn-refresh', function(e) {
                    $('#myTable').DataTable().ajax.reload(null, false);
                })

                function deleteImagery(imageryId) {
                    $.ajax({
                        type: 'DELETE',
                        url: "{{ route('admin.imagery.destroy', ':id') }}".replace(':id', imageryId),
                        success: function(response) {
                            $('#myTable').DataTable().ajax.reload(null, false);
                            MyZkToast.success(response.message);
                        },
                        error: function(error) {
                            console.log(error);
                            MyZkToast.error(error.responseJSON.message)
                        }
                    })
                }
            });
        </script>
    @endpush
</x-app-layout>

@section('title', __('Credit History'))
@section('meta_description', __('View credit balance changes and history'))

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="mb-3 flex items-center justify-between px-2 align-middle">
                <h2 class="text-foreground text-xl font-semibold">{{ __('Credit History') }}</h2>
            </div>

            <div class="table-container">
                <table class="display table" id="creditHistoryTable">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('No.') }}</th>
                            @if ($isSuperAdmin)
                                <th scope="col">{{ __('User') }}</th>
                                <th scope="col">{{ __('Email') }}</th>
                            @endif
                            <th scope="col">{{ __('Type') }}</th>
                            <th scope="col">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Balance Before') }}</th>
                            <th scope="col">{{ __('Balance After') }}</th>
                            <th scope="col">{{ __('Description') }}</th>
                            <th scope="col">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- DataTables will populate -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    @include('components.dependencies._datatables')

    @push('javascript')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const isSuperAdmin = @json($isSuperAdmin);

                const columns = [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    }];

                if (isSuperAdmin) {
                    columns.push({
                        data: 'user_name',
                        name: 'user.name'
                    });
                    columns.push({
                        data: 'user_email',
                        name: 'user.email'
                    });
                }

                columns.push({
                    data: 'type_label',
                    name: 'type'
                });

                columns.push({
                    data: 'change',
                    name: 'amount'
                });

                columns.push({
                    data: 'balance_before',
                    name: 'balance_before'
                });

                columns.push({
                    data: 'balance_after',
                    name: 'balance_after'
                });

                columns.push({
                    data: 'description',
                    name: 'description',
                    defaultContent: '-' // handle null description
                });

                const createdAtColumnIndex = columns.length;

                columns.push({
                    data: 'created_at',
                    name: 'created_at'
                });

                let urlParams = new URLSearchParams(window.location.search);
                let pageParam = parseInt(urlParams.get('page')) || 1;
                let limitParam = parseInt(urlParams.get('limit')) || 10;

                let table = new DataTable('#creditHistoryTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    displayStart: (pageParam - 1) * limitParam,
                    pageLength: limitParam,
                    ajax: {
                        url: "{{ url()->full() }}",
                        beforeSend: function() {
                            dt_showLoader('#creditHistoryTable');
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
                        [createdAtColumnIndex, 'desc']
                    ],
                    columns: columns
                });

                table.on('draw', function() {
                    var info = table.page.info();
                    var currentPage = info.page + 1;
                    var pageLength = info.length;

                    var newUrl = new URL(window.location);
                    newUrl.searchParams.set('page', currentPage);
                    newUrl.searchParams.set('limit', pageLength);
                    window.history.replaceState({}, '', newUrl);
                });
            });
        </script>
    @endpush
</x-app-layout>

@section('title', __('All Credit History'))
@section('meta_description', __('Review every credit point transaction recorded in the system.'))

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-foreground text-2xl font-semibold">{{ __('Credit History Management') }}</h1>
                    <p class="text-foreground/70">{{ __('Monitor credit changes for every user in the platform.') }}</p>
                </div>
            </div>

            <div class="table-container">
                <table class="display table w-full" id="allCreditHistoryTable">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('User') }}</th>
                            <th scope="col">{{ __('Email') }}</th>
                            <th scope="col">{{ __('Type') }}</th>
                            <th scope="col">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Balance Before') }}</th>
                            <th scope="col">{{ __('Balance After') }}</th>
                            <th scope="col">{{ __('Performed By') }}</th>
                            <th scope="col">{{ __('Reference') }}</th>
                            <th scope="col">{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- DataTables -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    @include('components.dependencies._datatables')

    @push('javascript')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const historyTable = new DataTable('#allCreditHistoryTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ url()->full() }}",
                        beforeSend: function() {
                            dt_showLoader('#allCreditHistoryTable');
                        },
                        complete: function() {
                            dt_hideLoader();
                        }
                    },
                    order: [
                        [1, 'desc']
                    ],
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'created_at',
                            name: 'created_at'
                        },
                        {
                            data: 'user_name',
                            name: 'user.name'
                        },
                        {
                            data: 'user_email',
                            name: 'user.email'
                        },
                        {
                            data: 'type_badge',
                            name: 'type',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'amount',
                            name: 'amount'
                        },
                        {
                            data: 'balance_before',
                            name: 'balance_before'
                        },
                        {
                            data: 'balance_after',
                            name: 'balance_after'
                        },
                        {
                            data: 'performed_by_name',
                            name: 'performedBy.name'
                        },
                        {
                            data: 'reference',
                            name: 'reference_type',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'description',
                            name: 'description',
                            orderable: false
                        },
                    ],
                    language: {
                        paginate: {
                            previous: '<i class="ri-arrow-left-s-line"></i>',
                            next: '<i class="ri-arrow-right-s-line"></i>'
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>

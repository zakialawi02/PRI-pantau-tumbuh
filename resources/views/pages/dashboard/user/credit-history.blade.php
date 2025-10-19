@section('title', __('My Credit History'))
@section('meta_description', __('View the detailed history of your credit point transactions.'))

<x-app-layout>
    <section class="space-y-4 p-1 md:p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('My Credit History') }}</h1>
                <p class="text-foreground/70">{{ __('Track every change to your credit balance.') }}</p>
            </div>
            <div class="rounded-lg border border-foreground/10 bg-background p-4 shadow-sm">
                <p class="text-sm font-medium text-foreground/70">{{ __('Current Balance') }}</p>
                <p class="text-2xl font-bold text-primary">
                    {{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }}
                    <span class="text-base font-medium text-foreground/70">{{ __('Credits') }}</span>
                </p>
            </div>
        </div>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-foreground text-xl font-semibold">{{ __('History') }}</h2>
            </div>
            <div class="table-container">
                <table class="display table w-full" id="creditHistoryTable">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('Type') }}</th>
                            <th scope="col">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Balance Before') }}</th>
                            <th scope="col">{{ __('Balance After') }}</th>
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
                const historyTable = new DataTable('#creditHistoryTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ url()->full() }}",
                        beforeSend: function() {
                            dt_showLoader('#creditHistoryTable');
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

@section('title', 'Plans Management')
@section('meta_description', 'Manage subscription plans and pricing')

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="mb-3 flex items-center justify-between px-2 align-middle">
                <h2 class="text-foreground text-xl font-semibold">Plans Management</h2>
                <x-button-primary id="create-new-plan" data-hs-overlay="#plan-modal" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="plan-modal">
                    <i class="ri-add-line"></i>
                    <span>Add Plan</span>
                </x-button-primary>
            </div>

            <div class="table-container">
                <table class="display table" id="myTable">
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Plan Name</th>
                            <th scope="col">Price per Hectare</th>
                            <th scope="col">Total Subscriptions</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Ajax datatable -->
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>


    <!-- Main modal -->
    <div class="hs-overlay z-80 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="plan-modal" role="dialog" aria-labelledby="plan-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-lg">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Add User
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 bg-foreground/15 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#plan-modal" type="button" aria-label="Close">
                        <span class="sr-only">Close</span>
                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body overflow-y-auto p-4">
                    <div id="error-messages"></div>

                    <div class="modal-loader-data hidden animate-pulse" role="status">
                        <div class="bg-base-content-muted mx-auto mb-4 h-2.5 w-60 rounded-full"></div>
                        <div class="w-50 bg-base-content-muted mx-auto mb-4 h-2.5 rounded-full"></div>
                        <span class="sr-only">Loading...</span>
                    </div>

                    <form class="" id="planForm" method="post" action="">
                        @csrf
                        <input id="_method" name="_method" type="hidden">

                        <div class="space-y-2.5">
                            <!-- Plan Name -->
                            <div>
                                <x-input-label for="name" :value="__('Plan Name')" />
                                <x-text-input class="px-1! py-1.5! mt-1 block w-full" id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name" placeholder="Standard Plan" />
                            </div>

                            <div class="flex w-full flex-col items-center justify-between gap-2 md:flex-row">
                                <!-- Price per Hectare -->
                                <div class="w-full md:w-1/2">
                                    <x-input-label for="price_per_hectare" :value="__('Price per Hectare')" />
                                    <x-text-input class="px-1! py-1.5! block w-full" id="price_per_hectare" name="price_per_hectare" type="number" step="0.01" min="0" :value="old('price_per_hectare')" required placeholder="1.00" />
                                </div>

                                <!-- Currency -->
                                <div class="w-full md:w-1/2">
                                    <x-input-label for="currency" :value="__('Currency')" />
                                    <select class="focus:ring-primary focus:border-primary border-ring bg-input/50 text-foreground block w-full rounded-lg border p-2" id="currency" name="currency">
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="IDR">IDR - Indonesian Rupiah</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Is Show -->
                            <div class="flex items-start align-baseline">
                                <input class="border-foreground/20 checked:border-primary focus:ring-primary text-primary mt-0.5 shrink-0 rounded-sm disabled:pointer-events-none disabled:opacity-50" id="isShow" name="isShow" type="checkbox" value="1">
                                <x-input-label class="mb-0 ml-2" for="isShow" :value="__('Show this plan publicly')" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="border-foreground/20 flex items-center justify-end gap-x-2 border-t px-4 py-3">
                    <x-button-light class="border-border bg-background text-foreground hover:bg-muted focus:bg-muted inline-flex items-center gap-x-2 rounded-lg border px-3 py-2 text-sm font-medium focus:outline-none disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#plan-modal" type="button">
                        Close
                    </x-button-light>
                    <x-button-primary id="saveBtn" type="submit">
                        Create
                    </x-button-primary>
                </div>
            </div>
        </div>
    </div>

    @include('components.dependencies._datatables')

    @push('javascript')
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
                        [1, 'asc']
                    ],
                    columns: [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'name',
                            name: 'name'
                        },
                        {
                            data: 'price_per_hectare',
                            name: 'price_per_hectare',
                            render: function(data, type, full, meta) {
                                return formatCurrency(data, full.currency);
                            }
                        },
                        {
                            data: 'subscriptions_count',
                            name: 'subscriptions_count',
                            orderable: true,
                            searchable: false
                        },
                        {
                            data: 'isShow',
                            name: 'isShow',
                            orderable: true,
                            searchable: false,
                            render: function(data, type, full, meta) {
                                if (data) {
                                    return '<span class="bg-success/80 text-white px-2 py-1 rounded-full text-xs font-medium">Visible</span>';
                                } else {
                                    return '<span class="bg-error/80 text-white px-2 py-1 rounded-full text-xs font-medium">Hidden</span>';
                                }
                            }
                        },
                        {
                            data: 'created_at',
                            name: 'created_at',
                            orderable: true,
                            searchable: false,
                            render: function(data) {
                                return formatCustomDate(data);
                            }
                        },
                        {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        },
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

                const modalInstance = HSOverlay.getInstance('#plan-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove plan_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('plan_id')) {
                            url.searchParams.delete('plan_id');
                            window.history.replaceState({}, '', url);
                        }
                    });
                }

                const cardErrorMessages = `<div id="body-messages" class="mb-3 rounded-md bg-error/30 p-4 text-sm text-error" role="alert"></div>`;

                // Open modal for creating new plan
                $('#create-new-plan').click(function() {
                    $(".modal-loader-data").hide()
                    $("#planForm").show();
                    $('#plan-modal').find('.modal-title').text('Add Plan');
                    $('#planForm').attr('method', 'POST');
                    $('#_method').val('POST');
                    $('#planForm').trigger("reset");
                    $('#isShow').prop('checked', false);
                    $('#planForm').attr('action', '{{ route('admin.plans.store') }}');
                    $('#saveBtn').text('Create');
                    $("#error-messages").html("");
                });

                // Save new or updated plan
                $('#saveBtn').on('click', function(e) {
                    e.preventDefault();
                    const formData = $('#planForm').serialize();
                    const formAction = $('#planForm').attr('action');
                    const method = $('#planForm').attr('method');

                    $.ajax({
                        type: method,
                        url: formAction,
                        data: formData,
                        beforeSend: function() {
                            $("#error-messages").html("");
                            $('#saveBtn').prop('disabled', true);
                        },
                        success: function(response) {
                            closeModal('#plan-modal');
                            $('#myTable').DataTable().ajax.reload();
                            MyZkToast.success(response.message);
                        },
                        error: function(error) {
                            displayErrors(error.responseJSON.errors);
                        },
                        complete: function() {
                            $('#saveBtn').prop('disabled', false);
                        }
                    });
                });

                // Edit plan
                $('body').on('click', '.edit-plan', function() {
                    $(".modal-loader-data").show();
                    $("#planForm").hide();
                    $('#saveBtn').prop('disabled', true);
                    $('#plan-modal').find('.modal-title').text('Edit Plan');
                    $("#error-messages").html("");
                    const planId = $(this).data('id');
                    // Tampilkan ID Plan di URL tanpa reload halaman
                    let newUrl = new URL(window.location);
                    newUrl.searchParams.set('plan_id', planId);
                    window.history.pushState({}, '', newUrl);
                    openModal('#plan-modal');
                    getPlanData(planId);
                });

                // Delete plan
                $('body').on('click', '.delete-plan', function(e) {
                    e.preventDefault();
                    const planId = $(this).data('id');
                    const url = `{{ route('admin.plans.destroy', ':planId') }}`.replace(':planId', planId);

                    ZkPopAlert.show({
                        message: "Are you sure you want to delete this plan?",
                        confirmText: "Yes, delete it",
                        cancelText: "No, cancel",
                        onConfirm: () => {
                            deletePlan(planId);
                        }
                    });
                });

                function deletePlan(planId) {
                    $.ajax({
                        type: "DELETE",
                        url: `{{ route('admin.plans.destroy', ':planId') }}`.replace(':planId', planId),
                        success: function(response) {
                            $('#myTable').DataTable().ajax.reload();
                            MyZkToast.success(response.message);
                        },
                        error: function(error) {
                            console.log(error);
                            MyZkToast.error(error?.responseJSON?.message ?? error.statusText)
                        }
                    });
                }

                // Cek URL saat halaman dimuat
                if (urlParams.has("plan_id")) {
                    let planId = urlParams.get("plan_id");
                    $(".modal-loader-data").show();
                    $("#planForm").hide();
                    $('#saveBtn').prop('disabled', true);
                    $('#plan-modal').find('.modal-title').text('Edit Plan');
                    $("#error-messages").html("");
                    setTimeout(() => {
                        openModal('#plan-modal');
                    }, 800);
                    getPlanData(planId);
                }

                function getPlanData(planId) {
                    // Panggil AJAX untuk menampilkan data plan_id
                    $.get(`{{ route('admin.plans.show', ':planId') }}`.replace(':planId', planId))
                        .done(function(data) {
                            $(".modal-loader-data").hide();
                            $("#planForm").show();
                            $('#saveBtn').prop('disabled', false);
                            $('#planForm').attr('action', `{{ route('admin.plans.update', ':planId') }}`.replace(':planId', planId));
                            $('#saveBtn').text('Update');
                            $('#_method').val('PUT');
                            $('#name').val(data.name);
                            $('#price_per_hectare').val(data.price_per_hectare);
                            $('#currency').val(data.currency);
                            $('#isShow').prop('checked', data.isShow);
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error("Error fetching plan data:", textStatus, errorThrown);
                            displayErrors({
                                general: [`${textStatus}: ${errorThrown}`]
                            });
                            $(".modal-loader-data").hide();
                            $("#planForm").hide();
                            $('#saveBtn').prop('disabled', true);
                        });

                }

                function displayErrors(errors = {}) {
                    $('#error-messages').html(cardErrorMessages);
                    Object.values(errors).forEach((message) => {
                        $('#body-messages').append(`<span>${message[0]}</span><br>`);
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>

@section('title', 'Plans Management')
@section('meta_description', 'Manage subscription plans and pricing')

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="mb-3 flex items-center justify-between px-2 align-middle">
                <h2 class="text-foreground text-xl font-semibold">Plans Management</h2>
                <x-button-primary id="createNewPlan" data-modal-target="planModal" data-modal-toggle="planModal" type="button">
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
    <div class="z-60 fixed left-0 right-0 top-0 hidden h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden md:inset-0" id="planModal" aria-hidden="true" tabindex="-1">
        <div class="relative max-h-full w-full max-w-2xl p-4">
            <!-- Modal content -->
            <div class="bg-background border-border relative rounded-lg border shadow-sm">
                <!-- Modal header -->
                <div class="border-foreground/30 flex items-center justify-between rounded-t border-b p-2 md:p-3">
                    <h3 class="modal-title text-foreground text-xl font-semibold">
                        Add Plan
                    </h3>
                    <button class="text-foreground/70 hover:bg-background hover:text-foreground ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm" data-modal-hide="planModal" type="button">
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <!-- Modal body -->
                <div class="modal-body space-y-2 p-2 md:p-3">
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
                                    <x-text-input class="px-1! py-1.5! block w-full" id="price_per_hectare" name="price_per_hectare" type="number" step="0.01" min="0" :value="old('price_per_hectare')" required placeholder="100.00" />
                                </div>

                                <!-- Currency -->
                                <div class="w-full md:w-1/2">
                                    <x-input-label for="currency" :value="__('Currency')" />
                                    <select class="focus:ring-primary focus:border-primary border-ring bg-input/50 text-foreground block w-full rounded-lg border p-2" id="currency" name="currency">
                                        <option value="USD">USD - US Dollar</option>
                                        <option value="IDR">IDR - Indonesian Rupiah</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="GBP">GBP - British Pound</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Is Show -->
                            <div class="flex items-center">
                                <input class="focus:ring-primary focus:border-primary border-ring bg-input/50 text-primary mr-2 rounded" id="isShow" name="isShow" type="checkbox" value="1">
                                <x-input-label class="mb-0" for="isShow" :value="__('Show this plan publicly')" />
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Modal footer -->
                <div class="border-foreground/30 flex flex-row-reverse items-center gap-2 rounded-b border-t p-2 md:p-3">
                    <x-button-primary id="saveBtn" type="submit">
                        Save
                    </x-button-primary>
                    <x-button-light data-modal-hide="planModal" type="button">
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
                                return full.currency + ' ' + data.toLocaleString();
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
                                    return '<span class="bg-success/20 text-success px-2 py-1 rounded-full text-xs font-medium">Visible</span>';
                                } else {
                                    return '<span class="bg-error/20 text-error px-2 py-1 rounded-full text-xs font-medium">Hidden</span>';
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

                const cardErrorMessages = `<div id="body-messages" class="mb-3 rounded-md bg-error/30 p-4 text-sm text-error" role="alert"></div>`;

                const modal = new Modal(document.getElementById('planModal'), {
                    onHide: () => {
                        // Hapus parameter plan_id dari URL
                        let newUrl = window.location.pathname;
                        window.history.pushState({
                            path: newUrl
                        }, "", newUrl);
                    },
                });
                document.querySelectorAll("[data-modal-hide]").forEach((button) => {
                    button.addEventListener("click", function() {
                        modal.hide();
                    });
                });

                // Open modal for creating new plan
                $('#createNewPlan').click(function() {
                    $(".modal-loader-data").hide()
                    $("#planForm").show();
                    $('#planModal').find('.modal-title').text('Add Plan');
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
                            modal.hide();
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
                $('body').on('click', '.editPlan', function() {
                    $(".modal-loader-data").show();
                    $("#planForm").hide();
                    $('#saveBtn').prop('disabled', true);
                    $('#planModal').find('.modal-title').text('Edit Plan');
                    $("#error-messages").html("");
                    const planId = $(this).data('id');
                    // Tampilkan ID Plan di URL tanpa reload halaman
                    let newUrl = window.location.pathname + "?plan_id=" + planId;
                    window.history.replaceState({}, '', newUrl);
                    modal.show();
                    getPlanData(planId);
                });

                // Delete plan
                $('body').on('click', '.deletePlan', function(e) {
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
                let params = new URLSearchParams(window.location.search);
                if (params.has("plan_id")) {
                    let planId = params.get("plan_id");
                    $(".modal-loader-data").show();
                    $("#planForm").hide();
                    $('#saveBtn').prop('disabled', true);
                    $('#planModal').find('.modal-title').text('Edit Plan');
                    $("#error-messages").html("");
                    setTimeout(() => {
                        modal.show();
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

                // Fungsi untuk memperbarui URL dengan parameter baru
                function updateURLParams() {
                    let page = table.page() + 1; // Ambil halaman saat ini (DataTables mulai dari 0)
                    let limit = table.page.len(); // Ambil jumlah data per halaman
                    let url = new URL(window.location.href);
                    url.searchParams.set('page', page);
                    url.searchParams.set('limit', limit);
                    window.history.pushState({}, '', url); // Perbarui URL tanpa reload
                }
                // Event listener untuk paging
                table.on('page.dt', function() {
                    updateURLParams();
                });
                // Event listener tambahan untuk perubahan limit dropdown DataTables
                $('.dt-length select').on('change', function() {
                    updateURLParams();
                });
            });
        </script>
    @endpush
</x-app-layout>

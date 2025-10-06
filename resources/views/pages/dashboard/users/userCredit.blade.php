@section('title', 'User Credits Management')
@section('meta_description', 'Manage user credits and balances')

<x-app-layout>
    <section class="p-1 md:p-4">
        <x-card>
            <div class="mb-3 flex items-center justify-between px-2 align-middle">
                <h2 class="text-foreground text-xl font-semibold">User Credits Management</h2>
            </div>

            <div class="table-container">
                <table class="display table" id="usersTable">
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">User</th>
                            <th scope="col">Email</th>
                            <th scope="col">Current Credits</th>
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

    <!-- Add Credits Modal -->
    <div class="hs-overlay z-80 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="add-credits-modal" role="dialog" aria-labelledby="add-credits-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-lg">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        User Credits
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 bg-foreground/15 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#add-credits-modal" type="button" aria-label="Close">
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

                    <form class="" id="addCreditsForm" method="post" action="">
                        @csrf
                        <input id="_method" name="_method" type="hidden">

                        <div class="space-y-2.5">
                            <!-- User Info -->
                            <div>
                                <x-input-label for="user_info" :value="__('User')" />
                                <x-text-input class="px-1! py-1.5! mt-1 block w-full" id="user_info" name="user_info" type="text" :value="old('user_info')" disabled />
                            </div>

                            <!-- Current Credits -->
                            <div>
                                <x-input-label for="current_credits" :value="__('Current Credits')" />
                                <x-text-input class="px-1! py-1.5! mt-1 block w-full" id="current_credits" name="current_credits" type="number" inputmode="numeric" min="0" step="1" :value="old('current_credits')" />
                                <x-input-error class="mt-2" :messages="$errors->get('current_credits')" />
                            </div>

                            <!-- Credits to Add -->
                            <div>
                                <x-input-label for="credits" :value="__('Credits to Add')" />
                                <x-text-input class="px-1! py-1.5! mt-1 block w-full" id="credits" name="credits" type="number" inputmode="numeric" min="0" step="1" :value="old('credits')" required placeholder="100" />
                                <x-input-error class="mt-2" :messages="$errors->get('credits')" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="border-foreground/20 flex items-center justify-end gap-x-2 border-t px-4 py-3">
                    <x-button-light class="border-border bg-background text-foreground hover:bg-muted focus:bg-muted inline-flex items-center gap-x-2 rounded-lg border px-3 py-2 text-sm font-medium focus:outline-none disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#add-credits-modal" type="button">
                        Close
                    </x-button-light>
                    <x-button-primary id="saveBtn" type="submit">
                        Update
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
                let pageParam = parseInt(urlParams.get('page')) || 1;
                let limitParam = parseInt(urlParams.get('limit')) || 10;

                let table = new DataTable('#usersTable', {
                    responsive: true,
                    scrollX: true,
                    processing: true,
                    serverSide: true,
                    displayStart: (pageParam - 1) * limitParam, // Atur posisi awal paging
                    pageLength: limitParam,
                    ajax: {
                        url: "{{ url()->full() }}",
                        beforeSend: function() {
                            dt_showLoader("#usersTable");
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
                            data: 'user.name',
                            name: 'user.name'
                        },
                        {
                            data: 'user.email',
                            name: 'user.email'
                        },
                        {
                            data: 'credits',
                            name: 'credits',
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

                const modalInstance = HSOverlay.getInstance('#add-credits-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove user_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('user_id')) {
                            url.searchParams.delete('user_id');
                            window.history.replaceState({}, '', url);
                        }
                    });
                }

                const cardErrorMessages = `<div id="body-messages" class="mb-3 rounded-md bg-error/30 p-4 text-sm text-error" role="alert"></div>`;

                // Add credits to user
                $('body').on('click', '.add-credits', function() {
                    $(".modal-loader-data").show();
                    $("#addCreditsForm").hide();
                    $('#saveBtn').prop('disabled', true);
                    $("#error-messages").html("");
                    const userId = $(this).data('id');
                    openModal('#add-credits-modal');
                    getUserData(userId);
                });

                function getUserData(userId) {
                    // Call AJAX to get user data
                    $.get(`{{ route('admin.user-credits.showAddCreditsForm', ':userId') }}`.replace(':userId', userId))
                        .done(function(data) {
                            $(".modal-loader-data").hide();
                            $("#addCreditsForm").show();
                            $('#saveBtn').prop('disabled', false);
                            $('#addCreditsForm').attr('action', `{{ route('admin.user-credits.addCredits', ':userId') }}`.replace(':userId', userId));
                            $('#user_info').val(data.user.name + ' (' + data.user.email + ')');
                            $('#current_credits').val(data.current_credits);
                            $('#credits').val('');
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error("Error fetching user data:", textStatus, errorThrown);
                            displayErrors({
                                general: [`${textStatus}: ${errorThrown}`]
                            });
                            $(".modal-loader-data").hide();
                            $("#addCreditsForm").hide();
                            $('#saveBtn').prop('disabled', true);
                        });
                }

                // Save credits
                $('#saveBtn').on('click', function(e) {
                    e.preventDefault();
                    const formData = $('#addCreditsForm').serialize();
                    const formAction = $('#addCreditsForm').attr('action');
                    const method = $('#addCreditsForm').attr('method') || 'POST';

                    $.ajax({
                        type: method,
                        url: formAction,
                        data: formData,
                        beforeSend: function() {
                            $("#error-messages").html("");
                            $('#saveBtn').prop('disabled', true);
                        },
                        success: function(response) {
                            closeModal('#add-credits-modal');
                            $('#usersTable').DataTable().ajax.reload();
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

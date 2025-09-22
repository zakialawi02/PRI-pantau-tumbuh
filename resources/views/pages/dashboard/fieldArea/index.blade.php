@section('title', $data['title'] ?? 'Field Areas')
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ $data['title'] }}</h1>
            <p class="text-foreground/60 mt-1">
                @if (auth()->user()->role === 'user')
                    View your field areas
                @else
                    Manage all field areas in the system
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
                                Field Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Area (ha)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Created At
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" scope="col">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-foreground/20 divide-y">
                        @forelse ($data['query'] as $key => $fieldArea)
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                    <td>{{ $fieldArea->user->name }}</td>
                                @endif
                                <td>{{ $fieldArea->name }}</td>
                                <td>{{ Number::format($fieldArea->area_ha ?? 0, locale: app()->getLocale()) }} ha</td>
                                <td>{{ $fieldArea->subscriptions->status }}</td>
                                <td>{{ $fieldArea->created_at->isoFormat('LL, HH:mm') }}</td>
                                <td>
                                    <x-button-primary class="bg-secondary/80 hover:bg-secondary/60 btn-view-area text-neutral inline-flex items-center rounded-full px-2 py-1 text-xs font-medium" data-id="{{ $fieldArea->id }}" type="button" title="View Details" size="small">
                                        <i class="ri-eye-line mr-1"></i> View
                                    </x-button-primary>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="7">No field areas found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </section>

    <!-- Field Area Details Modal -->
    <div class="hs-overlay z-90 pointer-events-none fixed start-0 top-0 hidden size-full overflow-y-auto overflow-x-hidden" id="field-area-modal" role="dialog" aria-labelledby="field-area-modal-label" tabindex="-1">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 m-3 flex min-h-[calc(100%-56px)] scale-95 items-center opacity-0 transition-all duration-200 ease-in-out sm:mx-auto sm:w-full sm:max-w-md">
            <div class="shadow-2xs border-foreground/20 bg-background pointer-events-auto flex w-full flex-col rounded-xl border">
                <div class="border-foreground/20 flex items-center justify-between border-b px-4 py-3">
                    <h3 class="modal-title text-foreground font-semibold">
                        Field Area Details
                    </h3>
                    <button class="focus:outline-hidden hover:bg-foreground/20 focus:bg-foreground/20 text-foreground/80 inline-flex size-8 items-center justify-center gap-x-2 rounded-full border border-transparent disabled:pointer-events-none disabled:opacity-50" data-hs-overlay="#field-area-modal" type="button" aria-label="Close">
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

                    <div class="" id="field-area-details">
                        <!-- Map Container -->
                        <div class="mb-4">
                            <h4 class="text-foreground text-lg font-medium">Field Area Map</h4>
                            <div class="border-foreground/30 mt-2 h-64 w-full rounded-lg border" id="field-area-map"></div>
                        </div>

                        <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <h4 class="text-foreground text-lg font-medium">Field Information</h4>
                                <dl class="mt-2 space-y-2">
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Field Name</dt>
                                        <dd class="text-foreground text-sm" id="modal-field-name"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Area Size</dt>
                                        <dd class="text-foreground text-sm" id="modal-area-size"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-foreground/60 text-sm font-medium">Status</dt>
                                        <dd class="text-foreground text-sm" id="modal-status-area"></dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h4 class="text-foreground text-lg font-medium">Date Information</h4>
                                <dl class="mt-2 space-y-2">
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
                    </div>

                    <!-- Error message container -->
                    <div class="text-error hidden py-4 text-center" id="field-area-details-error">
                        Error fetching field area details data
                    </div>
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
                let Map = ol.Map;
                let View = ol.View;
                let TileLayer = ol.layer.Tile;
                let LayerGroup = ol.layer.Group;
                let VectorLayer = ol.layer.Vector;
                let VectorSource = ol.source.Vector;
                let XYZ = ol.source.XYZ;
                let GeoJSON = ol.format.GeoJSON;
                const World_Boundaries_and_Places = new TileLayer({
                    source: new ol.source.XYZ({
                        url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}",
                        maxZoom: 18,
                        crossOrigin: "anonymous",
                        attributions: '© <a href="https://www.esri.com/">Esri</a>',
                    }),
                });
                const World_Imagery = new TileLayer({
                    source: new XYZ({
                        url: "https://services.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
                        maxZoom: 18,
                        crossOrigin: "anonymous",
                        attributions: '© <a href="https://www.esri.com/">Esri</a>, © <a href="https://www.digitalglobe.com/">DigitalGlobe</a>, © <a href="http://www.geoeye.com/">GeoEye</a>, © <a href="https://www.i-cubed.com/">i-cubed</a>, © <a href="https://www.usda.gov/">USDA</a>, © <a href="https://www.usgs.gov/">USGS</a>, © <a href="https://www.aerogrid.com/">AeroGRID</a>, © <a href="https://www.igncorporation.com/">IGN</a>, and the GIS User Community',
                    }),
                });
                const World_Transportation = new TileLayer({
                    source: new XYZ({
                        url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}",
                        maxZoom: 18,
                        crossOrigin: "anonymous",
                        attributions: '© <a href="https://www.esri.com/">Esri</a>',
                    }),
                });
                const esriMapGroup = new LayerGroup({
                    layers: [World_Imagery, World_Boundaries_and_Places, World_Transportation],
                    visible: true,
                });

                // Function to render GeoJSON on map
                function renderFieldAreaMap(geojsonData) {
                    // Clear previous map if exists
                    if (window.fieldAreaMap) {
                        window.fieldAreaMap.setTarget(null);
                    }

                    // Get the map container element
                    const mapContainer = document.getElementById('field-area-map');

                    // Initialize new map
                    const map = new Map({
                        target: 'field-area-map',
                        layers: [esriMapGroup],
                        view: new View({
                            center: [0, 0],
                            zoom: 2,
                            maxZoom: 19
                        })
                    });

                    if (geojsonData) {
                        // Create vector source and layer for GeoJSON
                        const vectorSource = new VectorSource({
                            features: new GeoJSON().readFeatures(geojsonData, {
                                featureProjection: 'EPSG:3857',
                                dataProjection: 'EPSG:4326'
                            })
                        });

                        const vectorLayer = new VectorLayer({
                            source: vectorSource,
                            style: new ol.style.Style({
                                fill: new ol.style.Fill({
                                    color: 'rgba(255, 0, 0, 0.2)'
                                }),
                                stroke: new ol.style.Stroke({
                                    color: 'rgba(0, 0, 255, 1)',
                                    width: 2
                                })
                            })
                        });

                        map.addLayer(vectorLayer);

                        // Fit view to the geometry after a small delay to ensure DOM is ready
                        setTimeout(function() {
                            const extent = vectorSource.getExtent();
                            if (extent && extent[0] !== Infinity) {
                                map.getView().fit(extent, {
                                    padding: [50, 50, 50, 50],
                                    duration: 500,
                                });
                            }
                        }, 100);
                    }

                    // Store map instance
                    window.fieldAreaMap = map;
                    return map;
                }

                const modalInstance = HSOverlay.getInstance('#field-area-modal', true);
                if (modalInstance && modalInstance.element) {
                    // Listen for the close event
                    modalInstance.element.on('close', function() {
                        // Remove area_id parameter from URL if present
                        let url = new URL(window.location);
                        if (url.searchParams.has('area_id')) {
                            url.searchParams.delete('area_id');
                        }
                        window.history.replaceState({}, '', url);
                    });
                }

                // View Field Area Details
                $(document).on('click', '.btn-view-area', function() {
                    let areaId = $(this).data('id');
                    currentFieldAreaId = areaId;

                    // Update URL with area ID
                    let newUrl = new URL(window.location);
                    newUrl.searchParams.set('area_id', currentFieldAreaId);
                    window.history.pushState({}, '', newUrl);

                    // Show loader
                    $('#modal-loader-data').removeClass('hidden');
                    $('#field-area-details').addClass('hidden');
                    $('#field-area-details-error').addClass('hidden');

                    openModal('#field-area-modal');
                    getAreaDetails(areaId);
                });

                function getAreaDetails(areaId) {
                    // Show loader
                    $('#modal-loader-data').removeClass('hidden');
                    $('#field-area-details').addClass('hidden');
                    $('#field-area-details-error').addClass('hidden');

                    $.ajax({
                        url: `{{ route('admin.fieldArea.show', ':field_area_id') }}`.replace(':field_area_id', areaId),
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                let data = response.data;
                                console.log(data);

                                // Populate field information
                                $('#modal-field-name').text(data.name || '-');
                                $('#modal-area-size').text((data.area_ha ? formatNumber(data.area_ha) : '-') + ' ha');

                                const status = data?.subscriptions?.status || '-';
                                const statusConfig = STATUS_CONFIG_BADGE_COLOR?.[data?.subscriptions?.status] || STATUS_CONFIG_BADGE_COLOR?.default || {
                                    class: "bg-gray-100 text-gray-800",
                                    text: status
                                };
                                $('#modal-status-area').html(`<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold ${statusConfig.class}">${statusConfig.text}</span>`);

                                // Format dates
                                let createdAt = data.created_at ? formatCustomDate(data.created_at) : '-';
                                let updatedAt = data.updated_at ? formatCustomDate(data.updated_at) : '-';

                                $('#modal-created-at').text(createdAt);
                                $('#modal-updated-at').text(updatedAt);

                                // Render GeoJSON map with a slight delay to ensure modal is fully open
                                setTimeout(function() {
                                    renderFieldAreaMap(data.geom);
                                }, 300);

                                // Populate customer information if user is admin/superadmin
                                @if (in_array(auth()->user()->role, ['superadmin', 'admin']))
                                    if (data.user) {
                                        $('#modal-customer-name').text(data.user.name || '-');
                                        $('#modal-customer-email').text(data.user.email || '-');
                                    } else {
                                        $('#modal-customer-name').text('-');
                                        $('#modal-customer-email').text('-');
                                    }
                                @endif

                                // Hide loader and show details
                                $('#modal-loader-data').addClass('hidden');
                                $('#field-area-details').removeClass('hidden');
                            } else {
                                $('#modal-loader-data').addClass('hidden');
                                $('#field-area-details-error').removeClass('hidden').text(response.message || 'Error fetching data');
                            }
                        },
                        error: function(xhr) {
                            $('#modal-loader-data').addClass('hidden');
                            $('#field-area-details-error').removeClass('hidden').text('Error fetching data');
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>

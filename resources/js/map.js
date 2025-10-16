let Map = ol.Map;
let View = ol.View;
let Source = ol.source.ImageTile;
let OSM = ol.source.OSM;
let TileLayer = ol.layer.Tile;
let TileSource = ol.source.Tile;
let XYZ = ol.source.XYZ;
let Layer = ol.layer.WebGLTile;
let {
    fromLonLat,
    toLonLat,
    Projection,
    useGeographic,
    getProjection,
    getTransform,
    addCoordinateTransforms,
    addProjection,
    transform,
} = ol.proj;
let VectorLayer = ol.layer.Vector;
let VectorSource = ol.source.Vector;
let LayerGroup = ol.layer.Group;
let Overlay = ol.Overlay;
let TileWMS = ol.source.TileWMS;
let GeoJSON = ol.format.GeoJSON;
let Feature = ol.Feature;
let { Point, Circle, LineString, Polygon } = ol.geom;
let {
    Circle: CircleStyle,
    Style,
    Fill,
    Stroke,
    Text,
    IconImage,
    Icon,
} = ol.style;
let { Attribution, OverviewMap, ScaleLine, MousePosition } = ol.control;
let { register } = ol.proj.proj4;
let { format, toStringHDMS, toStringXY } = ol.coordinate;
let Draw = ol.interaction.Draw;
let { getArea, getLength } = ol.sphere;
let { unByKey } = ol.Observable;

let WGS84 = new Projection("EPSG:4326");
let MERCATOR = new Projection("EPSG:3857");
let UTM49S = new Projection("EPSG:32649");

const loader = `<div class="text-center"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></>`;

// Init View
const view = new View({
    // projection: "EPSG:4326",
    center: ol.proj.fromLonLat([113.37706060700374, -1.1304744207092927]),
    zoom: 5.8,
    minZoom: 5,
    maxZoom: 19,
});

// BaseMap
const osmBaseMap = new TileLayer({
    source: new OSM(),
    crossOrigin: "anonymous",
    visible: false,
    preload: 15,
});

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
        attributions:
            '© <a href="https://www.esri.com/">Esri</a>, © <a href="https://www.digitalglobe.com/">DigitalGlobe</a>, © <a href="http://www.geoeye.com/">GeoEye</a>, © <a href="https://www.i-cubed.com/">i-cubed</a>, © <a href="https://www.usda.gov/">USDA</a>, © <a href="https://www.usgs.gov/">USGS</a>, © <a href="https://www.aerogrid.com/">AeroGRID</a>, © <a href="https://www.igncorporation.com/">IGN</a>, and the GIS User Community',
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

const mapboxBaseURL =
    "https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiNjg2MzUzMyIsImEiOiJjbDh4NDExZW0wMXZsM3ZwODR1eDB0ajY0In0.6jHWxwN6YfLftuCFHaa1zw";
const mapboxStyleId = "mapbox/streets-v11";
const mapboxSource = new XYZ({
    url: mapboxBaseURL.replace("{id}", mapboxStyleId),
    attributions:
        '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> <strong><a href="https://www.mapbox.com/map-feedback/" target="_blank">Improve this map</a></strong>',
});
const mapboxBaseMap = new ol.layer.Tile({
    source: mapboxSource,
    crossOrigin: "anonymous",
    visible: false,
    preload: 15,
});

const esriMap = new TileLayer({
    source: new Source({
        attributions:
            'Tiles © <a href="https://services.arcgisonline.com/ArcGIS/' +
            'rest/services/World_Topo_Map/MapServer">ArcGIS</a>',
        url:
            "https://server.arcgisonline.com/ArcGIS/rest/services/" +
            "World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
    }),
    crossOrigin: "anonymous",
    visible: false,
});

const baseMaps = [osmBaseMap, esriMapGroup, mapboxBaseMap, esriMap];

// Minimap
const overviewMapControl = new OverviewMap({
    layers: [
        new TileLayer({
            source: new OSM(),
        }),
    ],
    // Don't use target initially - let OpenLayers handle the positioning
    className: "ol-overviewmap ol-custom-overviewmap",
    collapsed: false,
    tipLabel: "Minimap",
    collapseLabel: "\u00BB",
    label: "\u00AB",
});

// Attribution
const attribution = new Attribution({
    target: document.getElementById("attribution"),
    collapsible: true,
    className: "ol-attribution",
});

// ScaleLine
const scaleControl = new ScaleLine({
    target: document.getElementById("scaleline"),
    units: "metric",
    bar: true,
    steps: 4,
    text: true,
    minWidth: 140,
    maxWidth: 180,
    className: "ol-scale-line",
});

// Custom zoom functions (no default zoom control used)
// Zoom functionality will be handled by custom buttons

// Mouse Position
const mousePositionControl = new MousePosition({
    target: document.getElementById("mousePosition"),
    coordinateFormat: function (coordinate) {
        const [lon, lat] = coordinate;
        const formattedLon = formatNumber(
            lon,
            6,
            document.documentElement.lang
        );
        const formattedLat = formatNumber(
            lat,
            6,
            document.documentElement.lang
        );
        return (
            "Long: " + formattedLon + " &nbsp&nbsp&nbsp  Lat: " + formattedLat
        );
    },
    projection: "EPSG:4326",
    placeholder: "Long: - &nbsp&nbsp&nbsp  Lat: -",
    className: "ol-custom-mouse-position",
});

//** STYLE ***/
// marker style using custom SVG
const markerClickedStyle = new Style({
    image: new Icon({
        anchor: [0.5, 0.99],
        anchorXUnits: "fraction",
        anchorYUnits: "fraction",
        with: 50,
        height: 50,
        opacity: 0.9,
        src: "/assets/img/icon/marker.svg",
    }),
});

// hightlight style
const hightlightClickedStyle = new ol.style.Style({
    fill: new ol.style.Fill({
        color: "orange",
    }),
    stroke: new ol.style.Stroke({
        color: "yellow",
        width: 2,
    }),
});

// Custom marker style for search results (same as clicked marker)
const customMarkerStyle = markerClickedStyle;

// Init To Canvas/View
let map = new Map({
    target: "map",

    layers: [
        new LayerGroup({
            layers: baseMaps,
        }),
    ],

    view: view,

    controls: [
        scaleControl,
        overviewMapControl,
        attribution,
        mousePositionControl,
    ],
});

// Add click handler to clear markers when clicking on empty areas
map.on("click", function (evt) {
    // Check if we clicked on a feature
    const feature = map.forEachFeatureAtPixel(evt.pixel, function (feature) {
        return feature;
    });

    // If no feature was clicked, clear the markers
    if (!feature) {
        vectorSourceEventClick.clear();
    }
});

map.on("loadstart", function () {
    map.getTargetElement().classList.add("spinner");
});
map.on("loadend", function () {
    map.getTargetElement().classList.remove("spinner");
});

function showSpinner() {
    map.getTargetElement().classList.add("spinner");
}

function hideSpinner() {
    map.getTargetElement().classList.remove("spinner");
}

window.addEventListener("resize", () => {
    if (window.innerWidth <= 768) {
        map.removeControl(mousePositionControl);
    } else {
        if (!map.getControls().getArray().includes(mousePositionControl)) {
            map.addControl(mousePositionControl);
        }
    }
});

function setBasemap(mapType, element = null) {
    document.getElementById("active-basemap").src =
        element?.nextElementSibling?.src ?? element?.querySelector("img")?.src;
    if (mapType === "osm") {
        setOsmBasemap();
    } else if (mapType === "bing") {
        setBingmapBasemap();
    } else if (mapType === "mapbox") {
        setMapboxBasemap();
    } else if (mapType === "esriTerrain") {
        setEsriBasemap();
    }

    localStorage.setItem("basemap", mapType);
}

function toggleOptions() {
    document.querySelector(".basemap-options").classList.toggle("flex");
}

function initBasemap() {
    const savedBasemap = localStorage.getItem("basemap");
    if (savedBasemap) {
        const element = document.querySelector(
            `input[name='basemap'][value='${savedBasemap}']`
        ).parentElement;
        setBasemap(savedBasemap, element);
    } else {
        const checkedInput = document.querySelector(
            "input[name='basemap']:checked"
        );
        setBasemap(checkedInput.value, checkedInput.parentElement);
    }
}
initBasemap();

function setOsmBasemap() {
    osmBaseMap.setVisible(true);
    esriMapGroup.setVisible(false);
    mapboxBaseMap.setVisible(false);
    esriMap.setVisible(false);
}

function setBingmapBasemap() {
    osmBaseMap.setVisible(false);
    esriMapGroup.setVisible(true);
    mapboxBaseMap.setVisible(false);
    esriMap.setVisible(false);
}

function setMapboxBasemap() {
    osmBaseMap.setVisible(false);
    esriMapGroup.setVisible(false);
    mapboxBaseMap.setVisible(true);
    esriMap.setVisible(false);
}

function setEsriBasemap() {
    osmBaseMap.setVisible(false);
    esriMapGroup.setVisible(false);
    mapboxBaseMap.setVisible(false);
    esriMap.setVisible(true);
}

// Layer Click Event
const vectorSourceEventClick = new VectorSource();
const vectorLayerEventClick = new VectorLayer({
    source: vectorSourceEventClick,
    style: markerClickedStyle,
    zIndex: 9999,
});
map.addLayer(vectorLayerEventClick);

/**
 * Marks a clicked position on the map.
 *
 * @param {ol.Coordinate} coordinate - The coordinate of the clicked position.
 * @return {void}
 */
function markToClickedPosition(coordinate) {
    const marker = new Feature({
        geometry: new Point(coordinate),
    });
    if (vectorSourceEventClick) {
        vectorSourceEventClick.clear();
    }
    vectorLayerEventClick.getSource().addFeatures([marker]);
}

// Geocoding
let searchTimeout;
const resultsBox = $("#search-results-recommendation");
const searchLocationInput = $("#search-location");
const clearSearchButton = $("#clear-search");

// Add event listener for search input
if (searchLocationInput.length && resultsBox.length) {
    searchLocationInput.on("input", function (e) {
        const q = $(this).val().trim();
        if (!q) {
            resultsBox.addClass("hidden");
            clearSearchButton.addClass("hidden");
            return;
        }

        // Show clear button when there's text
        clearSearchButton.removeClass("hidden");

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchPlace(q), 400);
    });

    // Add event listener for Enter key
    searchLocationInput.on("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            const q = $(this).val().trim();
            if (q) {
                // Clear any pending search
                clearTimeout(searchTimeout);
                // Process immediately
                processSearchOnEnter(q);
            }
        }
    });

    // Add event listener for clear button
    clearSearchButton.on("click", function () {
        searchLocationInput.val("");
        resultsBox.addClass("hidden");
        clearSearchButton.addClass("hidden");
        // Clear markers
        vectorSourceEventClick.clear();
    });

    // Hide results when clicking outside
    $(document).on("click", function (e) {
        if (
            !searchLocationInput.is(e.target) &&
            !resultsBox.is(e.target) &&
            resultsBox.has(e.target).length === 0 &&
            !clearSearchButton.is(e.target)
        ) {
            resultsBox.addClass("hidden");
        }
    });
}

// Function to validate and parse coordinate input
function parseCoordinateInput(input) {
    // Remove extra spaces and convert to lowercase
    input = input.trim().toLowerCase();

    // Don't process if input is too short to be a coordinate
    if (input.length < 10) {
        return null;
    }

    // Regex patterns for different coordinate formats
    // Format 1: Decimal degrees (lat, lon or lon, lat)
    const decimalPattern1 = /^(-?\d+\.?\d*)[,\s]+(-?\d+\.?\d*)$/;
    // Format 2: Degrees, minutes, seconds
    const dmsPattern =
        /^(-?\d+)[°\s]+(\d+)[′'\s]+(\d+\.?\d*)[″"\s]*[,\s]+(-?\d+)[°\s]+(\d+)[′'\s]+(\d+\.?\d*)[″"\s]*$/;
    // Format 3: Degrees and decimal minutes
    const ddmPattern =
        /^(-?\d+)[°\s]+(\d+\.?\d*)[′'\s]+[,\s]+(-?\d+)[°\s]+(\d+\.?\d*)[′'\s]*$/;

    let match;

    // Try decimal degrees format
    if ((match = input.match(decimalPattern1))) {
        const lat = parseFloat(match[1]);
        const lon = parseFloat(match[2]);

        // Check if the values are valid coordinates
        if (isValidCoordinate(lat, lon)) {
            return [lon, lat]; // Return as [longitude, latitude]
        }

        // Try swapping lat/lon if the first attempt is invalid
        if (isValidCoordinate(lon, lat)) {
            return [lat, lon]; // Return as [longitude, latitude]
        }
    }

    // Try DMS format
    if ((match = input.match(dmsPattern))) {
        const latDeg = parseInt(match[1]);
        const latMin = parseInt(match[2]);
        const latSec = parseFloat(match[3]);
        const lonDeg = parseInt(match[4]);
        const lonMin = parseInt(match[5]);
        const lonSec = parseFloat(match[6]);

        const lat = latDeg + latMin / 60 + latSec / 3600;
        const lon = lonDeg + lonMin / 60 + lonSec / 3600;

        if (isValidCoordinate(lat, lon)) {
            return [lon, lat]; // Return as [longitude, latitude]
        }
    }

    // Try DDM format
    if ((match = input.match(ddmPattern))) {
        const latDeg = parseInt(match[1]);
        const latMin = parseFloat(match[2]);
        const lonDeg = parseInt(match[3]);
        const lonMin = parseFloat(match[4]);

        const lat = latDeg + latMin / 60;
        const lon = lonDeg + lonMin / 60;

        if (isValidCoordinate(lat, lon)) {
            return [lon, lat]; // Return as [longitude, latitude]
        }
    }

    return null; // Invalid coordinate format
}

// Function to validate coordinate values
function isValidCoordinate(lat, lon) {
    return lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180;
}

// Function to process search when Enter is pressed
function processSearchOnEnter(q) {
    // Check if input is a coordinate
    const coordinate = parseCoordinateInput(q);
    if (coordinate) {
        // If it's a valid coordinate, focus on that location
        focusPlace(coordinate);
        resultsBox.addClass("hidden");
        // Don't clear the input - keep the coordinate value
        return;
    }

    // For location names, check if we have search results
    if (resultsBox.find("button").length > 0) {
        // Click the first result
        resultsBox.find("button").first().click();
    } else {
        // If no results yet, do a search and then click first result
        searchPlaceAndClickFirst(q);
    }
}

// Function to search and click the first result
async function searchPlaceAndClickFirst(q) {
    if (!resultsBox.length) return;

    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(
        q
    )}&limit=6&addressdetails=1`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.length > 0) {
            // Focus on the first result
            const firstResult = data[0];
            const lon = parseFloat(firstResult.lon);
            const lat = parseFloat(firstResult.lat);
            focusPlace([lon, lat]);
            resultsBox.addClass("hidden");
            // Don't clear the input - keep the search value
        } else {
            // No results found
            resultsBox.removeClass("hidden");
            resultsBox.html(
                '<div class="p-2 text-sm text-gray-500">No results found</div>'
            );
        }
    } catch (error) {
        console.error("Search error:", error);
        resultsBox.removeClass("hidden");
        resultsBox.html(
            '<div class="p-2 text-sm text-red-500">Search failed. Please try again.</div>'
        );
    }
}

async function searchPlace(q) {
    if (!resultsBox.length) return;

    // First check if it's a valid coordinate
    const coordinate = parseCoordinateInput(q);
    if (coordinate) {
        // Show "Go to coordinate" option in results
        resultsBox.removeClass("hidden");
        resultsBox.html(`
            <button class="block w-full text-left rounded-lg p-2 hover:bg-gray-100 transition-colors border-b border-gray-100 last:border-b-0" data-coord='${JSON.stringify(
                coordinate
            )}'>
                <div class='font-medium'>Go to Coordinate</div>
                <div class='text-sm text-gray-600'>${q}</div>
            </button>
        `);

        // Add click event to the coordinate result
        resultsBox
            .find("button")
            .first()
            .on("click", function () {
                const coord = JSON.parse($(this).data("coord"));
                focusPlace(coord);
                resultsBox.addClass("hidden");
                // Don't clear the input - keep the coordinate value
            });
        return;
    }

    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(
        q
    )}&limit=6&addressdetails=1`;

    resultsBox.removeClass("hidden");
    resultsBox.html(
        '<div class="p-2 text-sm text-gray-500">Searching...</div>'
    );

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.length === 0) {
            resultsBox.html(
                '<div class="p-2 text-sm text-gray-500">No results found</div>'
            );
            return;
        }

        let resultsHtml = "";
        data.forEach((item) => {
            resultsHtml += `<button class="block w-full text-left rounded-lg p-2 hover:bg-gray-100 transition-colors border-b border-gray-100 last:border-b-0" data-lat='${
                item.lat
            }' data-lon='${item.lon}'>
                        <div class='font-medium'>${
                            item.display_name.split(",")[0]
                        }</div>
                        <div class='text-sm text-gray-600'>${item.display_name
                            .substring(item.display_name.indexOf(",") + 1)
                            .trim()}</div>
                    </button>`;
        });

        resultsBox.html(resultsHtml);

        // Add click event to each result
        resultsBox.find("button").each(function () {
            $(this).on("click", function () {
                const lon = parseFloat($(this).data("lon"));
                const lat = parseFloat($(this).data("lat"));
                focusPlace([lon, lat]);
                resultsBox.addClass("hidden");
                // Don't clear the input - keep the search value
            });
        });
    } catch (error) {
        console.error("Search error:", error);
        resultsBox.html(
            '<div class="p-2 text-sm text-red-500">Search failed. Please try again.</div>'
        );
    }
}

// Focus place
function focusPlace(coord) {
    const c = ol.proj.fromLonLat(coord);
    map.getView().animate({ center: c, zoom: 15, duration: 800 });

    // Clear previous markers
    vectorSourceEventClick.clear();

    // Add new marker with custom SVG image
    const marker = new Feature({ geometry: new Point(c) });
    marker.setStyle(markerClickedStyle);
    vectorLayerEventClick.getSource().addFeature(marker);
}

function updatePanel(t, d) {
    $("#panelTitle").innerHTML = t;
    $("#panelDesc").innerHTML = d;
}

// Global variabel
let geojsonFeature;
let geojsonArea;
let drawingRunning;
let drawed;
let minimapVisible = true;
let listener;

/**
 * Vector source for drawing.
 * @type {VectorSource}
 */
let vectorSourceDrawing = new VectorSource();
let vectorLayerDrawing;

/**
 * store current drawing interaction
 */
let draw;

/**
 * Currently drawn feature.
 * @type {import("../src/ol/Feature.js").default}
 */
let sketch;

/**
 * The help tooltip element.
 * @type {HTMLElement}
 */
let helpTooltipElement;

/**
 * Overlay to show the help messages.
 * @type {Overlay}
 */
let helpTooltip;

/**
 * The measure tooltip element.
 * @type {HTMLElement}
 */
let measureTooltipElement;

/**
 * Overlay to show the measurement.
 * @type {Overlay}
 */
let measureTooltip;

/**
 * Message to show when the user is drawing a polygon.
 * @type {string}
 */
const continuePolygonMsg = "Click to continue drawing the polygon";

/**
 * Message to show when the user is drawing a line.
 * @type {string}
 */
const continueLineMsg = "Click to continue drawing the line";

/**
 * Handle pointer move.
 * @param {import("../src/ol/MapBrowserEvent").default} evt The event.
 */
const pointerMoveHandler = function (evt) {
    if (evt.dragging) {
        return;
    }
    /** @type {string} */
    let helpMsg = "Click to start drawing";

    if (sketch) {
        const geom = sketch.getGeometry();
        if (geom instanceof Polygon) {
            helpMsg = continuePolygonMsg;
        } else if (geom instanceof LineString) {
            helpMsg = continueLineMsg;
        }
    }

    if (helpTooltipElement) {
        helpTooltipElement.innerHTML = helpMsg;
        helpTooltip.setPosition(evt.coordinate);
        helpTooltipElement.classList.remove("hidden");
    }
};

map.on("pointermove", pointerMoveHandler);

map.getViewport().addEventListener("mouseout", function () {
    if (helpTooltipElement) {
        helpTooltipElement.classList.add("hidden");
    }
});

/**
 * Format length output.
 * @param {LineString} line The line.
 * @return {string} The formatted length.
 */
const formatLength = function (line) {
    const length = getLength(line);
    let output;
    if (length > 100) {
        output = Math.round((length / 1000) * 100) / 100 + " " + "km";
    } else {
        output = Math.round(length * 100) / 100 + " " + "m";
    }
    return output;
};

/**
 * Format area output.
 * @param {Polygon} polygon The polygon.
 * @return {string} Formatted area.
 */
const formatArea = function (polygon) {
    const area = getArea(polygon);
    let output;
    if (area > 10000) {
        output =
            formatNumber(area / 10000, 2, document.documentElement.lang) +
            " ha";
    } else {
        output = formatNumber(area, 2, document.documentElement.lang) + " m²";
    }
    return output;
};

// Style definition
const drawingStyle = new Style({
    fill: new Fill({
        color: "rgba(255, 0, 0, 0.2)",
    }),
    stroke: new Stroke({
        color: "rgba(255, 0, 0, 1)",
        lineDash: [10, 10],
        width: 2,
    }),
    image: new CircleStyle({
        radius: 5,
        stroke: new Stroke({
            color: "rgba(255, 0, 0, 1)",
        }),
        fill: new Fill({
            color: "rgba(255, 0, 0, 0.2)",
        }),
    }),
});

/**
 * Adds a draw interaction to the map for measuring.
 * @param {string} [type="Polygon"] The type of geometry to draw. Can be "Polygon" or "LineString".
 * @returns {void}
 */
function addInteraction(type = "Polygon") {
    // Remove previous measurement layer and tooltips if any
    if (vectorLayerDrawing) {
        map.removeLayer(vectorLayerDrawing);
    }

    // Clear the vector source to remove any existing features
    vectorSourceDrawing.clear();

    // Remove previous tooltips from the DOM if they exist
    if (measureTooltipElement) {
        measureTooltipElement.remove(); // Remove tooltip element from DOM
    }

    // Remove the tooltip overlay from the map if it exists
    if (measureTooltip) {
        map.removeOverlay(measureTooltip); // Remove the overlay from the map
    }

    // Remove previous help tooltip if any
    if (helpTooltipElement) {
        helpTooltipElement.remove();
    }

    // Create the vector layer for measuring
    vectorLayerDrawing = new VectorLayer({
        source: vectorSourceDrawing,
        style: drawingStyle,
        zIndex: 999,
    });
    map.addLayer(vectorLayerDrawing);

    // Create a new draw interaction
    draw = new Draw({
        source: vectorSourceDrawing,
        type: type,
        style: drawingStyle,
    });

    // Add the new draw interaction to the map
    map.addInteraction(draw);

    createMeasureTooltip();
    createHelpTooltip();

    draw.on("drawstart", function (evt) {
        sketch = evt.feature;
        let tooltipCoord = evt.coordinate;

        listener = sketch.getGeometry().on("change", function (evt) {
            const geom = evt.target;
            let output;
            if (geom instanceof Polygon) {
                output = formatArea(geom);
                tooltipCoord = geom.getInteriorPoint().getCoordinates();
            } else if (geom instanceof LineString) {
                output = formatLength(geom);
                tooltipCoord = geom.getLastCoordinate();
            }
            geojsonArea = getArea(geom);
            window.geojsonArea = geojsonArea;
            measureTooltipElement.innerHTML = output;
            measureTooltip.setPosition(tooltipCoord);
        });
    });

    draw.on("drawend", function (evt) {
        drawed = true;

        // Get the feature drawn by the user
        const feature = evt.feature;

        // Convert the feature to GeoJSON
        const geojsonFormat = new GeoJSON();
        const geojson = geojsonFormat.writeFeature(feature, {
            dataProjection: "EPSG:4326", // Output projection
            featureProjection: "EPSG:3857", // Map's projection (assuming EPSG:3857)
        });
        geojsonFeature = JSON.parse(geojson);

        // Display the GeoJSON string in the #drawerGeojson element
        const geojsonOutput = document.getElementById("drawerGeojson");
        if (geojsonOutput) {
            geojsonOutput.innerHTML = `<pre>${JSON.stringify(geojsonFeature, null, 1)}</pre>`;
        }

        const measurementOutput = document.getElementById("measurementOutput");
        if (measurementOutput) {
            measurementOutput.innerHTML =
                formatNumber(geojsonArea / 10000) + " ha";
        }

        drawingEnd();

        const detail = {
            geojson: geojsonFeature,
            geometry: geojsonFeature.geometry,
            areaSquareMeters: geojsonArea,
            areaHectares: geojsonArea / 10000,
            pretty: JSON.stringify(geojsonFeature, null, 1),
        };

        try {
            document.dispatchEvent(
                new CustomEvent("app:polygon-drawn", { detail })
            );
        } catch (error) {
            console.warn("Unable to dispatch polygon drawn event", error);
        }

        if (typeof window.calculateTotalPrice === "function") {
            window.calculateTotalPrice();
        }
    });
}

/**
 * Start the draw interaction and clear the vector layer if exists
 * @returns {void}
 */
function drawingStart() {
    addInteraction();
    drawingRunning = true;
    drawed = null;
    buttonStateDrawing();
    $("#drawerGeojson").html("");

    try {
        document.dispatchEvent(new CustomEvent("app:polygon-reset"));
    } catch (error) {
        console.warn("Unable to dispatch polygon reset event", error);
    }

    window.geojsonArea = 0;
}

/**
 * End the draw interaction
 * @returns {void}
 */
function drawingEnd() {
    drawingRunning = false;

    // Remove the measurement tooltip overlay from the map
    if (measureTooltip) {
        map.removeOverlay(measureTooltip);
    }

    measureTooltipElement.className = "ol-tooltip ol-tooltip-static";
    measureTooltip.setOffset([0, -7]);
    sketch = null;
    measureTooltipElement = null;

    // Remove tooltips and overlays
    if (measureTooltipElement) {
        measureTooltipElement.remove();
    }
    if (helpTooltipElement) {
        helpTooltipElement.remove();
    }

    // Remove the draw interaction and vector layer after drawing is done
    map.removeInteraction(draw);

    // Unset the listener to avoid memory leaks
    if (listener) {
        unByKey(listener);
        listener = null;
    }

    buttonStateDrawing();
}

/**
 * Creates a new help tooltip
 * @returns {void}
 */
function createHelpTooltip() {
    if (helpTooltipElement) {
        helpTooltipElement.remove();
    }
    helpTooltipElement = document.createElement("div");
    helpTooltipElement.className = "ol-tooltip hidden";
    helpTooltip = new Overlay({
        element: helpTooltipElement,
        offset: [15, 0],
        positioning: "center-left",
    });
    map.addOverlay(helpTooltip);
}

/**
 * Creates a new measure tooltip
 * @returns {void}
 */
function createMeasureTooltip() {
    if (measureTooltipElement) {
        measureTooltipElement.remove();
    }
    measureTooltipElement = document.createElement("div");
    measureTooltipElement.className = "ol-tooltip ol-tooltip-measure";
    measureTooltip = new Overlay({
        element: measureTooltipElement,
        offset: [0, -15],
        positioning: "bottom-center",
        stopEvent: false,
        insertFirst: false,
    });
    map.addOverlay(measureTooltip);
}

/**
 * Update the draw button state and text based on the value of drawingRunning
 * @returns {void}
 */
function buttonStateDrawing() {
    const button = $("#drawPolygonBtn");
    if (!button.length) {
        return;
    }
    button.html(
        drawingRunning
            ? "<i class='ri-close-line'></i>&nbsp; Cancel Drawing"
            : "<i class='ri-pencil-line'></i>&nbsp; Draw Polygon"
    );
}

// Button to start/cancel the draw/measurement
$("#drawPolygonBtn").click(function (e) {
    if (drawingRunning) {
        drawingEnd();
    } else {
        drawingStart();
        const featureForm = $("#featurePropertiesForm");
        if (featureForm.length && featureForm[0]) {
            featureForm[0].reset();
        }
    }
});
const cancelFeatureButton = $("#cancelFeatureProperties");
if (cancelFeatureButton.length) {
    cancelFeatureButton.click(function () {
        $("#drawerGeojson").html("");
        if (vectorLayerDrawing) {
            map.removeLayer(vectorLayerDrawing);
            vectorSourceDrawing.clear();
        }
        try {
            document.dispatchEvent(new CustomEvent("app:polygon-reset"));
        } catch (error) {
            console.warn("Unable to dispatch polygon reset event", error);
        }
    });
}

$("#saveFeatureProperties").click(function () {
    const geojson = geojsonFeature;
    const area_hectares = geojsonArea;

    if (geojson) {
        $("#geometryInput").val(JSON.stringify(geojson.geometry));
        $("#areaInput").val(area_hectares);
    }
});

/**
 * Zoom in function
 * @returns {void}
 */
function zoomIn() {
    const view = map.getView();
    const currentZoom = view.getZoom();
    const maxZoom = view.getMaxZoom();

    if (currentZoom < maxZoom) {
        view.animate({
            zoom: currentZoom + 1,
            duration: 250,
        });
    }
}

/**
 * Zoom out function
 * @returns {void}
 */
function zoomOut() {
    const view = map.getView();
    const currentZoom = view.getZoom();
    const minZoom = view.getMinZoom();

    if (currentZoom > minZoom) {
        view.animate({
            zoom: currentZoom - 1,
            duration: 250,
        });
    }
}

/**
 * Toggle minimap visibility
 * @param {HTMLElement} button - The button element that triggered the toggle
 * @returns {void}
 */
function toggleMinimap(button) {
    if (minimapVisible) {
        hideMinimap();
        button.classList.remove("rotate-180");
        if (button) {
        }
    } else {
        showMinimap();
        if (button) {
            button.classList.add("rotate-180");
        }
    }
}

/**
 * Show the minimap
 * @returns {void}
 */
function showMinimap() {
    if (overviewMapControl) {
        overviewMapControl.setCollapsed(false);
        minimapVisible = true;

        // Add a slight delay to ensure the minimap is rendered
        setTimeout(() => {
            const minimapElement = document.querySelector(
                ".ol-custom-overviewmap"
            );
            if (minimapElement) {
                minimapElement.style.display = "block";
                minimapElement.style.opacity = "1";
                minimapElement.style.transform = "scale(1)";
            }
        }, 50);
    }
}

/**
 * Hide the minimap
 * @returns {void}
 */
function hideMinimap() {
    if (overviewMapControl) {
        const minimapElement = document.querySelector(".ol-custom-overviewmap");
        if (minimapElement) {
            minimapElement.style.opacity = "0";
            minimapElement.style.transform = "scale(0.8)";

            // Hide after animation completes
            setTimeout(() => {
                overviewMapControl.setCollapsed(true);
                minimapElement.style.display = "none";
            }, 300);
        } else {
            overviewMapControl.setCollapsed(true);
        }
        minimapVisible = false;
    }
}

document.addEventListener("DOMContentLoaded", function () {
    if (!minimapVisible) {
        toggleMinimap();
    }
});

// Export functions to global scope for access from HTML
window.map = map;
try {
    window.dispatchEvent(
        new CustomEvent("map:ready", {
            detail: { map },
        })
    );
} catch (error) {
    console.warn("Unable to dispatch map:ready event", error);
}
window.zoomIn = zoomIn;
window.zoomOut = zoomOut;
window.toggleMinimap = toggleMinimap;
window.setBasemap = setBasemap;
window.setBingmapBasemap = setBingmapBasemap;
window.setMapboxBasemap = setMapboxBasemap;
window.setOsmBasemap = setOsmBasemap;
window.setEsriBasemap = setEsriBasemap;
window.geojsonArea = geojsonArea;

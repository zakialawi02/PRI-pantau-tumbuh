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
    }),
});
const World_Imagery = new TileLayer({
    source: new XYZ({
        url: "https://services.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        maxZoom: 18,
        crossOrigin: "anonymous",
    }),
});
const World_Transportation = new TileLayer({
    source: new XYZ({
        url: "https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}",
        maxZoom: 18,
        crossOrigin: "anonymous",
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
});
const mapboxBaseMap = new ol.layer.Tile({
    source: mapboxSource,
    crossOrigin: "anonymous",
    visible: false,
    preload: 15,
});
const baseMaps = [osmBaseMap, esriMapGroup, mapboxBaseMap];

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
        const { formattedLon, formattedLat } = coordinateFormatIndo(
            coordinate,
            "dd"
        );

        return (
            "Long: " + formattedLon + " &nbsp&nbsp&nbsp  Lat: " + formattedLat
        );
    },
    projection: "EPSG:4326",
    placeholder: "Long: - &nbsp&nbsp&nbsp  Lat: -",
    className: "ol-custom-mouse-position",
});

/**
 * Formats the given coordinate into a specific format for Indo coordinates.
 *
 * @param {Array<number>} coordinate - The coordinate to be formatted. It should be an array with two elements: [longitude, latitude].
 * @param {string} [format="dd"] - The format to use for the coordinate. It can be "dd" for decimal degrees, or "dms" for degrees, minutes, and seconds.
 * @return {Object} An object containing the formatted longitude and latitude.
 * @example
 * dd=> {"formattedLon": "112.74719° BT", "formattedLat": "7.26786° LS"}
 * or
 * dms=> {"formattedLon": "112° 47' 17.00\" BT", "formattedLat": "7° 24' 46.00\" LS"}
 */
function coordinateFormatIndo(coordinate, format = "dd") {
    const lon = coordinate[0];
    const lat = coordinate[1];

    const lonDirection = lon < 0 ? "BB" : "BT";
    const latDirection = lat < 0 ? "LS" : "LU"; // LS: Lintang Selatan, LU: Lintang Utara

    if (format === "dms") {
        const convertToDMS = (coord, direction) => {
            const absoluteCoord = Math.abs(coord);
            const degrees = Math.floor(absoluteCoord);
            const minutes = Math.floor((absoluteCoord - degrees) * 60);
            const seconds = (
                (absoluteCoord - degrees - minutes / 60) *
                3600
            ).toFixed(2);
            return `${degrees}° ${minutes}' ${seconds}" ${direction}`;
        };
        const formattedLon = convertToDMS(lon, lonDirection);
        const formattedLat = convertToDMS(lat, latDirection);
        return { formattedLon, formattedLat };
    } else {
        const formattedLon = `${Math.abs(lon).toFixed(5)}° ${lonDirection}`;
        const formattedLat = `${Math.abs(lat).toFixed(5)}° ${latDirection}`;
        return { formattedLon, formattedLat };
    }
}

//** STYLE ***/
// marker style
const markerClickedStyle = new Style({
    image: new Icon({
        anchor: [0.5, 0.99],
        anchorXUnits: "fraction",
        anchorYUnits: "fraction",
        with: 50,
        height: 50,
        opacity: 0.9,
        src: "./assets/img/map/marker-click.svg",
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

function setOsmBasemap() {
    osmBaseMap.setVisible(true);
    esriMapGroup.setVisible(false);
    mapboxBaseMap.setVisible(false);
}

function setBingmapBasemap() {
    osmBaseMap.setVisible(false);
    esriMapGroup.setVisible(true);
    mapboxBaseMap.setVisible(false);
}

function setMapboxBasemap() {
    osmBaseMap.setVisible(false);
    esriMapGroup.setVisible(false);
    mapboxBaseMap.setVisible(true);
}

$("#basemap").change(function (e) {
    e.preventDefault();
    switch ($("#basemap").val()) {
        case "osm":
            setOsmBasemap();
            break;
        case "esri":
            setBingmapBasemap();
            break;
        case "mapbox":
            setMapboxBasemap();
            break;
        default:
            setOsmBasemap();
            break;
    }
});

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

// Global variabel
let geojsonFeature;
let geojsonArea;
let drawingRunning;
let drawed;
let minimapVisible = true;

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
        output = Math.round((area / 10000) * 100) / 100 + " ha";
    } else {
        output = Math.round(area * 100) / 100 + " m²";
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
        document.getElementById(
            "drawerGeojson"
        ).innerHTML = `<pre>${JSON.stringify(geojsonFeature, null, 1)}</pre>`;

        // Display measurement result in the #measurementOutput div
        document.getElementById("measurementOutput").innerHTML =
            measureTooltipElement.innerHTML;

        drawingEnd();

        // Show feature properties after drawing
        if (draw) {
            $("#featureProperties").removeClass("d-none");
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
    $("#featureProperties").addClass("d-none");
    $("#drawerGeojson").html("");
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
    unByKey(listener);

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
    $("#drawPolygonBtn").html(
        drawingRunning
            ? "Batal"
            : "<i class='fas fa-pencil-ruler'></i>&nbsp;&nbsp; gambar polygon"
    );
    $("#drawPolygonBtn")
        .removeClass()
        .addClass(
            drawingRunning ? "btn btn-sm btn-danger" : "btn btn-sm btn-primary"
        );
}

// Button to start/cancel the draw/measurement
$("#drawPolygonBtn").click(function (e) {
    if (drawingRunning) {
        drawingEnd();
    } else {
        drawingStart();
        $("#featurePropertiesForm")[0].reset();
    }
});
$("#batalFeatureProperties").click(function (e) {
    $("#featureProperties").addClass("d-none");
    $("#drawerGeojson").html("");
    if (vectorLayerDrawing) {
        map.removeLayer(vectorLayerDrawing);
        vectorSourceDrawing.clear();
    }
});

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
        if (button) {
            button.classList.add("rotate-180");
        }
    } else {
        showMinimap();
        if (button) {
            button.classList.remove("rotate-180");
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

// Export functions to global scope for access from HTML
window.zoomIn = zoomIn;
window.zoomOut = zoomOut;
window.toggleMinimap = toggleMinimap;

document.addEventListener("DOMContentLoaded", function () {
    if (!minimapVisible) {
        toggleMinimap();
    }
});

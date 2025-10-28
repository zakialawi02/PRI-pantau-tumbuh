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
    transformExtent,
} = ol.proj;
let VectorLayer = ol.layer.Vector;
let VectorSource = ol.source.Vector;
let LayerGroup = ol.layer.Group;
let Overlay = ol.Overlay;
let TileWMS = ol.source.TileWMS;
let GeoJSON = ol.format.GeoJSON;
let WMSCapabilities = ol.format.WMSCapabilities;
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
    collapsed: true,
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

const clipSelectionStyle = new Style({
    fill: new Fill({
        color: "rgba(34, 197, 94, 0.25)",
    }),
    stroke: new Stroke({
        color: "rgba(34, 197, 94, 0.9)",
        width: 2,
    }),
});

const clipVectorSource = new VectorSource();
const clipVectorLayer = new VectorLayer({
    source: clipVectorSource,
    style: clipSelectionStyle,
    zIndex: 998,
    visible: false,
});
map.addLayer(clipVectorLayer);

const clipGeojsonParser = new GeoJSON();

function clearClipSelectionLayer() {
    clipVectorSource.clear();
    clipVectorLayer.setVisible(false);
}

function fitClipSelectionExtent() {
    const extent = clipVectorSource.getExtent();
    if (!extent || !extent.every((value) => Number.isFinite(value))) {
        return;
    }
    map.getView().fit(extent, {
        duration: 350,
        padding: [60, 60, 60, 60],
        maxZoom: 18,
    });
}

function showClipFeatureCollection(featureCollection, options = {}) {
    if (!featureCollection || typeof featureCollection !== "object") {
        clearClipSelectionLayer();
        return null;
    }

    const { fitToExtent = true } = options;

    let features = [];
    try {
        features = clipGeojsonParser.readFeatures(featureCollection, {
            dataProjection: "EPSG:4326",
            featureProjection: map.getView().getProjection(),
        });
    } catch (error) {
        console.error("Unable to parse clip feature collection", error);
        clearClipSelectionLayer();
        return null;
    }

    clipVectorSource.clear();

    if (!features.length) {
        clearClipSelectionLayer();
        return null;
    }

    clipVectorSource.addFeatures(features);
    clipVectorLayer.setVisible(true);

    if (fitToExtent) {
        fitClipSelectionExtent();
    }

    const totalArea = features.reduce((accumulator, feature) => {
        const geometry = feature.getGeometry();
        if (!geometry) {
            return accumulator;
        }
        const area = getArea(geometry);
        return Number.isFinite(area) ? accumulator + area : accumulator;
    }, 0);

    return {
        features,
        areaSquareMeters: totalArea,
    };
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

const geoserverLayerRegistry = new Map();
const wmsCapabilitiesCache = new Map();

function normaliseLayerOptions(options = {}) {
    if (!options || typeof options !== "object") {
        return {
            id: "",
            layer: "",
            url: "",
            style: "",
        };
    }

    const layerName =
        typeof options.layer === "string" ? options.layer.trim() : "";
    const identifier =
        typeof options.id === "string" && options.id.trim() !== ""
            ? options.id.trim()
            : layerName;

    const bounds = normaliseBoundsOption(options.bounds);

    const normalised = {
        id: identifier,
        layer: layerName,
        url: typeof options.url === "string" ? options.url.trim() : "",
        style: typeof options.style === "string" ? options.style : "",
        parameters:
            options.parameters && typeof options.parameters === "object"
                ? { ...options.parameters }
                : undefined,
        opacity:
            typeof options.opacity === "number" ? options.opacity : undefined,
        zIndex: typeof options.zIndex === "number" ? options.zIndex : undefined,
        crossOrigin:
            typeof options.crossOrigin === "string" && options.crossOrigin
                ? options.crossOrigin
                : "anonymous",
        title: typeof options.title === "string" ? options.title.trim() : "",
        padding: Array.isArray(options.padding) ? [...options.padding] : null,
        maxZoom:
            typeof options.maxZoom === "number" ? options.maxZoom : undefined,
        duration:
            typeof options.duration === "number" ? options.duration : undefined,
    };

    if (bounds) {
        normalised.bounds = bounds;
    }

    return normalised;
}

function createGeoserverLayer(options = {}) {
    const normalised = normaliseLayerOptions(options);
    const { url, layer, id } = normalised;

    if (!url || !layer) {
        return null;
    }

    const params = {
        LAYERS: layer,
        TILED: true,
        STYLES: normalised.style || "",
        FORMAT: "image/png",
        TRANSPARENT: true,
    };

    if (normalised.parameters) {
        Object.assign(params, normalised.parameters);
    }

    const source = new TileWMS({
        url,
        params,
        transition: 200,
        crossOrigin: normalised.crossOrigin,
    });

    const tileLayer = new TileLayer({
        source,
        visible: true,
        opacity:
            typeof normalised.opacity === "number" ? normalised.opacity : 0.7,
    });

    const layerId = id || layer;
    tileLayer.set("id", `geoserver:${layerId}`);
    tileLayer.setZIndex(
        typeof normalised.zIndex === "number" ? normalised.zIndex : 400
    );

    map.addLayer(tileLayer);

    const entry = {
        id: layerId,
        layer: tileLayer,
        source,
        options: {},
        visible: true,
        bounds: null,
        boundsPromise: null,
    };

    mergeEntryOptions(entry, normalised);

    return entry;
}

function normaliseExtent(values) {
    if (!Array.isArray(values) || values.length !== 4) {
        return null;
    }

    const numeric = values.map((value) => Number(value));
    return numeric.every((value) => Number.isFinite(value)) ? numeric : null;
}

function normaliseSingleBounds(bounds, fallbackProjection) {
    if (!bounds || typeof bounds !== "object") {
        return null;
    }

    let extent = null;

    if (Array.isArray(bounds.extent)) {
        extent = normaliseExtent(bounds.extent);
    } else {
        const components = [
            bounds.minx ?? bounds.minX,
            bounds.miny ?? bounds.minY,
            bounds.maxx ?? bounds.maxX,
            bounds.maxy ?? bounds.maxY,
        ];

        if (components.some((value) => value !== undefined)) {
            extent = normaliseExtent(components);
        }
    }

    if (!extent) {
        return null;
    }

    let projection = null;

    if (typeof bounds.projection === "string" && bounds.projection.trim()) {
        projection = bounds.projection.trim();
    } else if (typeof bounds.crs === "string" && bounds.crs.trim()) {
        projection = bounds.crs.trim();
    } else if (
        typeof fallbackProjection === "string" &&
        fallbackProjection.trim()
    ) {
        projection = fallbackProjection.trim();
    }

    const result = {
        extent,
    };

    if (projection) {
        result.projection = projection;
    }

    return result;
}

function normaliseBoundsOption(bounds) {
    if (!bounds) {
        return null;
    }

    if (Array.isArray(bounds)) {
        const extent = normaliseExtent(bounds);
        return extent ? { native: { extent } } : null;
    }

    if (typeof bounds !== "object") {
        return null;
    }

    const result = {};

    if (bounds.native || bounds.wgs84) {
        const native = normaliseSingleBounds(bounds.native);
        if (native) {
            result.native = native;
        }

        const wgs84 = normaliseSingleBounds(bounds.wgs84, "EPSG:4326");
        if (wgs84) {
            result.wgs84 = wgs84;
        }
    } else {
        const single = normaliseSingleBounds(bounds);
        if (single) {
            result.native = single;
        }
    }

    return Object.keys(result).length ? result : null;
}

function cloneBounds(bounds) {
    if (!bounds || typeof bounds !== "object") {
        return null;
    }

    const extent = normaliseExtent(bounds.extent);
    if (!extent) {
        return null;
    }

    const cloned = {
        extent,
    };

    if (typeof bounds.projection === "string" && bounds.projection.trim()) {
        cloned.projection = bounds.projection.trim();
    }

    return cloned;
}

function pickPreferredBounds(bounds) {
    if (!bounds) {
        return null;
    }

    if (bounds.extent) {
        return cloneBounds(bounds);
    }

    const native = bounds.native ? cloneBounds(bounds.native) : null;
    const wgs84 = bounds.wgs84 ? cloneBounds(bounds.wgs84) : null;

    if (native && native.projection) {
        return native;
    }

    if (wgs84) {
        return wgs84;
    }

    return native || null;
}

function mergeEntryOptions(entry, normalised) {
    if (!entry || !normalised) {
        return;
    }

    const existingOptions = entry.options || {};
    const merged = { ...existingOptions, ...normalised };

    if (normalised.bounds) {
        merged.bounds = normalised.bounds;
    } else if (existingOptions.bounds && !merged.bounds) {
        merged.bounds = existingOptions.bounds;
    }

    entry.options = merged;

    const preferred =
        pickPreferredBounds(normalised.bounds) ||
        pickPreferredBounds(merged.bounds) ||
        null;

    if (preferred) {
        entry.bounds = preferred;
    }
}

function findLayerByName(layers, targetName) {
    if (!Array.isArray(layers) || !targetName) {
        return null;
    }

    for (const layer of layers) {
        if (!layer) {
            continue;
        }

        if (layer.Name === targetName || layer.name === targetName) {
            return layer;
        }

        const childLayers = layer.Layer || layer.layers;
        const found = findLayerByName(childLayers, targetName);
        if (found) {
            return found;
        }
    }

    return null;
}

function parseLayerBounds(layer) {
    if (!layer) {
        return null;
    }

    if (
        Array.isArray(layer.EX_GeographicBoundingBox) &&
        layer.EX_GeographicBoundingBox.length === 4
    ) {
        const extent = normaliseExtent(layer.EX_GeographicBoundingBox);
        if (extent) {
            return { extent, projection: "EPSG:4326" };
        }
    }

    if (layer.LatLonBoundingBox) {
        const bbox = layer.LatLonBoundingBox;
        const extent = normaliseExtent([
            bbox.minx,
            bbox.miny,
            bbox.maxx,
            bbox.maxy,
        ]);
        if (extent) {
            return { extent, projection: "EPSG:4326" };
        }
    }

    if (Array.isArray(layer.BoundingBox)) {
        for (const bbox of layer.BoundingBox) {
            if (!bbox) {
                continue;
            }

            const extent =
                normaliseExtent(bbox.extent) ||
                normaliseExtent([bbox.minx, bbox.miny, bbox.maxx, bbox.maxy]);

            if (extent) {
                const projection = bbox.crs || bbox.CRS || "EPSG:4326";
                return { extent, projection };
            }
        }
    }

    return null;
}

async function loadWmsCapabilities(url) {
    if (!url) {
        return null;
    }

    if (wmsCapabilitiesCache.has(url)) {
        return wmsCapabilitiesCache.get(url);
    }

    const fetchPromise = (async () => {
        const separator = url.includes("?") ? "&" : "?";
        const requestUrl = `${url}${separator}service=WMS&version=1.3.0&request=GetCapabilities`;
        const response = await fetch(requestUrl);

        if (!response.ok) {
            throw new Error(
                `GeoServer capabilities request failed with status ${response.status}`
            );
        }

        const text = await response.text();
        const parser = new WMSCapabilities();
        return parser.read(text);
    })()
        .then((capabilities) => {
            wmsCapabilitiesCache.set(url, Promise.resolve(capabilities));
            return capabilities;
        })
        .catch((error) => {
            console.warn("Failed to load WMS capabilities", error);
            wmsCapabilitiesCache.delete(url);
            throw error;
        });

    wmsCapabilitiesCache.set(url, fetchPromise);
    return fetchPromise;
}

async function ensureLayerBounds(entry, options = {}) {
    if (!entry) {
        return null;
    }

    if (entry.bounds && entry.bounds.extent) {
        return entry.bounds;
    }

    const providedBounds =
        pickPreferredBounds(options.bounds) ||
        pickPreferredBounds(entry.options?.bounds);

    if (providedBounds && providedBounds.extent) {
        entry.bounds = providedBounds;
        return entry.bounds;
    }

    if (entry.boundsPromise) {
        return entry.boundsPromise;
    }

    const layerName = options.layer || entry.options?.layer;
    const wmsUrl = options.url || entry.options?.url;

    if (!layerName || !wmsUrl) {
        return null;
    }

    entry.boundsPromise = (async () => {
        try {
            const capabilities = await loadWmsCapabilities(wmsUrl);
            if (!capabilities) {
                return null;
            }

            const topLayer = capabilities.Capability?.Layer;
            if (!topLayer) {
                return null;
            }

            const layers = Array.isArray(topLayer.Layer)
                ? topLayer.Layer
                : [topLayer];
            const targetLayer = findLayerByName(layers, layerName);

            if (!targetLayer) {
                return null;
            }

            const parsed = parseLayerBounds(targetLayer);
            if (parsed) {
                entry.bounds = parsed;
            }

            return parsed || null;
        } catch (error) {
            console.warn(
                `Failed to resolve WMS bounds for layer ${layerName}`,
                error
            );
            return null;
        } finally {
            entry.boundsPromise = null;
        }
    })();

    return entry.boundsPromise;
}

function toggleGeoserverLayer(options = {}) {
    const normalised = normaliseLayerOptions(options);
    const layerId = normalised.id || normalised.layer;

    if (!layerId) {
        return { visible: false };
    }

    const registryKey = `geoserver:${layerId}`;
    let entry = geoserverLayerRegistry.get(registryKey);

    if (!entry) {
        entry = createGeoserverLayer(normalised);
        if (!entry) {
            return { visible: false };
        }
        geoserverLayerRegistry.set(registryKey, entry);
        return {
            visible: true,
            layer: entry.layer,
            source: entry.source,
            options: entry.options,
        };
    }

    mergeEntryOptions(entry, normalised);

    if (entry.layer.getVisible()) {
        entry.layer.setVisible(false);
        entry.visible = false;
        return {
            visible: false,
            layer: entry.layer,
            source: entry.source,
            options: entry.options,
        };
    }

    const targetLayerName = entry.options?.layer;

    if (
        targetLayerName &&
        entry.source.getParams().LAYERS !== targetLayerName
    ) {
        entry.source.updateParams({
            LAYERS: targetLayerName,
            STYLES: entry.options?.style || "",
        });
    }

    if (typeof entry.options?.opacity === "number") {
        entry.layer.setOpacity(entry.options.opacity);
    }

    entry.layer.setVisible(true);
    entry.layer.changed();
    entry.visible = true;

    return {
        visible: true,
        layer: entry.layer,
        source: entry.source,
        options: entry.options,
    };
}

function showGeoserverLayer(options = {}) {
    const normalised = normaliseLayerOptions(options);
    const layerId = normalised.id || normalised.layer;

    if (!layerId) {
        return null;
    }

    const registryKey = `geoserver:${layerId}`;
    let entry = geoserverLayerRegistry.get(registryKey);
    if (!entry) {
        entry = createGeoserverLayer(normalised);
        if (!entry) {
            return null;
        }
        geoserverLayerRegistry.set(registryKey, entry);
    } else {
        entry.layer.setVisible(true);
        entry.visible = true;
        mergeEntryOptions(entry, normalised);

        if (typeof entry.options?.opacity === "number") {
            entry.layer.setOpacity(entry.options.opacity);
        }

        if (entry.options?.layer) {
            entry.source.updateParams({
                LAYERS: entry.options.layer,
                STYLES: entry.options?.style || "",
            });
        }

        entry.layer.changed();
    }

    return entry.layer;
}

function hideGeoserverLayer(id) {
    if (!id) {
        return false;
    }

    const registryKey = `geoserver:${id}`;
    const entry = geoserverLayerRegistry.get(registryKey);
    if (!entry) {
        return false;
    }

    entry.layer.setVisible(false);
    entry.visible = false;
    return true;
}

function removeGeoserverLayer(id) {
    if (!id) {
        return false;
    }

    const registryKey = `geoserver:${id}`;
    const entry = geoserverLayerRegistry.get(registryKey);
    if (!entry) {
        return false;
    }

    map.removeLayer(entry.layer);
    geoserverLayerRegistry.delete(registryKey);
    return true;
}

async function zoomToGeoserverLayer(options = {}) {
    const normalised = normaliseLayerOptions(options);
    const layerId = normalised.id || normalised.layer;

    if (!layerId) {
        return false;
    }

    const registryKey = `geoserver:${layerId}`;
    let entry = geoserverLayerRegistry.get(registryKey);

    if (!entry) {
        entry = createGeoserverLayer(normalised);
        if (!entry) {
            return false;
        }
        geoserverLayerRegistry.set(registryKey, entry);
    } else {
        mergeEntryOptions(entry, normalised);
    }

    const boundsInfo = await ensureLayerBounds(entry, normalised);
    if (!boundsInfo || !Array.isArray(boundsInfo.extent)) {
        return false;
    }

    const view = map.getView();
    if (!view) {
        return false;
    }

    let extentToFit = boundsInfo.extent;
    const sourceProjection = boundsInfo.projection || "EPSG:4326";
    const targetProjection = view.getProjection();

    if (
        targetProjection &&
        targetProjection.getCode &&
        sourceProjection &&
        targetProjection.getCode() !== sourceProjection
    ) {
        try {
            extentToFit = transformExtent(
                boundsInfo.extent,
                sourceProjection,
                targetProjection.getCode()
            );
        } catch (error) {
            console.warn("Failed to transform extent for zooming", error);
        }
    }

    if (!extentToFit || !Array.isArray(extentToFit)) {
        return false;
    }

    const size = map.getSize();
    if (!size) {
        return false;
    }

    view.fit(extentToFit, {
        size,
        duration: normalised.duration || 600,
        padding: normalised.padding || [80, 80, 80, 80],
        maxZoom:
            typeof normalised.maxZoom === "number" ? normalised.maxZoom : 16,
    });

    return true;
}

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
let minimapVisible = false;
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

function clearDrawnClipPolygon() {
    if (typeof drawingRunning !== "undefined" && drawingRunning) {
        try {
            drawingEnd();
        } catch (error) {
            console.warn("Unable to terminate drawing interaction", error);
        }
    }

    if (vectorSourceDrawing) {
        vectorSourceDrawing.clear();
    }

    if (measureTooltip) {
        map.removeOverlay(measureTooltip);
        measureTooltip = null;
    }

    if (measureTooltipElement) {
        measureTooltipElement.remove();
        measureTooltipElement = null;
    }

    if (helpTooltipElement) {
        helpTooltipElement.remove();
        helpTooltipElement = null;
    }

    sketch = null;
    geojsonFeature = null;
    geojsonArea = null;
    drawed = null;
}

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
        window.geojsonFeature = geojsonFeature;

        // Display the GeoJSON string in the #drawerGeojson element
        const drawerGeojsonEl = document.getElementById("drawerGeojson");
        if (drawerGeojsonEl) {
            drawerGeojsonEl.innerHTML = `<pre>${JSON.stringify(
                geojsonFeature,
                null,
                1
            )}</pre>`;
        }

        const sentinelClipModule =
            document.getElementById("sentinelClipModule");
        if (sentinelClipModule) {
            const locale = document.documentElement.lang;
            const areaHa = geojsonArea / 10000;

            const clipAreaOutput = document.getElementById("clipAreaOutput");
            if (clipAreaOutput) {
                clipAreaOutput.textContent = `${formatNumber(
                    areaHa,
                    2,
                    locale
                )} ha`;
            }

            const clipCreditOutput =
                document.getElementById("clipCreditOutput");
            if (clipCreditOutput) {
                const creditRate = parseFloat(
                    sentinelClipModule.dataset.creditRate || "0"
                );
                if (creditRate > 0) {
                    const estimatedCost = areaHa * creditRate;
                    clipCreditOutput.textContent = formatNumber(
                        estimatedCost,
                        2,
                        locale
                    );
                } else {
                    clipCreditOutput.textContent = "–";
                }
            }

            const clipGeojsonOutput =
                document.getElementById("clipGeojsonOutput");
            if (clipGeojsonOutput) {
                clipGeojsonOutput.innerHTML = `<pre class="whitespace-pre-wrap">${JSON.stringify(
                    geojsonFeature,
                    null,
                    2
                )}</pre>`;
            }
        }

        // Display measurement result in the #measurementOutput div
        const measurementOutputEl =
            document.getElementById("measurementOutput");
        if (measurementOutputEl) {
            measurementOutputEl.innerHTML =
                formatNumber(geojsonArea / 10000) + " ha"; // Convert m² to hectares;
        }

        drawingEnd();

        // Show feature properties after drawing
        if (draw) {
            $("#featureProperties").removeClass("hidden");
        }

        // Calculate total price after drawing
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
    clearDrawnClipPolygon();
    clearClipSelectionLayer();
    addInteraction();
    drawingRunning = true;
    drawed = null;
    buttonStateDrawing();
    $("#featureProperties").addClass("hidden");
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
    $("#drawPolygonBtn").html(
        drawingRunning
            ? "Cancel Drawing"
            : "<i class='ri-pencil-line'></i>&nbsp; Draw Polygon"
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
    }
});

const clipDrawPolygonBtn = document.getElementById("clipDrawPolygonBtn");
if (clipDrawPolygonBtn) {
    clipDrawPolygonBtn.addEventListener("click", function () {
        if (drawingRunning) {
            drawingEnd();
        } else {
            drawingStart();
        }
    });
}

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
    if (overviewMapControl) {
        overviewMapControl.setCollapsed(true);
        minimapVisible = false;
    }
});

// Export functions to global scope for access from HTML
window.map = map;
window.AppMap = window.AppMap || {};
window.AppMap.geoserver = Object.assign({}, window.AppMap.geoserver, {
    toggleLayer: toggleGeoserverLayer,
    showLayer: showGeoserverLayer,
    hideLayer: hideGeoserverLayer,
    removeLayer: removeGeoserverLayer,
    zoomToLayer: zoomToGeoserverLayer,
    listLayers: () => Array.from(geoserverLayerRegistry.keys()),
});
window.AppMap.clip = Object.assign({}, window.AppMap.clip, {
    showFeatureCollection: (featureCollection, options) =>
        showClipFeatureCollection(featureCollection, options),
    clear: () => {
        clearClipSelectionLayer();
        return true;
    },
    fitToExtent: () => {
        fitClipSelectionExtent();
        return true;
    },
    clearDrawnPolygon: () => {
        clearDrawnClipPolygon();
        return true;
    },
});
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

/**
 * Sentinel-2 catalogue module
 *
 * Handles catalogue fetching, rendering collection cards, and map previews.
 */
(() => {
    const initialiseSentinelModule = () => {
        const moduleEl = document.getElementById("sentinel-panel");
        if (!moduleEl) {
            return;
        }

        // Prevent re-initialisation when Vite hot reloads.
        if (window.AppMap?.sentinel) {
            return;
        }

        window.AppMap = window.AppMap || {};

        const config = {
            endpoint: "https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json",
            defaultMaxRecords: 10,
            userMaxRecords: 20,
            defaultMonthsBack: 1,
        };

        const elements = {
            form: moduleEl.querySelector("#sentinelFilterForm"),
            resetButton: moduleEl.querySelector("#sentinelFilterResetButton"),
            cloudInput: moduleEl.querySelector("#sentinelCloudFilter"),
            productSelect: moduleEl.querySelector("#sentinelProductLevel"),
            startInput: moduleEl.querySelector("#sentinelStartDate"),
            endInput: moduleEl.querySelector("#sentinelEndDate"),
            latInput: moduleEl.querySelector("#sentinelLatFilter"),
            lonInput: moduleEl.querySelector("#sentinelLonFilter"),
            status: moduleEl.querySelector("#sentinelCollectionStatus"),
            list: moduleEl.querySelector("#sentinelCollectionList"),
            template: document.getElementById("sentinelCollectionTemplate"),
            lastUpdated: document.getElementById("sentinelLastUpdated"),
            previewPanel: document.getElementById("sentinelPreviewPanel"),
            previewTitle: document.querySelector("[data-sentinel-preview-title]"),
            previewAcquired: document.querySelector("[data-sentinel-preview-acquired]"),
            previewDetails: document.querySelector("[data-sentinel-preview-details]"),
            previewStatus: document.querySelector("[data-sentinel-preview-status]"),
            previewImageryBtn: document.getElementById("sentinelPreviewImageryBtn"),
            previewImageryIcon: document.querySelector("[data-sentinel-preview-imagery-icon]"),
            previewImageryLabel: document.querySelector("[data-sentinel-preview-imagery-label]"),
            previewDownloadBtn: document.getElementById("sentinelPreviewDownloadBtn"),
            previewClearBtn: document.getElementById("sentinelPreviewClearBtn"),
        };

        const defaultPreviewStatus =
            elements.previewStatus?.textContent || "Awaiting preview selection.";

        const state = {
            token: (moduleEl.dataset.sentinelToken || "").trim(),
            processUrl: (moduleEl.dataset.sentinelProcessUrl || "").trim(),
            map: window.map || null,
            loading: false,
            scenes: [],
            lastFilters: null,
            preview: {
                scene: null,
                cardId: null,
                vectorLayer: null,
                wmsLayer: null,
                wmsActive: false,
            },
        };

        if (!state.map) {
            document.addEventListener(
                "map:ready",
                (event) => {
                    state.map = event.detail?.map || window.map || state.map;
                },
                { once: true }
            );
        }

        /**
         * Utility helpers
         */
        const toNumber = (value, fallback = null) => {
            const numeric = Number(value);
            return Number.isFinite(numeric) ? numeric : fallback;
        };

        const formatDateInput = (date) => {
            if (typeof window.formatISODate === "function") {
                return window.formatISODate(date);
            }
            if (!(date instanceof Date)) {
                return "";
            }
            const month = `${date.getMonth() + 1}`.padStart(2, "0");
            const day = `${date.getDate()}`.padStart(2, "0");
            return `${date.getFullYear()}-${month}-${day}`;
        };

        const formatDateTime = (value) => {
            if (!value) {
                return null;
            }
            if (typeof window.formatReadableDate === "function") {
                return window.formatReadableDate(value);
            }
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed.toLocaleString();
        };

        const formatCloud = (value) =>
            Number.isFinite(value) ? `${Number(value).toFixed(1)}%` : null;

        const getDefaultDateRange = () => {
            if (typeof window.getDefaultDateRange === "function") {
                return window.getDefaultDateRange(config.defaultMonthsBack);
            }
            const end = new Date();
            const start = new Date(end.getTime());
            start.setMonth(start.getMonth() - config.defaultMonthsBack);
            start.setHours(0, 0, 0, 0);
            end.setHours(23, 59, 59, 999);
            return { start, end };
        };

        const applyDefaultDateInputs = (force = false) => {
            const { start, end } = getDefaultDateRange();
            if (elements.startInput && (force || !elements.startInput.value)) {
                elements.startInput.value = formatDateInput(start);
            }
            if (elements.endInput && (force || !elements.endInput.value)) {
                elements.endInput.value = formatDateInput(end);
            }
        };

        const buildFiltersFromForm = () => {
            applyDefaultDateInputs();
            const startDate = (elements.startInput?.value || "").trim();
            const endDate = (elements.endInput?.value || "").trim();

            return {
                startDate: startDate || null,
                endDate: endDate || null,
                cloudCover: toNumber(elements.cloudInput?.value, null),
                productLevel:
                    (elements.productSelect?.value || "S2MSI2A").trim() || "S2MSI2A",
                latitude: toNumber(elements.latInput?.value, null),
                longitude: toNumber(elements.lonInput?.value, null),
            };
        };

        const buildQueryParams = (filters, limit) => {
            const params = new URLSearchParams();
            params.set("maxRecords", String(limit));
            params.set("sortParam", "startDate");
            params.set("sortOrder", "descending");
            params.set("dataset", "ESA-DATASET");
            params.set("productType", filters.productLevel);

            if (filters.startDate) {
                params.set("startDate", `${filters.startDate}T00:00:00Z`);
            }
            if (filters.endDate) {
                params.set("completionDate", `${filters.endDate}T23:59:59Z`);
            }
            if (Number.isFinite(filters.cloudCover)) {
                const bounded = Math.max(0, Math.min(100, filters.cloudCover));
                params.set("cloudCover", `[0,${bounded}]`);
            }
            if (Number.isFinite(filters.latitude) && Number.isFinite(filters.longitude)) {
                params.set("lat", filters.latitude.toFixed(6));
                params.set("lon", filters.longitude.toFixed(6));
            }

            return params;
        };

        const sanitizeFilename = (value) =>
            (value || "")
                .replace(/[^a-z0-9_\-]+/gi, "_")
                .replace(/_+/g, "_")
                .replace(/^_+|_+$/g, "")
                .slice(0, 80) || "sentinel-scene";

        const createDownloadFilename = (scene) =>
            `${sanitizeFilename(scene.title || scene.productId)}.zip`;

        const ensureMap = () => {
            if (state.map) {
                return state.map;
            }
            if (window.map) {
                state.map = window.map;
            }
            return state.map;
        };

        const ensureVectorLayer = () => {
            const mapInstance = ensureMap();
            const ol = window.ol;
            if (!mapInstance || !ol?.layer?.Vector || !ol?.source?.Vector || !ol?.style) {
                return null;
            }
            if (!state.preview.vectorLayer) {
                state.preview.vectorLayer = new ol.layer.Vector({
                    source: new ol.source.Vector(),
                    style: new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: "rgba(37, 99, 235, 0.85)",
                            width: 2,
                        }),
                        fill: new ol.style.Fill({
                            color: "rgba(37, 99, 235, 0.15)",
                        }),
                    }),
                });
                mapInstance.addLayer(state.preview.vectorLayer);
            }
            return state.preview.vectorLayer;
        };

        const clearVectorLayer = () => {
            state.preview.vectorLayer?.getSource()?.clear();
        };

        const removeWmsLayer = () => {
            if (state.preview.wmsLayer && state.map?.removeLayer) {
                state.map.removeLayer(state.preview.wmsLayer);
            }
            state.preview.wmsLayer = null;
            state.preview.wmsActive = false;
        };

        const buildFeatureFromScene = (scene) => {
            const mapInstance = ensureMap();
            const ol = window.ol;
            if (!mapInstance || !ol?.format) {
                return null;
            }
            const projection = mapInstance.getView?.().getProjection?.() || "EPSG:3857";

            if (scene.geometry) {
                try {
                    return new ol.format.GeoJSON().readFeature(scene.geometry, {
                        dataProjection: "EPSG:4326",
                        featureProjection: projection,
                    });
                } catch (error) {
                    console.warn("Unable to parse Sentinel GeoJSON geometry", error);
                }
            }
            if (scene.footprint) {
                try {
                    return new ol.format.WKT().readFeature(scene.footprint, {
                        dataProjection: "EPSG:4326",
                        featureProjection: projection,
                    });
                } catch (error) {
                    console.warn("Unable to parse Sentinel footprint WKT", error);
                }
            }
            if (Array.isArray(scene.bbox) && scene.bbox.length === 4) {
                const [minLon, minLat, maxLon, maxLat] = scene.bbox.map(Number);
                if ([minLon, minLat, maxLon, maxLat].every(Number.isFinite)) {
                    const polygon = {
                        type: "Feature",
                        geometry: {
                            type: "Polygon",
                            coordinates: [
                                [
                                    [minLon, minLat],
                                    [minLon, maxLat],
                                    [maxLon, maxLat],
                                    [maxLon, minLat],
                                    [minLon, minLat],
                                ],
                            ],
                        },
                    };
                    try {
                        return new ol.format.GeoJSON().readFeature(polygon, {
                            dataProjection: "EPSG:4326",
                            featureProjection: projection,
                        });
                    } catch (error) {
                        console.warn("Unable to build Sentinel feature from bbox", error);
                    }
                }
            }
            return null;
        };

        const setStatus = (message, tone = "info") => {
            if (!elements.status) {
                return;
            }
            elements.status.textContent = message;
            elements.status.classList.remove(
                "text-red-500",
                "text-amber-500",
                "text-foreground/70"
            );
            if (tone === "error") {
                elements.status.classList.add("text-red-500");
            } else if (tone === "warning") {
                elements.status.classList.add("text-amber-500");
            } else {
                elements.status.classList.add("text-foreground/70");
            }
        };

        const updateLastUpdated = () => {
            if (!elements.lastUpdated) {
                return;
            }
            const now = new Date();
            if (typeof window.formatReadableDate === "function") {
                elements.lastUpdated.textContent = window.formatReadableDate(
                    now.toISOString()
                );
            } else {
                elements.lastUpdated.textContent = now.toLocaleString();
            }
        };

        const clearPreview = (options = {}) => {
            const { hidePanel = true } = options;
            removeWmsLayer();
            clearVectorLayer();
            state.preview.scene = null;
            state.preview.cardId = null;
            if (hidePanel && elements.previewPanel) {
                elements.previewPanel.classList.add("hidden");
            }
            if (elements.previewStatus) {
                elements.previewStatus.textContent = defaultPreviewStatus;
            }
            if (elements.previewImageryBtn) {
                elements.previewImageryBtn.setAttribute("aria-pressed", "false");
            }
        };

        const updatePreviewPanel = (scene) => {
            if (!elements.previewPanel) {
                return;
            }
            elements.previewPanel.classList.remove("hidden");

            if (elements.previewTitle) {
                elements.previewTitle.textContent = scene.title || "Sentinel-2 Scene";
            }

            if (elements.previewAcquired) {
                const formatted = formatDateTime(scene.datetime);
                elements.previewAcquired.textContent = formatted
                    ? `Acquired: ${formatted}`
                    : "Acquisition time unavailable.";
            }

            if (elements.previewDetails) {
                const items = [];
                if (scene.tileId) {
                    items.push(`Tile: ${scene.tileId}`);
                }
                const cloud = formatCloud(scene.cloudCover);
                if (cloud) {
                    items.push(`Cloud: ${cloud}`);
                }
                if (scene.collection) {
                    items.push(scene.collection);
                }
                if (items.length) {
                    elements.previewDetails.textContent = items.join(" • ");
                    elements.previewDetails.classList.remove("hidden");
                } else {
                    elements.previewDetails.textContent = "";
                    elements.previewDetails.classList.add("hidden");
                }
            }

            if (elements.previewDownloadBtn) {
                if (scene.downloadUrl) {
                    elements.previewDownloadBtn.href = scene.downloadUrl;
                    elements.previewDownloadBtn.download = createDownloadFilename(scene);
                    elements.previewDownloadBtn.setAttribute("aria-disabled", "false");
                    elements.previewDownloadBtn.classList.remove("hidden");
                } else {
                    elements.previewDownloadBtn.href = "#";
                    elements.previewDownloadBtn.setAttribute("aria-disabled", "true");
                    elements.previewDownloadBtn.classList.add("hidden");
                }
            }

            if (elements.previewImageryBtn) {
                if (scene.wmsUrl) {
                    elements.previewImageryBtn.removeAttribute("disabled");
                    elements.previewImageryBtn.setAttribute("aria-disabled", "false");
                    elements.previewImageryBtn.classList.remove("hidden");
                    elements.previewImageryBtn.setAttribute("aria-pressed", "false");
                    elements.previewImageryIcon?.classList.remove("ri-eye-off-line");
                    elements.previewImageryIcon?.classList.add("ri-eye-line");
                    if (elements.previewImageryLabel) {
                        elements.previewImageryLabel.textContent = "Preview Imagery";
                    }
                } else {
                    elements.previewImageryBtn.setAttribute("aria-disabled", "true");
                    elements.previewImageryBtn.classList.add("hidden");
                }
            }

            if (elements.previewStatus) {
                elements.previewStatus.textContent =
                    "Footprint highlighted on the map.";
            }
        };

        const previewSceneOnMap = (scene) => {
            const vectorLayer = ensureVectorLayer();
            const feature = buildFeatureFromScene(scene);
            if (!vectorLayer || !feature) {
                setStatus(
                    "Unable to preview this scene on the map. The footprint is unavailable.",
                    "warning"
                );
                return;
            }

            vectorLayer.getSource().clear();
            vectorLayer.getSource().addFeature(feature);

            const mapInstance = ensureMap();
            const geometry = feature.getGeometry?.();
            if (mapInstance && geometry) {
                try {
                    mapInstance.getView().fit(geometry.getExtent(), {
                        padding: [40, 40, 40, 40],
                        duration: 450,
                        maxZoom: 14,
                    });
                } catch (error) {
                    console.warn("Unable to fit map view to Sentinel geometry", error);
                }
            }

            removeWmsLayer();
            state.preview.scene = scene;
            state.preview.cardId = scene.id;
            updatePreviewPanel(scene);
        };

        const toggleImageryLayer = () => {
            const scene = state.preview.scene;
            const mapInstance = ensureMap();
            const ol = window.ol;
            if (!scene || !scene.wmsUrl) {
                setStatus("WMS preview is not available for this scene.", "warning");
                return;
            }
            if (!mapInstance || !ol?.layer?.Tile || !ol?.source?.TileWMS) {
                setStatus("Map preview is not ready yet.", "warning");
                return;
            }

            if (state.preview.wmsActive) {
                removeWmsLayer();
                elements.previewImageryBtn?.setAttribute("aria-pressed", "false");
                elements.previewImageryIcon?.classList.remove("ri-eye-off-line");
                elements.previewImageryIcon?.classList.add("ri-eye-line");
                if (elements.previewImageryLabel) {
                    elements.previewImageryLabel.textContent = "Preview Imagery";
                }
                if (elements.previewStatus) {
                    elements.previewStatus.textContent =
                        "Footprint highlighted on the map.";
                }
                return;
            }

            state.preview.wmsLayer = new ol.layer.Tile({
                source: new ol.source.TileWMS({
                    url: scene.wmsUrl,
                    params: {
                        LAYERS: scene.wmsLayer || "TRUE_COLOR",
                        FORMAT: "image/png",
                        TRANSPARENT: true,
                        ...scene.wmsParams,
                    },
                    crossOrigin: "anonymous",
                }),
                opacity: 0.8,
            });
            mapInstance.addLayer(state.preview.wmsLayer);
            state.preview.wmsActive = true;

            elements.previewImageryBtn?.setAttribute("aria-pressed", "true");
            elements.previewImageryIcon?.classList.remove("ri-eye-line");
            elements.previewImageryIcon?.classList.add("ri-eye-off-line");
            if (elements.previewImageryLabel) {
                elements.previewImageryLabel.textContent = "Hide Imagery";
            }
            if (elements.previewStatus) {
                elements.previewStatus.textContent =
                    "Displaying Sentinel-2 preview imagery.";
            }
        };

        const populateThumbnail = (card, scene) => {
            const imageEl = card.querySelector("[data-sentinel-thumbnail]");
            const placeholder = card.querySelector("[data-sentinel-placeholder]");
            if (!imageEl) {
                return;
            }
            if (scene.quicklookUrl) {
                imageEl.src = scene.quicklookUrl;
                imageEl.classList.remove("hidden");
                placeholder?.classList.add("hidden");
                imageEl.addEventListener(
                    "error",
                    () => {
                        imageEl.classList.add("hidden");
                        placeholder?.classList.remove("hidden");
                    },
                    { once: true }
                );
            } else {
                imageEl.classList.add("hidden");
                placeholder?.classList.remove("hidden");
            }
        };

        const renderCard = (scene) => {
            if (!elements.template?.content) {
                return null;
            }
            const fragment = elements.template.content.cloneNode(true);
            const card = fragment.querySelector(".sentinel-card");
            if (!card) {
                return null;
            }
            card.dataset.sceneId = scene.id;

            populateThumbnail(card, scene);

            const titleEl = card.querySelector("[data-sentinel-title]");
            if (titleEl) {
                titleEl.textContent = scene.title || "Sentinel-2 Scene";
            }

            const productEl = card.querySelector("[data-sentinel-product]");
            if (productEl) {
                productEl.textContent = scene.productId || "Unknown Product ID";
            }

            const datetimeEl = card.querySelector("[data-sentinel-datetime]");
            if (datetimeEl) {
                const formatted = formatDateTime(scene.datetime);
                datetimeEl.textContent = formatted
                    ? `Acquired: ${formatted}`
                    : "Acquisition time unavailable.";
            }

            const detailsEl = card.querySelector("[data-sentinel-details]");
            if (detailsEl) {
                const parts = [];
                if (scene.tileId) {
                    parts.push(scene.tileId);
                }
                const cloud = formatCloud(scene.cloudCover);
                if (cloud) {
                    parts.push(`Cloud ${cloud}`);
                }
                if (scene.collection) {
                    parts.push(scene.collection);
                }
                detailsEl.textContent = parts.length
                    ? parts.join(" • ")
                    : "Details unavailable";
            }

            const downloadEl = card.querySelector("[data-sentinel-download]");
            if (downloadEl) {
                if (scene.downloadUrl) {
                    downloadEl.href = scene.downloadUrl;
                    downloadEl.download = createDownloadFilename(scene);
                    downloadEl.setAttribute("aria-disabled", "false");
                    downloadEl.classList.remove("hidden");
                } else {
                    downloadEl.href = "#";
                    downloadEl.setAttribute("aria-disabled", "true");
                }
            }

            const previewBtn = card.querySelector("[data-sentinel-preview]");
            previewBtn?.addEventListener("click", () => previewSceneOnMap(scene));

            const processBtn = card.querySelector("[data-sentinel-process]");
            if (processBtn) {
                processBtn.dataset.sceneId = scene.id;
                processBtn.dataset.sceneTitle = scene.title || "";
                processBtn.dataset.sceneUrl = scene.downloadUrl || "";
            }

            return fragment;
        };

        const renderCollections = (scenes) => {
            if (elements.list) {
                elements.list.innerHTML = "";
            }
            if (!Array.isArray(scenes) || !scenes.length) {
                setStatus(
                    "No Sentinel-2 scenes match the current filters. Try widening the search.",
                    "warning"
                );
                return;
            }
            const container = document.createDocumentFragment();
            scenes.forEach((scene) => {
                const card = renderCard(scene);
                if (card) {
                    container.appendChild(card);
                }
            });
            elements.list?.appendChild(container);
            setStatus(
                `Showing ${scenes.length} Sentinel-2 scene${
                    scenes.length > 1 ? "s" : ""
                }.`
            );
        };

        const normaliseScene = (feature) => {
            if (!feature || typeof feature !== "object") {
                return null;
            }
            const props = feature.properties ?? {};
            const services = props.services ?? {};
            const geometry = feature.geometry ?? null;

            const idCandidate =
                feature.id ?? props.id ?? props.productIdentifier ?? props.title;
            const id = idCandidate ? String(idCandidate) : null;
            const titleCandidate =
                props.title ?? props.productIdentifier ?? id ?? "Sentinel-2 Scene";
            const title = String(titleCandidate).trim();
            const productId = props.productIdentifier
                ? String(props.productIdentifier)
                : id;
            const tileIdentifier =
                props.tileIdentifier ??
                props.tileid ??
                props.mgrs ??
                props.mgrsId ??
                props["sentinel:tile_id"] ??
                null;
            const cloudCover = [
                props["eo:cloudCover"],
                props.cloudCover,
                props.cloudcoverpercentage,
                props.cloudCoverageAssessment,
            ]
                .map((value) => Number(value))
                .find((value) => Number.isFinite(value));
            const acquisition =
                props.startDate ??
                props.contentDate?.start ??
                props.beginningDateTime ??
                props.sensingStart ??
                props.datetime ??
                props.completionDate ??
                props.endPosition ??
                null;
            const quicklook =
                props.quicklook ??
                props.thumbnail ??
                services.quicklook?.url ??
                services.preview?.url ??
                services.thumbnail?.url ??
                null;
            const downloadUrl =
                (typeof services.download?.url === "string" && services.download.url) ||
                (typeof services.download === "string" && services.download) ||
                null;
            const wmsService =
                services.wms ?? services.ogc?.wms ?? services.ogc ?? null;
            const wmsUrl = typeof wmsService?.url === "string" ? wmsService.url : null;
            const wmsLayer = wmsService?.layers ?? wmsService?.layer ?? null;
            const wmsParams =
                typeof wmsService?.params === "object" && wmsService.params
                    ? wmsService.params
                    : {};
            const bbox = Array.isArray(feature.bbox)
                ? feature.bbox
                : Array.isArray(props.bbox)
                    ? props.bbox
                    : null;

            return {
                id: id ?? title,
                title,
                productId: productId ?? title,
                collection:
                    props.collection ?? props.platformname ?? props.platformName ?? "Sentinel-2",
                datetime: acquisition,
                completionDate: props.completionDate ?? props.contentDate?.end ?? null,
                cloudCover: cloudCover ?? null,
                tileId: tileIdentifier ? String(tileIdentifier) : null,
                downloadUrl,
                quicklookUrl: quicklook,
                wmsUrl,
                wmsLayer,
                wmsParams,
                geometry,
                footprint: props.footprint ?? null,
                bbox,
                raw: feature,
            };
        };

        const fetchScenes = async (filters, limit) => {
            const params = buildQueryParams(filters, limit);
            const url = `${config.endpoint}?${params.toString()}`;
            const headers = { Accept: "application/json" };
            if (state.token) {
                headers.Authorization = `Bearer ${state.token}`;
            }

            const response = await fetch(url, { headers });
            if (!response.ok) {
                const errorText = await response.text().catch(() => "");
                throw new Error(
                    errorText || `Catalogue request failed (${response.status})`
                );
            }

            const payload = await response.json().catch(() => ({}));
            const features = Array.isArray(payload?.features) ? payload.features : [];
            return features.map(normaliseScene).filter(Boolean);
        };

        const loadCollections = async ({ triggeredByUser = false } = {}) => {
            if (state.loading) {
                return;
            }
            const filters = buildFiltersFromForm();
            state.loading = true;
            state.lastFilters = filters;
            setStatus(
                triggeredByUser
                    ? "Fetching Sentinel-2 scenes..."
                    : "Loading latest Sentinel-2 acquisitions..."
            );
            clearPreview({ hidePanel: true });
            if (elements.list) {
                elements.list.innerHTML = "";
            }

            try {
                const limit = triggeredByUser
                    ? config.userMaxRecords
                    : config.defaultMaxRecords;
                const scenes = await fetchScenes(filters, limit);
                state.scenes = scenes;
                renderCollections(scenes);
                updateLastUpdated();
            } catch (error) {
                console.error("Failed to load Sentinel-2 catalogue", error);
                setStatus(
                    error?.message || "Unable to load Sentinel-2 scenes at the moment.",
                    "error"
                );
            } finally {
                state.loading = false;
                sentinelModule.loadedOnce = true;
            }
        };

        const handleFormSubmit = (event) => {
            event.preventDefault();
            loadCollections({ triggeredByUser: true });
        };

        const handleReset = (event) => {
            event.preventDefault();
            applyDefaultDateInputs(true);
            if (elements.cloudInput) {
                elements.cloudInput.value = elements.cloudInput.defaultValue || "40";
            }
            if (elements.productSelect) {
                elements.productSelect.value =
                    elements.productSelect.defaultValue || "S2MSI2A";
            }
            if (elements.latInput) {
                elements.latInput.value = elements.latInput.defaultValue || "";
            }
            if (elements.lonInput) {
                elements.lonInput.value = elements.lonInput.defaultValue || "";
            }
            loadCollections({ triggeredByUser: false });
        };

        const sentinelModule = {
            loadCollections,
            clearPreview,
            get scenes() {
                return state.scenes;
            },
            get loading() {
                return state.loading;
            },
            get previewScene() {
                return state.preview.scene;
            },
            loadedOnce: false,
        };

        window.AppMap.sentinel = sentinelModule;

        elements.form?.addEventListener("submit", handleFormSubmit);
        elements.resetButton?.addEventListener("click", handleReset);
        elements.previewImageryBtn?.addEventListener("click", (event) => {
            event.preventDefault();
            toggleImageryLayer();
        });
        elements.previewClearBtn?.addEventListener("click", () => {
            clearPreview({ hidePanel: true });
        });

        applyDefaultDateInputs();

        try {
            window.dispatchEvent(
                new CustomEvent("app:sentinel:ready", { detail: sentinelModule })
            );
        } catch (error) {
            console.warn("Unable to dispatch app:sentinel:ready event", error);
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialiseSentinelModule, {
            once: true,
        });
    } else {
        initialiseSentinelModule();
    }
})();

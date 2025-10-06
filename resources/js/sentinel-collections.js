const sentinelCatalogEndpoint =
    "https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json";

const formatISODate = (date) => {
    if (!(date instanceof Date)) return "";
    const copy = new Date(date.getTime());
    copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());
    return copy.toISOString().split("T")[0];
};

const formatReadableDate = (value) => {
    if (!value) return "Unknown date";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return (
        parsed.toLocaleString("id-ID", {
            dateStyle: "medium",
            timeStyle: "short",
            timeZone: "UTC",
        }) + " UTC"
    );
};

const formatCloudCover = (value) => {
    if (typeof value === "number" && !Number.isNaN(value)) {
        return `${value.toFixed(1)}%`;
    }
    return "N/A";
};

const attemptFetch = async (targetUrl) => {
    const response = await fetch(targetUrl);
    if (!response.ok) {
        throw new Error(`Status ${response.status}`);
    }
    return response.json();
};

const fetchSentinelCatalog = async (url) => {
    try {
        return await attemptFetch(url);
    } catch (err) {
        const proxyUrl = `https://api.allorigins.win/raw?url=${encodeURIComponent(
            url
        )}`;
        return await attemptFetch(proxyUrl);
    }
};

const createSentinelCard = (feature, template) => {
    if (!template) return null;

    const clone = template.content.cloneNode(true);
    const props = feature?.properties ?? {};
    const links = feature?.links ?? [];
    const assets = feature?.assets ?? {};

    const titleEl = clone.querySelector("[data-sentinel-title]");
    const productEl = clone.querySelector("[data-sentinel-product]");
    const datetimeEl = clone.querySelector("[data-sentinel-datetime]");
    const detailEl = clone.querySelector("[data-sentinel-details]");
    const previewLink = clone.querySelector("[data-sentinel-preview]");
    const openLink = clone.querySelector("[data-sentinel-open]");

    const shortenText =
        typeof window?.shortenFilename === "function"
            ? (value, max = 40) => window.shortenFilename(String(value), max)
            : (value) => String(value ?? "");

    const productId =
        props.productIdentifier ||
        props.title ||
        feature?.id ||
        "Sentinel-2 Product";
    const acquisitionDate =
        props.completionDate ||
        props.startDate ||
        props.endPosition ||
        props.beginPosition ||
        props.startTimeFromAscendingNode;
    const mgrsIdentifier = props.mgrsId || props.tileId || props.MGRS;
    const tileText = mgrsIdentifier ? `Tile ${mgrsIdentifier}` : null;
    const cloudCover =
        props.cloudCover ??
        props["cloudcoverpercentage"] ??
        props["cloudCoverageAssessment"];

    const titleText = shortenText(props.title) || shortenText(productId);
    const productText = productId;

    if (titleEl) {
        titleEl.textContent = shortenText(titleText, 48);
        titleEl.setAttribute("title", titleText);
    }

    if (productEl) {
        productEl.textContent = shortenText(productText, 44);
        productEl.setAttribute("title", productText);
    }

    if (datetimeEl)
        datetimeEl.textContent = `Acquired: ${formatReadableDate(
            acquisitionDate
        )}`;

    const detailParts = [];
    if (tileText) detailParts.push(tileText);
    if (cloudCover !== undefined)
        detailParts.push(`Cloud cover: ${formatCloudCover(Number(cloudCover))}`);
    if (props.collection) detailParts.push(`Collection: ${props.collection}`);

    if (detailEl) {
        detailEl.textContent = detailParts.length
            ? detailParts.join(" • ")
            : "No additional metadata available";
    }

    const quicklookUrl =
        props.quicklook ||
        assets?.thumbnail?.href ||
        assets?.overview?.href ||
        links.find((link) => link.rel === "preview")?.href;
    const openUrl =
        props.services?.download?.url ||
        links.find((link) => link.rel === "self")?.href ||
        (typeof feature?.id === "string" && feature.id.startsWith("http")
            ? feature.id
            : null);

    if (previewLink) {
        if (quicklookUrl) {
            previewLink.href = quicklookUrl;
            previewLink.classList.remove("hidden");
        } else {
            previewLink.classList.add("hidden");
        }
    }

    if (openLink) {
        if (openUrl) {
            openLink.href = openUrl;
        } else {
            openLink.href = `https://dataspace.copernicus.eu/browser/?product=${encodeURIComponent(
                productId
            )}`;
        }
    }

    return clone;
};

export function initSentinelCollections({
    sentinelStatus,
    sentinelList,
    sentinelTemplate,
    sentinelLastUpdated,
    sentinelRefreshButton,
} = {}) {
    let sentinelLoadedOnce = false;

    if (!sentinelStatus || !sentinelList || !sentinelTemplate) {
        return {
            loadSentinelCollections: () => {},
            hasLoadedOnce: () => sentinelLoadedOnce,
        };
    }

    const loadSentinelCollections = async (forceRefresh = false) => {
        if (forceRefresh && window.MyZkToast?.info) {
            window.MyZkToast.info("Refreshing Sentinel-2 catalogue...");
        }

        sentinelStatus.classList.remove("hidden");
        sentinelStatus.textContent = "Fetching latest Sentinel-2 collections...";
        sentinelList.innerHTML = "";

        const endDate = new Date();
        const startDate = new Date(endDate);
        startDate.setMonth(startDate.getMonth() - 1);
        startDate.setHours(0, 0, 0, 0);
        const endDateAdjusted = new Date(endDate);
        endDateAdjusted.setHours(23, 59, 59, 999);

        const params = new URLSearchParams({
            startDate: formatISODate(startDate),
            completionDate: formatISODate(endDateAdjusted),
            maxRecords: "20",
            productType: "S2MSI2A",
            sortParam: "startDate",
            sortOrder: "descending",
        });

        const requestUrl = `${sentinelCatalogEndpoint}?${params.toString()}`;

        try {
            const response = await fetchSentinelCatalog(requestUrl);
            const features = Array.isArray(response?.features)
                ? response.features
                : [];

            if (!features.length) {
                sentinelStatus.textContent =
                    "No Sentinel-2 collections found in the last 30 days.";
            } else {
                sentinelStatus.classList.add("hidden");
                features.forEach((feature) => {
                    const card = createSentinelCard(feature, sentinelTemplate);
                    if (card) {
                        sentinelList.appendChild(card);
                    }
                });
            }

            sentinelLoadedOnce = true;

            if (sentinelLastUpdated) {
                sentinelLastUpdated.textContent = new Date().toLocaleString(
                    "id-ID"
                );
            }

            if (features.length && forceRefresh && window.MyZkToast?.success) {
                window.MyZkToast.success("Sentinel-2 collections updated.");
            }
        } catch (error) {
            const message = error?.message ? ` (${error.message})` : "";
            sentinelStatus.classList.remove("hidden");
            sentinelStatus.textContent = `Unable to fetch Sentinel-2 collections${message}. Please try again later.`;

            if (sentinelLastUpdated) {
                sentinelLastUpdated.textContent = new Date().toLocaleString(
                    "id-ID"
                );
            }

            if (window.MyZkToast?.error) {
                window.MyZkToast.error(
                    "Failed to update Sentinel-2 collections."
                );
            }
        }
    };

    if (sentinelRefreshButton) {
        sentinelRefreshButton.addEventListener("click", () =>
            loadSentinelCollections(true)
        );
    }

    return {
        loadSentinelCollections,
        hasLoadedOnce: () => sentinelLoadedOnce,
    };
}

window.initSentinelCollections = initSentinelCollections;


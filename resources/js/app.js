import "./bootstrap";
import "./variables";
import "./preline-helpers";
import "./navigation";

$.ajaxSetup({
    headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
    },
});

// Fungsi untuk menghitung waktu relatif (misal: 2 hours ago)
/**
 * Calculates and returns a human-readable relative time string from a given date
 *
 * @param {string} dateStr - The date string to calculate relative time from
 * @returns {string} A formatted relative time string (e.g., "2 hours ago", "just now")
 *
 * @example
 * timeAgo("2023-12-01T10:00:00Z") // Returns "2 days ago"
 * timeAgo("2023-12-03T11:55:00Z") // Returns "5 minutes ago"
 */
function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const seconds = Math.floor((new Date() - date) / 1000);
    const intervals = [
        { label: "year", seconds: 31536000 },
        { label: "month", seconds: 2592000 },
        { label: "day", seconds: 86400 },
        { label: "hour", seconds: 3600 },
        { label: "minute", seconds: 60 },
        { label: "second", seconds: 1 },
    ];

    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count > 0) {
            return `${count} ${interval.label}${count !== 1 ? "s" : ""} ago`;
        }
    }
    return "just now";
}

/**
 * Formats a date string into a customizable, localized date format
 *
 * @param {string|null|undefined} dateString - The date string to format (ISO format recommended)
 * @param {boolean} [includeTime=true] - Whether to include time in the formatted output
 * @param {Object|null} [customOptions=null] - Custom formatting options to override defaults
 * @param {string} [locale=document.documentElement.lang || "en-US"] - The locale to use for formatting (follows Laravel system locale)
 * @param {string} [fallback="-"] - The fallback value to return if formatting fails
 * @returns {string} A formatted date string or the fallback value
 *
 * @example
 * formatCustomDate("2023-12-01T10:30:00Z") // Returns "Dec 1, 2023, 10:30"
 * formatCustomDate("2023-12-01", false) // Returns "Dec 1, 2023"
 * formatCustomDate("", true, null, "en-US", "No date") // Returns "No date"
 * formatCustomDate("2023-12-01", true, { weekday: "long" }, "id-ID") // Returns custom format
 */
function formatCustomDate(
    dateString,
    includeTime = true,
    customOptions = null,
    locale = document.documentElement.lang || "en-US",
    fallback = "-"
) {
    if (!dateString) {
        return fallback;
    }

    const defaultOptions = {
        day: "numeric",
        month: "short",
        year: "numeric",
    };

    if (includeTime) {
        defaultOptions.hour = "2-digit";
        defaultOptions.minute = "2-digit";
        defaultOptions.hour12 = false;
    }

    const options = customOptions
        ? { ...defaultOptions, ...customOptions }
        : defaultOptions;

    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return fallback;
        }
        return date.toLocaleDateString(locale, options);
    } catch (error) {
        return fallback;
    }
}

/**
 * Formats a Date object into an ISO date string (YYYY-MM-DD) adjusted for the local timezone.
 *
 * This function takes a Date object and returns a string representation of the date
 * in ISO format (YYYY-MM-DD), ensuring the date is adjusted for the local timezone.
 * If the input is not a valid Date object, it returns an empty string.
 *
 * @param {Date} date - The Date object to format
 * @returns {string} The formatted date string in YYYY-MM-DD format, or an empty string if input is invalid
 *
 * @example
 * formatISODate(new Date("2023-12-01T10:30:00Z")) // Returns "2023-12-01" (adjusted for local timezone)
 * formatISODate("invalid date") // Returns ""
 */
function formatISODate(date) {
    if (!(date instanceof Date)) return "";
    const copy = new Date(date.getTime());
    copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());
    return copy.toISOString().split("T")[0];
}

/**
 * Calculates a default date range ending today at 23:59 and starting
 * a configurable number of months in the past at 00:00.
 *
 * @param {number} [monthsBack=1] - Number of months to subtract from the end date.
 * @returns {{start: Date, end: Date}} An object containing start and end Date instances.
 */
function getDefaultDateRange(monthsBack = 1) {
    const months = Number.isFinite(monthsBack) && monthsBack > 0 ? monthsBack : 1;
    const end = new Date();
    const endClone = new Date(end.getTime());
    endClone.setHours(23, 59, 59, 999);

    const startClone = new Date(endClone.getTime());
    startClone.setMonth(startClone.getMonth() - months);
    startClone.setHours(0, 0, 0, 0);

    return { start: startClone, end: endClone };
}

/**
 * Attempts to coerce the provided value into a valid Date instance.
 *
 * @param {string|Date|null|undefined} value - Input value to parse.
 * @returns {Date|null} A new Date instance when the value can be parsed, otherwise null.
 */
function parseDateInput(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return new Date(value.getTime());
    }

    if (typeof value === "string" && value.trim() !== "") {
        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed;
        }
    }

    return null;
}

/**
 * Formats a date string into a readable medium-style date with short time format.
 *
 * @param {string|Date|null|undefined} value - The date string or Date object to format
 * @returns {string} A formatted date string in medium date format with short time format,
 *                   or "Unknown date" if value is falsy,
 *                   or the original value if it cannot be parsed as a valid date
 *
 * @example
 * formatReadableDate("2023-12-01T10:30:00Z") // Returns "1 Des 2023 10.30 UTC"
 * formatReadableDate(null) // Returns "Unknown date"
 * formatReadableDate("invalid-date") // Returns "invalid-date"
 */
function formatReadableDate(value) {
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
}

window.timeAgo = timeAgo;
window.formatCustomDate = formatCustomDate;
window.formatISODate = formatISODate;
window.formatReadableDate = formatReadableDate;
window.getDefaultDateRange = getDefaultDateRange;
window.parseDateInput = parseDateInput;

/**
 * Formats a number into a currency string based on the specified locale and currency code.
 *
 * @param {number|string} amount - The amount to format as currency.
 * @param {string} [currencyCode="USD"] - The ISO currency code (e.g., "IDR", "USD").
 * @param {string} [locale="en-US"] - The locale to use for formatting (e.g., "id-ID", "en-US").
 * @param {Object} [options={}] - Additional options to pass to Intl.NumberFormat.
 * @returns {string} A formatted currency string or "-" if formatting fails.
 *
 * @example
 * formatCurrency(1234567.89) // Returns "$1,234,567.89" for default locale "en-US"
 * formatCurrency(1234567.89, "IDR", "id-ID") // Returns "Rp1.234.567,89"
 * formatCurrency("invalid") // Returns "-"
 */
function formatCurrency(
    amount,
    currencyCode = "USD",
    locale = document.documentElement.lang || "en-US",
    // locale = navigator.language || "en-US",
    options = {}
) {
    const defaultOptions = {
        style: "currency",
        currency: currencyCode,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    };

    const formatterOptions = { ...defaultOptions, ...options };

    try {
        const numericAmount =
            typeof amount === "string" ? parseFloat(amount) : amount;
        if (isNaN(numericAmount)) {
            return "-";
        }
        return new Intl.NumberFormat(locale, formatterOptions).format(
            numericAmount
        );
    } catch (error) {
        return "-";
    }
}
window.formatCurrency = formatCurrency;

/**
 * Formats a number into a localized string representation.
 *
 * @param {number|string} amount - The number to format.
 * @param {string} [locale="us-US"] - The locale to use for formatting (e.g., "us-US", "id-ID").
 * @param {number} [fractionDigits=3] - The number of digits to appear after the decimal point.
 * @returns {string} A formatted number string or "-" if formatting fails.
 *
 * @example
 * formatNumber(1234567.89) // Returns "1,234,567.890" for locale "us-US" with default 3 fraction digits
 * formatNumber(1234567.89, 2, "id-ID") // Returns "1.234.567,89" for locale "id-ID" with 2 fraction digits
 */
function formatNumber(
    amount,
    fractionDigits = 3,
    locale = document.documentElement.lang || "us-US"
    // locale = navigator.language || "us-US"
) {
    try {
        // Convert to number if it's a string
        const numericAmount =
            typeof amount === "string" ? parseFloat(amount) : amount;

        // Check if it's a valid number
        if (isNaN(numericAmount) || !isFinite(numericAmount)) {
            return "-";
        }

        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        }).format(numericAmount);
    } catch (error) {
        return "-";
    }
}
window.formatNumber = formatNumber;

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
window.coordinateFormatIndo = coordinateFormatIndo;

/**
 * Shortens a filename by truncating the middle portion and replacing it with ellipsis
 * if the filename exceeds the specified maximum length.
 *
 * @param {string} name - The filename to be shortened
 * @param {number} [maxLength=40] - The maximum allowed length of the filename
 * @returns {string} The shortened filename with ellipsis in the middle if it exceeded maxLength,
 *                   otherwise returns the original filename
 *
 * @example
 * shortenFilename("very_long_filename_with_many_characters.txt", 20)
 * // Returns "very_lo...characters.txt"
 *
 * shortenFilename("short.txt", 20)
 * // Returns "short.txt"
 */
function shortenFilename(name, maxLength = 40) {
    if (name.length <= maxLength) return name;
    const start = name.slice(0, Math.floor(maxLength / 2) - 3);
    const end = name.slice(-Math.floor(maxLength / 2));
    return `${start}...${end}`;
}
window.shortenFilename = shortenFilename;

/**
 * Formats a given time duration in seconds into a human-readable ETA string.
 *
 * @param {number} seconds - The time duration in seconds to format
 * @returns {string} A formatted time string in the format:
 *                   - "X s" for less than 60 seconds
 *                   - "Xm Ys" for less than 60 minutes
 *                   - "Xh Ym" for 60 minutes or more (h = hours, m = minutes)
 *
 * @example
 * formatTimeETA(30)    // Returns "30 s"
 * formatTimeETA(150)   // Returns "2m 30s"
 * formatTimeETA(3661)  // Returns "1h 1m"
 */
function formatTimeETA(seconds) {
    if (seconds < 60) return `${Math.round(seconds)} s`;
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    if (mins < 60) return `${mins}m ${secs}s`;
    const hrs = Math.floor(mins / 60);
    const remMins = mins % 60;
    return `${hrs}h ${remMins}m`;
}
window.formatTimeETA = formatTimeETA;

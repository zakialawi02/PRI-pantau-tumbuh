import "./bootstrap";
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
 * Formats a date string into a human-readable custom date format
 *
 * @param {string} dateString - The date string to format (ISO format or any valid date string)
 * @param {Object} customOptions - Custom formatting options (optional)
 * @param {string} locale - Locale string for formatting (optional, defaults to "en-US")
 * @param {string} fallback - Fallback string when date is invalid (optional, defaults to "-")
 * @returns {string} A formatted date string or fallback if input is invalid
 *
 * @example
 * formatCustomDate("2023-12-01T14:30:00Z") // Returns "Dec 1, 2023, 13:30"
 * formatCustomDate("2023-12-01T14:30:00Z", { year: "2-digit" }) // Returns "Dec 1, 23, 13:30"
 * formatCustomDate("2023-12-01T14:30:00Z", null, "id-ID") // Returns "1 Des 2023, 14.30"
 * formatCustomDate("", null, null, "No date") // Returns "No date"
 */
function formatCustomDate(
    dateString,
    customOptions = null,
    locale = "en-US",
    fallback = "-"
) {
    if (!dateString) {
        return fallback;
    }

    const defaultOptions = {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    };

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
window.timeAgo = timeAgo;
window.formatCustomDate = formatCustomDate;

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

$(document).ready(function () {
    const themeToggle = document.getElementById("theme-toggle");
    const iconSun = document.getElementById("icon-sun");
    const iconMoon = document.getElementById("icon-moon");
    function applyTheme(theme) {
        document.documentElement.classList.toggle("dark", theme === "dark");
        localStorage.setItem("theme", theme);
        iconSun ? iconSun.classList.toggle("hidden", theme !== "dark") : null;
        iconMoon ? iconMoon.classList.toggle("hidden", theme === "dark") : null;
    }
    // Cek tema saat ini
    const savedTheme = localStorage.getItem("theme") || "light";
    applyTheme(savedTheme);
    // Toggle tema saat tombol diklik
    if (themeToggle) {
        themeToggle.addEventListener("click", function () {
            const newTheme = document.documentElement.classList.contains("dark")
                ? "light"
                : "dark";
            applyTheme(newTheme);
        });
    }
});

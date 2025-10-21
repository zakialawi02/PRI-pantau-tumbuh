// Set status with appropriate styling
const STATUS_CONFIG_BADGE_COLOR = {
    paid: {
        class: "bg-green-100 text-green-800",
        text: "Paid",
    },
    pending: {
        class: "bg-yellow-100 text-yellow-800",
        text: "Pending",
    },
    waiting_verification: {
        class: "bg-blue-100 text-blue-800",
        text: "Waiting Verification",
    },
    failed: {
        class: "bg-red-200 text-red-800",
        text: "Failed",
    },
    refunded: {
        class: "bg-gray-100 text-gray-800",
        text: "Refunded",
    },
    chargeback: {
        class: "bg-pink-200 text-pink-800",
        text: "Chargeback",
    },
    active: {
        class: "bg-green-100 text-green-800",
        text: "Active",
    },
    expired: {
        class: "bg-red-100 text-red-800",
        text: "Expired",
    },
    cancelled: {
        class: "bg-rose-200 text-rose-800",
        text: "Cancelled",
    },
    trial: {
        class: "bg-blue-100 text-blue-800",
        text: "Trial",
    },
    awaiting_payment: {
        class: "bg-orange-100 text-orange-800",
        text: "Awaiting Payment",
    },
    suspended: {
        class: "bg-red-600 text-gray-200",
        text: "Suspended",
    },
    default: {
        class: "bg-gray-100 text-gray-800",
        text: "Unknown",
    },
};
window.STATUS_CONFIG_BADGE_COLOR = STATUS_CONFIG_BADGE_COLOR;

const CURRENCY_CONFIG = [
    {
        currencyCode: "IDR",
        currency: "Indonesian Rupiah",
        country: "Indonesia",
        countryCode: "ID",
        locale: "id-ID",
    },
    {
        currencyCode: "USD",
        currency: "US Dollar",
        country: "United States",
        countryCode: "US",
        locale: "en-US",
    },
    {
        currencyCode: "EUR",
        currency: "Euro",
        country: "European Union",
        countryCode: "EU",
        locale: "en-EU",
    },
    {
        currencyCode: "GBP",
        currency: "British Pound",
        country: "United Kingdom",
        countryCode: "GB",
        locale: "en-GB",
    },
];
window.CURRENCY_CONFIG = CURRENCY_CONFIG;

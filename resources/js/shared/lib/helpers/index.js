/**
 * Helper function to get transaction type configuration from window.YAFFA.config.transactionTypes
 * @param {string} transactionTypeValue - The enum value (e.g., 'buy', 'sell', 'withdrawal')
 * @returns {object} Transaction type configuration with category, label, multipliers, etc.
 */
export function getTransactionTypeConfig(transactionTypeValue) {
    const transactionTypes = window.YAFFA.config.transactionTypes || {};
    return transactionTypes[transactionTypeValue] || {
        value: transactionTypeValue,
        label: transactionTypeValue,
        category: 'unknown',
        amount_multiplier: null,
        quantity_multiplier: null,
    };
}

/**
 * Escapes a value for safe HTML interpolation.
 *
 * @param {*} value
 * @returns {string}
 */
export function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Escapes HTML and keeps line breaks for display contexts.
 *
 * @param {*} value
 * @returns {string}
 */
export function escapeHtmlWithLineBreaks(value) {
    return escapeHtml(value).replace(/\r?\n/g, '<br>');
}

/**
 * Parse a fetch response as JSON and throw server-provided errors for non-2xx responses.
 *
 * @param {Response} response
 * @returns {Promise<*>}
 */
export async function jsonFromResponse(response) {
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || response.statusText);
    }

    return data;
}

/**
 * Parse an ISO date-only string ("YYYY-MM-DD") as a local calendar date.
 *
 * new Date("YYYY-MM-DD") is specified to treat the string as UTC midnight,
 * which shifts the displayed date backward by one day for users in timezones
 * west of UTC. This function constructs the Date from components so it always
 * lands in the browser's local timezone.
 *
 * @param {string|null} dateString
 * @returns {Date|null}
 */
export function parseIsoDate(dateString) {
    if (!dateString) return null;
    if (dateString instanceof Date) return dateString;
    const match = String(dateString).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return null;
    const y = Number(match[1]);
    const m = Number(match[2]);
    const d = Number(match[3]);
    if (m < 1 || m > 12) return null;
    return new Date(y, m - 1, d);
}

// Return the ISO "YYYY-MM-DD" representation of a Date in local time.
export function toIsoDateString(date) {
    if (!(date instanceof Date)) {
        date = new Date();
    }
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// Function to create a new date in UTC
export function todayInUTC() {
    let date = new Date();
    return new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0, 0));
}

/**
 * Function to preprocess transaction data returned from the API.
 *
 * @param {Object} transaction
 * @property {Date} transaction.date
 * @property {Object} transaction.transaction_schedule
 * @returns {Object}
 */
export function processTransaction(transaction) {
    // Convert ISO date strings to local-timezone Date objects.
    if (transaction.date) {
        transaction.date = parseIsoDate(transaction.date);
    }

    // toIsoDateString uses local date components, so year_month is always correct.
    transaction.year_month = transaction.date ? toIsoDateString(transaction.date).slice(0, 7) : null;

    if (transaction.transaction_schedule?.start_date) {
        transaction.transaction_schedule.start_date = parseIsoDate(transaction.transaction_schedule.start_date);
    }

    if (transaction.transaction_schedule?.end_date) {
        transaction.transaction_schedule.end_date = parseIsoDate(transaction.transaction_schedule.end_date);
    }

    if (transaction.transaction_schedule?.next_date) {
        transaction.transaction_schedule.next_date = parseIsoDate(transaction.transaction_schedule.next_date);
    }

    // We need an array of categories for standard transactions, extracted from the item array
    if (transaction.config_type === 'standard') {
        // We only need each category once, so we need to remove duplicates by their IDs
        transaction.categories = transaction.transaction_items
            .map(item => item.category)
            // Exclude null categories
            .filter(category => category)
            .filter((category, index, self) => self.findIndex(c => c.id === category.id) === index);
    } else {
        transaction.categories = [];
    }

    // We need an array of tags for standard transactions, extracted from the item array
    if (transaction.config_type === 'standard') {
        // We only need each tag once, so we need to remove duplicates by their IDs
        transaction.tags = transaction.transaction_items
            .map(item => item.tags)
            // Flatten the array of arrays
            .flat()
            // Exclude null tags
            .filter(tag => tag)
            .filter((tag, index, self) => self.findIndex(t => t.id === tag.id) === index);
    } else {
        transaction.tags = [];
    }

    return transaction;
}

import { RRule } from 'rrule';

export function processScheduledTransaction(transaction) {
    if (transaction.transaction_schedule) {
        transaction.transaction_schedule.rule = new RRule({
            dtstart: transaction.transaction_schedule.start_date,
            freq: RRule[transaction.transaction_schedule.frequency],
            interval: transaction.transaction_schedule.interval,
            until: transaction.transaction_schedule.end_date,
        });
    }

    return transaction;
}

/**
 * Function to generate an anchor element with a link to a transaction.
 *
 * @param {number} id The transaction ID.
 * @param {string} text The text to display in the link.
 * @returns {string}
 */
export function transactionLink(id, text) {
    const url = window.route(
        'transaction.open',
        {
            action: 'show',
            transaction: id,
        }
    );

    return `<a href="${url}">${text}</a>`;
}

/**
 * Initialises Bootstrap/CoreUI tooltips within the given parent element,
 * disposing any existing tooltip instances first to avoid duplicates
 * (e.g. after a DataTables redraw).
 *
 * @param {Document|Element} parent - The parent element to search within.
 */
export function initializeBootstrapTooltips(parent = document) {
    const tooltipTriggerList = parent.querySelectorAll(
        '[data-bs-toggle="tooltip"], [data-coreui-toggle="tooltip"]',
    );
    tooltipTriggerList.forEach((tooltipTriggerEl) => {
        const existing = window.bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (existing) {
            existing.dispose();
        }
        new window.bootstrap.Tooltip(tooltipTriggerEl);
    });
}

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

// Normalizes a Date object, an ISO 'YYYY-MM-DD' string, a full ISO datetime
// string (e.g. a Carbon 'datetime'-cast attribute like "2026-06-26T00:00:00.000000Z"),
// or a null/undefined value into the plain 'YYYY-MM-DD' string native
// `<input type="date">` elements require for their value/v-model binding -
// anything else silently renders blank per the HTML date-input spec.
export function toDateInputValue(value) {
    if (!value) return '';
    if (value instanceof Date) return toIsoDateString(value);
    const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

// Function to create a new date in UTC
export function todayInUTC() {
    let date = new Date();
    return new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0, 0));
}

// RRule reads a Date's UTC getters, not its local ones, so a local-midnight
// Date (e.g. from parseIsoDate) has to be re-expressed with matching UTC
// fields before use - otherwise occurrences can land a day off for anyone
// outside UTC. Accepts either a Date or a 'YYYY-MM-DD' string.
export function toRRuleDate(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return new Date(Date.UTC(value.getFullYear(), value.getMonth(), value.getDate()));
    }

    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3) {
        return null;
    }

    const [year, month, day] = parts;
    return new Date(Date.UTC(year, month - 1, day));
}

// Reverses toRRuleDate(): converts an rrule.js occurrence (UTC-anchored
// Date) back into the schedule's stored 'YYYY-MM-DD' string using UTC
// getters - using local getters here would reintroduce the exact
// off-by-one-day class of bug toRRuleDate exists to avoid.
export function fromRRuleDate(date) {
    if (!date) {
        return null;
    }

    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
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

/**
 * Converts a stored RFC5545 ordinal BYDAY token (e.g. "1WE", "-1FR") into
 * the rrule.js Weekday instance used for RRule's byweekday option.
 *
 * @param {string} token
 * @returns {object}
 */
export function byDayToRRuleWeekday(token) {
    const weekdayCode = token.slice(-2);
    const ordinal = parseInt(token.slice(0, -2), 10);

    return RRule[weekdayCode].nth(ordinal);
}

// Imported from the leaf translate module (not the '@/shared/lib/i18n' barrel)
// to avoid a circular import: that barrel re-exports format.js, which itself
// imports parseIsoDate from this file.
import { __ } from '@/shared/lib/i18n/translate';

// Ordinal/weekday/month labels for a schedule's by_day/by_month RFC5545
// fields, shared by the schedule edit form (TransactionSchedule.vue) and its
// read-only display (Schedule.vue) so both read the same values the same way.
export const ordinalLabels = {
    1: __('First'),
    2: __('Second'),
    3: __('Third'),
    4: __('Fourth'),
    '-1': __('Last'),
};

// Weekday/month names are derived from Intl rather than translated in lang/*.json: they are a
// fixed, unambiguous set, and the browser's locale data already knows them. Intl returns the
// grammatically-correct lowercase form for some locales (e.g. French, Hungarian, Polish), so the
// first letter is capitalized to read correctly as a standalone label (e.g. dropdown option).
function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

const locale = window.YAFFA?.userSettings?.locale || window.YAFFA?.locale;

export const weekdayLabels = Object.fromEntries(
    ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'].map((code, index) => {
        // 2024-01-07 is a Sunday, so index 0..6 walks Sunday through Saturday.
        const date = new Date(2024, 0, 7 + index);
        return [code, capitalize(new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(date))];
    })
);

export const monthLabels = Object.fromEntries(
    Array.from({ length: 12 }, (_, index) => {
        const date = new Date(2024, index, 1);
        return [index + 1, capitalize(new Intl.DateTimeFormat(locale, { month: 'long' }).format(date))];
    })
);

export const ordinalOptions = Object.entries(ordinalLabels).map(([value, label]) => ({ value, label }));
export const weekdayOptions = Object.entries(weekdayLabels).map(([value, label]) => ({ value, label }));
export const monthOptions = Object.entries(monthLabels).map(([value, label]) => ({ value: Number(value), label }));

export function processScheduledTransaction(transaction) {
    if (transaction.transaction_schedule) {
        const schedule = transaction.transaction_schedule;

        transaction.transaction_schedule.rule = new RRule({
            dtstart: toRRuleDate(schedule.start_date),
            freq: RRule[schedule.frequency],
            interval: schedule.interval,
            until: toRRuleDate(schedule.end_date),
            byweekday: schedule.by_day ? byDayToRRuleWeekday(schedule.by_day) : null,
            // Mirrors TransactionSchedule::buildRule() on the backend: by_month
            // only applies alongside a YEARLY by_day rule, otherwise it's ignored.
            bymonth:
                schedule.by_day && schedule.frequency === 'YEARLY'
                    ? schedule.by_month || null
                    : null,
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

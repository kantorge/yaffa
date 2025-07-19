/**
 * @param {number} input The number to be formatted as currency.
 * @param {string} locale The locale to be used for formatting.
 * @param {Object} currencySettings Object with settings to apply. Expected key(s): iso_code. Optional key(s): min_digits, max_digits.
 * @property {string} currencySettings.iso_code
 * @property {number} currencySettings.min_digits
 * @property {number} currencySettings.max_digits
 *
 * @type {string}
 */
export function toFormattedCurrency(input, locale, currencySettings) {
    // Fallback to raw input if currency settings are missing
    if (!currencySettings || !currencySettings.iso_code) {
        return input.toString();
    }

    // If input is not a number, return it as is
    if (input === null || input === undefined) {
        return '';
    }
    if (isNaN(input)) {
        return input.toString();
    }

    return input.toLocaleString(
        locale,
        {
            style: 'currency',
            currency: currencySettings.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: currencySettings.min_digits || 0,
            maximumFractionDigits: currencySettings.max_digits
        }
    );
}

export function getCurrencySymbol(locale, iso_code) {
    const numberFormat = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: iso_code,
        currencyDisplay: 'narrowSymbol',
    });
    const symbol = numberFormat.format(0).match(/[^0-9,.\s]+/);
    return symbol[0];
}

// Function to return just the ISO version of a date.
export function toIsoDateString(date) {
    // Verify that the date is a Date object
    if (!(date instanceof Date)) {
        date = new Date();
    }

    return date.toISOString().split('T')[0];
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
    // Convert date strings to Date objects
    if (transaction.date) {
        transaction.date = new Date(transaction.date);
    }

    if (transaction.transaction_schedule?.start_date) {
        transaction.transaction_schedule.start_date = new Date(transaction.transaction_schedule.start_date);
    }

    if (transaction.transaction_schedule?.end_date) {
        transaction.transaction_schedule.end_date = new Date(transaction.transaction_schedule.end_date);
    }

    if (transaction.transaction_schedule?.next_date) {
        transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
    }

    // We need an array of categories for standard transactions, extracted from the item array
    if (transaction.transaction_type.type === 'standard') {
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
    if (transaction.transaction_type.type === 'standard') {
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
 * Function to translate strings using the predefined translations in the window.YAFFA.translations object.
 *
 * @param {string} key The translation key.
 * @param {Object} replace An object with key/value pairs to replace in the translation string.
 * @property {string} replace.key The key to replace in the translation string.
 * @property {string} replace.value The value to replace the key with.
 * @returns {string}
 */
export function __(key, replace = {}) {
    let translation = window.YAFFA.translations[key] || key;

    // If the replace object is empty, return the translation as is
    if (Object.keys(replace).length === 0) {
        return translation;
    }

    for (const [key, value] of Object.entries(replace)) {
        translation = translation.replace(':' + key, String(value));
    }

    return translation;
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
 * Function to display a Toast notification.
 *
 * @param {string} header The header of the toast.
 * @param {string} body The body of the toast.
 * @param {string} toastClass The class of the toast.
 * @param {Object} otherProperties Other properties to pass to the toast.
 *
 * @returns {void}
 */
export function showToast(header, body, toastClass, otherProperties ) {
    otherProperties = otherProperties || {};

    // Emit a custom event to global scope to display the Toast
    let notificationEvent = new CustomEvent('toast', {
        detail: {
            ...otherProperties,
            ...{
                header: header,
                body: body,
                toastClass: toastClass,
            }
        }
    });
    window.dispatchEvent(notificationEvent);
}

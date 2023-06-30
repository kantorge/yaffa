/**
 * @param {number} input The number to be formatted as currency.
 * @param {string} locale The locale to be used for formatting.
 * @param {Object} currencySettings Object with settings to apply. Expected keys: iso_code, num_digits.
 * @property {string} currencySettings.iso_code
 * @property {number} currencySettings.num_digits
 *
 * @type {string}
 */
export function toFormattedCurrency(input, locale, currencySettings) {
    // Fallback to raw input if currency settings are missing
    if (!currencySettings || !currencySettings.iso_code) {
        return input.toString();
    }

    return input?.toLocaleString(
        locale,
        {
            style: 'currency',
            currency: currencySettings.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: currencySettings.num_digits,
            maximumFractionDigits: currencySettings.num_digits
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
        // Create rule
        transaction.transaction_schedule.rule = new RRule({
            freq: RRule[transaction.transaction_schedule.frequency],
            interval: transaction.transaction_schedule.interval,
            dtstart: transaction.transaction_schedule.start_date,
            until: transaction.transaction_schedule.end_date,
        });

        transaction.transaction_schedule.active = !!transaction.transaction_schedule.rule.after(new Date(), true);
    }

    return transaction;
}

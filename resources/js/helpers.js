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

    // Add a helper to handle year-month level handling
    transaction.year_month = transaction.date ? transaction.date.toISOString().slice(0, 7) : null;

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

export function initializeBootstrapTooltips() {
    const tooltipTriggerList = document.querySelectorAll(
      '[data-bs-toggle="tooltip"]',
    );
    [...tooltipTriggerList].map(
      (tooltipTriggerEl) => new window.bootstrap.Tooltip(tooltipTriggerEl),
    );
}

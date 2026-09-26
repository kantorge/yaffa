/**
 * Repeatedly calls fetcher() until it resolves with a payload whose `result` is not 'busy',
 * retrying with exponential backoff. Shared by every consumer of an endpoint backed by
 * AccountMonthlySummary recalculation batches (account balance, cash flow report, dashboard
 * account balance widget) so they all wait out an in-progress calculation the same way instead of
 * each inventing its own retry (or no-retry) behavior.
 *
 * @param {Function} fetcher - returns a Promise resolving to the parsed JSON response.
 * @param {Object} callbacks
 * @param {Function} callbacks.onBusy - called with the busy message on each busy response.
 * @param {Function} callbacks.onReady - called with the response data once it is not busy.
 * @param {Function} [callbacks.onError] - called with the error on fetch/network failure.
 * @param {number} [initialInterval=5000] - delay in ms before the first retry; doubles after each subsequent busy response.
 * @returns {Function} cancel - stops any pending retry.
 */
export function pollUntilReady(fetcher, { onBusy, onReady, onError }, initialInterval = 5000) {
    let interval = initialInterval;
    let timeoutId = null;

    function attempt() {
        fetcher()
            .then((data) => {
                if (data.result === 'busy') {
                    onBusy(data.message);

                    timeoutId = setTimeout(attempt, interval);
                    interval *= 2;

                    return;
                }

                onReady(data);
            })
            .catch((error) => {
                if (onError) {
                    onError(error);
                }
            });
    }

    attempt();

    return function cancel() {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };
}

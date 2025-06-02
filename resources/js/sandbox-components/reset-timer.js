// Functions used by the sandbox countdown alert

function getNextResetDate() {
    // Possible improvement: get the schedule from configuration, which is set by the server

    const now = new Date();
    const day = now.getUTCDay();
    const hour = now.getUTCHours();

    let nextReset = new Date(now);
    nextReset.setUTCHours(2, 0, 0, 0); // Set time to 2 AM UTC

    if (day === 1 || day === 3 || day === 5) {
        if (hour >= 2) {
            nextReset.setUTCDate(now.getUTCDate() + (day === 5 ? 3 : 2));
        }
    } else {
        const daysUntilNextReset = [1, 3, 5].find(d => d > day) || 1;
        nextReset.setUTCDate(now.getUTCDate() + ((daysUntilNextReset - day + 7) % 7));
    }

    return nextReset;
}

function getTimeUntilNextReset() {
    const now = new Date();
    const nextReset = getNextResetDate();
    const diff = nextReset - now;

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

    return { days, hours };
}

function getTimeoutMessage(days, hours) {
    const daysLabel = days === 1 ? __('day') : __('days');
    const hoursLabel = hours === 1 ? __('hour') : __('hours');

    return __(
        'The data in this sandbox environment is regularly cleared. Time until next reset: :days :daysLabel and :hours :hoursLabel',
        { days, daysLabel, hours, hoursLabel }
    );
}

// Display the countdown alert using a self-invoking function
(function displayTimeUntilNextReset() {
    const alertContainer = document.getElementById('sandBoxResetAlert');
    if (!alertContainer) {
        return;
    }
    const messageContainer = alertContainer.querySelector('span');
    if (!messageContainer) {
        return;
    }

    const { days, hours } = getTimeUntilNextReset();

    messageContainer.innerText = getTimeoutMessage(days, hours);

    // Set the class based on the remaining time
    if (days === 0 && hours <= 1) {
        alertContainer.classList.add('alert-danger');
    } else if (days === 0 && hours < 3) {
        alertContainer.classList.add('alert-warning');
    } else {
        alertContainer.classList.add('alert-info');
    }

    alertContainer.classList.remove('hidden');
})();

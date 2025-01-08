// General function for the sandbox countdown alert
function getNextResetDate() {
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

function displayTimeUntilNextReset() {
    const alertContainer = document.getElementById('sandBoxResetAlert');
    if (!alertContainer) {
        return;
    }
    const messageContainer = alertContainer.querySelector('span');
    if (!messageContainer) {
        return;
    }

    const { days, hours } = getTimeUntilNextReset();

    messageContainer.innerText = __(`The data in this sandbox environment is regularly cleared. Time until next reset: :days days and :hours hours`, { days, hours });

    // Set the class based on the remaining time
    if (days === 0 && hours <= 1) {
        alertContainer.classList.add('alert-danger');
    } else if (days === 0 && hours < 3) {
        alertContainer.classList.add('alert-warning');
    } else {
        alertContainer.classList.add('alert-info');
    }

    alertContainer.classList.remove('hidden');
}

displayTimeUntilNextReset();

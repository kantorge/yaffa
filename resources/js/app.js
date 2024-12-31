require('./bootstrap');

const routeMap = new Map();
routeMap.set('home', 'dashboard');
routeMap.set('account-group.index', 'account-group/index');
routeMap.set('payees.merge.form', 'payee/merge');
routeMap.set('account.history', 'account/history');
routeMap.set('categories.index', 'categories/index');
routeMap.set('categories.merge.form', 'categories/merge');
routeMap.set('currency.index', 'currency/index');
routeMap.set('currency-rate.index', 'currencyrates/index');
routeMap.set('investment-group.index', 'investment-group/index');
routeMap.set('investment.index', 'investment/index');
routeMap.set('investment.show', 'investment/show');
routeMap.set('investment-price.create', 'investment-price/form');
routeMap.set('investment-price.edit', 'investment-price/form');
routeMap.set('investment-price.list', 'investment-price/list');
routeMap.set('received-mail.index', 'received-mail/index');
routeMap.set('received-mail.show', 'received-mail/show');
routeMap.set('report.schedules', 'reports/schedules');
routeMap.set('reports.cashflow', 'reports/cashflow');
routeMap.set('reports.budgetchart', 'reports/budgetchart');
routeMap.set('reports.transactions', 'reports/transactions');
routeMap.set('reports.investment_timeline', 'reports/investment-timeline');
routeMap.set('search', 'search/search');
routeMap.set('import.csv', 'import/csv');
routeMap.set('register', 'auth/register');
routeMap.set('login', 'auth/login');
routeMap.set('tag.index', 'tag/index');
routeMap.set('user.settings', 'user/settings');

// Generic loader based on map above
// Check if current route exists in map. If yes, load the corresponding file.
if (routeMap.has(route().current())) {
    require('./' + routeMap.get(route().current()));
}

// More specific loaders

// Workaround for POST routes
if (route('transactions.createFromDraft') === window.location.href) {
    require('./transactions/standard');
}

// Index for accounts or payees. Type is verified and used to load the correct file.
if (route().current() === 'account-entity.index'
    && ['account', 'payee'].includes(route().params.type)) {
    require(`./${route().params.type}/index`);
}

// Show only for accounts.
// Payees not supported yet. This should be handled by the controller.
if (route().current() === 'account-entity.show') {
    require('./account/show');
}

// Create or edit payee
// Accounts don't need extra JS
if ((route().current() === 'account-entity.create' || route().current() === 'account-entity.edit')
    && route().params.type === 'payee') {
    require('./payee/form');
}

// Create new transaction. Type is verified and used to load the correct file.
if (route().current() === 'transaction.create'
    && ['standard', 'investment'].includes(route().params.type)) {
    require(`./transactions/${route().params.type}`);
}

// Edit, clone, enter or replace transaction.
// Action type is verified and transaction type is used to load the correct file.
if (route().current() === 'transaction.open'
    && ['edit', 'clone', 'enter', 'replace'].includes(route().params.action)) {
    // Load file based on type of transaction, which is expected to be available in a global variable.
    require('./transactions/' + window.transaction.transaction_type.type);
}

// Show transaction. Action type is verified
if (route().current() === 'transaction.open'
    && ['show'].includes(route().params.action)) {
    require('./transactions/show');
}

// Notifications
require('./notifications');

$(function () {
    // Generally available account selector
    $('#jump_to_account').on('change', function () {
        if (this.value === '') {
            return false;
        }
        window.location.href = route('account-entity.show', {account_entity: this.value});
    });

    // Generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function () {
        return confirm(__('Are you sure to abandon this form?'));
    });
});

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

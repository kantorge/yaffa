import '../sass/app.scss';
import './bootstrap';

// One glob map for all .js files under resources/js
const modules = import.meta.glob('./**/*.js');

const loadModule = (path) => {
    // normalize to "./foo/bar.js"
    const withDot = path.startsWith('./') ? path : `./${path}`;
    const key = withDot.endsWith('.js') ? withDot : `${withDot}.js`;
    if (modules[key]) {
        modules[key]();
    }
};

const routeMap = new Map([
    ['home', 'dashboard'],
    ['account-group.index', 'account-group/index'],
    ['payees.merge.form', 'payee/merge'],
    ['account.history', 'account/history'],
    ['categories.index', 'categories/index'],
    ['categories.merge.form', 'categories/merge'],
    ['currency.index', 'currency/index'],
    ['currency-rate.index', 'currencyrates/index'],
    ['investment-group.index', 'investment-group/index'],
    ['investment.index', 'investment/index'],
    ['investment.show', 'investment/show'],
    ['investment-price.create', 'investment-price/form'],
    ['investment-price.edit', 'investment-price/form'],
    ['investment-price.list', 'investment-price/list'],
    ['received-mail.index', 'received-mail/index'],
    ['received-mail.show', 'received-mail/show'],
    ['report.schedules', 'reports/schedules'],
    ['reports.cashflow', 'reports/cashflow'],
    ['reports.budgetchart', 'reports/budgetchart'],
    ['reports.transactions', 'reports/transactions'],
    ['reports.investment_timeline', 'reports/investment-timeline'],
    ['search', 'search/search'],
    ['import.csv', 'import/csv'],
    ['register', 'auth/register'],
    ['login', 'auth/login'],
    ['tag.index', 'tag/index'],
    ['user.settings', 'user/settings'],
]);

// Generic loader
const current = route().current();
if (routeMap.has(current)) {
    loadModule(routeMap.get(current));
}

// More specific loaders
if (route('transactions.createFromDraft') === window.location.href) {
    loadModule('transactions/standard');
}

if (current === 'account-entity.index' && ['account', 'payee'].includes(route().params.type)) {
    loadModule(`${route().params.type}/index`);
}

if (current === 'account-entity.show') {
    loadModule('account/show');
}

if ((current === 'account-entity.create' || current === 'account-entity.edit') && route().params.type === 'payee') {
    loadModule('payee/form');
}

if (current === 'transaction.create' && ['standard', 'investment'].includes(route().params.type)) {
    loadModule(`transactions/${route().params.type}`);
}

if (current === 'transaction.open' && ['edit', 'clone', 'enter', 'replace'].includes(route().params.action)) {
    loadModule(`transactions/${window.transaction.transaction_type.type}`);
}

if (current === 'transaction.open' && ['show'].includes(route().params.action)) {
    loadModule('transactions/show');
}

// Notifications
import './notifications';

// jQuery handlers...
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

/**
 * Display current Bootstrap breakpoint in local environment
 */
if (import.meta.env.DEV) {
    (function () {
        const breakpointLabel = document.getElementById('breakpoint-label');
        if (!breakpointLabel) {
            return;
        }

        const getBreakpoint = () => {
            const width = window.innerWidth;
            if (width < 576) return 'xs';
            if (width < 768) return 'sm';
            if (width < 992) return 'md';
            if (width < 1200) return 'lg';
            if (width < 1400) return 'xl';
            return 'xxl';
        };

        const updateBreakpoint = () => {
            breakpointLabel.textContent = getBreakpoint();
        };

        updateBreakpoint();
        window.addEventListener('resize', updateBreakpoint);
    })();
}

/**
 * The scripts below are needed only if the application is in sandbox mode, or configured to use related features.
 */
if (window.sandbox_mode) {
    if (current !== 'login') {
        loadModule('sandbox-components/reset-timer');
    } else {
        loadModule('sandbox-components/login-helper');
    }
}
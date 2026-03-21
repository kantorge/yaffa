import '../sass/app.scss';
import './bootstrap';
import { initializeDataTablesI18n } from '@/shared/lib/i18n/datatables';

// One glob map for all .js files under resources/js
// Exclude files that are statically imported to avoid redundant dynamic imports
const modules = import.meta.glob([
    './**/*.js',
    '!./bootstrap.js',
    '!./shared/lib/i18n/**/*.js',
    '!./shared/lib/vue/installRouteGlobal.js',
    '!./shared/lib/notifications/displayNotifications.js'
]);

const dataTablesI18nReady = initializeDataTablesI18n(
    window.YAFFA?.userSettings?.locale || window.YAFFA?.locale,
    window.YAFFA?.userSettings?.language || window.YAFFA?.language,
)
    .catch(() => null);

const loadModule = async (path) => {
    await dataTablesI18nReady;

    // normalize to "./foo/bar.js"
    const withDot = path.startsWith('./') ? path : `./${path}`;
    const key = withDot.endsWith('.js') ? withDot : `${withDot}.js`;
    if (modules[key]) {
        modules[key]();
    }
};

const routeMap = new Map([
    ['home', 'dashboard/index'],
    ['account-groups.index', 'account-groups/index'],
    ['payees.merge.form', 'payee/merge'],
    ['account.history', 'account/history'],
    ['categories.index', 'categories/index'],
    ['categories.merge.form', 'categories/merge'],
    ['currencies.index', 'currencies/index'],
    ['currency-rate.index', 'currency-rates/index'],
    ['investment-groups.index', 'investment-groups/index'],
    ['investments.index', 'investments/index'],
    ['investments.show', 'investments/show'],
    ['investment-price.list', 'investment-price/list'],
    ['report.schedules', 'reports/schedules'],
    ['reports.cashflow', 'reports/cashflow'],
    ['reports.budgetchart', 'reports/budgetchart'],
    ['reports.transactions', 'reports/transactions'],
    ['reports.investment_timeline', 'reports/investment-timeline'],
    ['search', 'search/search'],
    ['import.csv', 'import/csv'],
    ['ai-documents.index', 'ai-documents/index'],
    ['ai-documents.show', 'ai-documents/show'],
    ['register', 'auth/register'],
    ['login', 'auth/login'],
    ['tags.index', 'tags/index'],
    ['user.settings', 'user/settings'],
    ['user.ai-settings', 'user/settings'],
]);

// Generic loader
const current = route().current();
if (routeMap.has(current)) {
    loadModule(routeMap.get(current));
}

// More specific loaders
const createFromDraftPath = new URL(
    route('transactions.createFromDraft'),
    window.location.origin,
).pathname;
const currentPath = window.location.pathname;

if (currentPath === createFromDraftPath) {
    if (document.querySelector('transaction-container-investment')) {
        loadModule('transactions/investment');
    } else {
        loadModule('transactions/standard');
    }
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
    loadModule(`transactions/${window.transaction.config_type}`);
}

if (current === 'transaction.open' && ['show'].includes(route().params.action)) {
    loadModule('transactions/show');
}

// Notifications
import '@/shared/lib/notifications/displayNotifications';

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
if (window.YAFFA.config.sandbox_mode) {
    if (current !== 'login') {
        loadModule('sandbox-components/reset-timer');
    } else {
        loadModule('sandbox-components/login-helper');
    }
}
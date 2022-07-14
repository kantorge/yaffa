require('./bootstrap');

// Helper functions
// TODO: find appropriate place for these
Number.prototype.toLocalCurrency = function(currency, nonBreakingSpaces) {
    if (nonBreakingSpaces !== false) {
        nonBreakingSpaces = true;
    }

    var result = this.toLocaleString(
        'hu-HU',
        {
            style: 'currency',
            currency: currency.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: currency.num_digits,
            maximumFractionDigits: currency.num_digits
        }
    );

    if (nonBreakingSpaces) {
        result = result.replace(/\s/g, '&nbsp;');
    }

    return result;
};

Number.prototype.toLocalQuantity = function(maximumFractionDigits, nonBreakingSpaces) {
    if (nonBreakingSpaces !== false) {
        nonBreakingSpaces = true;
    }

    maximumFractionDigits = maximumFractionDigits || 4;

    var result = this.toLocaleString('hu-HU',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: maximumFractionDigits
        }
    );

    if (nonBreakingSpaces) {
        result = result.replace(/\s/g, '&nbsp;');
    }

    return result;
};

if (   window.location.pathname === '/account/summary'
    || window.location.pathname === '/account/summary/withClosed'
    || window.location.pathname === '/') {
    require('./account/summary');
}

if (window.location.pathname === '/account-group') {
    require('./account-group/index');
}
if (   window.location.pathname === '/account-entity'
    && /type=account/.test(window.location.search)) {
    require('./account/index');
}
if (   window.location.pathname === '/account-entity'
    && /type=payee/.test(window.location.search)) {
    require('./payees/index');
}

if (/^\/payees\/merge/.test(window.location.pathname)) {
    require('./payees/merge');
}

if (/^\/account\/history\/\d+/.test(window.location.pathname)) {
    require('./account/history');
}
if (window.location.pathname === '/categories') {
    require('./categories/index');
}
if (/^\/categories\/merge/.test(window.location.pathname)) {
    require('./categories/merge');
}

if (window.location.pathname === '/currencies') {
    require('./currencies/index');
}
if (/^\/currencyrates\/\d+\/\d+/.test(window.location.pathname)) {
    require('./currencyrates/index');
}
if (window.location.pathname === '/investment-group') {
    require('./investment-group/index');
}
if (window.location.pathname === '/investment') {
    require('./investment/index');
}
if (/^\/investment\/summary/.test(window.location.pathname)) {
    require('./investment/summary');
}
if (/^\/investment\/\d+/.test(window.location.pathname)) {
    require('./investment/show');
}
if (   window.location.pathname === '/investment-price/create'
    || /^\/investment-price\/\d+\/edit/.test(window.location.pathname)) {
    require('./investment-price/form');
}
if (/^\/investment-price\/list\/\d+/.test(window.location.pathname)) {
    require('./investment-price/list');
}
if (window.location.pathname === '/tag') {
    require('./tags/index');
}
if (   window.location.pathname === '/transactions/standard/create'
    || /^\/transactions\/standard\/\d+\/(edit|clone|enter|replaceSchedule)/.test(window.location.pathname)) {

    require('./transactions/standard');
}

if (   window.location.pathname === '/transactions/investment/create'
    || /^\/transactions\/investment\/\d+\/(edit|clone|enter|replaceSchedule)/.test(window.location.pathname)) {

    require('./transactions/investment');
}

if (/^\/transactions\/standard\/\d+\/(show)/.test(window.location.pathname)) {
    require('./transactions/show');
}

if (window.location.pathname === '/schedule') {
    require('./schedule/index');
}

if (window.location.pathname === '/reports/cashflow') {
    require('./reports/cashflow');
}

if (window.location.pathname === '/reports/budgetchart') {
    require('./reports/budgetchart');
}

if (window.location.pathname === '/reports/transactions') {
    require('./reports/transactions');
}

if (window.location.pathname === '/search') {
    require('./search/search');
}

$(function() {
    // Generally available account selector
    $('#jump_to_account').on('change', function() {
        if (this.value == '') {
            return false;
        }
        window.location.href = route('account.history', { account: this.value });
    });

    // Generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function() {
        return confirm('Are you sure to abandon this form?');
    });
});

require('./bootstrap');

if (   window.location.pathname === '/account/summary'
    || window.location.pathname === '/') {
    require('./account/summary');
}

if (window.location.pathname === '/account-group') {
    require('./account-group/index');
}
if (window.location.pathname === '/account') {
    require('./account/index');
}
if (/^\/account\/history\/\d+/.test(window.location.pathname)) {
    require('./account/history');
}
if (window.location.pathname === '/categories') {
    require('./categories/index');
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

if (window.location.pathname === '/payees') {
    require('./payees/index');
}
if (window.location.pathname === '/tag') {
    require('./tags/index');
}
if (   window.location.pathname === '/transactions/standard/create'
    || /^\/transactions\/standard\/\d+\/(edit|clone|enter)/.test(window.location.pathname)) {

    require('./transactions/standard');
}

if (   window.location.pathname === '/transactions/investment/create'
    || /^\/transactions\/investment\/\d+\/(edit|clone|enter)/.test(window.location.pathname)) {

    require('./transactions/investment');
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

$( function () {
    // Generally available account selector
    document.getElementById('jump_to_account').addEventListener('change', function() {
        if (this.value == '') {
            return false;
        }
        window.location.href = route('account.history', { account: this.value });
    });

    // Generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function(e) {
        return confirm('Are you sure to abandon this form?');
    });
});


// Helper functions
// TODO: find appropriate place for these
Number.prototype.toLocalCurreny = function(currency, nonBreakingSpaces) {
    if (nonBreakingSpaces !== false) {
        nonBreakingSpaces = true;
    }

    var result = this.toLocaleString(
        'hu-HU',
        {
            style: 'currency',
            currency: currency.iso_code,
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

require('./bootstrap');

if (window.location.pathname === '/accountgroups') {
    require('./accountgroups/index');
}
if (window.location.pathname === '/accounts') {
    require('./accounts/index');
}
if (  /^\/accounts\/history\/\d+/.test(window.location.pathname)) {
    require('./accounts/history');
}
if (window.location.pathname === '/categories') {
    require('./categories/index');
}
if (window.location.pathname === '/currencies') {
    require('./currencies/index');
}
if (  /^\/currencyrates\/\d+\/\d+/.test(window.location.pathname)) {
    require('./currencyrates/index');
}
if (window.location.pathname === '/investmentgroups') {
    require('./investmentgroups/index');
}
if (window.location.pathname === '/investments') {
    require('./investments/index');
}
if (/^\/investments\/summary/.test(window.location.pathname)) {
    require('./investments/summary');
}
if (window.location.pathname === '/payees') {
    require('./payees/index');
}
if (window.location.pathname === '/tags') {
    require('./tags/index');
}
if (   window.location.pathname === '/transactions/create/standard'
    || /^\/transactions\/\d+\/edit\/standard/.test(window.location.pathname)) {
    require('./transactions/formCommon');
    require('./transactions/formSchedule');
    require('./transactions/formStandard');
}

if (   window.location.pathname === '/transactions/create/investment'
    || /^\/transactions\/\d+\/edit\/investment/.test(window.location.pathname)) {
    require('./transactions/formCommon');
    require('./transactions/formSchedule');
    require('./transactions/formInvestment');
}

document.getElementById('jump_to_account').addEventListener('change', function() {
    if (this.value == '') {
        return false;
    }
    window.location.href = "/accounts/history/" + this.value;
});

$( function () {
    //generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function(e) {
        return confirm('Are you sure to abandon this form?');
    });
});

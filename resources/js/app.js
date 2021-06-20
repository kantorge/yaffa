require('./bootstrap');

if (window.location.pathname === '/account-group') {
    require('./account-group/index');
}
if (window.location.pathname === '/account') {
    require('./account/index');
}
if (  /^\/account\/history\/\d+/.test(window.location.pathname)) {
    require('./account/history');
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
if (   window.location.pathname === '/transactions/create/standard'
    || /^\/transactions\/\d+\/edit\/standard/.test(window.location.pathname)
    || /^\/transactions\/\d+\/clone\/standard/.test(window.location.pathname)
    || /^\/transactions\/\d+\/enter\/standard/.test(window.location.pathname)) {

    require('./transactions/vue'); //TODO: rename this file

    //require('./transactions/formCommon');
    //require('./transactions/formSchedule');
}

if (   window.location.pathname === '/transactions/create/investment'
    || /^\/transactions\/\d+\/edit\/investment/.test(window.location.pathname)
    || /^\/transactions\/\d+\/clone\/investment/.test(window.location.pathname)) {
    require('./transactions/formCommon');
    require('./transactions/formSchedule');
    require('./transactions/formInvestment');
}

document.getElementById('jump_to_account').addEventListener('change', function() {
    if (this.value == '') {
        return false;
    }
    //TODO: get path from route
    window.location.href = "/account/history/" + this.value;
});

$( function () {
    //generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function(e) {
        return confirm('Are you sure to abandon this form?');
    });
});

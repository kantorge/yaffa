require('./bootstrap');

Date.prototype.datePart = function () {
    var d = new Date(this);
    d.setHours(0, 0, 0, 0);
    return d;
}

Date.prototype.isoDateString = function () {
    return this.toISOString().split('T')[0];
}

// Function to create a new date in UTC
// TODO: where to put this function instead of global scope?
window.todayInUTC = function () {
    let date = new Date();
    return new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0, 0, 0));
}

if (window.location.pathname === '/') {
    require('./dashboard');
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
if (/^\/account-entity\/\d+/.test(window.location.pathname)) {
    require('./account/show');
}

if (   /^\/account-entity\/(create|\d+\/edit)/.test(window.location.pathname)
    && /type=payee/.test(window.location.search)) {
    require('./payees/form');
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
if (window.location.pathname === '/investment/timeline') {
    require('./investment/timeline');
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
    || /^\/transactions\/standard\/\d+\/(edit|clone|enter|replace)/.test(window.location.pathname)) {

    require('./transactions/standard');
}

if (   window.location.pathname === '/transactions/investment/create'
    || /^\/transactions\/investment\/\d+\/(edit|clone|enter|replace)/.test(window.location.pathname)) {

    require('./transactions/investment');
}

if (/^\/transactions\/standard\/\d+\/(show)/.test(window.location.pathname)) {
    require('./transactions/show');
}

if (window.location.pathname === '/reports/schedule') {
    require('./reports/schedule');
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

if (window.location.pathname === '/import/csv') {
    require('./import/csv');
}

if (window.location.pathname === '/register') {
    require('./auth/register');
}

// Notifications
require('./notifications');

$(function() {
    // Generally available account selector
    $('#jump_to_account').on('change', function() {
        if (this.value == '') {
            return false;
        }
        window.location.href = route('account-entity.show', { account_entity: this.value });
    });

    // Generally available cancel button with confirmation
    $(".cancel.confirm-needed").on("click", function() {
        return confirm(__('Are you sure to abandon this form?'));
    });
});

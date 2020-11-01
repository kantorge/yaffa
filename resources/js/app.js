require('./bootstrap');

if (window.location.pathname === '/accountgroups') {
    require('./accountgroups/index');
}
if (window.location.pathname === '/accounts') {
    require('./accounts/index');
}
if (window.location.pathname === '/categories') {
    require('./categories/index');
}
if (window.location.pathname === '/currencies') {
    require('./currencies/index');
}
if (window.location.pathname === '/investmentgroups') {
    require('./investmentgroups/index');
}
if (window.location.pathname === '/investments') {
    require('./investments/index');
}
if (window.location.pathname === '/payees') {
    require('./payees/index');
}
if (window.location.pathname === '/tags') {
    require('./tags/index');
}
if (   window.location.pathname === '/transactions/create'
    || /^\/transactions\/\d+\/edit/.test(window.location.pathname)) {
    require('./transactions/form');
}
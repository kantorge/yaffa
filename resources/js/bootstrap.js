/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {
    window.$ = window.jQuery = require('jquery');

    require('bootstrap');
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Get CSRF Token from meta tag
window.csrfToken = $('meta[name="csrf-token"]').attr('content');

if (window.csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

import * as coreui from '@coreui/coreui';
window.coreui = coreui;

// Custom translation function
window.__ = function (key, replace) {
    var translation = window.YAFFA.translations[key] ? window.YAFFA.translations[key] : key;

    for (const [key, value] of Object.entries(replace || {})) {
        translation = translation.replace(':' + key, value);
    }

    return translation;
  };

window.$ = window.jQuery = require('jquery');
require('bootstrap')

// Get CSRF Token from meta tag
window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Axios
window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (window.csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// CoreUI
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

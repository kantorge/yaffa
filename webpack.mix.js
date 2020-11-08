const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js([
        'resources/js/app.js',
        'vendor/almasaeed2010/adminlte/plugins/bootstrap/js/bootstrap.bundle.js',  //TODO: ezt miért kell külön hívni?
    ], 'public/js')
    .extract([
        'datatables.net',
        'datatables.net-bs4',
        'jquery',
        'mathjs',
        'select2'
    ], 'public/js/vendor.js')
    .sass('resources/sass/app.scss', 'public/css')
    .webpackConfig({
        externals: function (context, request, callback) {
            if (/xlsx|canvg|pdfmake/.test(request)) {
              return callback(null, 'commonjs ' + request);
            }
            callback();
          },
    });
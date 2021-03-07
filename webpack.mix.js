const mix = require('laravel-mix');

mix.js([
        'resources/js/app.js',
        'vendor/almasaeed2010/adminlte/bower_components/bootstrap/dist/js/bootstrap.min.js',
    ], 'public/js')
    .extract([
        'datatables.net',
        'datatables.net-bs',
        'jquery',
        'mathjs',
        'moment',
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

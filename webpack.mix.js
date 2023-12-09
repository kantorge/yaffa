const mix = require('laravel-mix');
const webpack = require('webpack')

mix.js([
        'resources/js/app.js',
    ], 'public/js')
    .vue({ version: 3 })
    .extract([
        'amcharts4',
        'jquery',
        'jquery-csv',
        'json-rules-engine',
        'jstree',
        'mathjs',
        'rrule',
        'select2',
    ], 'public/js/vendor.js')
    .sass('resources/sass/app.scss', 'public/css')
    .webpackConfig({
        externals: function (_context, request, callback) {
            if (/xlsx|canvg|pdfmake/.test(request)) {
              return callback(null, 'commonjs ' + request);
            }
            callback();
        },
        plugins: [
            new webpack.DefinePlugin({
              __VUE_OPTIONS_API__: true,
              __VUE_PROD_DEVTOOLS__: false,
            }),
          ],
    });

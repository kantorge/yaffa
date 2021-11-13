const mix = require('laravel-mix');
const webpack = require('webpack')

mix.js([
        'resources/js/app.js',
        'vendor/almasaeed2010/adminlte/bower_components/bootstrap/dist/js/bootstrap.min.js',
    ], 'public/js')
    .vue({ version: 3 })
    .extract([
        'datatables.net',
        'datatables.net-bs',
        'jquery',
        'mathjs',
        'moment',
        'select2'
    ], 'public/js/vendor.js')
    .sass('resources/sass/app.scss', 'public/css')
    .sourceMaps()
    .webpackConfig({
        externals: function (context, request, callback) {
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

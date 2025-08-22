const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

/*mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        require('postcss-import'),
        require('tailwindcss'),
    ]);

if (mix.inProduction()) {
    mix.version();
}*/

mix
/* CSS */
/*.sass('resources/sass/main.scss', 'public/css/dashmix.css')
.sass('resources/sass/dashmix/themes/xeco.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xinspire.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xmodern.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xsmooth.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xwork.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xdream.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xpro.scss', 'public/css/themes/')
.sass('resources/sass/dashmix/themes/xplay.scss', 'public/css/themes/')*/

/* JS */
//.js('resources/js/app.js', 'public/js/laravel.app.js')
//.js('resources/js/dashmix/app.js', 'public/js/dashmix.app.js')

/* Page JS */
//.js('resources/js/pages/tables_datatables.js', 'public/js/pages/tables_datatables.js')

// BACK
//.js('resources/js/back/ag-input-field.js', 'public/js/ag-input-field.js').vue()

.js('resources/js/front/cart/app.js', 'public/js/cart.js').vue()
//.js('resources/js/front/filter/app.js', 'public/js/filter.js').vue()
//.js('resources/js/back/ag-order-products.js', 'public/js/components/ag-order-products.js').vue()

/* Tools */
/*.browserSync('http://127.0.0.1:8000/')
.disableNotifications()

/!* Options *!/
.options({
    processCssUrls: false
})*/;

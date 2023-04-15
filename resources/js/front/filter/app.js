/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

import Vue from "vue";
window.Vue = Vue;

/*import Vuex from 'vuex';
window.Vuex = Vuex;
Vue.use(Vuex);

import store from './../cart/store';*/

import VueRouter from 'vue-router'
Vue.use(VueRouter)

const router = new VueRouter({
    mode: 'history',
    /*base: location.origin,
    routes: [
        // dynamic segments start with a colon
        { path: '/', component: Filter, name: 'home' }
    ]*/
});

Vue.component('filter-view', require('./components/Filter/Filter').default);
Vue.component('products-view', require('./components/ProductsList/ProductsList').default);
Vue.component('pagination', require('./components/Pagination/LaravelVuePagination').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const filter = new Vue({
    router,
    /*store: new Vuex.Store(store)*/
}).$mount('#filter-app');


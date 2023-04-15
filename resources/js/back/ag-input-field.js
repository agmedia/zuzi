
const BASE_PATH = window.location.origin;
const API_PATH = BASE_PATH + '/api/v2/';

window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = API_PATH;

import Vue from "vue";
window.Vue = Vue;

Vue.component('ag-input-field', require('./components/InputField/InputField').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#ag-table-with-input-fields',
});

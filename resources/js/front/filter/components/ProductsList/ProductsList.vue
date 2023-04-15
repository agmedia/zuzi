<template>
    <section class="col-lg-8">
        <!-- Toolbar-->
        <div class="d-flex justify-content-center justify-content-sm-between align-items-center pt-2 pb-4 pb-sm-5">
            <div class="d-flex flex-wrap">
                <div class="dropdown me-2 d-sm-none"><a class="btn btn-primary dropdown-toggle collapsed" href="#shop-sidebar" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-filter-alt"></i></a></div>
                <div class="d-flex align-items-center flex-nowrap me-3 me-sm-4 pb-3">
                    <label class="text-light opacity-75 text-nowrap fs-sm me-2 d-none d-sm-block" for="sorting"></label>
                    <select class="form-select" v-model="sorting">
                        <option value="">Sortiraj</option>
                        <option value="novi">Najnovije</option>
                        <option value="price_up">Najmanja cijena</option>
                        <option value="price_down">Najveća cijena</option>
                        <option value="naziv_up">A - Ž</option>
                        <option value="naziv_down">Ž - A</option>
                    </select>
                </div>
            </div>
            <div class="d-flex pb-3"><span class="fs-sm text-light btn btn-primary btn-sm text-nowrap ms-2 d-none d-sm-block">Ukupno {{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }} artikala</span></div>
        </div>

        <!-- Products grid-->
        <div class="row mx-n2 mb-3" v-if="products.total">
            <div class="col-md-4 col-6 px-2 mb-4" v-for="product in products.data">
                <div class="card product-card-alt">
                    <span class="badge rounded-pill bg-primary mt-3 ms-1 badge-shadow" v-if="product.special">-{{ ($store.state.service.getDiscountAmount(product.price, product.special)) }}%</span>
                    <div class="product-thumb">
                        <div class="product-card-actions">
                            <a class="btn btn-light btn-icon btn-shadow fs-base mx-2" :href="origin + product.url"><i class="ci-eye"></i></a>
                            <button type="button" class="btn btn-light btn-icon btn-shadow fs-base mx-2" v-on:click="add(product.id)"><i class="ci-cart"></i></button>
<!--                            <add-to-cart-btn-simple :id="(product.id).toString()"></add-to-cart-btn-simple>-->
                        </div>
                        <a class="product-thumb-overlay" :href="origin + product.url"></a>
                        <img load="lazy" :src="product.image.replace('.webp', '-thumb.webp')" width="250" height="300" :alt="product.name">
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex flex-wrap justify-content-between align-items-start pb-2">
                            <div class="text-muted fs-xs me-1">
                                <a class="product-meta fw-medium" :href="product.author ? (origin + product.author.url) : '#'">{{ product.author ? product.author.title : '' }}</a>
                            </div>

                        </div>
                        <h3 class="product-title fs-sm mb-0"><a :href="origin + product.url">{{ product.name }}</a></h3>
                        <div class="d-flex flex-wrap justify-content-between align-items-center" v-if="product.category_string">
                            <div class="fs-sm me-2"><i class="ci-book text-muted" style="font-size: 11px;"></i> <span v-html="product.category_string"></span></div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center price-box mt-2">
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="product.special" style="text-decoration: line-through;">{{ product.main_price_text }}</div>
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="product.special">{{ product.main_special_text }}</div>
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="!product.special">{{ product.main_price_text }}</div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center price-box mt-2" v-if="product.secondary_price">
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="product.special" style="text-decoration: line-through;">{{ product.secondary_price_text }}</div>
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="product.special">{{ product.secondary_special_text }}</div>
                            <div class="bg-faded-accent text-accent fs-sm rounded-1 py-1 px-2" v-if="!product.special">{{ product.secondary_price_text }}</div>
                        </div>
                    </div>
                </div>
                <hr class="d-sm-none">
            </div>
        </div>

        <pagination :data="products" align="center" :show-disabled="true" :limit="4" @pagination-change-page="getProductsPage"></pagination>

        <div class="row" v-if="!products_loaded">
            <div class="col-md-12 d-flex justify-content-center mt-4">
                <div class="spinner-border text-muted opacity-75" role="status" style="width: 9rem; height: 9rem;"></div>
            </div>
            <div class="col-md-12 d-flex justify-content-center mt-4">
                <p class="fs-3 fw-lighter opacity-50">Učitavanje knjiga...</p>
            </div>
        </div>

        <div class="col-md-12 d-flex justify-content-center mt-4" v-if="products.total">
            <p class="fs-sm">Prikazano
                <span class="font-weight-bolder mx-1">{{ products.from ? Number(products.from).toLocaleString('hr-HR') : 0 }}</span> do
                <span class="font-weight-bolder mx-1">{{ products.to ? Number(products.to).toLocaleString('hr-HR') : 0 }}</span> od
                <span class="font-weight-bold mx-1">{{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }}</span> {{ hr_total }}
            </p>
        </div>

        <div class="col-md-12 px-2 mb-4" v-if="products_loaded && search_zero_result">
            <h2>Nema rezultata pretrage</h2>
            <p> Vaša pretraga za  <mark>{{ search_query }}</mark> pronašla je 0 rezultata.</p>
            <h4 class="h5">Savjeti i smjernica</h4>
            <ul class="list-style">
                <li>Dvaput provjerite pravopis.</li>
                <li>Ograničite pretragu na samo jedan ili dva pojma.</li>
                <li>Budite manje precizni u terminologiji. Koristeći više općenitih termina prije ćete doći do sličnih i povezanih proizvoda.</li>
            </ul>
            <hr class="d-sm-none">
        </div>

        <div class="col-md-12 px-2 mb-4" v-if="products_loaded && navigation_zero_result">
            <h2>Trenutno nema proizvoda</h2>
            <p> Pogledajte u nekoj drugoj kategoriji ili probajte sa tražilicom :-)</p>
            <hr class="d-sm-none">
        </div>

        <hr class="my-3">
    </section>
</template>

<script>
    export default {
        name: 'ProductsList',
        props: {
            ids: String,
            group: String,
            cat: String,
            subcat: String,
            author: String,
            publisher: String,
        },
        //
        data() {
            return {
                products: {},
                autor: '',
                nakladnik: '',
                start: '',
                end: '',
                sorting: '',
                search_query: '',
                page: 1,
                origin: location.origin + '/',
                hr_total: 'rezultata',
                products_loaded: false,
                search_zero_result: false,
                navigation_zero_result: false,
            }
        },
        //
        watch: {
            sorting(value) {
                this.setQueryParam('sort', value);
            },
            $route(params) {
                this.checkQuery(params);
            }
        },
        //
        mounted() {
            this.checkQuery(this.$route);

            /*console.log('twindow.AGSettings')
            console.log(window.AGSettings)*/
        },

        methods: {
            /**
             *
             */
            getProducts() {
                this.search_zero_result = false;
                this.navigation_zero_result = false;
                this.products_loaded = false;
                let params = this.setParams();

                axios.post('filter/getProducts', { params }).then(response => {
                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();

                    if (params.pojam != '' && !this.products.total) {
                        this.search_zero_result = true;
                    }

                    if (params.pojam == '' && !this.products.total) {
                        this.navigation_zero_result = true;
                    }
                });
            },

            /**
             *
             * @param page
             */
            getProductsPage(page = 1) {
                this.products_loaded = false;
                this.page = page;
                this.setQueryParam('page', page);

                let params = this.setParams();
                window.scrollTo({top: 0, behavior: 'smooth'});

                axios.post('filter/getProducts?page=' + page, { params }).then(response => {
                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();
                });
            },

            /**
             *
             * @param type
             * @param value
             */
            setQueryParam(type, value) {
                this.closeFilter();
                this.$router.push({query: this.resolveQuery()}).catch(()=>{});

                if (value == '' || value == 1) {
                    this.$router.push({query: this.resolveQuery()}).catch(()=>{});
                }
            },

            /**
             *
             * @return {{}}
             */
            resolveQuery() {
                let params = {
                    start: this.start,
                    end: this.end,
                    autor: this.autor,
                    nakladnik: this.nakladnik,
                    sort: this.sorting,
                    pojam: this.search_query,
                    page: this.page
                };

                return Object.entries(params).reduce((acc, [key, val]) => {
                    if (!val) return acc
                    return { ...acc, [key]: val }
                }, {});
            },

            /**
             *
             * @param params
             */
            checkQuery(params) {
                this.start = params.query.start ? params.query.start : '';
                this.end = params.query.end ? params.query.end : '';
                this.autor = params.query.autor ? params.query.autor : '';
                this.nakladnik = params.query.nakladnik ? params.query.nakladnik : '';
                this.page = params.query.page ? params.query.page : '';
                this.sorting = params.query.sort ? params.query.sort : '';
                this.search_query = params.query.pojam ? params.query.pojam : '';

                if (this.page != '') {
                    this.getProductsPage(this.page);
                } else {
                    this.getProducts();
                }
            },

            /**
             *
             * @return {{cat: String, start: string, pojam: string, subcat: String, end: string, sort: string, nakladnik: string, autor: string, group: String}}
             */
            setParams() {
                let params = {
                    ids: this.ids,
                    group: this.group,
                    cat: this.cat,
                    subcat: this.subcat,
                    autor: this.autor,
                    nakladnik: this.nakladnik,
                    start: this.start,
                    end: this.end,
                    sort: this.sorting,
                    pojam: this.search_query
                };

                if (this.author != '') {
                    params.autor = this.author;
                }
                if (this.publisher != '') {
                    params.nakladnik = this.publisher;
                }

                return params;
            },


            checkSpecials() {
                let now = new Date();

                for (let i = 0; i < this.products.data.length; i++) {
                    if (this.products.data[i].main_price <= this.products.data[i].main_special) {
                        this.products.data[i].special = false;
                    }
                }
            },

            /**
             *
             */
            checkHrTotal() {
                this.hr_total = 'rezultata';

                if ((this.products.total).toString().slice(-1) == '1') {
                    this.hr_total = 'rezultat';
                }
            },

            /**
             *
             * @param id
             */
            add(id) {
                this.$store.dispatch('addToCart', {
                    id: id,
                    quantity: 1
                })
            },

            /**
             *
             */
            closeFilter() {
                $('#shop-sidebar').removeClass('collapse show');
            }
        }
    };
</script>

<style>
</style>

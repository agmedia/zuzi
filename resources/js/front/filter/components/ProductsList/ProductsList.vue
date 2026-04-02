<template>
    <section class="col">
        <!-- Toolbar-->
        <div class="catalog-toolbar pt-2 pb-4 pb-sm-2 mb-3">
            <div class="catalog-toolbar__desktop d-none d-xl-flex align-items-center">
                <div class="catalog-toolbar__filters">
                    <div class="catalog-toolbar__filter-scroll">
                        <select v-if="facetConditions.length" class="form-select catalog-toolbar__select" v-model="condition" aria-label="Filtriraj po stanju" @change="applyToolbarFilters">
                            <option value="">Stanje</option>
                            <option v-for="option in facetConditions" :key="'desktop-condition-' + option" :value="option">{{ option }}</option>
                        </select>

                        <select v-if="facetBindings.length" class="form-select catalog-toolbar__select" v-model="binding" aria-label="Filtriraj po uvezu" @change="applyToolbarFilters">
                            <option value="">Uvez</option>
                            <option v-for="option in facetBindings" :key="'desktop-binding-' + option" :value="option">{{ option }}</option>
                        </select>

                        <select v-if="facetLetters.length" class="form-select catalog-toolbar__select" v-model="letter" aria-label="Filtriraj po pismu" @change="applyToolbarFilters">
                            <option value="">Pismo</option>
                            <option v-for="option in facetLetters" :key="'desktop-letter-' + option" :value="option">{{ option }}</option>
                        </select>
                    </div>

                    <div
                        v-if="author === '' && facetAuthors.length"
                        ref="desktopAuthorDropdown"
                        class="catalog-toolbar__author-select"
                    >
                        <button
                            class="catalog-toolbar__author-trigger"
                            type="button"
                            :aria-expanded="activeAuthorDropdown === 'desktop'"
                            aria-label="Filtriraj po autoru"
                            @click="toggleAuthorDropdown('desktop')"
                        >
                            <span class="catalog-toolbar__author-trigger-label">{{ selectedAuthorTitle }}</span>
                            <span class="catalog-toolbar__author-trigger-icon"></span>
                        </button>

                        <div v-if="activeAuthorDropdown === 'desktop'" class="catalog-toolbar__author-panel">
                            <input
                                ref="desktopAuthorSearch"
                                v-model="authorSearchTerm"
                                type="search"
                                class="form-control catalog-toolbar__author-search"
                                placeholder="Pretraži autora"
                            >

                            <div class="catalog-toolbar__author-options">
                                <button
                                    class="catalog-toolbar__author-option"
                                    :class="{ 'catalog-toolbar__author-option--active': autor === '' }"
                                    type="button"
                                    @click="selectAuthor('')"
                                >
                                    Svi autori
                                </button>

                                <button
                                    v-for="(authorItem, authorIndex) in filteredFacetAuthors"
                                    :key="'desktop-author-' + authorItem.id + '-' + authorIndex"
                                    class="catalog-toolbar__author-option"
                                    :class="{ 'catalog-toolbar__author-option--active': autor === authorItem.slug }"
                                    type="button"
                                    @click="selectAuthor(authorItem.slug)"
                                >
                                    {{ authorItem.title }}
                                </button>

                                <div v-if="!filteredFacetAuthors.length" class="catalog-toolbar__author-empty">
                                    Nema autora za taj pojam.
                                </div>
                            </div>
                        </div>
                    </div>

                    <button v-if="hasActiveToolbarFilters" class="btn btn-outline-secondary catalog-toolbar__clear" type="button" @click="clearToolbarFilters">
                        <span class="catalog-toolbar__clear-icon" aria-hidden="true">×</span>
                        <span>Očisti filtere</span>
                    </button>
                </div>

                <div class="catalog-toolbar__actions">
                    <select class="form-select catalog-toolbar__select catalog-toolbar__select--sort" v-model="sorting" aria-label="Sortiraj proizvode">
                        <option value="">Sortiraj</option>
                        <option value="novi">Najnovije</option>
                        <option value="price_up">Najmanja cijena</option>
                        <option value="price_down">Najveća cijena</option>
                        <option value="naziv_up">A - Ž</option>
                        <option value="naziv_down">Ž - A</option>
                    </select>

                    <span class="catalog-toolbar__summary">
                        Ukupno {{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }} artikala
                    </span>
                </div>
            </div>

            <div class="catalog-toolbar__mobile d-xl-none">
                <div class="catalog-toolbar__mobile-top d-flex flex-wrap align-items-center">


                    <button v-if="hasToolbarFilters" class="btn btn-outline-secondary catalog-toolbar__toggle" type="button" @click="toggleMobileFilters">
                        Filteri
                    </button>

                    <button v-if="hasActiveToolbarFilters" class="btn btn-outline-secondary catalog-toolbar__clear catalog-toolbar__toggle catalog-toolbar__toggle--clear" type="button" @click="clearToolbarFilters">
                        <span class="catalog-toolbar__clear-icon" aria-hidden="true">×</span>
                        <span>Očisti</span>
                    </button>

                    <select class="form-select catalog-toolbar__select catalog-toolbar__select--sort" v-model="sorting" aria-label="Sortiraj proizvode">
                        <option value="">Sortiraj</option>
                        <option value="novi">Najnovije</option>
                        <option value="price_up">Najmanja cijena</option>
                        <option value="price_down">Najveća cijena</option>
                        <option value="naziv_up">A - Ž</option>
                        <option value="naziv_down">Ž - A</option>
                    </select>

                    <span class="catalog-toolbar__summary">
                        Ukupno {{ products.total ? Number(products.total).toLocaleString('hr-HR') : 0 }} artikala
                    </span>
                </div>

                <transition name="filter-drawer">
                    <div v-if="showMobileFilters && hasToolbarFilters" class="catalog-toolbar__drawer">
                        <button class="catalog-toolbar__drawer-backdrop" type="button" aria-label="Zatvori filtere" @click="closeMobileFilters"></button>

                        <aside class="catalog-toolbar__drawer-panel">
                            <div class="catalog-toolbar__drawer-header">
                                <div>
                                    <p class="catalog-toolbar__drawer-eyebrow">Pregled filtera</p>
                                    <h3 class="catalog-toolbar__drawer-title">Filteri</h3>
                                </div>

                                <button class="catalog-toolbar__drawer-close" type="button" aria-label="Zatvori filtere" @click="closeMobileFilters">
                                    <span></span>
                                    <span></span>
                                </button>
                            </div>

                            <div class="catalog-toolbar__drawer-body">
                                <div class="catalog-toolbar__mobile-grid">
                                    <select v-if="facetConditions.length" class="form-select catalog-toolbar__select" v-model="condition" aria-label="Filtriraj po stanju" @change="applyToolbarFilters">
                                        <option value="">Stanje</option>
                                        <option v-for="option in facetConditions" :key="'mobile-condition-' + option" :value="option">{{ option }}</option>
                                    </select>

                                    <select v-if="facetBindings.length" class="form-select catalog-toolbar__select" v-model="binding" aria-label="Filtriraj po uvezu" @change="applyToolbarFilters">
                                        <option value="">Uvez</option>
                                        <option v-for="option in facetBindings" :key="'mobile-binding-' + option" :value="option">{{ option }}</option>
                                    </select>

                                    <select v-if="facetLetters.length" class="form-select catalog-toolbar__select" v-model="letter" aria-label="Filtriraj po pismu" @change="applyToolbarFilters">
                                        <option value="">Pismo</option>
                                        <option v-for="option in facetLetters" :key="'mobile-letter-' + option" :value="option">{{ option }}</option>
                                    </select>

                                    <div
                                        v-if="author === '' && facetAuthors.length"
                                        ref="mobileAuthorDropdown"
                                        class="catalog-toolbar__author-select catalog-toolbar__author-select--mobile"
                                    >
                                        <button
                                            class="catalog-toolbar__author-trigger"
                                            type="button"
                                            :aria-expanded="activeAuthorDropdown === 'mobile'"
                                            aria-label="Filtriraj po autoru"
                                            @click="toggleAuthorDropdown('mobile')"
                                        >
                                            <span class="catalog-toolbar__author-trigger-label">{{ selectedAuthorTitle }}</span>
                                            <span class="catalog-toolbar__author-trigger-icon"></span>
                                        </button>

                                        <div v-if="activeAuthorDropdown === 'mobile'" class="catalog-toolbar__author-panel catalog-toolbar__author-panel--mobile">
                                            <input
                                                ref="mobileAuthorSearch"
                                                v-model="authorSearchTerm"
                                                type="search"
                                                class="form-control catalog-toolbar__author-search"
                                                placeholder="Pretraži autora"
                                            >

                                            <div class="catalog-toolbar__author-options">
                                                <button
                                                    class="catalog-toolbar__author-option"
                                                    :class="{ 'catalog-toolbar__author-option--active': autor === '' }"
                                                    type="button"
                                                    @click="selectAuthor('')"
                                                >
                                                    Svi autori
                                                </button>

                                                <button
                                                    v-for="(authorItem, authorIndex) in filteredFacetAuthors"
                                                    :key="'mobile-author-' + authorItem.id + '-' + authorIndex"
                                                    class="catalog-toolbar__author-option"
                                                    :class="{ 'catalog-toolbar__author-option--active': autor === authorItem.slug }"
                                                    type="button"
                                                    @click="selectAuthor(authorItem.slug)"
                                                >
                                                    {{ authorItem.title }}
                                                </button>

                                                <div v-if="!filteredFacetAuthors.length" class="catalog-toolbar__author-empty">
                                                    Nema autora za taj pojam.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button v-if="hasActiveToolbarFilters" class="btn btn-outline-secondary catalog-toolbar__clear catalog-toolbar__clear--mobile" type="button" @click="clearToolbarFilters">
                                    <span class="catalog-toolbar__clear-icon" aria-hidden="true">×</span>
                                    <span>Očisti filtere</span>
                                </button>
                            </div>
                        </aside>
                    </div>
                </transition>
            </div>
        </div>
        <!-- Products grid-->
        <div class=" row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 row-cols-xxxl-6 mb-3 px-2" v-if="products.total">
            <div class="col px-2 mb-4 d-flex align-items-stretch " v-for="product in products.data">
                <div class="card product-card catalog-grid-card shadow pb-2 position-relative">
                    <div style="position:absolute; top:.75rem; left:.75rem; right:.75rem; z-index:5; display:flex; justify-content:space-between; align-items:flex-start;">
                        <span class="badge rounded-pill bg-primary badge-shadow" style="position:static;" v-if="product.special">-{{ ($store.state.service.getDiscountAmount(product.price, product.special)) }}%</span>
                        <span v-else></span>
                        <span
                            class="badge rounded-pill badge-shadow"
                            v-if="product.delivery_24h"
                            style="position:static; background:#e50077; color:#fff;"
                        ><i class="ci-delivery me-1"></i>24 sata</span>
                    </div>
                       <a class="card-img-top catalog-grid-card__image-link d-block overflow-hidden text-center" :href="origin + product.url">
                           <img class="catalog-grid-card__image" loading="lazy" :src="product.image.replace('.webp', '-thumb.webp')" width="250" height="300" :alt="product.name">
                    </a>
                    <div class="card-body catalog-grid-card__body py-2">
                        <h3 class="product-title catalog-grid-card__title fs-sm mt-2 mb-1"><a :href="origin + product.url">{{ product.name }}</a></h3>
                        <div class="catalog-grid-card__price-group">
                            <div class="product-price" >
                                <span class="text-muted p-0" v-if="product.special" ><small >NC 30 dana: {{ product.main_price_text }} </small></span>
                            </div>
                            <div class="product-price">
                                <span class="text-primary" v-if="product.special">{{ product.main_special_text }}</span>
                             </div>
                            <div class="product-price">
                                <span class="text-primary" v-if="!product.special">{{ product.main_price_text }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="product-floating-btn">
                        <button class="btn btn-primary btn-shadow btn-sm" :disabled="product.disabled" v-on:click="add(product.id, product.quantity)" type="button">+<i class="ci-cart fs-base ms-1"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <pagination :data="products" align="center" :show-disabled="true" :limit="4" @pagination-change-page="getProductsPage"></pagination>

        <div class="row" v-if="!products_loaded">
            <div class="col-md-12 d-flex justify-content-center mt-4">
                <div class="spinner-border text-primary opacity-75" role="status" style="width: 9rem; height: 9rem;"></div>
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
                activeAuthorDropdown: '',
                authorSearchTerm: '',
                facetAuthors: [],
                autor: '',
                nakladnik: '',
                start: '',
                end: '',
                condition: '',
                binding: '',
                letter: '',
                facetConditions: [],
                facetBindings: [],
                facetLetters: [],
                productsRequestToken: 0,
                sorting: '',
                search_query: '',
                page: 1,
                showMobileFilters: false,
                toolbarRequestToken: 0,
                bodyOverflowValue: '',
                isIPhone: false,
                origin: location.origin + '/',
                hr_total: 'rezultata',
                products_loaded: false,
                defaultRobots: '',
                search_zero_result: false,
                navigation_zero_result: false,
            }
        },
        computed: {
            normalizedFacetAuthors() {
                return this.facetAuthors.filter((authorItem) => {
                    return authorItem && authorItem.title && authorItem.title.toString().trim() !== '';
                });
            },
            filteredFacetAuthors() {
                let search = this.normalizeAuthorSearchValue(this.authorSearchTerm);
                let authors = this.normalizedFacetAuthors;

                if (!search) {
                    return authors;
                }

                return authors.filter((authorItem) => {
                    return this.normalizeAuthorSearchValue(authorItem.title).includes(search);
                });
            },
            hasToolbarFilters() {
                return this.facetConditions.length || this.facetBindings.length || this.facetLetters.length || (this.author === '' && this.normalizedFacetAuthors.length);
            },
            hasActiveToolbarFilters() {
                return this.autor || this.nakladnik || this.start || this.end || this.condition || this.binding || this.letter;
            },
            selectedAuthorTitle() {
                if (!this.autor) {
                    return 'Autor';
                }

                let selectedAuthor = this.normalizedFacetAuthors.find((authorItem) => authorItem.slug === this.autor);

                return selectedAuthor ? selectedAuthor.title : 'Autor';
            }
        },
        //
        watch: {
            sorting(value) {
                this.page = '';
                this.setQueryParam('sort', value);
            },
            $route(params) {
                this.checkQuery(params);
                this.getToolbarFilters();
            }
        },
        //
        mounted() {
            const robotsMeta = document.querySelector("meta[name='robots']");
            this.defaultRobots = robotsMeta ? robotsMeta.getAttribute('content') || '' : '';
            this.isIPhone = this.detectIPhone();

            document.addEventListener('click', this.handleDocumentClick);
            document.addEventListener('keydown', this.handleDocumentKeydown);

            this.checkQuery(this.$route);
            this.getToolbarFilters();

            /*console.log('twindow.AGSettings')
            console.log(window.AGSettings)*/
        },
        beforeDestroy() {
            document.removeEventListener('click', this.handleDocumentClick);
            document.removeEventListener('keydown', this.handleDocumentKeydown);
            this.setBodyScrollLock(false);
        },

        methods: {
            /**
             *
             */
            getToolbarFilters() {
                const requestToken = ++this.toolbarRequestToken;
                let params = this.setParams();

                axios.post('filter/getToolbarFilters', { params }).then(response => {
                    if (requestToken !== this.toolbarRequestToken) {
                        return;
                    }

                    this.facetAuthors = response.data.authors || [];
                    this.facetConditions = response.data.conditions || [];
                    this.facetBindings = response.data.bindings || [];
                    this.facetLetters = response.data.letters || [];

                    if (!this.hasToolbarFilters) {
                        this.closeMobileFilters();
                    }
                });
            },

            /**
             *
             */
            getProducts() {
                const requestToken = ++this.productsRequestToken;
                this.search_zero_result = false;
                this.navigation_zero_result = false;
                this.products_loaded = false;
                let params = this.setParams();

                axios.post('filter/getProducts', { params }).then(response => {
                    if (requestToken !== this.productsRequestToken) {
                        return;
                    }

                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();
                    this.checkAvailables();

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
            getProductsPage(page = 1, syncRoute = true) {
                this.page = page;

                if (syncRoute) {
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    this.setQueryParam('page', page);
                    return;
                }

                const requestToken = ++this.productsRequestToken;
                this.products_loaded = false;
                let params = this.setParams();

                axios.post('filter/getProducts?page=' + page, { params }).then(response => {
                    if (requestToken !== this.productsRequestToken) {
                        return;
                    }

                    this.products_loaded = true;
                    this.products = response.data;
                    this.checkHrTotal();
                    this.checkSpecials();
                    this.checkAvailables();
                });
            },

            /**
             *
             */
            applyToolbarFilters() {
                this.page = '';
                this.setQueryParam('filters', '');
            },

            /**
             *
             */
            clearToolbarFilters() {
                this.page = '';
                this.autor = '';
                this.authorSearchTerm = '';
                this.nakladnik = '';
                this.start = '';
                this.end = '';
                this.condition = '';
                this.binding = '';
                this.letter = '';
                this.closeAuthorDropdown();
                this.closeMobileFilters();
                this.setQueryParam('clear', '');
            },

            /**
             *
             * @param slug
             */
            selectAuthor(slug = '') {
                if (this.autor === slug) {
                    this.closeAuthorDropdown();
                    return;
                }

                this.autor = slug;
                this.authorSearchTerm = '';
                this.closeAuthorDropdown();
                this.applyToolbarFilters();
            },

            /**
             *
             * @param type
             * @param value
             */
            setQueryParam(type, value) {
                this.closeFilter();
                this.$router.push({query: this.resolveQuery()}).catch(()=>{});
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
                    condition: this.condition,
                    binding: this.binding,
                    letter: this.letter,
                    sort: this.sorting,
                    pojam: this.search_query,
                    page: this.page
                };

                this.checkNoFollowQuery(params);

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
                this.condition = params.query.condition ? params.query.condition : '';
                this.binding = params.query.binding ? params.query.binding : '';
                this.letter = params.query.letter ? params.query.letter : '';
                this.page = params.query.page ? params.query.page : '';
                this.sorting = params.query.sort ? params.query.sort : '';
                this.search_query = params.query.pojam ? params.query.pojam : '';

                if (this.page != '') {
                    this.getProductsPage(this.page, false);
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
                    condition: this.condition,
                    binding: this.binding,
                    letter: this.letter,
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

            /**
             *
             * @param params
             */
            checkNoFollowQuery(params) {
                let robotsMeta = document.querySelector("meta[name='robots']");

                if (!robotsMeta) {
                    $('head').append('<meta name="robots" content="">');
                    robotsMeta = document.querySelector("meta[name='robots']");
                }

                if (!robotsMeta) {
                    return;
                }

                if (params.nakladnik || params.autor || params.start || params.end || params.condition || params.binding || params.letter) {
                    robotsMeta.setAttribute('content', 'noindex,nofollow');
                } else if (this.defaultRobots) {
                    robotsMeta.setAttribute('content', this.defaultRobots);
                } else {
                    robotsMeta.remove();
                }
            },

            /**
             *
             */
            checkSpecials() {
                let now = new Date();

                for (let i = 0; i < this.products.data.length; i++) {
                    if (Number(this.products.data[i].main_price) <= Number(this.products.data[i].main_special)) {
                        this.products.data[i].special = false;
                    }
                }
            },

            /**
             *
             */
            checkAvailables() {
                let cart = this.$store.state.storage.getCart();

                if (cart) {

                    for (let i = 0; i < this.products.data.length; i++) {
                        this.products.data[i].disabled = false;

                        for (const key in cart.items) {
                            if (this.products.data[i].id == cart.items[key].id) {
                                if (this.products.data[i].quantity <= cart.items[key].quantity) {
                                    this.products.data[i].disabled = true;
                                }
                            }
                        }
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
            add(id, product_quantity) {
                let cart = this.$store.state.storage.getCart();
                if (cart) {
                    for (const key in cart.items) {
                        if (id == cart.items[key].id) {
                            if (product_quantity <= cart.items[key].quantity) {
                                return window.ToastWarning.fire('Nažalost nema dovoljnih količina artikla..!');
                            }
                        }
                    }
                }

                this.$store.dispatch('addToCart', {
                    id: id,
                    quantity: 1
                });
            },

            /**
             *
             */
            closeFilter() {
                $('#shop-sidebar').removeClass('collapse show');
                this.closeAuthorDropdown();
                this.closeMobileFilters();
            },

            /**
             *
             */
            toggleMobileFilters() {
                if (this.showMobileFilters) {
                    this.closeMobileFilters();
                    return;
                }

                this.openMobileFilters();
            },

            /**
             *
             */
            openMobileFilters() {
                this.closeAuthorDropdown();
                this.showMobileFilters = true;
                this.setBodyScrollLock(true);
            },

            /**
             *
             */
            closeMobileFilters() {
                this.showMobileFilters = false;
                this.closeAuthorDropdown();
                this.setBodyScrollLock(false);
            },

            /**
             *
             * @param target
             */
            toggleAuthorDropdown(target) {
                if (this.activeAuthorDropdown === target) {
                    this.closeAuthorDropdown();
                    return;
                }

                this.activeAuthorDropdown = target;
                this.authorSearchTerm = '';

                this.$nextTick(() => {
                    if (this.shouldSkipAuthorSearchAutofocus(target)) {
                        return;
                    }

                    let refName = target === 'mobile' ? 'mobileAuthorSearch' : 'desktopAuthorSearch';

                    if (this.$refs[refName]) {
                        this.$refs[refName].focus();
                    }
                });
            },

            /**
             *
             */
            closeAuthorDropdown() {
                this.activeAuthorDropdown = '';
                this.authorSearchTerm = '';
            },

            /**
             *
             * @param event
             */
            handleDocumentClick(event) {
                if (!this.activeAuthorDropdown) {
                    return;
                }

                let refName = this.activeAuthorDropdown === 'mobile' ? 'mobileAuthorDropdown' : 'desktopAuthorDropdown';
                let dropdown = this.$refs[refName];

                if (dropdown && !dropdown.contains(event.target)) {
                    this.closeAuthorDropdown();
                }
            },

            /**
             *
             * @param event
             */
            handleDocumentKeydown(event) {
                if (event.key === 'Escape') {
                    this.closeMobileFilters();
                    this.closeAuthorDropdown();
                }
            },

            /**
             *
             * @param locked
             */
            setBodyScrollLock(locked) {
                if (typeof document === 'undefined') {
                    return;
                }

                if (locked) {
                    this.bodyOverflowValue = document.body.style.overflow || '';
                    document.body.style.overflow = 'hidden';
                    return;
                }

                document.body.style.overflow = this.bodyOverflowValue;
            },

            /**
             *
             * @return {boolean}
             */
            detectIPhone() {
                if (typeof navigator === 'undefined') {
                    return false;
                }

                return /iPhone/i.test(navigator.userAgent || '') || navigator.platform === 'iPhone';
            },

            /**
             *
             * @param target
             * @return {boolean}
             */
            shouldSkipAuthorSearchAutofocus(target) {
                return target === 'mobile' && this.isIPhone;
            },

            /**
             *
             * @param value
             * @return {string}
             */
            normalizeAuthorSearchValue(value) {
                return (value || '')
                    .toString()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase();
            }
        }
    };
</script>

<style>
.catalog-toolbar__desktop {
    gap: 1rem;
    min-width: 0;
}

.catalog-toolbar__filters {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1 1 auto;
    min-width: 0;
}

.catalog-toolbar__filter-scroll {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 0 1 auto;
    min-width: 0;
    overflow-x: auto;
    padding-bottom: 0.15rem;
}

.catalog-toolbar__filter-scroll::-webkit-scrollbar {
    height: 6px;
}

.catalog-toolbar__filter-scroll::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.45);
    border-radius: 999px;
}

.catalog-toolbar__select {
    min-width: 150px;
    min-height: 46px;
    padding: 0.7rem 2.85rem 0.7rem 1rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    background-color: #fff;
    background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6L8 10L12 6' stroke='%235f6c82' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"),
        linear-gradient(180deg, #ffffff 0%, #ffffff 100%);
    background-repeat: no-repeat, no-repeat;
    background-position: right 1rem center, center;
    background-size: 0.95rem, 100% 100%;
    box-shadow: none;
    color: #334155;
    font-weight: 500;
    appearance: none;
    -webkit-appearance: none;
    transition: border-color 0.2s ease;
}

.catalog-toolbar__select:hover,
.catalog-toolbar__select:focus {
    border-color: #bfcada;
    box-shadow: none;
}

.catalog-toolbar__select:focus {
    outline: none;
}

.catalog-toolbar__select--sort {
    min-width: 170px;
}

.catalog-toolbar__author-select {
    position: relative;
    flex: 0 0 150px;
    min-width: 150px;
    max-width: 150px;
}

.catalog-toolbar__author-select--mobile {
    flex: 1 1 auto;
    min-width: 100%;
    max-width: none;
}

.catalog-toolbar__author-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-height: 46px;
    padding: 0.7rem 0.95rem 0.7rem 1rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    background: #fff;
    box-shadow: none;
    color: #334155;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25;
    text-align: left;
}

.catalog-toolbar__author-trigger:focus,
.catalog-toolbar__author-trigger:hover {
    border-color: #bfcada;
    box-shadow: none;
    outline: none;
}

.catalog-toolbar__author-trigger-label {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-right: 0.75rem;
}

.catalog-toolbar__author-trigger-icon {
    flex: 0 0 auto;
    width: 0.95rem;
    height: 0.95rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6L8 10L12 6' stroke='%235f6c82' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
}

.catalog-toolbar__author-panel {
    position: absolute;
    top: calc(100% + 0.35rem);
    left: 0;
    z-index: 30;
    width: 280px;
    max-width: min(280px, calc(100vw - 2rem));
    padding: 0.65rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    background: #fff;
    box-shadow: none;
}

.catalog-toolbar__author-panel--mobile {
    position: static;
    width: 100%;
    max-width: none;
    margin-top: 0.35rem;
}

.catalog-toolbar__author-search {
    min-height: 38px;
    margin-bottom: 0.5rem;
    padding: 0.45rem 0.75rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    box-shadow: none !important;
    font-size: 0.875rem;
}

.catalog-toolbar__author-search:focus {
    border-color: #bfcada;
    box-shadow: none !important;
}

.catalog-toolbar__author-options {
    max-height: 260px;
    overflow-y: auto;
    padding-right: 0.15rem;
}

.catalog-toolbar__author-option {
    display: block;
    width: 100%;
    padding: 0.45rem 0.6rem;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: #334155;
    font-size: 0.875rem;
    line-height: 1.35;
    text-align: left;
}

.catalog-toolbar__author-option:hover,
.catalog-toolbar__author-option--active {
    background: #eef4ff;
    color: #1d4ed8;
}

.catalog-toolbar__author-empty {
    padding: 0.45rem 0.6rem;
    color: #64748b;
    font-size: 0.875rem;
}

.catalog-toolbar__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-shrink: 0;
    margin-left: auto;
}

.catalog-toolbar__clear,
.catalog-toolbar__toggle {
    min-height: 46px;
    padding-inline: 1rem;
    border-radius: 0.45rem;
    border-color: #d8e0ea;
    background: #fff;
    box-shadow: none;
    color: #334155;
    white-space: nowrap;
}

.catalog-toolbar__clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-color: #e50077;
    color: #e50077;
    font-weight: 500;
}

.catalog-toolbar__clear:hover,
.catalog-toolbar__clear:focus {
    border-color: #e50077;
    background: rgba(229, 0, 119, 0.05);
    color: #e50077;
    box-shadow: none;
}

.catalog-toolbar__clear-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1;
}

.catalog-toolbar__summary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0.45rem 1rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    background: #fff;
    box-shadow: none;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
}

.catalog-toolbar__mobile-top,
.catalog-toolbar__mobile-grid {
    gap: 0.75rem;
}

.catalog-toolbar__drawer {
    position: fixed;
    inset: 0;
    z-index: 1080;
}

.catalog-toolbar__drawer-backdrop {
    position: absolute;
    inset: 0;
    border: 0;
    background: rgba(15, 23, 42, 0.4);
}

.catalog-toolbar__drawer-panel {
    position: absolute;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    width: min(22rem, calc(100vw - 2rem));
    height: 100dvh;
    max-height: 100dvh;
    padding: 1rem;
    border-right: 1px solid #d8e0ea;
    background: #fff;
    overflow: hidden;
}

.catalog-toolbar__drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid #e2e8f0;
}

.catalog-toolbar__drawer-eyebrow {
    margin: 0 0 0.2rem;
    color: #64748b;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.catalog-toolbar__drawer-title {
    margin: 0;
    color: #0f172a;
    font-size: 1.1rem;
    font-weight: 700;
}

.catalog-toolbar__drawer-close {
    position: relative;
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    border: 1px solid #d8e0ea;
    border-radius: 0.45rem;
    background: #fff;
}

.catalog-toolbar__drawer-close span {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0.95rem;
    height: 1.5px;
    background: #334155;
}

.catalog-toolbar__drawer-close span:first-child {
    transform: translate(-50%, -50%) rotate(45deg);
}

.catalog-toolbar__drawer-close span:last-child {
    transform: translate(-50%, -50%) rotate(-45deg);
}

.catalog-toolbar__drawer-body {
    flex: 1 1 auto;
    min-height: 0;
    padding-top: 1rem;
    padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
    overflow-y: auto;
}

.catalog-toolbar__mobile-grid {
    display: grid;
    grid-template-columns: 1fr;
}

.catalog-toolbar__clear--mobile {
    margin-top: 0.75rem;
}

.filter-drawer-enter-active .catalog-toolbar__drawer-backdrop,
.filter-drawer-leave-active .catalog-toolbar__drawer-backdrop {
    transition: opacity 0.22s ease;
}

.filter-drawer-enter-active .catalog-toolbar__drawer-panel,
.filter-drawer-leave-active .catalog-toolbar__drawer-panel {
    transition: transform 0.24s ease;
}

.filter-drawer-enter .catalog-toolbar__drawer-backdrop,
.filter-drawer-leave-to .catalog-toolbar__drawer-backdrop {
    opacity: 0;
}

.filter-drawer-enter .catalog-toolbar__drawer-panel,
.filter-drawer-leave-to .catalog-toolbar__drawer-panel {
    transform: translateX(-100%);
}

@media (max-width: 1199.98px) {
    .catalog-toolbar__mobile-top {
        align-items: stretch;
    }

    .catalog-toolbar__toggle,
    .catalog-toolbar__toggle--clear,
    .catalog-toolbar__select--sort,
    .catalog-toolbar__summary {
        flex: 1 1 calc(50% - 0.375rem);
    }
}

@media (max-width: 767.98px) {
    .catalog-toolbar__toggle,
    .catalog-toolbar__toggle--clear,
    .catalog-toolbar__select--sort,
    .catalog-toolbar__summary,
    .catalog-toolbar__select,
    .catalog-toolbar__author-select {
        width: 100%;
        flex-basis: 100%;
    }

    .catalog-toolbar__drawer-panel {
        padding: 0.9rem;
    }
}

@media (min-width: 1400px) {
    .catalog-grid-card {
        width: 100%;
        height: 100%;
    }

    .catalog-grid-card__image-link {
        display: flex !important;
        align-items: center;
        justify-content: center;
        min-height: 250px;
        padding: 0.5rem;
        background-color: #fff;
    }

    .catalog-grid-card__image {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 250px;
        margin: 0 auto;
    }

    .catalog-grid-card__body {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 6.5rem;
    }

    h3.catalog-grid-card__title {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.25;
        min-height: 3.2rem;
    }

    .catalog-grid-card__price-group {
        margin-top: auto;
    }
}

@media (min-width: 1600px) {
    .catalog-grid-card__image {
        max-height: 260px;
    }

    .catalog-grid-card__body {
        min-height: 6rem;
    }
}
</style>

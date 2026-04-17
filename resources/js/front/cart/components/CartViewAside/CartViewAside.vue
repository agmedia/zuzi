<template>
    <div>
        <div class=" rounded-3  p-4" v-if="route == 'kosarica'" style="border: 1px dashed #e3e9ef;background-color: #fff !important;">
            <div class="py-2 px-xl-2" v-cloak>
                <div class="text-center mb-2 pb-2">
                    <h2 class="h6 mb-3 pb-1">Ukupno</h2>
                    <h3 class="fw-bold text-primary">{{ $store.state.service.formatMainPrice($store.state.cart.total) }}</h3>
                    <h4 class="fs-sm" v-if="$store.state.cart.secondary_price">{{ $store.state.service.formatSecondaryPrice($store.state.cart.total) }}</h4>
                </div>
                <a class="btn btn-primary btn-shadow d-block w-100 mt-4" :href="checkouturl">NASTAVI NA NAPLATU <i class="ci-arrow-right fs-sm"></i></a>
            </div>
        </div>

        <div class="rounded-3 p-4 mt-3 cart-bookmarker-promo" v-if="route == 'kosarica' && showBookmarkerPromo" style="border: 1px solid #dae1e7;background-color: #fff !important;">
            <div class="py-2 px-xl-2">
                <div class="cart-bookmarker-promo__eyebrow mb-0">
                    <i class="ci-bookmark me-2"></i>Trebate bookmarker?
                </div>
                <button type="button" class="btn btn-outline-primary btn-shadow w-100 mt-2" @click="scrollToBookmarkers">
                    Pokaži bookmarkere
                </button>
            </div>
        </div>

        <div class="rounded-3 p-4 ms-lg-auto" v-if="route == 'naplata'" style="border: 1px dashed #e3e9ef;background-color: #fff !important;">
            <div class="py-2 px-xl-2">
                <div class="widget mb-3">
                    <h2 class="widget-title text-center mb-2">Sažetak narudžbe</h2>

                    <div class="d-flex align-items-center pb-2 border-bottom" v-for="item in $store.state.cart.items">
                        <a v-if="!isGiftWrap(item)" class="d-block flex-shrink-0" :href="base_path + item.attributes.path"><img :src="item.associatedModel.image" :alt="item.name" width="64"></a>
                        <a v-else class="gift-wrap-thumb gift-wrap-thumb--sm d-inline-flex flex-shrink-0" :href="base_path + item.attributes.path" :aria-label="item.name">
                            <span class="gift-wrap-thumb__icon" aria-hidden="true"></span>
                        </a>
                        <div class="ps-2">
                            <h6 class="widget-product-title"><a :href="base_path + item.attributes.path">{{ item.name }}</a></h6>
                            <div class="widget-product-meta"><span class="text-primary me-2">{{ Object.keys(item.conditions).length ? item.associatedModel.main_special_text : item.associatedModel.main_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                            <div class="widget-product-meta"><span class="text-muted me-2" v-if="hasSecondaryCurrency && item.associatedModel.secondary_price_text">{{ Object.keys(item.conditions).length ? item.associatedModel.secondary_special_text : item.associatedModel.secondary_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                            <div class="widget-product-meta text-muted" v-if="isGiftVoucher(item)">
                                {{ giftVoucherData(item).recipient_email }}
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="list-unstyled fs-sm pb-2 border-bottom">
                    <li class="d-flex justify-content-between align-items-center"><span class="me-2">Ukupno:</span><span class="text-end">{{ $store.state.service.formatMainPrice($store.state.cart.subtotal) }}</span></li>
                    <li v-if="hasSecondaryCurrency" class="d-flex justify-content-between align-items-center">
                        <span class="me-2"></span><span class="text-end">{{ $store.state.service.formatSecondaryPrice($store.state.cart.subtotal) }}</span>
                    </li>
                    <div v-for="condition in visibleDetailConditions" :key="`checkout-${condition.name}-${condition.type}-${condition.value}`">
                        <li class="d-flex justify-content-between align-items-center"><span class="me-2">{{ condition.name }}</span><span class="text-end">{{ $store.state.service.formatMainPrice(condition.value) }}</span></li>
                        <li v-if="hasSecondaryCurrency" class="d-flex justify-content-between align-items-center"><span class="me-2"></span><span class="text-end">{{ $store.state.service.formatSecondaryPrice(condition.value) }}</span></li>
                    </div>
                </ul>
                <h3 class="fw-bold text-primary text-center my-2">{{ $store.state.service.formatMainPrice($store.state.cart.total) }}</h3>
                <h4 v-if="hasSecondaryCurrency" class="fs-sm text-center my-2">{{ $store.state.service.formatSecondaryPrice($store.state.cart.total) }}</h4>
                <p class="small text-center mt-0 mb-0">PDV uračunat u cijeni</p>
            </div>
        </div>

        <div class="rounded-3 p-4 ms-lg-auto" v-if="route == 'pregled'" style="border: 1px dashed #e3e9ef;background-color: #fff !important;">
            <div class="py-2 px-xl-2">
                <div class="widget mb-3">
                    <h2 class="widget-title text-center">Sažetak narudžbe</h2>
                </div>
                <ul class="list-unstyled fs-sm pb-2 border-bottom">
                    <li class="d-flex justify-content-between align-items-center"><span class="me-2">Ukupno:</span><span class="text-end">{{ $store.state.service.formatMainPrice($store.state.cart.subtotal) }}</span></li>
                    <li v-if="hasSecondaryCurrency" class="d-flex justify-content-between align-items-center">
                        <span class="me-2"></span><span class="text-end">{{ $store.state.service.formatSecondaryPrice($store.state.cart.subtotal) }}</span>
                    </li>
                    <div v-for="condition in visibleDetailConditions" :key="`review-${condition.name}-${condition.type}-${condition.value}`">
                        <li class="d-flex justify-content-between align-items-center"><span class="me-2">{{ condition.name }}</span><span class="text-end">{{ $store.state.service.formatMainPrice(condition.value) }}</span></li>
                        <li v-if="hasSecondaryCurrency" class="d-flex justify-content-between align-items-center"><span class="me-2"></span><span class="text-end">{{ $store.state.service.formatSecondaryPrice(condition.value) }}</span></li>
                    </div>
                </ul>
                <h3 class="fw-bold text-primary text-center my-2">{{ $store.state.service.formatMainPrice($store.state.cart.total) }}</h3>
                <h4 v-if="hasSecondaryCurrency" class="fs-sm text-center my-2">{{ $store.state.service.formatSecondaryPrice($store.state.cart.total) }}</h4>
                <p class="small text-center mt-0 mb-0">PDV uračunat u cijeni</p>
            </div>
        </div>

        <div class="rounded-3 p-4 mt-3" v-if="!hasGiftVoucher && (route == 'kosarica' || route == 'naplata')" style="border: 1px dashed #e3e9ef;background-color: #fff !important;">
            <div class="py-2 px-xl-2" v-cloak>
                <button
                    type="button"
                    class="coupon-toggle btn btn-link text-decoration-none w-100 px-0"
                    @click="toggleCouponPanel"
                    :aria-expanded="showCouponPanel ? 'true' : 'false'"
                >
                    <span class="coupon-toggle__content text-start">
                        <strong class="d-block text-dark">Imate kod?</strong>
                        <small class="text-muted">{{ hasActiveCoupon ? 'Kod je spremljen u košarici. Novi unos zamijenit će postojeći.' : 'Kupon ili poklon-bon.' }}</small>
                    </span>
                    <span class="coupon-toggle__action text-primary">
                        {{ showCouponPanel ? 'Sakrij' : 'Otvori' }}
                    </span>
                </button>

                <div v-show="showCouponPanel" class="mt-3">
                    <div class="form-group mb-3">
                        <label class="form-label">Kod za popust ili poklon-bon</label>
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control"
                                v-model="codeInput"
                                placeholder="Upišite kupon ili poklon-bon kod..."
                                autocomplete="off"
                                @keyup.enter="setCoupon"
                            >
                            <div class="input-group-append">
                                <button type="button" v-on:click="setCoupon" class="btn btn-outline-primary btn-shadow" :disabled="couponSubmitting">Primijeni</button>
                            </div>
                        </div>
                    </div>

                    <p class="small text-muted mb-0 mt-2">
                        Moguće je primijeniti jedan kod po narudžbi. Novi unos zamjenjuje postojeći.
                    </p>

                    <p v-if="hasActiveCoupon" class="small text-success mb-0 mt-2">
                        Aktivan kod: {{ coupon }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-3 p-4 mt-3" v-if="!hasGiftVoucher && (has_loyalty && route == 'kosarica' || has_loyalty && route == 'naplata')" style="border: 1px dashed #e3e9ef;background-color: #fff !important;">
            <div class="py-2 px-xl-2" v-cloak>
                <button
                    type="button"
                    class="coupon-toggle btn btn-link text-decoration-none w-100 px-0"
                    @click="toggleLoyaltyPanel"
                    :aria-expanded="showLoyaltyPanel ? 'true' : 'false'"
                >
                    <span class="coupon-toggle__content text-start">
                        <strong class="d-block text-dark">Iskoristite Loyalty popust</strong>
                        <small class="text-muted">{{ hasActiveLoyaltySelection ? 'Loyalty popust je odabran za ovu narudžbu.' : 'Primijenite skupljene Loyalty bodove.' }}</small>
                    </span>
                    <span class="coupon-toggle__action text-primary">
                        {{ showLoyaltyPanel ? 'Sakrij' : 'Otvori' }}
                    </span>
                </button>

                <div v-show="showLoyaltyPanel" class="mt-3">
                    <div class="form-group mb-3">
                        <div class="form-check" v-if="$store.state.cart.has_loyalty >= 100">
                            <input class="form-check-input" type="radio" v-model="selected_loyalty" value="100">
                            <label class="form-check-label" for="ex-radio-2">100 = 5€ popust</label>
                        </div>
                        <div class="form-check" v-if="$store.state.cart.has_loyalty >= 200">
                            <input class="form-check-input" type="radio" v-model="selected_loyalty" value="200">
                            <label class="form-check-label" for="ex-radio-3">200 = 12€ popust</label>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" v-on:click="clearLoyalty" class="btn btn-outline-primary btn-shadow">Odbaci</button>
                        <button type="button" v-on:click="setLoyalty" class="btn btn-outline-primary btn-shadow">Primjeni</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

</template>

<script>
export default {
    props: {
        continueurl: String,
        checkouturl: String,
        buttons: {type: Boolean, default: true},
        route: String,
        bookmarkersTarget: {
            type: String,
            default: 'cart-bookmarkers'
        },
        showBookmarkerPromo: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            base_path: window.location.origin + '/',
            mobile: false,
            show_delete_btn: true,
            coupon: '',
            codeInput: '',
            couponSubmitting: false,
            showCouponPanel: false,
            showLoyaltyPanel: false,
            has_loyalty: false,
            selected_loyalty: 0,
            tax: 0,
        }
    },
    computed: {
        hasActiveCoupon() {
            return String(this.coupon || '').trim() !== '' && String(this.coupon || '').trim() !== 'null';
        },
        hasGiftVoucher() {
            return !!this.$store.state.cart.has_gift_voucher;
        },
        hasActiveLoyaltySelection() {
            return Number(this.selected_loyalty || 0) > 0;
        },
        hasSecondaryCurrency() {
            const settings = this.$store.state.settings || {};
            const currencyList = Array.isArray(settings['currency.list']) ? settings['currency.list'] : [];

            return currencyList.some((item) => item && item.status !== false && !item.main);
        },
        visibleDetailConditions() {
            const conditions = Array.isArray(this.$store.state.cart.detail_con) ? this.$store.state.cart.detail_con : [];

            return conditions.filter((condition) => {
                if (!condition) {
                    return false;
                }

                if (String(condition.name || '').toLowerCase() !== 'loyalty') {
                    return true;
                }

                return Number(condition.value || 0) !== 0;
            });
        }
    },
    mounted() {
        if (window.innerWidth < 800) {
            this.mobile = true;
        }

        this.checkIfEmpty();
        this.checkLoyalty();
        //this.setCoupon();
    },

    methods: {
        isGiftVoucher(item) {
            return item?.attributes?.item_type === 'gift_voucher';
        },

        isGiftWrap(item) {
            return item?.attributes?.item_type === 'gift_wrap';
        },

        giftVoucherData(item) {
            return item?.attributes?.gift_voucher || {};
        },

        /**
         *
         * @param item
         */
        updateCart(item) {
            this.$store.dispatch('updateCart', item);
        },

        /**
         *
         * @param item
         */
        removeFromCart(item) {
            this.$store.dispatch('removeFromCart', item);
        },

        /**
         *
         * @param qty
         * @returns {number|*}
         * @constructor
         */
        CheckQuantity(qty) {
            if (qty < 1) {
                return 1;
            }

            return qty;
        },

        /**
         *
         */
        checkIfEmpty() {
            let cart = this.$store.state.storage.getCart();

            if (!cart) {
                return;
            }

            // Check coupon
            if (cart && cart.coupon != '' && cart.coupon != 'null') {
                this.coupon = cart.coupon;
                this.showCouponPanel = true;
            }

            // Check loyalty
            if (cart.loyalty != '' && cart.loyalty != 'null') {
                this.selected_loyalty = cart.loyalty;
                this.showLoyaltyPanel = Number(cart.loyalty || 0) > 0;
            }

            if (cart && ! cart.count && window.location.pathname != '/kosarica') {
                window.location.href = '/kosarica';
            }
        },

        /**
         *
         */
        setCoupon() {
            let normalizedCoupon = String(this.codeInput || '').trim().toUpperCase();

            if (this.couponSubmitting) {
                return Promise.resolve(false);
            }

            if ( ! normalizedCoupon) {
                this.$store.state.service.returnError('Upišite kod za popust ili poklon-bon.');
                return Promise.resolve(false);
            }

            this.couponSubmitting = true;

            return this.checkCoupon(normalizedCoupon)
                .then((response) => {
                    this.coupon = String(this.$store.state.cart.coupon || '').trim();

                    if (response && response.success) {
                        if (this.coupon === normalizedCoupon) {
                            this.codeInput = '';
                        }

                        this.showCouponPanel = true;
                    }

                    return response;
                })
                .finally(() => {
                    this.couponSubmitting = false;
                });
        },

        toggleCouponPanel() {
            this.showCouponPanel = ! this.showCouponPanel;
        },

        toggleLoyaltyPanel() {
            this.showLoyaltyPanel = ! this.showLoyaltyPanel;
        },

        scrollToBookmarkers() {
            let target = document.getElementById(this.bookmarkersTarget);

            if (!target) {
                window.location.hash = this.bookmarkersTarget;
                return;
            }

            target.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start'
            });
        },

        setLoyalty() {
            let cart = this.$store.state.storage.getCart();

            cart.loyalty = this.selected_loyalty;
            this.showLoyaltyPanel = true;
            this.updateLoyalty();
        },

        clearLoyalty() {
            this.selected_loyalty = null;
            this.showLoyaltyPanel = false;
            this.updateLoyalty();
        },

        /**
         *
         */
        /**
         *
         */
        checkCoupon(coupon = this.coupon) {
            return this.$store.dispatch('checkCoupon', coupon);
        },


        /**
         *
         */
        updateLoyalty() {
            this.$store.dispatch('updateLoyalty', this.selected_loyalty);
        },

        /**
         *
         */
        checkLoyalty() {
            let cart = this.$store.state.storage.getCart();

            if (cart && cart.has_loyalty > 0) {
                this.has_loyalty = true;
            }
        }
    }
};
</script>


<style>
@font-face {
    font-family: "Font Awesome 5 Free";
    font-style: normal;
    font-weight: 900;
    font-display: block;
    src: url("/fonts/fontawesome/fa-solid-900.woff2") format("woff2"),
         url("/fonts/fontawesome/fa-solid-900.woff") format("woff");
}

.table th, .table td {
    padding: 0.75rem 0.45rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.empty th, .empty td {
    padding: 1rem !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}
.coupon-toggle {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.coupon-toggle__content {
    flex: 1 1 auto;
    min-width: 0;
}
.coupon-toggle__action {
    flex: 0 0 auto;
    white-space: nowrap;
    padding-top: 0.15rem;
}
.cart-bookmarker-promo {
    background:
        radial-gradient(circle at top right, rgba(229, 0, 119, 0.08), transparent 38%),
        linear-gradient(180deg, #fff 0%, #fff8fc 100%);
}
.cart-bookmarker-promo__eyebrow {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0.75rem;
    color: #e50077;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.cart-bookmarker-promo__title {
    margin-bottom: 0.65rem;
    color: #2b3445;
    font-size: 1.15rem;
    line-height: 1.3;
}
.cart-bookmarker-promo__text {
    color: #5f6c82;
    font-size: 0.95rem;
    line-height: 1.5;
}
.gift-wrap-thumb {
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: linear-gradient(180deg, #fff0f7 0%, #ffe0ef 100%);
    border: 1px solid rgba(229, 0, 119, 0.14);
    text-decoration: none;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}
.gift-wrap-thumb--sm {
    width: 64px;
    height: 64px;
}
.gift-wrap-thumb__icon::before {
    content: "\f06b";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    color: #e50077;
    font-size: 1.2rem;
}
</style>

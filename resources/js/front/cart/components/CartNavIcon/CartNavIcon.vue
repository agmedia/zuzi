<template>
    <div class="navbar-tool dropdown ms-1"><a class="navbar-tool-icon-box  dropdown-toggle" :href="carturl"><span class="navbar-tool-label">{{ $store.state.cart ? $store.state.cart.count : 0 }}</span><i class="navbar-tool-icon ci-bag"></i></a>
        <!-- Cart dropdown-->
        <div class="dropdown-menu dropdown-menu-end">
            <div class="widget widget-cart px-3 pt-2 pb-3" style="width: 24rem;" v-if="$store.state.cart.count">
                <div data-simplebar-auto-hide="false" v-for="item in $store.state.cart.items">
                    <div class="widget-cart-item pb-2 border-bottom">
                        <button class="btn-close text-danger" type="button" @click.prevent="removeFromCart(item)" aria-label="Remove"><span aria-hidden="true">&times;</span></button>
                        <div class="d-flex align-items-center">
                            <a v-if="!isGiftWrap(item)" class="d-block flex-shrink-0 pt-2" :href="base_path + item.attributes.path"><img :src="item.associatedModel.image" :alt="item.name" :title="item.name" style="width: 5rem;"></a>
                            <a v-else class="gift-wrap-thumb gift-wrap-thumb--nav d-inline-flex flex-shrink-0" :href="base_path + item.attributes.path" :aria-label="item.name">
                                <span class="gift-wrap-thumb__icon" aria-hidden="true"></span>
                            </a>
                            <div class="ps-2">
                                <h6 class="widget-product-title"><a :href="base_path + item.attributes.path">{{ item.name }}</a></h6>
                                <div class="widget-product-meta"><span class="text-primary me-2">{{ Object.keys(item.conditions).length ? item.associatedModel.main_special_text : item.associatedModel.main_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                                <div class="widget-product-meta"><span class="text-dark fs-sm me-2" v-if="item.associatedModel.secondary_price">{{ Object.keys(item.conditions).length ? item.associatedModel.secondary_special_text : item.associatedModel.secondary_price_text }}</span><span class="text-muted">x {{ item.quantity }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center py-3">
                    <div class="fs-sm me-2 py-2">
                        <span class="text-muted">Ukupno:</span><span class="text-primary fs-base ms-1">{{ $store.state.service.formatMainPrice($store.state.cart.total) }}</span>
                        <span v-if="$store.state.cart.secondary_price" class="text-muted">{{ $store.state.service.formatSecondaryPrice($store.state.cart.total) }}</span>
                    </div>

                </div><a class="btn btn-primary btn-sm d-block w-100" :href="carturl"><i class="ci-card me-2 fs-base align-middle"></i>Dovrši kupnju</a>
            </div>
            <div class="widget widget-cart px-3 pt-2 pb-3" style="width: 20rem;" v-else>
                <i class="fa fa-cart-arrow-down fa-2x" style="color: #aaaaaa"></i>
                <p>Vaša košarica je prazna!</p>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            carturl: String,
            checkouturl: String
        },
        //
        data() {
            return {
                base_path: window.location.origin + '/',
                success_path: window.location.origin + '/kosarica/success',
                mobile: false
            }
        },
        //
        mounted() {
            this.checkCart();

            if (window.location.pathname == '/kosarica/success') {
                this.$store.dispatch('flushCart');
            }

            if (window.innerWidth < 800) {
                this.mobile = true;
            }

            if (window.location.pathname == '/pregled') {
                window.setInterval(this.checkCart, 15000);
            }
        },
        //
        methods: {
            isGiftWrap(item) {
                return item?.attributes?.item_type === 'gift_wrap';
            },

            /**
             *
             */
            checkCart() {
                let kos = [];
                let cart = this.$store.state.storage.getCart();

                this.$store.dispatch('getSettings');

                if ( ! cart) {
                    return this.$store.dispatch('getCart');
                }

                Object.keys(cart.items).forEach(function(key) {
                    kos.push(cart.items[key].id)
                });

                this.$store.dispatch('checkCart', kos);
            },

            /**
             *
             * @param item
             */
            removeFromCart(item) {
                this.$store.dispatch('removeFromCart', item);
                //window.location.reload();
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

.gift-wrap-thumb {
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: linear-gradient(180deg, #fff0f7 0%, #ffe0ef 100%);
    border: 1px solid rgba(229, 0, 119, 0.14);
    text-decoration: none;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.gift-wrap-thumb--nav {
    width: 5rem;
    height: 5rem;
    margin-top: 0.5rem;
}

.gift-wrap-thumb__icon::before {
    content: "\f06b";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    color: #e50077;
    font-size: 1.5rem;
}
</style>

<template>
    <button class="btn btn-primary btn-shadow btn-sm" :disabled="disabled" @click="add()" type="button">+<i class="ci-cart fs-base ms-1"></i></button>
</template>

<script>
export default {
    props: {
        id: String,
        available: String
    },

    data() {
        return {
            has_in_cart: 0,
            disabled: false
        }
    },

    mounted() {
        let cart = this.$store.state.storage.getCart();



        if(cart) {
            for (const key in cart.items) {
                if (this.id == cart.items[key].id) {
                    this.has_in_cart = Number(cart.items[key].quantity) || 0;
                }
            }
        }

        this.checkAvailability();
    },

    methods: {
        add() {
            const available = Number(this.available) || 0;

            if (available && this.has_in_cart + 1 > available) {
                this.disabled = this.has_in_cart >= available;
                return;
            }

            const item = {
                id: this.id,
                quantity: 1
            };

            const action = this.has_in_cart ? 'updateCart' : 'addToCart';

            if (this.has_in_cart) {
                item.relative = true;
            }

            this.$store.dispatch(action, item).then((cart) => {
                if (cart) {
                    this.syncHasInCart(cart);
                }
            });
        },

        syncHasInCart(cart) {
            let quantity = 0;

            if (cart && cart.items) {
                Object.keys(cart.items).forEach((key) => {
                    if (String(this.id) === String(cart.items[key].id)) {
                        quantity = Number(cart.items[key].quantity) || 0;
                    }
                });
            }

            this.has_in_cart = quantity;
            this.checkAvailability();
        },

        checkAvailability() {
            const available = Number(this.available) || 0;

            this.disabled = available ? this.has_in_cart >= available : false;
        }
    }
};
</script>

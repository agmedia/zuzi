<template>
    <div class="cart d-flex flex-wrap align-items-center pt-2 pb-2 mb-3">




        <input class="form-control me-3 mb-1" type="number" v-model="quantity" min="1" :max="available" style="width: 5rem;">
        <button class="btn btn-primary btn-shadow me-3 mb-1 " @click="addToCart()"><i class=" ci-bag"></i> Dodaj u Košaricu</button>
    </div>
</template>

<script>
export default {
    props: {
        id: String,
        available: String
    },

    data() {
        return {
            quantity: 1,
            has_in_cart: false
        }
    },

    mounted() {
        let cart = this.$store.state.storage.getCart();

        for (const key in cart.items) {
            if (this.id == cart.items[key].id) {
                this.has_in_cart = true;
                this.quantity = cart.items[key].quantity;
            }
        }
    },

    methods: {
        add() {
            if (this.has_in_cart) {
                this.updateCart();
            } else {
                this.add();
            }
        },
        /**
         *
         */
        addToCart() {
            let item = {
                id: this.id,
                quantity: this.quantity
            }

            this.$store.dispatch('addToCart', item);
        },

        /**
         *
         */
        updateCart() {
            if (this.available != 'undefined' && this.quantity > this.available) {
                this.quantity = this.available;
            }

            let item = {
                id: this.id,
                quantity: this.quantity
            }

            this.$store.dispatch('updateCart', item);
        },
    }
};
</script>

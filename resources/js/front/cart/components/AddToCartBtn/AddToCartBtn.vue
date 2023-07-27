<template>
    <div class="cart mb-4 text-center text-lg-start">
        <input class="form-control" type="number" v-model="quantity" min="1" :max="available">
        <button class="btn btn-primary btn-shadow " @click="addToCart()"><i class=" ci-bag"></i> Dodaj u Košaricu</button>
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

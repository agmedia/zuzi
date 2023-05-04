<template>
    <div class="cart mb-5 d-flex align-items-center">
        <button class="btn btn-primary btn-shadow btn-sm" @click="addToCart()" type="button">+<i class="ci-cart fs-base ms-1"></i></button>

    </div>
</template>

<script>
export default {
    props: {
        id: String
    },

    data() {
        return {
            quantity: 1
        }
    },

    mounted() {},

    methods: {
        addToCart() {
            let item = {
                id: this.id,
                quantity: this.quantity
            }

            this.$store.dispatch('addToCart', item);
        }
    }
};
</script>

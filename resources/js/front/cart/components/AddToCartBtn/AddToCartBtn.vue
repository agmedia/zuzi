<template>
    <div class="cart mb-5 d-flex align-items-center">

        <button class="btn btn-primary btn-shadow d-block w-100" @click="addToCart()">Dodaj u Košaricu</button>
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

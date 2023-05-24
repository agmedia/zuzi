<template>
    <div class="cart mb-4 text-center text-lg-start">
        <button class="btn btn-primary btn-shadow " @click="addToCart()"><i class=" ci-bag"></i> Dodaj u Košaricu</button>
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

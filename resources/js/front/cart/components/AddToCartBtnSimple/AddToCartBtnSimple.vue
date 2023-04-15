<template>
    <button class="btn btn-light btn-icon btn-shadow fs-base mx-2" type="button" @click="addToCart()"><i class="ci-cart"></i></button>
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
        mounted() {
        },
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

<template>
    <div>
        <div v-if="target == 'price'">
            <a href="javascript:void(0)" v-if="product.special && ! view_input" v-on:click="viewField">
                <s>{{ formatPrice(product.price) }}</s><br>
                <strong>{{ formatPrice(product.special) }}</strong>
            </a>
            <strong v-if="! product.special && ! view_input" style="cursor: pointer;"><span v-on:click="viewField">{{ formatPrice(product.price) }}</span></strong>
            <div class="input-group" v-if="view_input">
                <input type="text" class="form-control" v-model="field_value" v-on:keyup.enter="updateField">
                <div class="input-group-append">
                    <button type="button" class="btn btn-alt-success" v-on:click="updateField"><i class="fa fa-save"></i></button>
                </div>
            </div>
        </div>

        <div v-if="target == 'year'">
            <span v-if="!view_input" style="cursor: pointer;" v-on:click="viewField">{{ product.year ? product.year : '...' }}</span>
            <div class="input-group" v-if="view_input">
                <input type="text" class="form-control" v-model="field_value" v-on:keyup.enter="updateField">
                <div class="input-group-append">
                    <button type="button" class="btn btn-alt-success" v-on:click="updateField"><i class="fa fa-save"></i></button>
                </div>
            </div>
        </div>
        <div v-if="target == 'dimensions'">
            <span v-if="!view_input" style="cursor: pointer;" v-on:click="viewField">{{ product.dimensions ? product.dimensions : '...' }}</span>
            <div class="input-group" v-if="view_input">
                <input type="text" class="form-control" v-model="field_value" v-on:keyup.enter="updateField">
                <div class="input-group-append">
                    <button type="button" class="btn btn-alt-success" v-on:click="updateField"><i class="fa fa-save"></i></button>
                </div>
            </div>
        </div>
        <div v-if="target == 'polica'">
            <span v-if="!view_input" style="cursor: pointer;" v-on:click="viewField">{{ product.polica ? product.polica : '...' }}</span>
            <div class="input-group" v-if="view_input">
                <input type="text" class="form-control" v-model="field_value" v-on:keyup.enter="updateField">
                <div class="input-group-append">
                    <button type="button" class="btn btn-alt-success" v-on:click="updateField"><i class="fa fa-save"></i></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            item: {
                type: String,
                required: true,
                default: ''
            },
            target: {
                type: String,
                required: true,
                default: ''
            },
        },
        //
        data() {
            return {
                product: JSON.parse(this.item),
                view_input: false,
                field_value: 0,
            }
        },
        //
        mounted() {
            this.setFieldValue();
        },
        //
        methods: {
            /**
             *
             */
            setFieldValue() {
                if (this.target == 'price') {
                    this.field_value = Number(this.product.price).toFixed(2);
                    this.checkSpecials();
                }
                if (this.target == 'year') {
                    this.field_value = this.product.year;
                }

                if (this.target == 'dimensions') {
                    this.field_value = this.product.dimensions;
                }

                if (this.target == 'polica') {
                    this.field_value = this.product.polica;
                }
            },
            /**
             *
             * @param price
             * @return {string}
             */
            formatPrice(price) {
                return Number(price).toLocaleString('hr-HR', {
                    style: 'currency',
                    //currencyDisplay: 'narrowSymbol',
                    currencyDisplay: 'symbol',
                    currency: 'EUR'
                });
            },
            /**
             *
             */
            viewField() {
                this.view_input = true;
            },
            /**
             *
             */
            checkSpecials() {
                let now = new Date();
                let from = new Date(this.product.special_from);
                let to = new Date(this.product.special_to);

                if (now > from && now < to) {
                } else {
                    this.product.special = null;
                }
            },
            /**
             *
             */
            updateField() {
                let product = {
                    item: this.product,
                    target: this.target,
                    new_value: this.field_value
                };
                let context = this;

                axios.post('products/update-item/single', { product }).then(response => {
                    if (response.data.success) {
                        successToast.fire();
                        this.view_input = false;

                        if (this.target == 'price') {
                            context.product.price = response.data.value_1;
                            context.product.special = response.data.value_2;
                        }
                        if (this.target == 'year') {
                            context.product.year = response.data.value_1;
                        }

                        if (this.target == 'dimensions') {
                            context.product.dimensions = response.data.value_1;
                        }

                        if (this.target == 'polica') {
                            context.product.polica = response.data.value_1;
                        }
                    }
                    //
                    if (response.data.error) {
                        this.view_input = false;
                    }
                });
            }
        }
    };
</script>
<style>

</style>

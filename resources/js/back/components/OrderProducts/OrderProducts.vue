<template>
    <div class="OrderProducts">
        <div class="row mb-4">
            <div class="col-sm-12 col-md-3 text-right"><label class="pt-2">Upišite Proizvod za Dodati</label></div>
            <div class="col-sm-12 col-md-9">
                <input type="text" v-model="query" @keyup="autoComplete" class="form-control">
                <div class="panel-footer" v-if="results.length">
                    <ul class="list-group agm">
                        <li class="list-group-item" v-for="result in results" @click="select(result)">
                            {{ result.name }} -  {{ result.sku }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="block black mt-50" v-if="items.length">
            <!--<div class="block-header block-header-default">
                Proizvodi
            </div>-->
            <div class="block-content-full">
                <table class="table table-hover table-vcenter">
                    <thead>
                    <tr class="bg-light">
                        <th class="text-center px-0" style="width: 3%;"></th>
                        <th class="text-center" style="width: 5%;">#</th>
                        <th>Ime</th>
                        <th class="text-center" style="width: 7%;">Kol.</th>
                        <th class="text-center" style="width: 12%;">Jed.Cijena</th>
                        <th class="text-center" style="width: 12%;">Iznos</th>
                        <th class="text-center" style="width: 12%;">Rabat</th>
                        <th class="text-center" style="width: 12%;">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(product, index) in items">
                        <td class="text-center px-0">
                            <i class="si si-trash text-danger float-right" style="margin-top: 2px; cursor: pointer;" @click="removeRow(index)"></i>
                        </td>
                        <td class="text-center">{{ index + 1 }}</td>
                        <td>{{ product.name }}</td>
                        <td class="text-center">
                            <div class="form-material" style="padding-top: 0;">
                                <input type="text" class="form-control py-0" style="height: 26px;" :value="product.quantity" @keyup="ChangeQty(product.id, $event)" @blur="Recalculate()">
                            </div>
                        </td>
                        <td class="text-right">
                            <input v-if="product.edit" type="text" class="form-control py-0" style="height: 26px;" :value="product.org_price" @keyup.enter="product.edit=false; $emit('update')" @blur="product.edit=false; ChangePrice(product.id, $event); $emit('update')">
                            <span v-else @click="product.edit=true;">{{ Number(product.org_price).toLocaleString(localization, currency_style) }}</span>
                        </td>
                        <td class="text-right">{{ Number(product.org_price * product.quantity).toLocaleString(localization, currency_style) }}</td>
                        <td class="text-right">
                            <input v-if="product.edit" type="text" class="form-control py-0" style="height: 26px;" :value="product.rabat" @keyup.enter="product.edit=false; $emit('update')" @blur="product.edit=false; ChangeRabat(product.id, $event); $emit('update')">
                            <span v-else @click="product.edit=true;">-{{ Number((product.rabat) * product.quantity).toLocaleString(localization, currency_style) }}</span>
                        </td>
                        <td class="text-right font-w600">{{ Number(product.total).toLocaleString(localization, currency_style) }}</td>
                    </tr>

                    <!-- Totals -->
                    <tr v-if="sums.length" v-for="(total, index) in sums">
                        <td colspan="6" class="text-right">{{ total.name }}:</td>
                        <td colspan="2" class="text-right font-w600">{{ Number(total.value).toLocaleString(localization, currency_style) }}</td>
                    </tr>

                    <input type="hidden" :value="JSON.stringify(items)" name="items">
                    <input type="hidden" :value="JSON.stringify(sums)" name="sums">

                    </tbody>
                </table>

            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        products: {
            type: String,
            required: false,
            default: []
        },
        totals: {
            type: String,
            required: false,
            default: []
        },
        products_autocomplete_url: {
            type: String,
            required: true
        }
    },
    //
    data() {
        return {
            products_local: [],
            totals_local: [],
            query: '',
            results: [],
            items: [],
            sums: [],
            selected_product: {},
            is_shipping: true,
            shipping_value: 30,
            is_action: false,
            action_value: 0,
            currency_style: {
                style: 'currency',
                currency: 'EUR'
            },
            localization: 'de-DE'
        }
    },
    //
    mounted() {
        if (this.products.length && this.totals.length) {
            this.products_local = JSON.parse(this.products)
            this.totals_local = JSON.parse(this.totals)
            this.Sort()
        }
    },
    //
    methods: {

        /**
         *
         * @constructor
         */
        Sort() {
            this.products_local.forEach((item) => {
                this.items.push({
                    id: item.product_id,
                    name: item.name,
                    quantity: item.quantity,
                    price: item.price,
                    org_price: item.org_price,
                    rabat: item.org_price - item.price,
                    total: item.total,
                    edit: false
                })
            })

            this.Recalculate()
        },

        /**
         *
         * @param selected
         */
        select(selected) {
            this.results = [];
            this.query = '';
            let price = selected.price;

            if (selected.actions) {
                if (selected.actions.price) {
                    price = selected.actions.price;
                }
                if (selected.actions.discount) {
                    price = selected.price - (selected.price * (selected.actions.discount / 100));
                }
            }

            this.items.push({
                id: selected.id,
                name: selected.name,
                quantity: 1,
                price: price,
                org_price: selected.price,
                rabat: selected.price - price,
                total: price,
                edit: false
            })

            this.Recalculate();
        },

        /**
         *
         * @param row
         * @param product
         */
        removeRow(row, product) {
            this.items.splice(row, 1);

            if (!this.items.length) {
                this.sums = [];
            }

            this.Recalculate();
        },

        /**
         *
         * @param id
         * @param event
         * @constructor
         */
        ChangeQty(id, event) {
            for (let i = 0; i < this.items.length; i++) {
                if (this.items[i].id == id) {
                    this.items[i].quantity = Number(event.target.value);
                    this.items[i].total = this.items[i].price * Number(event.target.value);
                }
            }
            this.Recalculate();
        },

        /**
         *
         * @param id
         * @param event
         * @constructor
         */
        ChangePrice(id, event) {
            for (let i = 0; i < this.items.length; i++) {
                if (this.items[i].id == id) {
                    let inserted_price = Number(event.target.value);

                    if (inserted_price > this.items[i].rabat) {
                        this.items[i].org_price = inserted_price;
                        this.items[i].price = Number(this.items[i].org_price) - this.items[i].rabat;
                        this.items[i].total = Number(this.items[i].price) * this.items[i].quantity;
                    }
                }
            }
            this.Recalculate();
        },

        /**
         *
         * @param id
         * @param event
         * @constructor
         */
        ChangeRabat(id, event) {
            for (let i = 0; i < this.items.length; i++) {
                if (this.items[i].id == id) {
                    let inserted_rabat = Number(event.target.value);

                    if (inserted_rabat < this.items[i].org_price) {
                        this.items[i].rabat = inserted_rabat;
                        this.items[i].price = Number(this.items[i].org_price) - inserted_rabat;
                        this.items[i].total = Number(this.items[i].price) * this.items[i].quantity;
                    }
                }
            }
            this.Recalculate();
        },

        /**
         *
         */
        Recalculate() {
            this.sums = [];
            let subtotal = 0;
            let total = 0;

            this.items.forEach((item) => {
                subtotal = subtotal + Number(item.total);
            });

            total = subtotal;

            this.totals_local.forEach((item) => {
                if (item.code == 'shipping' || item.code == 'payment') {
                    total += Number(item.value);
                }
            });

            this.totals_local.forEach((item) => {
                let value = Number(item.value);

                if (item.code == 'subtotal') {
                    value = subtotal;
                }

                if (item.code == 'total') {
                    value = total;
                }

                this.sums.push({
                    name: item.title,
                    value: value,
                    code: item.code
                });
            });
        },

        /**
         *
         */
        autoComplete() {
            this.results = []

            if (this.query.length > 2) {
                axios.get(this.products_autocomplete_url, {params: {query: this.query}}).then(response => {
                    this.results = response.data;
                })
            }
        }
    }
};
</script>

<style>
.panel-footer {
    width: 100%;
    position: absolute;
    z-index: 999;
    padding-right: 30px;
}

ul li agm {
    cursor: pointer;
}

ul li:hover agm {
    background-color: #eeeeee;
}
</style>

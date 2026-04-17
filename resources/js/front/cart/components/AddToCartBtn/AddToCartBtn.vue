<template>
    <div class="product-add-to-cart">
        <div class="product-add-to-cart__controls">
            <label class="product-add-to-cart__quantity" for="product-quantity-input">
                <span class="product-add-to-cart__label">Količina</span>

                <div class="quantity-stepper">
                    <button
                        type="button"
                        class="quantity-stepper__button"
                        aria-label="Smanji količinu"
                        :disabled="quantityNumber <= 1 || isBusy"
                        @click="decreaseQuantity"
                    >
                        -
                    </button>

                    <input
                        id="product-quantity-input"
                        class="quantity-stepper__input"
                        type="number"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        v-model="quantity"
                        min="1"
                        :max="maxInputValue"
                        :disabled="isBusy"
                        @blur="normalizeQuantity"
                    >

                    <button
                        type="button"
                        class="quantity-stepper__button"
                        aria-label="Povećaj količinu"
                        :disabled="incrementDisabled"
                        @click="increaseQuantity"
                    >
                        +
                    </button>
                </div>
            </label>

            <button type="button" class="btn btn-primary product-add-to-cart__cta" @click="add()" :disabled="disabled || isBusy">
                <i class="ci-bag"></i>
                {{ buttonLabel }}
            </button>
        </div>

        <p v-if="stockLimitMessage" class="product-add-to-cart__status product-add-to-cart__status--warning">{{ stockLimitMessage }}</p>
        <p v-else-if="has_in_cart" class="product-add-to-cart__status">U košarici: {{ has_in_cart }} kom.</p>

        <label v-if="giftWrapEnabled" class="gift-wrap-option" for="gift-wrap-checkbox">
            <span class="gift-wrap-option__checkbox">
                <input id="gift-wrap-checkbox" class="form-check-input" type="checkbox" v-model="giftWrap">
            </span>
            <span class="gift-wrap-option__copy">
                <span class="gift-wrap-option__title"><i class="fas fa-gift gift-wrap-option__icon" aria-hidden="true"></i> Dodaj zamatanje za poklon</span>
                <span class="gift-wrap-option__description">Ukrasni papir, mašna i priprema knjige za dar (opcionalno).</span>
                <span class="gift-wrap-option__price">+5,00 €</span>
            </span>
            <span class="gift-wrap-option__info" tabindex="0" aria-label="Više informacija o zamatanju">
                i
                <span class="gift-wrap-option__tooltip">Usluga uključuje ukrasni papir, mašnu i pripremu knjige za poklon.</span>
            </span>
        </label>
    </div>
</template>

<script>
export default {
    props: {
        id: String,
        available: String,
        trackStock: {
            type: String,
            default: 'true'
        },
        allowGiftWrap: {
            type: String,
            default: 'true'
        }
    },

    data() {
        return {
            quantity: 1,
            has_in_cart: 0,
            disabled: false,
            isBusy: false,
            giftWrap: false
        }
    },

    computed: {
        giftWrapEnabled() {
            return String(this.allowGiftWrap) !== 'false';
        },

        stockTrackingEnabled() {
            return String(this.trackStock) !== 'false';
        },

        availableCount() {
            return Number(this.available) || 0;
        },

        quantityNumber() {
            return Math.max(1, parseInt(this.quantity, 10) || 1);
        },

        currentQuantity() {
            return Number(this.has_in_cart) || 0;
        },

        remainingAvailable() {
            if (!this.stockTrackingEnabled) {
                return null;
            }

            return Math.max(this.availableCount - this.currentQuantity, 0);
        },

        maxSelectableQuantity() {
            if (this.remainingAvailable === null) {
                return null;
            }

            return this.remainingAvailable > 0 ? this.remainingAvailable : 1;
        },

        maxInputValue() {
            return this.maxSelectableQuantity || null;
        },

        incrementDisabled() {
            return this.isBusy || (this.remainingAvailable !== null && this.quantityNumber >= this.maxSelectableQuantity);
        },

        stockLimitMessage() {
            if (this.remainingAvailable === null) {
                return null;
            }

            if (this.remainingAvailable === 0) {
                return 'Dosegli ste maksimalnu dostupnu količinu za ovaj naslov.';
            }

            if (this.remainingAvailable <= 3) {
                return `Možete dodati još ${this.remainingAvailable} kom.`;
            }

            return null;
        },

        buttonLabel() {
            if (this.isBusy) {
                return 'Dodavanje...';
            }

            return this.has_in_cart ? 'Dodaj još u košaricu' : 'Dodaj u košaricu';
        }
    },

    mounted() {
        let cart = this.$store.state.storage.getCart();
        if(cart) {
            for (const key in cart.items) {
                if (this.id == cart.items[key].id) {
                    this.has_in_cart = cart.items[key].quantity;
                }
            }
        }

        this.checkAvailability();
    },

    methods: {
        add() {
            const quantity = this.normalizeQuantity();
            const currentQuantity = this.currentQuantity;
            const available = this.remainingAvailable;

            if (available !== null && quantity > available) {
                this.quantity = available > 0 ? available : 1;
                this.disabled = available <= 0;
                return;
            }

            const item = {
                id: this.id,
                quantity: quantity,
                gift_wrap: this.giftWrap
            };

            const action = currentQuantity ? 'updateCart' : 'addToCart';

            if (currentQuantity) {
                item.relative = true;
            }

            this.isBusy = true;

            this.$store.dispatch(action, item)
                .then((cart) => {
                    if (cart) {
                        this.syncHasInCart(cart);
                    }
                })
                .finally(() => {
                    this.isBusy = false;
                });
        },

        normalizeQuantity() {
            const maxSelectableQuantity = this.maxSelectableQuantity;
            let quantity = Math.max(1, parseInt(this.quantity, 10) || 1);

            if (maxSelectableQuantity !== null) {
                quantity = Math.min(quantity, maxSelectableQuantity);
            }

            this.quantity = quantity;

            return quantity;
        },

        decreaseQuantity() {
            this.quantity = Math.max(1, this.quantityNumber - 1);
        },

        increaseQuantity() {
            const nextQuantity = this.quantityNumber + 1;

            if (this.maxSelectableQuantity !== null) {
                this.quantity = Math.min(nextQuantity, this.maxSelectableQuantity);
                return;
            }

            this.quantity = nextQuantity;
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
            if (!this.stockTrackingEnabled) {
                this.disabled = false;
                return;
            }

            this.disabled = this.remainingAvailable !== null ? this.remainingAvailable <= 0 : false;

            if (this.maxSelectableQuantity !== null && this.quantityNumber > this.maxSelectableQuantity) {
                this.quantity = this.maxSelectableQuantity;
            }
        }
    }
};
</script>

<style scoped>
@font-face {
    font-family: "Font Awesome 5 Free";
    font-style: normal;
    font-weight: 900;
    font-display: block;
    src: url("/fonts/fontawesome/fa-solid-900.woff2") format("woff2"),
         url("/fonts/fontawesome/fa-solid-900.woff") format("woff");
}

.fas {
    display: inline-block;
    font-family: "Font Awesome 5 Free";
    font-style: normal;
    font-weight: 900;
    line-height: 1;
    text-rendering: auto;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.fa-gift::before {
    content: "\f06b";
}

.product-add-to-cart {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.product-add-to-cart__controls {
    display: grid;
    grid-template-columns: minmax(8.75rem, 9.5rem) minmax(0, 1fr);
    gap: 0.65rem;
    align-items: end;
}

.product-add-to-cart__quantity {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.product-add-to-cart__label {
    color: #667085;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.product-add-to-cart__cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 3rem;
    width: 100%;
    border-radius: 0.9rem;
    font-size: 0.95rem;
    font-weight: 700;
    box-shadow: none;
}

.product-add-to-cart__status {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.35;
    color: #667085;
}

.product-add-to-cart__status--warning {
    color: #c2410c;
}

.quantity-stepper {
    display: flex;
    align-items: center;
    min-height: 3rem;
    padding: 0.2rem;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 0.9rem;
    background: #ffffff;
    box-shadow: none;
}

.quantity-stepper__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 2.2rem;
    width: 2.2rem;
    height: 2.2rem;
    border: 0;
    border-radius: 0.75rem;
    background: #f4f6fb;
    color: #2f3441;
    font-size: 1rem;
    font-weight: 700;
    transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.quantity-stepper__button:not(:disabled):hover {
    background: rgba(229, 0, 119, 0.1);
    color: #e50077;
    transform: translateY(-1px);
}

.quantity-stepper__button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.quantity-stepper__input {
    flex: 1 1 auto;
    width: 100%;
    border: 0;
    padding: 0 0.3rem;
    background: transparent;
    color: #2f3441;
    font-size: 0.95rem;
    font-weight: 700;
    text-align: center;
    box-shadow: none;
    -moz-appearance: textfield;
    appearance: textfield;
}

.quantity-stepper__input:focus {
    outline: 0;
}

.quantity-stepper__input::-webkit-outer-spin-button,
.quantity-stepper__input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.gift-wrap-option {
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0.8rem;
    background: rgba(255, 255, 255, 0.96);
    cursor: pointer;
}

.gift-wrap-option__checkbox {
    display: inline-flex;
    align-items: center;
}

.gift-wrap-option__copy {
    display: flex;
    flex: 1 1 auto;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.12rem 0.55rem;
    color: #2f3441;
}

.gift-wrap-option__title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.88rem;
    font-weight: 600;
}

.gift-wrap-option__description {
    width: 100%;
    color: #667085;
    font-size: 0.75rem;
    line-height: 1.3;
}

.gift-wrap-option__icon {
    color: #e50077;
    font-size: 0.95rem;
}

.gift-wrap-option__price {
    font-size: 0.82rem;
    font-weight: 700;
    color: #e50077;
}

.gift-wrap-option__info {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid rgba(47, 52, 65, 0.15);
    color: #5c667a;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
}

.gift-wrap-option__tooltip {
    position: absolute;
    right: 0;
    bottom: calc(100% + 0.65rem);
    z-index: 10;
    width: min(15rem, 80vw);
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
    background: #2f3441;
    color: #ffffff;
    font-size: 0.76rem;
    line-height: 1.45;
    box-shadow: 0 12px 30px rgba(18, 25, 38, 0.2);
    opacity: 0;
    visibility: hidden;
    transform: translateY(0.25rem);
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
    pointer-events: none;
}

.gift-wrap-option__tooltip::after {
    content: "";
    position: absolute;
    right: 0.55rem;
    top: 100%;
    border-width: 0.4rem;
    border-style: solid;
    border-color: #2f3441 transparent transparent transparent;
}

.gift-wrap-option__info:hover .gift-wrap-option__tooltip,
.gift-wrap-option__info:focus .gift-wrap-option__tooltip,
.gift-wrap-option__info:focus-within .gift-wrap-option__tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

@media (max-width: 575.98px) {
    .product-add-to-cart__controls {
        grid-template-columns: 1fr;
    }

    .product-add-to-cart__cta {
        min-height: 2.95rem;
    }

    .gift-wrap-option {
        grid-template-columns: auto minmax(0, 1fr);
        align-items: flex-start;
    }

    .gift-wrap-option__copy {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
    }

    .gift-wrap-option__info {
        grid-column: 2;
        justify-self: flex-start;
    }
}
</style>

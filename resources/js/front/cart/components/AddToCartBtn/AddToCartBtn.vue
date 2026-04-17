<template>
    <div class="cart gift-cart d-flex flex-wrap align-items-center pt-2 pb-2 mb-3">
        <div class="gift-cart__controls d-flex flex-wrap align-items-center w-100">
            <input class="form-control me-3 mb-1" type="number" inputmode="numeric" pattern="[0-9]*" v-model="quantity" min="1" :max="available" style="width: 5rem;">
            <button class="btn btn-primary btn-shadow me-3 mb-1" @click="add()" :disabled="disabled"><i class="ci-cart"></i> Dodaj u Košaricu</button>
        </div>
        <label v-if="giftWrapEnabled" class="gift-wrap-option mb-2" for="gift-wrap-checkbox">
            <span class="gift-wrap-option__checkbox">
                <input id="gift-wrap-checkbox" class="form-check-input" type="checkbox" v-model="giftWrap">
            </span>
            <span class="gift-wrap-option__copy">
                <span class="gift-wrap-option__title"><i class="fas fa-gift gift-wrap-option__icon" aria-hidden="true"></i> Želim zamatanje za poklon.</span>
                <span class="gift-wrap-option__price">+5,00 €</span>
            </span>
            <span class="gift-wrap-option__info" tabindex="0" aria-label="Više informacija o zamatanju">
                i
                <span class="gift-wrap-option__tooltip">Usluga uključuje ukrasni papir, mašnu i pripremu knjige za poklon.</span>
            </span>
        </label>
        <p style="width: 100%;" class="fs-md fw-light text-danger" v-if="has_in_cart">Imate {{ has_in_cart }} artikala u košarici.</p>
    </div>
</template>

<script>
export default {
    props: {
        id: String,
        available: String,
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
            giftWrap: false
        }
    },

    computed: {
        giftWrapEnabled() {
            return String(this.allowGiftWrap) !== 'false';
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

        if (this.available == undefined) {
            this.available = 0;
        }

        this.checkAvailability();
    },

    methods: {
        add() {
            const quantity = this.normalizeQuantity();
            const currentQuantity = Number(this.has_in_cart) || 0;
            const available = Number(this.available) || 0;

            if (available && currentQuantity + quantity > available) {
                this.disabled = currentQuantity >= available;
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

            this.$store.dispatch(action, item).then((cart) => {
                if (cart) {
                    this.syncHasInCart(cart);
                }
            });
        },

        normalizeQuantity() {
            const quantity = Math.max(1, parseInt(this.quantity, 10) || 1);
            this.quantity = quantity;

            return quantity;
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
            const inCart = Number(this.has_in_cart) || 0;

            this.disabled = available ? inCart >= available : false;
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

.gift-cart__controls {
    margin-bottom: 0.35rem;
}

.gift-wrap-option {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.7rem 0.8rem;
    border: 1px solid rgba(229, 0, 119, 0.16);
    border-radius: 0.85rem;
    background: linear-gradient(180deg, rgba(255, 239, 246, 0.86) 0%, rgba(255, 250, 252, 0.96) 100%);
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
    align-items: center;
    gap: 0.4rem 0.75rem;
    color: #2f3441;
}

.gift-wrap-option__title {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.95rem;
    font-weight: 600;
}

.gift-wrap-option__icon {
    color: #e50077;
    font-size: 0.95rem;
}

.gift-wrap-option__price {
    font-size: 0.88rem;
    font-weight: 700;
    color: #e50077;
}

.gift-wrap-option__info {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid rgba(47, 52, 65, 0.15);
    color: #5c667a;
    font-size: 0.78rem;
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
    .gift-wrap-option {
        align-items: flex-start;
    }

    .gift-wrap-option__copy {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
    }
}
</style>

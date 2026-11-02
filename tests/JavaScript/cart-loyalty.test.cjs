const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const Vue = require('vue');
const compiler = require('vue-template-compiler');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/front/cart/components/CartViewAside/CartViewAside.vue'), 'utf8');
const component = compiler.parseComponent(source);
const context = { module: { exports: {} }, window: { location: { origin: 'https://zuzi.test' } } };
vm.runInNewContext(component.script.content.replace('export default', 'module.exports ='), context);
const options = context.module.exports;

function createAside(cart = {}, coupon = '') {
    const state = Vue.observable({
        cart: {
            coupon,
            has_loyalty: 200,
            loyalty: 100,
            loyalty_redemption_enabled: true,
            loyalty_coupon_compatible: true,
            has_gift_voucher: false,
            ...cart,
        },
    });
    const store = { state, dispatch: async () => ({ success: true }) };
    const aside = new Vue({
        ...options,
        beforeCreate() { this.$store = store; },
        data() { return { ...options.data.call(this), coupon }; },
    });

    return { aside, store };
}

test('offers loyalty with a compatible coupon while respecting eligibility', () => {
    for (const [cart, coupon, expected] of [
        [{}, 'ZUZI20', true],
        [{ loyalty_coupon_compatible: false }, 'OTHER', false],
        [{ loyalty_coupon_compatible: false }, '', true],
        [{ loyalty_redemption_enabled: false }, 'ZUZI20', false],
        [{ has_loyalty: 0 }, 'ZUZI20', false],
        [{ has_gift_voucher: true }, 'ZUZI20', false],
    ]) {
        const { aside } = createAside(cart, coupon);
        assert.equal(aside.canRedeemLoyalty, expected);
        aside.$destroy();
    }
});

test('applying ZUZI20 keeps an existing loyalty selection visible', async () => {
    const { aside, store } = createAside({}, '');
    aside.codeInput = 'zuzi20';
    store.dispatch = async () => {
        store.state.cart.coupon = 'ZUZI20';
        return { success: true };
    };

    await aside.setCoupon();

    assert.equal(aside.coupon, 'ZUZI20');
    assert.equal(aside.selected_loyalty, 100);
    assert.equal(aside.showLoyaltyPanel, true);
    assert.equal(aside.codeInput, '');
    aside.$destroy();
});

test('removes the visible selection when the server rejects an unaffordable reward', async () => {
    const { aside, store } = createAside({}, 'ZUZI20');
    assert.equal(aside.showLoyaltyPanel, true);

    store.state.cart.loyalty = '';
    await Vue.nextTick();

    assert.equal(aside.selected_loyalty, null);
    assert.equal(aside.showLoyaltyPanel, false);
    aside.$destroy();
});

test('applying an incompatible coupon hides and clears loyalty selection', async () => {
    const { aside, store } = createAside({}, '');
    aside.codeInput = 'OTHER';
    store.dispatch = async () => {
        store.state.cart = { ...store.state.cart, coupon: 'OTHER', loyalty: '', loyalty_coupon_compatible: false, has_loyalty: 0 };
        return { success: true };
    };

    await aside.setCoupon();
    await Vue.nextTick();

    assert.equal(aside.canRedeemLoyalty, false);
    assert.equal(aside.selected_loyalty, null);
    assert.equal(aside.showLoyaltyPanel, false);
    aside.$destroy();
});

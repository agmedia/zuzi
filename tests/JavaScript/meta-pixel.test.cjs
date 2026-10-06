const assert = require('node:assert/strict');
const test = require('node:test');

const { buildMetaEvent, createTracker } = require('../../public/js/meta-pixel.js');

test('maps a GA4 purchase to a Meta Purchase event', () => {
    const event = buildMetaEvent({
        event: 'purchase',
        ecommerce: {
            transaction_id: '1234',
            value: 31.5,
            currency: 'EUR',
            items: [
                { item_id: 'BOOK-1', item_name: 'Knjiga', price: 10.5, quantity: 3 }
            ]
        }
    });

    assert.equal(event.name, 'Purchase');
    assert.equal(event.params.order_id, '1234');
    assert.equal(event.params.value, 31.5);
    assert.equal(event.params.currency, 'EUR');
    assert.deepEqual(event.params.contents, [{ id: 'BOOK-1', quantity: 3, item_price: 10.5 }]);
    assert.deepEqual(event.params.content_ids, ['BOOK-1']);
    assert.equal(event.eventId, 'zuzi_purchase_1234');
});

test('calculates value when an ecommerce event has no explicit total', () => {
    const event = buildMetaEvent({
        event: 'add_to_cart',
        ecommerce: {
            items: [
                { item_id: 'BOOK-2', price: '12,50', quantity: 2, currency: 'EUR' }
            ]
        }
    });

    assert.equal(event.name, 'AddToCart');
    assert.equal(event.params.value, 25);
    assert.equal(event.params.num_items, 2);
});

test('normalizes thousands separators in product prices', () => {
    const event = buildMetaEvent({
        event: 'add_to_cart',
        ecommerce: {
            items: [
                { item_id: 'BOOK-EXPENSIVE', price: '1,234.50', quantity: 1, currency: 'EUR' }
            ]
        }
    });

    assert.equal(event.params.value, 1234.5);
    assert.equal(event.params.contents[0].item_price, 1234.5);
});

test('does not initialize or send events before marketing consent', () => {
    const calls = [];
    const fakeWindow = {
        dataLayer: [{ event: 'view_item', ecommerce: { items: [{ item_id: 'BOOK-3', price: 9 }] } }],
        document: { readyState: 'complete' },
        fbq: (...args) => calls.push(args)
    };

    createTracker(fakeWindow, '1118812093430338');

    assert.deepEqual(calls, []);
});

test('drops events after an explicit marketing denial', () => {
    const calls = [];
    const deniedEvent = {
        event: 'view_item',
        ecommerce: { items: [{ item_id: 'BOOK-DENIED', price: 9 }] }
    };
    const fakeWindow = {
        dataLayer: [deniedEvent],
        document: { readyState: 'complete' },
        fbq: (...args) => calls.push(args)
    };
    const tracker = createTracker(fakeWindow, '1118812093430338');

    tracker.setConsent(false, true);
    tracker.setConsent(true, true);

    assert.equal(calls.some((call) => call[2] === 'ViewContent'), false);
});

test('drops queued events when stored denial is applied before page load', () => {
    const calls = [];
    let loadHandler = null;
    const fakeWindow = {
        dataLayer: [{
            event: 'view_item',
            ecommerce: { items: [{ item_id: 'BOOK-STORED-DENIAL', price: 9 }] }
        }],
        document: { readyState: 'loading' },
        addEventListener: (name, handler) => {
            if (name === 'load') {
                loadHandler = handler;
            }
        },
        fbq: (...args) => calls.push(args)
    };
    const tracker = createTracker(fakeWindow, '1118812093430338');

    tracker.setConsent(false, true);
    tracker.setConsent(true, true);
    loadHandler();

    assert.equal(calls.some((call) => call[2] === 'ViewContent'), false);
});

test('sends a consented Purchase without waiting for the window load event', () => {
    const calls = [];
    const fakeWindow = {
        dataLayer: [{
            event: 'purchase',
            ecommerce: { transaction_id: 'FAST-1', value: 22, currency: 'EUR' }
        }],
        document: { readyState: 'loading' },
        addEventListener: () => {},
        fbq: (...args) => calls.push(args)
    };
    const tracker = createTracker(fakeWindow, '1118812093430338');

    tracker.setConsent(true, true);

    assert.equal(calls.some((call) => call[2] === 'Purchase'), true);
});

test('continues when sessionStorage access is blocked', () => {
    const calls = [];
    const fakeWindow = {
        dataLayer: [{
            event: 'purchase',
            ecommerce: { transaction_id: 'PRIVATE-1', value: 18, currency: 'EUR' }
        }],
        document: { readyState: 'complete' },
        fbq: (...args) => calls.push(args)
    };

    Object.defineProperty(fakeWindow, 'sessionStorage', {
        get: () => { throw new Error('SecurityError'); }
    });

    const tracker = createTracker(fakeWindow, '1118812093430338');
    tracker.setConsent(true, true);

    assert.equal(calls.some((call) => call[2] === 'Purchase'), true);
});

test('sends PageView and queued ecommerce events after consent', () => {
    const calls = [];
    const fakeWindow = {
        dataLayer: [{ event: 'view_item', ecommerce: { items: [{ item_id: 'BOOK-4', price: 17 }] } }],
        document: { readyState: 'complete' },
        fbq: (...args) => calls.push(args)
    };
    const tracker = createTracker(fakeWindow, '1118812093430338');

    tracker.setConsent(true);

    assert.deepEqual(calls[0], ['init', '1118812093430338']);
    assert.deepEqual(calls[1], ['consent', 'grant']);
    assert.deepEqual(calls[2], ['trackSingle', '1118812093430338', 'PageView']);
    assert.equal(calls[3][2], 'ViewContent');

    fakeWindow.dataLayer.push({
        event: 'add_to_cart',
        ecommerce: { items: [{ item_id: 'BOOK-5', price: 8, quantity: 1 }] }
    });

    assert.equal(calls.at(-1)[2], 'AddToCart');
});

test('uses the application tracker as the explicit Pixel owner', () => {
    const calls = [];
    const fakeWindow = {
        _fbq_gtm_ids: ['1118812093430338'],
        dataLayer: [{ event: 'purchase', ecommerce: { value: 20, currency: 'EUR' } }],
        document: { readyState: 'complete' },
        fbq: (...args) => calls.push(args)
    };
    const tracker = createTracker(fakeWindow, '1118812093430338');

    tracker.setConsent(true);

    assert.equal(calls.some((call) => call[0] === 'init'), true);
    assert.equal(calls.some((call) => call[2] === 'Purchase'), true);
});

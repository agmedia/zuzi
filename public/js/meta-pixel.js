(function (root, factory) {
    'use strict';

    var api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.ZuziMetaPixel = api;
    }
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var META_EVENT_NAMES = {
        view_item: 'ViewContent',
        add_to_cart: 'AddToCart',
        begin_checkout: 'InitiateCheckout',
        add_payment_info: 'AddPaymentInfo',
        purchase: 'Purchase'
    };

    function numberValue(value, fallback) {
        var normalized = value;

        if (typeof value === 'string') {
            normalized = value.trim();

            if (normalized.indexOf(',') !== -1 && normalized.indexOf('.') !== -1) {
                normalized = normalized.lastIndexOf(',') > normalized.lastIndexOf('.')
                    ? normalized.replace(/\./g, '').replace(',', '.')
                    : normalized.replace(/,/g, '');
            } else if (normalized.indexOf(',') !== -1) {
                normalized = normalized.replace(',', '.');
            }
        }

        var number = Number(normalized);

        return Number.isFinite(number) ? number : fallback;
    }

    function buildMetaEvent(dataLayerEntry) {
        if (!dataLayerEntry || typeof dataLayerEntry !== 'object') {
            return null;
        }

        var metaEventName = META_EVENT_NAMES[dataLayerEntry.event];
        var ecommerce = dataLayerEntry.ecommerce;

        if (!metaEventName || !ecommerce || typeof ecommerce !== 'object') {
            return null;
        }

        var items = Array.isArray(ecommerce.items) ? ecommerce.items : [];
        var contents = items.map(function (item) {
            return {
                id: String(item.item_id || item.id || ''),
                quantity: Math.max(1, numberValue(item.quantity, 1)),
                item_price: numberValue(item.price, 0)
            };
        }).filter(function (item) {
            return item.id !== '';
        });
        var calculatedValue = items.reduce(function (total, item) {
            return total + (numberValue(item.price, 0) * Math.max(1, numberValue(item.quantity, 1)));
        }, 0);
        var firstItem = items[0] || {};
        var params = {
            content_type: 'product',
            currency: String(ecommerce.currency || firstItem.currency || 'EUR'),
            value: numberValue(ecommerce.value, Math.round(calculatedValue * 100) / 100)
        };

        if (contents.length > 0) {
            params.contents = contents;
            params.content_ids = contents.map(function (item) { return item.id; });
            params.num_items = contents.reduce(function (total, item) { return total + item.quantity; }, 0);
        }

        if (firstItem.item_name) {
            params.content_name = String(firstItem.item_name);
        }

        if (firstItem.item_category) {
            params.content_category = String(firstItem.item_category);
        }

        if (metaEventName === 'Purchase' && ecommerce.transaction_id) {
            params.order_id = String(ecommerce.transaction_id);
        }

        return {
            name: metaEventName,
            params: params,
            eventId: metaEventName === 'Purchase' && ecommerce.transaction_id
                ? 'zuzi_purchase_' + String(ecommerce.transaction_id)
                : null
        };
    }

    function createTracker(global, pixelId) {
        var processedEntries = new WeakSet();
        var marketingAllowed = false;
        var discardWhileDenied = false;
        var started = false;
        var initialized = false;
        var pixelConsentGranted = false;
        var pageViewTracked = false;
        var managedByGtm = false;

        function pixelIsManagedByGtm() {
            return Array.isArray(global._fbq_gtm_ids)
                && global._fbq_gtm_ids.map(String).indexOf(String(pixelId)) !== -1;
        }

        function installFacebookQueue() {
            if (typeof global.fbq === 'function') {
                return;
            }

            var fbq = function () {
                if (fbq.callMethod) {
                    fbq.callMethod.apply(fbq, arguments);
                    return;
                }

                fbq.queue.push(arguments);
            };

            global.fbq = fbq;

            if (!global._fbq) {
                global._fbq = fbq;
            }

            fbq.push = fbq;
            fbq.loaded = true;
            fbq.version = '2.0';
            fbq.queue = [];

            var script = global.document.createElement('script');
            var firstScript = global.document.getElementsByTagName('script')[0];

            script.async = true;
            script.src = 'https://connect.facebook.net/en_US/fbevents.js';
            script.setAttribute('data-zuzi-meta-pixel', String(pixelId));

            if (firstScript && firstScript.parentNode) {
                firstScript.parentNode.insertBefore(script, firstScript);
            } else {
                global.document.head.appendChild(script);
            }
        }

        function syncManagedPixelConsent() {
            if (typeof global.fbq !== 'function') {
                return;
            }

            global.fbq('consent', marketingAllowed ? 'grant' : 'revoke');
            pixelConsentGranted = marketingAllowed;
        }

        function initializePixel() {
            if (pixelIsManagedByGtm()) {
                managedByGtm = true;
                syncManagedPixelConsent();
                return false;
            }

            installFacebookQueue();

            if (!initialized) {
                global.fbq('init', String(pixelId));
                initialized = true;
            }

            if (!pixelConsentGranted) {
                global.fbq('consent', 'grant');
                pixelConsentGranted = true;
            }

            if (!pageViewTracked) {
                global.fbq('trackSingle', String(pixelId), 'PageView');
                pageViewTracked = true;
            }

            return true;
        }

        function eventWasSent(eventId) {
            if (!eventId || !global.sessionStorage) {
                return false;
            }

            try {
                return global.sessionStorage.getItem('zuzi_meta_' + eventId) === '1';
            } catch (error) {
                return false;
            }
        }

        function rememberSentEvent(eventId) {
            if (!eventId || !global.sessionStorage) {
                return;
            }

            try {
                global.sessionStorage.setItem('zuzi_meta_' + eventId, '1');
            } catch (error) {
                // Tracking must not interfere with checkout when storage is unavailable.
            }
        }

        function trackEntry(entry) {
            if (!started || managedByGtm || !entry || typeof entry !== 'object') {
                return;
            }

            if (processedEntries.has(entry)) {
                return;
            }

            if (!marketingAllowed) {
                if (discardWhileDenied) {
                    processedEntries.add(entry);
                }

                return;
            }

            var event = buildMetaEvent(entry);

            if (!event || !initializePixel()) {
                return;
            }

            if (eventWasSent(event.eventId)) {
                processedEntries.add(entry);
                return;
            }

            if (event.eventId) {
                global.fbq('trackSingle', String(pixelId), event.name, event.params, { eventID: event.eventId });
            } else {
                global.fbq('trackSingle', String(pixelId), event.name, event.params);
            }

            rememberSentEvent(event.eventId);
            processedEntries.add(entry);
        }

        function processExistingEntries() {
            (global.dataLayer || []).forEach(trackEntry);
        }

        function attachToDataLayer() {
            var dataLayer = global.dataLayer = global.dataLayer || [];

            if (dataLayer.push && dataLayer.push.__zuziMetaPixelWrapper === true) {
                return;
            }

            var originalPush = dataLayer.push;
            var wrappedPush = function () {
                var entries = Array.prototype.slice.call(arguments);
                var result = originalPush.apply(dataLayer, entries);

                entries.forEach(trackEntry);

                return result;
            };

            wrappedPush.__zuziMetaPixelWrapper = true;
            dataLayer.push = wrappedPush;
        }

        function start() {
            if (started) {
                return;
            }

            started = true;
            attachToDataLayer();

            if (pixelIsManagedByGtm()) {
                managedByGtm = true;
                syncManagedPixelConsent();
                return;
            }

            if (!marketingAllowed && discardWhileDenied) {
                processExistingEntries();
                return;
            }

            if (marketingAllowed && initializePixel()) {
                processExistingEntries();
            }
        }

        function setConsent(granted, consentDecided) {
            marketingAllowed = granted === true;
            discardWhileDenied = !marketingAllowed && consentDecided === true;

            if (!marketingAllowed) {
                if (typeof global.fbq === 'function') {
                    global.fbq('consent', 'revoke');
                }

                pixelConsentGranted = false;

                if (started && discardWhileDenied) {
                    processExistingEntries();
                }

                return;
            }

            discardWhileDenied = false;

            if (!started) {
                return;
            }

            if (managedByGtm) {
                syncManagedPixelConsent();
                return;
            }

            if (initializePixel()) {
                processExistingEntries();
            }
        }

        if (global.document.readyState === 'complete') {
            start();
        } else {
            global.addEventListener('load', start, { once: true });
        }

        return {
            setConsent: setConsent,
            start: start
        };
    }

    return {
        buildMetaEvent: buildMetaEvent,
        createTracker: createTracker
    };
});

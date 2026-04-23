const DEFAULT_IMAGE = '/media/img/zuzi-logo.webp';

const CHECK_ICON = `
    <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M9.55 17.3 4.8 12.55l1.4-1.4 3.35 3.35 8.25-8.25 1.4 1.4Z"/>
    </svg>
`;

const LOYALTY_ICON = `
    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="m12 2 2.47 5 5.53.81-4 3.89.94 5.5L12 14.6 7.06 17.2 8 11.7 4 7.81 9.53 7Z"/>
    </svg>
`;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function toNumber(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    const normalized = String(value ?? '')
        .replace(',', '.')
        .replace(/[^0-9.-]/g, '');

    const parsed = parseFloat(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
}

function formatEuro(value) {
    return `${Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })} €`;
}

function pointsLabel(points) {
    const absolute = Math.abs(Number(points) || 0);
    const mod100 = absolute % 100;
    const mod10 = absolute % 10;

    if (mod100 > 10 && mod100 < 20) {
        return 'loyalty bodova';
    }

    if (mod10 === 1) {
        return 'loyalty bod';
    }

    if (mod10 >= 2 && mod10 <= 4) {
        return 'loyalty boda';
    }

    return 'loyalty bodova';
}

function cartItems(cart) {
    return Object.values(cart?.items || {});
}

function findCartItem(cart, itemId) {
    const directMatch = cart?.items?.[itemId];

    if (directMatch) {
        return directMatch;
    }

    return cartItems(cart).find((item) => String(item?.id) === String(itemId));
}

function findGiftWrapItem(cart, productId) {
    return cartItems(cart).find((item) => (
        item?.attributes?.item_type === 'gift_wrap'
        && String(item?.attributes?.wrapped_product_id) === String(productId)
    ));
}

function resolveUnitPrice(cartItem) {
    const associated = cartItem?.associatedModel || {};
    const special = toNumber(associated.eur_special);
    const regular = toNumber(associated.eur_price || cartItem?.price);

    if (special > 0 && special <= regular) {
        return special;
    }

    return regular;
}

function resolveLinePrice(cart, cartItem, requestedItem) {
    const quantity = Math.max(1, parseInt(requestedItem?.quantity, 10) || 1);
    const giftWrapItem = findGiftWrapItem(cart, requestedItem?.id);
    const lineBase = resolveUnitPrice(cartItem) * quantity;
    const wrapBase = giftWrapItem ? toNumber(giftWrapItem.price) * quantity : 0;

    return lineBase + wrapBase;
}

function resolvePriceText(cart, cartItem, requestedItem) {
    const quantity = Math.max(1, parseInt(requestedItem?.quantity, 10) || 1);
    const linePrice = resolveLinePrice(cart, cartItem, requestedItem);
    const hasGiftWrap = Boolean(findGiftWrapItem(cart, requestedItem?.id));

    if (quantity > 1 || hasGiftWrap) {
        return `Dodano: ${formatEuro(linePrice)}`;
    }

    const associated = cartItem?.associatedModel || {};
    const regular = toNumber(associated.eur_price || cartItem?.price);
    const special = toNumber(associated.eur_special);

    if (special > 0 && special < regular && associated.main_special_text) {
        return associated.main_special_text;
    }

    if (associated.main_price_text) {
        return associated.main_price_text;
    }

    return formatEuro(resolveUnitPrice(cartItem));
}

function resolveImage(cartItem) {
    return cartItem?.associatedModel?.image || DEFAULT_IMAGE;
}

function resolveQuantity(value) {
    return Math.max(1, parseInt(value, 10) || 1);
}

function resolveLoyaltyCopy(cart, cartItem, requestedItem) {
    const pointsPerEuro = Math.max(0, toNumber(cart?.loyalty_points_per_euro || 1));
    const estimatedPoints = Math.max(0, Math.floor(resolveLinePrice(cart, cartItem, requestedItem) * pointsPerEuro));

    if (!estimatedPoints) {
        return {
            headline: 'Registrirani kupci skupljaju loyalty bodove pri svakoj kupnji.'
        };
    }

    return {
        headline: `Registrirani kupci ovom stavkom dobiju ${estimatedPoints} ${pointsLabel(estimatedPoints)}.`
    };
}

function buildModalHtml(payload) {
    const cartItem = payload.cartItem;
    const requestedItem = payload.requestedItem;
    const quantityAdded = resolveQuantity(requestedItem?.quantity);
    const quantityInCart = resolveQuantity(cartItem?.quantity);
    const giftWrapIncluded = Boolean(findGiftWrapItem(payload.cart, requestedItem?.id));
    const loyaltyCopy = resolveLoyaltyCopy(payload.cart, cartItem, requestedItem);

    return `
        <div class="cart-add-modal">
            <div class="cart-add-modal__hero">
                <span class="cart-add-modal__hero-icon">${CHECK_ICON}</span>
                <div class="cart-add-modal__hero-copy">
                    <h2 class="cart-add-modal__heading">Uletjelo u košaricu 😉</h2>
                    <p class="cart-add-modal__lead">Sve je spremno... još samo jedan klik do užitka.</p>
                </div>
            </div>

            <div class="cart-add-modal__card">
                <div class="cart-add-modal__image-wrap">
                    <img
                        class="cart-add-modal__image"
                        src="${escapeHtml(resolveImage(cartItem))}"
                        alt="${escapeHtml(cartItem?.name || 'Dodani proizvod')}"
                    >
                </div>

                <div class="cart-add-modal__body">
                    <span class="cart-add-modal__price">${escapeHtml(resolvePriceText(payload.cart, cartItem, requestedItem))}</span>
                    <h3 class="cart-add-modal__name">${escapeHtml(cartItem?.name || 'Odabrani proizvod')}</h3>

                    <div class="cart-add-modal__chips">
                        <span class="cart-add-modal__chip">Dodano: <strong>${quantityAdded} kom</strong></span>
                        ${quantityInCart > quantityAdded ? `<span class="cart-add-modal__chip">Ukupno u košarici: <strong>${quantityInCart} kom</strong></span>` : ''}
                        ${giftWrapIncluded ? '<span class="cart-add-modal__chip cart-add-modal__chip--accent">Poklon zamatanje uključeno</span>' : ''}
                    </div>
                </div>
            </div>

            <div class="cart-add-modal__loyalty">
                <span class="cart-add-modal__loyalty-icon">${LOYALTY_ICON}</span>
                <div class="cart-add-modal__loyalty-copy">
                    <strong>${escapeHtml(loyaltyCopy.headline)}</strong>
                </div>
            </div>
        </div>
    `;
}

export function showCartAddSuccessModal(swal, payload = {}) {
    const cart = payload.cart || {};
    const requestedItem = payload.item || {};
    const cartItem = findCartItem(cart, requestedItem.id);

    if (!swal || !cartItem) {
        return null;
    }

    return swal.fire({
        html: buildModalHtml({
            cart,
            cartItem,
            requestedItem
        }),
        showCloseButton: true,
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Dovrši kupnju',
        cancelButtonText: 'Nastavi šopingirati',
        closeButtonAriaLabel: 'Zatvori',
        focusConfirm: false,
        buttonsStyling: false,
        customClass: {
            container: 'cart-add-modal-container',
            popup: 'cart-add-modal-popup',
            htmlContainer: 'cart-add-modal-html',
            closeButton: 'cart-add-modal-close',
            actions: 'cart-add-modal-actions',
            confirmButton: 'btn btn-primary btn-shadow cart-add-modal-confirm',
            cancelButton: 'btn btn-outline-primary cart-add-modal-cancel'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '/kosarica';
        }

        return result;
    });
}

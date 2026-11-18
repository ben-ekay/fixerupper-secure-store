/*
 * Checkout page enhancements.
 *
 * - Shows the delivery address fields only when "Home delivery" is chosen.
 * - Updates the delivery fee and total shown on screen.
 *
 * This is for convenience only. Without JavaScript all fields are shown,
 * and the server always recalculates prices and validates every field.
 */
(function () {
    'use strict';

    var form = document.getElementById('checkout-form');

    if (!form) {
        return;
    }

    var radios = form.querySelectorAll('input[name="fulfilment_method"]');
    var deliveryFields = document.querySelector('[data-delivery-fields]');
    var subtotalEl = document.querySelector('[data-subtotal-pence]');
    var feeText = document.querySelector('[data-fee-text]');
    var totalTexts = document.querySelectorAll('[data-total-text]');

    var subtotalPence = parseInt(subtotalEl.getAttribute('data-subtotal-pence'), 10);

    function formatPence(pence) {
        return '£' + (pence / 100).toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function update() {
        var selected = form.querySelector('input[name="fulfilment_method"]:checked');
        var isDelivery = selected && selected.value === 'delivery';
        var feePence = selected ? parseInt(selected.getAttribute('data-fee-pence'), 10) : 0;

        deliveryFields.hidden = !isDelivery;

        ['address_line1', 'city', 'postcode'].forEach(function (id) {
            var input = document.getElementById(id);
            input.required = isDelivery;
            input.setAttribute('aria-required', isDelivery ? 'true' : 'false');
        });

        feeText.textContent = feePence === 0 ? 'Free' : formatPence(feePence);

        totalTexts.forEach(function (el) {
            el.textContent = formatPence(subtotalPence + feePence);
        });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', update);
    });

    // Prevent an accidental second click while the order is being placed.
    // (The server's one-time checkout token is the real protection.)
    form.addEventListener('submit', function () {
        var button = form.querySelector('.checkout-button');

        window.setTimeout(function () {
            button.disabled = true;
            button.textContent = 'Placing your order…';
        }, 0);
    });

    update();
}());

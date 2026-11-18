/*
 * Admin screens. Kept in this file, rather than in onclick="" style attributes,
 * so the Content Security Policy can refuse all inline scripts.
 */
(function () {
    'use strict';

    // Forms with data-confirm ask before submitting.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Dropdowns with data-auto-submit apply their filter straight away.
    document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    // Stock overview bar: widths come from data-width.
    document.querySelectorAll('[data-width]').forEach(function (bar) {
        bar.style.width = bar.getAttribute('data-width') + '%';
    });

    // Dashboard actions menus: only one open at a time; clicking elsewhere closes it.
    var menus = document.querySelectorAll('.admin-actions-menu details');

    document.addEventListener('click', function (event) {
        menus.forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    menus.forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (!menu.open) {
                return;
            }

            menus.forEach(function (other) {
                if (other !== menu) {
                    other.removeAttribute('open');
                }
            });
        });
    });
}());

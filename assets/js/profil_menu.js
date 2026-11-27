/**
 * profil_menu.js - buka/tutup dropdown akun.
 * Klik avatar = buka/tutup; klik di luar atau tekan Esc = tutup;
 * panah atas/bawah, Home, End = pindah antar item menu.
 */
(function () {
    'use strict';
    var root = document.getElementById('pmRoot');
    if (!root) { return; }
    var trigger = document.getElementById('pmTrigger');
    var menu = document.getElementById('pmMenu');

    function items() { return Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]')); }
    function terbuka() { return menu.classList.contains('buka'); }

    function buka(fokusPertama) {
        menu.classList.add('buka');
        trigger.setAttribute('aria-expanded', 'true');
        if (fokusPertama) { var it = items(); if (it.length) { it[0].focus(); } }
    }
    function tutup(kembaliFokus) {
        menu.classList.remove('buka');
        trigger.setAttribute('aria-expanded', 'false');
        if (kembaliFokus) { trigger.focus(); }
    }

    trigger.addEventListener('click', function () { terbuka() ? tutup(false) : buka(false); });

    document.addEventListener('click', function (e) {
        if (terbuka() && !root.contains(e.target)) { tutup(false); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && terbuka()) { tutup(true); return; }
        var fokusDiMenu = menu.contains(document.activeElement);
        if (e.target === trigger && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault(); buka(true); return;
        }
        if (!terbuka() || !fokusDiMenu) { return; }
        var it = items(), i = it.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); it[(i + 1) % it.length].focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); it[(i - 1 + it.length) % it.length].focus(); }
        else if (e.key === 'Home') { e.preventDefault(); it[0].focus(); }
        else if (e.key === 'End') { e.preventDefault(); it[it.length - 1].focus(); }
        else if (e.key === 'Tab') { tutup(false); }
    });
})();

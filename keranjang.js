/**
 * keranjang.js
 * Keranjang pesanan di sisi browser. SATU sumber data (objek `items`) dipakai oleh
 * kartu menu, halaman detail, tombol keranjang, dan overlay, sehingga semuanya selalu sinkron.
 * Disimpan di localStorage (per pengguna) agar tidak hilang saat pindah halaman.
 * Harga di sini hanya untuk tampilan; server menghitung ulang harga dari database saat checkout.
 */
(function () {
    'use strict';

    var cfg = window.KERANJANG;
    if (!cfg) { return; }

    var MENU = cfg.menu;      // { "id": { nama, harga } } - hanya menu yang bisa dipesan
    var MAKS = cfg.maks;
    var KUNCI = cfg.kunci;

    var items = {};           // id -> jumlah
    var catatan = '';

    var fab = document.getElementById('cartFab');
    var badge = document.getElementById('cartBadge');
    var modal = document.getElementById('cartModal');
    var backdrop = document.getElementById('cartBackdrop');
    var daftarEl = document.getElementById('cartList');
    var kosongEl = document.getElementById('cartKosong');
    var totalEl = document.getElementById('cartTotal');
    var bayarBtn = document.getElementById('cartBayar');
    var tutupBtn = document.getElementById('cartClose');
    var itemsInput = document.getElementById('cartItemsInput');
    var catatanEl = document.getElementById('cartCatatan');
    var fokusSebelum = null;

    function rupiah(n) {
        return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // ---------- Penyimpanan ----------
    function muat() {
        items = {};
        catatan = '';
        try {
            var mentah = localStorage.getItem(KUNCI);
            if (!mentah) { return; }
            var data = JSON.parse(mentah);
            if (data && typeof data.items === 'object' && data.items) {
                Object.keys(data.items).forEach(function (id) {
                    var q = parseInt(data.items[id], 10);
                    // buang menu yang sudah tidak bisa dipesan (habis / dihapus)
                    if (MENU[id] && q > 0) { items[id] = Math.min(q, MAKS); }
                });
            }
            if (data && typeof data.catatan === 'string') { catatan = data.catatan.slice(0, 255); }
        } catch (e) { /* data rusak -> mulai kosong */ }
    }

    function simpan() {
        try { localStorage.setItem(KUNCI, JSON.stringify({ items: items, catatan: catatan })); } catch (e) { /* mode privat dll. */ }
    }

    // ---------- Komponen ----------
    function el(tag, kelas, teks) {
        var n = document.createElement(tag);
        if (kelas) { n.className = kelas; }
        if (teks !== undefined) { n.textContent = teks; }
        return n;
    }

    function buatQty(id) {
        var wrap = el('div', 'qty');
        wrap.setAttribute('data-id', id);
        var kurang = el('button', 'qty-btn', '\u2212');
        kurang.type = 'button';
        kurang.setAttribute('data-aksi', 'kurang');
        kurang.setAttribute('aria-label', 'Kurangi jumlah ' + MENU[id].nama);
        var num = el('span', 'qty-num', '0');
        num.setAttribute('aria-live', 'polite');
        var tambah = el('button', 'qty-btn', '+');
        tambah.type = 'button';
        tambah.setAttribute('data-aksi', 'tambah');
        tambah.setAttribute('aria-label', 'Tambah jumlah ' + MENU[id].nama);
        wrap.appendChild(kurang);
        wrap.appendChild(num);
        wrap.appendChild(tambah);
        return wrap;
    }

    function ubah(id, delta) {
        if (!MENU[id]) { return; }
        var q = (items[id] || 0) + delta;
        q = Math.max(0, Math.min(MAKS, q));
        if (q === 0) { delete items[id]; } else { items[id] = q; }
        simpan();
        render();
        if (delta > 0 && fab) {
            fab.classList.remove('denyut');
            void fab.offsetWidth; // restart animasi
            fab.classList.add('denyut');
        }
    }

    // ---------- Render (satu fungsi untuk semua tampilan) ----------
    function render() {
        var ids = Object.keys(items);
        var jumlahTotal = 0, hargaTotal = 0;

        // 1) isi overlay
        daftarEl.textContent = '';
        ids.forEach(function (id) {
            var m = MENU[id], q = items[id];
            jumlahTotal += q;
            hargaTotal += m.harga * q;

            var li = el('li', 'cart-item');
            var info = el('div', 'cart-item-info');
            info.appendChild(el('div', 'cart-item-nama', m.nama));
            info.appendChild(el('div', 'cart-item-harga', rupiah(m.harga) + ' / porsi'));
            info.appendChild(el('div', 'cart-item-sub', rupiah(m.harga * q)));
            li.appendChild(info);
            li.appendChild(buatQty(id));
            daftarEl.appendChild(li);
        });
        kosongEl.style.display = ids.length ? 'none' : '';
        totalEl.textContent = rupiah(hargaTotal);
        bayarBtn.disabled = ids.length === 0;

        // 2) tombol melayang
        badge.textContent = jumlahTotal > 99 ? '99+' : String(jumlahTotal);
        badge.hidden = jumlahTotal === 0;
        fab.setAttribute('aria-label', 'Buka keranjang pesanan, ' + jumlahTotal + ' item');

        // 3) semua kontrol jumlah di halaman (kartu menu, detail, dan baris overlay)
        document.querySelectorAll('.qty[data-id]').forEach(function (w) {
            var q = items[w.getAttribute('data-id')] || 0;
            w.querySelector('.qty-num').textContent = q;
            w.querySelector('[data-aksi="kurang"]').disabled = q <= 0;
            w.querySelector('[data-aksi="tambah"]').disabled = q >= MAKS;
        });

        // 4) catatan (jangan timpa saat sedang diketik)
        if (document.activeElement !== catatanEl && catatanEl.value !== catatan) {
            catatanEl.value = catatan;
        }
    }

    // ---------- Overlay ----------
    var FOKUS = 'button:not([disabled]), textarea, a[href]';

    function buka() {
        fokusSebelum = document.activeElement;
        modal.classList.add('buka');
        backdrop.classList.add('buka');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cart-terbuka');
        setTimeout(function () { tutupBtn.focus(); }, 60);
    }

    function tutup() {
        modal.classList.remove('buka');
        backdrop.classList.remove('buka');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cart-terbuka');
        if (fokusSebelum && fokusSebelum.focus) { fokusSebelum.focus(); }
    }

    function terbuka() { return modal.classList.contains('buka'); }

    // ---------- Event ----------
    // Satu pendengar untuk SEMUA tombol +/- (kartu, detail, overlay)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.qty-btn') : null;
        if (!btn || btn.disabled) { return; }
        var wrap = btn.closest('.qty');
        if (!wrap) { return; }
        e.preventDefault();
        ubah(wrap.getAttribute('data-id'), btn.getAttribute('data-aksi') === 'tambah' ? 1 : -1);
    });

    fab.addEventListener('click', buka);
    tutupBtn.addEventListener('click', tutup);
    backdrop.addEventListener('click', tutup);

    document.addEventListener('keydown', function (e) {
        if (!terbuka()) { return; }
        if (e.key === 'Escape') { tutup(); return; }
        if (e.key === 'Tab') { // fokus tidak keluar dari overlay
            var daftar = modal.querySelectorAll(FOKUS);
            if (!daftar.length) { return; }
            var awal = daftar[0], akhir = daftar[daftar.length - 1];
            if (e.shiftKey && document.activeElement === awal) { e.preventDefault(); akhir.focus(); }
            else if (!e.shiftKey && document.activeElement === akhir) { e.preventDefault(); awal.focus(); }
        }
    });

    catatanEl.addEventListener('input', function () {
        catatan = catatanEl.value.slice(0, 255);
        simpan();
    });

    modal.addEventListener('submit', function (e) {
        if (Object.keys(items).length === 0) { e.preventDefault(); return; }
        itemsInput.value = JSON.stringify(items); // diterima & divalidasi ulang oleh checkout.php
    });

    // Tab/jendela lain mengubah keranjang -> ikut sinkron
    window.addEventListener('storage', function (e) {
        if (e.key === KUNCI) { muat(); render(); }
    });

    // Kembali lewat tombol "back" (cache halaman) -> muat ulang dari penyimpanan
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { muat(); render(); }
    });

    muat();
    simpan(); // tulis ulang versi yang sudah dibersihkan
    render();
})();

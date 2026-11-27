<?php
/**
 * keranjang_ui.php
 * Tombol keranjang melayang + overlay. Dipakai di halaman pelanggan (anggota).
 * Butuh: $db dan sesi (sesi.php). Letakkan tepat sebelum </body>.
 */

if (($_SESSION['peran'] ?? '') === 'anggota'):
    require_once __DIR__ . '/../fungsi_pesanan.php';

    // Hanya menu yang BISA dipesan. Angka stok tidak ikut dikirim ke browser.
    $hasil_menu = $db->query(
        "SELECT id, nama_menu, harga FROM menu
         WHERE dihapus_pada IS NULL AND tersedia = 'Ya' AND stok > 0"
    );
    $data_keranjang = [];
    foreach ($hasil_menu->fetch_all(MYSQLI_ASSOC) as $m) {
        $data_keranjang[(string) $m['id']] = ['nama' => $m['nama_menu'], 'harga' => (int) $m['harga']];
    }
    $config_keranjang = [
        'menu'  => $data_keranjang,
        'maks'  => JUMLAH_MAKS_PER_PESANAN,
        'kunci' => 'keranjang_u' . (int) $_SESSION['id'],
    ];
    $json_keranjang = json_encode(
        $config_keranjang,
        JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
?>
    <button type="button" class="cart-fab" id="cartFab" aria-haspopup="dialog" aria-label="Buka keranjang pesanan">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="9" cy="21" r="1.5"></circle>
            <circle cx="19" cy="21" r="1.5"></circle>
            <path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.5L22 7H6"></path>
        </svg>
        <span class="cart-badge" id="cartBadge" hidden>0</span>
    </button>

    <div class="cart-backdrop" id="cartBackdrop"></div>

    <form class="cart-modal" id="cartModal" method="POST" action="checkout.php"
          role="dialog" aria-modal="true" aria-labelledby="cartJudul" aria-hidden="true">
        <?= inputCsrf() ?>
        <input type="hidden" name="aksi" value="tinjau">
        <input type="hidden" name="items" id="cartItemsInput" value="{}">

        <div class="cart-head">
            <h2 id="cartJudul">Keranjang Pesanan</h2>
            <button type="button" class="cart-close" id="cartClose" aria-label="Tutup keranjang">&times;</button>
        </div>

        <div class="cart-body">
            <!-- Hanya daftar item yang bisa digulir; catatan di bawahnya selalu tetap terlihat -->
            <div class="cart-items">
                <p class="cart-kosong" id="cartKosong">Keranjang masih kosong. Tekan tombol + pada menu untuk menambah pesanan.</p>
                <ul class="cart-list" id="cartList"></ul>
            </div>

            <div class="cart-note">
                <label class="cart-label" for="cartCatatan">Catatan untuk pesanan (opsional)</label>
                <textarea id="cartCatatan" name="catatan" maxlength="255"
                          placeholder="Contoh: tidak pakai pedas, es dipisah"></textarea>
            </div>
        </div>

        <div class="cart-foot">
            <div class="cart-total"><span>Total</span><strong id="cartTotal">Rp 0</strong></div>
            <button type="submit" class="cart-bayar" id="cartBayar" disabled>Bayar</button>
        </div>
    </form>

    <script>window.KERANJANG = <?= $json_keranjang ?>;</script>
    <script src="assets/js/keranjang.js?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/keranjang.js') ?>"></script>
<?php endif; ?>

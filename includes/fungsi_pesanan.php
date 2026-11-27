<?php
/**
 * fungsi_pesanan.php
 * Logika pesanan + stok. Setiap operasi berjalan dalam TRANSAKSI:
 * stok menu dan baris pesanan selalu berubah bersamaan, atau tidak sama sekali.
 * Baris menu dikunci (SELECT ... FOR UPDATE) agar dua pelanggan yang memesan
 * bersamaan tidak bisa membuat stok menjadi minus.
 */

require_once __DIR__ . '/koneksi.php';

const JUMLAH_MAKS_PER_PESANAN = 20;
const STOK_MAKS = 99999;
const METODE_BAYAR = ['Tunai', 'QRIS', 'Transfer'];

/**
 * Error yang pesannya AMAN ditampilkan ke pengguna
 * (error lain, mis. gangguan database, hanya dicatat ke log)
 */
class PesananGagal extends Exception
{
}

/**
 * Buat pesanan untuk SEMUA item keranjang sekaligus (all-or-nothing):
 * bila satu menu saja habis / stok kurang, tidak ada pesanan yang dibuat
 * dan stok tidak berubah.
 * Harga diambil dari database, BUKAN dari browser.
 *
 * @param array<int,int> $items  [menu_id => jumlah]
 * @return int[] id pesanan yang dibuat
 * @throws PesananGagal bila menu tidak ada / habis / stok kurang
 */
function buatPesananBanyak(mysqli $db, int $penggunaId, array $items, string $catatan, string $metode): array
{
    if (empty($items)) {
        throw new PesananGagal('Keranjang masih kosong.');
    }
    if (!in_array($metode, METODE_BAYAR, true)) {
        throw new PesananGagal('Metode pembayaran tidak valid.');
    }

    // Kunci menu SELALU dalam urutan id yang sama -> dua transaksi tidak saling menunggu (deadlock)
    ksort($items);
    $catatanDb = $catatan === '' ? null : $catatan;

    $db->begin_transaction();
    try {
        $idBaru = [];
        foreach ($items as $menuId => $jumlah) {
            $menuId = (int) $menuId;
            $jumlah = (int) $jumlah;

            // Kunci baris menu selama transaksi berjalan; menu yang sudah dihapus dianggap tidak ada
            $stmt = $db->prepare(
                'SELECT nama_menu, harga, stok, tersedia FROM menu
                 WHERE id = ? AND dihapus_pada IS NULL FOR UPDATE'
            );
            $stmt->bind_param('i', $menuId);
            $stmt->execute();
            $menu = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$menu) {
                throw new PesananGagal('Salah satu menu sudah tidak tersedia.');
            }
            if ($menu['tersedia'] !== 'Ya' || (int) $menu['stok'] < 1) {
                throw new PesananGagal('Maaf, ' . $menu['nama_menu'] . ' sedang habis.');
            }
            // Pesan sengaja tidak menyebut angka stok (stok hanya boleh dilihat admin)
            if ((int) $menu['stok'] < $jumlah) {
                throw new PesananGagal('Maaf, stok ' . $menu['nama_menu'] . ' tidak mencukupi untuk jumlah yang diminta.');
            }

            $stmt = $db->prepare('UPDATE menu SET stok = stok - ? WHERE id = ?');
            $stmt->bind_param('ii', $jumlah, $menuId);
            $stmt->execute();
            $stmt->close();

            $harga = (int) $menu['harga'];
            $stmt = $db->prepare(
                'INSERT INTO pesanan (pengguna_id, menu_id, jumlah, harga_satuan, catatan, metode_bayar)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iiiiss', $penggunaId, $menuId, $jumlah, $harga, $catatanDb, $metode);
            $stmt->execute();
            $idBaru[] = (int) $db->insert_id;
            $stmt->close();
        }

        $db->commit();
        return $idBaru;
    } catch (Throwable $t) {
        $db->rollback();
        throw $t;
    }
}

/**
 * Ubah jumlah/catatan pesanan milik pengguna, sesuaikan stok sebesar selisihnya.
 * - jumlah naik  -> stok berkurang (harus cukup)
 * - jumlah turun -> stok dikembalikan
 *
 * @throws PesananGagal
 */
function ubahPesanan(mysqli $db, int $penggunaId, int $pesananId, int $jumlahBaru, string $catatan): void
{
    $db->begin_transaction();
    try {
        // Hanya pemilik pesanan yang bisa mengubah (pengguna_id dari SESSION)
        $stmt = $db->prepare('SELECT menu_id, jumlah FROM pesanan WHERE id = ? AND pengguna_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $pesananId, $penggunaId);
        $stmt->execute();
        $pesanan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pesanan) {
            throw new PesananGagal('Pesanan tidak ditemukan.');
        }

        $menuId = (int) $pesanan['menu_id'];
        $selisih = $jumlahBaru - (int) $pesanan['jumlah'];

        $stmt = $db->prepare('SELECT stok, tersedia, dihapus_pada FROM menu WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $menuId);
        $stmt->execute();
        $menu = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($selisih > 0) {
            // Menambah jumlah: menu harus masih ada, tersedia, dan stok harus cukup
            if (!$menu || $menu['tersedia'] !== 'Ya' || $menu['dihapus_pada'] !== null) {
                throw new PesananGagal('Maaf, menu ini sedang tidak tersedia, jumlah tidak bisa ditambah.');
            }
            if ((int) $menu['stok'] < $selisih) {
                throw new PesananGagal('Maaf, stok menu ini tidak mencukupi untuk penambahan tersebut.');
            }
            $stmt = $db->prepare('UPDATE menu SET stok = stok - ? WHERE id = ?');
            $stmt->bind_param('ii', $selisih, $menuId);
            $stmt->execute();
            $stmt->close();
        } elseif ($selisih < 0) {
            // Mengurangi jumlah: kembalikan selisihnya ke stok
            $kembali = -$selisih;
            $stmt = $db->prepare('UPDATE menu SET stok = stok + ? WHERE id = ?');
            $stmt->bind_param('ii', $kembali, $menuId);
            $stmt->execute();
            $stmt->close();
        }

        $catatanDb = $catatan === '' ? null : $catatan;
        $stmt = $db->prepare('UPDATE pesanan SET jumlah = ?, catatan = ? WHERE id = ?');
        $stmt->bind_param('isi', $jumlahBaru, $catatanDb, $pesananId);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $t) {
        $db->rollback();
        throw $t;
    }
}

/**
 * Batalkan (hapus) pesanan milik pengguna dan kembalikan stok ke menu.
 *
 * @throws PesananGagal
 */
function hapusPesanan(mysqli $db, int $penggunaId, int $pesananId): void
{
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT menu_id, jumlah FROM pesanan WHERE id = ? AND pengguna_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $pesananId, $penggunaId);
        $stmt->execute();
        $pesanan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pesanan) {
            throw new PesananGagal('Pesanan tidak ditemukan.');
        }

        $menuId = (int) $pesanan['menu_id'];
        $jumlah = (int) $pesanan['jumlah'];

        $stmt = $db->prepare('UPDATE menu SET stok = stok + ? WHERE id = ?');
        $stmt->bind_param('ii', $jumlah, $menuId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('DELETE FROM pesanan WHERE id = ?');
        $stmt->bind_param('i', $pesananId);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $t) {
        $db->rollback();
        throw $t;
    }
}

/**
 * HTML kontrol jumlah (- 0 +). Markup SAMA dengan yang dibuat assets/js/keranjang.js untuk baris overlay;
 * assets/js/keranjang.js yang mengisi angka dan menghidupkan tombolnya.
 */
function htmlKontrolJumlah(int $menuId, string $namaMenu): string
{
    $nama = htmlspecialchars($namaMenu, ENT_QUOTES, 'UTF-8');
    return '<div class="qty" data-id="' . $menuId . '">'
        . '<button type="button" class="qty-btn" data-aksi="kurang" aria-label="Kurangi jumlah ' . $nama . '" disabled>&minus;</button>'
        . '<span class="qty-num" aria-live="polite">0</span>'
        . '<button type="button" class="qty-btn" data-aksi="tambah" aria-label="Tambah jumlah ' . $nama . '">+</button>'
        . '</div>';
}

/**
 * Sesuaikan stok menu (admin): delta positif = tambah, negatif = kurangi.
 * Stok TIDAK BOLEH kurang dari 0. Perhitungan dilakukan di bawah penguncian baris
 * menu, sehingga aman walau ada pelanggan yang sedang memesan pada saat yang sama.
 * Bila $deskripsi diisi, deskripsi diperbarui dalam transaksi yang sama.
 *
 * @return int stok baru
 * @throws PesananGagal bila menu tidak ada atau hasilnya di luar 0..STOK_MAKS
 */
function sesuaikanStok(mysqli $db, int $menuId, int $delta, ?string $deskripsi = null): int
{
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT stok FROM menu WHERE id = ? AND dihapus_pada IS NULL FOR UPDATE');
        $stmt->bind_param('i', $menuId);
        $stmt->execute();
        $menu = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$menu) {
            throw new PesananGagal('Menu tidak ditemukan.');
        }

        $stokLama = (int) $menu['stok'];
        $stokBaru = $stokLama + $delta;
        if ($stokBaru < 0) {
            throw new PesananGagal("Stok tidak bisa kurang dari 0. Stok saat ini $stokLama, pengurangan " . abs($delta) . ' melebihi stok.');
        }
        if ($stokBaru > STOK_MAKS) {
            throw new PesananGagal('Stok maksimal ' . STOK_MAKS . ". Stok saat ini $stokLama.");
        }

        if ($deskripsi !== null) {
            $stmt = $db->prepare('UPDATE menu SET deskripsi = ?, stok = ? WHERE id = ?');
            $stmt->bind_param('sii', $deskripsi, $stokBaru, $menuId);
        } else {
            $stmt = $db->prepare('UPDATE menu SET stok = ? WHERE id = ?');
            $stmt->bind_param('ii', $stokBaru, $menuId);
        }
        $stmt->execute();
        $stmt->close();

        $db->commit();
        return $stokBaru;
    } catch (Throwable $t) {
        $db->rollback();
        throw $t;
    }
}

/**
 * Tentukan file gambar menu: gambar unggahan admin > gambar bawaan per ID > gambar per kategori.
 * (Dipindahkan ke sini agar tidak dideklarasikan ulang di setiap halaman.)
 */
function ambilGambarMenu($menu_id, $kategori, $gambar_db = null) {
    // 1) Jika admin upload gambar, gunakan itu (folder assets/images/)
    if (!empty($gambar_db)) {
        return 'assets/images/' . $gambar_db;
    }
    
    // 2) Mapping default per ID menu
    $gambar_map = [
        1 => 'nasi_goreng.png',
        2 => 'teh_tarik.png',
        3 => 'martabak.png',
        4 => 'es_krim.png',
        5 => 'mie_ayam.png',
        6 => 'es_teh.png',
        7 => 'soto_ayam.png',
        8 => 'jus_alpukat.png'
    ];
    
    if (isset($gambar_map[$menu_id])) {
        return 'assets/images/' . $gambar_map[$menu_id];
    }
    
    // 3) Fallback: gunakan kategori
    $kategori_map = [
        'Makanan' => 'nasi_goreng.png',
        'Minum' => 'teh_tarik.png',
        'Camilan' => 'martabak.png',
        'Dessert' => 'es_krim.png',
        'Lainnya' => 'nasi_goreng.png'
    ];
    
    return 'assets/images/' . ($kategori_map[$kategori] ?? 'nasi_goreng.png');
}

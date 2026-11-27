<?php
/**
 * checkout.php
 * Halaman pembayaran. Alur dua langkah (hanya pelanggan):
 *   1) POST aksi=tinjau : terima isi keranjang dari overlay, validasi, simpan di session,
 *                         lalu redirect ke GET checkout.php (halaman pembayaran)
 *   2) POST aksi=bayar  : buat semua pesanan dalam SATU transaksi (stok berkurang),
 *                         lalu redirect ke pesanan.php
 * Harga SELALU dibaca dari database; angka dari browser tidak dipercaya.
 */

require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/sesi.php';
require_once __DIR__ . '/includes/validasi.php';
require_once __DIR__ . '/includes/fungsi_pesanan.php';

wajibLogin();
wajibPeran('anggota');

$uid = (int) $_SESSION['id'];

// ---------------------------------------------------------------
// POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibCsrf();
    $aksi = ambilPost('aksi');

    if ($aksi === 'tinjau') {
        $catatan = bersihkanTeks(ambilPost('catatan'));
        $mentah = json_decode(ambilPost('items'), true);

        if (!is_array($mentah) || count($mentah) === 0 || count($mentah) > 50) {
            setFlash('Keranjang masih kosong.');
            header('Location: daftar.php');
            exit;
        }
        if (mb_strlen($catatan) > 255) {
            setFlash('Catatan maksimal 255 karakter.');
            header('Location: daftar.php');
            exit;
        }

        $items = [];
        foreach ($mentah as $id => $jumlah) {
            // kunci = id menu, nilai = jumlah; keduanya harus bilangan bulat positif yang wajar
            if (!is_numeric($id) || (int) $id <= 0 || !is_numeric($jumlah)
                || (int) $jumlah < 1 || (int) $jumlah > JUMLAH_MAKS_PER_PESANAN) {
                setFlash('Isi keranjang tidak valid. Silakan periksa kembali jumlah pesanan (1-' . JUMLAH_MAKS_PER_PESANAN . ').');
                header('Location: daftar.php');
                exit;
            }
            $items[(int) $id] = (int) $jumlah;
        }

        $_SESSION['checkout'] = ['items' => $items, 'catatan' => $catatan];
        header('Location: checkout.php');
        exit;
    }

    if ($aksi === 'bayar') {
        $data = $_SESSION['checkout'] ?? null;
        if (!$data || empty($data['items'])) {
            setFlash('Tidak ada pesanan untuk dibayar. Silakan pilih menu terlebih dahulu.');
            header('Location: daftar.php');
            exit;
        }
        $metode = ambilPost('metode');
        if (!in_array($metode, METODE_BAYAR, true)) {
            setFlash('Pilih metode pembayaran terlebih dahulu.');
            header('Location: checkout.php');
            exit;
        }

        try {
            $ids = buatPesananBanyak($db, $uid, $data['items'], $data['catatan'], $metode);
            unset($_SESSION['checkout']);
            $_SESSION['kosongkan_keranjang'] = true; // pesanan.php mengosongkan keranjang di browser
            setFlash('Pembayaran berhasil. ' . count($ids) . ' pesanan telah dibuat.');
            header('Location: pesanan.php');
        } catch (PesananGagal $ex) {
            setFlash($ex->getMessage());
            header('Location: checkout.php');
        } catch (Throwable $ex) {
            error_log('Checkout gagal: ' . $ex->getMessage());
            setFlash('Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.');
            header('Location: checkout.php');
        }
        exit;
    }

    header('Location: daftar.php');
    exit;
}

// ---------------------------------------------------------------
// GET: tampilkan halaman pembayaran
// ---------------------------------------------------------------
$data = $_SESSION['checkout'] ?? null;
if (!$data || empty($data['items'])) {
    setFlash('Keranjang masih kosong. Pilih menu terlebih dahulu.');
    header('Location: daftar.php');
    exit;
}
$flash = ambilFlash();

// Ambil nama & harga TERBARU dari database untuk setiap item
$ids = array_keys($data['items']);
$placeholder = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT id, nama_menu, harga, stok, tersedia FROM menu
     WHERE dihapus_pada IS NULL AND id IN ($placeholder)"
);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$menu_db = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
    $menu_db[(int) $m['id']] = $m;
}
$stmt->close();

$baris = [];
$total = 0;
$semua_bisa = true;
foreach ($data['items'] as $id => $jumlah) {
    $m = $menu_db[$id] ?? null;
    // Hanya cek tersedia/habis (boolean); angka stok tidak ditampilkan ke pelanggan
    $bisa = $m && $m['tersedia'] === 'Ya' && (int) $m['stok'] > 0;
    $subtotal = $m ? (int) $m['harga'] * $jumlah : 0;
    if ($bisa) {
        $total += $subtotal;
    } else {
        $semua_bisa = false;
    }
    $baris[] = [
        'nama'     => $m ? $m['nama_menu'] : 'Menu sudah tidak tersedia',
        'harga'    => $m ? (int) $m['harga'] : 0,
        'jumlah'   => $jumlah,
        'subtotal' => $subtotal,
        'bisa'     => $bisa,
    ];
}
$jumlah_item = array_sum($data['items']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Menu Kantin</title>
    <link rel="icon" href="assets/images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #edf5f0 0%, #dff2e8 100%); color: #1a1a1a; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .navbar { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 640px; margin: 32px auto; padding: 0 20px; }
        .langkah { display: flex; gap: 8px; font-size: 13px; color: #888; margin-bottom: 18px; }
        .langkah .aktif { color: #2a9d72; font-weight: 700; }
        h1 { font-size: 24px; margin-bottom: 18px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 24px; margin-bottom: 18px; }
        .card h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .5px; color: #2a9d72; margin-bottom: 14px; }
        .flash { background: #fff8e1; color: #8d6e00; border-left: 4px solid #ffc107; padding: 14px 18px; border-radius: 10px; margin-bottom: 18px; font-weight: 500; font-size: 14px; }
        .baris { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f3f3; font-size: 14px; }
        .baris:last-of-type { border-bottom: none; }
        .baris .nama { font-weight: 600; }
        .baris .rinci { font-size: 12px; color: #888; margin-top: 2px; }
        .baris.tidak { color: #d32f2f; }
        .baris .sub { font-weight: 700; white-space: nowrap; }
        .total { display: flex; justify-content: space-between; align-items: center; padding-top: 14px; margin-top: 6px; border-top: 2px solid #f0f0f0; font-size: 16px; font-weight: 700; }
        .total strong { font-size: 22px; color: #2a9d72; }
        .catatan { background: #f8f9fa; border-left: 3px solid #2a9d72; padding: 12px 14px; border-radius: 6px; font-size: 14px; color: #555; white-space: pre-wrap; }
        .metode { display: block; padding: 14px 16px; border: 2px solid #eee; border-radius: 10px; margin-bottom: 10px; cursor: pointer; font-size: 14px; }
        .metode:hover { border-color: #b9e8c1; }
        .metode input { margin-right: 10px; accent-color: #2a9d72; }
        .metode:has(input:checked) { border-color: #2a9d72; background: #edf5f0; }
        .metode small { display: block; margin-left: 26px; color: #888; margin-top: 2px; }
        .info { font-size: 12px; color: #999; margin-top: 4px; }
        .tombol { display: flex; gap: 12px; margin-top: 6px; }
        .btn { flex: 1; padding: 15px; border-radius: 10px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; text-align: center; font-family: inherit; }
        .btn-bayar { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); color: #fff; }
        .btn-bayar:disabled { background: #cfd8d3; cursor: not-allowed; }
        .btn-kembali { background: #fff; color: #555; border: 1px solid #ddd; flex: 0 0 auto; padding: 15px 22px; }
    </style>
</head>
<body>
    <?php $nb_aktif = 'pesanan'; include __DIR__ . '/includes/komponen/komponen_navbar.php'; ?>

    <div class="container">
        <div class="langkah"><span>1. Pilih menu</span> &rsaquo; <span class="aktif">2. Pembayaran</span> &rsaquo; <span>3. Selesai</span></div>
        <h1>Pembayaran</h1>

        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Ringkasan Pesanan (<?= (int) $jumlah_item ?> porsi)</h2>
            <?php foreach ($baris as $b): ?>
                <div class="baris <?= $b['bisa'] ? '' : 'tidak' ?>">
                    <div>
                        <div class="nama"><?= e($b['nama']) ?></div>
                        <div class="rinci">
                            <?= $b['bisa'] ? (int) $b['jumlah'] . ' &times; ' . formatRupiah($b['harga']) : 'Sedang habis / sudah dihapus. Ubah pesanan Anda.' ?>
                        </div>
                    </div>
                    <div class="sub"><?= $b['bisa'] ? formatRupiah($b['subtotal']) : '&mdash;' ?></div>
                </div>
            <?php endforeach; ?>
            <div class="total"><span>Total Pembayaran</span><strong><?= formatRupiah($total) ?></strong></div>
        </div>

        <?php if ($data['catatan'] !== ''): ?>
            <div class="card">
                <h2>Catatan</h2>
                <div class="catatan"><?= e($data['catatan']) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <?= inputCsrf() ?>
            <input type="hidden" name="aksi" value="bayar">

            <div class="card">
                <h2>Metode Pembayaran</h2>
                <label class="metode"><input type="radio" name="metode" value="Tunai" checked> Tunai
                    <small>Bayar langsung di kasir.</small></label>
                <label class="metode"><input type="radio" name="metode" value="QRIS"> QRIS
                    <small>Pindai kode QR.</small></label>
                <label class="metode"><input type="radio" name="metode" value="Transfer"> Transfer Bank
                    <small>Transfer ke rekening kantin.</small></label>
                <p class="info">Simulasi: aplikasi ini belum terhubung ke payment gateway. Metode yang dipilih hanya dicatat pada pesanan.</p>
            </div>

            <div class="tombol">
                <a href="daftar.php" class="btn btn-kembali">Ubah Pesanan</a>
                <button type="submit" class="btn btn-bayar" <?= $semua_bisa ? '' : 'disabled' ?>>
                    Bayar Sekarang &middot; <?= formatRupiah($total) ?>
                </button>
            </div>
        </form>
    </div>
</body>
</html>

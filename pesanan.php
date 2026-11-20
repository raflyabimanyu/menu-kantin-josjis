<?php
/**
 * pesanan.php
 * Daftar pesanan (READ).
 * - Pelanggan (anggota): hanya pesanan miliknya, bisa ubah & batalkan
 * - Admin: semua pesanan dari semua pelanggan (hanya lihat)
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'fungsi_pesanan.php';

wajibLogin();

$adalah_admin = $_SESSION['peran'] === 'admin';
$flash = ambilFlash();

$pesanan = [];
$error_data = null;
try {
    if ($adalah_admin) {
        $result = $db->query(
            'SELECT p.id, p.jumlah, p.harga_satuan, p.catatan, p.metode_bayar, p.dibuat_pada, p.diperbarui_pada,
                    m.nama_menu, u.nama AS pemesan
             FROM pesanan AS p
             JOIN menu AS m ON m.id = p.menu_id
             JOIN pengguna AS u ON u.id = p.pengguna_id
             ORDER BY p.dibuat_pada DESC, p.id DESC'
        );
        $pesanan = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt = $db->prepare(
            'SELECT p.id, p.jumlah, p.harga_satuan, p.catatan, p.metode_bayar, p.dibuat_pada, p.diperbarui_pada,
                    m.nama_menu, u.nama AS pemesan
             FROM pesanan AS p
             JOIN menu AS m ON m.id = p.menu_id
             JOIN pengguna AS u ON u.id = p.pengguna_id
             WHERE p.pengguna_id = ?
             ORDER BY p.dibuat_pada DESC, p.id DESC'
        );
        $uid = (int) $_SESSION['id'];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $pesanan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $ex) {
    // Halaman tetap tampil (bukan layar putih berisi fatal error)
    error_log('Gagal memuat pesanan: ' . $ex->getMessage());
    $error_data = 'Data pesanan belum bisa dimuat karena struktur database belum diperbarui.';
}

// Setelah pembayaran berhasil, keranjang di browser harus dikosongkan
$kosongkan_keranjang = !empty($_SESSION['kosongkan_keranjang']);
unset($_SESSION['kosongkan_keranjang']);

$total_semua = 0;
foreach ($pesanan as $p) {
    $total_semua += (int) $p['jumlah'] * (int) $p['harga_satuan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $adalah_admin ? 'Semua Pesanan' : 'Riwayat Pesanan' ?> - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #edf5f0 0%, #dff2e8 100%); color: #1a1a1a; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .navbar { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        .navbar-user { display: flex; align-items: center; gap: 12px; }
        .btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; display: inline-block; }
        .btn-green { background: #2a9d72; color: white; }
        .btn-green:hover { background: #23865f; }
        .btn-logout { background: transparent; color: #555; }
        .btn-logout:hover { background: #f0f0f0; }
        .container { max-width: 1240px; margin: 0 auto; padding: 32px 24px; }
        h1 { font-size: 24px; margin-bottom: 6px; }
        .sub { color: #666; font-size: 14px; margin-bottom: 20px; }
        .flash { background: #e8f5e9; color: #2e7d32; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .card { background: white; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .tabel-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #edf5f0; color: #2a9d72; text-align: left; padding: 12px 14px; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        td { padding: 12px 14px; border-top: 1px solid #f0f0f0; vertical-align: top; }
        .angka { text-align: right; white-space: nowrap; }
        .catatan { color: #777; font-size: 13px; }
        .aksi { display: flex; gap: 8px; }
        .btn-kecil { padding: 6px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #ddd; background: white; cursor: pointer; font-family: inherit; }
        .btn-kecil:hover { background: #f5f5f5; }
        .btn-bahaya { color: #d32f2f; border-color: #f5c6c6; }
        .btn-bahaya:hover { background: #ffebee; }
        .total-bar { display: flex; justify-content: space-between; padding: 16px 14px; border-top: 2px solid #f0f0f0; font-weight: 700; }
        .kosong { text-align: center; padding: 60px 20px; color: #888; }
        .kosong p { margin-bottom: 14px; }
        .kecil { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
    <?php $nb_aktif = 'pesanan'; include __DIR__ . '/komponen_navbar.php'; ?>

    <div class="container">
        <h1><?= $adalah_admin ? 'Semua Pesanan' : 'Riwayat Pesanan' ?></h1>
        <p class="sub">
            <?= $adalah_admin
                ? 'Pesanan dari seluruh pelanggan.'
                : 'Anda bisa mengubah jumlah atau membatalkan pesanan. Pembatalan mengembalikan stok.' ?>
        </p>

        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if ($error_data): ?>
                <div class="kosong">
                    <p><?= e($error_data) ?></p>
                    <?php if ($adalah_admin): ?>
                        <a href="setup_db.php" class="btn btn-green">Perbarui Database</a>
                    <?php else: ?>
                        <p class="kecil">Hubungi admin untuk memperbaikinya.</p>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($pesanan)): ?>
                <div class="kosong">
                    <p><strong>Belum ada pesanan</strong></p>
                    <p class="kecil"><?= $adalah_admin ? 'Pesanan dari pelanggan akan muncul di sini.' : 'Pilih menu favoritmu lalu pesan lewat keranjang.' ?></p>
                    <a href="daftar.php" class="btn btn-green">Lihat Daftar Menu</a>
                </div>
            <?php else: ?>
                <div class="tabel-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <?php if ($adalah_admin): ?><th>Pemesan</th><?php endif; ?>
                                <th class="angka">Jumlah</th>
                                <th class="angka">Harga</th>
                                <th class="angka">Total</th>
                                <th>Pembayaran</th>
                                <th>Catatan</th>
                                <th>Waktu</th>
                                <?php if (!$adalah_admin): ?><th>Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesanan as $p): ?>
                                <tr>
                                    <td><strong><?= e($p['nama_menu']) ?></strong></td>
                                    <?php if ($adalah_admin): ?><td><?= e($p['pemesan']) ?></td><?php endif; ?>
                                    <td class="angka"><?= (int) $p['jumlah'] ?></td>
                                    <td class="angka"><?= formatRupiah($p['harga_satuan']) ?></td>
                                    <td class="angka"><strong><?= formatRupiah((int) $p['jumlah'] * (int) $p['harga_satuan']) ?></strong></td>
                                    <td><?= e($p['metode_bayar']) ?></td>
                                    <td class="catatan"><?= $p['catatan'] !== null ? e($p['catatan']) : '-' ?></td>
                                    <td>
                                        <?= e(date('d/m/Y H:i', strtotime($p['dibuat_pada']))) ?>
                                        <?php if ($p['diperbarui_pada'] !== null): ?>
                                            <div class="kecil">diubah <?= e(date('d/m/Y H:i', strtotime($p['diperbarui_pada']))) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$adalah_admin): ?>
                                        <td>
                                            <div class="aksi">
                                                <a href="pesanan_edit.php?id=<?= (int) $p['id'] ?>" class="btn-kecil">Ubah</a>
                                                <form method="POST" action="pesanan_hapus.php"
                                                      onsubmit="return confirm('Batalkan pesanan ini?');">
                                                    <?= inputCsrf() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                    <button type="submit" class="btn-kecil btn-bahaya">Batalkan</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total-bar">
                    <span><?= count($pesanan) ?> pesanan</span>
                    <span>Total: <?= formatRupiah($total_semua) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php if ($kosongkan_keranjang): ?>
    <script>
        try { localStorage.removeItem('keranjang_u<?= (int) $_SESSION['id'] ?>'); } catch (e) {}
    </script>
<?php endif; ?>
</body>
</html>

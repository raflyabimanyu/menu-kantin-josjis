<?php
/**
 * daftar.php
 * Halaman utama yang menampilkan semua menu kantin
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';
require_once 'fungsi_pesanan.php';

// Wajib login untuk mengakses halaman ini
wajibLogin();

// Ambil pesan flash jika ada
$flash = ambilFlash();

// --- LOGIKA: BACA DATA MENU BERSAMA PENULIS ---
// Kueri JOIN untuk mengambil menu + nama penulis (pengguna)
$sql = 'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.stok, m.tersedia, 
               m.dibuat_pada, u.nama AS penulis
        FROM menu AS m
        JOIN pengguna AS u ON u.id = m.pengguna_id
        WHERE m.dihapus_pada IS NULL
        ORDER BY m.dibuat_pada DESC';

// Filter kategori jika ada
$KATEGORI_VALID = ['Makanan', 'Minum', 'Camilan', 'Dessert', 'Lainnya'];
$kategori_aktif = null;
if (isset($_GET['kategori'])) {
    // Pilihan eksplisit pengguna. Kategori valid disimpan di cookie (30 hari),
    // "semua" atau nilai tak valid menghapus cookie.
    if (is_string($_GET['kategori']) && in_array($_GET['kategori'], $KATEGORI_VALID, true)) {
        $kategori_aktif = $_GET['kategori'];
        setcookie(COOKIE_KATEGORI, $kategori_aktif, opsiCookie(time() + 30 * 86400));
    } else {
        setcookie(COOKIE_KATEGORI, '', opsiCookie(time() - 3600));
    }
} elseif (isset($_COOKIE[COOKIE_KATEGORI]) && in_array($_COOKIE[COOKIE_KATEGORI], $KATEGORI_VALID, true)) {
    // Tidak memilih apa-apa: pakai kategori terakhir yang diingat cookie
    $kategori_aktif = $_COOKIE[COOKIE_KATEGORI];
}
if ($kategori_aktif) {
    $stmt = $db->prepare(
        'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.stok, m.tersedia, 
                m.dibuat_pada, u.nama AS penulis
         FROM menu AS m
         JOIN pengguna AS u ON u.id = m.pengguna_id
         WHERE m.dihapus_pada IS NULL AND m.kategori = ?
         ORDER BY m.dibuat_pada DESC'
    );
    $stmt->bind_param('s', $kategori_aktif);
    $stmt->execute();
    $result = $stmt->get_result();
    $menus = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $db->query($sql);
    $menus = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// --- DATA PENDUKUNG TAMPILAN (tanpa input pengguna) ---
// Semua menu aktif: dipakai untuk jumlah & gambar kategori serta tumpukan foto di hero
$hasil_semua = $db->query(
    'SELECT id, nama_menu, harga, kategori, gambar FROM menu
     WHERE dihapus_pada IS NULL ORDER BY dibuat_pada DESC'
);
$semua_menu = $hasil_semua ? $hasil_semua->fetch_all(MYSQLI_ASSOC) : [];
$info_kategori = [];
foreach ($semua_menu as $mn) {
    $k = $mn['kategori'];
    if (!isset($info_kategori[$k])) {
        $info_kategori[$k] = ['jumlah' => 0, 'gambar' => ambilGambarMenu($mn['id'], $mn['kategori'], $mn['gambar'] ?? null)];
    }
    $info_kategori[$k]['jumlah']++;
}
$daftar_kategori = [
    'Makanan' => ['Makanan', '🍛'],
    'Minum'   => ['Minuman', '🥤'],
    'Camilan' => ['Camilan', '🥟'],
    'Dessert' => ['Dessert', '🍨'],
    'Lainnya' => ['Lainnya', '🍽️'],
];

// Sapaan sesuai jam
$jam_sekarang = (int) date('G');
$sapaan = $jam_sekarang < 11 ? 'Selamat pagi' : ($jam_sekarang < 15 ? 'Selamat siang' : ($jam_sekarang < 18 ? 'Selamat sore' : 'Selamat malam'));
$nama_depan = explode(' ', trim((string) ($_SESSION['nama'] ?? 'Teman')))[0];
$inisial_nama = preg_match('/^./u', $nama_depan, $mi) ? strtoupper($mi[0]) : '?';
$adalah_admin = ($_SESSION['peran'] ?? '') === 'admin';
$nb_aktif = 'beranda';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <link rel="stylesheet" href="beranda.css?v=<?= (int) @filemtime(__DIR__ . '/beranda.css') ?>">
    <link rel="stylesheet" href="keranjang.css?v=<?= (int) @filemtime(__DIR__ . '/keranjang.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/komponen_navbar.php'; ?>

    <!-- Hero -->
    <section class="hero-k">
        <div class="hero-panel">
            <div>
                <div class="hero-sapa">
                    <div class="hero-avatar" aria-hidden="true"><?= e($inisial_nama) ?></div>
                    <div>
                        <strong>Hei, <?= e($nama_depan) ?> 👋</strong>
                        <span><?= e($sapaan) ?></span>
                    </div>
                </div>
                <p class="hero-tanya"><?= $adalah_admin ? '🧑‍🍳 Dapur hari ini' : '😋 Lapar, ya?' ?></p>
                <h1><?= $adalah_admin ? 'Kelola menu kantin' : 'Mau makan apa hari ini?' ?></h1>
                <div class="cari">
                    <input type="text" id="searchInput" placeholder="<?= $adalah_admin ? 'Cari menu yang mau diatur…' : 'Cari menu, misal: nasi goreng' ?>" autocomplete="off" aria-label="Cari menu">
                    <button type="button" onclick="filterMenu()" aria-label="Cari">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    </button>
                </div>
            </div>

            <?php if (count($semua_menu) >= 3): ?>
                <div class="tumpukan" aria-hidden="true">
                    <?php foreach (array_slice($semua_menu, 0, 3) as $mh): ?>
                        <a href="detail.php?id=<?= (int) $mh['id'] ?>" tabindex="-1">
                            <img src="<?= e(ambilGambarMenu($mh['id'], $mh['kategori'], $mh['gambar'] ?? null)) ?>" alt=""
                                 onerror="this.onerror=null;this.src='images/nasi_goreng.png';">
                            <span><?= e($mh['nama_menu']) ?> · <?= formatRupiah($mh['harga']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Kategori -->
    <section class="kat-wrap" id="kategori">
        <div class="judul-seksi"><h2>Kategori</h2></div>
        <div class="kat-grid">
            <a href="?kategori=semua#menu" class="kat-tile semua <?= !$kategori_aktif ? 'aktif' : '' ?>">
                <span class="kat-nama">Semua</span>
                <span class="kat-jumlah"><?= count($semua_menu) ?> menu</span>
                <span class="kat-semua-ikon" aria-hidden="true">🍴</span>
            </a>
            <?php foreach ($daftar_kategori as $kode => [$label, $emoji]): ?>
                <a href="?kategori=<?= e($kode) ?>#menu" class="kat-tile <?= $kategori_aktif === $kode ? 'aktif' : '' ?>">
                    <span class="kat-nama"><?= $emoji ?> <?= e($label) ?></span>
                    <span class="kat-jumlah"><?= (int) ($info_kategori[$kode]['jumlah'] ?? 0) ?> menu</span>
                    <?php if (isset($info_kategori[$kode])): ?>
                        <img src="<?= e($info_kategori[$kode]['gambar']) ?>" alt="" loading="lazy"
                             onerror="this.onerror=null;this.src='images/nasi_goreng.png';">
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Menu -->
    <main class="menu-wrap" id="menu">
        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="judul-seksi">
            <h2>Menu <?= $kategori_aktif ? e($daftar_kategori[$kategori_aktif][0]) : 'Populer' ?></h2>
            <span class="section-count"><?= count($menus) ?> menu ditemukan</span>
        </div>

        <div class="menu-grid" id="menuGrid">
            <?php if (empty($menus)): ?>
                <div class="empty-state">
                    <h3>Belum ada menu di sini</h3>
                    <p>Menu akan muncul setelah ditambahkan.</p>
                    <?php if ($adalah_admin): ?>
                        <a href="tambah.php" class="btn-3d">Tambah menu pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($menus as $menu): ?>
                    <?php $bisa_dipesan = $menu['tersedia'] === 'Ya' && (int) $menu['stok'] > 0; ?>
                    <article class="menu-card" id="menu-<?= (int) $menu['id'] ?>" data-nama="<?= strtolower(e($menu['nama_menu'])) ?>">
                        <a href="detail.php?id=<?= (int) $menu['id'] ?>" class="mk-link">
                            <div class="mk-foto">
                                <img src="<?= e(ambilGambarMenu($menu['id'], $menu['kategori'], $menu['gambar'] ?? null)) ?>?v=<?= time() ?>"
                                     alt="<?= e($menu['nama_menu']) ?>"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='images/nasi_goreng.png';">
                                <span class="mk-status <?= $bisa_dipesan ? '' : 'habis' ?>"><?= $bisa_dipesan ? 'Tersedia' : 'Habis' ?></span>
                            </div>
                            <div class="mk-isi">
                                <p class="mk-kat">
                                    <?= e($daftar_kategori[$menu['kategori']][0] ?? $menu['kategori']) ?>
                                    <?php if ($adalah_admin): ?>
                                        <?php /* Stok HANYA dirender untuk admin */ ?>
                                        <span class="mk-stok <?= (int) $menu['stok'] <= 5 ? 'rendah' : '' ?>">Stok: <?= (int) $menu['stok'] ?></span>
                                    <?php endif; ?>
                                </p>
                                <h3 class="mk-nama"><?= e($menu['nama_menu']) ?></h3>
                                <p class="mk-desk"><?= e($menu['deskripsi']) ?></p>
                                <div class="mk-harga-baris">
                                    <span class="mk-harga"><?= formatRupiah($menu['harga']) ?></span>
                                    <?php if ($adalah_admin): ?>
                                        <span class="mk-oleh">oleh <?= e($menu['penulis']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>

                        <?php if ($adalah_admin): ?>
                            <div class="mk-admin">
                                <form method="POST" action="stok_menu.php" class="stok-cepat" title="Ubah stok 1 porsi">
                                    <?= inputCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                                    <button type="submit" name="aksi" value="kurang" class="btn-stok" aria-label="Kurangi stok <?= e($menu['nama_menu']) ?>" <?= (int) $menu['stok'] <= 0 ? 'disabled' : '' ?>>&minus;</button>
                                    <span class="stok-label" aria-hidden="true">Stok</span>
                                    <button type="submit" name="aksi" value="tambah" class="btn-stok" aria-label="Tambah stok <?= e($menu['nama_menu']) ?>">+</button>
                                </form>
                                <a href="edit.php?id=<?= (int) $menu['id'] ?>" class="btn-kartu">Edit</a>
                                <form method="POST" action="hapus_menu.php"
                                      onsubmit="return confirm('Hapus menu &quot;<?= e(addslashes($menu['nama_menu'])) ?>&quot; dari daftar? Pesanan yang sudah ada tetap tersimpan.');">
                                    <?= inputCsrf() ?>
                                    <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                                    <button type="submit" class="btn-kartu hapus">Hapus</button>
                                </form>
                            </div>
                        <?php elseif ($bisa_dipesan): ?>
                            <div class="mk-bar">
                                <span>Pesan</span>
                                <?= htmlKontrolJumlah((int) $menu['id'], $menu['nama_menu']) ?>
                            </div>
                        <?php else: ?>
                            <div class="mk-bar habis">Habis</div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="footer">
        <p>Menu Kantin &copy; 2026 - Dibuat untuk Tugas Pemrograman Web I</p>
    </div>

    <script>
        function filterMenu() {
            var query = document.getElementById('searchInput').value.toLowerCase().trim();
            var cards = document.querySelectorAll('.menu-card');
            var visible = 0;
            cards.forEach(function(card) {
                var nama = card.getAttribute('data-nama') || '';
                if (query === '' || nama.indexOf(query) !== -1) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            var countEl = document.querySelector('.section-count');
            if (countEl) {
                countEl.textContent = visible + ' menu ditemukan';
            }
        }
        document.getElementById('searchInput').addEventListener('keyup', filterMenu);
        document.getElementById('searchInput').addEventListener('change', filterMenu);
    </script>

    <?php include __DIR__ . '/keranjang_ui.php'; ?>
</body>
</html>

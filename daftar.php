<?php
/**
 * daftar.php
 * Halaman utama yang menampilkan semua menu kantin
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';

// Wajib login untuk mengakses halaman ini
wajibLogin();

// Ambil pesan flash jika ada
$flash = ambilFlash();

// --- LOGIKA: BACA DATA MENU BERSAMA PENULIS ---
// Kueri JOIN untuk mengambil menu + nama penulis (pengguna)
$sql = 'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.tersedia, 
               m.dibuat_pada, u.nama AS penulis
        FROM menu AS m
        JOIN pengguna AS u ON u.id = m.pengguna_id
        ORDER BY m.dibuat_pada DESC';

// Filter kategori jika ada
$kategori_aktif = $_GET['kategori'] ?? null;
if ($kategori_aktif && in_array($kategori_aktif, ['Makanan', 'Minum', 'Camilan', 'Dessert', 'Lainnya'], true)) {
    $stmt = $db->prepare(
        'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.tersedia, 
                m.dibuat_pada, u.nama AS penulis
         FROM menu AS m
         JOIN pengguna AS u ON u.id = m.pengguna_id
         WHERE m.kategori = ?
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

// Format harga ke format Rupiah
function formatRupiah($harga) {
    return 'Rp ' . number_format($harga, 0, ',', '.');
}

// Fungsi untuk mapping ID menu ke file gambar
function ambilGambarMenu($menu_id, $kategori, $gambar_db = null) {
    // 1) Jika admin upload gambar, gunakan itu (folder images/)
    if (!empty($gambar_db)) {
        return 'images/' . $gambar_db;
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
        return 'images/' . $gambar_map[$menu_id];
    }
    
    // 3) Fallback: gunakan kategori
    $kategori_map = [
        'Makanan' => 'nasi_goreng.png',
        'Minum' => 'teh_tarik.png',
        'Camilan' => 'martabak.png',
        'Dessert' => 'es_krim.png',
        'Lainnya' => 'nasi_goreng.png'
    ];
    
    return 'images/' . ($kategori_map[$kategori] ?? 'nasi_goreng.png');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #1a1a1a;
            min-height: 100vh;
        }
        a { text-decoration: none; color: inherit; }

        .navbar {
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 12px 24px;
        }
        .navbar-brand {
            font-size: 26px;
            font-weight: 700;
            color: #00aa13;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: auto;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f0f0f0;
            border-radius: 20px;
            font-size: 13px;
            color: #333;
        }
        .user-info strong::after {
            content: ' (';
        }
        .user-info::after {
            content: ')';
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-add {
            background: #00aa13;
            color: white;
        }
        .btn-add:hover {
            background: #009911;
        }
        .btn-logout {
            background: transparent;
            color: #555;
        }
        .btn-logout:hover {
            background: #f0f0f0;
        }
        
        .hero {
            background: linear-gradient(135deg, #00aa13 0%, #00cc17 100%);
            color: white;
            padding: 48px 24px 56px;
            text-align: center;
        }
        .hero h1 { font-size: 32px; font-weight: 700; margin-bottom: 10px; }
        .hero p { font-size: 16px; opacity: 0.95; max-width: 520px; margin: 0 auto; }

        .search-section {
            max-width: 600px;
            margin: -28px auto 0;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }
        .page-header { display: none; }
        .stats { display: none; }
        
        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.12);
            padding: 4px;
        }
        .search-box input {
            flex: 1;
            border: none;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            background: transparent;
            font-family: inherit;
        }
        .search-box .search-icon { padding: 0 12px; color: #999; font-size: 18px; }
        .search-box button {
            background: #00aa13;
            color: white;
            border: solid;
            border-color: black;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: background 0.2s;
        }
        .search-box button:hover { background: #009911; }

        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }

        .categories {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            scrollbar-width: none;
        }
        .categories::-webkit-scrollbar { display: none; }
        .cat-pill {
            padding: 10px 20px;
            border-radius: 24px;
            border: 1px solid #e0e0e0;
            background: white;
            color: #555;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .cat-pill:hover { border-color: #000000; color: #000000; }
        .cat-pill.active {
            background: #00aa13;
            color: white;
            border-color: #000000;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        h1 { font-size: 20px; font-weight: 700; color: #1a1a1a; }
        .section-count { font-size: 13px; color: #888; font-weight: 500; }

        .flash {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .menu-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.25s ease;
            cursor: pointer;
            display: block;
            border: 1px solid #f0f0f0;
        }
        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }
        .menu-image-wrapper {
            position: relative;
            width: 100%;
            height: 170px;
            overflow: hidden;
            background: #f5f5f5;
            border-radius: 0;
        }
        .menu-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
            display: block;
        }
        .menu-card:hover .menu-image { transform: scale(1.06); }
        .menu-available {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .available-yes {
            background: #00aa13;
            color: white;
        }
        .available-no {
            background: #ff5252;
            color: white;
        }
        .menu-header { display: none; }
        .menu-body {
            padding: 16px;
        }
        .menu-category {
            display: inline-block;
            padding: 4px 10px;
            background: #f0f9f4;
            border-radius: 6px;
            font-size: 11px;
            color: #00aa13;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .menu-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 6px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .menu-description {
            font-size: 13px;
            color: #888;
            margin-bottom: 12px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 39px;
        }
        .menu-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #f5f5f5;
        }
        .menu-price { font-size: 17px; font-weight: 700; color: #00aa13; }
        .menu-meta { font-size: 12px; color: #aaa; }
        .btn-detail { display: none; }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #888;
            grid-column: 1 / -1;
        }
        .empty-state h3 { font-size: 20px; color: #555; margin-bottom: 8px; }
        .empty-state p { margin-bottom: 16px; }
        .empty-state::before { content: ''; font-size: 64px; display: block; margin-bottom: 16px; opacity: 0.5; }

        .footer {
            text-align: center;
            padding: 32px 24px;
            color: #aaa;
            font-size: 13px;
            border-top: 1px solid #eee;
            margin-top: 40px;
        }

        @media (max-width: 768px) {
            .hero { padding: 32px 20px 44px; }
            .hero h1 { font-size: 24px; }
            .menu-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
            .menu-image-wrapper { height: 130px; }
            .menu-body { padding: 12px; }
            .menu-title { font-size: 14px; }
            .menu-description { font-size: 12px; min-height: 36px; }
            .menu-price { font-size: 15px; }
            .menu-meta { display: none; }
            .navbar { padding: 10px 16px; }
            .user-info { display: none; }
            .container { padding: 24px 16px; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="daftar.php" class="navbar-brand"><img src="images/logo.png" alt="Logo Menu Kantin" style="height:40px;"></a>
        <div class="navbar-user">
            <div class="user-info">
                <strong><?= e($_SESSION['nama'] ?? '') ?></strong> (<?= e($_SESSION['peran'] ?? '') ?>)
            </div>
            <?php if ($_SESSION['peran'] === 'admin'): ?>
                <a href="tambah.php" class="btn btn-add">+ Tambah Menu</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-logout">Keluar</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <h1><?= $_SESSION['peran'] === 'admin' ? 'Kelola Menu Kantin' : 'Pesan Menu Favoritmu!' ?></h1>
        <p><?= $_SESSION['peran'] === 'admin' ? 'Tambah, edit, dan atur menu makanan serta minuman kantin.' : 'Temukan berbagai hidangan lezat dari kantin kami. Pilih, pesan, dan nikmati.' ?></p>
    </section>

    <!-- Search -->
    <div class="search-section">
        <div class="search-box">
            <span class="search-icon"></span>
            <input type="text" id="searchInput" placeholder="Cari menu... (misal: nasi goreng)" autocomplete="off">
            <button type="button" onclick="filterMenu()">Cari</button>
        </div>
    </div>

    <!-- Main -->
    <div class="container">
        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <!-- Categories -->
        <div class="categories">
            <a href="daftar.php" class="cat-pill <?= !$kategori_aktif ? 'active' : '' ?>">Semua</a>
            <a href="?kategori=Makanan" class="cat-pill <?= $kategori_aktif === 'Makanan' ? 'active' : '' ?>">Makanan</a>
            <a href="?kategori=Minum" class="cat-pill <?= $kategori_aktif === 'Minum' ? 'active' : '' ?>">Minuman</a>
            <a href="?kategori=Camilan" class="cat-pill <?= $kategori_aktif === 'Camilan' ? 'active' : '' ?>">Camilan</a>
            <a href="?kategori=Dessert" class="cat-pill <?= $kategori_aktif === 'Dessert' ? 'active' : '' ?>">Dessert</a>
            <a href="?kategori=Lainnya" class="cat-pill <?= $kategori_aktif === 'Lainnya' ? 'active' : '' ?>">Lainnya</a>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <h1>Menu <?= $kategori_aktif ? e($kategori_aktif) : 'Populer' ?></h1>
            <span class="section-count"><?= count($menus) ?> menu ditemukan</span>
        </div>

        <!-- Menu Grid -->
        <div class="menu-grid" id="menuGrid">
            <?php if (empty($menus)): ?>
                <div class="empty-state">
                    <h3>Belum ada menu tersedia</h3>
                    <p>Menu akan muncul di sini setelah ditambahkan.</p>
                    <?php if ($_SESSION['peran'] === 'admin'): ?>
                        <a href="tambah.php" class="btn btn-add">Tambahkan Menu Pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($menus as $menu): ?>
                    <a href="detail.php?id=<?= (int)$menu['id'] ?>" class="menu-card" data-nama="<?= strtolower(e($menu['nama_menu'])) ?>">
                        <div class="menu-image-wrapper">
                            <img src="<?= e(ambilGambarMenu($menu['id'], $menu['kategori'], $menu['gambar'] ?? null)) ?>?v=<?= time() ?>"
                                 alt="<?= e($menu['nama_menu']) ?>"
                                 class="menu-image"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='images/nasi_goreng.png';">
                            <span class="menu-available <?= $menu['tersedia'] === 'Ya' ? 'available-yes' : 'available-no' ?>">
                                <?= $menu['tersedia'] === 'Ya' ? 'Tersedia' : 'Habis' ?>
                            </span>
                        </div>
                        <div class="menu-body">
                            <span class="menu-category"><?= e($menu['kategori']) ?></span>
                            <h3 class="menu-title"><?= e($menu['nama_menu']) ?></h3>
                            <p class="menu-description"><?= e($menu['deskripsi']) ?></p>
                            <div class="menu-footer">
                                <span class="menu-price"><?= formatRupiah($menu['harga']) ?></span>
                                <span class="menu-meta">oleh <?= e($menu['penulis']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
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
</body>
</html>

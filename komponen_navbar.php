<?php
/**
 * komponen_navbar.php
 * Navbar melayang bersama untuk semua halaman setelah login.
 * Isi $nb_aktif sebelum include: 'beranda' | 'pesanan' | 'akun' (atau kosong).
 */
$nb_aktif = $nb_aktif ?? '';
$nb_admin = ($_SESSION['peran'] ?? '') === 'admin';
?>
<link rel="stylesheet" href="tema.css?v=<?= (int) @filemtime(__DIR__ . '/tema.css') ?>">
<header class="nb-wrap">
    <nav class="nb" aria-label="Navigasi utama">
        <a href="daftar.php" class="nb-logo"><img src="images/logo.png" alt="Logo Menu Kantin"></a>

        <ul class="nb-tabs">
            <li>
                <a href="daftar.php" class="nb-tab <?= $nb_aktif === 'beranda' ? 'aktif' : '' ?>" <?= $nb_aktif === 'beranda' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>
                    <span>Beranda</span>
                </a>
            </li>
            <li>
                <a href="daftar.php#kategori" class="nb-tab">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    <span>Kategori</span>
                </a>
            </li>
            <li>
                <a href="pesanan.php" class="nb-tab <?= $nb_aktif === 'pesanan' ? 'aktif' : '' ?>" <?= $nb_aktif === 'pesanan' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12M6 21h12"/><path d="M7 3c0 5 5 6 5 9s-5 4-5 9"/><path d="M17 3c0 5-5 6-5 9s5 4 5 9"/></svg>
                    <span><?= $nb_admin ? 'Semua Pesanan' : 'Pesanan' ?></span>
                </a>
            </li>
            <li>
                <a href="profil.php" class="nb-tab <?= $nb_aktif === 'akun' ? 'aktif' : '' ?>" <?= $nb_aktif === 'akun' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                    <span>Akun</span>
                </a>
            </li>
        </ul>

        <div class="nb-kanan">
            <?php include __DIR__ . '/komponen_profil.php'; ?>
        </div>
    </nav>
</header>

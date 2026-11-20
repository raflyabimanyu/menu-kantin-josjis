<?php
/**
 * komponen_profil.php
 * Avatar + dropdown akun di pojok kanan atas navbar (gaya menu akun modern).
 * Dipakai dengan:  <?php include __DIR__ . '/komponen_profil.php'; ?>  di dalam <nav>.
 * Data dibaca dari SESSION (tanpa query database di setiap halaman).
 */

require_once __DIR__ . '/fungsi_profil.php';

if (sudahLogin()):
    $pm_nama = (string) ($_SESSION['nama'] ?? '');
    $pm_username = (string) ($_SESSION['username'] ?? '');
    $pm_admin = ($_SESSION['peran'] ?? '') === 'admin';
    $pm_foto = urlFotoProfil($_SESSION['foto_profil'] ?? null);
    $pm_inisial = mb_strtoupper(mb_substr($pm_nama !== '' ? $pm_nama : $pm_username, 0, 1));
?>
<link rel="stylesheet" href="profil_menu.css?v=<?= (int) @filemtime(__DIR__ . '/profil_menu.css') ?>">
<div class="pm" id="pmRoot">
    <button type="button" class="pm-trigger" id="pmTrigger" aria-haspopup="menu" aria-expanded="false"
            aria-controls="pmMenu" aria-label="Menu akun <?= e($pm_nama) ?>">
        <span class="pm-avatar">
            <?php if ($pm_foto): ?>
                <img src="<?= e($pm_foto) ?>" alt="">
            <?php else: ?>
                <?= e($pm_inisial) ?>
            <?php endif; ?>
        </span>
    </button>

    <div class="pm-menu" id="pmMenu" role="menu" aria-labelledby="pmTrigger">
        <div class="pm-head">
            <span class="pm-avatar pm-avatar-besar">
                <?php if ($pm_foto): ?>
                    <img src="<?= e($pm_foto) ?>" alt="">
                <?php else: ?>
                    <?= e($pm_inisial) ?>
                <?php endif; ?>
            </span>
            <div class="pm-identitas">
                <div class="pm-nama"><?= e($pm_nama) ?></div>
                <div class="pm-user">@<?= e($pm_username) ?></div>
            </div>
        </div>

        <div class="pm-sep" role="separator"></div>

        <?php if ($pm_admin): ?>
        <a href="tambah.php" class="pm-item" role="menuitem" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
            Tambah Menu
        </a>
        <?php endif; ?>
        <a href="profil.php" class="pm-item" role="menuitem" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
            Profil Pengguna
        </a>
        <a href="pesanan.php" class="pm-item" role="menuitem" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
            <?= $pm_admin ? 'Semua Pesanan' : 'History Pesanan' ?>
        </a>

        <div class="pm-sep" role="separator"></div>

        <form method="POST" action="logout.php" role="none">
            <?= inputCsrf() ?>
            <button type="submit" class="pm-item pm-keluar" role="menuitem" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                Keluar
            </button>
        </form>
    </div>
</div>
<script src="profil_menu.js?v=<?= (int) @filemtime(__DIR__ . '/profil_menu.js') ?>" defer></script>
<?php endif; ?>

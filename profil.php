<?php
/**
 * profil.php
 * Pengaturan profil pengguna.
 * - Nama asli: HANYA DIBACA (server mengabaikan kiriman "nama" apa pun)
 * - Dapat diubah: foto profil, username, bio
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'fungsi_profil.php';

wajibLogin();

$uid = (int) $_SESSION['id'];

// Data pengguna saat ini (id dari SESSION, bukan dari form)
$stmt = $db->prepare('SELECT username, nama, bio, foto_profil FROM pengguna WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$pengguna = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pengguna) {
    // Akun sudah tidak ada: akhiri sesi
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

$errors = [];
$input = [
    'username' => $pengguna['username'],
    'bio'      => (string) ($pengguna['bio'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibCsrf('profil.php', 'Kembali ke Profil');

    // 1) Ambil & bersihkan masukan. Field "nama" sengaja TIDAK dibaca.
    $username = strtolower(bersihkanTeks(ambilPost('username')));
    $bio      = bersihkanBio(ambilPost('bio'));
    $hapus_foto = ambilPost('hapus_foto') === '1';
    $input = ['username' => $username, 'bio' => $bio];

    // 2) Validasi sisi server
    if ($e = validasiUsername($username)) {
        $errors['username'] = $e;
    }
    if (mb_strlen($bio) > BIO_MAKS) {
        $errors['bio'] = 'Bio maksimal ' . BIO_MAKS . ' karakter.';
    }
    [$ekstensi, $err_foto] = periksaFotoProfil($_FILES['foto'] ?? null);
    if ($err_foto) {
        $errors['foto'] = $err_foto;
    }

    // 3) Simpan (file baru baru ditulis ke disk setelah SEMUA validasi lolos)
    if (empty($errors)) {
        $foto_lama = $pengguna['foto_profil'];
        $foto_baru = $foto_lama;
        $file_baru = null;

        if ($ekstensi !== null) {
            $file_baru = simpanFotoProfil($_FILES['foto'], $ekstensi, $uid);
            if ($file_baru === null) {
                $errors['foto'] = 'Gagal menyimpan foto. Pastikan folder uploads/profil/ bisa ditulis.';
            } else {
                $foto_baru = $file_baru;
            }
        } elseif ($hapus_foto) {
            $foto_baru = null;
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare('UPDATE pengguna SET username = ?, bio = ?, foto_profil = ? WHERE id = ?');
                $bio_db = $bio === '' ? null : $bio;
                $stmt->bind_param('sssi', $username, $bio_db, $foto_baru, $uid);
                $stmt->execute();
                $stmt->close();

                // Berhasil: buang file lama yang tidak dipakai lagi
                if ($foto_lama !== null && $foto_lama !== $foto_baru) {
                    hapusFotoProfil($foto_lama);
                }

                // Sinkronkan session agar navbar langsung memakai data baru
                $_SESSION['username'] = $username;
                $_SESSION['foto_profil'] = $foto_baru;

                setFlash('Profil berhasil diperbarui.');
                header('Location: profil.php');
                exit;
            } catch (mysqli_sql_exception $ex) {
                hapusFotoProfil($file_baru); // jangan tinggalkan file yatim
                if ($ex->getCode() === 1062) {
                    $errors['username'] = 'Username sudah dipakai. Silakan pilih username lain.';
                } else {
                    error_log('Update profil gagal: ' . $ex->getMessage());
                    $errors['umum'] = 'Terjadi kesalahan saat menyimpan. Silakan coba lagi.';
                }
            }
        }
    }
}

$flash = ambilFlash();
$foto_url = urlFotoProfil($pengguna['foto_profil']);
$inisial = mb_strtoupper(mb_substr($pengguna['nama'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #edf5f0 0%, #dff2e8 100%); color: #1a1a1a; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .navbar { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 100; }
        .navbar-kanan { display: flex; align-items: center; gap: 14px; }
        .navbar-kanan > a { color: #555; font-weight: 500; font-size: 14px; }
        .container { max-width: 640px; margin: 32px auto; padding: 0 20px; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        .sub { color: #666; font-size: 14px; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 28px; }
        .flash { background: #e8f5e9; color: #2e7d32; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .error-box { background: #ffebee; color: #d32f2f; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #d32f2f; font-size: 14px; }
        .foto-baris { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
        .foto-besar { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; flex: none; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); color: #fff; font-size: 40px; font-weight: 700; }
        .foto-besar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .foto-aksi { flex: 1; min-width: 200px; }
        .btn-file { display: inline-block; padding: 9px 16px; border-radius: 8px; border: 1px solid #ddd; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-file:hover { background: #f5f5f5; }
        .btn-file input { display: none; }
        .cek { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px; color: #666; font-weight: 400; cursor: pointer; }
        .form-group { margin-bottom: 20px; }
        label.judul { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        input[type="text"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; }
        input[readonly] { background: #f5f5f5; color: #777; cursor: not-allowed; }
        textarea { height: 110px; resize: none; }
        input:focus, textarea:focus { outline: none; border-color: #2a9d72; }
        .prefix { display: flex; align-items: center; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .prefix span { padding: 12px 0 12px 12px; color: #888; }
        .prefix input { border: none; padding-left: 4px; }
        .prefix:focus-within { border-color: #2a9d72; }
        small { display: block; margin-top: 6px; color: #888; font-size: 12px; }
        .error { color: #d32f2f; font-size: 13px; margin-top: 6px; }
        .hitung { text-align: right; }
        .btn-group { display: flex; gap: 12px; margin-top: 8px; }
        .btn { flex: 1; padding: 14px; border-radius: 10px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; text-align: center; font-family: inherit; }
        .btn-simpan { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); color: #fff; }
        .btn-batal { background: #f5f5f5; color: #666; }
    </style>
</head>
<body>
    <?php $nb_aktif = 'akun'; include __DIR__ . '/komponen_navbar.php'; ?>

    <div class="container">
        <h1>Profil Pengguna</h1>
        <p class="sub">Atur foto, username, dan bio akunmu.</p>

        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors['umum'])): ?>
            <div class="error-box"><?= e($errors['umum']) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= inputCsrf() ?>

                <div class="foto-baris">
                    <div class="foto-besar" id="fotoPratinjau">
                        <?php if ($foto_url): ?>
                            <img src="<?= e($foto_url) ?>" alt="Foto profil">
                        <?php else: ?>
                            <?= e($inisial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="foto-aksi">
                        <label class="btn-file">
                            Ganti Foto
                            <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                        </label>
                        <?php if ($pengguna['foto_profil'] !== null): ?>
                            <label class="cek"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto profil</label>
                        <?php endif; ?>
                        <small>JPG, PNG, atau WEBP. Maksimal 2 MB.</small>
                        <?php if (!empty($errors['foto'])): ?>
                            <div class="error"><?= e($errors['foto']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="judul" for="nama">Nama Asli</label>
                    <input type="text" id="nama" value="<?= e($pengguna['nama']) ?>" readonly tabindex="-1" aria-describedby="nama-info">
                    <small id="nama-info">Nama asli tidak dapat diubah.</small>
                </div>

                <div class="form-group">
                    <label class="judul" for="username">Username</label>
                    <div class="prefix">
                        <span>@</span>
                        <input type="text" id="username" name="username" value="<?= e($input['username']) ?>"
                               minlength="3" maxlength="30" pattern="[a-z0-9_]+" required autocomplete="username">
                    </div>
                    <?php if (!empty($errors['username'])): ?>
                        <div class="error"><?= e($errors['username']) ?></div>
                    <?php endif; ?>
                    <small>3-30 karakter: huruf kecil, angka, dan underscore (_). Dipakai untuk login.</small>
                </div>

                <div class="form-group">
                    <label class="judul" for="bio">Bio</label>
                    <textarea id="bio" name="bio" maxlength="<?= BIO_MAKS ?>" placeholder="Ceritakan sedikit tentang dirimu"><?= e($input['bio']) ?></textarea>
                    <?php if (!empty($errors['bio'])): ?>
                        <div class="error"><?= e($errors['bio']) ?></div>
                    <?php endif; ?>
                    <small class="hitung"><span id="bioHitung"><?= mb_strlen($input['bio']) ?></span>/<?= BIO_MAKS ?></small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-simpan">Simpan Perubahan</button>
                    <a href="daftar.php" class="btn btn-batal">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Pratinjau foto sebelum disimpan + penghitung karakter bio
        (function () {
            var input = document.getElementById('foto');
            var kotak = document.getElementById('fotoPratinjau');
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f || !/^image\//.test(f.type)) { return; }
                var url = URL.createObjectURL(f);
                kotak.textContent = '';
                var img = document.createElement('img');
                img.alt = 'Pratinjau foto';
                img.src = url;
                kotak.appendChild(img);
            });
            var bio = document.getElementById('bio'), hitung = document.getElementById('bioHitung');
            bio.addEventListener('input', function () { hitung.textContent = bio.value.length; });
        })();
    </script>
</body>
</html>

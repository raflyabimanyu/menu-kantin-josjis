<?php
/**
 * fungsi_profil.php
 * Validasi & penyimpanan data profil pengguna (username, bio, foto) + URL foto untuk tampilan.
 */

const FOTO_PROFIL_DIR = __DIR__ . '/../uploads/profil/';
const FOTO_PROFIL_MAKS_BYTE = 2 * 1024 * 1024; // 2 MB
const BIO_MAKS = 200;

/**
 * Username: 3-30 karakter, huruf kecil/angka/underscore (sama dengan aturan registrasi).
 * @return string|null pesan error, atau null bila valid
 */
function validasiUsername(string $username): ?string
{
    $panjang = mb_strlen($username);
    if ($panjang < 3 || $panjang > 30) {
        return 'Username harus 3 sampai 30 karakter.';
    }
    if (!preg_match('/^[a-z0-9_]+$/', $username)) {
        return 'Username hanya boleh huruf kecil, angka, dan underscore (_).';
    }
    return null;
}

/**
 * Bersihkan bio: seragamkan baris baru, buang karakter kontrol, rapikan spasi.
 * (Escape HTML dilakukan saat ditampilkan dengan e(), bukan di sini.)
 */
function bersihkanBio(string $bio): string
{
    $bio = str_replace(["\r\n", "\r"], "\n", $bio);
    $bio = preg_replace('/[^\P{C}\n]+/u', '', $bio) ?? '';   // buang karakter kontrol kecuali \n
    $bio = preg_replace("/\n{3,}/", "\n\n", $bio) ?? '';
    return trim($bio);
}

/**
 * URL foto profil untuk <img>, atau null bila tidak ada / nama file tidak sesuai pola kita.
 * Pola ketat mencegah path traversal walau nilai di database dimanipulasi.
 */
function urlFotoProfil(?string $namaFile): ?string
{
    if ($namaFile === null || !preg_match('/^u\d+_[a-f0-9]{12}\.(jpg|png|webp)$/', $namaFile)) {
        return null;
    }
    $path = FOTO_PROFIL_DIR . $namaFile;
    if (!is_file($path)) {
        return null;
    }
    return 'uploads/profil/' . $namaFile . '?v=' . (int) filemtime($path);
}

/**
 * Periksa foto yang diunggah (BELUM menyimpannya).
 * @return array{0:?string,1:?string} [ekstensi, pesan error]; ekstensi null bila tidak ada file
 */
function periksaFotoProfil(?array $file): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'Ukuran foto terlalu besar (maksimal 2 MB).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'Terjadi kesalahan saat mengunggah foto.'];
    }
    if ($file['size'] > FOTO_PROFIL_MAKS_BYTE) {
        return [null, 'Ukuran foto terlalu besar (maksimal 2 MB).'];
    }

    // Tipe ditentukan dari ISI file, bukan dari nama/Content-Type kiriman pengguna
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $peta = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($peta[$mime])) {
        return [null, 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.'];
    }
    // Harus benar-benar bisa dibaca sebagai gambar
    if (@getimagesize($file['tmp_name']) === false) {
        return [null, 'File bukan gambar yang valid.'];
    }
    return [$peta[$mime], null];
}

/**
 * Simpan foto yang sudah lolos periksaFotoProfil(). Nama file dibuat acak oleh server.
 * @return string|null nama file, atau null bila gagal menyimpan
 */
function simpanFotoProfil(array $file, string $ekstensi, int $penggunaId): ?string
{
    if (!is_dir(FOTO_PROFIL_DIR) && !@mkdir(FOTO_PROFIL_DIR, 0755, true)) {
        return null;
    }
    $nama = 'u' . $penggunaId . '_' . bin2hex(random_bytes(6)) . '.' . $ekstensi;
    return move_uploaded_file($file['tmp_name'], FOTO_PROFIL_DIR . $nama) ? $nama : null;
}

/** Hapus file foto (hanya yang cocok pola nama kita). */
function hapusFotoProfil(?string $namaFile): void
{
    if ($namaFile !== null && preg_match('/^u\d+_[a-f0-9]{12}\.(jpg|png|webp)$/', $namaFile)) {
        $path = FOTO_PROFIL_DIR . $namaFile;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

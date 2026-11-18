<?php
/** Sesi, masuk/keluar, dan pemeriksaan peran. */

declare(strict_types=1);

function mulaiSesi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $aman = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $aman,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('RAJIMAN');
    session_start();

    // Keluar sendiri bila terlalu lama menganggur.
    $batas = (int)konfig('sesi_menit') * 60;
    if (!empty($_SESSION['akhir']) && time() - (int)$_SESSION['akhir'] > $batas) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['akhir'] = time();
}

/** Data pengguna yang sedang masuk, atau null. */
function saya(): ?array
{
    if (empty($_SESSION['username'])) return null;

    static $u = null;
    if ($u === null) {
        $u = satu('SELECT username, nama, peran, email, id_penghuni, aktif FROM pengguna WHERE username = ?',
                  [$_SESSION['username']]);
        if (!$u || (int)$u['aktif'] !== 1) {
            keluarSesi();
            return null;
        }
    }
    return $u;
}

function sudahMasuk(): bool
{
    return saya() !== null;
}

function peran(): string
{
    return saya()['peran'] ?? '';
}

function pemilik(): bool
{
    return peran() === 'pemilik';
}

/** Pemilik dan pengelola boleh mengelola; penyewa tidak. */
function bolehKelola(): bool
{
    return in_array(peran(), ['pemilik', 'pengelola'], true);
}

/** Pagar halaman pengelola. */
function wajibKelola(): void
{
    if (!sudahMasuk())  pergi('masuk.php');
    if (!bolehKelola()) pergi('portal.php');
}

/** Pagar halaman khusus pemilik. */
function wajibPemilik(): void
{
    wajibKelola();
    if (!pemilik()) {
        http_response_code(403);
        exit('Halaman ini hanya untuk pemilik.');
    }
}

/** Pagar halaman penyewa. */
function wajibPenyewa(): void
{
    if (!sudahMasuk()) pergi('masuk.php');
    if (peran() !== 'penyewa') pergi('index.php');
}

/**
 * Coba masuk. Kembalikan null bila berhasil, atau pesan galat.
 * Pesannya sengaja sama untuk username salah dan sandi salah,
 * supaya tidak bisa dipakai menebak username yang terdaftar.
 */
function cobaMasuk(string $username, string $sandi): ?string
{
    $username = strtolower(trim($username));
    $ip       = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    // Tahan percobaan beruntun: maksimal 8 kegagalan per 15 menit per IP.
    $gagal = (int)nilai(
        'SELECT COUNT(*) FROM log_masuk WHERE ip = ? AND berhasil = 0 AND tgl > ?',
        [$ip, date('Y-m-d H:i:s', time() - 900)], 0
    );
    if ($gagal >= 8) {
        return 'Terlalu banyak percobaan masuk. Coba lagi sekitar 15 menit.';
    }

    $u = satu('SELECT username, hash, aktif FROM pengguna WHERE username = ?', [$username]);
    $ok = $u && (int)$u['aktif'] === 1 && password_verify($sandi, $u['hash']);

    sisip('log_masuk', [
        'username' => $username,
        'ip'       => $ip,
        'berhasil' => $ok ? 1 : 0,
        'tgl'      => sekarang(),
    ]);

    if (!$ok) {
        return 'Username atau password salah.';
    }

    // Perbarui hash bila algoritmanya sudah usang.
    if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
        ubah('pengguna', ['hash' => password_hash($sandi, PASSWORD_DEFAULT)], 'username', $username);
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['akhir']    = time();
    return null;
}

function keluarSesi(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Aturan sandi minimum. Kembalikan null bila lolos. */
function sandiLemah(string $sandi): ?string
{
    if (mb_strlen($sandi) < 8)        return 'Password minimal 8 karakter.';
    if (!preg_match('/[A-Za-z]/', $sandi)) return 'Password harus memuat huruf.';
    if (!preg_match('/[0-9]/', $sandi))    return 'Password harus memuat angka.';
    return null;
}

/** Aturan username. Kembalikan null bila lolos. */
function usernameSalah(string $u): ?string
{
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $u)) {
        return 'Username 3-30 karakter: huruf kecil, angka, titik, strip, atau garis bawah.';
    }
    return null;
}

<?php
/**
 * Koneksi database dan pembantu kueri.
 * Semua kueri memakai prepared statement, jadi aman dari SQL injection.
 */

declare(strict_types=1);

function konfig(?string $kunci = null)
{
    static $c = null;
    if ($c === null) {
        $berkas = __DIR__ . '/../config.php';
        if (!is_file($berkas)) {
            http_response_code(500);
            exit('Berkas config.php belum dibuat. Salin config/config.contoh.php ke app/config.php lalu isi data database.');
        }
        $c = require $berkas;
        $c += ['db_host' => 'localhost', 'zona' => 'Asia/Jakarta', 'sesi_menit' => 240, 'db_driver' => 'mysql'];
    }
    return $kunci === null ? $c : ($c[$kunci] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $k = konfig();
    $opsi = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        if (($k['db_driver'] ?? 'mysql') === 'sqlite') {
            // Hanya dipakai saat pengujian di komputer pengembang.
            $pdo = new PDO('sqlite:' . $k['db_nama'], null, null, $opsi);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $k['db_host'], $k['db_nama']);
            $pdo = new PDO($dsn, $k['db_user'], $k['db_pass'], $opsi);
            $pdo->exec("SET time_zone = '+07:00'");
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Koneksi database gagal: ' . $e->getMessage());
        exit('Tidak bisa terhubung ke database. Periksa data di app/config.php.');
    }
    return $pdo;
}

/** Jalankan kueri dan kembalikan statement-nya. */
function q(string $sql, array $p = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}

/** Satu baris, atau null bila tidak ada. */
function satu(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

/** Semua baris. */
function semua(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

/** Satu nilai dari kolom pertama. */
function nilai(string $sql, array $p = [], $bawaan = null)
{
    $r = q($sql, $p)->fetchColumn();
    return $r === false ? $bawaan : $r;
}

/** Sisipkan satu baris dari larik kolom => nilai. */
function sisip(string $tabel, array $data): void
{
    $kolom = array_keys($data);
    $isi   = implode(', ', array_map(fn($k) => ':' . $k, $kolom));
    q('INSERT INTO ' . $tabel . ' (' . implode(', ', $kolom) . ') VALUES (' . $isi . ')', $data);
}

/** Ubah satu baris berdasarkan kolom kunci. */
function ubah(string $tabel, array $data, string $kolomKunci, $nilaiKunci): void
{
    $set = implode(', ', array_map(fn($k) => $k . ' = :' . $k, array_keys($data)));
    $data['__kunci'] = $nilaiKunci;
    q('UPDATE ' . $tabel . ' SET ' . $set . ' WHERE ' . $kolomKunci . ' = :__kunci', $data);
}

/** Hapus baris berdasarkan kolom kunci. */
function hapus(string $tabel, string $kolomKunci, $nilaiKunci): void
{
    q('DELETE FROM ' . $tabel . ' WHERE ' . $kolomKunci . ' = ?', [$nilaiKunci]);
}

/** Apakah tabel inti sudah ada? Dipakai pasang.php. */
function sudahDipasang(): bool
{
    try {
        nilai('SELECT COUNT(*) FROM pengguna');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

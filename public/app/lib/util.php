<?php
/** Fungsi pembantu umum: format, keamanan keluaran, tanggal, dan nomor. */

declare(strict_types=1);

/** Lolos karakter HTML. Dipakai di SETIAP keluaran ke halaman. */
function h($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Rupiah: 2300000 -> "Rp 2.300.000" */
function rp($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

/** Tanggal pendek: 2026-11-18 -> "18 Nov 2026" */
function tglPendek(?string $t): string
{
    if (!$t) return '-';
    $d = date_create($t);
    if (!$d) return '-';
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return $d->format('j') . ' ' . $bulan[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

/** Tanggal panjang: 2026-11-18 -> "18 November 2026" */
function tglPanjang(?string $t): string
{
    if (!$t) return '-';
    $d = date_create($t);
    if (!$d) return '-';
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $d->format('j') . ' ' . $bulan[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

/** Periode 2026-11 -> "November 2026" */
function periodeTeks(?string $p): string
{
    if (!$p || strlen($p) < 7) return '-';
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $bulan[(int)substr($p, 5, 2) - 1] . ' ' . substr($p, 0, 4);
}

/** Waktu sekarang dalam zona aplikasi. */
function sekarang(string $format = 'Y-m-d H:i:s'): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(konfig('zona'))))->format($format);
}

function hariIni(): string
{
    return sekarang('Y-m-d');
}

/** ID baru, contoh: uid('KMR') -> "KMR-3F9A21C8" */
function uid(string $awalan): string
{
    return $awalan . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/** Ambil nilai dari POST, sudah dipangkas dan dibatasi panjangnya. */
function post(string $nama, int $maks = 200): string
{
    $v = $_POST[$nama] ?? '';
    if (!is_string($v)) return '';
    return mb_substr(trim($v), 0, $maks);
}

/** Ambil angka dari POST. */
function postAngka(string $nama): float
{
    $v = preg_replace('/[^0-9.\-]/', '', (string)($_POST[$nama] ?? ''));
    return (float)($v === '' ? 0 : $v);
}

/** Ambil nilai dari query string. */
function get(string $nama, int $maks = 200): string
{
    $v = $_GET[$nama] ?? '';
    if (!is_string($v)) return '';
    return mb_substr(trim($v), 0, $maks);
}

/** Tanggal dari POST, atau null bila kosong/tidak sah. */
function postTanggal(string $nama): ?string
{
    $v = post($nama, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

/** Pindah halaman lalu berhenti. */
function pergi(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Pesan singkat yang tampil sekali setelah pindah halaman. */
function kabar(?string $teks = null, string $jenis = 'ok'): ?array
{
    if ($teks !== null) {
        $_SESSION['kabar'] = ['teks' => $teks, 'jenis' => $jenis];
        return null;
    }
    $k = $_SESSION['kabar'] ?? null;
    unset($_SESSION['kabar']);
    return $k;
}

/** Token anti pemalsuan permintaan (CSRF). */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function kolomCsrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(tokenCsrf()) . '">';
}

/** Hentikan permintaan POST yang tidak membawa token sah. */
function periksaCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $kirim = (string)($_POST['_csrf'] ?? '');
    if (!$kirim || !hash_equals($_SESSION['csrf'] ?? '', $kirim)) {
        http_response_code(400);
        exit('Permintaan tidak sah. Muat ulang halaman lalu coba lagi.');
    }
}

/** Nomor WhatsApp: 08123 -> 628123 */
function noWa(?string $hp): string
{
    $d = preg_replace('/\D/', '', (string)$hp);
    if ($d === '') return '';
    if (str_starts_with($d, '0'))  return '62' . substr($d, 1);
    if (str_starts_with($d, '62')) return $d;
    return '62' . $d;
}

/** Selisih hari antara dua tanggal (ke - dari). */
function selisihHari(string $dari, string $ke): int
{
    $a = date_create($dari);
    $b = date_create($ke);
    if (!$a || !$b) return 0;
    return (int)$a->diff($b)->format('%r%a');
}

/** Tambah bulan pada tanggal, aman untuk akhir bulan. */
function tambahBulan(string $tgl, int $n): string
{
    $d = date_create($tgl);
    if (!$d) return $tgl;
    $hari = (int)$d->format('j');
    $d->modify('first day of this month')->modify("+{$n} months");
    $maks = (int)$d->format('t');
    return $d->setDate((int)$d->format('Y'), (int)$d->format('n'), min($hari, $maks))->format('Y-m-d');
}

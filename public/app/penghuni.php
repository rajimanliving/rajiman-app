<?php
/** Penghuni: daftar, tambah, ubah, tandai keluar. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();
if (!sudahDipasang()) pergi('pasang.php');
wajibKelola();
require __DIR__ . '/lib/layout.php';

$aksi   = get('aksi', 10);
$id     = get('id', 24);
$filter = get('status', 10) ?: 'Aktif';

const PAKET = ['Bulanan', '6 Bulan', 'Tahunan'];

/** Samakan status kamar dengan ada/tidaknya penghuni aktif. */
function segarkanStatusKamar(?string $idKamar): void
{
    if (!$idKamar) return;
    $k = satu('SELECT status FROM kamar WHERE id_kamar = ?', [$idKamar]);
    if (!$k || $k['status'] === 'Perbaikan') return;   // status perbaikan diatur manual
    $ada = (int)nilai("SELECT COUNT(*) FROM penghuni WHERE id_kamar = ? AND status = 'Aktif'", [$idKamar], 0);
    ubah('kamar', ['status' => $ada > 0 ? 'Terisi' : 'Kosong'], 'id_kamar', $idKamar);
}

/* ---------- Simpan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi !== 'keluar') {
    periksaCsrf();
    $lama = $id ? satu('SELECT id_kamar FROM penghuni WHERE id_penghuni = ?', [$id]) : null;

    $data = [
        'nama'             => post('nama', 80),
        'no_hp'            => post('no_hp', 30),
        'email'            => post('email', 120),
        'id_kamar'         => post('id_kamar', 24) ?: null,
        'jenis_kelamin'    => in_array(post('jenis_kelamin', 20), ['Laki-laki', 'Perempuan'], true) ? post('jenis_kelamin', 20) : null,
        'nik'              => preg_replace('/\D/', '', post('nik', 16)),
        'alamat_asal'      => post('alamat_asal', 400),
        'pekerjaan'        => post('pekerjaan', 60),
        'kontak_darurat'   => post('kontak_darurat', 60),
        'hp_darurat'       => post('hp_darurat', 30),
        'hubungan_darurat' => post('hubungan_darurat', 30),
        'tgl_masuk'        => postTanggal('tgl_masuk'),
        'paket'            => in_array(post('paket', 20), PAKET, true) ? post('paket', 20) : 'Bulanan',
        'harga_paket'      => postAngka('harga_paket'),
        'hari_tempo'       => max(1, min(28, (int)postAngka('hari_tempo'))),
        'reminder_hari'    => max(0, min(30, (int)postAngka('reminder_hari'))),
    ];

    $galat = '';
    if ($data['nama'] === '')                                   $galat = 'Nama lengkap wajib diisi.';
    elseif ($data['nik'] !== '' && strlen($data['nik']) !== 16)  $galat = 'NIK harus 16 digit angka.';
    elseif ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $galat = 'Email tidak sah.';
    elseif ($data['id_kamar'] && !satu('SELECT id_kamar FROM kamar WHERE id_kamar = ?', [$data['id_kamar']]))
        $galat = 'Kamar tidak sah.';

    if ($galat !== '') {
        kabar($galat, 'galat');
        pergi('penghuni.php?aksi=' . ($id ? 'ubah&id=' . urlencode($id) : 'baru'));
    }

    if ($data['nik'] === '') $data['nik'] = null;
    if ($data['email'] === '') $data['email'] = null;

    // Harga paket kosong -> ambil dari harga kamar.
    if ($data['harga_paket'] <= 0 && $data['id_kamar']) {
        $k = satu('SELECT harga, harga_6bln, harga_12bln FROM kamar WHERE id_kamar = ?', [$data['id_kamar']]);
        $data['harga_paket'] = match ($data['paket']) {
            '6 Bulan' => (float)$k['harga_6bln'] > 0 ? (float)$k['harga_6bln'] : (float)$k['harga'] * 6,
            'Tahunan' => (float)$k['harga_12bln'] > 0 ? (float)$k['harga_12bln'] : (float)$k['harga'] * 12,
            default   => (float)$k['harga'],
        };
    }

    if ($id && satu('SELECT id_penghuni FROM penghuni WHERE id_penghuni = ?', [$id])) {
        ubah('penghuni', $data, 'id_penghuni', $id);
        kabar('Data "' . $data['nama'] . '" sudah diperbarui.');
    } else {
        $data['id_penghuni'] = uid('PNG');
        $data['status']      = 'Aktif';
        if (!$data['tgl_masuk']) $data['tgl_masuk'] = hariIni();
        sisip('penghuni', $data);
        kabar('Penghuni "' . $data['nama'] . '" sudah ditambahkan.');
    }

    segarkanStatusKamar($lama['id_kamar'] ?? null);
    segarkanStatusKamar($data['id_kamar']);
    pergi('penghuni.php');
}

/* ---------- Tandai keluar ---------- */
if ($aksi === 'keluar' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $p = satu('SELECT nama, id_kamar FROM penghuni WHERE id_penghuni = ?', [$id]);
    if ($p) {
        ubah('penghuni', ['status' => 'Keluar', 'tgl_keluar' => postTanggal('tgl_keluar') ?? hariIni()], 'id_penghuni', $id);
        segarkanStatusKamar($p['id_kamar']);
        kabar('"' . $p['nama'] . '" sudah ditandai keluar. Kamarnya kembali kosong.');
    }
    pergi('penghuni.php');
}

/* ---------- Formulir ---------- */
if ($aksi === 'baru' || $aksi === 'ubah') {
    $p = $aksi === 'ubah' ? satu('SELECT * FROM penghuni WHERE id_penghuni = ?', [$id]) : null;
    if ($aksi === 'ubah' && !$p) { kabar('Penghuni tidak ditemukan.', 'galat'); pergi('penghuni.php'); }
    $v  = fn(string $x) => h($p[$x] ?? '');
    $vn = fn(string $x, $bawaan = '') => $p && (float)$p[$x] > 0 ? (string)(int)$p[$x] : (string)$bawaan;

    // Kamar kosong + kamar yang sedang ditempati orang ini
    $kamar = semua(
        "SELECT k.id_kamar, k.nama, k.tipe, k.harga, pr.nama AS properti
         FROM kamar k JOIN properti pr ON pr.id_properti = k.id_properti
         WHERE k.status = 'Kosong' OR k.id_kamar = ?
         ORDER BY pr.urut, pr.nama, k.nama", [$p['id_kamar'] ?? '']);

    tataLetakAtas($aksi === 'baru' ? 'Tambah penghuni' : 'Ubah penghuni', 'penghuni.php');
    judulHalaman($aksi === 'baru' ? 'Tambah penghuni' : 'Ubah penghuni',
                 'Data dipakai untuk tagihan, kuitansi, dan perjanjian sewa.',
                 '<a class="btn" href="penghuni.php">Batal</a>');
    ?>
    <div class="kartu">
      <form method="post" class="form" action="penghuni.php<?= $p ? '?id=' . h($id) : '' ?>">
        <?= kolomCsrf() ?>
        <fieldset>
          <legend>Data diri</legend>
          <div class="baris">
            <div><label for="nama">Nama lengkap</label>
              <input id="nama" name="nama" type="text" required maxlength="80" value="<?= $v('nama') ?>"></div>
            <div><label for="jenis_kelamin">Jenis kelamin</label>
              <select id="jenis_kelamin" name="jenis_kelamin">
                <option value="">Pilih...</option>
                <?php foreach (['Laki-laki', 'Perempuan'] as $jk): ?>
                  <option<?= ($p['jenis_kelamin'] ?? '') === $jk ? ' selected' : '' ?>><?= h($jk) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="no_hp">No. HP / WhatsApp</label>
              <input id="no_hp" name="no_hp" type="tel" maxlength="30" value="<?= $v('no_hp') ?>" placeholder="08xxxxxxxxxx"></div>
            <div><label for="email">Email <span class="opsional">(untuk kirim tagihan)</span></label>
              <input id="email" name="email" type="email" maxlength="120" value="<?= $v('email') ?>"></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="nik">NIK <span class="opsional">(16 digit)</span></label>
              <input id="nik" name="nik" type="text" inputmode="numeric" maxlength="16" value="<?= $v('nik') ?>"></div>
            <div><label for="pekerjaan">Pekerjaan / kampus</label>
              <input id="pekerjaan" name="pekerjaan" type="text" maxlength="60" value="<?= $v('pekerjaan') ?>"></div>
          </div>
          <div style="margin-top:15px"><label for="alamat_asal">Alamat asal</label>
            <textarea id="alamat_asal" name="alamat_asal" maxlength="400"><?= $v('alamat_asal') ?></textarea></div>
        </fieldset>

        <fieldset style="margin-top:10px">
          <legend>Kontak darurat</legend>
          <div class="baris">
            <div><label for="kontak_darurat">Nama</label>
              <input id="kontak_darurat" name="kontak_darurat" type="text" maxlength="60" value="<?= $v('kontak_darurat') ?>"></div>
            <div><label for="hp_darurat">No. HP</label>
              <input id="hp_darurat" name="hp_darurat" type="tel" maxlength="30" value="<?= $v('hp_darurat') ?>"></div>
          </div>
          <div style="margin-top:15px"><label for="hubungan_darurat">Hubungan</label>
            <input id="hubungan_darurat" name="hubungan_darurat" type="text" maxlength="30" value="<?= $v('hubungan_darurat') ?>" placeholder="Ibu, Ayah, Kakak"></div>
        </fieldset>

        <fieldset style="margin-top:10px">
          <legend>Sewa</legend>
          <div class="baris">
            <div><label for="id_kamar">Kamar</label>
              <select id="id_kamar" name="id_kamar">
                <option value="">Belum ditentukan</option>
                <?php foreach ($kamar as $k): ?>
                  <option value="<?= h($k['id_kamar']) ?>"<?= ($p['id_kamar'] ?? '') === $k['id_kamar'] ? ' selected' : '' ?>>
                    <?= h($k['properti'] . ' · ' . $k['nama'] . ($k['tipe'] ? ' (' . $k['tipe'] . ')' : '') . ' · ' . rp($k['harga'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="bantuan">Hanya kamar kosong yang muncul di sini.</p></div>
            <div><label for="tgl_masuk">Tanggal masuk</label>
              <input id="tgl_masuk" name="tgl_masuk" type="date" value="<?= $v('tgl_masuk') ?: h(hariIni()) ?>"></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="paket">Paket sewa</label>
              <select id="paket" name="paket">
                <?php foreach (PAKET as $pk): ?>
                  <option<?= ($p['paket'] ?? 'Bulanan') === $pk ? ' selected' : '' ?>><?= h($pk) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div><label for="harga_paket">Harga per siklus <span class="opsional">(kosongkan = ikut harga kamar)</span></label>
              <input id="harga_paket" name="harga_paket" type="number" min="0" step="1000" value="<?= $vn('harga_paket') ?>"></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="hari_tempo">Tanggal jatuh tempo tiap bulan</label>
              <input id="hari_tempo" name="hari_tempo" type="number" min="1" max="28" value="<?= $vn('hari_tempo', 5) ?>"></div>
            <div><label for="reminder_hari">Pengingat berapa hari sebelum</label>
              <input id="reminder_hari" name="reminder_hari" type="number" min="0" max="30" value="<?= $vn('reminder_hari', 3) ?>"></div>
          </div>
        </fieldset>

        <div><button class="btn utama" type="submit">Simpan</button></div>
      </form>
    </div>
    <?php
    tataLetakBawah();
    exit;
}

/* ---------- Daftar ---------- */
$kondisi = $filter === 'Semua' ? '' : ' WHERE p.status = ?';
$param   = $filter === 'Semua' ? [] : [$filter];
$daftar  = semua(
    'SELECT p.*, k.nama AS kamar, pr.nama AS properti
     FROM penghuni p
     LEFT JOIN kamar k ON k.id_kamar = p.id_kamar
     LEFT JOIN properti pr ON pr.id_properti = k.id_properti' . $kondisi . '
     ORDER BY p.status, pr.urut, k.nama, p.nama', $param);

$nAktif = (int)nilai("SELECT COUNT(*) FROM penghuni WHERE status = 'Aktif'", [], 0);
$nKeluar = (int)nilai("SELECT COUNT(*) FROM penghuni WHERE status = 'Keluar'", [], 0);

tataLetakAtas('Penghuni', 'penghuni.php');
judulHalaman('Penghuni', $nAktif . ' penyewa aktif',
             '<a class="btn utama" href="penghuni.php?aksi=baru">+ Tambah penghuni</a>');
?>

<div class="kartu" style="padding:14px 18px;margin-bottom:16px">
  <div style="display:flex;gap:9px;flex-wrap:wrap">
    <?php foreach ([['Aktif', $nAktif], ['Keluar', $nKeluar], ['Semua', $nAktif + $nKeluar]] as [$s, $n]): ?>
      <a class="btn kecil<?= $filter === $s ? ' utama' : '' ?>" href="penghuni.php?status=<?= h($s) ?>"><?= h($s) ?> (<?= $n ?>)</a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$daftar): ?>
  <div class="kartu"><div class="kosong">Belum ada penghuni untuk pilihan ini.</div></div>
<?php else: ?>
  <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Nama</th><th>Kamar</th><th>Kontak</th><th>Masuk</th><th class="kanan">Sewa</th><th>Status</th><th class="aksi">Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($daftar as $p): $aktif = $p['status'] === 'Aktif'; ?>
        <tr<?= $aktif ? '' : ' style="opacity:.62"' ?>>
          <td><b><?= h($p['nama']) ?></b>
            <?= $p['pekerjaan'] ? '<br><small style="color:var(--mut)">' . h($p['pekerjaan']) . '</small>' : '' ?></td>
          <td><?= $p['kamar']
                ? h($p['kamar']) . '<br><small style="color:var(--mut)">' . h($p['properti']) . '</small>'
                : '<span style="color:var(--mut)">-</span>' ?></td>
          <td><?php if ($p['no_hp']): ?>
                <a href="https://wa.me/<?= h(noWa($p['no_hp'])) ?>" target="_blank" rel="noopener"
                   style="color:var(--deep);font-weight:600"><?= h($p['no_hp']) ?></a>
              <?php else: ?><span style="color:var(--mut)">-</span><?php endif; ?>
              <?= $p['email'] ? '<br><small style="color:var(--mut)">' . h($p['email']) . '</small>' : '' ?></td>
          <td><?= h(tglPendek($p['tgl_masuk'])) ?>
              <?= $p['tgl_keluar'] ? '<br><small style="color:var(--mut)">keluar ' . h(tglPendek($p['tgl_keluar'])) . '</small>' : '' ?></td>
          <td class="kanan"><b><?= h(rp($p['harga_paket'])) ?></b><br>
              <small style="color:var(--mut)"><?= h($p['paket']) ?></small></td>
          <td><span class="lenca <?= $aktif ? 'ok' : 'netral' ?>"><i></i><?= h($p['status']) ?></span></td>
          <td class="aksi">
            <a class="btn kecil" href="penghuni.php?aksi=ubah&amp;id=<?= h($p['id_penghuni']) ?>">Ubah</a>
            <?php if ($aktif): ?>
              <form method="post" action="penghuni.php?aksi=keluar&amp;id=<?= h($p['id_penghuni']) ?>" style="display:inline">
                <?= kolomCsrf() ?>
                <button class="btn kecil bahaya" type="submit"
                        data-konfirmasi="Tandai &quot;<?= h($p['nama']) ?>&quot; sudah keluar? Kamarnya akan jadi kosong.">Keluar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php tataLetakBawah(); ?>

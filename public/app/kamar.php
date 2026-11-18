<?php
/** Kamar: daftar per properti, tambah, ubah, hapus. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();
if (!sudahDipasang()) pergi('pasang.php');
wajibKelola();
require __DIR__ . '/lib/layout.php';

$aksi     = get('aksi', 10);
$id       = get('id', 24);
$filterPr = get('properti', 24);

$properti = semua('SELECT id_properti, nama FROM properti ORDER BY urut, nama');
if (!$properti) {
    kabar('Tambah properti dulu sebelum menambahkan kamar.', 'info');
    pergi('properti.php?aksi=baru');
}

const STATUS_KAMAR = ['Kosong', 'Terisi', 'Perbaikan'];

/* ---------- Simpan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $data = [
        'id_properti' => post('id_properti', 24),
        'nama'        => post('nama', 40),
        'tipe'        => post('tipe', 40),
        'harga'       => postAngka('harga'),
        'harga_6bln'  => postAngka('harga_6bln'),
        'harga_12bln' => postAngka('harga_12bln'),
        'deposit'     => postAngka('deposit'),
        'status'      => in_array(post('status', 20), STATUS_KAMAR, true) ? post('status', 20) : 'Kosong',
    ];

    $galat = '';
    if ($data['nama'] === '')  $galat = 'Nama atau nomor kamar wajib diisi.';
    elseif (!satu('SELECT id_properti FROM properti WHERE id_properti = ?', [$data['id_properti']]))
        $galat = 'Properti tidak sah.';
    elseif ($data['harga'] <= 0) $galat = 'Harga sewa per bulan harus lebih dari nol.';

    if ($galat !== '') {
        kabar($galat, 'galat');
        pergi('kamar.php?aksi=' . ($id ? 'ubah&id=' . urlencode($id) : 'baru'));
    }

    if ($id && satu('SELECT id_kamar FROM kamar WHERE id_kamar = ?', [$id])) {
        ubah('kamar', $data, 'id_kamar', $id);
        kabar('Kamar "' . $data['nama'] . '" sudah diperbarui.');
    } else {
        $data['id_kamar'] = uid('KMR');
        sisip('kamar', $data);
        kabar('Kamar "' . $data['nama'] . '" sudah ditambahkan.');
    }
    pergi('kamar.php' . ($filterPr ? '?properti=' . urlencode($filterPr) : ''));
}

/* ---------- Hapus ---------- */
if ($aksi === 'hapus' && $id) {
    $k = satu('SELECT nama FROM kamar WHERE id_kamar = ?', [$id]);
    if ($k) {
        $n = (int)nilai("SELECT COUNT(*) FROM penghuni WHERE id_kamar = ? AND status = 'Aktif'", [$id], 0);
        if ($n > 0) {
            kabar('Kamar "' . $k['nama'] . '" masih ditempati. Pindahkan penghuninya dulu.', 'galat');
        } else {
            hapus('kamar', 'id_kamar', $id);
            kabar('Kamar "' . $k['nama'] . '" sudah dihapus.');
        }
    }
    pergi('kamar.php' . ($filterPr ? '?properti=' . urlencode($filterPr) : ''));
}

/* ---------- Formulir ---------- */
if ($aksi === 'baru' || $aksi === 'ubah') {
    $k = $aksi === 'ubah' ? satu('SELECT * FROM kamar WHERE id_kamar = ?', [$id]) : null;
    if ($aksi === 'ubah' && !$k) { kabar('Kamar tidak ditemukan.', 'galat'); pergi('kamar.php'); }
    $v  = fn(string $x) => h($k[$x] ?? '');
    $vn = fn(string $x) => $k && (float)$k[$x] > 0 ? (string)(int)$k[$x] : '';
    $prTerpilih = $k['id_properti'] ?? $filterPr ?: $properti[0]['id_properti'];

    tataLetakAtas($aksi === 'baru' ? 'Tambah kamar' : 'Ubah kamar', 'kamar.php');
    judulHalaman($aksi === 'baru' ? 'Tambah kamar' : 'Ubah kamar',
                 'Harga paket boleh dikosongkan bila hanya menyewakan bulanan.',
                 '<a class="btn" href="kamar.php' . ($filterPr ? '?properti=' . h($filterPr) : '') . '">Batal</a>');
    ?>
    <div class="kartu">
      <form method="post" class="form" action="kamar.php?<?= $k ? 'id=' . h($id) . '&amp;' : '' ?>properti=<?= h($filterPr) ?>">
        <?= kolomCsrf() ?>
        <fieldset>
          <legend>Kamar</legend>
          <div class="baris">
            <div><label for="id_properti">Properti</label>
              <select id="id_properti" name="id_properti" required>
                <?php foreach ($properti as $p): ?>
                  <option value="<?= h($p['id_properti']) ?>"<?= $p['id_properti'] === $prTerpilih ? ' selected' : '' ?>><?= h($p['nama']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div><label for="nama">Nomor / nama kamar</label>
              <input id="nama" name="nama" type="text" required maxlength="40" value="<?= $v('nama') ?>" placeholder="A1"></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="tipe">Tipe</label>
              <input id="tipe" name="tipe" type="text" maxlength="40" value="<?= $v('tipe') ?>" placeholder="Campur / Putri / Putra"></div>
            <div><label for="status">Status</label>
              <select id="status" name="status">
                <?php foreach (STATUS_KAMAR as $s): ?>
                  <option<?= ($k['status'] ?? 'Kosong') === $s ? ' selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
              </select></div>
          </div>
        </fieldset>

        <fieldset style="margin-top:10px">
          <legend>Harga</legend>
          <div class="baris">
            <div><label for="harga">Sewa per bulan</label>
              <input id="harga" name="harga" type="number" min="0" step="1000" required value="<?= $vn('harga') ?>" placeholder="2300000"></div>
            <div><label for="deposit">Deposit</label>
              <input id="deposit" name="deposit" type="number" min="0" step="1000" value="<?= $vn('deposit') ?>" placeholder="500000"></div>
          </div>
          <div class="baris" style="margin-top:15px">
            <div><label for="harga_6bln">Paket 6 bulan <span class="opsional">(total)</span></label>
              <input id="harga_6bln" name="harga_6bln" type="number" min="0" step="1000" value="<?= $vn('harga_6bln') ?>"></div>
            <div><label for="harga_12bln">Paket 12 bulan <span class="opsional">(total)</span></label>
              <input id="harga_12bln" name="harga_12bln" type="number" min="0" step="1000" value="<?= $vn('harga_12bln') ?>"></div>
          </div>
          <p class="bantuan">Kosongkan paket bila belum ditawarkan. Harga bulanan dipakai sebagai dasar tagihan.</p>
        </fieldset>

        <div><button class="btn utama" type="submit">Simpan</button></div>
      </form>
    </div>
    <?php
    tataLetakBawah();
    exit;
}

/* ---------- Daftar ---------- */
$kondisi = $filterPr ? ' WHERE k.id_properti = ?' : '';
$param   = $filterPr ? [$filterPr] : [];
$daftar  = semua(
    'SELECT k.*, p.nama AS properti,
            (SELECT ph.nama FROM penghuni ph WHERE ph.id_kamar = k.id_kamar AND ph.status = \'Aktif\' LIMIT 1) AS penghuni
     FROM kamar k JOIN properti p ON p.id_properti = k.id_properti' . $kondisi . '
     ORDER BY p.urut, p.nama, k.nama', $param);

$lenca = ['Kosong' => 'ok', 'Terisi' => 'netral', 'Perbaikan' => 'warn'];

tataLetakAtas('Kamar', 'kamar.php');
judulHalaman('Kamar', count($daftar) . ' kamar terdaftar',
             '<a class="btn utama" href="kamar.php?aksi=baru' . ($filterPr ? '&amp;properti=' . h($filterPr) : '') . '">+ Tambah kamar</a>');
?>

<div class="kartu" style="padding:14px 18px;margin-bottom:16px">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <label for="properti" style="margin:0">Properti</label>
    <select id="properti" name="properti" style="width:auto;min-width:220px" onchange="this.form.submit()">
      <option value="">Semua properti</option>
      <?php foreach ($properti as $p): ?>
        <option value="<?= h($p['id_properti']) ?>"<?= $p['id_properti'] === $filterPr ? ' selected' : '' ?>><?= h($p['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn kecil" type="submit">Terapkan</button></noscript>
  </form>
</div>

<?php if (!$daftar): ?>
  <div class="kartu"><div class="kosong">Belum ada kamar untuk pilihan ini.</div></div>
<?php else: ?>
  <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Kamar</th><th>Properti</th><th>Tipe</th><th class="kanan">Harga / bulan</th>
        <th class="kanan">Deposit</th><th>Status</th><th class="aksi">Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($daftar as $k): ?>
        <tr>
          <td><b><?= h($k['nama']) ?></b>
            <?= $k['penghuni'] ? '<br><small style="color:var(--mut)">' . h($k['penghuni']) . '</small>' : '' ?></td>
          <td><?= h($k['properti']) ?></td>
          <td><?= h($k['tipe'] ?: '-') ?></td>
          <td class="kanan"><b><?= h(rp($k['harga'])) ?></b></td>
          <td class="kanan"><?= (float)$k['deposit'] > 0 ? h(rp($k['deposit'])) : '<span style="color:var(--mut)">-</span>' ?></td>
          <td><span class="lenca <?= h($lenca[$k['status']] ?? 'netral') ?>"><i></i><?= h($k['status']) ?></span></td>
          <td class="aksi">
            <a class="btn kecil" href="kamar.php?aksi=ubah&amp;id=<?= h($k['id_kamar']) ?>&amp;properti=<?= h($filterPr) ?>">Ubah</a>
            <a class="btn kecil bahaya" href="kamar.php?aksi=hapus&amp;id=<?= h($k['id_kamar']) ?>&amp;properti=<?= h($filterPr) ?>"
               data-konfirmasi="Hapus kamar &quot;<?= h($k['nama']) ?>&quot;?">Hapus</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php tataLetakBawah(); ?>

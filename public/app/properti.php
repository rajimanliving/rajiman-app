<?php
/** Properti: daftar, tambah, ubah, hapus. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();
if (!sudahDipasang()) pergi('pasang.php');
wajibKelola();
require __DIR__ . '/lib/layout.php';

$aksi = get('aksi', 10);
$id   = get('id', 24);

/* ---------- Simpan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $data = [
        'nama'            => post('nama', 100),
        'alamat'          => post('alamat', 400),
        'kode'            => strtoupper(post('kode', 10)),
        'no_hp'           => post('no_hp', 30),
        'bank'            => post('bank', 40),
        'no_rekening'     => post('no_rekening', 40),
        'atas_nama'       => post('atas_nama', 80),
        'info_pembayaran' => post('info_pembayaran', 400),
    ];

    if ($data['nama'] === '') {
        kabar('Nama properti wajib diisi.', 'galat');
        pergi('properti.php?aksi=' . ($id ? 'ubah&id=' . urlencode($id) : 'baru'));
    }
    if ($data['kode'] === '') {
        $data['kode'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['nama']) . 'XX', 0, 3));
    }

    if ($id && satu('SELECT id_properti FROM properti WHERE id_properti = ?', [$id])) {
        ubah('properti', $data, 'id_properti', $id);
        kabar('Properti "' . $data['nama'] . '" sudah diperbarui.');
    } else {
        $data['id_properti'] = uid('PRP');
        $data['urut'] = (int)nilai('SELECT COALESCE(MAX(urut),0)+1 FROM properti', [], 1);
        sisip('properti', $data);
        kabar('Properti "' . $data['nama'] . '" sudah ditambahkan.');
    }
    pergi('properti.php');
}

/* ---------- Hapus ---------- */
if ($aksi === 'hapus' && $id) {
    $p = satu('SELECT nama FROM properti WHERE id_properti = ?', [$id]);
    if ($p) {
        $nKamar = (int)nilai('SELECT COUNT(*) FROM kamar WHERE id_properti = ?', [$id], 0);
        if ($nKamar > 0) {
            kabar('Properti "' . $p['nama'] . '" tidak bisa dihapus karena masih punya ' . $nKamar . ' kamar. Hapus kamarnya dulu.', 'galat');
        } else {
            hapus('properti', 'id_properti', $id);
            kabar('Properti "' . $p['nama'] . '" sudah dihapus.');
        }
    }
    pergi('properti.php');
}

/* ---------- Formulir ---------- */
if ($aksi === 'baru' || $aksi === 'ubah') {
    $p = $aksi === 'ubah' ? satu('SELECT * FROM properti WHERE id_properti = ?', [$id]) : null;
    if ($aksi === 'ubah' && !$p) { kabar('Properti tidak ditemukan.', 'galat'); pergi('properti.php'); }
    $v = fn(string $k) => h($p[$k] ?? '');

    tataLetakAtas($aksi === 'baru' ? 'Tambah properti' : 'Ubah properti', 'properti.php');
    judulHalaman($aksi === 'baru' ? 'Tambah properti' : 'Ubah properti',
                 'Satu properti adalah satu kos atau satu bangunan.',
                 '<a class="btn" href="properti.php">Batal</a>');
    ?>
    <div class="kartu">
      <form method="post" class="form" action="properti.php<?= $p ? '?id=' . h($id) : '' ?>">
        <?= kolomCsrf() ?>
        <fieldset>
          <legend>Identitas</legend>
          <div class="baris">
            <div><label for="nama">Nama properti</label>
              <input id="nama" name="nama" type="text" required maxlength="100" value="<?= $v('nama') ?>"></div>
            <div><label for="kode">Kode <span class="opsional">(untuk nomor faktur)</span></label>
              <input id="kode" name="kode" type="text" maxlength="10" value="<?= $v('kode') ?>" placeholder="RK1">
              <p class="bantuan">Kosongkan untuk dibuatkan otomatis.</p></div>
          </div>
          <div style="margin-top:15px"><label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat" maxlength="400"><?= $v('alamat') ?></textarea></div>
          <div style="margin-top:15px"><label for="no_hp">Nomor HP pengelola</label>
            <input id="no_hp" name="no_hp" type="tel" maxlength="30" value="<?= $v('no_hp') ?>" placeholder="08xxxxxxxxxx"></div>
        </fieldset>

        <fieldset style="margin-top:10px">
          <legend>Rekening pembayaran</legend>
          <div class="baris">
            <div><label for="bank">Bank</label>
              <input id="bank" name="bank" type="text" maxlength="40" value="<?= $v('bank') ?>" placeholder="BCA"></div>
            <div><label for="no_rekening">Nomor rekening</label>
              <input id="no_rekening" name="no_rekening" type="text" maxlength="40" value="<?= $v('no_rekening') ?>"></div>
          </div>
          <div style="margin-top:15px"><label for="atas_nama">Atas nama</label>
            <input id="atas_nama" name="atas_nama" type="text" maxlength="80" value="<?= $v('atas_nama') ?>"></div>
          <div style="margin-top:15px"><label for="info_pembayaran">Catatan pembayaran <span class="opsional">(tampil di faktur)</span></label>
            <textarea id="info_pembayaran" name="info_pembayaran" maxlength="400"><?= $v('info_pembayaran') ?></textarea></div>
        </fieldset>

        <div><button class="btn utama" type="submit">Simpan</button></div>
      </form>
    </div>
    <?php
    tataLetakBawah();
    exit;
}

/* ---------- Daftar ---------- */
$daftar = semua(
    'SELECT p.*,
            (SELECT COUNT(*) FROM kamar k WHERE k.id_properti = p.id_properti) AS jml_kamar,
            (SELECT COUNT(*) FROM kamar k WHERE k.id_properti = p.id_properti AND k.status = \'Terisi\') AS terisi
     FROM properti p ORDER BY p.urut, p.nama');

tataLetakAtas('Properti', 'properti.php');
judulHalaman('Properti', 'Kelola kos, kamar, dan harga sewa',
             '<a class="btn utama" href="properti.php?aksi=baru">+ Tambah properti</a>');
?>

<?php if (!$daftar): ?>
  <div class="kartu"><div class="kosong">
    Belum ada properti. Klik <b>Tambah properti</b> untuk mulai.
  </div></div>
<?php else: ?>
  <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Properti</th><th>Kode</th><th>Rekening</th><th>Kamar</th><th class="aksi">Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($daftar as $p):
        $penuh = (int)$p['jml_kamar'] > 0 && (int)$p['terisi'] >= (int)$p['jml_kamar']; ?>
        <tr>
          <td>
            <b><?= h($p['nama']) ?></b>
            <?= $p['alamat'] ? '<br><small style="color:var(--mut)">' . h($p['alamat']) . '</small>' : '' ?>
          </td>
          <td><?= h($p['kode'] ?: '-') ?></td>
          <td><?= $p['no_rekening']
                ? h($p['bank'] . ' ' . $p['no_rekening']) . '<br><small style="color:var(--mut)">' . h($p['atas_nama']) . '</small>'
                : '<span style="color:var(--mut)">Belum diisi</span>' ?></td>
          <td>
            <span class="lenca <?= $penuh ? 'warn' : 'ok' ?>"><i></i><?= (int)$p['terisi'] ?> / <?= (int)$p['jml_kamar'] ?> terisi</span>
          </td>
          <td class="aksi">
            <a class="btn kecil" href="kamar.php?properti=<?= h($p['id_properti']) ?>">Kamar</a>
            <a class="btn kecil" href="properti.php?aksi=ubah&amp;id=<?= h($p['id_properti']) ?>">Ubah</a>
            <a class="btn kecil bahaya" href="properti.php?aksi=hapus&amp;id=<?= h($p['id_properti']) ?>"
               data-konfirmasi="Hapus properti &quot;<?= h($p['nama']) ?>&quot;? Tindakan ini tidak bisa dibatalkan.">Hapus</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php tataLetakBawah(); ?>

-- ============================================================
--  Rajiman Living — skema database MySQL
--  Dijalankan otomatis oleh app/pasang.php, atau impor manual
--  lewat phpMyAdmin di cPanel Rumahweb.
--  Kolomnya mengikuti sheet aplikasi lama agar data bisa dipindah.
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Pengguna & akses ----------
CREATE TABLE IF NOT EXISTS pengguna (
  username     VARCHAR(30)  NOT NULL,
  nama         VARCHAR(80)  NOT NULL,
  peran        ENUM('pemilik','pengelola','penyewa') NOT NULL DEFAULT 'pengelola',
  hash         VARCHAR(255) NOT NULL,
  email        VARCHAR(120) NULL,
  aktif        TINYINT(1)   NOT NULL DEFAULT 1,
  id_penghuni  VARCHAR(24)  NULL,
  reset_kode   VARCHAR(10)  NULL,
  reset_sampai DATETIME     NULL,
  dibuat       DATETIME     NOT NULL,
  PRIMARY KEY (username),
  KEY idx_pengguna_penghuni (id_penghuni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Properti (satu kos / satu bangunan) ----------
CREATE TABLE IF NOT EXISTS properti (
  id_properti      VARCHAR(24) NOT NULL,
  nama             VARCHAR(100) NOT NULL,
  alamat           TEXT NULL,
  kode             VARCHAR(10) NULL,
  no_hp            VARCHAR(30) NULL,
  bank             VARCHAR(40) NULL,
  no_rekening      VARCHAR(40) NULL,
  atas_nama        VARCHAR(80) NULL,
  info_pembayaran  TEXT NULL,
  logo             VARCHAR(255) NULL,
  promo_foto       TEXT NULL,
  promo_wa         VARCHAR(30) NULL,
  promo_peta       VARCHAR(255) NULL,
  promo_teks       TEXT NULL,
  promo_fasilitas  TEXT NULL,
  urut             INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_properti)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kamar ----------
CREATE TABLE IF NOT EXISTS kamar (
  id_kamar     VARCHAR(24) NOT NULL,
  id_properti  VARCHAR(24) NOT NULL,
  nama         VARCHAR(40) NOT NULL,
  tipe         VARCHAR(40) NULL,
  harga        DECIMAL(12,2) NOT NULL DEFAULT 0,
  harga_6bln   DECIMAL(12,2) NOT NULL DEFAULT 0,
  harga_12bln  DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposit      DECIMAL(12,2) NOT NULL DEFAULT 0,
  status       ENUM('Kosong','Terisi','Perbaikan') NOT NULL DEFAULT 'Kosong',
  PRIMARY KEY (id_kamar),
  KEY idx_kamar_properti (id_properti),
  CONSTRAINT fk_kamar_properti FOREIGN KEY (id_properti)
    REFERENCES properti (id_properti) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Penghuni / penyewa ----------
CREATE TABLE IF NOT EXISTS penghuni (
  id_penghuni       VARCHAR(24) NOT NULL,
  nama              VARCHAR(80) NOT NULL,
  no_hp             VARCHAR(30) NULL,
  email             VARCHAR(120) NULL,
  id_kamar          VARCHAR(24) NULL,
  jenis_kelamin     ENUM('Laki-laki','Perempuan') NULL,
  nik               VARCHAR(16) NULL,
  alamat_asal       TEXT NULL,
  pekerjaan         VARCHAR(60) NULL,
  kontak_darurat    VARCHAR(60) NULL,
  hp_darurat        VARCHAR(30) NULL,
  hubungan_darurat  VARCHAR(30) NULL,
  file_ktp          VARCHAR(255) NULL,
  file_selfie       VARCHAR(255) NULL,
  tgl_masuk         DATE NULL,
  tgl_keluar        DATE NULL,
  status            ENUM('Aktif','Keluar') NOT NULL DEFAULT 'Aktif',
  paket             ENUM('Bulanan','6 Bulan','Tahunan') NOT NULL DEFAULT 'Bulanan',
  harga_paket       DECIMAL(12,2) NOT NULL DEFAULT 0,
  hari_tempo        TINYINT NOT NULL DEFAULT 5,
  reminder_hari     TINYINT NOT NULL DEFAULT 3,
  kirim_pdf         TINYINT(1) NOT NULL DEFAULT 1,
  teman_sekamar     VARCHAR(80) NULL,
  rencana_sewa      VARCHAR(40) NULL,
  PRIMARY KEY (id_penghuni),
  KEY idx_penghuni_kamar (id_kamar),
  KEY idx_penghuni_status (status),
  CONSTRAINT fk_penghuni_kamar FOREIGN KEY (id_kamar)
    REFERENCES kamar (id_kamar) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Tagihan ----------
CREATE TABLE IF NOT EXISTS tagihan (
  id_tagihan   VARCHAR(24) NOT NULL,
  id_penghuni  VARCHAR(24) NOT NULL,
  periode      CHAR(7) NOT NULL,              -- contoh: 2026-11
  jumlah       DECIMAL(12,2) NOT NULL DEFAULT 0,
  jatuh_tempo  DATE NOT NULL,
  status       ENUM('Belum Lunas','Lunas') NOT NULL DEFAULT 'Belum Lunas',
  no_faktur    VARCHAR(30) NULL,
  tgl_faktur   DATE NULL,
  cakupan      TINYINT NOT NULL DEFAULT 1,    -- jumlah bulan yang ditagih
  PRIMARY KEY (id_tagihan),
  UNIQUE KEY uq_tagihan (id_penghuni, periode),
  KEY idx_tagihan_status (status),
  KEY idx_tagihan_periode (periode),
  CONSTRAINT fk_tagihan_penghuni FOREIGN KEY (id_penghuni)
    REFERENCES penghuni (id_penghuni) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pembayaran ----------
CREATE TABLE IF NOT EXISTS pembayaran (
  id_bayar      VARCHAR(24) NOT NULL,
  id_tagihan    VARCHAR(24) NOT NULL,
  tanggal       DATE NOT NULL,
  jumlah        DECIMAL(12,2) NOT NULL DEFAULT 0,
  metode        ENUM('Transfer','Tunai','QRIS','Lainnya') NOT NULL DEFAULT 'Transfer',
  no_transaksi  VARCHAR(30) NULL,
  bukti         VARCHAR(255) NULL,
  PRIMARY KEY (id_bayar),
  KEY idx_bayar_tagihan (id_tagihan),
  CONSTRAINT fk_bayar_tagihan FOREIGN KEY (id_tagihan)
    REFERENCES tagihan (id_tagihan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Deposit / uang jaminan ----------
CREATE TABLE IF NOT EXISTS deposit (
  id_deposit        VARCHAR(24) NOT NULL,
  id_penghuni       VARCHAR(24) NOT NULL,
  jumlah            DECIMAL(12,2) NOT NULL DEFAULT 0,
  tgl_terima        DATE NULL,
  metode            VARCHAR(20) NULL,
  status            ENUM('Ditahan','Dikembalikan') NOT NULL DEFAULT 'Ditahan',
  tgl_kembali       DATE NULL,
  potongan          DECIMAL(12,2) NOT NULL DEFAULT 0,
  rincian_potongan  TEXT NULL,
  jumlah_kembali    DECIMAL(12,2) NOT NULL DEFAULT 0,
  metode_kembali    VARCHAR(20) NULL,
  catatan           TEXT NULL,
  PRIMARY KEY (id_deposit),
  KEY idx_deposit_penghuni (id_penghuni),
  CONSTRAINT fk_deposit_penghuni FOREIGN KEY (id_penghuni)
    REFERENCES penghuni (id_penghuni) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pengeluaran ----------
CREATE TABLE IF NOT EXISTS pengeluaran (
  id           VARCHAR(24) NOT NULL,
  id_properti  VARCHAR(24) NULL,
  tanggal      DATE NOT NULL,
  kategori     VARCHAR(40) NULL,
  keterangan   VARCHAR(200) NULL,
  jumlah       DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_keluar_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pendaftaran mandiri ----------
CREATE TABLE IF NOT EXISTS pendaftaran (
  id_daftar         VARCHAR(24) NOT NULL,
  tgl_daftar        DATETIME NOT NULL,
  id_properti       VARCHAR(24) NULL,
  id_kamar          VARCHAR(24) NULL,
  nama              VARCHAR(80) NOT NULL,
  no_hp             VARCHAR(30) NULL,
  email             VARCHAR(120) NULL,
  jenis_kelamin     VARCHAR(20) NULL,
  nik               VARCHAR(16) NULL,
  alamat_asal       TEXT NULL,
  pekerjaan         VARCHAR(60) NULL,
  kontak_darurat    VARCHAR(60) NULL,
  hp_darurat        VARCHAR(30) NULL,
  hubungan_darurat  VARCHAR(30) NULL,
  file_ktp          VARCHAR(255) NULL,
  file_selfie       VARCHAR(255) NULL,
  tgl_rencana_masuk DATE NULL,
  username          VARCHAR(30) NULL,
  status            ENUM('Baru','Disetujui','Ditolak') NOT NULL DEFAULT 'Baru',
  id_penghuni       VARCHAR(24) NULL,
  catatan           TEXT NULL,
  PRIMARY KEY (id_daftar),
  KEY idx_daftar_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pesan (tiket penyewa ↔ pengelola) ----------
CREATE TABLE IF NOT EXISTS pesan (
  id_pesan        VARCHAR(24) NOT NULL,
  id_penghuni     VARCHAR(24) NOT NULL,
  id_properti     VARCHAR(24) NULL,
  tgl_pesan       DATETIME NOT NULL,
  tgl_update      DATETIME NOT NULL,
  kategori        VARCHAR(40) NULL,
  subjek          VARCHAR(160) NOT NULL,
  status          ENUM('Baru','Diproses','Selesai') NOT NULL DEFAULT 'Baru',
  baca_pengelola  TINYINT(1) NOT NULL DEFAULT 0,
  baca_penyewa    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_pesan),
  KEY idx_pesan_penghuni (id_penghuni),
  CONSTRAINT fk_pesan_penghuni FOREIGN KEY (id_penghuni)
    REFERENCES penghuni (id_penghuni) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesan_isi (
  id         INT AUTO_INCREMENT NOT NULL,
  id_pesan   VARCHAR(24) NOT NULL,
  dari       ENUM('penyewa','pengelola') NOT NULL,
  nama       VARCHAR(80) NULL,
  isi        TEXT NOT NULL,
  tgl        DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_isi_pesan (id_pesan),
  CONSTRAINT fk_isi_pesan FOREIGN KEY (id_pesan)
    REFERENCES pesan (id_pesan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Perjanjian sewa ----------
CREATE TABLE IF NOT EXISTS perjanjian (
  id_perjanjian  VARCHAR(24) NOT NULL,
  nomor          VARCHAR(40) NOT NULL,
  id_penghuni    VARCHAR(24) NOT NULL,
  id_properti    VARCHAR(24) NULL,
  judul          VARCHAR(160) NULL,
  isi            MEDIUMTEXT NULL,
  tgl_dibuat     DATETIME NOT NULL,
  tgl_mulai      DATE NULL,
  tgl_selesai    DATE NULL,
  durasi_bulan   SMALLINT NOT NULL DEFAULT 0,
  harga          DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposit        DECIMAL(12,2) NOT NULL DEFAULT 0,
  status         ENUM('Draf','Terkirim','Ditandatangani','Batal') NOT NULL DEFAULT 'Draf',
  token          VARCHAR(64) NULL,
  tgl_kirim      DATETIME NULL,
  tgl_ttd        DATETIME NULL,
  nama_ttd       VARCHAR(80) NULL,
  file_ttd       VARCHAR(255) NULL,
  file_pdf       VARCHAR(255) NULL,
  hash_isi       VARCHAR(64) NULL,
  info_ttd       TEXT NULL,
  PRIMARY KEY (id_perjanjian),
  UNIQUE KEY uq_perjanjian_nomor (nomor),
  KEY idx_pj_penghuni (id_penghuni),
  KEY idx_pj_token (token),
  CONSTRAINT fk_pj_penghuni FOREIGN KEY (id_penghuni)
    REFERENCES penghuni (id_penghuni) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pengaturan (pasangan kunci–nilai) ----------
CREATE TABLE IF NOT EXISTS pengaturan (
  kunci  VARCHAR(60) NOT NULL,
  nilai  TEXT NULL,
  PRIMARY KEY (kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Catatan masuk (untuk menelusuri bila ada masalah) ----------
CREATE TABLE IF NOT EXISTS log_masuk (
  id        INT AUTO_INCREMENT NOT NULL,
  username  VARCHAR(30) NULL,
  ip        VARCHAR(45) NULL,
  berhasil  TINYINT(1) NOT NULL DEFAULT 0,
  tgl       DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_log_tgl (tgl)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

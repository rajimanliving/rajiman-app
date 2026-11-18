<?php
/** Keluar dari sesi. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

mulaiSesi();
keluarSesi();
header('Location: masuk.php');

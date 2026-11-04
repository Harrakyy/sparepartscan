<?php

/*
 * Form pembuat lisensi (laptop Ashira). Dijalankan oleh "Buat Lisensi.bat":
 *   php -S 127.0.0.1:8777 -t tools\buat-lisensi
 * Hanya melayani 127.0.0.1. Butuh storage/app/keys/secret.key di repo ini.
 */

if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403);
    exit('Hanya bisa dibuka dari laptop ini.');
}

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/LicenseForm.php';

$keys = $app->make(App\Services\License\Keys::class);
$historyFile = storage_path('app/keys/riwayat-lisensi.csv');
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $result = LicenseForm::handle($_POST, $keys, $historyFile);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$result['filename'].'"');
        echo $result['content'];
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buat Lisensi · Verifikasi Faktur</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6fb; color: #1d2433; margin: 0; padding: 32px 16px; }
        main { max-width: 640px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #c9d0de; border-radius: 8px; font: inherit; }
        input.mono { font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
        button { margin-top: 18px; background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 12px 18px; font: inherit; font-weight: 600; cursor: pointer; }
        .bad { background: #fde8e8; color: #9b1c1c; padding: 10px 12px; border-radius: 8px; }
        .muted { color: #5b6478; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td, th { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eef1f6; }
        td.mono { font-family: ui-monospace, Consolas, monospace; }
    </style>
</head>
<body>
<main>
    <form class="card" method="post">
        <h1>Buat lisensi</h1>
        <p class="muted">Untuk komputer master pelanggan. Lisensi berlaku selamanya dan hanya jalan di komputer dengan ID ini.</p>
        <?php if ($error): ?><p class="bad"><?= $e($error) ?></p><?php endif ?>
        <label for="id">ID komputer (dari halaman Aktivasi)</label>
        <input id="id" name="id" class="mono" value="<?= $e($_POST['id'] ?? '') ?>" maxlength="80" required autofocus>
        <label for="untuk">Untuk</label>
        <input id="untuk" name="untuk" value="<?= $e($_POST['untuk'] ?? '') ?>" maxlength="100" placeholder="PT Mitra Ghanim Indosarana — komputer master" required>
        <button type="submit">Buat &amp; unduh file lisensi</button>
    </form>

    <div class="card">
        <h2>Riwayat</h2>
        <?php $rows = LicenseForm::history($historyFile); ?>
        <?php if (! $rows): ?><p class="muted">Belum ada lisensi yang dibuat.</p><?php else: ?>
        <table>
            <tr><th>Waktu</th><th>Untuk</th><th>ID</th></tr>
            <?php foreach ($rows as [$when, $for, $id]): ?>
                <tr><td><?= $e(substr($when, 0, 16)) ?></td><td><?= $e($for) ?></td><td class="mono"><?= $e(substr($id, 0, 12)) ?>…</td></tr>
            <?php endforeach ?>
        </table>
        <?php endif ?>
    </div>
</main>
</body>
</html>

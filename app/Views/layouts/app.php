<!doctype html>
<html lang="<?= e(\App\Services\Locale::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?></title>
    <meta name="theme-color" content="#087f8c">
    <meta name="app-base" content="<?= e(rtrim(url(), '/') . '/') ?>">
    <link rel="manifest" href="<?= e(url('manifest?lang='.\App\Services\Locale::current())) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('icons/logo-apple-touch.png')) ?>">
    <link rel="icon" type="image/png" href="<?= e(url('icons/logo-32.png')) ?>">
    <?php
    $manifestPath = BASE_PATH . '/public/build/.vite/manifest.json';
    $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
    $entry = $manifest['resources/js/app.jsx'] ?? null;
    ?>
    <?php foreach ($entry['css'] ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= e(url('build/' . $css)) ?>">
    <?php endforeach; ?>
    <?php if ($entry): ?>
    <script type="module" src="<?= e(url('build/' . $entry['file'])) ?>"></script>
    <?php endif; ?>
</head>
<body><main><?= $content ?><?php if (!$entry): ?><p><?= e(__('Run')) ?> <code>npm install</code> <?= e(__('and')) ?> <code>npm run build</code> <?= e(__('to prepare the app.')) ?></p><?php endif; ?></main></body>
</html>

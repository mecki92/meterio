<?php
/** @var string $content @var string $title @var bool $bare @var string $active */
$user = $bare ? null : Auth::user();
$nav = [
    'dashboard' => ['dashboard', 'Dashboard'],
    'meters'    => ['meters', 'Zähler'],
    'contracts' => ['contracts', 'Verträge'],
];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> · <?= e(APP_BRAND) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.0.6/css/pico.min.css">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container bar">
        <a class="brand" href="<?= e(url($user ? 'dashboard' : 'login')) ?>"><span class="bolt">⚡</span> meterio <small>by MeckByte</small></a>
        <?php if ($user): ?>
            <input type="checkbox" id="navtoggle" class="navtoggle" aria-label="Menü">
            <label for="navtoggle" class="burger" aria-hidden="true">☰</label>
            <nav class="mainnav">
                <?php foreach ($nav as $key => [$path, $label]): ?>
                    <a href="<?= e(url($path)) ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/users')) ?>" class="<?= $active === 'admin' ? 'on' : '' ?>">Benutzer</a>
                <?php endif; ?>
                <a href="<?= e(url('profile')) ?>" class="<?= $active === 'profile' ? 'on' : '' ?>"><?= e($user['name']) ?></a>
                <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_field() ?><button class="linklike" type="submit">Abmelden</button></form>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container <?= $bare ? 'narrow' : '' ?>">
    <?php foreach (take_flash() as [$type, $msg]): ?>
        <div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">⚡ <?= e(APP_BRAND) ?> · Version <?= e(APP_VERSION) ?> · Daten werden verschlüsselt gespeichert</div>
</footer>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>

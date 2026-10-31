<?php
/** @var string $content @var string $title @var bool $bare @var string $active */
$user = $bare ? null : Auth::user();
$nav = [
    'dashboard' => ['dashboard', 'Dashboard', 'bi-speedometer2'],
    'meters'    => ['meters', 'Zähler', 'bi-lightning-charge'],
    'contracts' => ['contracts', 'Verträge', 'bi-file-earmark-text'],
];
$alertClass = ['ok' => 'success', 'warn' => 'warning', 'error' => 'danger'];
?><!doctype html>
<html lang="de" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e(APP_BRAND) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <script src="<?= e(asset('theme.js')) ?>"></script>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-md site-nav">
    <div class="container-xxl">
        <a class="navbar-brand fw-bold" href="<?= e(url($user ? 'dashboard' : 'login')) ?>">
            <i class="bi bi-lightning-charge-fill bolt"></i> meterio <small class="fw-normal opacity-75">by MeckByte</small>
        </a>
        <?php if ($user): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainnav" aria-controls="mainnav" aria-expanded="false" aria-label="Menü">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainnav">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    <?php foreach ($nav as $key => [$path, $label, $icon]): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link <?= $active === 'admin' ? 'active' : '' ?>" href="<?= e(url('admin/users')) ?>"><i class="bi bi-people"></i> Benutzer</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary theme-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Darstellung">
        <i class="bi bi-circle-half ico ico-auto"></i><i class="bi bi-sun-fill ico ico-light"></i><i class="bi bi-moon-stars-fill ico ico-dark"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" type="button" data-theme-set="auto"><i class="bi bi-circle-half"></i> Automatisch</button></li>
        <li><button class="dropdown-item" type="button" data-theme-set="light"><i class="bi bi-sun"></i> Hell</button></li>
        <li><button class="dropdown-item" type="button" data-theme-set="dark"><i class="bi bi-moon-stars"></i> Dunkel</button></li>
    </ul>
</div>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('profile')) ?>"><i class="bi bi-person-circle"></i> <?= e($user['name']) ?></a>
                    <form method="post" action="<?= e(url('logout')) ?>" class="m-0"><?= csrf_field() ?>
                        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-box-arrow-right"></i> Abmelden</button></form>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!$user): ?><div class="ms-auto"><div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary theme-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Darstellung">
        <i class="bi bi-circle-half ico ico-auto"></i><i class="bi bi-sun-fill ico ico-light"></i><i class="bi bi-moon-stars-fill ico ico-dark"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" type="button" data-theme-set="auto"><i class="bi bi-circle-half"></i> Automatisch</button></li>
        <li><button class="dropdown-item" type="button" data-theme-set="light"><i class="bi bi-sun"></i> Hell</button></li>
        <li><button class="dropdown-item" type="button" data-theme-set="dark"><i class="bi bi-moon-stars"></i> Dunkel</button></li>
    </ul>
</div></div><?php endif; ?>
    </div>
</nav>

<main class="container-xxl py-4 flex-grow-1 <?= $bare ? 'narrow' : '' ?>">
    <?php foreach (take_flash() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($alertClass[$type] ?? 'info') ?> alert-dismissible fade show" role="alert">
            <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer text-center text-body-secondary small py-3">
    <i class="bi bi-lightning-charge-fill bolt"></i> <?= e(APP_BRAND) ?> · Version <?= e(APP_VERSION) ?> · Daten werden verschlüsselt gespeichert
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('app.js')) ?>" defer></script>
</body>
</html>

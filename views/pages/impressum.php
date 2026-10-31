<?php /** @var array $legal */
$set = $legal['name'] !== '' && $legal['street'] !== '' && $legal['city'] !== ''; ?>
<div class="page-head">
    <div>
        <h1>Impressum</h1>
        <p class="sub">Angaben gemäß § 5 DDG</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('datenschutz')) ?>"><i class="bi bi-shield-check"></i> Datenschutzerklärung</a>
</div>
<?php if (!$set): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Die Anbieterangaben sind noch nicht hinterlegt. Bitte in <code>config.php</code> unter <code>legal</code> ergänzen.</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header"><i class="bi bi-person-vcard"></i> Anbieter</div>
        <div class="card-body">
            <p class="mb-0 fs-6">
                <b><?= e($legal['name'] ?: '[Name]') ?></b><br>
                <?= e($legal['street'] ?: '[Straße und Hausnummer]') ?><br>
                <?= e($legal['city'] ?: '[PLZ Ort]') ?>
            </p>
        </div>
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header"><i class="bi bi-envelope"></i> Kontakt</div>
        <div class="card-body">
            <dl class="cond-grid mb-0">
                <div><dt>E-Mail</dt><dd><?php if ($legal['email'] !== ''): ?><a href="mailto:<?= e($legal['email']) ?>"><?= e($legal['email']) ?></a><?php else: ?>[E-Mail]<?php endif; ?></dd></div>
                <?php if ($legal['phone'] !== ''): ?><div><dt>Telefon</dt><dd><?= e($legal['phone']) ?></dd></div><?php endif; ?>
            </dl>
        </div>
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header"><i class="bi bi-journal-text"></i> Verantwortlich für den Inhalt</div>
        <div class="card-body"><p class="mb-0"><?= e($legal['name'] ?: '[Name]') ?>, Anschrift wie oben.</p></div>
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header"><i class="bi bi-info-circle"></i> Hinweis</div>
        <div class="card-body"><p class="mb-0"><?= e(APP_BRAND) ?> ist eine nicht öffentliche Anwendung zur Verwaltung von Zählerständen und Stromverträgen. Der Zugang erfolgt nur mit einem vom Betreiber eingerichteten Benutzerkonto.</p></div>
    </div></div>
</div>

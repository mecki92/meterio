<?php /** @var array $u @var string $secret @var string $uri @var bool $emailSent @var bool $changing */ ?>
<h2 class="h3">Zwei-Faktor-Authentifizierung einrichten</h2>
<?php if (!$changing): ?>
    <p>Für dein Konto ist eine zweite Sicherheitsstufe verpflichtend. Wähle eine Methode und bestätige sie mit einem Code.</p>
<?php else: ?>
    <p>Aktuelle Methode: <b><?= $u['twofa_method'] === 'totp' ? 'Authenticator-App' : 'E-Mail-Code' ?></b>. Die Methode wechselt erst, nachdem du die neue bestätigt hast.</p>
<?php endif; ?>
<div class="row g-3">
    <div class="col-md-6"><div class="card h-100">
        <div class="card-header"><b>Option 1:</b> Authenticator-App (empfohlen)</div>
        <div class="card-body">
            <p class="small">Google Authenticator, 1Password, Bitwarden, Aegis u. a. Scanne den QR-Code und gib den angezeigten Code ein.</p>
            <div class="qrbox"><div id="qr" data-uri="<?= e($uri) ?>" aria-label="QR-Code"></div></div>
            <p class="small">Oder manuell eingeben:<br><code class="secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
            <form method="post" action="<?= e(url('2fa/totp/confirm')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Code aus der App</label>
                    <input name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required></div>
                <button type="submit" class="btn btn-primary">Authenticator aktivieren</button>
            </form>
        </div>
    </div></div>
    <div class="col-md-6"><div class="card h-100">
        <div class="card-header"><b>Option 2:</b> Code per E-Mail</div>
        <div class="card-body">
            <p class="small">Bei jeder Anmeldung senden wir einen 6-stelligen Code an <b><?= e($u['email']) ?></b>.</p>
            <?php if ($emailSent): ?>
                <form method="post" action="<?= e(url('2fa/email/confirm')) ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label">Code aus der E-Mail</label>
                        <input name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" required></div>
                    <button type="submit" class="btn btn-primary">E-Mail-Code aktivieren</button>
                </form>
                <form method="post" action="<?= e(url('2fa/email/send')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Code erneut senden</button></form>
            <?php else: ?>
                <form method="post" action="<?= e(url('2fa/email/send')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-primary">Testcode senden</button></form>
            <?php endif; ?>
        </div>
    </div></div>
</div>
<?php if ($changing): ?><p class="mt-3"><a href="<?= e(url('profile')) ?>">Abbrechen</a></p><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" defer></script>

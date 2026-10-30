<?php /** @var array $u @var string $secret @var string $uri @var bool $emailSent @var bool $changing */ ?>
<h2>Zwei-Faktor-Authentifizierung einrichten</h2>
<?php if (!$changing): ?>
    <p>Für dein Konto ist eine zweite Sicherheitsstufe verpflichtend. Wähle eine Methode und bestätige sie mit einem Code.</p>
<?php else: ?>
    <p>Aktuelle Methode: <b><?= $u['twofa_method'] === 'totp' ? 'Authenticator-App' : 'E-Mail-Code' ?></b>. Die Methode wechselt erst, nachdem du die neue bestätigt hast.</p>
<?php endif; ?>

<div class="grid">
    <article>
        <header><b>Option 1:</b> Authenticator-App (empfohlen)</header>
        <p class="small">Google Authenticator, 1Password, Bitwarden, Aegis u. a. Scanne den QR-Code und gib den angezeigten Code ein.</p>
        <div class="qrbox"><div id="qr" data-uri="<?= e($uri) ?>" aria-label="QR-Code"></div></div>
        <p class="small">Oder manuell eingeben:<br><code class="secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
        <form method="post" action="<?= e(url('2fa/totp/confirm')) ?>">
            <?= csrf_field() ?>
            <label>Code aus der App <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required></label>
            <button type="submit">Authenticator aktivieren</button>
        </form>
    </article>

    <article>
        <header><b>Option 2:</b> Code per E-Mail</header>
        <p class="small">Bei jeder Anmeldung senden wir einen 6-stelligen Code an <b><?= e($u['email']) ?></b>.</p>
        <?php if ($emailSent): ?>
            <form method="post" action="<?= e(url('2fa/email/confirm')) ?>">
                <?= csrf_field() ?>
                <label>Code aus der E-Mail <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" required></label>
                <button type="submit">E-Mail-Code aktivieren</button>
            </form>
            <form method="post" action="<?= e(url('2fa/email/send')) ?>"><?= csrf_field() ?><button class="secondary outline" type="submit">Code erneut senden</button></form>
        <?php else: ?>
            <form method="post" action="<?= e(url('2fa/email/send')) ?>"><?= csrf_field() ?><button type="submit">Testcode senden</button></form>
        <?php endif; ?>
    </article>
</div>
<?php if ($changing): ?><p><a href="<?= e(url('profile')) ?>">Abbrechen</a></p><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" defer></script>

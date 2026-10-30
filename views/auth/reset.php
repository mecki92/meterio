<article class="authcard">
    <header class="authhead"><div class="bolt big">⚡</div><h1><?= $invite ? 'Passwort festlegen' : 'Neues Passwort' ?></h1><p>meterio by MeckByte</p></header>
    <form method="post" action="<?= e(url(($invite ? 'invite/' : 'reset/') . $token)) ?>">
        <?= csrf_field() ?>
        <label>Neues Passwort <input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus></label>
        <label>Wiederholen <input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
        <small>Mindestens 10 Zeichen, mit Buchstaben und Ziffern.</small>
        <button type="submit">Speichern</button>
    </form>
</article>

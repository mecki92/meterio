<div class="page-head">
    <div><h1>Freigabecode einlösen</h1><p class="sub">Erhalte Zugriff auf den Zähler einer anderen Person</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('meters')) ?>"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>
<div class="row"><div class="col-lg-6">
    <div class="card"><div class="card-body">
        <p class="text-body-secondary">Gib den Code ein, den du von der Person bekommen hast, die ihren Zähler mit dir teilt. Danach siehst du den Zähler in deiner Zählerliste. <b>Verträge und Kosten bleiben privat</b> und werden nicht geteilt.</p>
        <form method="post" action="<?= e(url('shares/redeem')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="code">Freigabecode</label>
                <input id="code" name="code" class="form-control form-control-lg text-uppercase font-monospace" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="25" required autofocus autocapitalize="characters" spellcheck="false"></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Einlösen</button>
        </form>
    </div></div>
</div></div>
